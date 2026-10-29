
<?php
require_once __DIR__ . '/includes/functions.php';

$user = current_user();

if (!$user) {
    http_response_code(401);
    exit('Please log in to access this attachment.');
}

$attachmentId = (int) ($_GET['id'] ?? 0);

if ($attachmentId <= 0) {
    http_response_code(400);
    exit('Invalid attachment ID.');
}

$pdo = db();

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.ticket_id,
        a.original_name,
        a.stored_name,
        a.mime_type,
        a.file_size,
        t.user_id,
        t.assigned_to
    FROM ticket_attachments a
    INNER JOIN tickets t ON t.id = a.ticket_id
    WHERE a.id = ?
    LIMIT 1
");

$stmt->execute([$attachmentId]);
$attachment = $stmt->fetch();

if (!$attachment) {
    http_response_code(404);
    exit('Attachment not found.');
}

// Check permission according to user role.
$role = $user['role'] ?? '';
$userId = (int) $user['id'];

$authorized = false;

if ($role === 'admin') {
    // Admin can access all ticket attachments.
    $authorized = true;

} elseif ($role === 'client') {
    // Client can access attachments only from their own tickets.
    $authorized = (int) $attachment['user_id'] === $userId;

} elseif ($role === 'staff') {
    // Staff can access attachments only from tickets assigned to them.
    $authorized = (int) $attachment['assigned_to'] === $userId;
}

if (!$authorized) {
    http_response_code(403);
    exit('You do not have permission to access this attachment.');
}

// Build the physical path using the server-generated stored filename.
$uploadDirectory = __DIR__ . '/uploads/tickets/';
$storedName = basename($attachment['stored_name']);
$filePath = $uploadDirectory . $storedName;

if (!is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    exit('The attachment file could not be found.');
}

// Allow only the MIME types used by the upload feature.
$allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'application/pdf',
    'text/plain'
];

if (!in_array($attachment['mime_type'], $allowedMimeTypes, true)) {
    http_response_code(403);
    exit('This file type is not allowed.');
}

$originalName = basename($attachment['original_name']);
$originalName = preg_replace('/[\r\n"]/', '_', $originalName);

header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . filesize($filePath));
header(
    "Content-Disposition: attachment; filename=\""
    . addcslashes($originalName, "\\")
    . "\""
);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($filePath);
exit;


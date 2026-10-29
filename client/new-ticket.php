
<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('client');

$pageHeading = 'Create New Ticket';
$pageTitle = 'New Ticket | SupportHub';
$error = '';
$selectedCategory = trim($_POST['category'] ?? '');
$selectedPriority = $_POST['priority'] ?? 'Medium';
$description = trim($_POST['issue_description'] ?? '');
$categories = [
    'Hardware' => '▣',
    'Software' => '◈',
    'Network' => '⌁',
    'Printer' => '▤',
    'Account / Access' => '♙',
    'Other' => '•••'
];

// File upload settings
$maxFiles = 5;
$maxFileSize = 5 * 1024 * 1024;
$allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'txt'];
$allowedMimeTypes = [
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'pdf'  => ['application/pdf'],
    'txt'  => ['text/plain']
];

$uploadDirectory = __DIR__ . '/../uploads/tickets/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = trim($_POST['category'] ?? '');
    $priority = $_POST['priority'] ?? '';
    $description = trim($_POST['issue_description'] ?? '');
    $allowedPriority = ['Low', 'Medium', 'High', 'Critical'];

    $files = $_FILES['attachments'] ?? null;
    $validFiles = [];

    if ($csrfError = post_error_for_invalid_csrf()) {
        $error = $csrfError;
    } elseif (
        $category === '' ||
        $description === '' ||
        !array_key_exists($category, $categories) ||
        !in_array($priority, $allowedPriority, true)
    ) {
        $error = 'Please complete all required fields.';
    } elseif (mb_strlen($description) > 5000) {
        $error = 'Issue details must be 5,000 characters or fewer.';
    } else {
        // Validate uploaded files
        if ($files !== null && isset($files['name']) && is_array($files['name'])) {
            $totalFiles = count($files['name']);

            if ($totalFiles > $maxFiles) {
                $error = 'You can upload a maximum of 5 files.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);

                for ($i = 0; $i < $totalFiles; $i++) {
                    $originalName = $files['name'][$i] ?? '';
                    $tmpName = $files['tmp_name'][$i] ?? '';
                    $fileSize = (int) ($files['size'][$i] ?? 0);
                    $uploadError = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;

                    // Ignore empty file input fields
                    if ($uploadError === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }

                    if ($uploadError !== UPLOAD_ERR_OK) {
                        $error = 'A file could not be uploaded. Please try again.';
                        break;
                    }

                    if (!is_uploaded_file($tmpName)) {
                        $error = 'Invalid file upload detected.';
                        break;
                    }

                    if ($fileSize <= 0 || $fileSize > $maxFileSize) {
                        $error = 'Each file must be smaller than or equal to 5 MB.';
                        break;
                    }

                    // Check file extension
                    $extension = strtolower(
                        pathinfo(basename($originalName), PATHINFO_EXTENSION)
                    );

                    if (!in_array($extension, $allowedExtensions, true)) {
                        $error = 'Only JPG, JPEG, PNG, PDF and TXT files are allowed.';
                        break;
                    }

                    // Check actual file MIME type
                    $mimeType = $finfo->file($tmpName);

                    if (
                        $mimeType === false ||
                        !in_array($mimeType, $allowedMimeTypes[$extension], true)
                    ) {
                        $error = 'A file has an invalid file type.';
                        break;
                    }

                    $validFiles[] = [
                        'original_name' => basename($originalName),
                        'tmp_name' => $tmpName,
                        'extension' => $extension,
                        'mime_type' => $mimeType,
                        'file_size' => $fileSize
                    ];
                }
            }
        }

        if ($error === '') {
            $pdo = db();
            $storedPaths = [];
            $ticketId = null;

            try {
                if (!is_dir($uploadDirectory)) {
                    throw new RuntimeException(
                        'The upload folder does not exist. Please create uploads/tickets.'
                    );
                }

                if (!is_writable($uploadDirectory)) {
                    throw new RuntimeException(
                        'The upload folder is not writable.'
                    );
                }

                $pdo->beginTransaction();

                // Create ticket
                $code = generate_ticket_code();

                $stmt = $pdo->prepare(
                    "INSERT INTO tickets
                    (ticket_code, user_id, issue_description, category, priority, status)
                    VALUES (?, ?, ?, ?, ?, 'Open')"
                );

                $stmt->execute([
                    $code,
                    current_user()['id'],
                    $description,
                    $category,
                    $priority
                ]);

                $ticketId = (int) $pdo->lastInsertId();

                // Save attachment files and database records
                if (!empty($validFiles)) {
                    $attachmentStmt = $pdo->prepare(
                        "INSERT INTO ticket_attachments
                        (ticket_id, original_name, stored_name, mime_type, file_size)
                        VALUES (?, ?, ?, ?, ?)"
                    );

                    foreach ($validFiles as $file) {
                        $storedName = bin2hex(random_bytes(24))
                            . '.' . $file['extension'];

                        $destination = $uploadDirectory . $storedName;

                        if (!move_uploaded_file(
                            $file['tmp_name'],
                            $destination
                        )) {
                            throw new RuntimeException(
                                'A file could not be saved. Please try again.'
                            );
                        }

                        $storedPaths[] = $destination;

                        $attachmentStmt->execute([
                            $ticketId,
                            $file['original_name'],
                            $storedName,
                            $file['mime_type'],
                            $file['file_size']
                        ]);
                    }
                }

                $pdo->commit();

                header(
                    'Location: /it-support-system/client/ticket.php?id='
                    . $ticketId . '&created=1'
                );
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                // Remove files if the ticket or attachment save fails
                foreach ($storedPaths as $storedPath) {
                    if (is_file($storedPath)) {
                        unlink($storedPath);
                    }
                }

                error_log('Ticket creation error: ' . $e->getMessage());

                $error = 'Unable to create the ticket or save its attachments. Please check the upload folder and database configuration, then try again.';
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="ticket-wizard">
    <div class="wizard-progress">
        <div class="wizard-step active">
            <span class="wizard-step-number">1</span>
            <div>
                <strong>Request details</strong>
                <span>Tell us what you need</span>
            </div>
        </div>

        <div class="wizard-step">
            <span class="wizard-step-number">2</span>
            <div>
                <strong>Review</strong>
                <span>Check your request</span>
            </div>
        </div>

        <div class="wizard-step">
            <span class="wizard-step-number">3</span>
            <div>
                <strong>Submit</strong>
                <span>Send to IT support</span>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="new-ticket-grid">
            <section class="card wizard-panel">
                <span class="eyebrow">STEP 1 · DETAILS</span>

                <h2>What can we help you with?</h2>

                <p>
                    Choose the support area that best matches your issue.
                </p>

                <div class="form-group">
                    <label>Issue Category *</label>

                    <div class="choice-grid" data-choice-group>
                        <?php foreach ($categories as $name => $icon): ?>
                            <label class="choice-card <?= $selectedCategory === $name ? 'selected' : '' ?>">
                                <input
                                    type="radio"
                                    name="category"
                                    value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $selectedCategory === $name ? 'checked' : '' ?>
                                    required
                                >

                                <span class="choice-icon"><?= $icon ?></span>

                                <strong>
                                    <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group" style="margin-top:20px">
                    <label>Describe the issue *</label>

                    <textarea
                        class="textarea"
                        name="issue_description"
                        maxlength="5000"
                        placeholder="Please provide as much detail as possible. Include error messages, device names or what happened before the issue started..."
                        required
                    ><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>

                    <div class="issue-helper">
                        <span>
                            Clear details help the support team resolve requests faster.
                        </span>
                        <span>Required</span>
                    </div>
                </div>

                <!-- File attachment section -->
                <div class="form-group" style="margin-top:20px">
                    <label for="attachments">Attach Files (Optional)</label>

                    <input
                        type="file"
                        id="attachments"
                        name="attachments[]"
                        multiple
                        accept=".jpg,.jpeg,.png,.pdf,.txt"
                    >

                    <small style="display:block;margin-top:8px;color:#75869e">
                        You can attach up to 5 files. Allowed formats:
                        JPG, JPEG, PNG, PDF and TXT. Maximum 5 MB per file.
                    </small>

                    <div
                        id="attachment-error"
                        role="alert"
                        style="display:none;color:#dc2626;margin-top:8px"
                    ></div>
                </div>
            </section>

            <aside class="card wizard-panel">
                <span class="eyebrow">STEP 2 · PRIORITY</span>

                <h2>How urgent is it?</h2>

                <p>
                    Select the level that best describes the impact on your work.
                </p>

                <div class="form-group">
                    <label>Priority Level *</label>

                    <div class="priority-choice-grid" data-choice-group>
                        <?php foreach (['Low', 'Medium', 'High', 'Critical'] as $priority): ?>
                            <label class="choice-card priority-choice priority-<?= strtolower($priority) ?> <?= $selectedPriority === $priority ? 'selected' : '' ?>">
                                <input
                                    type="radio"
                                    name="priority"
                                    value="<?= $priority ?>"
                                    <?= $selectedPriority === $priority ? 'checked' : '' ?>
                                    required
                                >

                                <span class="choice-icon">
                                    <?= $priority === 'Critical'
                                        ? '!'
                                        : ($priority === 'High'
                                            ? '↑'
                                            : ($priority === 'Medium' ? '•' : '↓')) ?>
                                </span>

                                <strong><?= $priority ?></strong>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="margin-top:22px;padding:14px;border:1px solid #e6edf6;border-radius:13px;background:#f8fafc">
                    <strong style="font-size:10px;display:block">
                        Before you submit
                    </strong>

                    <ul style="margin:8px 0 0;padding-left:17px;color:#75869e;font-size:9px;line-height:1.75">
                        <li>Describe the problem clearly.</li>
                        <li>Choose the correct urgency.</li>
                        <li>Attach screenshots or documents if needed.</li>
                        <li>Keep your ticket ID for future reference.</li>
                    </ul>
                </div>

                <div class="ticket-form-footer">
                    <a
                        class="btn btn-secondary"
                        href="/it-support-system/client/dashboard.php"
                    >
                        Cancel
                    </a>

                    <button class="btn btn-primary" type="submit">
                        Submit Ticket <span>→</span>
                    </button>
                </div>
            </aside>
        </div>
    </form>
</div>

<script>
document.getElementById('attachments').addEventListener('change', function () {
    const errorBox = document.getElementById('attachment-error');
    const files = Array.from(this.files);
    const allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'txt'];
    const maxFileSize = 5 * 1024 * 1024;

    errorBox.style.display = 'none';
    errorBox.textContent = '';

    if (files.length > 5) {
        errorBox.textContent = 'You can select a maximum of 5 files.';
        errorBox.style.display = 'block';
        this.value = '';
        return;
    }

    for (const file of files) {
        const extension = file.name.split('.').pop().toLowerCase();

        if (!allowedExtensions.includes(extension)) {
            errorBox.textContent =
                'Only JPG, JPEG, PNG, PDF and TXT files are allowed.';
            errorBox.style.display = 'block';
            this.value = '';
            return;
        }

        if (file.size <= 0 || file.size > maxFileSize) {
            errorBox.textContent =
                'Each file must be smaller than or equal to 5 MB.';
            errorBox.style.display = 'block';
            this.value = '';
            return;
        }
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


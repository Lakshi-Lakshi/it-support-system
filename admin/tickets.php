
<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$pdo = db();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $staffId = (int)($_POST['staff_id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT t.*, u.name AS client_name
        FROM tickets t
        JOIN users u ON u.id = t.user_id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT id, name
        FROM users
        WHERE id = ? AND role = 'staff'
    ");
    $stmt->execute([$staffId]);
    $staff = $stmt->fetch();

    if ($csrfError = post_error_for_invalid_csrf()) {
        $error = $csrfError;
    } elseif (!$ticket || !$staff) {
        $error = 'Invalid ticket or staff selection.';
    } else {
        $stmt = $pdo->prepare("
            UPDATE tickets
            SET assigned_to = ?, status = 'Assigned'
            WHERE id = ?
        ");
        $stmt->execute([$staffId, $ticketId]);

        create_notification(
            $staffId,
            $ticketId,
            'Ticket Assigned',
            'Ticket ' . $ticket['ticket_code'] . ' has been assigned to you.'
        );

        create_notification(
            (int)current_user()['id'],
            $ticketId,
            'Ticket Assigned',
            'Ticket ' . $ticket['ticket_code'] . ' was assigned to ' . $staff['name'] . '.'
        );

        $success = 'Ticket ' . $ticket['ticket_code'] . ' assigned to ' . $staff['name'] . '.';
    }
}

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$priority = trim($_GET['priority'] ?? '');
$category = trim($_GET['category'] ?? '');

$sql = "
    SELECT
        t.*,
        u.name AS client_name,
        s.name AS staff_name
    FROM tickets t
    JOIN users u ON u.id = t.user_id
    LEFT JOIN users s ON s.id = t.assigned_to
    WHERE 1 = 1
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            t.ticket_code LIKE ?
            OR u.name LIKE ?
            OR t.category LIKE ?
            OR t.issue_description LIKE ?
        )
    ";

    $term = "%{$search}%";
    array_push($params, $term, $term, $term, $term);
}

if ($status !== '') {
    $sql .= " AND t.status = ?";
    $params[] = $status;
}

if ($priority !== '') {
    $sql .= " AND t.priority = ?";
    $params[] = $priority;
}

if ($category !== '') {
    $sql .= " AND t.category = ?";
    $params[] = $category;
}

$sql .= " ORDER BY t.updated_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

/*
 * Fetch attachments for the tickets shown in the admin list.
 * Admins can access attachments for all tickets.
 */

$attachmentsByTicket = [];

if (!empty($tickets)) {
    $ticketIds = array_map('intval', array_column($tickets, 'id'));
    $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));

    $attachmentStmt = $pdo->prepare("
        SELECT
            a.id,
            a.ticket_id,
            a.original_name,
            a.file_size
        FROM ticket_attachments a
        WHERE a.ticket_id IN ($placeholders)
        ORDER BY a.created_at DESC
    ");

    $attachmentStmt->execute($ticketIds);
    $allAttachments = $attachmentStmt->fetchAll();

    foreach ($allAttachments as $attachment) {
        $attachmentsByTicket[(int)$attachment['ticket_id']][] = $attachment;
    }
}

$staffList = $pdo->query("
    SELECT id, name
    FROM users
    WHERE role = 'staff'
    ORDER BY name
")->fetchAll();

$categoryList = $pdo->query("
    SELECT DISTINCT category
    FROM tickets
    ORDER BY category
")->fetchAll(PDO::FETCH_COLUMN);

$pageHeading = 'All Tickets';
$pageTitle = 'All Tickets | SupportHub';

include __DIR__ . '/../includes/header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-error">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<div class="dashboard-hero" style="margin-bottom:14px">

    <div>
        <span class="eyebrow">TICKET OPERATIONS</span>
        <h2>All Support Tickets</h2>
        <p>View, search, filter, download attachments and assign every support request in the system.</p>
    </div>

    <div class="quick-action">
        <span style="font-size:9px;color:#8a96a8">
            <?= count($tickets) ?> matching tickets
        </span>

        <a class="btn btn-secondary" href="/it-support-system/admin/users.php">
            Manage Users
        </a>
    </div>

</div>

<div class="card filter-card">

    <form method="get" class="filter-toolbar" style="flex-wrap:wrap">

        <div class="filter-search">
            <input
                class="input"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="Search ticket, client, category or issue..."
            >
        </div>

        <select class="select" name="category">
            <option value="">All Categories</option>

            <?php foreach ($categoryList as $c): ?>
                <option
                    value="<?= htmlspecialchars($c) ?>"
                    <?= $category === $c ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($c) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select class="select" name="priority">
            <option value="">All Priorities</option>

            <?php foreach (['Critical', 'High', 'Medium', 'Low'] as $p): ?>
                <option
                    value="<?= htmlspecialchars($p) ?>"
                    <?= $priority === $p ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($p) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select class="select" name="status">
            <option value="">All Statuses</option>

            <?php foreach (['Open', 'Assigned', 'In Progress', 'Resolved', 'Closed'] as $s): ?>
                <option
                    value="<?= htmlspecialchars($s) ?>"
                    <?= $status === $s ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($s) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-primary">Filter</button>

        <?php if ($search !== '' || $status !== '' || $priority !== '' || $category !== ''): ?>
            <a class="btn btn-secondary" href="/it-support-system/admin/tickets.php">
                Reset
            </a>
        <?php endif; ?>

    </form>

</div>

<div class="card" style="margin-top:13px;padding:13px">

    <div class="table-wrap">

        <table class="table">

            <thead>
                <tr>
                    <th>#</th>
                    <th>Ticket</th>
                    <th>Client</th>
                    <th>Category</th>
                    <th>Attachments</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th style="min-width:250px">Assignment</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($tickets as $index => $t): ?>

                <tr>

                    <td style="color:#9aa7b8">
                        #<?= $index + 1 ?>
                    </td>

                    <td>
                        <strong>
                            <?= htmlspecialchars($t['ticket_code']) ?>
                        </strong>

                        <small style="display:block;color:#94a3b8;font-size:8px;margin-top:3px">
                            <?= date('d M Y', strtotime($t['created_at'])) ?>
                        </small>
                    </td>

                    <td>
                        <?= htmlspecialchars($t['client_name']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($t['category']) ?>
                    </td>

                    <td style="min-width:180px">

                        <?php $ticketAttachments = $attachmentsByTicket[(int)$t['id']] ?? []; ?>

                        <?php if (!empty($ticketAttachments)): ?>

                            <div style="display:flex;flex-direction:column;gap:10px">

                                <?php foreach ($ticketAttachments as $attachment): ?>

                                    <div>

                                        <div style="font-size:12px;margin-bottom:4px;overflow-wrap:anywhere">
                                            📎
                                            <?= htmlspecialchars($attachment['original_name']) ?>
                                        </div>

                                        <small style="display:block;margin-bottom:5px;color:#64748b">
                                            <?= number_format($attachment['file_size'] / 1024, 1) ?> KB
                                        </small>

                                        <a
                                            class="btn btn-secondary"
                                            href="/it-support-system/download_attachment.php?id=<?= (int)$attachment['id'] ?>"
                                        >
                                            Download
                                        </a>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <span style="color:#64748b;font-size:12px">
                                No attachments
                            </span>

                        <?php endif; ?>

                    </td>

                    <td class="priority-<?= htmlspecialchars(strtolower($t['priority'])) ?>">
                        <?= htmlspecialchars($t['priority']) ?>
                    </td>

                    <td>
                        <span class="status <?= htmlspecialchars(status_class($t['status'])) ?>">
                            <?= htmlspecialchars($t['status']) ?>
                        </span>
                    </td>

                    <td>
                        <?= htmlspecialchars($t['staff_name'] ?? 'Unassigned') ?>
                    </td>

                    <td>

                        <form method="post" style="display:flex;gap:6px;min-width:235px">

                            <?= csrf_field() ?>

                            <input type="hidden" name="action" value="assign">

                            <input
                                type="hidden"
                                name="ticket_id"
                                value="<?= (int)$t['id'] ?>"
                            >

                            <select class="select" name="staff_id" required>

                                <option value="">Select staff</option>

                                <?php foreach ($staffList as $s): ?>

                                    <option
                                        value="<?= (int)$s['id'] ?>"
                                        <?= (int)$t['assigned_to'] === (int)$s['id'] ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($s['name']) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <button
                                class="btn btn-primary"
                                type="submit"
                                title="Assign or reassign"
                            >
                                Assign
                            </button>

                        </form>

                    </td>

                </tr>

            <?php endforeach; ?>

            <?php if (!$tickets): ?>

                <tr>
                    <td colspan="9">

                        <div class="empty-state">

                            <div class="empty-state-icon">⌕</div>

                            <strong style="display:block;font-size:12px;color:#334155">
                                No tickets found
                            </strong>

                            <span style="display:block;margin-top:4px;font-size:9px">
                                Adjust the filters and try again.
                            </span>

                        </div>

                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>


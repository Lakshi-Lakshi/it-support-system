
<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('staff');

$pdo = db();
$staffId = current_user()['id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_ticket') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? '');
    $comment = trim($_POST['comment'] ?? '');
    $allowed = ['Assigned', 'In Progress', 'Resolved', 'Closed'];

    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id=? AND assigned_to=?");
    $stmt->execute([$ticketId, $staffId]);
    $ticket = $stmt->fetch();

    if ($csrfError = post_error_for_invalid_csrf()) {
        $error = $csrfError;
    } elseif (!$ticket) {
        $error = 'This ticket is not assigned to your account.';
    } elseif (!in_array($newStatus, $allowed, true)) {
        $error = 'Invalid status selected.';
    } elseif ($comment === '' && $newStatus === $ticket['status']) {
        $error = 'Add a progress note or choose a new status.';
    } elseif (mb_strlen($comment) > 5000) {
        $error = 'Progress notes must be 5,000 characters or fewer.';
    } else {
        $oldStatus = $ticket['status'];

        $stmt = $pdo->prepare("UPDATE tickets SET status=? WHERE id=? AND assigned_to=?");
        $stmt->execute([$newStatus, $ticketId, $staffId]);

        if ($stmt->rowCount() === 0 && $oldStatus !== $newStatus) {
            $error = 'The ticket could not be updated. Please refresh and try again.';
        } else {
            if ($oldStatus !== $newStatus) {
                create_notification(
                    (int)$ticket['user_id'],
                    $ticketId,
                    'Status Changed',
                    'Your ticket ' . $ticket['ticket_code'] . ' status changed from ' . $oldStatus . ' to ' . $newStatus . '.'
                );

                $admins = $pdo->query("SELECT id FROM users WHERE role='admin'")->fetchAll();

                foreach ($admins as $adminUser) {
                    create_notification(
                        (int)$adminUser['id'],
                        $ticketId,
                        'Status Changed',
                        'Ticket ' . $ticket['ticket_code'] . ' was changed from ' . $oldStatus . ' to ' . $newStatus . ' by ' . current_user()['name'] . '.'
                    );
                }
            }

            if ($comment !== '') {
                $stmt = $pdo->prepare(
                    "INSERT INTO ticket_comments(ticket_id,user_id,comment) VALUES(?,?,?)"
                );
                $stmt->execute([$ticketId, $staffId, $comment]);

                create_notification(
                    (int)$ticket['user_id'],
                    $ticketId,
                    'Resolution Note Added',
                    'A new update was added to ticket ' . $ticket['ticket_code'] . '.'
                );
            }

            header('Location: /it-support-system/staff/tickets.php?updated=1&ticket=' . $ticketId);
            exit;
        }
    }
}

if (isset($_GET['updated'])) {
    $success = 'Ticket updated successfully. Relevant users have been notified.';
}

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT t.*, u.name AS client_name
    FROM tickets t
    JOIN users u ON u.id=t.user_id
    WHERE t.assigned_to=?
";

$params = [$staffId];

if ($search !== '') {
    $sql .= " AND (t.ticket_code LIKE ? OR u.name LIKE ? OR t.issue_description LIKE ? OR t.category LIKE ?)";
    $term = "%{$search}%";
    array_push($params, $term, $term, $term, $term);
}

if ($statusFilter !== '') {
    $sql .= " AND t.status=?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY t.updated_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

/*
 * Fetch attachments for all tickets assigned to this staff member.
 * Only attachments belonging to these assigned tickets are displayed.
 */

$attachmentsByTicket = [];

if (!empty($tickets)) {
    $ticketIds = array_map('intval', array_column($tickets, 'id'));
    $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));

    $attachmentStmt = $pdo->prepare("
        SELECT a.id, a.ticket_id, a.original_name, a.file_size
        FROM ticket_attachments a
        INNER JOIN tickets t ON t.id = a.ticket_id
        WHERE t.assigned_to = ?
          AND a.ticket_id IN ($placeholders)
        ORDER BY a.created_at DESC
    ");

    $attachmentStmt->execute(array_merge([$staffId], $ticketIds));
    $allAttachments = $attachmentStmt->fetchAll();

    foreach ($allAttachments as $attachment) {
        $attachmentsByTicket[(int)$attachment['ticket_id']][] = $attachment;
    }
}

$pageHeading = 'Assigned Tickets';
$pageTitle = 'Assigned Tickets | SupportHub';

include __DIR__ . '/../includes/header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="dashboard-hero" style="margin-bottom:14px">
    <div>
        <span class="eyebrow">WORK QUEUE</span>
        <h2>Assigned Tickets</h2>
        <p>Update ticket status, view customer attachments and add progress or resolution notes.</p>
    </div>
    <div class="dashboard-date">
        <strong><?= count($tickets) ?> tickets</strong>
        assigned to you
    </div>
</div>

<div class="card filter-card" style="margin-bottom:13px">
    <form method="get" class="filter-toolbar">
        <div class="filter-search">
            <input
                class="input"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="Search assigned ticket, client or issue..."
            >
        </div>

        <select class="select" name="status">
            <option value="">All Statuses</option>
            <?php foreach (['Assigned', 'In Progress', 'Resolved', 'Closed'] as $st): ?>
                <option value="<?= htmlspecialchars($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>>
                    <?= htmlspecialchars($st) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-primary">Filter</button>

        <?php if ($search !== '' || $statusFilter !== ''): ?>
            <a class="btn btn-secondary" href="/it-support-system/staff/tickets.php">Reset</a>
        <?php endif; ?>
    </form>
</div>

<div class="card" style="padding:13px">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Ticket</th>
                    <th>Client</th>
                    <th>Issue</th>
                    <th>Attachments</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th style="min-width:340px">Update Ticket</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($tickets as $t): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($t['ticket_code']) ?></strong>
                    </td>

                    <td><?= htmlspecialchars($t['client_name']) ?></td>

                    <td style="max-width:210px">
                        <?= htmlspecialchars(mb_strimwidth($t['issue_description'], 0, 60, '...')) ?>
                    </td>

                    <td style="min-width:180px">
                        <?php $ticketAttachments = $attachmentsByTicket[(int)$t['id']] ?? []; ?>

                        <?php if (!empty($ticketAttachments)): ?>
                            <div style="display:flex;flex-direction:column;gap:8px">
                                <?php foreach ($ticketAttachments as $attachment): ?>
                                    <div>
                                        <div style="font-size:12px;margin-bottom:4px;overflow-wrap:anywhere">
                                            📎 <?= htmlspecialchars($attachment['original_name']) ?>
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
                            <span style="color:#64748b;font-size:12px">No attachments</span>
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
                        <form method="post" style="min-width:320px">
                            <?= csrf_field() ?>

                            <input type="hidden" name="action" value="update_ticket">
                            <input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">

                            <select class="select" name="status" required style="margin-bottom:7px">
                                <?php foreach (['Assigned', 'In Progress', 'Resolved', 'Closed'] as $st): ?>
                                    <option value="<?= htmlspecialchars($st) ?>" <?= $t['status'] === $st ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($st) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <textarea
                                class="textarea"
                                name="comment"
                                maxlength="5000"
                                placeholder="Progress / resolution note..."
                                style="min-height:70px;margin-bottom:7px"
                            ></textarea>

                            <button class="btn btn-primary" type="submit">Save Update</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$tickets): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="empty-state-icon">✓</div>
                            <strong style="display:block;font-size:12px;color:#334155">
                                No tickets are assigned to you
                            </strong>
                            <span style="display:block;margin-top:4px;font-size:9px">
                                Ask an administrator to assign a ticket from All Tickets.
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


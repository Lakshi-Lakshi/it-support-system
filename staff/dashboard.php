<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('staff');

$pageHeading = 'Support Staff Dashboard';
$pageTitle = 'Staff Dashboard | SupportHub';
$pdo = db();
$staffId = current_user()['id'];

// GET PREVIOUS LOGIN TIME
$stmt = $pdo->prepare("
    SELECT previous_login
    FROM users
    WHERE id = ?
");
$stmt->execute([$staffId]);
$previousLogin = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to=?"); $stmt->execute([$staffId]); $total=(int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to=? AND status IN ('Assigned','In Progress')"); $stmt->execute([$staffId]); $active=(int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to=? AND status='Resolved'"); $stmt->execute([$staffId]); $resolved=(int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to=? AND status='Closed'"); $stmt->execute([$staffId]); $closed=(int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT t.*, u.name AS client_name FROM tickets t JOIN users u ON u.id=t.user_id WHERE t.assigned_to=? ORDER BY t.updated_at DESC LIMIT 8");
$stmt->execute([$staffId]); $tickets=$stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-hero">
    <div><span class="eyebrow">SUPPORT WORKSPACE</span><h2>Good afternoon, <?= htmlspecialchars(explode(' ', trim(current_user()['name']))[0]) ?>. 👋</h2><p>Here are the support requests currently assigned to you.</p></div>

    <div class="dashboard-date">
        <strong>Previous Login</strong>
        <?= $previousLogin
            ? date('d M Y, h:i A', strtotime($previousLogin))
            : 'First Login'
        ?>
    </div>
</div>

<div class="stat-grid-four">
    <div class="card stat-card"><span class="stat-label">Assigned to Me</span><div class="stat-number"><?= $total ?></div><span class="stat-foot">Total linked tickets</span><div class="stat-icon">▤</div></div>
    <div class="card stat-card"><span class="stat-label">Active</span><div class="stat-number"><?= $active ?></div><span class="stat-foot">Assigned or in progress</span><div class="stat-icon violet">◷</div></div>
    <div class="card stat-card"><span class="stat-label">Resolved</span><div class="stat-number"><?= $resolved ?></div><span class="stat-foot">Ready for closure</span><div class="stat-icon green">✓</div></div>
    <div class="card stat-card"><span class="stat-label">Closed</span><div class="stat-number"><?= $closed ?></div><span class="stat-foot">Completed requests</span><div class="stat-icon orange">●</div></div>
</div>

<div class="dashboard-section">
    <div class="dashboard-section-head"><div><h2>My Assigned Tickets</h2><p>Open a ticket to update its status and add progress notes.</p></div><a class="btn btn-primary" href="/it-support-system/staff/tickets.php">Manage Assigned Tickets</a></div>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Ticket ID</th><th>Client</th><th>Issue</th><th>Priority</th><th>Status</th><th>Last Updated</th></tr></thead>
                <tbody>
                <?php foreach ($tickets as $ticket): ?>
                    <tr><td><strong><?= htmlspecialchars($ticket['ticket_code']) ?></strong></td><td><?= htmlspecialchars($ticket['client_name']) ?></td><td><?= htmlspecialchars(mb_strimwidth($ticket['issue_description'],0,46,'...')) ?></td><td class="priority-<?= strtolower($ticket['priority']) ?>"><?= htmlspecialchars($ticket['priority']) ?></td><td><span class="status <?= status_class($ticket['status']) ?>"><?= htmlspecialchars($ticket['status']) ?></span></td><td><?= date('d M Y, h:i A', strtotime($ticket['updated_at'])) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$tickets): ?><tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">✓</div><strong style="display:block;font-size:12px;color:#334155">No assigned tickets</strong><span style="display:block;margin-top:4px;font-size:9px">New assignments from the administrator will appear here.</span></div></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

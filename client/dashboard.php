<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('client');

$pageHeading = 'Client Dashboard';
$pageTitle = 'Client Dashboard | SupportHub';
$pdo = db();
$userId = (int)current_user()['id'];

// Get Previous Login Time
$stmt = $pdo->prepare("
    SELECT previous_login
    FROM users
    WHERE id = ?
");
$stmt->execute([$userId]);
$previousLogin = $stmt->fetchColumn();

// Ticket Statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id=?");
$stmt->execute([$userId]);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id=? AND status='Open'");
$stmt->execute([$userId]);
$open = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id=? AND status='In Progress'");
$stmt->execute([$userId]);
$progress = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id=? AND status IN ('Resolved','Closed')");
$stmt->execute([$userId]);
$resolved = (int)$stmt->fetchColumn();

// Recent Tickets
$stmt = $pdo->prepare("SELECT * FROM tickets WHERE user_id=? ORDER BY updated_at DESC LIMIT 6");
$stmt->execute([$userId]);
$tickets = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-hero">
    <div>
        <span class="eyebrow">CLIENT WORKSPACE</span>
        <h2>Good afternoon, <?= htmlspecialchars(explode(' ', trim(current_user()['name']))[0]) ?>. 👋</h2>
        <p>Track your support requests and get help from the IT team.</p>
    </div>

    <div class="quick-action">
        <div class="dashboard-date">
            <strong>Previous Login</strong>
            <?= $previousLogin
                ? date('d M Y, h:i A', strtotime($previousLogin))
                : 'First Login'
            ?>
        </div>

        <a class="btn btn-primary" href="/it-support-system/client/new-ticket.php">
            ＋ Create New Ticket
        </a>
    </div>
</div>

<div class="stat-grid-four">

    <div class="card stat-card">
        <span class="stat-label">Total Requests</span>
        <div class="stat-number"><?= $total ?></div>
        <span class="stat-foot">All submitted tickets</span>
        <div class="stat-icon">▤</div>
    </div>

    <div class="card stat-card">
        <span class="stat-label">Open</span>
        <div class="stat-number"><?= $open ?></div>
        <span class="stat-foot">Waiting for assignment</span>
        <div class="stat-icon violet">◷</div>
    </div>

    <div class="card stat-card">
        <span class="stat-label">In Progress</span>
        <div class="stat-number"><?= $progress ?></div>
        <span class="stat-foot">Currently being handled</span>
        <div class="stat-icon">⚙</div>
    </div>

    <div class="card stat-card">
        <span class="stat-label">Resolved</span>
        <div class="stat-number"><?= $resolved ?></div>
        <span class="stat-foot">Resolved or closed</span>
        <div class="stat-icon green">✓</div>
    </div>

</div>

<div class="dashboard-section">

    <div class="dashboard-section-head">
        <div>
            <h2>Recent Support Tickets</h2>
            <p>Your latest requests and current status.</p>
        </div>

        <a class="dashboard-link" href="/it-support-system/client/tickets.php">
            View all tickets →
        </a>
    </div>

    <div class="card">

        <div style="margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;gap:10px">
            <div>
                <strong style="font-size:11px">Ticket activity</strong>
                <span style="display:block;color:#94a3b8;font-size:8px;margin-top:3px">
                    Updates are reflected in your ticket timeline.
                </span>
            </div>

            <a class="btn btn-secondary" href="/it-support-system/client/tickets.php">
                My Tickets
            </a>
        </div>

        <div class="table-wrap">
            <table class="table">

                <thead>
                    <tr>
                        <th>Ticket ID</th>
                        <th>Category</th>
                        <th>Issue</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach($tickets as $t): ?>

                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($t['ticket_code']) ?></strong>
                            </td>

                            <td><?= htmlspecialchars($t['category']) ?></td>

                            <td>
                                <?= htmlspecialchars(mb_strimwidth($t['issue_description'], 0, 42, '...')) ?>
                            </td>

                            <td class="priority-<?= strtolower($t['priority']) ?>">
                                <?= htmlspecialchars($t['priority']) ?>
                            </td>

                            <td>
                                <span class="status <?= status_class($t['status']) ?>">
                                    <?= htmlspecialchars($t['status']) ?>
                                </span>
                            </td>

                            <td>
                                <?= date('d M Y', strtotime($t['created_at'])) ?>
                            </td>

                            <td>
                                <a class="btn btn-secondary" href="/it-support-system/client/ticket.php?id=<?= (int)$t['id'] ?>">
                                    View
                                </a>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    <?php if(!$tickets): ?>

                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <div class="empty-state-icon">＋</div>

                                    <strong style="display:block;font-size:12px;color:#334155">
                                        No tickets yet
                                    </strong>

                                    <span style="display:block;margin-top:4px;font-size:9px">
                                        Create your first support request to get started.
                                    </span>
                                </div>
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pageHeading = 'Administrator Dashboard';
$pageTitle = 'Admin Dashboard | SupportHub';

$pdo = db();

/* GET ADMIN PREVIOUS LOGIN TIME */
$adminId = (int)current_user()['id'];

$stmt = $pdo->prepare("
    SELECT previous_login
    FROM users
    WHERE id = ?
");
$stmt->execute([$adminId]);
$previousLogin = $stmt->fetchColumn();

/* Core ticket metrics */
$total = (int)$pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
$open = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status='Open'")->fetchColumn();
$assigned = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status='Assigned'")->fetchColumn();
$progress = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status='In Progress'")->fetchColumn();
$resolved = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status='Resolved'")->fetchColumn();
$closed = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status='Closed'")->fetchColumn();

$users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$staffCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetchColumn();
$activeTickets = $open + $assigned + $progress;
$completedTickets = $resolved + $closed;
$completionRate = $total > 0 ? round(($completedTickets / $total) * 100) : 0;

/* Status distribution */
$statusRows = $pdo->query("
    SELECT status, COUNT(*) AS total
    FROM tickets
    GROUP BY status
")->fetchAll();

$statusData = [
    'Open' => 0,
    'Assigned' => 0,
    'In Progress' => 0,
    'Resolved' => 0,
    'Closed' => 0
];
foreach ($statusRows as $row) {
    if (isset($statusData[$row['status']])) {
        $statusData[$row['status']] = (int)$row['total'];
    }
}

/* Category distribution */
$categoryRows = $pdo->query("
    SELECT category, COUNT(*) AS total
    FROM tickets
    GROUP BY category
    ORDER BY total DESC
")->fetchAll();

$maxCategory = 1;
foreach ($categoryRows as $row) {
    $maxCategory = max($maxCategory, (int)$row['total']);
}

/* Priority distribution */
$priorityRows = $pdo->query("
    SELECT priority, COUNT(*) AS total
    FROM tickets
    GROUP BY priority
    ORDER BY FIELD(priority,'Critical','High','Medium','Low')
")->fetchAll();

$maxPriority = 1;
foreach ($priorityRows as $row) {
    $maxPriority = max($maxPriority, (int)$row['total']);
}

/* Staff workload */
$staffRows = $pdo->query("
    SELECT
        u.id,
        u.name,
        COUNT(t.id) AS assigned_total,
        SUM(CASE WHEN t.status='Assigned' THEN 1 ELSE 0 END) AS assigned_count,
        SUM(CASE WHEN t.status='In Progress' THEN 1 ELSE 0 END) AS progress_count,
        SUM(CASE WHEN t.status='Resolved' THEN 1 ELSE 0 END) AS resolved_count,
        SUM(CASE WHEN t.status='Closed' THEN 1 ELSE 0 END) AS closed_count
    FROM users u
    LEFT JOIN tickets t ON t.assigned_to=u.id
    WHERE u.role='staff'
    GROUP BY u.id, u.name
    ORDER BY assigned_total DESC, u.name ASC
")->fetchAll();

$maxStaffWork = 1;
foreach ($staffRows as $row) {
    $maxStaffWork = max($maxStaffWork, (int)$row['assigned_total']);
}

/* Recent activity */
$recent = $pdo->query("
    SELECT t.*, u.name AS client_name, s.name AS staff_name
    FROM tickets t
    JOIN users u ON u.id=t.user_id
    LEFT JOIN users s ON s.id=t.assigned_to
    ORDER BY t.updated_at DESC
    LIMIT 7
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-hero">
    <div><span class="eyebrow">ADMIN CONTROL CENTER</span><h2>Good afternoon, <?= htmlspecialchars(explode(' ', trim(current_user()['name']))[0]) ?>. 👋</h2><p>Here is an overview of your IT support operations, workload and recent ticket activity.</p></div>

    <div class="quick-action">
        <div class="dashboard-date">
            <strong>Previous Login</strong>
            <?= $previousLogin
                ? date('d M Y, h:i A', strtotime($previousLogin))
                : 'First Login'
            ?>
        </div>
        <a class="btn btn-primary" href="/it-support-system/admin/tickets.php">Manage Tickets →</a>
    </div>
</div>

<!-- KPI row -->
<div class="analytics-kpi-grid">
    <div class="card analytics-kpi">
        <div class="analytics-kpi-top">
            <span>Total Tickets</span><i>▤</i>
        </div>
        <strong><?= $total ?></strong>
        <small>All submitted support requests</small>
    </div>

    <div class="card analytics-kpi">
        <div class="analytics-kpi-top">
            <span>Active Tickets</span><i>◷</i>
        </div>
        <strong><?= $activeTickets ?></strong>
        <small>Open, assigned or in progress</small>
    </div>

    <div class="card analytics-kpi">
        <div class="analytics-kpi-top">
            <span>Completed</span><i>✓</i>
        </div>
        <strong><?= $completedTickets ?></strong>
        <small>Resolved or closed</small>
    </div>

    <div class="card analytics-kpi">
        <div class="analytics-kpi-top">
            <span>Completion Rate</span><i>%</i>
        </div>
        <strong><?= $completionRate ?>%</strong>
        <small><?= $staffCount ?> support staff · <?= $users ?> users</small>
    </div>
</div>

<!-- Status overview -->
<div class="analytics-section-head">
    <div>
        <div class="section-title" style="margin:0">Ticket Overview</div>
        <p>Current distribution across the support workflow.</p>
    </div>
    <a class="btn btn-primary" href="/it-support-system/admin/tickets.php">Manage Tickets</a>
</div>

<div class="analytics-overview-grid">
    <div class="card status-analytics-card">
        <div class="analytics-card-heading">
            <div>
                <h3>Status Distribution</h3>
                <span><?= $total ?> total ticket<?= $total === 1 ? '' : 's' ?></span>
            </div>
        </div>

        <?php
            $statusTotal = max(1, array_sum($statusData));
            $openDeg = ($statusData['Open'] / $statusTotal) * 360;
            $assignedDeg = ($statusData['Assigned'] / $statusTotal) * 360;
            $progressDeg = ($statusData['In Progress'] / $statusTotal) * 360;
            $resolvedDeg = ($statusData['Resolved'] / $statusTotal) * 360;
            $closedDeg = ($statusData['Closed'] / $statusTotal) * 360;

            $a = $openDeg;
            $b = $a + $assignedDeg;
            $c = $b + $progressDeg;
            $d = $c + $resolvedDeg;
            $donut = "conic-gradient(#f59e0b 0deg {$a}deg,#3b82f6 {$a}deg {$b}deg,#6366f1 {$b}deg {$c}deg,#16a34a {$c}deg {$d}deg,#64748b {$d}deg 360deg)";
        ?>

        <div class="donut-layout">
            <div class="donut-chart" style="background:<?= $donut ?>">
                <div><strong><?= $total ?></strong><span>Tickets</span></div>
            </div>

            <div class="legend-list">
                <?php
                $legend = [
                    ['Open','#f59e0b'],
                    ['Assigned','#3b82f6'],
                    ['In Progress','#6366f1'],
                    ['Resolved','#16a34a'],
                    ['Closed','#64748b']
                ];
                foreach ($legend as [$label,$dot]):
                ?>
                    <div class="legend-item">
                        <span><i style="background:<?= $dot ?>"></i><?= htmlspecialchars($label) ?></span>
                        <strong><?= $statusData[$label] ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card status-analytics-card">
        <div class="analytics-card-heading">
            <div>
                <h3>Priority Breakdown</h3>
                <span>Ticket urgency levels</span>
            </div>
        </div>

        <div class="bar-list">
            <?php foreach ($priorityRows as $row): ?>
                <?php
                    $value = (int)$row['total'];
                    $width = round(($value / $maxPriority) * 100);
                    $priority = $row['priority'];
                ?>
                <div class="bar-row">
                    <div class="bar-label">
                        <span class="priority-<?= strtolower($priority) ?>"><?= htmlspecialchars($priority) ?></span>
                        <strong><?= $value ?></strong>
                    </div>
                    <div class="bar-track">
                        <span class="bar-fill priority-fill-<?= strtolower($priority) ?>" style="width:<?= $width ?>%"></span>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!$priorityRows): ?>
                <div class="analytics-empty">Priority data will appear when tickets are submitted.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Category + workload -->
<div class="analytics-two-col">
    <div class="card analytics-panel">
        <div class="analytics-card-heading">
            <div>
                <h3>Support Categories</h3>
                <span>Most requested support areas</span>
            </div>
        </div>

        <div class="bar-list">
            <?php foreach ($categoryRows as $row): ?>
                <?php $value=(int)$row['total']; $width=round(($value/$maxCategory)*100); ?>
                <div class="bar-row">
                    <div class="bar-label">
                        <span><?= htmlspecialchars($row['category']) ?></span>
                        <strong><?= $value ?></strong>
                    </div>
                    <div class="bar-track">
                        <span class="bar-fill category-fill" style="width:<?= $width ?>%"></span>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!$categoryRows): ?>
                <div class="analytics-empty">No category data yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card analytics-panel">
        <div class="analytics-card-heading">
            <div>
                <h3>Staff Workload</h3>
                <span>Tickets currently linked to each staff member</span>
            </div>
            <a href="/it-support-system/admin/users.php" class="mini-link">Manage Users →</a>
        </div>

        <?php foreach ($staffRows as $staff): ?>
            <?php
                $work = (int)$staff['assigned_total'];
                $width = round(($work/$maxStaffWork)*100);
                $active = (int)$staff['assigned_count'] + (int)$staff['progress_count'];
            ?>
            <div class="staff-work-row">
                <div class="staff-work-head">
                    <div class="staff-person">
                        <span class="staff-avatar"><?= strtoupper(substr($staff['name'],0,1)) ?></span>
                        <div>
                            <strong><?= htmlspecialchars($staff['name']) ?></strong>
                            <small><?= $active ?> active · <?= (int)$staff['resolved_count'] + (int)$staff['closed_count'] ?> completed</small>
                        </div>
                    </div>
                    <strong><?= $work ?></strong>
                </div>
                <div class="bar-track"><span class="bar-fill staff-fill" style="width:<?= $width ?>%"></span></div>
            </div>
        <?php endforeach; ?>

        <?php if (!$staffRows): ?>
            <div class="analytics-empty">No support staff accounts found.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent activity -->
<div class="analytics-section-head" style="margin-top:25px">
    <div>
        <div class="section-title" style="margin:0">Recent Ticket Activity</div>
        <p>Latest ticket records, ordered by most recent update.</p>
    </div>
</div>

<div class="card analytics-recent-card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Ticket</th>
                    <th>Client</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Staff</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $ticket): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($ticket['ticket_code']) ?></strong></td>
                    <td><?= htmlspecialchars($ticket['client_name']) ?></td>
                    <td><?= htmlspecialchars($ticket['category']) ?></td>
                    <td class="priority-<?= strtolower($ticket['priority']) ?>"><?= htmlspecialchars($ticket['priority']) ?></td>
                    <td><span class="status status-<?= strtolower(str_replace(' ', '-', $ticket['status'])) ?>"><?= htmlspecialchars($ticket['status']) ?></span></td>
                    <td><?= htmlspecialchars($ticket['staff_name'] ?? 'Unassigned') ?></td>
                    <td><?= date('d M Y, h:i A', strtotime($ticket['updated_at'])) ?></td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$recent): ?>
                <tr><td colspan="7" class="analytics-empty-cell">No tickets have been submitted yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.analytics-kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.analytics-kpi{padding:18px 19px}
.analytics-kpi-top{display:flex;justify-content:space-between;align-items:center;color:#718096;font-size:11px;font-weight:700}
.analytics-kpi-top i{width:34px;height:34px;border-radius:10px;background:#eff6ff;color:#2563eb;display:grid;place-items:center;font-style:normal}
.analytics-kpi>strong{display:block;font-size:28px;margin-top:10px;letter-spacing:-.5px}
.analytics-kpi small{display:block;margin-top:4px;color:#94a3b8;font-size:10px}
.analytics-section-head{display:flex;justify-content:space-between;align-items:end;gap:18px;margin:25px 0 12px}
.analytics-section-head p{margin:4px 0 0;color:#8a96a8;font-size:11px}
.analytics-overview-grid{display:grid;grid-template-columns:1.15fr .85fr;gap:18px}
.status-analytics-card,.analytics-panel{min-height:285px}
.analytics-card-heading{display:flex;justify-content:space-between;align-items:start;margin-bottom:18px}
.analytics-card-heading h3{margin:0;font-size:15px}
.analytics-card-heading span{display:block;color:#8a96a8;font-size:10px;margin-top:4px}
.donut-layout{display:grid;grid-template-columns:190px 1fr;align-items:center;gap:20px}
.donut-chart{width:180px;height:180px;border-radius:50%;display:grid;place-items:center}
.donut-chart>div{width:112px;height:112px;background:#fff;border-radius:50%;display:grid;place-items:center;align-content:center;box-shadow:0 2px 12px rgba(15,23,42,.05)}
.donut-chart strong{font-size:27px;line-height:1}
.donut-chart span{font-size:9px;color:#8a96a8;margin-top:4px}
.legend-list{display:grid;gap:12px}
.legend-item{display:flex;justify-content:space-between;align-items:center;font-size:11px}
.legend-item span{display:flex;align-items:center;gap:8px;color:#475569}
.legend-item i{width:9px;height:9px;border-radius:50%;display:inline-block}
.legend-item strong{font-size:12px}
.bar-list{display:grid;gap:17px}
.bar-row{display:grid;gap:7px}
.bar-label{display:flex;justify-content:space-between;align-items:center;font-size:11px}
.bar-label span{color:#475569;font-weight:600}
.bar-label strong{font-size:11px}
.bar-track{height:8px;border-radius:99px;background:#edf1f6;overflow:hidden}
.bar-fill{display:block;height:100%;border-radius:99px;min-width:2px}
.category-fill{background:#3b82f6}
.staff-fill{background:#6366f1}
.priority-fill-critical{background:#dc2626}
.priority-fill-high{background:#f97316}
.priority-fill-medium{background:#f59e0b}
.priority-fill-low{background:#16a34a}
.analytics-two-col{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px}
.mini-link{font-size:10px;color:#2563eb;font-weight:800}
.staff-work-row{padding:11px 0;border-bottom:1px solid #edf1f6}
.staff-work-row:last-child{border-bottom:0}
.staff-work-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.staff-person{display:flex;align-items:center;gap:9px}
.staff-avatar{width:30px;height:30px;border-radius:50%;background:#e8f0ff;color:#2563eb;display:grid;place-items:center;font-weight:800;font-size:11px}
.staff-person strong,.staff-person small{display:block}
.staff-person strong{font-size:11px}
.staff-person small{font-size:9px;color:#8a96a8;margin-top:2px}
.analytics-empty{padding:25px 5px;text-align:center;color:#94a3b8;font-size:11px}
.analytics-empty-cell{text-align:center;color:#94a3b8!important;padding:35px!important}
@media(max-width:1100px){
    .analytics-kpi-grid{grid-template-columns:repeat(2,1fr)}
    .analytics-overview-grid,.analytics-two-col{grid-template-columns:1fr}
}
@media(max-width:650px){
    .analytics-kpi-grid{grid-template-columns:1fr}
    .analytics-section-head{align-items:stretch;flex-direction:column}
    .donut-layout{grid-template-columns:1fr;justify-items:center}
    .legend-list{width:100%}
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>

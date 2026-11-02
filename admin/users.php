<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_user') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? 'client');

    if ($csrfError = post_error_for_invalid_csrf()) {
        $error = $csrfError;
    } elseif ($name === '' || $email === '' || $password === '') {
        $error = 'Name, email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($name) > 100) {
        $error = 'Name must be 100 characters or fewer.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must contain at least 8 characters.';
    } elseif (!in_array($role, ['client','staff','admin'], true)) {
        $error = 'Invalid account role selected.';
    } else {
        try {
            $check = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
            $check->execute([$email]);

            if ($check->fetch()) {
                $error = 'An account with this email already exists.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)');
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
                header('Location: /it-support-system/admin/users.php?created=1');
                exit;
            }
        } catch (Throwable $e) {
            $error = 'The account could not be created. Please check the database connection.';
        }
    }
}

if (isset($_GET['created'])) {
    $success = 'User account created successfully.';
}

$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(name LIKE ? OR email LIKE ?)';
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
}
if ($roleFilter !== '' && in_array($roleFilter, ['client','staff','admin'], true)) {
    $where[] = 'role=?';
    $params[] = $roleFilter;
}

/* ADDED last_login */
$sql = 'SELECT id,name,email,role,created_at,last_login FROM users';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$clientCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn();
$staffCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetchColumn();
$adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();

$pageHeading = 'User Management';
$pageTitle = 'Users | SupportHub';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="user-kpi-grid">
    <div class="card user-kpi"><span>Total Users</span><strong><?= $totalUsers ?></strong><small>All registered accounts</small></div>
    <div class="card user-kpi"><span>Clients</span><strong><?= $clientCount ?></strong><small>Support request creators</small></div>
    <div class="card user-kpi"><span>Support Staff</span><strong><?= $staffCount ?></strong><small>Ticket handlers</small></div>
    <div class="card user-kpi"><span>Administrators</span><strong><?= $adminCount ?></strong><small>System management accounts</small></div>
</div>

<div class="user-management-grid">
    <div class="card">
        <div class="management-heading">
            <div>
                <h2>Create User Account</h2>
                <p>Create a client, support staff or administrator account.</p>
            </div>
            <span class="secure-badge">ADMIN ONLY</span>
        </div>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_user">

            <div class="form-group">
                <label>Full Name</label>
                <input class="input" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" maxlength="100" placeholder="e.g. Support Officer" required>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input class="input" type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="name@example.com" required>
            </div>

            <div class="form-group">
                <label>Account Role</label>
                <select class="select" name="role" required>
                    <?php foreach (['client'=>'Client','staff'=>'IT Support Staff','admin'=>'Administrator'] as $value=>$label): ?>
                        <option value="<?= $value ?>" <?= ($_POST['role'] ?? 'client') === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Temporary Password</label>
                <input class="input" type="password" name="password" minlength="8" placeholder="Minimum 8 characters" required>
            </div>

            <button class="btn btn-primary" type="submit" style="width:100%">+ Create Account</button>
        </form>
    </div>

    <div class="card account-policy-card">
        <div class="management-heading">
            <div>
                <h2>Account Overview</h2>
                <p>Quick role guidance for system administrators.</p>
            </div>
        </div>

        <div class="role-guide">
            <div class="role-guide-item"><span class="role-dot client-dot">C</span><div><strong>Client</strong><small>Creates and tracks support tickets.</small></div></div>
            <div class="role-guide-item"><span class="role-dot staff-dot">S</span><div><strong>IT Support Staff</strong><small>Handles assigned tickets and updates progress.</small></div></div>
            <div class="role-guide-item"><span class="role-dot admin-dot">A</span><div><strong>Administrator</strong><small>Manages users, assignments, notifications and system analytics.</small></div></div>
        </div>

        <div class="policy-note"><strong>Security note</strong><span>Passwords are stored using PHP's secure password hashing. The administrator area is protected by role-based access control.</span></div>
    </div>
</div>

<div class="card user-list-card">
    <div class="management-heading list-heading">
        <div>
            <h2>Registered Users</h2>
            <p>Search and review all SupportHub accounts.</p>
        </div>
        <span class="record-count"><?= count($rows) ?> shown</span>
    </div>

    <form method="get" class="user-filter-form">
        <input class="input" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name or email...">
        <select class="select" name="role">
            <option value="">All Roles</option>
            <option value="client" <?= $roleFilter === 'client' ? 'selected' : '' ?>>Clients</option>
            <option value="staff" <?= $roleFilter === 'staff' ? 'selected' : '' ?>>Support Staff</option>
            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Administrators</option>
        </select>
        <button class="btn btn-primary">Filter</button>
        <?php if ($search !== '' || $roleFilter !== ''): ?><a class="btn btn-secondary" href="/it-support-system/admin/users.php">Reset</a><?php endif; ?>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined</th>
                    <th>Last Login</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $u): ?>
                <tr>
                    <td>
                        <div class="user-cell">
                            <span class="user-list-avatar"><?= strtoupper(substr($u['name'],0,1)) ?></span>
                            <div>
                                <strong><?= htmlspecialchars($u['name']) ?></strong>
                                <?php if ((int)$u['id'] === (int)current_user()['id']): ?>
                                    <small>Current session</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>

                    <td><?= htmlspecialchars($u['email']) ?></td>

                    <td>
                        <span class="role-badge role-<?= htmlspecialchars($u['role']) ?>">
                            <?= htmlspecialchars(ucfirst($u['role'])) ?>
                        </span>
                    </td>

                    <td><?= date('d M Y, h:i A', strtotime($u['created_at'])) ?></td>

                    <td>
                        <?php if (!empty($u['last_login'])): ?>
                            <span class="last-login-time">
                                <?= date('d M Y, h:i A', strtotime($u['last_login'])) ?>
                            </span>
                        <?php else: ?>
                            <span class="never-login">Never Logged In</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$rows): ?>
                <tr><td colspan="5" class="analytics-empty-cell">No users match your search.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.user-kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.user-kpi{padding:18px 19px}.user-kpi span{display:block;color:#718096;font-size:11px;font-weight:700}.user-kpi strong{display:block;font-size:27px;margin-top:8px}.user-kpi small{display:block;color:#94a3b8;font-size:9px;margin-top:3px}
.user-management-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px}.management-heading{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;margin-bottom:18px}.management-heading h2{margin:0;font-size:16px}.management-heading p{margin:5px 0 0;color:#8a96a8;font-size:10px;line-height:1.5}.secure-badge{font-size:8px;font-weight:800;color:#2563eb;background:#eff6ff;border-radius:999px;padding:6px 8px;white-space:nowrap}.account-policy-card{background:linear-gradient(135deg,#fff,#f8fbff)}
.role-guide{display:grid;gap:12px}.role-guide-item{display:flex;align-items:center;gap:10px;padding:11px;border:1px solid #edf1f6;border-radius:12px;background:#fff}.role-guide-item strong,.role-guide-item small{display:block}.role-guide-item strong{font-size:11px}.role-guide-item small{font-size:9px;color:#8a96a8;margin-top:3px;line-height:1.4}.role-dot{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;font-size:10px;font-weight:800}.client-dot{background:#eff6ff;color:#2563eb}.staff-dot{background:#eef2ff;color:#4f46e5}.admin-dot{background:#fff7ed;color:#c2410c}.policy-note{margin-top:15px;padding:11px 12px;border-radius:11px;background:#f8fafc;border:1px solid #e8edf4}.policy-note strong,.policy-note span{display:block}.policy-note strong{font-size:10px}.policy-note span{font-size:9px;color:#718096;line-height:1.5;margin-top:3px}
.user-list-card{margin-top:18px}.list-heading{align-items:center}.record-count{font-size:9px;font-weight:800;color:#64748b;background:#f1f5f9;padding:6px 9px;border-radius:999px;white-space:nowrap}.user-filter-form{display:grid;grid-template-columns:1fr 180px auto auto;gap:10px;margin-bottom:16px}.user-cell{display:flex;align-items:center;gap:9px}.user-cell small{display:block;color:#94a3b8;font-size:8px;margin-top:2px}.user-list-avatar{width:31px;height:31px;border-radius:50%;background:#e8f0ff;color:#2563eb;display:grid;place-items:center;font-weight:800;font-size:10px;flex:0 0 auto}.role-badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800}.role-client{background:#eff6ff;color:#1d4ed8}.role-staff{background:#eef2ff;color:#4f46e5}.role-admin{background:#fff7ed;color:#c2410c}.account-active{display:inline-flex;align-items:center;gap:5px;color:#15803d;font-size:9px;font-weight:800}.account-active i{width:6px;height:6px;border-radius:50%;background:#22c55e}

/* Last login display */
.last-login-time{font-size:10px;color:#334155;font-weight:600;white-space:nowrap}
.never-login{font-size:9px;color:#94a3b8;font-style:italic;white-space:nowrap}

@media(max-width:1050px){.user-kpi-grid{grid-template-columns:repeat(2,1fr)}.user-management-grid{grid-template-columns:1fr}.user-filter-form{grid-template-columns:1fr 180px auto auto}}
@media(max-width:650px){.user-kpi-grid{grid-template-columns:1fr}.user-filter-form{grid-template-columns:1fr}.list-heading{align-items:flex-start;flex-direction:column}}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>

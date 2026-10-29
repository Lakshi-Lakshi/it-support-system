<?php
require_once __DIR__ . '/auth.php';
$user = current_user();
$unread = 0;
// jf20
$profileError = '';
$profileSuccess = '';
$profileName = $user['name'] ?? '';
$profileEmail = $user['email'] ?? '';
$profileImage = $user['profile_img_url'] ?? '';

if ($user) {
    // jf20
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['profile_action'] ?? '') === 'update') {
        $profileName = trim((string)($_POST['profile_name'] ?? ''));
        $profileEmail = trim((string)($_POST['profile_email'] ?? ''));
        $upload = $_FILES['profile_image'] ?? null;
        $removeProfileImage = ($_POST['remove_profile_image'] ?? '') === '1';
        $imageInfo = null;
        $imageExtension = null;

        if ($csrfError = post_error_for_invalid_csrf()) {
            $profileError = $csrfError;
        } elseif ($profileName === '' || $profileEmail === '') {
            $profileError = 'Name and email are required.';
        } elseif (strlen($profileName) > 100) {
            $profileError = 'Name must be 100 characters or fewer.';
        } elseif (!filter_var($profileEmail, FILTER_VALIDATE_EMAIL)) {
            $profileError = 'Enter a valid email address.';
        } elseif ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE && $upload['error'] !== UPLOAD_ERR_OK) {
            $profileError = 'The profile image could not be uploaded.';
        } else {
            if ($upload && $upload['error'] === UPLOAD_ERR_OK) {
                $imageInfo = @getimagesize($upload['tmp_name']);
                $allowedImageTypes = [
                    IMAGETYPE_JPEG => 'jpg',
                    IMAGETYPE_PNG => 'png',
                ];
                if (!$imageInfo || !isset($allowedImageTypes[$imageInfo[2]]) || $upload['size'] > 10 * 1024 * 1024) {
                    $profileError = 'Use a JPG, JPEG, or PNG image up to 10 MB.';
                } else {
                    $imageExtension = $allowedImageTypes[$imageInfo[2]];
                }
            }
            if ($profileError === '') {
            try {
                $stmt = db()->prepare('SELECT name, email, role, profile_img_url FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([(int)$user['id']]);
                $storedUser = $stmt->fetch();

                if (!$storedUser) {
                    $profileError = 'Your account could not be found.';
                } else {
                    $stmt = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
                    $stmt->execute([$profileEmail, (int)$user['id']]);
                    if ($stmt->fetch()) {
                        $profileError = 'That email address is already in use.';
                    } else {
                        db()->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')
                            ->execute([$profileName, $profileEmail, (int)$user['id']]);

                        if ($upload && $upload['error'] === UPLOAD_ERR_OK) {
                            $safeName = preg_replace('/[^a-z0-9]+/i', '-', strtolower($profileName));
                            $safeRole = preg_replace('/[^a-z0-9]+/i', '-', strtolower($storedUser['role']));
                            $safeName = trim($safeName, '-') ?: 'user';
                            $safeRole = trim($safeRole, '-') ?: 'member';
                            do {
                                $filename = $safeName . '-' . $safeRole . '-' . random_int(100, 999) . '.' . $imageExtension;
                                $targetPath = __DIR__ . '/../profileImg/' . $filename;
                            } while (file_exists($targetPath));

                            if (!move_uploaded_file($upload['tmp_name'], $targetPath)) {
                                $profileError = 'The profile image could not be saved.';
                            } else {
                                if (!empty($storedUser['profile_img_url'])) {
                                    $oldImage = __DIR__ . '/../profileImg/' . basename((string)parse_url($storedUser['profile_img_url'], PHP_URL_PATH));
                                    if (is_file($oldImage) && !unlink($oldImage)) {
                                        $profileError = 'The old profile image could not be removed.';
                                    }
                                }
                                $profileImage = '/it-support-system/profileImg/' . $filename;
                                db()->prepare('UPDATE users SET profile_img_url = ? WHERE id = ?')
                                    ->execute([$profileImage, (int)$user['id']]);
                            }
                        } elseif ($removeProfileImage) {
                            if (!empty($storedUser['profile_img_url'])) {
                                $oldImage = __DIR__ . '/../profileImg/' . basename((string)parse_url($storedUser['profile_img_url'], PHP_URL_PATH));
                                if (is_file($oldImage) && !unlink($oldImage)) {
                                    $profileError = 'The profile image could not be removed.';
                                }
                            }
                            if ($profileError === '') {
                                $profileImage = '';
                                db()->prepare('UPDATE users SET profile_img_url = NULL WHERE id = ?')
                                    ->execute([(int)$user['id']]);
                            }
                        }

                        if ($profileError === '') {
                            $_SESSION['user']['name'] = $profileName;
                            $_SESSION['user']['email'] = $profileEmail;
                            $_SESSION['user']['profile_img_url'] = $profileImage !== '' ? $profileImage : null;
                            $user['name'] = $profileName;
                            $user['email'] = $profileEmail;
                            $user['profile_img_url'] = $_SESSION['user']['profile_img_url'];
                            $profileSuccess = 'Profile updated successfully.';
                        }
                    }
                }
            } catch (Throwable $e) {
                $profileError = 'Profile update failed. Please try again.';
            }
            }
        }
    }

    try {
        $stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user['id']]);
        $unread = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $unread = 0;
    }
}

$script = basename($_SERVER['PHP_SELF'] ?? '');
$path = $_SERVER['PHP_SELF'] ?? '';
$role = $user['role'] ?? '';
$searchAction = $role === 'admin'
    ? '/it-support-system/admin/tickets.php'
    : ($role === 'client' ? '/it-support-system/client/tickets.php' : '/it-support-system/staff/tickets.php');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#071326">
<title><?= htmlspecialchars($pageTitle ?? 'SupportHub') ?></title>
<script>
(() => { document.documentElement.dataset.theme = localStorage.getItem('supporthub.theme') || 'light'; document.documentElement.dataset.accent = localStorage.getItem('supporthub.accent') || 'blue'; })();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/it-support-system/assets/css/style.css">
</head>
<body>
<div class="app-shell" id="appShell">
<?php if ($user): ?>
    <div class="sidebar-overlay" data-sidebar-overlay></div>
    <aside class="sidebar" id="appSidebar">
        <div class="brand">
            <a class="brand-mark" href="<?= dashboard_url($user['role']) ?>" aria-label="SupportHub home">
                <span>IT</span>
            </a>
            <div class="brand-copy">
                <strong>SupportHub</strong>
                <span>IT Service Management</span>
            </div>
            <button class="sidebar-close" type="button" data-sidebar-close aria-label="Close menu">×</button>
        </div>

        <div class="sidebar-label">WORKSPACE</div>
        <nav class="nav" aria-label="Primary navigation">
            <a class="<?= $script === 'dashboard.php' ? 'active' : '' ?>" href="<?= dashboard_url($user['role']) ?>" title="Dashboard">
                <span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span><span class="nav-text">Dashboard</span>
            </a>
            <?php if ($role === 'client'): ?>
                <a class="<?= str_contains($path, '/client/new-ticket.php') ? 'active' : '' ?>" href="/it-support-system/client/new-ticket.php" title="New Ticket">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></span><span class="nav-text">New Ticket</span>
                </a>
                <a class="<?= str_contains($path, '/client/tickets.php') || str_contains($path, '/client/ticket.php') ? 'active' : '' ?>" href="/it-support-system/client/tickets.php" title="My Tickets">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span><span class="nav-text">My Tickets</span>
                </a>
            <?php elseif ($role === 'staff'): ?>
                <a class="<?= str_contains($path, '/staff/tickets.php') ? 'active' : '' ?>" href="/it-support-system/staff/tickets.php" title="Assigned Tickets">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/></svg></span><span class="nav-text">Assigned Tickets</span>
                </a>
            <?php else: ?>
                <a class="<?= str_contains($path, '/admin/tickets.php') ? 'active' : '' ?>" href="/it-support-system/admin/tickets.php" title="All Tickets">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h8M8 17h5"/></svg></span><span class="nav-text">All Tickets</span>
                </a>
                <a class="<?= str_contains($path, '/admin/users.php') ? 'active' : '' ?>" href="/it-support-system/admin/users.php" title="Users">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3.5 19c.7-3 2.5-4.5 5.5-4.5s4.8 1.5 5.5 4.5"/><circle cx="17" cy="10" r="2.3"/><path d="M14.5 19c.3-2 1.3-3.2 3.2-3.2 1.7 0 2.7.8 3 2.2"/></svg></span><span class="nav-text">Users</span>
                </a>
            <?php endif; ?>
            <a class="<?= $script === 'notifications.php' ? 'active' : '' ?>" href="/it-support-system/notifications.php" title="Notifications">
                <span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg></span><span class="nav-text">Notifications</span>
                <?php if ($unread > 0): ?><b class="badge-count"><?= $unread ?></b><?php endif; ?>
            </a>
        </nav>

        <div class="sidebar-spacer"></div>
        <div class="sidebar-bottom">
            <button class="user-mini profile-trigger" type="button" data-settings-open aria-label="Open profile settings">
                <div class="avatar"><?php if ($profileImage !== ''): ?><img src="<?= htmlspecialchars($profileImage) ?>" alt=""><?php else: ?><?= htmlspecialchars(strtoupper(substr($user['name'], 0, 1))) ?><?php endif; ?></div>
                <div class="user-copy">
                    <strong><?= htmlspecialchars($user['name']) ?></strong>
                    <span><?= htmlspecialchars(ucfirst($user['role'])) ?></span>
                </div>
                <span class="online-dot" title="Online"></span>
            </button>
            <form method="post" action="/it-support-system/logout.php" class="logout-form">
                <?= csrf_field() ?>
                <button class="logout-link" type="submit" title="Logout">
                    <span class="logout-icon">↪</span><span class="nav-text">Logout</span>
                </button>
            </form>
        </div>
    </aside>
<?php endif; ?>

<main class="<?= $user ? 'main-content' : 'public-content' ?>">
<?php if ($user): ?>
    <header class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <div class="topbar-title">
                <span class="eyebrow">IT SUPPORT MANAGEMENT</span>
                <h1><?= htmlspecialchars($pageHeading ?? 'Dashboard') ?></h1>
            </div>
        </div>
        <div class="topbar-actions">
            <form class="global-search" method="get" action="<?= $searchAction ?>" role="search">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/></svg>
                <input name="search" value="<?= htmlspecialchars(trim($_GET['search'] ?? '')) ?>" placeholder="Search tickets..." aria-label="Search tickets">
                <kbd>⌘ K</kbd>
                <button class="global-search-submit" type="submit" aria-label="Run search">Search</button>
            </form>
            <a class="icon-button notification-button" href="/it-support-system/notifications.php" aria-label="Notifications" title="Notifications">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
                <?php if ($unread > 0): ?><span><?= $unread ?></span><?php endif; ?>
            </a>
            <div class="profile-menu">
                <button class="profile-chip" type="button" data-profile-toggle aria-haspopup="menu" aria-expanded="false" aria-controls="profileDropdown">
                <div class="avatar small"><?php if ($profileImage !== ''): ?><img src="<?= htmlspecialchars($profileImage) ?>" alt=""><?php else: ?><?= htmlspecialchars(strtoupper(substr($user['name'], 0, 1))) ?><?php endif; ?></div>
                <div class="profile-chip-copy"><strong><?= htmlspecialchars($user['name']) ?></strong><span><?= htmlspecialchars(ucfirst($user['role'])) ?></span></div>
                <svg class="chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
                </button>
                <div class="profile-dropdown" id="profileDropdown" hidden>
                    <div class="profile-dropdown-user"><strong><?= htmlspecialchars($user['name']) ?></strong><span><?= htmlspecialchars($user['email']) ?></span></div>
                    <button type="button" class="profile-menu-action" data-settings-open>Profile &amp; appearance</button>
                </div>
            </div>
        </div>
    </header>
    <div class="settings-modal" data-settings-modal hidden role="dialog" aria-modal="true" aria-labelledby="settingsTitle">
        <div class="settings-backdrop" data-settings-close></div>
        <section class="settings-dialog">
            <button class="settings-close" type="button" data-settings-close aria-label="Close settings">×</button>
            <span class="eyebrow">PERSONALIZATION</span><h2 id="settingsTitle">Profile settings</h2>
            <!-- jf20 -->
            <?php if ($profileError !== ''): ?><div class="alert alert-error settings-alert"><?= htmlspecialchars($profileError) ?></div><?php endif; ?>
            <?php if ($profileSuccess !== ''): ?><div class="alert alert-success settings-alert"><?= htmlspecialchars($profileSuccess) ?></div><?php endif; ?>
            <form class="profile-settings-form" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="profile_action" value="update">
                <input type="hidden" name="remove_profile_image" value="0" data-remove-profile-image>
                <div class="profile-card">
                    <div class="profile-card-header">
                        <div class="profile-image-upload">
                            <?php if ($profileImage !== ''): ?>
                                <img src="<?= htmlspecialchars($profileImage) ?>" alt="Profile photo">
                            <?php else: ?>
                                <span><?= htmlspecialchars(strtoupper(substr($profileName, 0, 1))) ?></span>
                            <?php endif; ?>
                            <input type="file" name="profile_image" accept=".png,.jpg,.jpeg,image/png,image/jpeg" data-profile-image-input>
                            <div class="profile-image-actions" aria-label="Profile photo actions">
                                <button type="button" class="profile-image-action" data-profile-image-edit aria-label="Edit profile photo" title="Edit photo">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 16.5-.8 4.3 4.3-.8L19 8.5a2.1 2.1 0 0 0-3-3L4 16.5Z"/><path d="m14.5 7.5 2 2"/></svg>
                                </button>
                                <button type="button" class="profile-image-action profile-image-delete" data-profile-image-delete aria-label="Delete profile photo" title="Delete photo"<?= $profileImage === '' ? ' disabled' : '' ?>>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7l1-3h4l1 3"/></svg>
                                </button>
                            </div>
                        </div>
                        <div><strong><?= htmlspecialchars($profileName) ?></strong><span><?= htmlspecialchars($profileEmail) ?></span></div>
                    </div>
                    <div class="profile-card-fields">
                        <label>Username<input class="input" type="text" name="profile_name" value="<?= htmlspecialchars($profileName) ?>" maxlength="100" required></label>
                        <label>Email<input class="input" type="email" name="profile_email" value="<?= htmlspecialchars($profileEmail) ?>" maxlength="150" required></label>
                    </div>
                    <button class="btn btn-primary profile-save" type="submit">Save profile</button>
                </div>
            </form>
            <div class="settings-security-link">
                <div><strong>Account security</strong><span>Change your account password</span></div>
                <a class="btn btn-secondary" href="/it-support-system/change-password.php">Change password</a>
            </div>
            <div class="settings-group"><strong>Display mode</strong><div class="theme-options"><button type="button" data-theme-choice="light">Light</button><button type="button" data-theme-choice="dark">Dark</button></div></div>
            <div class="settings-group"><strong>Theme colour</strong><div class="accent-options"><button type="button" data-accent-choice="blue"><i class="accent-blue"></i>Blue</button><button type="button" data-accent-choice="violet"><i class="accent-violet"></i>Violet</button><button type="button" data-accent-choice="green"><i class="accent-green"></i>Green</button><button type="button" data-accent-choice="rose"><i class="accent-rose"></i>Rose</button></div></div>
            <p class="settings-note">Your choices are saved on this device.</p>
        </section>
    </div>
    <div class="profile-image-modal" data-profile-image-modal hidden role="dialog" aria-modal="true" aria-labelledby="profileImageTitle">
        <div class="profile-image-backdrop" data-profile-image-close></div>
        <section class="profile-image-dialog">
            <button class="settings-close" type="button" data-profile-image-close aria-label="Close image upload">×</button>
            <h2 id="profileImageTitle">Update profile image</h2>
            <label class="profile-image-file-label" for="profileImageModalInput">Select an image</label>
            <input id="profileImageModalInput" class="profile-image-modal-input" type="file" accept=".png,.jpg,.jpeg,image/png,image/jpeg" data-profile-image-modal-input>
            <p class="profile-image-help">PNG, JPG, or JPEG, up to 10 MB</p>
            <div class="profile-image-modal-actions">
                <button class="btn btn-secondary" type="button" data-profile-image-close>Cancel</button>
                <button class="btn btn-primary" type="button" data-profile-image-save>Save image</button>
            </div>
        </section>
    </div>
<?php endif; ?>

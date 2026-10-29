<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($csrfError = post_error_for_invalid_csrf()) {
        $error = $csrfError;
    } elseif ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $error = 'Complete all password fields.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'New password must contain at least 8 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match.';
    } else {
        try {
            $stmt = db()->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$user['id']]);
            $storedPassword = $stmt->fetchColumn();

            if (!$storedPassword || !password_verify($currentPassword, $storedPassword)) {
                $error = 'Current password is incorrect.';
            } else {
                $stmt = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
                $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int)$user['id']]);
                $success = 'Password changed successfully.';
            }
        } catch (Throwable $e) {
            $error = 'Password update failed. Please try again.';
        }
    }
}

$pageHeading = 'Change Password';
$pageTitle = 'Change Password | SupportHub';
include __DIR__ . '/includes/header.php';
?>
<section class="password-page">
    <div class="password-page-heading">
        <a class="btn btn-secondary" href="<?= htmlspecialchars(dashboard_url($user['role'])) ?>">← Back to dashboard</a>
        <span class="eyebrow">ACCOUNT SECURITY</span>
        <h2>Change your password</h2>
        <p>Use a strong password that you do not use anywhere else.</p>
    </div>
    <div class="password-card">
        <?php if ($error !== ''): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <label class="password-field">Current password
                <input class="input" type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <label class="password-field">New password
                <input class="input" type="password" name="new_password" minlength="8" autocomplete="new-password" required>
                <small>At least 8 characters.</small>
            </label>
            <label class="password-field">Re-enter new password
                <input class="input" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>
            </label>
            <div class="password-actions">
                <a class="btn btn-secondary" href="<?= htmlspecialchars(dashboard_url($user['role'])) ?>">Cancel</a>
                <button class="btn btn-primary" type="submit">Update password</button>
            </div>
        </form>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

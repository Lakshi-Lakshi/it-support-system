<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . dashboard_url(current_user()['role']));
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($csrfError = post_error_for_invalid_csrf()) {
        $error = $csrfError;
    } elseif ($name === '' || $email === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($name) > 100) {
        $error = 'Name must be 100 characters or fewer.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must contain at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $stmt = db()->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $error = 'An account with this email already exists.';
            } else {
                $stmt = db()->prepare("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'client')");
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $success = 'Account created successfully. You can now sign in.';
            }
        } catch (Throwable $e) {
            $error = 'Database error. Run setup.php first.';
        }
    }
}

$pageTitle = 'Register | SupportHub';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $pageTitle ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/it-support-system/assets/css/style.css">
</head>
<body>
<div class="public-content">
  <div class="auth-shell">
    <section class="auth-visual">
      <div class="auth-brand-row">
        <div class="brand-icon">IT</div>
        <div>
          <strong>SupportHub</strong>
          <span>IT Service Management</span>
        </div>
      </div>

      <div class="auth-visual-copy">
        <div class="eyebrow">GET STARTED</div>
        <h2>Your IT support, organized.</h2>
        <p>
          Create your client account and keep every support request,
          update and resolution in one professional workspace.
        </p>

        <div class="auth-points">
          <div class="auth-point"><i>1</i><span>Submit a support request</span></div>
          <div class="auth-point"><i>2</i><span>Follow the live ticket timeline</span></div>
          <div class="auth-point"><i>3</i><span>Receive resolution updates</span></div>
        </div>
      </div>
    </section>

    <section class="auth-form-panel">
      <div class="auth-form-inner">
        <div class="eyebrow">CLIENT REGISTRATION</div>
        <h1>Create your account</h1>
        <p>Set up your client profile to start submitting IT support requests. Staff accounts are created by an administrator.</p>

        <?php if ($error): ?>
          <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post">
          <?= csrf_field() ?>
          <div class="form-group">
            <label>Full Name</label>
            <input class="input" name="name" placeholder="Your full name" maxlength="100" autocomplete="name" required>
          </div>

          <div class="form-group">
            <label>Email Address</label>
            <input class="input" type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
          </div>

          <div class="form-group">
            <label>Password</label>
            <input class="input" type="password" name="password" placeholder="At least 8 characters" minlength="8" autocomplete="new-password" required>
          </div>

          <div class="form-group">
            <label>Confirm Password</label>
            <input class="input" type="password" name="confirm_password" placeholder="Repeat your password" minlength="8" autocomplete="new-password" required>
          </div>

          <button class="btn btn-primary" type="submit">Create Account <span>→</span></button>
        </form>

        <div class="auth-footer">
          Already registered?
          <a href="/it-support-system/login.php">Sign in</a>
        </div>
      </div>
    </section>
  </div>
</div>
<script src="/it-support-system/assets/js/password-toggle.js"></script>
</body>
</html>

<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . dashboard_url(current_user()['role']));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($csrfError = post_error_for_invalid_csrf()) {
        $error = $csrfError;
    } elseif ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        try {
            $stmt = db()->prepare("SELECT id,name,email,password,role,profile_img_url FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                login_user($user);
                header('Location: ' . dashboard_url($user['role']));
                exit;
            }
            $error = 'Invalid email or password.';
        } catch (Throwable $e) {
            $error = 'Database connection failed. Check XAMPP/MySQL and setup.php.';
        }
    }
}

$pageTitle = 'Login | SupportHub';
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
        <div class="eyebrow">SMARTER IT SUPPORT</div>
        <h2>Resolve issues. Keep work moving.</h2>
        <p>
          A centralized support workspace for reporting incidents,
          tracking progress and keeping every resolution visible.
        </p>

        <div class="auth-points">
          <div class="auth-point"><i>✓</i><span>Track every support request</span></div>
          <div class="auth-point"><i>✓</i><span>Real-time status and notifications</span></div>
          <div class="auth-point"><i>✓</i><span>Clear ownership from assignment to resolution</span></div>
        </div>
      </div>
    </section>

    <section class="auth-form-panel">
      <div class="auth-form-inner">
        <div class="eyebrow">WELCOME BACK</div>
        <h1>Sign in to SupportHub</h1>
        <p>Access your support workspace and continue where you left off.</p>

        <?php if ($error): ?>
          <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
          <?= csrf_field() ?>
          <div class="form-group">
            <label>Email Address</label>
            <input class="input" type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
          </div>

          <div class="form-group">
            <label>Password</label>
            <input class="input" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
          </div>

          <button class="btn btn-primary" type="submit">Sign In <span>→</span></button>
        </form>

        <div class="auth-footer">
          New to SupportHub?
          <a href="/it-support-system/register.php">Create an account</a>
        </div>

        <div class="auth-demo">
          <strong>Demo access</strong><br>
          Admin · admin@supporthub.test · admin123<br>
          Staff · staff@supporthub.test · staff123<br>
          Client · client@supporthub.test · client123
        </div>
      </div>
    </section>
  </div>
</div>
<script src="/it-support-system/assets/js/password-toggle.js"></script>
</body>
</html>

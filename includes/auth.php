<?php
require_once __DIR__ . '/../config/config.php';

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'profile_img_url' => $user['profile_img_url'] ?? null,
    ];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function post_error_for_invalid_csrf(): ?string
{
    return verify_csrf() ? null : 'Your form session expired. Please try again.';
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /it-support-system/login.php');
        exit;
    }
}

function require_role(string ...$roles): void
{
    require_login();
    $role = current_user()['role'] ?? '';
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        exit('403 - Access denied');
    }
}

function dashboard_url(string $role): string
{
    return match ($role) {
        'admin' => '/it-support-system/admin/dashboard.php',
        'staff' => '/it-support-system/staff/dashboard.php',
        default => '/it-support-system/client/dashboard.php',
    };
}

<?php
require_once __DIR__ . '/config/config.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $server = new PDO(
        'mysql:host=' . DB_HOST . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $server->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdo = db();
    $sql = file_get_contents(__DIR__ . '/database.sql');
    $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql)));

    foreach ($statements as $statement) {
        if ($statement !== '' && stripos($statement, 'CREATE DATABASE') !== 0 && stripos($statement, 'USE ') !== 0) {
            $pdo->exec($statement);
        }
    }
    $userColumns = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_img_url'")->fetch();
    if (!$userColumns) {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_img_url VARCHAR(255) NULL AFTER role");
    }

    $demoUsers = [
        ['System Administrator', 'admin@supporthub.test', 'admin123', 'admin'],
        ['IT Support Staff', 'staff@supporthub.test', 'staff123', 'staff'],
        ['Demo Client', 'client@supporthub.test', 'client123', 'client'],
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO users(name,email,password,role) VALUES(?,?,?,?)");
    foreach ($demoUsers as $u) {
        $stmt->execute([$u[0], $u[1], password_hash($u[2], PASSWORD_DEFAULT), $u[3]]);
    }

    echo '<h2>SupportHub setup completed successfully.</h2>';
    echo '<p><strong>Login accounts:</strong></p>';
    echo '<ul>';
    echo '<li>Admin: admin@supporthub.test / admin123</li>';
    echo '<li>Staff: staff@supporthub.test / staff123</li>';
    echo '<li>Client: client@supporthub.test / client123</li>';
    echo '</ul>';
    echo '<p><a href="/it-support-system/login.php">Go to Login</a></p>';
    echo '<p><strong>Important:</strong> Delete or rename setup.php after setup.</p>';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h2>Setup failed</h2>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
}

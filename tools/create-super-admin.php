<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI access only.');
}

require_once dirname(__DIR__) . '/app/shared/auth.php';

echo "BCP Super Administrator Setup\n";
echo "=============================\n\n";
echo "Run database/migrations/027_super_admin.sql first.\n\n";

echo 'Username: ';
$username = trim((string) fgets(STDIN));

echo 'Email (optional, press Enter to skip): ';
$email = trim((string) fgets(STDIN));

echo 'Password (minimum 12 characters): ';
$password = rtrim((string) fgets(STDIN), "\r\n");

if (!preg_match('/^[a-zA-Z0-9_.-]{4,80}$/', $username) || strlen($password) < 12) {
    exit("Invalid username or password.\n");
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Invalid email address.\n");
}

try {
    $db = authDb();

    $columns = $db->query('SHOW COLUMNS FROM auth_users')->fetchAll(PDO::FETCH_COLUMN);
    $hasEmail = in_array('email', $columns, true);
    $hasVerified = in_array('email_verified_at', $columns, true);
    $hasPasswordChanged = in_array('password_changed_at', $columns, true);

    $fields = ['username', 'password_hash', 'role', 'is_active'];
    $values = [':username', ':password_hash', "'SUPER_ADMIN'", '1'];
    $params = [
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ];

    if ($hasEmail) {
        $fields[] = 'email';
        $values[] = ':email';
        $params['email'] = $email !== '' ? $email : null;
    }
    if ($hasVerified) {
        $fields[] = 'email_verified_at';
        $values[] = $email !== '' ? 'NOW()' : 'NULL';
    }
    if ($hasPasswordChanged) {
        $fields[] = 'password_changed_at';
        $values[] = 'NOW()';
    }

    $sql = 'INSERT INTO auth_users (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    echo "\nSuper Administrator account created successfully.\n";
    echo "Login URL: /app/super-admin/login.php\n";
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        exit("\nUsername already exists.\n");
    }

    error_log('BCP super-admin setup: ' . $e->getMessage());
    exit("\nUnable to create Super Administrator account. Make sure migration 027 was applied.\n");
} finally {
    $password = '';
}

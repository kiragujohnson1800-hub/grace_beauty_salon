<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$name = trim((string)getenv('GRACE_ADMIN_NAME'));
$email = strtolower(trim((string)getenv('GRACE_ADMIN_EMAIL')));
$password = (string)getenv('GRACE_ADMIN_PASSWORD');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Set GRACE_ADMIN_NAME, GRACE_ADMIN_EMAIL, and GRACE_ADMIN_PASSWORD (at least 12 characters) before running this script.\n");
    exit(1);
}

$statement = db()->prepare(
    "INSERT INTO users (name, email, password_hash, role, is_verified) VALUES (?, ?, ?, 'admin', 1)
     ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), role = 'admin', is_verified = 1"
);
$statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Admin account is ready for {$email}.\n");
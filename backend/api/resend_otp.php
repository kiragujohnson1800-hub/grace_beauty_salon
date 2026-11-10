<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/mailer.php';
require_method('POST');
$input = request_data();
$email = strtolower(trim((string)($input['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['success' => false, 'message' => 'Enter a valid email address.'], 422);
}

try {
    $statement = db()->prepare('SELECT id, name FROM users WHERE email = ? AND is_verified = 0');
    $statement->execute([$email]);
    $user = $statement->fetch();
    if (!$user) {
        json_response(['success' => false, 'message' => 'No unverified account was found for that email.'], 404);
    }

    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $update = db()->prepare('UPDATE users SET otp_hash = ?, otp_expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE) WHERE id = ?');
    $update->execute([password_hash($otp, PASSWORD_DEFAULT), $user['id']]);
    send_otp_email($email, $user['name'], $otp);
    json_response(['success' => true, 'message' => 'A new verification code has been sent.']);
} catch (Throwable $error) {
    log_server_error($error);
    json_response(['success' => false, 'message' => 'We could not send a new code. ' . $error->getMessage()], 500);
}
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth.php';
require_method('POST');
$input = request_data();
$email = strtolower(trim((string)($input['email'] ?? '')));
$otp = preg_replace('/\D/', '', (string)($input['otp'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($otp) !== 6) {
    json_response(['success' => false, 'message' => 'Enter the email address and six-digit code.'], 422);
}

try {
    $statement = db()->prepare('SELECT id, otp_hash FROM users WHERE email = ? AND is_verified = 0 AND otp_expires_at > UTC_TIMESTAMP()');
    $statement->execute([$email]);
    $user = $statement->fetch();
    if (!$user || !$user['otp_hash'] || !password_verify($otp, $user['otp_hash'])) {
        json_response(['success' => false, 'message' => 'That code is invalid or expired. Request a new code and try again.'], 422);
    }

    $update = db()->prepare('UPDATE users SET is_verified = 1, otp_hash = NULL, otp_expires_at = NULL WHERE id = ?');
    $update->execute([$user['id']]);
    json_response(['success' => true, 'message' => 'Email verified. You can now sign in.']);
} catch (Throwable $error) {
    log_server_error($error);
    json_response(['success' => false, 'message' => 'Email verification is temporarily unavailable.'], 500);
}
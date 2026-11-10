<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/mailer.php';
require_method('POST');
$input = request_data();
$name = trim((string)($input['name'] ?? ''));
$email = strtolower(trim((string)($input['email'] ?? '')));
$phone = trim((string)($input['phone'] ?? ''));
$password = (string)($input['password'] ?? '');

if ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || strlen($email) > 190 || strlen($password) < 8 || strlen($password) > 200
    || ($phone !== '' && strlen($phone) > 30)) {
    json_response(['success' => false, 'message' => 'Check your name, email, phone number, and password. Passwords must be at least 8 characters.'], 422);
}

try {
    $check = db()->prepare('SELECT id, is_verified FROM users WHERE email = ?');
    $check->execute([$email]);
    $existing = $check->fetch();
    if ($existing && (bool)$existing['is_verified']) {
        json_response(['success' => false, 'message' => 'An account already exists for this email. Please sign in.'], 409);
    }

    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    if ($existing) {
        $statement = db()->prepare('UPDATE users SET name = ?, phone = ?, password_hash = ?, otp_hash = ?, otp_expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE) WHERE id = ?');
        $statement->execute([$name, $phone ?: null, password_hash($password, PASSWORD_DEFAULT), $otpHash, $existing['id']]);
    } else {
        $statement = db()->prepare("INSERT INTO users (name, email, phone, password_hash, role, is_verified, otp_hash, otp_expires_at) VALUES (?, ?, ?, ?, 'customer', 0, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE))");
        $statement->execute([$name, $email, $phone ?: null, password_hash($password, PASSWORD_DEFAULT), $otpHash]);
    }

    try {
        send_otp_email($email, $name, $otp);
    } catch (Throwable $mailError) {
        log_server_error($mailError);
        json_response([
            'success' => false,
            'message' => 'Your account is saved, but the verification email could not be sent. ' . $mailError->getMessage()
                . ' After updating the mail settings, submit this same form again to send a fresh code.',
        ], 502);
    }
    json_response(['success' => true, 'message' => 'We sent a verification code to your email.', 'data' => ['email' => $email]], 201);
} catch (PDOException $error) {
    log_server_error($error);
    if ($error->getCode() === '23000') {
        json_response(['success' => false, 'message' => 'An account already uses that email address. Please sign in or verify the existing account.'], 409);
    }
    json_response(['success' => false, 'message' => 'We could not create your account. Please try again.'], 500);
}
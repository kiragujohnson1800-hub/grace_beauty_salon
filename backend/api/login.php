<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth.php';
require_method('POST');
$input = request_data();
$email = strtolower(trim((string)($input['email'] ?? '')));
$password = (string)($input['password'] ?? '');
$role = trim((string)($input['role'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    json_response(['success' => false, 'message' => 'Enter a valid email address and password.'], 422);
}
if ($role !== '' && !in_array($role, ['customer', 'salonist', 'admin'], true)) {
    json_response(['success' => false, 'message' => 'Choose a valid account type.'], 422);
}

try {
    $statement = db()->prepare('SELECT id, name, email, password_hash, role, is_verified FROM users WHERE email = ? LIMIT 1');
    $statement->execute([$email]);
    $user = $statement->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        json_response(['success' => false, 'message' => 'Email or password is incorrect.'], 401);
    }
    if ($role !== '' && $user['role'] !== $role) {
        json_response(['success' => false, 'message' => 'This account does not match the selected account type.'], 403);
    }
    if (!(bool)$user['is_verified']) {
        json_response(['success' => false, 'message' => 'Please verify your email before signing in.', 'needs_verification' => true], 403);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    unset($user['password_hash'], $user['is_verified']);
    json_response(['success' => true, 'data' => ['user' => $user, 'csrf_token' => csrf_token()]]);
} catch (Throwable $error) {
    log_server_error($error);
    json_response(['success' => false, 'message' => 'Sign in is temporarily unavailable. Please try again.'], 500);
}
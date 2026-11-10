<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth.php';
require_method('GET');
try {
    $user = current_user();
    if (!$user) {
        json_response(['success' => false, 'message' => 'Please sign in to continue.'], 401);
    }
    json_response(['success' => true, 'data' => ['user' => $user, 'csrf_token' => csrf_token()]]);
} catch (Throwable $error) {
    log_server_error($error);
    json_response(['success' => false, 'message' => 'Your account could not be loaded. Please try again.'], 500);
}
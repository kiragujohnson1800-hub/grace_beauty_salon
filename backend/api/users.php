<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth.php';
require_method('GET', 'POST', 'DELETE');

try {
    require_role(['admin']);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $role = (string)($_GET['role'] ?? '');
        if ($role !== '' && !in_array($role, ['customer', 'salonist'], true)) {
            json_response(['success' => false, 'message' => 'Choose a valid user type.'], 422);
        }
        if ($role === '') {
            $statement = db()->query("SELECT id, name, email, phone, role, is_verified, created_at FROM users WHERE role IN ('customer', 'salonist') ORDER BY role, name");
            $users = $statement->fetchAll();
        } else {
            $statement = db()->prepare('SELECT id, name, email, phone, role, is_verified, created_at FROM users WHERE role = ? ORDER BY name');
            $statement->execute([$role]);
            $users = $statement->fetchAll();
        }
        json_response(['success' => true, 'data' => ['users' => $users]]);
    }

    require_csrf();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = request_data();
        $name = trim((string)($input['name'] ?? ''));
        $email = strtolower(trim((string)($input['email'] ?? '')));
        $phone = trim((string)($input['phone'] ?? ''));
        $password = (string)($input['password'] ?? '');
        if ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($password) < 12 || strlen($password) > 200 || strlen($phone) > 30) {
            json_response(['success' => false, 'message' => 'Enter valid details and a temporary password of at least 12 characters.'], 422);
        }
        $statement = db()->prepare("INSERT INTO users (name, email, phone, password_hash, role, is_verified) VALUES (?, ?, ?, ?, 'salonist', 1)");
        $statement->execute([$name, $email, $phone ?: null, password_hash($password, PASSWORD_DEFAULT)]);
        json_response(['success' => true, 'message' => 'Salonist account created.', 'data' => ['id' => (int)db()->lastInsertId()]], 201);
    }

    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) {
        json_response(['success' => false, 'message' => 'Choose a valid user.'], 422);
    }
    $delete = db()->prepare("DELETE FROM users WHERE id = ? AND role IN ('customer', 'salonist')");
    $delete->execute([$id]);
    if ($delete->rowCount() === 0) {
        json_response(['success' => false, 'message' => 'User not found or cannot be deleted.'], 404);
    }
    json_response(['success' => true, 'message' => 'User deleted.']);
} catch (PDOException $error) {
    log_server_error($error);
    if ($error->getCode() === '23000') {
        json_response(['success' => false, 'message' => 'An account already uses that email address.'], 409);
    }
    json_response(['success' => false, 'message' => 'The user request could not be completed.'], 500);
} catch (Throwable $error) {
    log_server_error($error);
    json_response(['success' => false, 'message' => 'The user request could not be completed.'], 500);
}
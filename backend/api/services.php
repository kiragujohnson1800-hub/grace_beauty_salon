<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth.php';
require_method('GET', 'POST', 'PATCH', 'DELETE');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        require_auth();
        $statement = db()->query('SELECT id, name, description, price, duration_minutes, image_url, is_active FROM services WHERE is_active = 1 ORDER BY display_order, name');
        json_response(['success' => true, 'data' => ['services' => $statement->fetchAll()]]);
    }

    $admin = require_role(['admin']);
    require_csrf();
    $input = request_data();
    if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        $id = filter_var($_GET['id'] ?? $input['id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim((string)($input['name'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $price = filter_var($input['price'] ?? null, FILTER_VALIDATE_FLOAT);
        $duration = filter_var($input['duration_minutes'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $name === '' || strlen($name) > 120 || $price === false || $price < 0 || $duration === false || $duration < 5 || $duration > 600) {
            json_response(['success' => false, 'message' => 'Enter valid service details.'], 422);
        }
        $update = db()->prepare('UPDATE services SET name = ?, description = ?, price = ?, duration_minutes = ? WHERE id = ? AND is_active = 1');
        $update->execute([$name, $description ?: null, $price, $duration, $id]);
        if ($update->rowCount() === 0) {
            $check = db()->prepare('SELECT id FROM services WHERE id = ? AND is_active = 1');
            $check->execute([$id]);
            if (!$check->fetch()) {
                json_response(['success' => false, 'message' => 'Service not found.'], 404);
            }
        }
        json_response(['success' => true, 'message' => 'Service updated.']);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            json_response(['success' => false, 'message' => 'Choose a valid service.'], 422);
        }
        $delete = db()->prepare('UPDATE services SET is_active = 0 WHERE id = ? AND is_active = 1');
        $delete->execute([$id]);
        if ($delete->rowCount() === 0) {
            json_response(['success' => false, 'message' => 'Service not found.'], 404);
        }
        json_response(['success' => true, 'message' => 'Service removed from the menu.']);
    }

    $name = trim((string)($input['name'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $price = filter_var($input['price'] ?? null, FILTER_VALIDATE_FLOAT);
    $duration = filter_var($input['duration_minutes'] ?? null, FILTER_VALIDATE_INT);
    if ($name === '' || strlen($name) > 120 || $price === false || $price < 0 || $duration === false || $duration < 5 || $duration > 600) {
        json_response(['success' => false, 'message' => 'Enter a service name, non-negative price, and duration between 5 and 600 minutes.'], 422);
    }
    $statement = db()->prepare('INSERT INTO services (name, description, price, duration_minutes, created_by) VALUES (?, ?, ?, ?, ?)');
    $statement->execute([$name, $description ?: null, $price, $duration, $admin['id']]);
    json_response(['success' => true, 'message' => 'Service added.', 'data' => ['id' => (int)db()->lastInsertId()]], 201);
} catch (Throwable $error) {
    log_server_error($error);
    json_response(['success' => false, 'message' => 'Services are temporarily unavailable.'], 500);
}
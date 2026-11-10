<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth.php';
require_method('GET', 'POST', 'PATCH', 'DELETE');
$method = $_SERVER['REQUEST_METHOD'];

try {
    $user = require_auth();

    if ($method === 'GET') {
        $sql = 'SELECT a.id, a.salonist_id, a.appointment_date, a.appointment_time, a.notes, a.status, a.created_at,
                       s.id AS service_id, s.name AS service_name, s.price, s.duration_minutes,
                       c.id AS customer_id, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
                       salonist.name AS salonist_name
                FROM appointments a
                JOIN services s ON s.id = a.service_id
                JOIN users c ON c.id = a.customer_id
                LEFT JOIN users salonist ON salonist.id = a.salonist_id';
        if ($user['role'] === 'customer') {
            $sql .= ' WHERE a.customer_id = ?';
            $parameters = [$user['id']];
        } elseif ($user['role'] === 'salonist') {
            $sql .= ' WHERE a.salonist_id = ?';
            $parameters = [$user['id']];
        } else {
            $parameters = [];
        }
        $sql .= ' ORDER BY a.appointment_date DESC, a.appointment_time DESC, a.id DESC';
        $statement = db()->prepare($sql);
        $statement->execute($parameters);
        json_response(['success' => true, 'data' => ['appointments' => $statement->fetchAll()]]);
    }

    require_csrf();
    $input = request_data();

    if ($method === 'POST') {
        if ($user['role'] !== 'customer') {
            json_response(['success' => false, 'message' => 'Only customers can book appointments.'], 403);
        }
        $serviceId = filter_var($input['service_id'] ?? null, FILTER_VALIDATE_INT);
        $date = (string)($input['appointment_date'] ?? '');
        $time = (string)($input['appointment_time'] ?? '');
        $notes = trim((string)($input['notes'] ?? ''));
        $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $timeValue = DateTimeImmutable::createFromFormat('!H:i', $time);
        if (!$serviceId || !$dateValue || $dateValue->format('Y-m-d') !== $date
            || $dateValue < new DateTimeImmutable('today') || !$timeValue || $timeValue->format('H:i') !== $time
            || strlen($notes) > 1000) {
            json_response(['success' => false, 'message' => 'Choose a valid service, future date, time, and notes under 1,000 characters.'], 422);
        }
        $service = db()->prepare('SELECT id FROM services WHERE id = ? AND is_active = 1');
        $service->execute([$serviceId]);
        if (!$service->fetch()) {
            json_response(['success' => false, 'message' => 'That service is not available. Please choose another.'], 422);
        }

        $statement = db()->prepare('INSERT INTO appointments (customer_id, service_id, appointment_date, appointment_time, notes) VALUES (?, ?, ?, ?, ?)');
        $statement->execute([$user['id'], $serviceId, $date, $time, $notes ?: null]);
        json_response(['success' => true, 'message' => 'Appointment request received.', 'data' => ['id' => (int)db()->lastInsertId()]], 201);
    }

    $appointmentId = filter_var($_GET['id'] ?? $input['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$appointmentId) {
        json_response(['success' => false, 'message' => 'Choose a valid appointment.'], 422);
    }
    $statement = db()->prepare('SELECT id, customer_id, salonist_id, status FROM appointments WHERE id = ?');
    $statement->execute([$appointmentId]);
    $appointment = $statement->fetch();
    if (!$appointment) {
        json_response(['success' => false, 'message' => 'Appointment not found.'], 404);
    }

    if ($method === 'DELETE') {
        if ($user['role'] !== 'admin') {
            json_response(['success' => false, 'message' => 'Only an admin can delete appointments.'], 403);
        }
        $delete = db()->prepare('DELETE FROM appointments WHERE id = ?');
        $delete->execute([$appointmentId]);
        json_response(['success' => true, 'message' => 'Appointment deleted.']);
    }

    if ($method === 'PATCH') {
        if (!in_array($user['role'], ['admin', 'salonist'], true)
            || ($user['role'] === 'salonist' && (int)$appointment['salonist_id'] !== $user['id'])) {
            json_response(['success' => false, 'message' => 'You cannot update this appointment.'], 403);
        }
        $status = (string)($input['status'] ?? '');
        if (!in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'], true)) {
            json_response(['success' => false, 'message' => 'Choose a valid appointment status.'], 422);
        }
        $salonistId = $input['salonist_id'] ?? null;
        if ($user['role'] === 'salonist') {
            $salonistId = $user['id'];
        } elseif ($salonistId !== null && $salonistId !== '') {
            $salonistId = filter_var($salonistId, FILTER_VALIDATE_INT);
            if (!$salonistId) {
                json_response(['success' => false, 'message' => 'Choose a valid salonist.'], 422);
            }
            $check = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'salonist' AND is_verified = 1");
            $check->execute([$salonistId]);
            if (!$check->fetch()) {
                json_response(['success' => false, 'message' => 'Choose an active salonist.'], 422);
            }
        }
        $update = db()->prepare('UPDATE appointments SET status = ?, salonist_id = ? WHERE id = ?');
        $update->execute([$status, $salonistId, $appointmentId]);
        json_response(['success' => true, 'message' => 'Appointment updated.']);
    }

    if ($method === 'POST') {
        if ($user['role'] !== 'customer' || (int)$appointment['customer_id'] !== $user['id']) {
            json_response(['success' => false, 'message' => 'You cannot cancel this appointment.'], 403);
        }
        if (in_array($appointment['status'], ['completed', 'cancelled'], true)) {
            json_response(['success' => false, 'message' => 'This appointment can no longer be cancelled.'], 409);
        }
        $cancel = db()->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
        $cancel->execute([$appointmentId]);
        json_response(['success' => true, 'message' => 'Appointment cancelled.']);
    }
} catch (Throwable $error) {
    log_server_error($error);
    json_response(['success' => false, 'message' => 'Appointments are temporarily unavailable. Please try again.'], 500);
}
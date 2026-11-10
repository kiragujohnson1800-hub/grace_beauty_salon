<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $settings = require __DIR__ . '/config.php';
    $database = $settings['database'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $database['host'],
        $database['port'],
        $database['name'],
        $database['charset']
    );
    $pdo = new PDO($dsn, $database['username'], $database['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function json_response(array $payload, int $status = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function request_data(): array
{
    if (!empty($_POST)) {
        return $_POST;
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        json_response(['success' => false, 'message' => 'Request body must be valid JSON.'], 400);
    }

    return $data;
}

function require_method(string ...$methods): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
}

function csrf_token(): string
{
    start_app_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function require_csrf(): void
{
    start_app_session();
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($provided) || empty($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $provided)) {
        json_response(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 419);
    }
}

function current_user(): ?array
{
    start_app_session();
    $id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT id, name, email, phone, role, is_verified, created_at FROM users WHERE id = ?'
    );
    $statement->execute([$id]);
    $user = $statement->fetch();
    if (!$user || (int)$user['is_verified'] !== 1) {
        unset($_SESSION['user_id']);
        return null;
    }

    $user['id'] = (int)$user['id'];
    $user['is_verified'] = (bool)$user['is_verified'];
    return $user;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        json_response(['success' => false, 'message' => 'Please sign in to continue.'], 401);
    }

    return $user;
}

function require_role(array $roles): array
{
    $user = require_auth();
    if (!in_array($user['role'], $roles, true)) {
        json_response(['success' => false, 'message' => 'You do not have permission to do that.'], 403);
    }

    return $user;
}

function log_server_error(Throwable $error): void
{
    error_log('[Grace Beauty Salon] ' . $error->getMessage());
}

start_app_session();

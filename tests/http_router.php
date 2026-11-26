<?php
declare(strict_types=1);

/**
 * CI-only router. The bootstrap endpoints are available only on the disposable
 * localhost PHP server started by feature-tests.yml.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path === '/__test/session') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $sid = (string)($input['sid'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $sid)) {
        http_response_code(400);
        exit('Invalid test session identifier.');
    }
    session_id($sid);
    session_start();
    $_SESSION = [
        'user_id' => (int)($input['user_id'] ?? 0),
        'role_name' => (string)($input['role'] ?? ''),
        'queue_csrf' => 'ci-valid-csrf-token',
        'username' => 'CI ' . (string)($input['role'] ?? '') . ' user',
    ];
    header('Content-Type: application/json');
    echo json_encode(['ready' => true]);
    exit;
}
return false;

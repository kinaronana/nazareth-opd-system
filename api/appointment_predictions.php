<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';
require_once __DIR__ . '/../services/AppointmentPredictionService.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if (empty($_SESSION['user_id']) || (string)($_SESSION['role_name'] ?? '') !== 'Admin') {
    http_response_code(empty($_SESSION['user_id']) ? 401 : 403);
    echo json_encode(['error'=>'Administrator access is required.']); exit;
}
try {
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
    if ($days < 1 || $days > 14) {
        http_response_code(422); echo json_encode(['error'=>'Forecast range must be between 1 and 14 days.']); exit;
    }
    echo json_encode(getAppointmentPredictions($pdo,$days),JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('Appointment prediction error: '.$e->getMessage());
    http_response_code(503);
    echo json_encode(['error'=>'Predictions are temporarily unavailable. Check that the appointment and queue tables are accessible.']);
}

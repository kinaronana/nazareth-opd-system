<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$role = (string) ($_SESSION['role_name'] ?? '');
enforceRoleAccess(['Admin', 'Doctor', 'Patient']);
try {
    $metrics = [];
    if ($role === 'Admin') {
        $metrics = ['patients' => (int) $pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn(), 'doctors' => (int) $pdo->query('SELECT COUNT(*) FROM doctors')->fetchColumn(), 'departments' => (int) $pdo->query('SELECT COUNT(*) FROM departments')->fetchColumn()];
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE appointment_date = ?');
        $stmt->execute([date('Y-m-d')]); $metrics['today'] = (int) $stmt->fetchColumn();
    } elseif ($role === 'Doctor') {
        $stmt = $pdo->prepare('SELECT doctor_id FROM doctors WHERE user_id = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['user_id']]); $doctorId = $stmt->fetchColumn();
        if (!$doctorId) { http_response_code(403); echo json_encode(['error' => 'Doctor profile is not linked to this account.']); exit; }
        $stmt = $pdo->prepare("SELECT status, COUNT(*) AS total FROM appointments WHERE doctor_id = ? AND status IN ('Pending','Approved','Completed') GROUP BY status");
        $stmt->execute([(int) $doctorId]); $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $metrics = ['pending' => (int) ($counts['Pending'] ?? 0), 'approved' => (int) ($counts['Approved'] ?? 0), 'completed' => (int) ($counts['Completed'] ?? 0)];
    } elseif ($role === 'Patient') {
        $stmt = $pdo->prepare('SELECT patient_id FROM patients WHERE user_id = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['user_id']]); $patientId = $stmt->fetchColumn();
        if (!$patientId) { http_response_code(403); echo json_encode(['error' => 'Patient profile is not linked to this account.']); exit; }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status IN ('Pending','Approved') AND appointment_date >= CURDATE()");
        $stmt->execute([(int) $patientId]); $upcoming = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status = 'Completed'");
        $stmt->execute([(int) $patientId]); $completed = (int) $stmt->fetchColumn();
        $metrics = ['upcoming' => $upcoming, 'completed' => $completed, 'prescriptions' => 0, 'results' => 0];
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM prescriptions WHERE patient_id = ?');
            $stmt->execute([(int) $patientId]); $metrics['prescriptions'] = (int) $stmt->fetchColumn();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM test_results WHERE patient_id = ?');
            $stmt->execute([(int) $patientId]); $metrics['results'] = (int) $stmt->fetchColumn();
        } catch (Throwable $ignored) { /* Optional tables may not yet exist on older installations. */ }
    }
    echo json_encode(['metrics' => $metrics, 'updated_at' => date(DATE_ATOM)], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Dashboard metrics are temporarily unavailable.']);
}

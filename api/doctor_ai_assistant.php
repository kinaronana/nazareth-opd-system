<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';
require_once __DIR__ . '/../services/DoctorAIService.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
function doctorAiJson(int $status, array $data): void { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); exit; }
enforceRoleAccess(['Doctor']);
$userId = (int)($_SESSION['user_id'] ?? 0);
$stmt = $pdo->prepare('SELECT doctor_id FROM doctors WHERE user_id = ? LIMIT 1');
$stmt->execute([$userId]);
$doctorId = (int)$stmt->fetchColumn();
if (!$doctorId) doctorAiJson(403, ['error' => 'Your account is not linked to a doctor profile.']);
if (empty($_SESSION['doctor_ai_csrf'])) $_SESSION['doctor_ai_csrf'] = bin2hex(random_bytes(32));
$csrf = (string)$_SESSION['doctor_ai_csrf'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $appointmentId = (int)($_GET['appointment_id'] ?? 0);
        if (!$appointmentId) doctorAiJson(422, ['error' => 'Select an appointment.']);
        $stmt = $pdo->prepare("SELECT a.appointment_id,a.patient_id,a.appointment_date,a.appointment_time,a.reason,a.status,p.full_name,p.gender,p.dob FROM appointments a JOIN patients p ON p.patient_id=a.patient_id WHERE a.appointment_id=? AND a.doctor_id=?");
        $stmt->execute([$appointmentId, $doctorId]);
        $current = $stmt->fetch();
        if (!$current) doctorAiJson(404, ['error' => 'Appointment not found in your assigned roster.']);
        $history = $pdo->prepare("SELECT appointment_date,appointment_time,status,reason FROM appointments WHERE patient_id=? AND doctor_id=? AND appointment_id<>? ORDER BY appointment_date DESC,appointment_time DESC LIMIT 10");
        $history->execute([(int)$current['patient_id'], $doctorId, $appointmentId]);
        $drafts = $pdo->prepare("SELECT draft_id,draft_type,draft_text,status,created_at FROM doctor_ai_drafts WHERE appointment_id=? AND doctor_id=? ORDER BY draft_id DESC LIMIT 20");
        $drafts->execute([$appointmentId, $doctorId]);
        doctorAiJson(200, ['appointment'=>$current, 'history'=>$history->fetchAll(), 'drafts'=>$drafts->fetchAll(), 'csrf'=>$csrf]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: GET, POST'); doctorAiJson(405, ['error'=>'Method not allowed.']); }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input) || !hash_equals($csrf, (string)($input['csrf'] ?? ''))) doctorAiJson(419, ['error'=>'Security token expired. Refresh and try again.']);
    $action = (string)($input['action'] ?? '');
    $draftId = (int)($input['draft_id'] ?? 0);
    if ($action === 'approve' || $action === 'review') {
        $text = trim((string)($input['draft_text'] ?? ''));
        if ($action === 'review' && ($text === '' || strlen($text) > 10000)) doctorAiJson(422, ['error'=>'Draft text must be between 1 and 10,000 characters.']);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT draft_id,status FROM doctor_ai_drafts WHERE draft_id=? AND doctor_id=? FOR UPDATE");
        $stmt->execute([$draftId, $doctorId]);
        $draft = $stmt->fetch();
        if (!$draft) { $pdo->rollBack(); doctorAiJson(404, ['error'=>'Draft not found.']); }
        if ($draft['status'] === 'Approved') { $pdo->rollBack(); doctorAiJson(409, ['error'=>'This draft has already been approved.']); }
        if ($action === 'review') $pdo->prepare("UPDATE doctor_ai_drafts SET draft_text=? WHERE draft_id=? AND doctor_id=?")->execute([$text,$draftId,$doctorId]);
        else $pdo->prepare("UPDATE doctor_ai_drafts SET status='Approved',reviewed_at=CURRENT_TIMESTAMP WHERE draft_id=? AND doctor_id=?")->execute([$draftId,$doctorId]);
        $pdo->prepare('INSERT INTO doctor_ai_audit(draft_id,doctor_id,action) VALUES(?,?,?)')->execute([$draftId,$doctorId,$action==='approve'?'Approved':'Reviewed']);
        $pdo->commit();
        doctorAiJson(200, ['message'=>$action==='approve'?'Draft approved.':'Draft updated and saved for review.']);
    }
    if ($action !== 'generate') doctorAiJson(422, ['error'=>'Unsupported action.']);
    $appointmentId = (int)($input['appointment_id'] ?? 0);
    $type = (string)($input['draft_type'] ?? '');
    $source = trim((string)($input['source_text'] ?? ''));
    if (!in_array($type, ['History Summary','Consultation Note','Follow-up Summary'], true) || $source === '' || strlen($source)>12000) doctorAiJson(422, ['error'=>'Choose a draft type and provide notes of up to 12,000 characters.']);
    $stmt = $pdo->prepare('SELECT patient_id FROM appointments WHERE appointment_id=? AND doctor_id=?');
    $stmt->execute([$appointmentId,$doctorId]);
    $patientId = (int)$stmt->fetchColumn();
    if (!$patientId) doctorAiJson(404, ['error'=>'Appointment not found in your assigned roster.']);
    $draftText = generateDoctorAIDraft($type, $source);
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO doctor_ai_drafts(doctor_id,patient_id,appointment_id,draft_type,source_text,draft_text) VALUES(?,?,?,?,?,?)");
    $stmt->execute([$doctorId,$patientId,$appointmentId,$type,$source,$draftText]);
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO doctor_ai_audit(draft_id,doctor_id,action) VALUES(?,?,'Created')")->execute([$id,$doctorId]);
    $pdo->commit();
    doctorAiJson(201, ['draft_id'=>$id,'draft_text'=>$draftText,'status'=>'Draft']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Doctor AI assistant request failed.');
    doctorAiJson(500, ['error'=>'Doctor AI assistant is temporarily unavailable.']);
}

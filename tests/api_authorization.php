<?php
declare(strict_types=1);

/**
 * Exercises the real PHP endpoints over HTTP with isolated CI-only sessions.
 * The workflow starts a localhost server and uses only disposable MySQL data.
 */
$base = getenv('OPD_TEST_BASE_URL') ?: 'http://127.0.0.1:8080';
function assertApi(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    echo "PASS: {$message}\n";
}
function httpRequest(string $path, ?array $body = null, ?array $session = null): array {
    $headers = ["Accept: application/json"];
    if ($body !== null) $headers[] = 'Content-Type: application/json';
    if ($session !== null) $headers[] = 'Cookie: PHPSESSID=' . $session['sid'];
    $options = [
        'http' => [
            'method' => $body === null ? 'GET' : 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body === null ? '' : json_encode($body),
            'ignore_errors' => true,
            'timeout' => 10,
        ],
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($GLOBALS['base'] . $path, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $m)) $status = (int)$m[1];
    }
    return ['status' => $status, 'body' => $response === false ? '' : $response];
}
function newSession(string $role, int $userId): array {
    $sid = bin2hex(random_bytes(16));
    $result = httpRequest('/__test/session', ['sid' => $sid, 'role' => $role, 'user_id' => $userId]);
    if ($result['status'] !== 200) throw new RuntimeException('Could not initialise CI session.');
    return ['sid' => $sid];
}
function decoded(array $response): array {
    $data = json_decode($response['body'], true);
    return is_array($data) ? $data : [];
}

$pdo = new PDO(
    'mysql:host=' . (getenv('OPD_TEST_DB_HOST') ?: '127.0.0.1') . ';dbname=' . (getenv('OPD_TEST_DB_NAME') ?: 'nazareth_opd_test') . ';charset=utf8mb4',
    getenv('OPD_TEST_DB_USER') ?: 'opd_test',
    getenv('OPD_TEST_DB_PASSWORD') ?: 'opd_test_password',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Create a fresh approved booking for patient one because the database-level
// integration suite has already completed its own appointment fixture.
$pdo->prepare("INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,reason,status) VALUES (1,1,CURDATE(),'12:00:00','Authenticated API check-in booking','Approved')")->execute();
$patientAppointmentId = (int)$pdo->lastInsertId();

// Add a second fictional clinician and an appointment owned by patient two.
$pdo->exec("INSERT INTO users (username,password,email,role_id,status) VALUES ('ci_doctor_two','test-hash','doctor2@example.test',2,'Active')");
$secondDoctorUserId = (int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO doctors (user_id,department_id,name) VALUES (?,1,?)')->execute([$secondDoctorUserId, 'Dr Second Test Clinician']);
$secondDoctorId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,reason,status) VALUES (2,?,CURDATE(),'11:00:00','Second clinician test booking','Approved')")->execute([$secondDoctorId]);
$secondAppointmentId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO patient_queue (appointment_id,patient_id,doctor_id,queue_date,queue_number) VALUES (?,2,?,CURDATE(),1)")->execute([$secondAppointmentId, $secondDoctorId]);
$otherDoctorQueueId = (int)$pdo->lastInsertId();

$admin = newSession('Admin', 1);
$doctor = newSession('Doctor', 2);
$doctorTwo = newSession('Doctor', $secondDoctorUserId);
$patient = newSession('Patient', 3);
$patientTwo = newSession('Patient', 4);


// Admin-only appointment predictions API.
$r = httpRequest('/api/appointment_predictions.php', null, $admin);
$predictionData = decoded($r);
assertApi($r['status'] === 200 && isset($predictionData['summary']['expected_appointments'], $predictionData['days'], $predictionData['doctor_workloads'], $predictionData['queue_estimates']), 'Admin can retrieve appointment and queue forecasts');
assertApi(($predictionData['data_quality']['confidence'] ?? '') === 'Insufficient data', 'Prediction API warns about sparse historical data');
$r = httpRequest('/api/appointment_predictions.php', null, $doctor);
assertApi($r['status'] === 403, 'Doctor cannot access admin predictions API');
$r = httpRequest('/api/appointment_predictions.php', null, $patient);
assertApi($r['status'] === 403, 'Patient cannot access admin predictions API');

// Role-specific dashboard APIs.
$r = httpRequest('/api/dashboard_metrics.php', null, $admin);
$d = decoded($r);
assertApi($r['status'] === 200 && isset($d['metrics']['patients'], $d['metrics']['doctors'], $d['metrics']['departments'], $d['metrics']['today']), 'Admin dashboard API returns only admin metrics');
$r = httpRequest('/api/dashboard_metrics.php', null, $doctor);
$d = decoded($r);
assertApi($r['status'] === 200 && isset($d['metrics']['pending'], $d['metrics']['approved'], $d['metrics']['completed']) && !isset($d['metrics']['patients']), 'Doctor dashboard API returns doctor-scoped metrics');
$r = httpRequest('/api/dashboard_metrics.php', null, $patient);
$d = decoded($r);
assertApi($r['status'] === 200 && isset($d['metrics']['upcoming'], $d['metrics']['completed']) && !isset($d['metrics']['patients']), 'Patient dashboard API returns patient-scoped metrics');

// Queue read access and role restrictions.
$r = httpRequest('/api/patient_queue.php', null, $patient);
assertApi($r['status'] === 200 && isset(decoded($r)['appointments']), 'Patient can read only their own queue appointments');
$r = httpRequest('/api/patient_queue.php', null, $doctor);
assertApi($r['status'] === 200 && isset(decoded($r)['queue']), 'Doctor can read their assigned queue');
$r = httpRequest('/api/patient_queue.php?doctor_id=' . $secondDoctorId, null, $admin);
assertApi($r['status'] === 200 && isset(decoded($r)['queue']), 'Admin can inspect a selected clinician queue');

// Patient cannot perform staff actions, and cannot check in another patient's booking.
$r = httpRequest('/api/patient_queue.php', ['action'=>'call','queue_id'=>$otherDoctorQueueId,'csrf'=>'ci-valid-csrf-token'], $patient);
assertApi($r['status'] === 403, 'Patient is denied staff-only queue actions');
$r = httpRequest('/api/patient_queue.php', ['action'=>'check_in','appointment_id'=>2,'csrf'=>'ci-valid-csrf-token'], $patient);
assertApi($r['status'] === 422, 'Patient cannot check in another patient appointment');
$r = httpRequest('/api/patient_queue.php', ['action'=>'check_in','appointment_id'=>$patientAppointmentId], $patient);
assertApi($r['status'] === 419, 'Queue API rejects missing CSRF token');

// A doctor cannot manage another clinician's queue.
$r = httpRequest('/api/patient_queue.php', ['action'=>'set_priority','queue_id'=>$otherDoctorQueueId,'priority'=>'Urgent','csrf'=>'ci-valid-csrf-token'], $doctor);
assertApi($r['status'] === 404, 'Doctor cannot update another doctor queue');
$r = httpRequest('/api/patient_queue.php', ['action'=>'set_priority','queue_id'=>$otherDoctorQueueId,'priority'=>'Urgent','csrf'=>'ci-valid-csrf-token'], $doctorTwo);
assertApi($r['status'] === 200, 'Assigned doctor can update their own queue priority');

// Patient check-in is limited to today's approved appointment and is idempotent.
$pdo->exec("UPDATE appointments SET appointment_date=DATE_ADD(CURDATE(), INTERVAL 1 DAY) WHERE appointment_id={$patientAppointmentId}");
$r = httpRequest('/api/patient_queue.php', ['action'=>'check_in','appointment_id'=>$patientAppointmentId,'csrf'=>'ci-valid-csrf-token'], $patient);
assertApi($r['status'] === 422, 'Patient cannot check in before the appointment date');
$pdo->exec("UPDATE appointments SET appointment_date=CURDATE() WHERE appointment_id={$patientAppointmentId}");
$r = httpRequest('/api/patient_queue.php', ['action'=>'check_in','appointment_id'=>$patientAppointmentId,'csrf'=>'ci-valid-csrf-token'], $patient);
$first = decoded($r);
assertApi($r['status'] === 201 && isset($first['queue_id']), 'Patient can check in for their approved appointment today');
$r = httpRequest('/api/patient_queue.php', ['action'=>'check_in','appointment_id'=>$patientAppointmentId,'csrf'=>'ci-valid-csrf-token'], $patient);
assertApi($r['status'] === 200 && (int)(decoded($r)['queue_id'] ?? 0) === (int)$first['queue_id'], 'Repeated patient check-in returns the existing queue entry');

// Authenticated page middleware blocks the wrong role.
$r = httpRequest('/admin/queue.php', null, $patient);
assertApi($r['status'] === 403, 'Patient cannot open the Admin queue page');
$r = httpRequest('/doctor/queue.php', null, $patient);
assertApi($r['status'] === 403, 'Patient cannot open the Doctor queue page');

// Patient Support Assistant API: ownership, CSRF, persistence and deletion.
$r = httpRequest('/api/patient_ai_assistant.php', null, $patient);
$patientAssistant = decoded($r);
assertApi($r['status'] === 200 && isset($patientAssistant['conversations'], $patientAssistant['csrf']), 'Patient can list assistant conversations and receive a CSRF token');
$patientAiCsrf = $patientAssistant['csrf'];

$r = httpRequest('/api/patient_ai_assistant.php', ['action'=>'create','csrf'=>$patientAiCsrf], $patient);
$createdConversation = decoded($r);
$conversationId = (int)($createdConversation['conversation_id'] ?? 0);
assertApi($r['status'] === 201 && $conversationId > 0, 'Patient can create an assistant conversation');

$r = httpRequest('/api/patient_ai_assistant.php', ['action'=>'send','conversation_id'=>$conversationId,'message'=>'How do I book an appointment?','csrf'=>$patientAiCsrf], $patient);
$firstAssistantReply = decoded($r)['reply'] ?? '';
assertApi($r['status'] === 200 && strpos($firstAssistantReply, 'Book Appointment') !== false, 'Patient message reaches the mocked Gemini provider and receives a generated response');

$r = httpRequest('/api/patient_ai_assistant.php', ['action'=>'send','conversation_id'=>$conversationId,'message'=>'Can you repeat what I asked?','csrf'=>$patientAiCsrf], $patient);
$contextualReply = decoded($r)['reply'] ?? '';
assertApi($r['status'] === 200 && strpos($contextualReply, 'previously asked how to book') !== false, 'Gemini request includes recent conversation context');

$r = httpRequest('/api/patient_ai_assistant.php?conversation_id=' . $conversationId, null, $patient);
$storedConversation = decoded($r);
$storedMessages = $storedConversation['messages'] ?? [];
assertApi($r['status'] === 200 && count($storedMessages) === 4 && ($storedMessages[0]['sender'] ?? '') === 'patient' && ($storedMessages[1]['sender'] ?? '') === 'assistant', 'Patient and assistant messages are persisted in conversation history');

$r = httpRequest('/api/patient_ai_assistant.php', ['action'=>'send','conversation_id'=>$conversationId,'message'=>'mock unsafe response','csrf'=>$patientAiCsrf], $patient);
$unsafeFallback = decoded($r)['reply'] ?? '';
assertApi($r['status'] === 200 && strpos($unsafeFallback, 'I can help with registration') !== false && stripos($unsafeFallback, 'pneumonia') === false, 'Clinically unsafe Gemini output is discarded in favour of the local safe fallback');

$r = httpRequest('/api/patient_ai_assistant.php', ['action'=>'send','conversation_id'=>$conversationId,'message'=>'mock blocked response','csrf'=>$patientAiCsrf], $patient);
$blockedFallback = decoded($r)['reply'] ?? '';
assertApi($r['status'] === 200 && strpos($blockedFallback, 'I can help with registration') !== false, 'Gemini safety-blocked response uses the local fallback');

$r = httpRequest('/api/patient_ai_assistant.php?conversation_id=' . $conversationId, null, $patientTwo);
assertApi($r['status'] === 404, 'A different patient cannot read another patient conversation');
$otherPatientAssistant = decoded(httpRequest('/api/patient_ai_assistant.php', null, $patientTwo));
$otherPatientAiCsrf = $otherPatientAssistant['csrf'] ?? '';
assertApi($otherPatientAiCsrf !== '', 'Second patient receives their own assistant CSRF token');
$r = httpRequest('/api/patient_ai_assistant.php', ['action'=>'delete','conversation_id'=>$conversationId,'csrf'=>$otherPatientAiCsrf], $patientTwo);
assertApi($r['status'] === 404, 'A different patient cannot delete another patient conversation');

$r = httpRequest('/api/patient_ai_assistant.php', ['action'=>'send','conversation_id'=>$conversationId,'message'=>'Test without token'], $patient);
assertApi($r['status'] === 419, 'Patient assistant API rejects requests without a CSRF token');

$r = httpRequest('/api/patient_ai_assistant.php', ['action'=>'create','csrf'=>'ci-valid-csrf-token'], $doctor);
assertApi($r['status'] === 403, 'Doctor cannot access the patient assistant API');
$r = httpRequest('/api/patient_ai_assistant.php', ['action'=>'create','csrf'=>'ci-valid-csrf-token'], $admin);
assertApi($r['status'] === 403, 'Admin cannot access the patient assistant API');

$r = httpRequest('/api/patient_ai_assistant.php', ['action'=>'unsupported','conversation_id'=>$conversationId,'csrf'=>$patientAiCsrf], $patient);
assertApi($r['status'] === 422, 'Patient assistant API rejects unsupported actions');

$r = httpRequest('/api/patient_ai_assistant.php', ['action'=>'delete','conversation_id'=>$conversationId,'csrf'=>$patientAiCsrf], $patient);
assertApi($r['status'] === 200, 'Conversation owner can delete their conversation');
$r = httpRequest('/api/patient_ai_assistant.php?conversation_id=' . $conversationId, null, $patient);
assertApi($r['status'] === 404, 'Deleted assistant conversation and its message history are no longer accessible');

// Doctor AI documentation: assigned clinician access, CSRF, draft review and approval.
$doctorAiRead = httpRequest('/api/doctor_ai_assistant.php?appointment_id=1', null, $doctor);
$doctorAiData = decoded($doctorAiRead);
assertApi($doctorAiRead['status'] === 200 && isset($doctorAiData['appointment']['full_name'], $doctorAiData['csrf']) && !isset($doctorAiData['appointment']['patient_id'],$doctorAiData['appointment']['gender'],$doctorAiData['appointment']['dob']), 'Assigned doctor receives minimum appointment context without unnecessary patient identifiers or demographics');
$doctorAiCsrf = $doctorAiData['csrf'];
$doctorAiGenerate = httpRequest('/api/doctor_ai_assistant.php', ['action'=>'generate','appointment_id'=>1,'draft_type'=>'Consultation Note','source_text'=>'Fictional note: patient reports mild headache since yesterday. No other information provided.','csrf'=>$doctorAiCsrf], $doctor);
$doctorAiDraft = decoded($doctorAiGenerate);
$doctorAiDraftId = (int)($doctorAiDraft['draft_id'] ?? 0);
assertApi($doctorAiGenerate['status'] === 201 && $doctorAiDraftId > 0 && ($doctorAiDraft['status'] ?? '') === 'Draft', 'Assigned doctor can generate an unapproved documentation draft');
$wrongDoctorRead = httpRequest('/api/doctor_ai_assistant.php?appointment_id=1', null, $doctorTwo);
$wrongDoctorCsrf = decoded(httpRequest('/api/doctor_ai_assistant.php?appointment_id='.$secondAppointmentId, null, $doctorTwo))['csrf'] ?? '';
assertApi($wrongDoctorRead['status'] === 404, 'Different doctor cannot read another doctor appointment context');
assertApi(!isset($doctorAiData['appointment']['reason']) && !isset($doctorAiData['history'][0]['reason']), 'Doctor AI context omits free-text appointment reasons from its response');
$wrongDoctorContext = decoded(httpRequest('/api/doctor_ai_assistant.php?appointment_id='.$secondAppointmentId, null, $doctorTwo));
assertApi(!in_array($doctorAiDraftId, array_map(static fn($item)=>(int)$item['draft_id'], $wrongDoctorContext['drafts'] ?? []), true), 'A different doctor cannot see another clinician draft in their appointment context');
$approveBeforeReview = httpRequest('/api/doctor_ai_assistant.php', ['action'=>'approve','draft_id'=>$doctorAiDraftId,'csrf'=>$doctorAiCsrf], $doctor);
assertApi($approveBeforeReview['status'] === 409 && (int)$pdo->query('SELECT COUNT(*) FROM medical_records')->fetchColumn() === 0, 'API blocks approval and record creation until a draft has been reviewed');
$wrongDoctorAction = httpRequest('/api/doctor_ai_assistant.php', ['action'=>'approve','draft_id'=>$doctorAiDraftId,'csrf'=>$wrongDoctorCsrf], $doctorTwo);
assertApi($wrongDoctorAction['status'] === 404, 'Different doctor cannot approve another doctor draft');
$noCsrf = httpRequest('/api/doctor_ai_assistant.php', ['action'=>'generate','appointment_id'=>1,'draft_type'=>'Consultation Note','source_text'=>'Fictional note'], $doctor);
assertApi($noCsrf['status'] === 419, 'Doctor AI API rejects missing CSRF token');
$review = httpRequest('/api/doctor_ai_assistant.php', ['action'=>'review','draft_id'=>$doctorAiDraftId,'draft_text'=>'Reviewed fictional consultation note. Missing fields remain not provided.','csrf'=>$doctorAiCsrf], $doctor);
assertApi($review['status'] === 200, 'Doctor can edit and save draft for review');
$reviewedState = $pdo->query('SELECT status,reviewed_at FROM doctor_ai_drafts WHERE draft_id=' . $doctorAiDraftId)->fetch(PDO::FETCH_ASSOC);
assertApi($review['status'] === 200 && $reviewedState['status'] === 'Reviewed' && !empty($reviewedState['reviewed_at']), 'Saving reviewed edits records a separate review state and timestamp');
$approve = httpRequest('/api/doctor_ai_assistant.php', ['action'=>'approve','draft_id'=>$doctorAiDraftId,'csrf'=>$doctorAiCsrf], $doctor);
$approvedData = decoded($approve);
$storedRecord = $pdo->query('SELECT record_id,patient_id,doctor_id,appointment_id,record_type,title,description FROM medical_records WHERE record_id=' . (int)($approvedData['medical_record_id'] ?? 0))->fetch(PDO::FETCH_ASSOC);
$approvedState = $pdo->query('SELECT status,patient_id,doctor_id,appointment_id,reviewed_at,approved_at,medical_record_id FROM doctor_ai_drafts WHERE draft_id=' . $doctorAiDraftId)->fetch(PDO::FETCH_ASSOC);
$auditActions = $pdo->query('SELECT action FROM doctor_ai_audit WHERE draft_id=' . $doctorAiDraftId . ' ORDER BY audit_id')->fetchAll(PDO::FETCH_COLUMN);
assertApi($approve['status'] === 200 && !empty($approvedData['medical_record_id']) && $storedRecord && $storedRecord['description'] === 'Reviewed fictional consultation note. Missing fields remain not provided.', 'Approval creates the corresponding patient medical record using the reviewed text');
assertApi($approvedState['status'] === 'Approved' && !empty($approvedState['reviewed_at']) && !empty($approvedState['approved_at']) && (int)$approvedState['medical_record_id'] === (int)$storedRecord['record_id'], 'Approved draft stores distinct review and approval timestamps and its linked record');
assertApi($storedRecord && (int)$storedRecord['patient_id'] === (int)$approvedState['patient_id'] && (int)$storedRecord['doctor_id'] === (int)$approvedState['doctor_id'] && (int)$storedRecord['appointment_id'] === (int)$approvedState['appointment_id'], 'Medical record is linked to the exact patient, doctor and appointment on the approved draft');
assertApi($auditActions === ['Created','Reviewed','Approved'], 'Draft audit trail records creation, review and approval in order');
$approvedAgain = httpRequest('/api/doctor_ai_assistant.php', ['action'=>'approve','draft_id'=>$doctorAiDraftId,'csrf'=>$doctorAiCsrf], $doctor);
$editApproved = httpRequest('/api/doctor_ai_assistant.php', ['action'=>'review','draft_id'=>$doctorAiDraftId,'draft_text'=>'Attempted post-approval edit','csrf'=>$doctorAiCsrf], $doctor);
assertApi($approvedAgain['status'] === 409 && $editApproved['status'] === 409 && (int)$pdo->query('SELECT COUNT(*) FROM medical_records')->fetchColumn() === 1, 'Approved drafts cannot be approved twice or edited and cannot create duplicate medical records');
$patientDenied = httpRequest('/api/doctor_ai_assistant.php?appointment_id=1', null, $patient);
assertApi($patientDenied['status'] === 403, 'Patient cannot access doctor AI documentation API');
$adminDenied = httpRequest('/api/doctor_ai_assistant.php?appointment_id=1', null, $admin);
assertApi($adminDenied['status'] === 403, 'Admin cannot access doctor AI documentation API');
$unauthPage = httpRequest('/doctor/ai_assistant.php', null, $patient);
assertApi($unauthPage['status'] === 403, 'Patient cannot open doctor AI documentation page');

echo "All authenticated PHP API checks passed.\n";

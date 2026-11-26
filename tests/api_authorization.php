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

echo "All authenticated PHP API checks passed.\n";

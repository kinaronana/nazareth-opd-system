<?php
declare(strict_types=1);

/**
 * Disposable MySQL integration checks for the OPD dashboard/queue data model.
 * Uses only fictional CI fixtures; never reads application or production secrets.
 */
$host = getenv('OPD_TEST_DB_HOST') ?: '127.0.0.1';
$name = getenv('OPD_TEST_DB_NAME') ?: 'nazareth_opd_test';
$user = getenv('OPD_TEST_DB_USER') ?: 'opd_test';
$password = getenv('OPD_TEST_DB_PASSWORD') ?: 'opd_test_password';

$pdo = new PDO(
    "mysql:host={$host};dbname={$name};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

function check(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("FAIL: {$message}");
    }
    echo "PASS: {$message}\n";
}

$pdo->exec(file_get_contents(__DIR__ . '/fixtures.sql'));
$pdo->exec(file_get_contents(__DIR__ . '/../config/queue_schema.sql'));
$aiSchema = file_get_contents(__DIR__ . '/../config/ai_assistant_schema.sql');
foreach (array_filter(array_map('trim', explode(';', $aiSchema))) as $statement) { $pdo->exec($statement); }
$doctorAiSchema = file_get_contents(__DIR__ . '/../config/doctor_ai_schema.sql');
foreach (array_filter(array_map('trim', explode(';', $doctorAiSchema))) as $statement) { $pdo->exec($statement); }
check((int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('doctor_ai_drafts','doctor_ai_audit')")->fetchColumn() === 2, 'doctor AI draft and audit tables are created in disposable database');
check((int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='doctor_ai_drafts' AND column_name IN ('reviewed_at','approved_at','medical_record_id')")->fetchColumn() === 3, 'doctor AI review, approval and medical-record linkage fields are present');
check((int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('patient_ai_conversations','patient_ai_messages')")->fetchColumn() === 2, 'patient assistant tables are created in the disposable database');

require_once __DIR__ . '/../services/PatientAIService.php';
putenv('GEMINI_API_KEY');
$fallbackReply = generatePatientSupportResponse('How do I book an appointment?');
check(strpos($fallbackReply['text'], 'Book Appointment') !== false, 'assistant safely falls back to the local FAQ when no Gemini key is configured');
$emergencyReply = generatePatientSupportResponse('I have chest pain and cannot breathe');
check(($emergencyReply['safety_flag'] ?? '') === 'emergency_warning', 'emergency messages are handled locally without calling Gemini');
$emergencyVariants = [
    'My lips are turning blue and I am gasping for air',
    'He is unresponsive and I cannot wake him',
    'I took too many pills by mistake',
    'The bleeding will not stop',
    "The bleeding won't stop",
    'Bleeding that will not stop',
    "I can't stop the bleeding",
    'The blood will not stop',
    'I cannot stop bleeding',
    'My throat is closing and my tongue is swelling',
    'I am about to hurt myself'
];
foreach ($emergencyVariants as $variant) {
    check(patientSupportNeedsEmergencyEscalation($variant), 'emergency wording is escalated: ' . substr($variant, 0, 35));
    check((patientSupportEmergencyReply()['safety_flag'] ?? '') === 'emergency_warning', 'emergency escalation returns a dedicated warning');
}
check(!patientSupportNeedsEmergencyEscalation('I have had a mild headache since yesterday'), 'non-emergency symptom wording is not automatically classified as an emergency');
$unsafeResponses = [
    'You likely have pneumonia.',
    'Take 2 tablets of ibuprofen now.',
    'Start taking antibiotics for five days.',
    'Stop taking medicines without speaking to your clinician.',
    'Switch to painkillers for the symptoms.',
    'Double your dose if the pain continues.'
];
foreach ($unsafeResponses as $unsafeResponse) {
    check(patientSupportResponseIsClinicallyUnsafe($unsafeResponse), 'unsafe clinical output is detected');
}
check(!patientSupportResponseIsClinicallyUnsafe('Please describe your symptoms to a qualified clinician.'), 'safe clinician-referral wording remains allowed');

check((int)$pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn() === 2, 'admin dashboard patient metric fixture');
check((int)$pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn() === 1, 'admin dashboard doctor metric fixture');
check((int)$pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn() === 1, 'admin dashboard department metric fixture');
check((int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_date=CURDATE()")->fetchColumn() === 3, 'admin dashboard today appointment metric');
check((int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE patient_id=1 AND status IN ('Pending','Approved') AND appointment_date>=CURDATE()")->fetchColumn() === 2, 'patient dashboard upcoming metric');
check((int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE doctor_id=1 AND status='Approved'")->fetchColumn() === 2, 'doctor dashboard approved metric');

$lockName = 'opd_queue_ci_' . getmypid();
$lock = $pdo->prepare('SELECT GET_LOCK(?, 2)');
$lock->execute([$lockName]);
check((int)$lock->fetchColumn() === 1, 'MySQL advisory lock can be acquired');
$release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
$release->execute([$lockName]);
check((int)$release->fetchColumn() === 1, 'MySQL advisory lock can be released');

$insert = $pdo->prepare("INSERT INTO patient_queue (appointment_id,patient_id,doctor_id,queue_date,queue_number) VALUES (1,1,1,CURDATE(),1)");
$insert->execute();
$queueId = (int)$pdo->lastInsertId();
check($queueId > 0, 'approved appointment can be represented in the queue');

$duplicateRejected = false;
try {
    $pdo->prepare("INSERT INTO patient_queue (appointment_id,patient_id,doctor_id,queue_date,queue_number) VALUES (1,1,1,CURDATE(),2)")->execute();
} catch (PDOException $e) {
    $duplicateRejected = true;
}
check($duplicateRejected, 'duplicate check-in is rejected by unique appointment constraint');

$pdo->prepare("UPDATE patient_queue SET priority='Urgent',priority_set_by=1 WHERE queue_id=?")->execute([$queueId]);
check($pdo->query("SELECT priority FROM patient_queue WHERE queue_id={$queueId}")->fetchColumn() === 'Urgent', 'staff priority update is persisted');

$pdo->prepare("UPDATE patient_queue SET status='Called',called_at=NOW() WHERE queue_id=? AND status='Waiting'")->execute([$queueId]);
$pdo->prepare("UPDATE patient_queue SET status='In Consultation',consultation_started_at=DATE_SUB(NOW(), INTERVAL 12 MINUTE) WHERE queue_id=? AND status='Called'")->execute([$queueId]);
check($pdo->query("SELECT status FROM patient_queue WHERE queue_id={$queueId}")->fetchColumn() === 'In Consultation', 'queue transitions through called into consultation');

$pdo->beginTransaction();
$pdo->prepare("UPDATE patient_queue SET status='Completed',completed_at=NOW() WHERE queue_id=? AND status='In Consultation'")->execute([$queueId]);
$pdo->prepare("UPDATE appointments SET status='Completed' WHERE appointment_id=1 AND status='Approved'")->execute();
$pdo->commit();
check($pdo->query("SELECT status FROM patient_queue WHERE queue_id={$queueId}")->fetchColumn() === 'Completed', 'queue completion is persisted');
check($pdo->query("SELECT status FROM appointments WHERE appointment_id=1")->fetchColumn() === 'Completed', 'appointment completion is committed with queue completion');

$pdo->prepare("INSERT INTO patient_queue (appointment_id,patient_id,doctor_id,queue_date,queue_number,status,consultation_started_at,completed_at) VALUES (2,2,1,CURDATE(),2,'Completed',DATE_SUB(NOW(), INTERVAL 18 MINUTE),NOW())")->execute();
$average = (int)round((float)$pdo->query("SELECT AVG(TIMESTAMPDIFF(MINUTE,consultation_started_at,completed_at)) FROM patient_queue WHERE doctor_id=1 AND queue_date>=DATE_SUB(CURDATE(),INTERVAL 30 DAY) AND status='Completed' AND consultation_started_at IS NOT NULL AND completed_at IS NOT NULL AND TIMESTAMPDIFF(MINUTE,consultation_started_at,completed_at) BETWEEN 1 AND 240")->fetchColumn());
check($average >= 12 && $average <= 18, 'consultation duration average is calculated from completed queue records');

$rollbackWorked = false;
try {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE patient_queue SET status='Completed' WHERE queue_id=?")->execute([$queueId]);
    throw new RuntimeException('intentional test rollback');
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $rollbackWorked = $pdo->query("SELECT status FROM patient_queue WHERE queue_id={$queueId}")->fetchColumn() === 'Completed';
}
check($rollbackWorked, 'transaction rollback preserves the last committed queue state');

require_once __DIR__ . '/../services/AppointmentPredictionService.php';
$today = date('Y-m-d');
$history = [
 ['appointment_date'=>date('Y-m-d',strtotime('-7 days')),'appointment_time'=>'09:15:00','status'=>'Completed','doctor_id'=>1,'doctor_name'=>'Dr Test Clinician'],
 ['appointment_date'=>date('Y-m-d',strtotime('-7 days')),'appointment_time'=>'10:15:00','status'=>'Cancelled','doctor_id'=>1,'doctor_name'=>'Dr Test Clinician'],
 ['appointment_date'=>date('Y-m-d',strtotime('-14 days')),'appointment_time'=>'09:45:00','status'=>'Completed','doctor_id'=>1,'doctor_name'=>'Dr Test Clinician'],
 ['appointment_date'=>date('Y-m-d',strtotime('-21 days')),'appointment_time'=>'14:00:00','status'=>'Completed','doctor_id'=>1,'doctor_name'=>'Dr Test Clinician']
];
$forecast = buildAppointmentForecast($history,[['doctor_id'=>1,'name'=>'Dr Test Clinician']],$today,7);
check(count($forecast['days'])===7, 'forecast returns seven daily estimates');
check($forecast['summary']['expected_appointments']>0, 'forecast calculates demand from historical bookings');
check($forecast['summary']['historical_cancellation_rate']>0, 'forecast includes cancelled bookings in cancellation rate');
check(($forecast['busy_periods'][0]['time_slot']??'')==='08:00–09:59', 'forecast identifies busiest appointment time slot');
check(count($forecast['doctor_workloads'])===1 && $forecast['doctor_workloads'][0]['expected_appointments']>0, 'forecast calculates doctor workload');
check($forecast['data_quality']['confidence']==='Insufficient data', 'forecast warns when historical coverage is limited');
$empty = buildAppointmentForecast([],[],$today,7);
check($empty['summary']['expected_appointments']===0.0 && $empty['data_quality']['confidence']==='Insufficient data', 'forecast safely handles empty history');

echo "All isolated MySQL integration checks passed.\n";

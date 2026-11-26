<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';
require_once __DIR__ . '/../config/helpers.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$role = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId || !in_array($role, ['Patient','Doctor','Admin'], true)) {
    http_response_code(401); echo json_encode(['error'=>'Please sign in again.']); exit;
}
function queueResponse(int $status, array $data): void {
    http_response_code($status); echo json_encode($data); exit;
}
function currentDoctorId(PDO $pdo, int $userId): int {
    $s=$pdo->prepare('SELECT doctor_id FROM doctors WHERE user_id=? LIMIT 1');
    $s->execute([$userId]); return (int)$s->fetchColumn();
}
function averageConsultationMinutes(PDO $pdo, int $doctorId): int {
    $s=$pdo->prepare("SELECT AVG(TIMESTAMPDIFF(MINUTE, consultation_started_at, completed_at))
        FROM patient_queue
        WHERE doctor_id=? AND queue_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
          AND status='Completed' AND consultation_started_at IS NOT NULL
          AND completed_at IS NOT NULL
          AND TIMESTAMPDIFF(MINUTE, consultation_started_at, completed_at) BETWEEN 1 AND 240");
    $s->execute([$doctorId]);
    $average=$s->fetchColumn();
    return $average !== false && $average !== null ? max(1,(int)round((float)$average)) : 15;
}
try {
    $today=date('Y-m-d');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input=json_decode(file_get_contents('php://input'), true) ?: [];
        if (!hash_equals((string)($_SESSION['queue_csrf'] ?? ''), (string)($input['csrf'] ?? ''))) {
            queueResponse(419,['error'=>'Security token expired. Refresh the page and try again.']);
        }
        $action=(string)($input['action'] ?? '');
        if ($action === 'check_in' && $role === 'Patient') {
            $patientStmt=$pdo->prepare('SELECT patient_id FROM patients WHERE user_id=? LIMIT 1');
            $patientStmt->execute([$userId]); $patientId=(int)$patientStmt->fetchColumn();
            $appointmentId=(int)($input['appointment_id'] ?? 0);
            if (!$patientId || !$appointmentId) queueResponse(422,['error'=>'Select a valid appointment.']);
            $appt=$pdo->prepare("SELECT appointment_id,patient_id,doctor_id,appointment_date,status FROM appointments WHERE appointment_id=? AND patient_id=? LIMIT 1");
            $appt->execute([$appointmentId,$patientId]); $appointment=$appt->fetch(PDO::FETCH_ASSOC);
            if (!$appointment || $appointment['status'] !== 'Approved' || $appointment['appointment_date'] !== $today) {
                queueResponse(422,['error'=>'Only an approved appointment scheduled for today can check in.']);
            }
            $lockName='opd_queue_'.$appointment['doctor_id'].'_'.$today;
            $lock=$pdo->prepare('SELECT GET_LOCK(?, 5)'); $lock->execute([$lockName]);
            if ((int)$lock->fetchColumn() !== 1) queueResponse(409,['error'=>'The queue is busy. Please try again shortly.']);
            $checkInStatus=201;
            $checkInData=[];
            try {
                $pdo->beginTransaction();
                $existing=$pdo->prepare('SELECT queue_id FROM patient_queue WHERE appointment_id=? LIMIT 1');
                $existing->execute([$appointmentId]); $existingId=(int)$existing->fetchColumn();
                if ($existingId) {
                    $pdo->commit();
                    $checkInStatus=200;
                    $checkInData=['message'=>'You are already checked in.','queue_id'=>$existingId];
                } else {
                    $next=$pdo->prepare('SELECT COALESCE(MAX(queue_number),0)+1 FROM patient_queue WHERE doctor_id=? AND queue_date=?');
                    $next->execute([(int)$appointment['doctor_id'],$today]); $number=(int)$next->fetchColumn();
                    $insert=$pdo->prepare("INSERT INTO patient_queue (appointment_id,patient_id,doctor_id,queue_date,queue_number) VALUES (?,?,?,?,?)");
                    $insert->execute([$appointmentId,$patientId,(int)$appointment['doctor_id'],$today,$number]);
                    $queueId=(int)$pdo->lastInsertId();
                    $pdo->commit();
                    $checkInData=['message'=>'Check-in successful.','queue_id'=>$queueId,'queue_number'=>$number];
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            } finally {
                try {
                    $release=$pdo->prepare('SELECT RELEASE_LOCK(?)');
                    $release->execute([$lockName]);
                } catch (Throwable $releaseError) {
                    error_log('Queue lock release error: '.$releaseError->getMessage());
                }
            }
            if ($checkInStatus===201) {
                logActivity($pdo,$userId,'Checked in for an appointment. Queue number #'.$checkInData['queue_number'],'ticket');
            }
            queueResponse($checkInStatus,$checkInData);
        }
        if (in_array($action,['call','start','complete','set_priority'],true) && in_array($role,['Doctor','Admin'],true)) {
            $queueId=(int)($input['queue_id'] ?? 0);
            if (!$queueId) queueResponse(422,['error'=>'Invalid queue entry.']);
            $params=[]; $scope='';
            if ($role==='Doctor') {
                $doctorId=currentDoctorId($pdo,$userId);
                if (!$doctorId) queueResponse(403,['error'=>'Your doctor profile is not linked to this account.']);
                $scope=' AND doctor_id=?'; $params[]=$doctorId;
            }
            $find=$pdo->prepare('SELECT queue_id,appointment_id,patient_id,status,doctor_id FROM patient_queue WHERE queue_id=?'.$scope);
            $find->execute(array_merge([$queueId],$params)); $entry=$find->fetch(PDO::FETCH_ASSOC);
            if (!$entry) queueResponse(404,['error'=>'Queue entry not found.']);
            if ($action==='set_priority') {
                $priority=($input['priority'] ?? '')==='Urgent'?'Urgent':'Normal';
                $update=$pdo->prepare('UPDATE patient_queue SET priority=?,priority_set_by=? WHERE queue_id=?');
                $update->execute([$priority,$userId,$queueId]);
            } else {
                $map=['call'=>['Waiting','Called','called_at'],'start'=>['Called','In Consultation','consultation_started_at'],'complete'=>['In Consultation','Completed','completed_at']];
                [$from,$to,$column]=$map[$action];
                if ($entry['status']!==$from) queueResponse(409,['error'=>'This queue entry has changed. Refresh and try again.']);
                if ($action==='complete') {
                    $pdo->beginTransaction();
                    try {
                        $update=$pdo->prepare("UPDATE patient_queue SET status=?, {$column}=NOW() WHERE queue_id=? AND status=?");
                        $update->execute([$to,$queueId,$from]);
                        if ($update->rowCount()!==1) {
                            $pdo->rollBack();
                            queueResponse(409,['error'=>'This queue entry has changed. Refresh and try again.']);
                        }
                        $appointmentUpdate=$pdo->prepare("UPDATE appointments SET status='Completed' WHERE appointment_id=? AND status='Approved'");
                        $appointmentUpdate->execute([(int)$entry['appointment_id']]);
                        if ($appointmentUpdate->rowCount()!==1) {
                            $pdo->rollBack();
                            queueResponse(409,['error'=>'The appointment is no longer in an approved state. Refresh the queue and try again.']);
                        }
                        $pdo->commit();
                    } catch (Throwable $transactionError) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        throw $transactionError;
                    }
                } else {
                    $update=$pdo->prepare("UPDATE patient_queue SET status=?, {$column}=NOW() WHERE queue_id=? AND status=?");
                    $update->execute([$to,$queueId,$from]);
                    if ($update->rowCount()!==1) queueResponse(409,['error'=>'This queue entry has changed. Refresh and try again.']);
                }
                $patient=$pdo->prepare('SELECT p.user_id FROM patient_queue q JOIN patients p ON p.patient_id=q.patient_id WHERE q.queue_id=?');
                $patient->execute([$queueId]); $patientUser=(int)$patient->fetchColumn();
                $messages=['call'=>'Your turn is approaching. Please proceed to the consultation area.','start'=>'Your consultation has started.','complete'=>'Your consultation has been marked complete.'];
                if ($patientUser) {
                    try { notifyUser($pdo,$patientUser,$messages[$action],'/patient/queue.php'); }
                    catch (Throwable $notificationError) { error_log('Queue notification error: '.$notificationError->getMessage()); }
                }
                logActivity($pdo,$userId,'Queue entry #'.$queueId.' updated to '.$to,'clipboard-check');
            }
            queueResponse(200,['message'=>'Queue updated successfully.']);
        }
        queueResponse(403,['error'=>'You are not allowed to perform this action.']);
    }

    if ($role==='Patient') {
        $s=$pdo->prepare('SELECT patient_id FROM patients WHERE user_id=? LIMIT 1'); $s->execute([$userId]); $patientId=(int)$s->fetchColumn();
        if (!$patientId) queueResponse(403,['error'=>'Patient profile not found.']);
        $appt=$pdo->prepare("SELECT a.appointment_id,a.appointment_time,d.name AS doctor_name,dept.department_name,q.queue_id,q.queue_number,q.status,q.checked_in_at,q.priority FROM appointments a JOIN doctors d ON d.doctor_id=a.doctor_id LEFT JOIN departments dept ON dept.department_id=d.department_id LEFT JOIN patient_queue q ON q.appointment_id=a.appointment_id WHERE a.patient_id=? AND a.appointment_date=? AND a.status='Approved' ORDER BY a.appointment_time");
        $appt->execute([$patientId,$today]); $appointments=$appt->fetchAll(PDO::FETCH_ASSOC);
        $averageByDoctor=[];
        foreach($appointments as &$a) {
            if ($a['queue_id']) {
                $pos=$pdo->prepare("SELECT COUNT(*) FROM patient_queue WHERE doctor_id=(SELECT doctor_id FROM patient_queue WHERE queue_id=?) AND queue_date=? AND status IN ('Waiting','Called','In Consultation') AND (priority='Urgent' AND ?='Normal' OR priority=? AND queue_number<=?)");
                $pos->execute([(int)$a['queue_id'],$today,$a['priority'],$a['priority'],(int)$a['queue_number']]);
                $a['position']=(int)$pos->fetchColumn();
                $doctorLookup=$pdo->prepare('SELECT doctor_id FROM patient_queue WHERE queue_id=?');
                $doctorLookup->execute([(int)$a['queue_id']]); $queueDoctorId=(int)$doctorLookup->fetchColumn();
                if (!isset($averageByDoctor[$queueDoctorId])) $averageByDoctor[$queueDoctorId]=averageConsultationMinutes($pdo,$queueDoctorId);
                $a['average_consultation_minutes']=$averageByDoctor[$queueDoctorId];
                $a['estimated_wait_minutes']=max(0,($a['position']-1)*$a['average_consultation_minutes']);
                $a['estimate_is_approximate']=true;
            }
        }
        unset($a);
        queueResponse(200,['appointments'=>$appointments,'updated_at'=>date(DATE_ATOM)]);
    }
    if ($role==='Doctor') {
        $doctorId=currentDoctorId($pdo,$userId);
        if (!$doctorId) queueResponse(403,['error'=>'Doctor profile is not linked to this account.']);
    } else {
        $doctorId=(int)($_GET['doctor_id'] ?? 0);
        if (!$doctorId) queueResponse(200,['doctors'=>$pdo->query('SELECT doctor_id,name FROM doctors ORDER BY name')->fetchAll(PDO::FETCH_ASSOC)]);
    }
    $s=$pdo->prepare("SELECT q.queue_id,q.queue_number,q.status,q.priority,q.checked_in_at,p.full_name AS patient_name,a.appointment_time FROM patient_queue q JOIN patients p ON p.patient_id=q.patient_id JOIN appointments a ON a.appointment_id=q.appointment_id WHERE q.doctor_id=? AND q.queue_date=? ORDER BY CASE q.priority WHEN 'Urgent' THEN 0 ELSE 1 END,q.queue_number");
    $s->execute([$doctorId,$today]); $rows=$s->fetchAll(PDO::FETCH_ASSOC);
    $waiting=count(array_filter($rows,fn($r)=>in_array($r['status'],['Waiting','Called'],true)));
    $averageMinutes=averageConsultationMinutes($pdo,$doctorId);
    queueResponse(200,['queue'=>$rows,'waiting'=>$waiting,'average_consultation_minutes'=>$averageMinutes,'estimated_wait_minutes'=>$waiting*$averageMinutes,'estimate_is_approximate'=>true,'updated_at'=>date(DATE_ATOM)]);
} catch (Throwable $e) {
    error_log('Patient queue error: '.$e->getMessage());
    queueResponse(500,['error'=>'The queue service is temporarily unavailable. Confirm that config/queue_schema.sql has been applied.']);
}

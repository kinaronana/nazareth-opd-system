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
function queueResponse(int $status, array $data): never {
    http_response_code($status); echo json_encode($data); exit;
}
function currentDoctorId(PDO $pdo, int $userId): int {
    $s=$pdo->prepare('SELECT doctor_id FROM doctors WHERE user_id=? LIMIT 1');
    $s->execute([$userId]); return (int)$s->fetchColumn();
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
            try {
                $pdo->beginTransaction();
                $existing=$pdo->prepare('SELECT queue_id FROM patient_queue WHERE appointment_id=? LIMIT 1');
                $existing->execute([$appointmentId]); $existingId=(int)$existing->fetchColumn();
                if ($existingId) {
                    $pdo->commit(); queueResponse(200,['message'=>'You are already checked in.','queue_id'=>$existingId]);
                }
                $next=$pdo->prepare('SELECT COALESCE(MAX(queue_number),0)+1 FROM patient_queue WHERE doctor_id=? AND queue_date=?');
                $next->execute([(int)$appointment['doctor_id'],$today]); $number=(int)$next->fetchColumn();
                $insert=$pdo->prepare("INSERT INTO patient_queue (appointment_id,patient_id,doctor_id,queue_date,queue_number) VALUES (?,?,?,?,?)");
                $insert->execute([$appointmentId,$patientId,(int)$appointment['doctor_id'],$today,$number]);
                $queueId=(int)$pdo->lastInsertId();
                $pdo->commit();
                logActivity($pdo,$userId,'Checked in for an appointment. Queue number #'.$number,'ticket');
                queueResponse(201,['message'=>'Check-in successful.','queue_id'=>$queueId,'queue_number'=>$number]);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            } finally {
                $release=$pdo->prepare('SELECT RELEASE_LOCK(?)'); $release->execute([$lockName]);
            }
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
            $find=$pdo->prepare('SELECT queue_id,patient_id,status,doctor_id FROM patient_queue WHERE queue_id=?'.$scope);
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
                $update=$pdo->prepare("UPDATE patient_queue SET status=?, {$column}=NOW() WHERE queue_id=? AND status=?");
                $update->execute([$to,$queueId,$from]);
                if ($action==='complete') {
                    $pdo->prepare("UPDATE appointments SET status='Completed' WHERE appointment_id=(SELECT appointment_id FROM patient_queue WHERE queue_id=?) AND status='Approved'")->execute([$queueId]);
                }
                $patient=$pdo->prepare('SELECT p.user_id FROM patient_queue q JOIN patients p ON p.patient_id=q.patient_id WHERE q.queue_id=?');
                $patient->execute([$queueId]); $patientUser=(int)$patient->fetchColumn();
                $messages=['call'=>'Your turn is approaching. Please proceed to the consultation area.','start'=>'Your consultation has started.','complete'=>'Your consultation has been marked complete.'];
                if ($patientUser) notifyUser($pdo,$patientUser,$messages[$action],'/patient/queue.php');
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
        foreach($appointments as &$a) {
            if ($a['queue_id']) {
                $pos=$pdo->prepare("SELECT COUNT(*) FROM patient_queue WHERE doctor_id=(SELECT doctor_id FROM patient_queue WHERE queue_id=?) AND queue_date=? AND status IN ('Waiting','Called','In Consultation') AND (priority='Urgent' AND ?='Normal' OR priority=? AND queue_number<=?)");
                $pos->execute([(int)$a['queue_id'],$today,$a['priority'],$a['priority'],(int)$a['queue_number']]);
                $a['position']=(int)$pos->fetchColumn();
                $a['estimated_wait_minutes']=max(0,($a['position']-1)*15);
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
    queueResponse(200,['queue'=>$rows,'waiting'=>$waiting,'estimated_wait_minutes'=>$waiting*15,'updated_at'=>date(DATE_ATOM)]);
} catch (Throwable $e) {
    error_log('Patient queue error: '.$e->getMessage());
    queueResponse(500,['error'=>'The queue service is temporarily unavailable. Confirm that config/queue_schema.sql has been applied.']);
}

<?php
$project_root = dirname(__DIR__, 2);
require_once $project_root . '/config/database.php';
require_once $project_root . '/config/auth_middleware.php';
require_once $project_root . '/config/helpers.php';

enforceRoleAccess(['Doctor']);

$docStmt = $pdo->prepare("SELECT doctor_id, name FROM doctors WHERE user_id = ?");
$docStmt->execute([$_SESSION['user_id']]);
$doctorProfile = $docStmt->fetch();
$doctor_id = $doctorProfile['doctor_id'] ?? 0;

$appointment_id = isset($_GET['appointment_id']) ? (int) $_GET['appointment_id'] : (int) ($_POST['appointment_id'] ?? 0);

$apptStmt = $pdo->prepare("
    SELECT a.*, p.full_name AS patient_name, p.user_id AS patient_user_id
    FROM appointments a INNER JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.appointment_id = ? AND a.doctor_id = ? AND a.status IN ('Pending', 'Approved')
");
$apptStmt->execute([$appointment_id, $doctor_id]);
$appt = $apptStmt->fetch();

if (!$appt) {
    require_once $project_root . '/includes/header.php';
    require_once $project_root . '/includes/navbar.php';
    echo '<div class="container my-5"><div class="alert alert-warning">This appointment could not be found, is not yours, or is no longer reschedulable.</div><a href="/doctor/dashboard.php" class="btn btn-primary">Back to Dashboard</a></div>';
    require_once $project_root . '/includes/footer.php';
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_date'])) {
    $newDate = $_POST['new_date'];
    $newTime = trim($_POST['new_time']);
    $rescheduleReason = trim($_POST['reschedule_reason']);

    if (preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])(:[0-5][0-9])?$/', $newTime, $tp)) {
        $newTime = $tp[1] . ':' . $tp[2] . ':00';
    } else {
        $newTime = '';
    }

    if (empty($newDate) || empty($newTime) || $rescheduleReason === '') {
        $error = 'Please provide a new date, a new time (HH:MM), and a reason for the reschedule.';
    } else {
        try {
            $dayOfWeek = date('l', strtotime($newDate));
            $schedStmt = $pdo->prepare("SELECT max_patients FROM doctor_schedule WHERE doctor_id = ? AND day = ?");
            $schedStmt->execute([$doctor_id, $dayOfWeek]);
            $scheduleDetails = $schedStmt->fetch();

            if (!$scheduleDetails) {
                $error = "You do not operate scheduled clinical blocks on {$dayOfWeek}s.";
            } else {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status != 'Cancelled' AND appointment_id != ?");
                $countStmt->execute([$doctor_id, $newDate, $appointment_id]);
                if ($countStmt->fetchColumn() >= $scheduleDetails['max_patients']) {
                    $error = 'Your booking volume cap for that date has already been reached. Please choose another date.';
                } else {
                    $conflictStmt = $pdo->prepare("SELECT appointment_id FROM appointments WHERE patient_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'Cancelled' AND appointment_id != ?");
                    $conflictStmt->execute([$appt['patient_id'], $newDate, $newTime, $appointment_id]);
                    if ($conflictStmt->rowCount() > 0) {
                        $error = 'This patient already has another active booking at that exact date and time.';
                    } else {
                        $pdo->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, status = 'Approved' WHERE appointment_id = ?")
                            ->execute([$newDate, $newTime, $appointment_id]);

                        $when = date('j M Y', strtotime($newDate)) . ' at ' . date('g:i A', strtotime($newTime));
                        $msg = "Dr. {$doctorProfile['name']} rescheduled your appointment to $when. Reason: $rescheduleReason";
                        notifyUser($pdo, (int) $appt['patient_user_id'], $msg, '/patient/appointments.php');
                        logActivity($pdo, (int) $appt['patient_user_id'], $msg, 'calendar-days');

                        $success = 'Appointment rescheduled and the patient has been notified.';
                        $apptStmt->execute([$appointment_id, $doctor_id]);
                        $appt = $apptStmt->fetch();
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Could not reschedule this appointment right now.';
        }
    }
}

require_once $project_root . '/includes/header.php';
require_once $project_root . '/includes/navbar.php';
?>
<div class="container my-5">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/doctor/dashboard.php">Dashboard</a></li><li class="breadcrumb-item active">Reschedule Appointment</li></ol></nav>
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm p-4">
                <h4 class="fw-bold text-primary border-bottom pb-2 mb-4"><i class="fa-solid fa-calendar-days me-2"></i>Reschedule Appointment</h4>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

                <div class="mb-4 p-3 bg-light rounded">
                    <div class="fw-bold">Patient: <?php echo htmlspecialchars($appt['patient_name']); ?></div>
                    <div class="small text-muted">Currently scheduled: <?php echo date('j M Y', strtotime($appt['appointment_date'])); ?> at <?php echo date('g:i A', strtotime($appt['appointment_time'])); ?> &bull; Status: <?php echo htmlspecialchars($appt['status']); ?></div>
                </div>

                <form method="POST">
                    <input type="hidden" name="appointment_id" value="<?php echo (int) $appointment_id; ?>">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">New Date <span class="text-danger">*</span></label>
                            <input type="date" name="new_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">New Time <span class="text-danger">*</span></label>
                            <input type="time" name="new_time" class="form-control" step="60" placeholder="HH:MM" pattern="([01][0-9]|2[0-3]):[0-5][0-9]" required>
                            <div class="form-text">Hours and minutes only (e.g. 10:30).</div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Reason for Reschedule <span class="text-danger">*</span></label>
                        <textarea name="reschedule_reason" class="form-control" rows="3" placeholder="e.g. Emergency theatre case came up" required></textarea>
                        <div class="form-text">This will be sent to the patient as a notification.</div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="/doctor/dashboard.php" class="btn btn-light fw-semibold px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary fw-bold px-4">Reschedule &amp; Notify Patient</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once $project_root . '/includes/footer.php'; ?>

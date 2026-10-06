<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';
require_once __DIR__ . '/../config/helpers.php';

enforceRoleAccess(['Patient']);

$stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$patient_id = $stmt->fetchColumn();

$appointment_id = isset($_GET['appointment_id']) ? (int) $_GET['appointment_id'] : (int) ($_POST['appointment_id'] ?? 0);

$apptStmt = $pdo->prepare("
    SELECT a.*, d.name AS doctor_name, d.user_id AS doctor_user_id
    FROM appointments a INNER JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE a.appointment_id = ? AND a.patient_id = ? AND a.status IN ('Pending', 'Approved')
");
$apptStmt->execute([$appointment_id, $patient_id]);
$appt = $apptStmt->fetch();

$activePage = 'appointments';

if (!$appt) {
    require_once __DIR__ . '/../includes/patient_layout_header.php';
    echo '<div class="alert alert-warning">This appointment could not be found or is no longer reschedulable.</div><a href="/patient/appointments.php" class="btn btn-primary">Back to My Appointments</a>';
    require_once __DIR__ . '/../includes/patient_layout_footer.php';
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
            $schedStmt->execute([$appt['doctor_id'], $dayOfWeek]);
            $scheduleDetails = $schedStmt->fetch();

            if (!$scheduleDetails) {
                $error = "Dr. {$appt['doctor_name']} does not operate scheduled clinical blocks on {$dayOfWeek}s.";
            } else {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status != 'Cancelled' AND appointment_id != ?");
                $countStmt->execute([$appt['doctor_id'], $newDate, $appointment_id]);
                if ($countStmt->fetchColumn() >= $scheduleDetails['max_patients']) {
                    $error = "The booking volume for that date is already full. Please choose another date.";
                } else {
                    $conflictStmt = $pdo->prepare("SELECT appointment_id FROM appointments WHERE patient_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'Cancelled' AND appointment_id != ?");
                    $conflictStmt->execute([$patient_id, $newDate, $newTime, $appointment_id]);
                    if ($conflictStmt->rowCount() > 0) {
                        $error = 'You already have another active booking at that exact date and time.';
                    } else {
                        // A patient-initiated reschedule needs the doctor to re-confirm the new slot.
                        $pdo->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, status = 'Pending' WHERE appointment_id = ?")
                            ->execute([$newDate, $newTime, $appointment_id]);

                        $when = date('j M Y', strtotime($newDate)) . ' at ' . date('g:i A', strtotime($newTime));
                        $msg = "A patient requested to reschedule their appointment with you to $when. Reason: $rescheduleReason";
                        notifyUser($pdo, (int) $appt['doctor_user_id'], $msg, '/doctor/dashboard.php');
                        logActivity($pdo, $_SESSION['user_id'], "Requested to reschedule your appointment with Dr. {$appt['doctor_name']} to $when", 'calendar-days');

                        $success = 'Your reschedule request has been sent. The appointment is now pending the doctor\'s confirmation.';
                        $apptStmt->execute([$appointment_id, $patient_id]);
                        $appt = $apptStmt->fetch();
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Could not reschedule this appointment right now.';
        }
    }
}

require_once __DIR__ . '/../includes/patient_layout_header.php';
?>

<h3 class="fw-bold text-primary mb-4">Reschedule Appointment</h3>

<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="mb-4 p-3 bg-light rounded">
            <div class="fw-bold">Doctor: Dr. <?php echo htmlspecialchars($appt['doctor_name']); ?></div>
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
                <textarea name="reschedule_reason" class="form-control" rows="3" placeholder="e.g. A work conflict came up" required></textarea>
                <div class="form-text">This will be sent to your doctor as a notification, and they will need to re-confirm the new time.</div>
            </div>
            <div class="d-flex justify-content-end gap-2 border-top pt-3">
                <a href="/patient/appointments.php" class="btn btn-light fw-semibold px-4">Cancel</a>
                <button type="submit" class="btn btn-primary fw-bold px-4">Reschedule &amp; Notify Doctor</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/patient_layout_footer.php'; ?>

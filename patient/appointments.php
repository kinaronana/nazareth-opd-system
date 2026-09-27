<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';
require_once __DIR__ . '/../config/helpers.php';

enforceRoleAccess(['Patient']);

$stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$patient_id = $stmt->fetchColumn();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment_id'])) {
    $apptId = (int) $_POST['cancel_appointment_id'];
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'Cancelled' WHERE appointment_id = ? AND patient_id = ? AND status IN ('Pending','Approved')");
    $stmt->execute([$apptId, $patient_id]);
    if ($stmt->rowCount() > 0) {
        logActivity($pdo, $_SESSION['user_id'], 'Cancelled an appointment', 'calendar-xmark');
        $message = 'Appointment cancelled successfully.';
    }
}

function fetchAppts(PDO $pdo, int $patient_id, array $statuses): array
{
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $pdo->prepare("
        SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status, a.reason,
               d.name AS doctor_name, dept.department_name
        FROM appointments a
        INNER JOIN doctors d ON a.doctor_id = d.doctor_id
        LEFT JOIN departments dept ON d.department_id = dept.department_id
        WHERE a.patient_id = ? AND a.status IN ($placeholders)
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute(array_merge([$patient_id], $statuses));
    return $stmt->fetchAll();
}

$upcoming = fetchAppts($pdo, $patient_id, ['Pending', 'Approved']);
$completed = fetchAppts($pdo, $patient_id, ['Completed']);
$cancelled = fetchAppts($pdo, $patient_id, ['Cancelled', 'Rejected']);

$activePage = 'appointments';
require_once __DIR__ . '/../includes/patient_layout_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-primary m-0">My Appointments</h3>
    <a href="/appointments/book.php" class="btn btn-primary fw-bold"><i class="fa-solid fa-calendar-plus me-1"></i>Book New Appointment</a>
</div>

<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <ul class="nav nav-tabs">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-up" type="button">Upcoming (<?php echo count($upcoming); ?>)</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-comp" type="button">Completed (<?php echo count($completed); ?>)</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-can" type="button">Cancelled (<?php echo count($cancelled); ?>)</button></li>
        </ul>
        <div class="tab-content pt-3">
            <?php
            function renderFullApptRows(array $rows, bool $allowCancel): void {
                if (empty($rows)) { echo '<p class="text-muted small m-0 py-3">Nothing here yet.</p>'; return; }
                echo '<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Date &amp; Time</th><th>Doctor</th><th>Department</th><th>Reason</th><th>Status</th>';
                if ($allowCancel) echo '<th></th>';
                echo '</tr></thead><tbody>';
                foreach ($rows as $r) {
                    $badge = ['Pending' => 'bg-warning text-dark', 'Approved' => 'bg-success', 'Completed' => 'bg-info text-dark', 'Cancelled' => 'bg-dark', 'Rejected' => 'bg-danger'][$r['status']] ?? 'bg-secondary';
                    echo '<tr>';
                    echo '<td>' . date('j M Y', strtotime($r['appointment_date'])) . '<br><span class="text-muted small">' . date('g:i A', strtotime($r['appointment_time'])) . '</span></td>';
                    echo '<td>Dr. ' . htmlspecialchars($r['doctor_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($r['department_name'] ?? 'General') . '</td>';
                    echo '<td class="small text-muted">' . htmlspecialchars($r['reason'] ?: '—') . '</td>';
                    echo '<td><span class="badge ' . $badge . '">' . htmlspecialchars($r['status']) . '</span></td>';
                    if ($allowCancel) {
                        echo '<td><form method="POST" onsubmit="return confirm(\'Cancel this appointment?\');">';
                        echo '<input type="hidden" name="cancel_appointment_id" value="' . (int)$r['appointment_id'] . '">';
                        echo '<button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button></form></td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            }
            ?>
            <div class="tab-pane fade show active" id="tab-up"><?php renderFullApptRows($upcoming, true); ?></div>
            <div class="tab-pane fade" id="tab-comp"><?php renderFullApptRows($completed, false); ?></div>
            <div class="tab-pane fade" id="tab-can"><?php renderFullApptRows($cancelled, false); ?></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/patient_layout_footer.php'; ?>

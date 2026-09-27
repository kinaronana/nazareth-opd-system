<?php
// =========================================================================
// BLOCK 1: BACKEND LOGIC PROCESSING LAYER
// =========================================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';
require_once __DIR__ . '/../config/helpers.php';

enforceRoleAccess(['Patient']);

$stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$patient_id = $stmt->fetchColumn();

if (!$patient_id) {
    die("<div class='container my-5 alert alert-danger fw-bold'>Your account has not been mapped to a patient profile yet.</div>");
}

// --- Stat cards -----------------------------------------------------------
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status IN ('Pending','Approved') AND appointment_date >= CURDATE()");
$countStmt->execute([$patient_id]);
$upcomingCount = (int) $countStmt->fetchColumn();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status = 'Completed'");
$countStmt->execute([$patient_id]);
$completedCount = (int) $countStmt->fetchColumn();

$prescriptionsCount = 0;
$testResultsCount = 0;
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM prescriptions WHERE patient_id = ?");
    $countStmt->execute([$patient_id]);
    $prescriptionsCount = (int) $countStmt->fetchColumn();

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM test_results WHERE patient_id = ?");
    $countStmt->execute([$patient_id]);
    $testResultsCount = (int) $countStmt->fetchColumn();
} catch (Exception $e) {
    // New tables not migrated yet on this environment; show zero rather than erroring the whole dashboard.
}

// --- Next appointment -------------------------------------------------
$nextAppointment = null;
$stmt = $pdo->prepare("
    SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status,
           d.name AS doctor_name, dept.department_name
    FROM appointments a
    INNER JOIN doctors d ON a.doctor_id = d.doctor_id
    LEFT JOIN departments dept ON d.department_id = dept.department_id
    WHERE a.patient_id = ? AND a.status IN ('Pending','Approved') AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 1
");
$stmt->execute([$patient_id]);
$nextAppointment = $stmt->fetch();

// --- Recent activity -------------------------------------------------
$recentActivity = [];
try {
    $stmt = $pdo->prepare("SELECT icon, description, created_at FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$_SESSION['user_id']]);
    $recentActivity = $stmt->fetchAll();
} catch (Exception $e) {
    $recentActivity = [];
}

// --- Appointments list (tabs) -------------------------------------------------
function fetchAppointmentsByStatuses(PDO $pdo, int $patient_id, array $statuses): array
{
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $pdo->prepare("
        SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status,
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

$upcomingAppointments = fetchAppointmentsByStatuses($pdo, $patient_id, ['Pending', 'Approved']);
$completedAppointments = fetchAppointmentsByStatuses($pdo, $patient_id, ['Completed']);
$cancelledAppointments = fetchAppointmentsByStatuses($pdo, $patient_id, ['Cancelled', 'Rejected']);

$activePage = 'dashboard';
require_once __DIR__ . '/../includes/patient_layout_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-primary m-0">Dashboard</h3>
        <p class="text-secondary m-0">Welcome back, <?php echo htmlspecialchars(explode(' ', $patientName)[0]); ?>! Manage your health with ease.</p>
    </div>
    <div class="text-secondary fw-semibold"><?php echo date('l, j F Y'); ?></div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="background:#e7f1ff;">
            <div class="card-body">
                <div class="text-primary fw-semibold small mb-1"><i class="fa-solid fa-calendar me-1"></i>Upcoming Appointments</div>
                <div class="fs-3 fw-bold"><?php echo $upcomingCount; ?></div>
                <a href="/patient/appointments.php" class="small fw-semibold text-decoration-none">View Details &rarr;</a>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="background:#e6f7ee;">
            <div class="card-body">
                <div class="text-success fw-semibold small mb-1"><i class="fa-solid fa-video me-1"></i>Completed Consultations</div>
                <div class="fs-3 fw-bold"><?php echo $completedCount; ?></div>
                <a href="/patient/appointments.php" class="small fw-semibold text-decoration-none">View Details &rarr;</a>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="background:#fff6e6;">
            <div class="card-body">
                <div class="fw-semibold small mb-1 text-warning-emphasis"><i class="fa-solid fa-file-prescription me-1"></i>Prescriptions</div>
                <div class="fs-3 fw-bold"><?php echo $prescriptionsCount; ?></div>
                <a href="/patient/prescriptions.php" class="small fw-semibold text-decoration-none">View Details &rarr;</a>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="background:#fdeaea;">
            <div class="card-body">
                <div class="fw-semibold small mb-1 text-danger"><i class="fa-solid fa-flask me-1"></i>Test Results</div>
                <div class="fs-3 fw-bold"><?php echo $testResultsCount; ?></div>
                <a href="/patient/test_results.php" class="small fw-semibold text-decoration-none">View Details &rarr;</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Next Appointment -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold m-0">Next Appointment</h6>
                    <a href="/patient/appointments.php" class="small text-decoration-none">View All</a>
                </div>
                <?php if ($nextAppointment): ?>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                            <i class="fa-solid fa-user-doctor text-primary"></i>
                        </div>
                        <div>
                            <div class="fw-bold">Dr. <?php echo htmlspecialchars($nextAppointment['doctor_name']); ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars($nextAppointment['department_name'] ?? 'General'); ?></div>
                        </div>
                    </div>
                    <div class="small mb-1"><i class="fa-solid fa-calendar-day me-2 text-muted"></i><?php echo date('l, j F Y', strtotime($nextAppointment['appointment_date'])); ?></div>
                    <div class="small mb-3"><i class="fa-solid fa-clock me-2 text-muted"></i><?php echo date('g:i A', strtotime($nextAppointment['appointment_time'])); ?></div>
                    <a href="/patient/appointments.php" class="btn btn-primary w-100 fw-bold mb-2">Manage Appointment</a>
                <?php else: ?>
                    <p class="text-muted small mb-3">You have no upcoming appointments scheduled.</p>
                    <a href="/appointments/book.php" class="btn btn-primary w-100 fw-bold">Book an Appointment</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Quick Actions</h6>
                <div class="row g-2">
                    <div class="col-6"><a href="/appointments/book.php" class="btn btn-light text-start w-100 py-2 small fw-semibold"><i class="fa-solid fa-calendar-plus text-primary d-block mb-1"></i>Book Appointment</a></div>
                    <div class="col-6"><a href="/patient/find_doctor.php" class="btn btn-light text-start w-100 py-2 small fw-semibold"><i class="fa-solid fa-user-doctor text-success d-block mb-1"></i>Find a Doctor</a></div>
                    <div class="col-6"><a href="/patient/medical_records.php" class="btn btn-light text-start w-100 py-2 small fw-semibold"><i class="fa-solid fa-folder-open text-purple d-block mb-1"></i>View Medical Records</a></div>
                    <div class="col-6"><a href="/patient/billing.php" class="btn btn-light text-start w-100 py-2 small fw-semibold"><i class="fa-solid fa-credit-card text-primary d-block mb-1"></i>Make Payment</a></div>
                    <div class="col-6"><a href="/patient/messages.php" class="btn btn-light text-start w-100 py-2 small fw-semibold"><i class="fa-solid fa-message text-info d-block mb-1"></i>Send a Message</a></div>
                    <div class="col-6"><a href="/patient/help.php" class="btn btn-light text-start w-100 py-2 small fw-semibold"><i class="fa-solid fa-circle-question text-secondary d-block mb-1"></i>Get Support</a></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold m-0">Recent Activity</h6>
                </div>
                <?php if (empty($recentActivity)): ?>
                    <p class="text-muted small m-0">No recent activity yet.</p>
                <?php else: ?>
                    <?php foreach ($recentActivity as $activity): ?>
                        <div class="d-flex gap-2 mb-3">
                            <i class="fa-solid fa-<?php echo htmlspecialchars($activity['icon']); ?> text-primary mt-1"></i>
                            <div class="small">
                                <div><?php echo htmlspecialchars($activity['description']); ?></div>
                                <div class="text-muted"><?php echo date('j M Y, g:i A', strtotime($activity['created_at'])); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Your Appointments -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold m-0">Your Appointments</h6>
                    <a href="/patient/appointments.php" class="small text-decoration-none">View All</a>
                </div>
                <ul class="nav nav-tabs" id="apptTabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-upcoming" type="button">Upcoming</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-completed" type="button">Completed</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-cancelled" type="button">Cancelled</button></li>
                </ul>
                <div class="tab-content pt-3">
                    <?php
                    function renderApptRows(array $rows): void {
                        if (empty($rows)) {
                            echo '<p class="text-muted small m-0">No appointments in this category.</p>';
                            return;
                        }
                        echo '<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Date &amp; Time</th><th>Doctor</th><th>Department</th><th>Status</th></tr></thead><tbody>';
                        foreach ($rows as $r) {
                            $badge = ['Pending' => 'bg-warning text-dark', 'Approved' => 'bg-success', 'Completed' => 'bg-info text-dark', 'Cancelled' => 'bg-dark', 'Rejected' => 'bg-danger'][$r['status']] ?? 'bg-secondary';
                            echo '<tr>';
                            echo '<td>' . date('j M Y', strtotime($r['appointment_date'])) . '<br><span class="text-muted small">' . date('g:i A', strtotime($r['appointment_time'])) . '</span></td>';
                            echo '<td>Dr. ' . htmlspecialchars($r['doctor_name']) . '</td>';
                            echo '<td>' . htmlspecialchars($r['department_name'] ?? 'General') . '</td>';
                            echo '<td><span class="badge ' . $badge . '">' . htmlspecialchars($r['status']) . '</span></td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table></div>';
                    }
                    ?>
                    <div class="tab-pane fade show active" id="tab-upcoming"><?php renderApptRows($upcomingAppointments); ?></div>
                    <div class="tab-pane fade" id="tab-completed"><?php renderApptRows($completedAppointments); ?></div>
                    <div class="tab-pane fade" id="tab-cancelled"><?php renderApptRows($cancelledAppointments); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Health Tips -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100" style="background:#eaf4ff;">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Health Tips</h6>
                <p class="small mb-2">A healthier you, a brighter tomorrow.</p>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i>Attend regular check-ups</li>
                    <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i>Take prescribed medications</li>
                    <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i>Maintain a balanced diet</li>
                    <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i>Stay active</li>
                    <li class="mb-0"><i class="fa-solid fa-circle-check text-success me-2"></i>Reach out to your doctor when needed</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/patient_layout_footer.php'; ?>

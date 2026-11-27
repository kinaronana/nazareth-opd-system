<?php
// =========================================================================
// BLOCK 1: BACKEND DOCTOR PORTAL LOGIC (Absolute Server Root Engine)
// =========================================================================

$project_root = dirname(__DIR__);

require_once $project_root . '/config/database.php';
require_once $project_root . '/config/auth_middleware.php';

// Enforce strict Doctor Role-Based Access Control [4]
enforceRoleAccess(['Doctor']);

$error = '';
$success = '';

// Fetch the current doctor extension record based on the authenticated core user session token
try {
    $docStmt = $pdo->prepare("
        SELECT d.doctor_id, d.name, d.specialization, d.profile_photo, dept.department_name 
        FROM doctors d
        INNER JOIN departments dept ON d.department_id = dept.department_id
        WHERE d.user_id = ?
    ");
    $docStmt->execute([$_SESSION['user_id']]);
    $doctorProfile = $docStmt->fetch();

    // Never fall back to another clinician's profile: dashboard data must
    // belong only to the doctor linked to the authenticated account.
    if (!$doctorProfile) {
        http_response_code(403);
        die("<div class='container my-5 alert alert-danger fw-bold'>Your doctor account is not linked to a clinical profile. Please contact the hospital administrator.</div>");
    }

    $doctor_id = (int) $doctorProfile['doctor_id'];

    // Approve / Reject a pending appointment.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_action'], $_POST['appointment_id'])) {
        require_once $project_root . '/config/helpers.php';
        $apptId = (int) $_POST['appointment_id'];
        $newStatus = $_POST['appointment_action'] === 'approve' ? 'Approved' : 'Rejected';

        $apptStmt = $pdo->prepare("
            SELECT a.*, p.user_id AS patient_user_id, p.full_name AS patient_name
            FROM appointments a INNER JOIN patients p ON a.patient_id = p.patient_id
            WHERE a.appointment_id = ? AND a.doctor_id = ? AND a.status = 'Pending'
        ");
        $apptStmt->execute([$apptId, $doctor_id]);
        $appt = $apptStmt->fetch();

        if ($appt) {
            $pdo->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?")->execute([$newStatus, $apptId]);
            $when = date('j M Y', strtotime($appt['appointment_date'])) . ' at ' . date('g:i A', strtotime($appt['appointment_time']));
            $msg = $newStatus === 'Approved'
                ? "Dr. {$doctorProfile['name']} approved your appointment on $when."
                : "Dr. {$doctorProfile['name']} was unable to accept your appointment request for $when.";
            notifyUser($pdo, (int) $appt['patient_user_id'], $msg, '/patient/appointments.php');
            logActivity($pdo, (int) $appt['patient_user_id'], $msg, $newStatus === 'Approved' ? 'calendar-check' : 'calendar-xmark');
            $success = "Appointment $newStatus successfully.";
        } else {
            $error = 'That appointment could not be found or is no longer pending.';
        }
    }

    // Gather live metrics for this specific clinician
    $pendingCount = $pdo->query("SELECT COUNT(*) FROM appointments WHERE doctor_id = $doctor_id AND status = 'Pending'")->fetchColumn();
    $approvedCount = $pdo->query("SELECT COUNT(*) FROM appointments WHERE doctor_id = $doctor_id AND status = 'Approved'")->fetchColumn();
    $completedCount = $pdo->query("SELECT COUNT(*) FROM appointments WHERE doctor_id = $doctor_id AND status = 'Completed'")->fetchColumn();

    // Fetch this doctor's complete appointment clinical roster
    $rosterStmt = $pdo->prepare("
        SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status, a.reason,
               p.patient_id, p.full_name AS patient_name, p.phone AS patient_phone, p.gender, p.dob
        FROM appointments a
        INNER JOIN patients p ON a.patient_id = p.patient_id
        WHERE a.doctor_id = ?
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $rosterStmt->execute([$doctor_id]);
    $myRoster = $rosterStmt->fetchAll();

    $doctorTrendStmt = $pdo->prepare("SELECT DATE_FORMAT(appointment_date, '%Y-%m') AS month_key, COUNT(*) AS total FROM appointments WHERE doctor_id = ? AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) GROUP BY DATE_FORMAT(appointment_date, '%Y-%m') ORDER BY month_key");
    $doctorTrendStmt->execute([$doctor_id]);
    $doctorTrendRows = $doctorTrendStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $doctorTrendLabels = []; $doctorTrendValues = [];
    for ($i = 5; $i >= 0; $i--) { $month = date('Y-m', strtotime(date('Y-m-01') . " -$i months")); $doctorTrendLabels[] = date('M Y', strtotime($month . '-01')); $doctorTrendValues[] = (int)($doctorTrendRows[$month] ?? 0); }
    $doctorStatusStmt = $pdo->prepare("SELECT status, COUNT(*) AS total FROM appointments WHERE doctor_id = ? GROUP BY status");
    $doctorStatusStmt->execute([$doctor_id]);
    $doctorStatusLabels = []; $doctorStatusValues = [];
    foreach ($doctorStatusStmt->fetchAll() as $row) { $doctorStatusLabels[] = (string)$row['status']; $doctorStatusValues[] = (int)$row['total']; }


} catch (Exception $e) {
    die("Data Query Operations Failure: Doctor dashboard data collection unaligned.");
}

require_once $project_root . '/includes/header.php';
require_once $project_root . '/includes/navbar.php';
?>

<div class="container my-5">
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <!-- Profile Identification Header Card -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card bg-white border-0 shadow-sm p-4">
                <div class="d-flex flex-column flex-md-row align-items-center gap-4">
                    <img src="/assets/uploads/<?php echo htmlspecialchars($doctorProfile['profile_photo']); ?>" 
                         alt="Doctor Avatar" class="rounded-circle shadow-sm border border-light" 
                         style="width: 90px; height: 90px; object-fit: cover;"
                         onerror="this.src='/assets/uploads/default-avatar.png';">
                    <div class="text-center text-md-start">
                        <h3 class="fw-bold text-primary m-0">Welcome, <?php echo htmlspecialchars($doctorProfile['name']); ?></h3>
                        <p class="text-secondary m-0 fw-semibold"><?php echo htmlspecialchars($doctorProfile['specialization']); ?> &bull; <span class="text-muted"><?php echo htmlspecialchars($doctorProfile['department_name']); ?></span></p>
                        <small class="text-muted font-monospace">Clinical Token: #DOC-00<?php echo $doctor_id; ?></small>
                    </div>
                    <div class="ms-md-auto d-grid d-md-flex gap-2">
                        <a href="/doctor/ai_assistant.php" class="btn btn-outline-success fw-bold"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>AI Documentation</a>
                        <a href="messages.php" class="btn btn-outline-secondary fw-bold">
                            <i class="fa-solid fa-envelope me-1"></i>Messages
                        </a>
                        <a href="schedule/manage_schedule.php" class="btn btn-outline-primary fw-bold">
                            <i class="fa-solid fa-calendar-days me-1"></i>Configure Schedule Rules
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Telemetry Metric Summary Indicators -->
    <div class="row g-3 mb-5">
        <div class="col-md-4">
            <div class="card bg-white border-start border-warning border-4 p-3 shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase small font-weight-bold">Pending Approval</h6>
                        <h3 class="fw-bold m-0 text-dark" data-counter-key="pending"><?php echo $pendingCount; ?></h3>
                    </div>
                    <div class="text-warning"><i class="fa-solid fa-clock fa-2x"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-white border-start border-success border-4 p-3 shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase small font-weight-bold">Confirmed Sessions</h6>
                        <h3 class="fw-bold m-0 text-dark" data-counter-key="approved"><?php echo $approvedCount; ?></h3>
                    </div>
                    <div class="text-success"><i class="fa-solid fa-circle-check fa-2x"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-white border-start border-info border-4 p-3 shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase small font-weight-bold">Completed Visits</h6>
                        <h3 class="fw-bold m-0 text-dark" data-counter-key="completed"><?php echo $completedCount; ?></h3>
                    </div>
                    <div class="text-info"><i class="fa-solid fa-hand-holding-medical fa-2x"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4 opd-analytics">
        <div class="col-lg-8"><section class="card border-0 p-4 h-100">
            <div class="d-flex justify-content-between align-items-start mb-3"><div><span class="opd-chart-kicker">MY CLINIC</span><h5 class="fw-bold mb-1">Appointment Activity</h5><p class="text-muted small mb-0">Your monthly bookings over the last six months</p></div><span class="opd-chart-icon"><i class="fa-solid fa-chart-line"></i></span></div>
            <div class="opd-chart-wrap"><canvas id="myclinicTrend" role="img" aria-label="Appointment Activity"></canvas></div>
        </section></div>
        <div class="col-lg-4"><section class="card border-0 p-4 h-100">
            <div class="d-flex justify-content-between align-items-start mb-3"><div><span class="opd-chart-kicker">STATUS MIX</span><h5 class="fw-bold mb-1">Visit Status</h5><p class="text-muted small mb-0">Your appointment distribution</p></div><span class="opd-chart-icon teal"><i class="fa-solid fa-chart-pie"></i></span></div>
            <div class="opd-chart-wrap opd-chart-doughnut"><canvas id="myclinicStatus" role="img" aria-label="Visit Status"></canvas></div>
        </section></div>
    </div>

    <!-- Clinical Patient Roster Registry Sheet -->
    <div class="card bg-white border-0 shadow-sm p-4">
        <h5 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fa-solid fa-clipboard-list me-2 text-primary"></i>My Scheduled Consultations Feed</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle m-0">
                <thead class="table-light">
                    <tr>
                        <th>Outpatient Name</th>
                        <th class="text-center">Age / Gender</th>
                        <th>Contact Scope</th>
                        <th>Appointment Slot</th>
                        <th>Chief Complaint / Reason</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($myRoster)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fa-solid fa-folder-open fa-2x mb-2 d-block text-secondary"></i>
                                No patient appointment allocations mapped to your profile yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($myRoster as $visit): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($visit['patient_name']); ?></td>
                                <td class="text-center">
                                    <?php 
                                    $age = date('Y') - date('Y', strtotime($visit['dob']));
                                    echo $age . ' Yrs &bull; ' . $visit['gender'];
                                    ?>
                                </td>
                                <td>
                                    <small class="d-block fw-semibold text-dark"><i class="fa-solid fa-phone me-1 text-muted"></i><?php echo htmlspecialchars($visit['patient_phone']); ?></small>
                                </td>
                                <td>
                                    <small class="d-block fw-bold text-primary"><i class="fa-solid fa-calendar me-1"></i><?php echo date('d M Y', strtotime($visit['appointment_date'])); ?></small>
                                    <small class="text-muted font-monospace"><i class="fa-solid fa-clock me-1"></i><?php echo date('h:i A', strtotime($visit['appointment_time'])); ?></small>
                                </td>
                                <td>
                                    <span class="small text-secondary d-inline-block text-truncate" style="max-width: 220px;" title="<?php echo htmlspecialchars($visit['reason']); ?>">
                                        <?php echo htmlspecialchars($visit['reason'] ?: 'General Assessment'); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php 
                                    $badge = 'bg-secondary';
                                    if ($visit['status'] === 'Pending') $badge = 'bg-warning text-dark';
                                    if ($visit['status'] === 'Approved') $badge = 'bg-success';
                                    if ($visit['status'] === 'Rejected') $badge = 'bg-danger';
                                    if ($visit['status'] === 'Completed') $badge = 'bg-info text-dark';
                                    if ($visit['status'] === 'Cancelled') $badge = 'bg-dark';
                                    ?>
                                    <span class="badge <?php echo $badge; ?> px-2 py-1 fw-semibold"><?php echo htmlspecialchars($visit['status']); ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <?php if ($visit['status'] === 'Pending'): ?>
                                            <form method="POST" onsubmit="return confirm('Approve this appointment?');">
                                                <input type="hidden" name="appointment_id" value="<?php echo (int) $visit['appointment_id']; ?>">
                                                <input type="hidden" name="appointment_action" value="approve">
                                                <button type="submit" class="btn btn-sm btn-success" title="Approve"><i class="fa-solid fa-check"></i></button>
                                            </form>
                                            <form method="POST" onsubmit="return confirm('Reject this appointment request?');">
                                                <input type="hidden" name="appointment_id" value="<?php echo (int) $visit['appointment_id']; ?>">
                                                <input type="hidden" name="appointment_action" value="reject">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Reject"><i class="fa-solid fa-xmark"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false">Actions</button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <?php if (in_array($visit['status'], ['Pending', 'Approved'], true)): ?>
                                                    <li><a class="dropdown-item" href="/doctor/appointments/reschedule.php?appointment_id=<?php echo (int) $visit['appointment_id']; ?>"><i class="fa-solid fa-calendar-days me-2"></i>Reschedule Appointment</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                <?php endif; ?>
                                                <li><a class="dropdown-item" href="/doctor/records/add_record.php?patient_id=<?php echo (int) $visit['patient_id']; ?>&appointment_id=<?php echo (int) $visit['appointment_id']; ?>"><i class="fa-solid fa-folder-open me-2"></i>Add Medical Record</a></li>
                                                <li><a class="dropdown-item" href="/doctor/prescriptions/issue.php?patient_id=<?php echo (int) $visit['patient_id']; ?>"><i class="fa-solid fa-file-prescription me-2"></i>Issue Prescription</a></li>
                                                <li><a class="dropdown-item" href="/doctor/results/add_result.php?patient_id=<?php echo (int) $visit['patient_id']; ?>"><i class="fa-solid fa-flask me-2"></i>Add Test Result</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>(function(){if(!window.Chart)return;
const labels=<?php echo json_encode($doctorTrendLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
const values=<?php echo json_encode($doctorTrendValues); ?>;
const sl=<?php echo json_encode($doctorStatusLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
const sv=<?php echo json_encode($doctorStatusValues); ?>;
const c=document.getElementById('myclinicTrend');if(c)new Chart(c,{type:'line',data:{labels,datasets:[{label:'Appointments',data:values,borderColor:'#1685c5',backgroundColor:'rgba(22,133,197,.14)',fill:true,tension:.38,borderWidth:3,pointRadius:4,pointBackgroundColor:'#20aaa9'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0},grid:{color:'rgba(35,66,82,.08)'}},x:{grid:{display:false}}}}});
const d=document.getElementById('myclinicStatus');if(d)new Chart(d,{type:'doughnut',data:{labels:sl,datasets:[{data:sv,backgroundColor:['#20aaa9','#1685c5','#f0a45d','#8b78d6','#e76f79','#70b88a','#91a4b7'],borderColor:'#fff',borderWidth:3,hoverOffset:5}]},options:{responsive:true,maintainAspectRatio:false,cutout:'68%',plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:8,padding:14}}}}});
})();</script>
<script src="/assets/js/dashboard-live.js" defer></script>

<?php
require_once $project_root . '/includes/footer.php';
?>

<?php
// =========================================================================
// BLOCK 1: BACKEND DOCTOR PORTAL LOGIC (Absolute Server Root Engine)
// =========================================================================

$project_root = $_SERVER['DOCUMENT_ROOT'] . '/nazareth-opd-system';

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

    // Fallback block if logged in via unmapped development parameters
    if (!$doctorProfile) {
        $doctorProfile = $pdo->query("
            SELECT d.doctor_id, d.name, d.specialization, d.profile_photo, dept.department_name 
            FROM doctors d 
            INNER JOIN departments dept ON d.department_id = dept.department_id 
            LIMIT 1
        ")->fetch();
        
        if (!$doctorProfile) {
            die("<div class='container my-5 alert alert-danger fw-bold'>System Profile Conflict: Your account hasn't been mapped to a clinical physician file record yet.</div>");
        }
    }
    
    $doctor_id = $doctorProfile['doctor_id'];

    // Gather live metrics for this specific clinician
    $pendingCount = $pdo->query("SELECT COUNT(*) FROM appointments WHERE doctor_id = $doctor_id AND status = 'Pending'")->fetchColumn();
    $approvedCount = $pdo->query("SELECT COUNT(*) FROM appointments WHERE doctor_id = $doctor_id AND status = 'Approved'")->fetchColumn();
    $completedCount = $pdo->query("SELECT COUNT(*) FROM appointments WHERE doctor_id = $doctor_id AND status = 'Completed'")->fetchColumn();

    // Fetch this doctor's complete appointment clinical roster
    $rosterStmt = $pdo->prepare("
        SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status, a.reason,
               p.full_name AS patient_name, p.phone AS patient_phone, p.gender, p.dob
        FROM appointments a
        INNER JOIN patients p ON a.patient_id = p.patient_id
        WHERE a.doctor_id = ?
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $rosterStmt->execute([$doctor_id]);
    $myRoster = $rosterStmt->fetchAll();

} catch (Exception $e) {
    die("Data Query Operations Failure: Doctor dashboard data collection unaligned.");
}

require_once $project_root . '/includes/header.php';
require_once $project_root . '/includes/navbar.php';
?>

<div class="container my-5">
    <!-- Profile Identification Header Card -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card bg-white border-0 shadow-sm p-4">
                <div class="d-flex flex-column flex-md-row align-items-center gap-4">
                    <img src="/nazareth-opd-system/assets/uploads/<?php echo htmlspecialchars($doctorProfile['profile_photo']); ?>" 
                         alt="Doctor Avatar" class="rounded-circle shadow-sm border border-light" 
                         style="width: 90px; height: 90px; object-fit: cover;"
                         onerror="this.src='/nazareth-opd-system/assets/uploads/default-avatar.png';">
                    <div class="text-center text-md-start">
                        <h3 class="fw-bold text-primary m-0">Welcome, <?php echo htmlspecialchars($doctorProfile['name']); ?></h3>
                        <p class="text-secondary m-0 fw-semibold"><?php echo htmlspecialchars($doctorProfile['specialization']); ?> &bull; <span class="text-muted"><?php echo htmlspecialchars($doctorProfile['department_name']); ?></span></p>
                        <small class="text-muted font-monospace">Clinical Token: #DOC-00<?php echo $doctor_id; ?></small>
                    </div>
                    <div class="ms-md-auto d-grid d-md-block gap-2">
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
                        <h3 class="fw-bold m-0 text-dark"><?php echo $pendingCount; ?></h3>
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
                        <h3 class="fw-bold m-0 text-dark"><?php echo $approvedCount; ?></h3>
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
                        <h3 class="fw-bold m-0 text-dark"><?php echo $completedCount; ?></h3>
                    </div>
                    <div class="text-info"><i class="fa-solid fa-hand-holding-medical fa-2x"></i></div>
                </div>
            </div>
        </div>
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
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($myRoster)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
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
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once $project_root . '/includes/footer.php';
?>

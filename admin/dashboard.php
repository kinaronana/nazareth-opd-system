<?php
// Initialize database connection and access restrictions using clean absolute paths
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';

// Strict Role-Based Access Enforcement
enforceRoleAccess(['Admin']);

// Execute scalar count queries to build dashboard telemetry blocks
try {
    $patientCount = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $doctorCount  = $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
    $deptCount    = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    
    // Fetch today's aggregate appointments counters
    $todayDate = date('Y-m-d');
    $stmtToday = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = ?");
    $stmtToday->execute([$todayDate]);
    $todayCount = $stmtToday->fetchColumn();

    // Fetch the 5 most recent appointment bookings for real-time monitoring
    $recentStmt = $pdo->query("
        SELECT a.appointment_id, p.full_name AS patient_name, d.name AS doctor_name, 
               a.appointment_date, a.appointment_time, a.status 
        FROM appointments a
        INNER JOIN patients p ON a.patient_id = p.patient_id
        INNER JOIN doctors d ON a.doctor_id = d.doctor_id
        ORDER BY a.created_at DESC LIMIT 5
    ");
    $recentAppointments = $recentStmt->fetchAll();
} catch (Exception $e) {
    // Graceful error fallback if the database tables are completely empty or unseeded yet
    $patientCount = 0;
    $doctorCount  = 0;
    $deptCount    = 0;
    $todayCount   = 0;
    $recentAppointments = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container my-5">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2 class="fw-bold text-primary"><i class="fa-solid fa-gauge-high me-2"></i>Administrative Control Center</h2>
            <p class="text-secondary">Real-time system telemetry and outpatient clinic workflow trackers for Nazareth Hospital OPD.</p>
        </div>
    </div>

    <!-- Administrative Quick Metric Blocks -->
    <div class="row g-3 mb-5">
        <div class="col-md-3">
            <div class="card bg-white border-start border-primary border-4 p-3 shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase small font-weight-bold">Total Patients</h6>
                        <h3 class="fw-bold m-0 text-dark"><?php echo $patientCount; ?></h3>
                    </div>
                    <div class="text-primary"><i class="fa-solid fa-hospital-user fa-2x"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-white border-start border-success border-4 p-3 shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase small font-weight-bold">Active Doctors</h6>
                        <h3 class="fw-bold m-0 text-dark"><?php echo $doctorCount; ?></h3>
                    </div>
                    <div class="text-success"><i class="fa-solid fa-user-doctor fa-2x"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-white border-start border-info border-4 p-3 shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase small font-weight-bold">Departments</h6>
                        <h3 class="fw-bold m-0 text-dark"><?php echo $deptCount; ?></h3>
                    </div>
                    <div class="text-info"><i class="fa-solid fa-sitemap fa-2x"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-white border-start border-warning border-4 p-3 shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase small font-weight-bold">Today's Visits</h6>
                        <h3 class="fw-bold m-0 text-dark"><?php echo $todayCount; ?></h3>
                    </div>
                    <div class="text-warning"><i class="fa-solid fa-calendar-day fa-2x"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Navigation Shortcuts & Live Feed Table -->
    <div class="row g-4">
        <!-- Module Navigation Panel -->
        <div class="col-lg-4">
            <div class="card bg-white border-0 p-4 mb-4">
                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fa-solid fa-folder-tree me-2 text-primary"></i>Management Framework</h5>
                <div class="d-grid gap-2">
                    <a href="departments/departments.php" class="btn btn-outline-primary text-start p-3 fw-semibold">
                        <i class="fa-solid fa-sitemap me-2"></i>Hospital Departments
                    </a>
                    <a href="doctors/manage_doctors.php" class="btn btn-outline-primary text-start p-3 fw-semibold disabled">
                        <i class="fa-solid fa-user-doctor me-2"></i>Clinical Physicians Directory
                    </a>
                    <a href="patients/manage_patients.php" class="btn btn-outline-primary text-start p-3 fw-semibold">
                        <i class="fa-solid fa-users me-2"></i>Patient Registry Logs
                    </a>
                    <a href="dashboard_reports.php" class="btn btn-outline-dark text-start p-3 fw-semibold">
                        <i class="fa-solid fa-file-invoice-dollar me-2"></i>Analytical Reports (PDF/Excel)
                    </a>
                </div>
            </div>
        </div>

        <!-- Real-time Live Appointments Activity Monitor -->
        <div class="col-lg-8">
            <div class="card bg-white border-0 p-4">
                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Recent Booking Requests Feed</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle m-0">
                        <thead class="table-light">
                            <tr>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Date/Time</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentAppointments)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No appointment operations have been registered yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentAppointments as $app): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($app['patient_name']); ?></td>
                                        <td>Dr. <?php echo htmlspecialchars($app['doctor_name']); ?></td>
                                        <td>
                                            <small class="d-block fw-bold"><?php echo htmlspecialchars($app['appointment_date']); ?></small>
                                            <small class="text-muted"><?php echo htmlspecialchars($app['appointment_time']); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <?php 
                                            $badgeClass = 'bg-secondary';
                                            if($app['status'] === 'Pending') $badgeClass = 'bg-warning text-dark';
                                            if($app['status'] === 'Approved') $badgeClass = 'bg-success';
                                            if($app['status'] === 'Rejected') $badgeClass = 'bg-danger';
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>"><?php echo $app['status']; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>
<? require_once __DIR__ . '';
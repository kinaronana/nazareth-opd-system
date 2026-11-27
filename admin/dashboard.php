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

    // Six-month hospital-wide appointment trend and current status distribution.
    $trendStmt = $pdo->query("SELECT DATE_FORMAT(appointment_date, '%Y-%m') AS month_key, COUNT(*) AS total FROM appointments WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) GROUP BY DATE_FORMAT(appointment_date, '%Y-%m') ORDER BY month_key");
    $trendRows = $trendStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $appointmentTrendLabels = [];
    $appointmentTrendValues = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime(date('Y-m-01') . " -$i months"));
        $appointmentTrendLabels[] = date('M Y', strtotime($month . '-01'));
        $appointmentTrendValues[] = (int)($trendRows[$month] ?? 0);
    }
    $statusStmt = $pdo->query("SELECT status, COUNT(*) AS total FROM appointments GROUP BY status");
    $appointmentStatusLabels = [];
    $appointmentStatusValues = [];
    foreach ($statusStmt->fetchAll() as $row) { $appointmentStatusLabels[] = (string)$row['status']; $appointmentStatusValues[] = (int)$row['total']; }

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
    $appointmentTrendLabels = [];
    $appointmentTrendValues = [];
    $appointmentStatusLabels = [];
    $appointmentStatusValues = [];
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
                        <h3 class="fw-bold m-0 text-dark" data-counter-key="patients"><?php echo $patientCount; ?></h3>
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
                        <h3 class="fw-bold m-0 text-dark" data-counter-key="doctors"><?php echo $doctorCount; ?></h3>
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
                        <h3 class="fw-bold m-0 text-dark" data-counter-key="departments"><?php echo $deptCount; ?></h3>
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
                        <h3 class="fw-bold m-0 text-dark" data-counter-key="today"><?php echo $todayCount; ?></h3>
                    </div>
                    <div class="text-warning"><i class="fa-solid fa-calendar-day fa-2x"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4 opd-analytics">
        <div class="col-lg-8"><section class="card border-0 p-4 h-100">
            <div class="d-flex justify-content-between align-items-start mb-3"><div><span class="opd-chart-kicker">HOSPITAL OVERVIEW</span><h5 class="fw-bold mb-1">Appointment Activity</h5><p class="text-muted small mb-0">Monthly bookings over the last six months</p></div><span class="opd-chart-icon"><i class="fa-solid fa-chart-line"></i></span></div>
            <div class="opd-chart-wrap"><canvas id="hospitaloverviewTrend" role="img" aria-label="Appointment Activity"></canvas></div>
        </section></div>
        <div class="col-lg-4"><section class="card border-0 p-4 h-100">
            <div class="d-flex justify-content-between align-items-start mb-3"><div><span class="opd-chart-kicker">STATUS MIX</span><h5 class="fw-bold mb-1">Appointment Status</h5><p class="text-muted small mb-0">Current status distribution</p></div><span class="opd-chart-icon teal"><i class="fa-solid fa-chart-pie"></i></span></div>
            <div class="opd-chart-wrap opd-chart-doughnut"><canvas id="hospitaloverviewStatus" role="img" aria-label="Appointment Status"></canvas></div>
        </section></div>
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
                    <a href="doctors/manage_doctors.php" class="btn btn-outline-primary text-start p-3 fw-semibold">
                        <i class="fa-solid fa-user-doctor me-2"></i>Clinical Physicians Directory
                    </a>
                    <a href="patients/manage_patients.php" class="btn btn-outline-primary text-start p-3 fw-semibold">
                        <i class="fa-solid fa-users me-2"></i>Patient Registry Logs
                    </a>
                    <a href="create_admin.php" class="btn btn-outline-primary text-start p-3 fw-semibold">
                        <i class="fa-solid fa-user-shield me-2"></i>Administrator Accounts
                    </a>
                    <a href="billing/manage_invoices.php" class="btn btn-outline-primary text-start p-3 fw-semibold">
                        <i class="fa-solid fa-file-invoice-dollar me-2"></i>Billing &amp; Invoices
                    </a>
                    <a href="messages.php" class="btn btn-outline-primary text-start p-3 fw-semibold">
                        <i class="fa-solid fa-envelope me-2"></i>Support Inbox
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>(function(){if(!window.Chart)return;
const labels=<?php echo json_encode($appointmentTrendLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
const values=<?php echo json_encode($appointmentTrendValues); ?>;
const sl=<?php echo json_encode($appointmentStatusLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
const sv=<?php echo json_encode($appointmentStatusValues); ?>;
const c=document.getElementById('hospitaloverviewTrend');if(c)new Chart(c,{type:'line',data:{labels,datasets:[{label:'Appointments',data:values,borderColor:'#1685c5',backgroundColor:'rgba(22,133,197,.14)',fill:true,tension:.38,borderWidth:3,pointRadius:4,pointBackgroundColor:'#20aaa9'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0},grid:{color:'rgba(35,66,82,.08)'}},x:{grid:{display:false}}}}});
const d=document.getElementById('hospitaloverviewStatus');if(d)new Chart(d,{type:'doughnut',data:{labels:sl,datasets:[{data:sv,backgroundColor:['#20aaa9','#1685c5','#f0a45d','#8b78d6','#e76f79','#70b88a','#91a4b7'],borderColor:'#fff',borderWidth:3,hoverOffset:5}]},options:{responsive:true,maintainAspectRatio:false,cutout:'68%',plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:8,padding:14}}}}});
})();</script>
<script src="/assets/js/dashboard-live.js" defer></script>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>
<? require_once __DIR__ . '';
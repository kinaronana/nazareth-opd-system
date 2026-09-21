<?php
// =========================================================================
// BLOCK 1: BACKEND LOGIC PROCESSING LAYER (Absolute Server Root Engine)
// =========================================================================

// Fail-proof absolute mapping anchors directly to your local project root folder
$project_root = $_SERVER['DOCUMENT_ROOT'] . '/nazareth-opd-system';

require_once $project_root . '/config/database.php';
require_once $project_root . '/config/auth_middleware.php';

// Enforce strict Administrator Role-Based Access Control
enforceRoleAccess(['Admin']);

$error = '';
$success = '';

// --- EXPORT PIPELINE ENGINE ---
if (isset($_GET['export_type'])) {
    $type = $_GET['export_type'];
    
    // 1. DOCTORS REGISTRY EXPORTER (CSV format mapping)
    if ($type === 'doctors_csv') {
        try {
            $stmt = $pdo->query("
                SELECT d.doctor_id, d.name, d.specialization, d.phone, dept.department_name, u.email, u.status 
                FROM doctors d
                INNER JOIN users u ON d.user_id = u.user_id
                INNER JOIN departments dept ON d.department_id = dept.department_id
                ORDER BY d.name ASC
            ");
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=nazareth_doctors_report_' . date('Ymd') . '.csv');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Doctor ID', 'Full Name', 'Medical Specialization', 'Assigned Department', 'Contact Phone', 'System Email', 'Status']);
            
            foreach ($records as $row) {
                fputcsv($output, [
                    '#DOC-00' . $row['doctor_id'],
                    $row['name'],
                    $row['specialization'],
                    $row['department_name'],
                    $row['phone'],
                    $row['email'],
                    $row['status']
                ]);
            }
            fclose($output);
            exit;
        } catch (Exception $e) {
            $error = "Export Fault: Failed to compile doctors dataset matrices.";
        }
    }
    
    // 2. PATIENTS REGISTRY EXPORTER (CSV format mapping)
    if ($type === 'patients_csv') {
        try {
            $stmt = $pdo->query("
                SELECT p.patient_id, p.full_name, p.gender, p.dob, p.phone, p.address, u.email, u.status 
                FROM patients p
                INNER JOIN users u ON p.user_id = u.user_id
                ORDER BY p.full_name ASC
            ");
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=nazareth_patients_report_' . date('Ymd') . '.csv');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Patient ID', 'Full Name', 'Gender', 'Date of Birth', 'Phone Number', 'Residential Address', 'Email', 'Status']);
            
            foreach ($records as $row) {
                fputcsv($output, [
                    '#PT-00' . $row['patient_id'],
                    $row['full_name'],
                    $row['gender'],
                    $row['dob'],
                    $row['phone'],
                    $row['address'],
                    $row['email'],
                    $row['status']
                ]);
            }
            fclose($output);
            exit;
        } catch (Exception $e) {
            $error = "Export Fault: Failed to compile patients dataset matrices.";
        }
    }

    // 3. APPOINTMENTS AUDIT LOG EXPORTER (CSV format mapping)
    if ($type === 'appointments_csv') {
        try {
            $stmt = $pdo->query("
                SELECT a.appointment_id, p.full_name AS patient_name, d.name AS doctor_name, 
                       dept.department_name, a.appointment_date, a.appointment_time, a.status, a.reason
                FROM appointments a
                INNER JOIN patients p ON a.patient_id = p.patient_id
                INNER JOIN doctors d ON a.doctor_id = d.doctor_id
                INNER JOIN departments dept ON d.department_id = dept.department_id
                ORDER BY a.appointment_date DESC
            ");
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=nazareth_appointments_audit_' . date('Ymd') . '.csv');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Ticket ID', 'Patient Name', 'Doctor Name', 'Department', 'Appointment Date', 'Time Slot', 'Status', 'Chief Complaint']);
            
            foreach ($records as $row) {
                fputcsv($output, [
                    '#OPD-00' . $row['appointment_id'],
                    $row['patient_name'],
                    $row['doctor_name'],
                    $row['department_name'],
                    $row['appointment_date'],
                    $row['appointment_time'],
                    $row['status'],
                    $row['reason'] ?: 'Routine Clinical Consultation'
                ]);
            }
            fclose($output);
            exit;
        } catch (Exception $e) {
            $error = "Export Fault: Failed to compile appointment transactions.";
        }
    }
}

// Gather statistical metric counters for visual dashboard summary blocks
try {
    $totalDocs  = $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
    $totalPats  = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $totalApps  = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
} catch (Exception $e) {
    $totalDocs = 0; $totalPats = 0; $totalApps = 0;
}

require_once $project_root . '/includes/header.php';
require_once $project_root . '/includes/navbar.php';
?>

<div class="container my-5">
    <div class="row mb-3">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none"><i class="fa-solid fa-gauge me-1"></i>Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Analytical Reports Hub</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-12">
            <h3 class="fw-bold text-primary"><i class="fa-solid fa-file-invoice-dollar me-2"></i>System Export & Data Analytics Center</h3>
            <p class="text-secondary">Generate and export historical database logs securely into spreadsheet formats for organizational audit guidelines.</p>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Card 1: Doctors Dataset -->
        <div class="col-md-4">
            <div class="card bg-white border-0 shadow-sm p-4 text-center h-100 d-flex flex-column justify-content-between">
                <div>
                    <i class="fa-solid fa-user-doctor fa-3x text-primary mb-3"></i>
                    <h5 class="fw-bold text-dark">Clinicians Master File</h5>
                    <p class="text-muted small">Contains doctor demographics, specialty nodes mappings, contact details, and account states configuration metrics.</p>
                    <span class="badge bg-primary mb-4"><?php echo (int)$totalDocs; ?> Active Profiles</span>
                </div>
                <div class="d-grid">
                    <a href="dashboard_reports.php?export_type=doctors_csv" class="btn btn-outline-primary fw-bold"><i class="fa-solid fa-file-csv me-1"></i>Export to CSV Sheet</a>
                </div>
            </div>
        </div>

        <!-- Card 2: Patients Dataset -->
        <div class="col-md-4">
            <div class="card bg-white border-0 shadow-sm p-4 text-center h-100 d-flex flex-column justify-content-between">
                <div>
                    <i class="fa-solid fa-hospital-user fa-3x text-success mb-3"></i>
                    <h5 class="fw-bold text-dark">Outpatient Registries Sheet</h5>
                    <p class="text-muted small">Tracks complete patient profiles, onboarding timestamps, raw phone strings, physical residential inputs, and age categories data rows.</p>
                    <span class="badge bg-success mb-4"><?php echo (int)$totalPats; ?> Registered Files</span>
                </div>
                <div class="d-grid">
                    <a href="dashboard_reports.php?export_type=patients_csv" class="btn btn-outline-success fw-bold"><i class="fa-solid fa-file-csv me-1"></i>Export to CSV Sheet</a>
                </div>
            </div>
        </div>

        <!-- Card 3: Appointment Audit Transactions -->
        <div class="col-md-4">
            <div class="card bg-white border-0 shadow-sm p-4 text-center h-100 d-flex flex-column justify-content-between">
                <div>
                    <i class="fa-solid fa-receipt fa-3x text-warning mb-3"></i>
                    <h5 class="fw-bold text-dark">Visit Transactions History</h5>
                    <p class="text-muted small">Comprehensive audit trails of consultation ticket parameters, dates, time block allocations, clinical complaints text, and outcome states records.</p>
                    <span class="badge bg-warning text-dark mb-4"><?php echo (int)$totalApps; ?> Processed Tickets</span>
                </div>
                <div class="d-grid">
                    <a href="dashboard_reports.php?export_type=appointments_csv" class="btn btn-outline-warning fw-bold"><i class="fa-solid fa-file-csv me-1"></i>Export to CSV Sheet</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once $project_root . '/includes/footer.php';
?>

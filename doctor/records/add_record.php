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

// Patients this doctor has seen, for the dropdown.
$patientsStmt = $pdo->prepare("
    SELECT DISTINCT p.patient_id, p.full_name, p.user_id
    FROM appointments a INNER JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.doctor_id = ? ORDER BY p.full_name ASC
");
$patientsStmt->execute([$doctor_id]);
$myPatients = $patientsStmt->fetchAll();

$preselectPatient = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;
$preselectAppointment = isset($_GET['appointment_id']) ? (int) $_GET['appointment_id'] : 0;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int) $_POST['patient_id'];
    $record_type = trim($_POST['record_type']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $record_date = $_POST['record_date'];
    $appointment_id = !empty($_POST['appointment_id']) ? (int) $_POST['appointment_id'] : null;

    if (empty($patient_id) || empty($title) || empty($description) || empty($record_date)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO medical_records (patient_id, doctor_id, appointment_id, record_type, title, description, record_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$patient_id, $doctor_id, $appointment_id, $record_type, $title, $description, $record_date]);

            $patientUser = $pdo->prepare("SELECT user_id FROM patients WHERE patient_id = ?");
            $patientUser->execute([$patient_id]);
            $patientUserId = $patientUser->fetchColumn();
            if ($patientUserId) {
                notifyUser($pdo, $patientUserId, "Dr. {$doctorProfile['name']} added a new record: $title", '/patient/medical_records.php');
                logActivity($pdo, $patientUserId, "New medical record added: $title", 'folder-open');
            }
            $success = 'Medical record saved successfully.';
        } catch (Exception $e) {
            $error = 'Could not save this record. Have you run the schema_additions.sql migration yet?';
        }
    }
}

require_once $project_root . '/includes/header.php';
require_once $project_root . '/includes/navbar.php';
?>
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm p-4">
                <h4 class="fw-bold text-primary border-bottom pb-2 mb-4"><i class="fa-solid fa-folder-open me-2"></i>Add Medical Record</h4>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Patient <span class="text-danger">*</span></label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Select a patient...</option>
                            <?php foreach ($myPatients as $p): ?>
                                <option value="<?php echo (int) $p['patient_id']; ?>" <?php echo $preselectPatient === (int) $p['patient_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" name="appointment_id" value="<?php echo $preselectAppointment ?: ''; ?>">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Record Type</label>
                            <select name="record_type" class="form-select">
                                <option>Consultation Note</option>
                                <option>Diagnosis</option>
                                <option>Lab Order</option>
                                <option>Referral</option>
                                <option>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Date <span class="text-danger">*</span></label>
                            <input type="date" name="record_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Follow-up consultation" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="/doctor/dashboard.php" class="btn btn-light fw-semibold px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary fw-bold px-4">Save Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once $project_root . '/includes/footer.php'; ?>

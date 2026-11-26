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

$patientsStmt = $pdo->prepare("
    SELECT DISTINCT p.patient_id, p.full_name
    FROM appointments a INNER JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.doctor_id = ? ORDER BY p.full_name ASC
");
$patientsStmt->execute([$doctor_id]);
$myPatients = $patientsStmt->fetchAll();

$preselectPatient = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int) $_POST['patient_id'];
    $medication_name = trim($_POST['medication_name']);
    $dosage = trim($_POST['dosage']);
    $frequency = trim($_POST['frequency']);
    $duration = trim($_POST['duration']);
    $notes = trim($_POST['notes']);
    $issued_date = $_POST['issued_date'];

    if (empty($patient_id) || empty($medication_name) || empty($dosage) || empty($frequency) || empty($duration) || empty($issued_date)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO prescriptions (patient_id, doctor_id, medication_name, dosage, frequency, duration, notes, issued_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')");
            $stmt->execute([$patient_id, $doctor_id, $medication_name, $dosage, $frequency, $duration, $notes, $issued_date]);

            $patientUser = $pdo->prepare("SELECT user_id FROM patients WHERE patient_id = ?");
            $patientUser->execute([$patient_id]);
            $patientUserId = $patientUser->fetchColumn();
            if ($patientUserId) {
                notifyUser($pdo, $patientUserId, "Dr. {$doctorProfile['name']} issued you a new prescription: $medication_name", '/patient/prescriptions.php');
                logActivity($pdo, $patientUserId, "New prescription issued: $medication_name", 'file-prescription');
            }
            $success = 'Prescription issued successfully.';
        } catch (Exception $e) {
            $error = 'Could not save this prescription. Have you run the schema_additions.sql migration yet?';
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
                <h4 class="fw-bold text-primary border-bottom pb-2 mb-4"><i class="fa-solid fa-file-prescription me-2"></i>Issue Prescription</h4>
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
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Medication <span class="text-danger">*</span></label>
                            <input type="text" name="medication_name" class="form-control" placeholder="e.g. Amoxicillin 500mg" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Dosage <span class="text-danger">*</span></label>
                            <input type="text" name="dosage" class="form-control" placeholder="e.g. 1 tablet" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Frequency <span class="text-danger">*</span></label>
                            <input type="text" name="frequency" class="form-control" placeholder="e.g. Twice daily" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Duration <span class="text-danger">*</span></label>
                            <input type="text" name="duration" class="form-control" placeholder="e.g. 7 days" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Date Issued <span class="text-danger">*</span></label>
                            <input type="date" name="issued_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Additional Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="e.g. Take with food"></textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="/doctor/dashboard.php" class="btn btn-light fw-semibold px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary fw-bold px-4">Issue Prescription</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once $project_root . '/includes/footer.php'; ?>

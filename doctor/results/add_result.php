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
    $test_name = trim($_POST['test_name']);
    $result_summary = trim($_POST['result_summary']);
    $result_date = $_POST['result_date'];
    $status = $_POST['status'];
    $filePath = null;

    if (empty($patient_id) || empty($test_name) || empty($result_summary) || empty($result_date)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            if (!empty($_FILES['attachment']['name'])) {
                $uploadDir = $project_root . '/assets/uploads/test_results/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['attachment']['name']));
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $safeName)) {
                    $filePath = $safeName;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO test_results (patient_id, doctor_id, test_name, result_summary, file_path, result_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$patient_id, $doctor_id, $test_name, $result_summary, $filePath, $result_date, $status]);

            $patientUser = $pdo->prepare("SELECT user_id FROM patients WHERE patient_id = ?");
            $patientUser->execute([$patient_id]);
            $patientUserId = $patientUser->fetchColumn();
            if ($patientUserId) {
                notifyUser($pdo, $patientUserId, "New test result available: $test_name", '/patient/test_results.php');
                logActivity($pdo, $patientUserId, "New test result uploaded: $test_name", 'flask');
            }
            $success = 'Test result saved successfully.';
        } catch (Exception $e) {
            $error = 'Could not save this test result. Have you run the schema_additions.sql migration yet?';
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
                <h4 class="fw-bold text-primary border-bottom pb-2 mb-4"><i class="fa-solid fa-flask me-2"></i>Add Test Result</h4>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
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
                            <label class="form-label fw-bold">Test Name <span class="text-danger">*</span></label>
                            <input type="text" name="test_name" class="form-control" placeholder="e.g. Full Blood Count" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Date <span class="text-danger">*</span></label>
                            <input type="date" name="result_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Status</label>
                            <select name="status" class="form-select">
                                <option value="Completed">Completed</option>
                                <option value="Pending">Pending</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Result Summary <span class="text-danger">*</span></label>
                        <textarea name="result_summary" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Attach File (optional)</label>
                        <input type="file" name="attachment" class="form-control">
                        <div class="small text-muted mt-1">PDF or image of the lab report, if available.</div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="/doctor/dashboard.php" class="btn btn-light fw-semibold px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary fw-bold px-4">Save Test Result</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once $project_root . '/includes/footer.php'; ?>

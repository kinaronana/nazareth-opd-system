<?php
$project_root = dirname(__DIR__, 2);
require_once $project_root . '/config/database.php';
require_once $project_root . '/config/auth_middleware.php';
require_once $project_root . '/config/helpers.php';

enforceRoleAccess(['Admin']);

$patients = $pdo->query("SELECT patient_id, full_name, user_id FROM patients ORDER BY full_name ASC")->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int) $_POST['patient_id'];
    $description = trim($_POST['description']);
    $amount = (float) $_POST['amount'];
    $issued_date = $_POST['issued_date'];

    if (empty($patient_id) || empty($description) || $amount <= 0 || empty($issued_date)) {
        $error = 'Please fill in all fields with a valid amount.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO invoices (patient_id, description, amount, issued_date, status) VALUES (?, ?, ?, ?, 'Unpaid')");
            $stmt->execute([$patient_id, $description, $amount, $issued_date]);

            $userStmt = $pdo->prepare("SELECT user_id FROM patients WHERE patient_id = ?");
            $userStmt->execute([$patient_id]);
            $patientUserId = $userStmt->fetchColumn();
            if ($patientUserId) {
                notifyUser($pdo, $patientUserId, "New invoice issued: $description (KES " . number_format($amount, 2) . ")", '/patient/billing.php');
            }
            $success = 'Invoice created successfully.';
        } catch (Exception $e) {
            $error = 'Could not save this invoice. Have you run the schema_additions.sql migration yet?';
        }
    }
}

require_once $project_root . '/includes/header.php';
require_once $project_root . '/includes/navbar.php';
?>
<div class="container my-5">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li><li class="breadcrumb-item active">Create Invoice</li></ol></nav>
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h4 class="fw-bold text-primary border-bottom pb-2 mb-4"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Create Invoice</h4>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Patient <span class="text-danger">*</span></label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Select a patient...</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?php echo (int) $p['patient_id']; ?>"><?php echo htmlspecialchars($p['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Description <span class="text-danger">*</span></label>
                        <input type="text" name="description" class="form-control" placeholder="e.g. Consultation fee - Dr. Njeri" required>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Amount (KES) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Issue Date <span class="text-danger">*</span></label>
                            <input type="date" name="issued_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="manage_invoices.php" class="btn btn-light fw-semibold px-4">View All Invoices</a>
                        <button type="submit" class="btn btn-primary fw-bold px-4">Create Invoice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once $project_root . '/includes/footer.php'; ?>

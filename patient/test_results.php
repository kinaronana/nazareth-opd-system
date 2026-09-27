<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';

enforceRoleAccess(['Patient']);

$stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$patient_id = $stmt->fetchColumn();

$results = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.result_id, r.test_name, r.result_summary, r.file_path, r.result_date, r.status, d.name AS doctor_name
        FROM test_results r
        INNER JOIN doctors d ON r.doctor_id = d.doctor_id
        WHERE r.patient_id = ?
        ORDER BY r.result_date DESC
    ");
    $stmt->execute([$patient_id]);
    $results = $stmt->fetchAll();
} catch (Exception $e) {
    $results = [];
}

$activePage = 'results';
require_once __DIR__ . '/../includes/patient_layout_header.php';
?>

<h3 class="fw-bold text-primary mb-4">Test Results</h3>

<?php if (empty($results)): ?>
    <div class="card border-0 shadow-sm text-center py-5 text-muted">
        <i class="fa-solid fa-flask fa-2x mb-3"></i>
        No test results have been uploaded yet.
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($results as $r): ?>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge <?php echo $r['status'] === 'Completed' ? 'bg-success' : 'bg-warning text-dark'; ?>"><?php echo htmlspecialchars($r['status']); ?></span>
                            <span class="small text-muted"><?php echo date('j M Y', strtotime($r['result_date'])); ?></span>
                        </div>
                        <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($r['test_name']); ?></h6>
                        <p class="small text-secondary mb-2"><?php echo nl2br(htmlspecialchars($r['result_summary'])); ?></p>
                        <div class="small text-muted mb-2">Ordered by Dr. <?php echo htmlspecialchars($r['doctor_name']); ?></div>
                        <?php if (!empty($r['file_path'])): ?>
                            <a href="/assets/uploads/test_results/<?php echo htmlspecialchars($r['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-file-arrow-down me-1"></i>View Attachment</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/patient_layout_footer.php'; ?>

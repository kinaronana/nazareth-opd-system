<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';

enforceRoleAccess(['Patient']);

$stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$patient_id = $stmt->fetchColumn();

$records = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.record_id, r.record_type, r.title, r.description, r.record_date, d.name AS doctor_name
        FROM medical_records r
        INNER JOIN doctors d ON r.doctor_id = d.doctor_id
        WHERE r.patient_id = ?
        ORDER BY r.record_date DESC
    ");
    $stmt->execute([$patient_id]);
    $records = $stmt->fetchAll();
} catch (Exception $e) {
    $records = [];
}

$activePage = 'records';
require_once __DIR__ . '/../includes/patient_layout_header.php';
?>

<h3 class="fw-bold text-primary mb-4">Medical Records</h3>

<?php if (empty($records)): ?>
    <div class="card border-0 shadow-sm text-center py-5 text-muted">
        <i class="fa-solid fa-folder-open fa-2x mb-3"></i>
        No medical records have been added to your file yet.
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($records as $rec): ?>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-primary-subtle text-primary"><?php echo htmlspecialchars($rec['record_type']); ?></span>
                            <span class="small text-muted"><?php echo date('j M Y', strtotime($rec['record_date'])); ?></span>
                        </div>
                        <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($rec['title']); ?></h6>
                        <p class="small text-secondary mb-2"><?php echo nl2br(htmlspecialchars($rec['description'])); ?></p>
                        <div class="small text-muted">Recorded by Dr. <?php echo htmlspecialchars($rec['doctor_name']); ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/patient_layout_footer.php'; ?>

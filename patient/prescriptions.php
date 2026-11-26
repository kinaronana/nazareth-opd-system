<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';

enforceRoleAccess(['Patient']);

$stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$patient_id = $stmt->fetchColumn();

$prescriptions = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.prescription_id, p.medication_name, p.dosage, p.frequency, p.duration, p.notes,
               p.issued_date, p.status, d.name AS doctor_name
        FROM prescriptions p
        INNER JOIN doctors d ON p.doctor_id = d.doctor_id
        WHERE p.patient_id = ?
        ORDER BY p.issued_date DESC
    ");
    $stmt->execute([$patient_id]);
    $prescriptions = $stmt->fetchAll();
} catch (Exception $e) {
    $prescriptions = [];
}

$activePage = 'prescriptions';
require_once __DIR__ . '/../includes/patient_layout_header.php';
?>

<h3 class="fw-bold text-primary mb-4">Prescriptions</h3>

<?php if (empty($prescriptions)): ?>
    <div class="card border-0 shadow-sm text-center py-5 text-muted">
        <i class="fa-solid fa-file-prescription fa-2x mb-3"></i>
        You have no prescriptions on file yet.
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle m-0">
                <thead class="table-light">
                    <tr><th>Medication</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Prescribed By</th><th>Date</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($prescriptions as $p): ?>
                        <tr>
                            <td class="fw-semibold"><?php echo htmlspecialchars($p['medication_name']); ?><?php if ($p['notes']): ?><div class="small text-muted"><?php echo htmlspecialchars($p['notes']); ?></div><?php endif; ?></td>
                            <td><?php echo htmlspecialchars($p['dosage']); ?></td>
                            <td><?php echo htmlspecialchars($p['frequency']); ?></td>
                            <td><?php echo htmlspecialchars($p['duration']); ?></td>
                            <td>Dr. <?php echo htmlspecialchars($p['doctor_name']); ?></td>
                            <td><?php echo date('j M Y', strtotime($p['issued_date'])); ?></td>
                            <td><span class="badge <?php echo $p['status'] === 'Active' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo htmlspecialchars($p['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/patient_layout_footer.php'; ?>

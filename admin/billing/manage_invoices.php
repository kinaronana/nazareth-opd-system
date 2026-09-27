<?php
$project_root = dirname(__DIR__, 2);
require_once $project_root . '/config/database.php';
require_once $project_root . '/config/auth_middleware.php';

enforceRoleAccess(['Admin']);

$invoices = [];
try {
    $invoices = $pdo->query("
        SELECT i.invoice_id, i.description, i.amount, i.status, i.issued_date, i.paid_date, p.full_name
        FROM invoices i INNER JOIN patients p ON i.patient_id = p.patient_id
        ORDER BY i.issued_date DESC
    ")->fetchAll();
} catch (Exception $e) {
    $invoices = [];
}

require_once $project_root . '/includes/header.php';
require_once $project_root . '/includes/navbar.php';
?>
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <nav aria-label="breadcrumb"><ol class="breadcrumb m-0"><li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li><li class="breadcrumb-item active">Invoices</li></ol></nav>
        <a href="create_invoice.php" class="btn btn-primary fw-bold"><i class="fa-solid fa-plus me-1"></i>New Invoice</a>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle m-0">
                <thead class="table-light">
                    <tr><th>Patient</th><th>Description</th><th>Amount</th><th>Issued</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-5">No invoices yet, or the billing tables haven't been migrated.</td></tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars($inv['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($inv['description']); ?></td>
                                <td>KES <?php echo number_format($inv['amount'], 2); ?></td>
                                <td><?php echo date('j M Y', strtotime($inv['issued_date'])); ?></td>
                                <td>
                                    <span class="badge <?php echo $inv['status'] === 'Paid' ? 'bg-success' : 'bg-warning text-dark'; ?>"><?php echo htmlspecialchars($inv['status']); ?></span>
                                    <?php if ($inv['paid_date']): ?><div class="small text-muted">Paid <?php echo date('j M Y', strtotime($inv['paid_date'])); ?></div><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once $project_root . '/includes/footer.php'; ?>

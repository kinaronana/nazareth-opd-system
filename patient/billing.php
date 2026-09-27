<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';
require_once __DIR__ . '/../config/helpers.php';

enforceRoleAccess(['Patient']);

$stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$patient_id = $stmt->fetchColumn();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_invoice_id'])) {
    $invoiceId = (int) $_POST['pay_invoice_id'];
    try {
        $stmt = $pdo->prepare("UPDATE invoices SET status = 'Paid', paid_date = CURDATE() WHERE invoice_id = ? AND patient_id = ? AND status = 'Unpaid'");
        $stmt->execute([$invoiceId, $patient_id]);
        if ($stmt->rowCount() > 0) {
            logActivity($pdo, $_SESSION['user_id'], 'Made a payment', 'credit-card');
            $message = 'Payment recorded successfully.';
        }
    } catch (Exception $e) {
        $message = 'Could not process payment right now.';
    }
}

$invoices = [];
try {
    $stmt = $pdo->prepare("SELECT invoice_id, description, amount, status, issued_date, paid_date FROM invoices WHERE patient_id = ? ORDER BY issued_date DESC");
    $stmt->execute([$patient_id]);
    $invoices = $stmt->fetchAll();
} catch (Exception $e) {
    $invoices = [];
}

$outstanding = array_sum(array_map(fn($i) => $i['status'] === 'Unpaid' ? (float) $i['amount'] : 0, $invoices));

$activePage = 'billing';
require_once __DIR__ . '/../includes/patient_layout_header.php';
?>

<h3 class="fw-bold text-primary mb-4">Billing &amp; Payments</h3>

<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

<div class="card border-0 shadow-sm mb-4" style="background:#fff6e6;">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <div class="small fw-semibold text-warning-emphasis">Outstanding Balance</div>
            <div class="fs-3 fw-bold">KES <?php echo number_format($outstanding, 2); ?></div>
        </div>
        <i class="fa-solid fa-file-invoice-dollar fa-2x text-warning"></i>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle m-0">
            <thead class="table-light">
                <tr><th>Description</th><th>Amount</th><th>Issued</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-5"><i class="fa-solid fa-receipt fa-2x mb-2 d-block"></i>No invoices on file yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($inv['description']); ?></td>
                            <td class="fw-semibold">KES <?php echo number_format($inv['amount'], 2); ?></td>
                            <td><?php echo date('j M Y', strtotime($inv['issued_date'])); ?></td>
                            <td>
                                <span class="badge <?php echo $inv['status'] === 'Paid' ? 'bg-success' : 'bg-warning text-dark'; ?>"><?php echo htmlspecialchars($inv['status']); ?></span>
                                <?php if ($inv['status'] === 'Paid' && $inv['paid_date']): ?>
                                    <div class="small text-muted">Paid <?php echo date('j M Y', strtotime($inv['paid_date'])); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($inv['status'] === 'Unpaid'): ?>
                                    <form method="POST" onsubmit="return confirm('Simulate payment of KES <?php echo number_format($inv['amount'], 2); ?>?');">
                                        <input type="hidden" name="pay_invoice_id" value="<?php echo (int) $inv['invoice_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-primary fw-semibold">Pay Now</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="small text-muted mt-3"><i class="fa-solid fa-circle-info me-1"></i>This is a demo payment flow &mdash; no real card or M-Pesa transaction is processed. Integrating a real payment gateway (e.g. M-Pesa Daraja, Stripe) can be added on request.</p>

<?php require_once __DIR__ . '/../includes/patient_layout_footer.php'; ?>

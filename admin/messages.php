<?php
$project_root = dirname(__DIR__);
require_once $project_root . '/config/database.php';
require_once $project_root . '/config/auth_middleware.php';

enforceRoleAccess(['Admin']);

$myUserId = $_SESSION['user_id'];

try {
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ?")->execute([$myUserId]);
} catch (Exception $e) {
}

$messages = [];
try {
    $stmt = $pdo->prepare("
        SELECT m.*, su.username AS sender_username
        FROM messages m INNER JOIN users su ON m.sender_id = su.user_id
        WHERE m.receiver_id = ?
        ORDER BY m.sent_at DESC
    ");
    $stmt->execute([$myUserId]);
    $messages = $stmt->fetchAll();
} catch (Exception $e) {
    $messages = [];
}

require_once $project_root . '/includes/header.php';
require_once $project_root . '/includes/navbar.php';
?>
<div class="container my-5">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li><li class="breadcrumb-item active">Support Inbox</li></ol></nav>
    <h3 class="fw-bold text-primary mb-4">Support Inbox</h3>
    <div class="card border-0 shadow-sm">
        <div class="list-group list-group-flush">
            <?php if (empty($messages)): ?>
                <div class="text-center text-muted py-5">No support messages yet, or the messages table hasn't been migrated.</div>
            <?php else: ?>
                <?php foreach ($messages as $m): ?>
                    <div class="list-group-item py-3">
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold small">From: <?php echo htmlspecialchars($m['sender_username']); ?></span>
                            <span class="small text-muted"><?php echo date('j M Y, g:i A', strtotime($m['sent_at'])); ?></span>
                        </div>
                        <div class="fw-semibold small"><?php echo htmlspecialchars($m['subject']); ?></div>
                        <div class="small text-secondary"><?php echo nl2br(htmlspecialchars($m['body'])); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once $project_root . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';

enforceRoleAccess(['Patient']);

$myUserId = $_SESSION['user_id'];

// Mark all as read when the page is viewed.
try {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$myUserId]);
} catch (Exception $e) {
}

$notifications = [];
try {
    $stmt = $pdo->prepare("SELECT notification_id, message, link, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$myUserId]);
    $notifications = $stmt->fetchAll();
} catch (Exception $e) {
    $notifications = [];
}

$activePage = 'notifications';
require_once __DIR__ . '/../includes/patient_layout_header.php';
?>

<h3 class="fw-bold text-primary mb-4">Notifications</h3>

<div class="card border-0 shadow-sm">
    <div class="list-group list-group-flush">
        <?php if (empty($notifications)): ?>
            <div class="text-center text-muted py-5"><i class="fa-solid fa-bell-slash fa-2x mb-2 d-block"></i>You're all caught up &mdash; no notifications.</div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <?php $row = '<div class="d-flex justify-content-between align-items-start list-group-item py-3">
                    <div class="me-3"><i class="fa-solid fa-circle-info text-primary me-2"></i>' . htmlspecialchars($n['message']) . '</div>
                    <span class="small text-muted text-nowrap">' . date('j M Y, g:i A', strtotime($n['created_at'])) . '</span>
                </div>'; ?>
                <?php if (!empty($n['link'])): ?>
                    <a href="<?php echo htmlspecialchars($n['link']); ?>" class="text-decoration-none text-dark"><?php echo $row; ?></a>
                <?php else: ?>
                    <?php echo $row; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/patient_layout_footer.php'; ?>

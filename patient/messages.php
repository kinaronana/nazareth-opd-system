<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';
require_once __DIR__ . '/../config/helpers.php';

enforceRoleAccess(['Patient']);

$myUserId = $_SESSION['user_id'];
$error = '';
$success = '';

// Recipients: doctors this patient has had an appointment with, plus generic Support (first Admin).
$stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->execute([$myUserId]);
$patient_id = $stmt->fetchColumn();

$doctorRecipients = $pdo->prepare("
    SELECT DISTINCT d.user_id, d.name
    FROM appointments a
    INNER JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE a.patient_id = ?
    ORDER BY d.name ASC
");
$doctorRecipients->execute([$patient_id]);
$doctorRecipients = $doctorRecipients->fetchAll();

$supportUserId = $pdo->query("
    SELECT u.user_id FROM users u INNER JOIN roles r ON u.role_id = r.role_id
    WHERE r.role_name = 'Admin' ORDER BY u.user_id ASC LIMIT 1
")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = (int) ($_POST['receiver_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if (empty($receiver_id) || empty($subject) || empty($body)) {
        $error = 'Please choose a recipient and fill in both subject and message.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?, ?, ?, ?)");
            $stmt->execute([$myUserId, $receiver_id, $subject, $body]);

            $roleStmt = $pdo->prepare("SELECT r.role_name FROM users u INNER JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ?");
            $roleStmt->execute([$receiver_id]);
            $receiverRole = $roleStmt->fetchColumn();
            $inboxLink = ['Doctor' => '/doctor/messages.php', 'Admin' => '/admin/messages.php'][$receiverRole] ?? '/patient/messages.php';

            notifyUser($pdo, $receiver_id, "New message: " . $subject, $inboxLink);
            logActivity($pdo, $myUserId, 'Sent a message: ' . $subject, 'envelope');
            $success = 'Message sent successfully.';
        } catch (Exception $e) {
            $error = 'Could not send your message right now.';
        }
    }
}

// Mark all as read when viewing inbox
try {
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ?")->execute([$myUserId]);
} catch (Exception $e) {
}

$messages = [];
try {
    $stmt = $pdo->prepare("
        SELECT m.*, su.username AS sender_username, ru.username AS receiver_username
        FROM messages m
        INNER JOIN users su ON m.sender_id = su.user_id
        INNER JOIN users ru ON m.receiver_id = ru.user_id
        WHERE m.sender_id = ? OR m.receiver_id = ?
        ORDER BY m.sent_at DESC
    ");
    $stmt->execute([$myUserId, $myUserId]);
    $messages = $stmt->fetchAll();
} catch (Exception $e) {
    $messages = [];
}

$activePage = 'messages';
require_once __DIR__ . '/../includes/patient_layout_header.php';
?>

<h3 class="fw-bold text-primary mb-4">Messages</h3>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">New Message</h6>
                <?php if ($error): ?><div class="alert alert-danger small"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success small"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">To</label>
                        <select name="receiver_id" class="form-select" required>
                            <option value="">Choose a recipient...</option>
                            <?php if ($supportUserId): ?><option value="<?php echo (int) $supportUserId; ?>">Hospital Support / Admin</option><?php endif; ?>
                            <?php foreach ($doctorRecipients as $doc): ?>
                                <option value="<?php echo (int) $doc['user_id']; ?>">Dr. <?php echo htmlspecialchars($doc['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($doctorRecipients)): ?><div class="small text-muted mt-1">You'll be able to message a doctor after your first appointment.</div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Subject</label>
                        <input type="text" name="subject" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Message</label>
                        <textarea name="body" class="form-control" rows="4" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary fw-bold w-100">Send Message</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Inbox</h6>
                <?php if (empty($messages)): ?>
                    <p class="text-muted small m-0 py-4 text-center">No messages yet.</p>
                <?php else: ?>
                    <?php foreach ($messages as $m): ?>
                        <div class="border-bottom py-3">
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold small"><?php echo $m['sender_id'] == $myUserId ? 'You &rarr; ' . htmlspecialchars($m['receiver_username']) : htmlspecialchars($m['sender_username']) . ' &rarr; You'; ?></span>
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
</div>

<?php require_once __DIR__ . '/../includes/patient_layout_footer.php'; ?>

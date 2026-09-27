<?php
$project_root = dirname(__DIR__);
require_once $project_root . '/config/database.php';
require_once $project_root . '/config/auth_middleware.php';
require_once $project_root . '/config/helpers.php';

enforceRoleAccess(['Doctor']);

$myUserId = $_SESSION['user_id'];

$docStmt = $pdo->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
$docStmt->execute([$myUserId]);
$doctor_id = $docStmt->fetchColumn();

$error = '';
$success = '';

$patientRecipients = $pdo->prepare("
    SELECT DISTINCT p.user_id, p.full_name
    FROM appointments a INNER JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.doctor_id = ? ORDER BY p.full_name ASC
");
$patientRecipients->execute([$doctor_id]);
$patientRecipients = $patientRecipients->fetchAll();

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
            notifyUser($pdo, $receiver_id, "New message from your doctor: " . $subject, '/patient/messages.php');
            $success = 'Message sent successfully.';
        } catch (Exception $e) {
            $error = 'Could not send your message right now.';
        }
    }
}

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

require_once $project_root . '/includes/header.php';
require_once $project_root . '/includes/navbar.php';
?>
<div class="container my-5">
    <h3 class="fw-bold text-primary mb-4">Messages</h3>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm p-4">
                <h6 class="fw-bold mb-3">New Message</h6>
                <?php if ($error): ?><div class="alert alert-danger small"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success small"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">To</label>
                        <select name="receiver_id" class="form-select" required>
                            <option value="">Choose a patient...</option>
                            <?php foreach ($patientRecipients as $p): ?>
                                <option value="<?php echo (int) $p['user_id']; ?>"><?php echo htmlspecialchars($p['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
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
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm p-4">
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
<?php require_once $project_root . '/includes/footer.php'; ?>

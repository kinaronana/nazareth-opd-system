<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';
require_once __DIR__ . '/../config/helpers.php';

enforceRoleAccess(['Patient']);

$myUserId = $_SESSION['user_id'];
$success = '';
$error = '';

$supportUserId = $pdo->query("
    SELECT u.user_id FROM users u INNER JOIN roles r ON u.role_id = r.role_id
    WHERE r.role_name = 'Admin' ORDER BY u.user_id ASC LIMIT 1
")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    if (empty($subject) || empty($body)) {
        $error = 'Please fill in both the subject and your message.';
    } elseif (!$supportUserId) {
        $error = 'No support account is configured yet. Please try again later.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?, ?, ?, ?)");
            $stmt->execute([$myUserId, $supportUserId, $subject, $body]);
            notifyUser($pdo, $supportUserId, "Support request: " . $subject, '/admin/messages.php');
            logActivity($pdo, $myUserId, 'Contacted support', 'circle-question');
            $success = "Your message has been sent to our support team. We'll get back to you soon.";
        } catch (Exception $e) {
            $error = 'Could not send your message right now.';
        }
    }
}

$activePage = 'help';
require_once __DIR__ . '/../includes/patient_layout_header.php';
?>

<h3 class="fw-bold text-primary mb-4">Help &amp; Support</h3>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Frequently Asked Questions</h6>
                <div class="accordion" id="faqAccordion">
                    <?php
                    $faqs = [
                        ['How do I book an appointment?', 'Go to "Book Appointment" in the sidebar, choose a department and doctor, then pick an available date and time slot.'],
                        ['How do I cancel or reschedule?', 'Open "My Appointments", find the appointment under the Upcoming tab, and use the Cancel button. To reschedule, cancel and book a new slot.'],
                        ['Where can I see my prescriptions and test results?', 'Both are available from the sidebar under "Prescriptions" and "Test Results" once your doctor has added them to your file.'],
                        ['How do payments work?', 'Outstanding invoices appear under "Billing & Payments" with a Pay Now option. This is currently a demo flow while a real payment gateway is integrated.'],
                        ['How do I contact my doctor directly?', 'Use "Messages" in the sidebar — once you\'ve had an appointment with a doctor, you can message them directly from there.'],
                    ];
                    foreach ($faqs as $i => $faq):
                    ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?php echo $i === 0 ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?php echo $i; ?>">
                                    <?php echo htmlspecialchars($faq[0]); ?>
                                </button>
                            </h2>
                            <div id="faq<?php echo $i; ?>" class="accordion-collapse collapse <?php echo $i === 0 ? 'show' : ''; ?>" data-bs-parent="#faqAccordion">
                                <div class="accordion-body small text-secondary"><?php echo htmlspecialchars($faq[1]); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Contact Support</h6>
                <?php if ($success): ?><div class="alert alert-success small"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger small"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Subject</label>
                        <input type="text" name="subject" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Message</label>
                        <textarea name="body" class="form-control" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary fw-bold w-100">Send to Support</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/patient_layout_footer.php'; ?>

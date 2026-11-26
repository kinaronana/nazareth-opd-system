<?php
// =========================================================================
// BLOCK 1: BACKEND LOGIC PROCESSING LAYER
// =========================================================================

require_once __DIR__ . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Any authenticated user (Admin, Doctor, or Patient) may change their own password.
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New password and confirmation do not match.";
    } elseif ($current_password === $new_password) {
        $error = "New password must be different from your current password.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($current_password, $user['password'])) {
                $error = "Your current password is incorrect.";
            } else {
                $hashedPassword = password_hash($new_password, PASSWORD_BCRYPT);

                $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $updateStmt->execute([$hashedPassword, $_SESSION['user_id']]);

                $success = "Your password has been updated successfully.";
            }
        } catch (Exception $e) {
            $error = "Database Execution Error: Failed to update your password.";
        }
    }
}

// =========================================================================
// BLOCK 2: PRESENTATION LAYER
// =========================================================================
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card bg-white border-0 shadow-sm p-4">
                <h4 class="fw-bold text-primary border-bottom pb-2 mb-4">
                    <i class="fa-solid fa-key me-2"></i>Change Password
                </h4>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger shadow-sm"><i class="fa-solid fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success shadow-sm"><i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form action="change_password.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Current Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="current_password" id="cpCurrent" class="form-control" required>
                            <span class="input-group-text bg-white password-toggle" role="button" data-target="cpCurrent"><i class="fa-solid fa-eye text-muted"></i></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="new_password" id="cpNew" class="form-control" placeholder="At least 8 characters" required minlength="8">
                            <span class="input-group-text bg-white password-toggle" role="button" data-target="cpNew"><i class="fa-solid fa-eye text-muted"></i></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Confirm New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="confirm_password" id="cpConfirm" class="form-control" required minlength="8">
                            <span class="input-group-text bg-white password-toggle" role="button" data-target="cpConfirm"><i class="fa-solid fa-eye text-muted"></i></span>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 border-top pt-3">
                        <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm">Update Password</button>
                    </div>
                </form>
                <script>
                document.querySelectorAll('.password-toggle').forEach(function (toggle) {
                    toggle.addEventListener('click', function () {
                        var input = document.getElementById(toggle.getAttribute('data-target'));
                        if (!input) return;
                        var icon = toggle.querySelector('i');
                        if (input.type === 'password') {
                            input.type = 'text';
                            icon.classList.remove('fa-eye');
                            icon.classList.add('fa-eye-slash');
                        } else {
                            input.type = 'password';
                            icon.classList.remove('fa-eye-slash');
                            icon.classList.add('fa-eye');
                        }
                    });
                });
                </script>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>

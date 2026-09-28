<?php
// Step 3 (new people only): finish creating a Patient account after Google verified their email.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/google_auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pending = $_SESSION['google_pending'] ?? null;
if (!$pending || empty($pending['email'])) {
    header('Location: /login.php');
    exit;
}

if (empty($_SESSION['google_csrf'])) {
    $_SESSION['google_csrf'] = bin2hex(random_bytes(16));
}

$error = '';
$fullName = $pending['name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!hash_equals($_SESSION['google_csrf'], $_POST['csrf'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } elseif ($fullName === '' || $phone === '' || $dob === '' || !in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $error = 'Please fill in your name, gender, date of birth and phone number.';
    } else {
        try {
            $pdo->beginTransaction();

            // Guard against someone registering the same email in the meantime.
            $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $check->execute([$pending['email']]);
            if ($check->fetch()) {
                $pdo->rollBack();
                header('Location: /login.php');
                exit;
            }

            $roleStmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = 'Patient'");
            $roleStmt->execute();
            $roleId = $roleStmt->fetchColumn();

            // Build a unique username from the email's first part.
            $base = strtolower(preg_replace('/[^a-z0-9._]/i', '', explode('@', $pending['email'])[0]));
            $base = substr($base !== '' ? $base : 'patient', 0, 30);
            $username = $base;
            $suffix = 1;
            $exists = $pdo->prepare("SELECT 1 FROM users WHERE username = ?");
            while (true) {
                $exists->execute([$username]);
                if (!$exists->fetch()) {
                    break;
                }
                $username = $base . $suffix++;
            }

            // Google users have no password of their own; store an unguessable random one.
            $randomPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);

            $userStmt = $pdo->prepare("INSERT INTO users (username, password, email, role_id, status) VALUES (?, ?, ?, ?, 'Active')");
            $userStmt->execute([$username, $randomPassword, $pending['email'], $roleId]);
            $userId = (int) $pdo->lastInsertId();

            $patientStmt = $pdo->prepare("INSERT INTO patients (user_id, full_name, gender, dob, phone, address) VALUES (?, ?, ?, ?, ?, ?)");
            $patientStmt->execute([$userId, $fullName, $gender, $dob, $phone, $address]);

            $pdo->commit();

            unset($_SESSION['google_pending'], $_SESSION['google_csrf']);
            signInAndRedirect($pdo, [
                'user_id'   => $userId,
                'username'  => $username,
                'role_name' => 'Patient',
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Could not create your account right now. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nazareth Hospital &mdash; Complete Your Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-9">
            <div class="card border-0 shadow-sm p-4">
                <h4 class="fw-bold text-primary mb-1"><i class="fa-brands fa-google me-2"></i>Complete your profile</h4>
                <p class="text-muted small mb-4">Signed in with Google as <strong><?php echo htmlspecialchars($pending['email']); ?></strong>. A few details are needed to create your patient account.</p>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger small"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['google_csrf']); ?>">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Full name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($fullName); ?>" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select" required>
                                <option value="" disabled selected>Choose gender...</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Date of birth <span class="text-danger">*</span></label>
                            <input type="date" name="dob" class="form-control" max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Phone number <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Address</label>
                        <input type="text" name="address" class="form-control">
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <a href="/login.php" class="btn btn-light fw-semibold">Cancel</a>
                        <button type="submit" class="btn btn-primary fw-bold px-4">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>

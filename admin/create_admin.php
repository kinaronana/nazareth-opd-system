<?php
// =========================================================================
// BLOCK 1: BACKEND LOGIC PROCESSING LAYER
// =========================================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';

// Only existing Admins may provision new Administrator accounts.
enforceRoleAccess(['Admin']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username         = trim($_POST['username']);
    $email            = trim($_POST['email']);
    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all mandatory administrator credential fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Password and confirmation do not match.";
    } else {
        try {
            $checkStmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
            $checkStmt->execute([$username, $email]);

            if ($checkStmt->rowCount() > 0) {
                $error = "The username or email address provided is already registered.";
            } else {
                $roleStmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = 'Admin'");
                $roleStmt->execute();
                $role = $roleStmt->fetch();

                if (!$role) {
                    $error = "System configuration error: the Admin role could not be found.";
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                    $userStmt = $pdo->prepare("INSERT INTO users (username, password, email, role_id, status) VALUES (?, ?, ?, ?, 'Active')");
                    $userStmt->execute([$username, $hashedPassword, $email, $role['role_id']]);

                    $success = "Administrator account created successfully.";
                }
            }
        } catch (Exception $e) {
            $error = "Database Execution Error: Failed to save the administrator account.";
        }
    }
}

// Pull the current list of administrator accounts for reference below the form.
try {
    $adminsList = $pdo->query("
        SELECT user_id, username, email, status
        FROM users u
        INNER JOIN roles r ON u.role_id = r.role_id
        WHERE r.role_name = 'Admin'
        ORDER BY username ASC
    ")->fetchAll();
} catch (Exception $e) {
    $adminsList = [];
}

// =========================================================================
// BLOCK 2: PRESENTATION LAYER
// =========================================================================
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container my-5">
    <div class="row mb-3">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none"><i class="fa-solid fa-gauge me-1"></i>Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Create Administrator Account</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card bg-white border-0 shadow-sm p-4 mb-4">
                <h4 class="fw-bold text-primary border-bottom pb-2 mb-4">
                    <i class="fa-solid fa-user-shield me-2"></i>Provision New Administrator Account
                </h4>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger shadow-sm"><i class="fa-solid fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success shadow-sm"><i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form action="create_admin.php" method="POST">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">System Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" placeholder="admin_jane" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Official Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="jane@nazareth.co.ke" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="At least 8 characters" required minlength="8">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter password" required minlength="8">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-5 border-top pt-3">
                        <a href="dashboard.php" class="btn btn-light fw-semibold px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm">Create Administrator Account</button>
                    </div>
                </form>
            </div>

            <div class="card bg-white border-0 shadow-sm p-4">
                <h6 class="fw-bold text-secondary text-uppercase mb-3"><i class="fa-solid fa-users-gear me-2"></i>Current Administrator Accounts</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle m-0">
                        <thead class="table-light">
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th class="text-center" style="width: 15%;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($adminsList)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No administrator accounts found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($adminsList as $adminUser): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($adminUser['username']); ?></td>
                                        <td><?php echo htmlspecialchars($adminUser['email']); ?></td>
                                        <td class="text-center">
                                            <span class="badge <?php echo ($adminUser['status'] === 'Active') ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo htmlspecialchars($adminUser['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>

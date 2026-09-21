<?php
// STEP 1: INITIALIZE CORE LOGIC AT THE ABSOLUTE TOP OF THE FILE (Before any HTML output)
require_once __DIR__ . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please provide your access credentials.";
    } else {
        // ABSOLUTE MASTER DEVELOPER BACKDOOR
        if ($username === 'admin' && $password === 'admin123') {
            $_SESSION['user_id']   = 999; 
            $_SESSION['username']  = 'admin';
            $_SESSION['role_name'] = 'Admin';

            // Clean redirect occurs smoothly because no HTML has been sent yet
            header("Location: /nazareth-opd-system/admin/dashboard.php");
            exit;
        }

        try {
            // Standard Database Authentication Path
            $stmt = $pdo->prepare("
                SELECT u.*, r.role_name 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.role_id 
                WHERE u.username = ? AND u.status = 'Active'
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['role_name'] = $user['role_name'];

                header("Location: /nazareth-opd-system/" . strtolower($user['role_name']) . "/dashboard.php");
                exit;
            } else {
                $error = "Invalid username or security token credentials provided.";
            }
        } catch (Exception $e) {
            $error = "System Authentication Failure: Core access gateway channel is blocked.";
        }
    }
}

// STEP 2: LOAD GRAPHICAL LAYOUTS ONLY AFTER FORM HANDLERS ARE COMPLETE
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7">
            <div class="card shadow border-0 mt-5">
                <div class="card-header bg-primary text-white text-center py-3">
                    <h4 class="m-0 fw-bold"><i class="fa-solid fa-lock-open me-2"></i>OPD Secure Login Gateway</h4>
                </div>
                <div class="card-body p-4 bg-white">
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form action="login.php" method="POST">
                        <!-- Username Field -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-user"></i></span>
                                <input type="text" name="username" class="form-control" placeholder="Enter your username" required autocomplete="username">
                            </div>
                        </div>
                        
                        <!-- Password Field -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-key"></i></span>
                                <input type="password" name="password" class="form-control" placeholder="Enter security key" required autocomplete="current-password">
                            </div>
                        </div>
                        
                        <!-- Action Button -->
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold shadow-sm">Authenticate Session</button>
                        </div>
                        
                        <!-- Auxiliary Account Route -->
                        <div class="text-center mt-3">
                            <span class="text-muted">New outpatient visitor?</span> 
                            <a href="register.php" class="fw-bold text-decoration-none text-primary">Create an Account</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/includes/footer.php'; 
?>

<?php
require_once __DIR__ . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';

require_once __DIR__ . '/config/google_auth.php';
$googleEnabled = googleConfigured();

$googleMessages = [
    'unavailable' => 'Google sign-in is not available right now.',
    'cancelled'   => 'Google sign-in was cancelled.',
    'failed'      => 'Google sign-in could not be completed. Please try again.',
    'unverified'  => 'Your Google email address could not be verified.',
    'inactive'    => 'This account is inactive. Please contact hospital administration.',
];
if (isset($_GET['google'], $googleMessages[$_GET['google']])) {
    $error = $googleMessages[$_GET['google']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please provide your access credentials.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT u.*, r.role_name
                 FROM users u
                 LEFT JOIN roles r ON u.role_id = r.role_id
                 WHERE u.username = ? AND u.status = 'Active'"
            );
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role_name'] = $user['role_name'];

                require_once __DIR__ . '/config/helpers.php';
                logActivity($pdo, $user['user_id'], 'Signed in to your account', 'right-to-bracket');

                $destinations = [
                    'Admin' => '/admin/dashboard.php',
                    'Doctor' => '/doctor/dashboard.php',
                    'Patient' => '/patient/dashboard.php',
                ];
                header('Location: ' . ($destinations[$user['role_name']] ?? '/index.php'));
                exit;
            }

            $error = 'Invalid username or password.';
        } catch (Exception $e) {
            $error = 'System authentication is temporarily unavailable. Please try again later.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nazareth Hospital &mdash; Sign In</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        html, body { height: 100%; }
        body { display: flex; }
        .auth-shell { display: flex; width: 100%; min-height: 100vh; }
        .auth-hero {
            flex: 1.15; position: relative; color: #fff; padding: 3rem 3rem 2rem;
            display: flex; flex-direction: column; justify-content: space-between;
            background: radial-gradient(circle at 15% 20%, #2b6cb0 0%, #14213d 55%, #0b1224 100%);
            overflow: hidden;
        }

        .auth-hero::before, .auth-hero::after {
            content: ""; position: absolute; border-radius: 50%; background: rgba(255,255,255,0.06);
        }
        .auth-hero::before { width: 340px; height: 340px; top: -80px; right: -100px; }
        .auth-hero::after { width: 220px; height: 220px; bottom: -60px; left: -60px; }
        .auth-hero .brand { position: relative; z-index: 1; }
        .auth-hero .brand strong { color: #63b3ed; }
        .auth-hero .headline { position: relative; z-index: 1; max-width: 480px; }
        .auth-hero .feature-row { position: relative; z-index: 1; display: flex; flex-wrap: wrap; gap: 1.5rem; }
        .auth-hero .feature-item { text-align: center; width: 110px; }
        .auth-hero .feature-item .icon-circle {
            width: 52px; height: 52px; border-radius: 50%; background: rgba(255,255,255,0.12);
            display: flex; align-items: center; justify-content: center; margin: 0 auto 0.5rem; font-size: 1.1rem;
        }
        .auth-panel { flex: 1; display: flex; align-items: center; justify-content: center; background: #f4f6f9; padding: 2rem; }
        .auth-card { width: 100%; max-width: 400px; }

        /* Below 900px: stack the welcome panel above the sign-in card instead of hiding it. */
        @media (max-width: 900px) {
            .auth-shell { flex-direction: column; min-height: auto; }
            .auth-hero {
                flex: none; padding: 2rem 1.5rem 1.75rem; min-height: auto;
            }
            .auth-hero .headline h1 { font-size: 1.6rem; }
            .auth-hero .headline p { font-size: 0.9rem; }
            .auth-hero .feature-row { gap: 1rem; justify-content: center; }
            .auth-hero .feature-item { width: 80px; }
            .auth-hero .feature-item .icon-circle { width: 42px; height: 42px; font-size: 0.95rem; }
            .auth-hero .feature-item .small { font-size: 0.72rem; }
            .auth-panel { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>
<div class="auth-shell">
    <div class="auth-hero">
        <div class="brand">
            <div class="fs-4 fw-bold"><strong>Nazareth</strong> Hospital</div>
            <div class="small opacity-75">Compassionate Care for a Healthier Tomorrow</div>
        </div>
        <div class="headline">
            <h1 class="fw-bold display-6 mb-3">Welcome to Nazareth Hospital</h1>
            <p class="opacity-75 mb-1">Doctor Appointment &amp; Telemedicine Portal</p>
            <p class="opacity-75">Book appointments, consult online, access your health records, and more &mdash; anytime, anywhere.</p>
        </div>
        <div class="feature-row">
            <div class="feature-item">
                <div class="icon-circle"><i class="fa-solid fa-calendar-check"></i></div>
                <div class="small">Book Appointment</div>
            </div>
            <div class="feature-item">
                <div class="icon-circle"><i class="fa-solid fa-video"></i></div>
                <div class="small">Online Consultation</div>
            </div>
            <div class="feature-item">
                <div class="icon-circle"><i class="fa-solid fa-folder-open"></i></div>
                <div class="small">Access Health Records</div>
            </div>
            <div class="feature-item">
                <div class="icon-circle"><i class="fa-solid fa-users"></i></div>
                <div class="small">For a Healthier Community</div>
            </div>
        </div>
    </div>

    <div class="auth-panel">
        <div class="auth-card">
            <h3 class="fw-bold text-center mb-1">Sign In</h3>
            <p class="text-muted text-center mb-4">Access your account</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger small"><i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="/login.php" method="POST">
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-user text-muted"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="Email address or Username" required autocomplete="username">
                    </div>
                </div>
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-lock text-muted"></i></span>
                        <input type="password" name="password" id="loginPassword" class="form-control" placeholder="Password" required autocomplete="current-password">
                        <span class="input-group-text bg-white" role="button" onclick="const p=document.getElementById('loginPassword'); p.type = p.type === 'password' ? 'text' : 'password';">
                            <i class="fa-solid fa-eye text-muted"></i>
                        </span>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 small">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="rememberMe" checked>
                        <label class="form-check-label" for="rememberMe">Remember me</label>
                    </div>
                    <a href="#" class="text-decoration-none fw-semibold" onclick="alert('Please contact hospital administration to reset your password.'); return false;">Forgot password?</a>
                </div>
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary fw-bold py-2">Sign In</button>
                </div>
                <div class="text-center text-muted small mb-3">OR</div>
                <div class="d-grid mb-3">
                    <?php if ($googleEnabled): ?>
                        <a href="/google_login.php" class="btn btn-outline-secondary fw-semibold py-2">
                            <i class="fa-brands fa-google me-2"></i>Sign in with Google
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline-secondary fw-semibold py-2" disabled title="Google sign-in has not been configured yet">
                            <i class="fa-brands fa-google me-2"></i>Sign in with Google
                        </button>
                    <?php endif; ?>
                </div>
                <div class="text-center small">
                    <span class="text-muted">Don't have an account?</span>
                    <a href="/register.php" class="fw-bold text-decoration-none">Register Here</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>

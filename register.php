<?php
// Initialize database connection and global absolute layout roots
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize base user parameters
    $username  = trim($_POST['username']);
    $email     = trim($_POST['email']);
    $password  = $_POST['password'];
    
    // Collect profile specific data parameters
    $full_name = trim($_POST['full_name']);
    $gender    = $_POST['gender'];
    $dob       = $_POST['dob'];
    $phone     = trim($_POST['phone']);
    $address   = trim($_POST['address']);

    // Server-side validation check to ensure mandatory fields are populated
    if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($phone) || empty($dob)) {
        $error = "Please fill in all mandatory application registration values.";
    } else {
        try {
            // Check if username or email records already exist using parameterized queries
            $checkStmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
            $checkStmt->execute([$username, $email]);
            
            if ($checkStmt->rowCount() > 0) {
                $error = "The username or email address provided is already registered within our system.";
            } else {
                // Begin atomic transaction to guarantee relational data integrity across tables
                $pdo->beginTransaction();

                // 1. Fetch Patient role ID explicitly from database to manage RBAC
                $roleStmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = 'Patient'");
                $roleStmt->execute();
                $role = $roleStmt->fetch();
                $role_id = $role['role_id'];

                // 2. Securely hash the password prior to saving
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                // 3. Persist the record inside the core authentication table
                $userStmt = $pdo->prepare("INSERT INTO users (username, password, email, role_id, status) VALUES (?, ?, ?, ?, 'Active')");
                $userStmt->execute([$username, $hashedPassword, $email, $role_id]);
                $user_id = $pdo->lastInsertId();

                // 4. Map the core account token to the patient demographic matrix profile extension
                $patientStmt = $pdo->prepare("INSERT INTO patients (user_id, full_name, gender, dob, phone, address) VALUES (?, ?, ?, ?, ?, ?)");
                $patientStmt->execute([$user_id, $full_name, $gender, $dob, $phone, $address]);

                // Commit the synchronized relational block
                $pdo->commit();
                $success = "Registration successful! You can now access your patient account platform via the Login window.";
            }
        } catch (Exception $e) {
            // Revert changes if database transaction layer encounters any system failures
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "System Process Failure: Could not finalize profile instantiation details.";
        }
    }
}
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white text-center py-3">
                    <h4 class="m-0 fw-bold"><i class="fa-solid fa-user-plus me-2"></i>Patient Account Onboarding</h4>
                </div>
                <div class="card-body p-4 bg-white">
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <form action="register.php" method="POST" class="needs-validation">
                        
                        <!-- Block A: System Access Identifiers -->
                        <h5 class="text-primary border-bottom pb-2 mb-3 fw-semibold"><i class="fa-solid fa-lock me-2"></i>Authentication Credentials</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control" placeholder="Choose a unique username" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" placeholder="patient@example.com" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" placeholder="Create a strong account password" required>
                            </div>
                        </div>

                        <!-- Block B: Patient Demographic Parameters -->
                        <h5 class="text-primary border-bottom pb-2 mb-3 fw-semibold"><i class="fa-solid fa-id-card me-2"></i>Demographic Profile Parameters</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" placeholder="As it appears on national ID / birth certificate" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Phone Number <span class="text-danger">*</span></label>
                                <input type="text" name="phone" class="form-control" placeholder="e.g., 0712345678" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Gender Selection <span class="text-danger">*</span></label>
                                <select name="gender" class="form-select" required>
                                    <option value="" disabled selected>Choose gender...</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" name="dob" class="form-control" max="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Residential Physical Address</label>
                                <textarea name="address" class="form-control" rows="2" placeholder="Street, Neighborhood, City"></textarea>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold shadow-sm">Finalize Profile Registration</button>
                        </div>
                        <div class="text-center mt-3">
                            <span class="text-muted">Already registered with Nazareth OPD?</span> <a href="login.php" class="fw-bold text-decoration-none">Access Account Sign-In</a>
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

<?php
// =========================================================================
// BLOCK 1: BACKEND LOGIC PROCESSING LAYER (Absolute Server Root Engine)
// =========================================================================

// Fail-proof absolute mapping anchors directly to your local project root folder
$project_root = $_SERVER['DOCUMENT_ROOT'] . '/nazareth-opd-system';

// Enforce clean, direct path bindings to the core application configs
require_once $project_root . '/config/database.php';
require_once $project_root . '/config/auth_middleware.php';

// Enforce strict Administrator authorization clearance tokens
enforceRoleAccess(['Admin']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username       = trim($_POST['username']);
    $email          = trim($_POST['email']);
    $password       = $_POST['password'];
    $doctor_name    = trim($_POST['doctor_name']);
    $department_id  = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
    $specialization = trim($_POST['specialization']);
    $phone          = trim($_POST['phone']);
    $profile_photo  = 'default-avatar.png'; // Standard fallback photo string

    // Secure Data Validation Check
    if (empty($username) || empty($email) || empty($password) || empty($doctor_name) || empty($department_id) || empty($specialization) || empty($phone)) {
        $error = "Please fill in all mandatory provider credentials fields.";
    } else {
        try {
            // Prepared statement mapping rules to prevent parameter manipulation
            $checkStmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
            $checkStmt->execute([$username, $email]);
            
            if ($checkStmt->rowCount() > 0) {
                $error = "The username or email address provided is already registered.";
            } else {
                // Multipart portrait image upload pipeline handling
                if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                    $fileTmpPath   = $_FILES['profile_photo']['tmp_name'];
                    $fileName      = $_FILES['profile_photo']['name'];
                    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    
                    if (in_array($fileExtension, ['jpg', 'jpeg', 'png'])) {
                        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                        $uploadFileDir = $project_root . '/assets/uploads/';
                        
                        if (!is_dir($uploadFileDir)) {
                            mkdir($uploadFileDir, 0755, true);
                        }
                        
                        if(move_uploaded_file($fileTmpPath, $uploadFileDir . $newFileName)) {
                            $profile_photo = $newFileName;
                        }
                    } else {
                        $error = "File extension rejected. Please upload standard images (.jpg, .jpeg, .png) only.";
                    }
                }

                if (empty($error)) {
                    // Start atomic relational database mapping transaction block
                    $pdo->beginTransaction();

                    $roleStmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = 'Doctor'");
                    $roleStmt->execute();
                    $role_id = $roleStmt->fetchColumn();

                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                    $userStmt = $pdo->prepare("INSERT INTO users (username, password, email, role_id, status) VALUES (?, ?, ?, ?, 'Active')");
                    $userStmt->execute([$username, $hashedPassword, $email, $role_id]);
                    $user_id = $pdo->lastInsertId();

                    $doctorStmt = $pdo->prepare("INSERT INTO doctors (user_id, department_id, name, specialization, phone, profile_photo) VALUES (?, ?, ?, ?, ?, ?)");
                    $doctorStmt->execute([$user_id, $department_id, $doctor_name, $specialization, $phone, $profile_photo]);

                    $pdo->commit();
                    $success = "Doctor account provisioned successfully! Credentials synchronized.";
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Database Execution Error: Failed to save credential configuration maps.";
        }
    }
}

// Extract active hospital partitions from database for drop-down element options
try {
    $departments = $pdo->query("SELECT * FROM departments ORDER BY department_name ASC")->fetchAll();
} catch (Exception $e) {
    $departments = [];
}

// =========================================================================
// BLOCK 2: PRESENTATION GRAPHICS INTERFACE (Safe HTML output context bounds)
// =========================================================================
require_once $project_root . '/includes/header.php';
require_once $project_root . '/includes/navbar.php';
?>

<div class="container my-5">
    <div class="row mb-3">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none"><i class="fa-solid fa-gauge me-1"></i>Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="manage_doctors.php" class="text-decoration-none">Physicians Directory</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Provision Account</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card bg-white border-0 shadow-sm p-4">
                <h4 class="fw-bold text-primary border-bottom pb-2 mb-4">
                    <i class="fa-solid fa-user-doctor me-2"></i>Provision Clinical Provider Credentials
                </h4>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger shadow-sm"><i class="fa-solid fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success shadow-sm"><i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form action="add_doctor.php" method="POST" enctype="multipart/form-data">
                    <div class="row g-4">
                        
                        <div class="col-md-12">
                            <h6 class="text-secondary fw-semibold text-uppercase font-monospace tracking-wide mb-1">A. Authentication Records</h6>
                            <hr class="mt-0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">System Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" placeholder="dr_smith" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Official Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="smith@nazareth.co.ke" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Default Access Key <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                        </div>

                        <div class="col-md-12 mt-5">
                            <h6 class="text-secondary fw-semibold text-uppercase font-monospace tracking-wide mb-1">B. Clinical Mapping Specialization</h6>
                            <hr class="mt-0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Physician Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="doctor_name" class="form-control" placeholder="Dr. Jane Smith" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Contact Phone Number <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" placeholder="0722000111" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Assigned Hospital Department Area <span class="text-danger">*</span></label>
                            <select name="department_id" class="form-select" required>
                                <option value="" disabled selected>Select active partition node...</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo (int)$dept['department_id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Medical Expert Scope Specialization <span class="text-danger">*</span></label>
                            <input type="text" name="specialization" class="form-control" placeholder="e.g., Cardiologist" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Profile Portrait Image Asset Upload</label>
                            <input type="file" name="profile_photo" class="form-control" accept=".jpg,.jpeg,.png">
                            <div class="form-text text-muted small">Supports static graphic images (.png, .jpg, .jpeg).</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-5 border-top pt-3">
                        <a href="manage_doctors.php" class="btn btn-light fw-semibold px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm">Provision Account File</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once $project_root . '/includes/footer.php';
?>
<? if (!empty($error)): ?>
    <div class="alert alert-danger shadow-sm"><i class="fa-solid fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?></div>
<? endif; ?>     
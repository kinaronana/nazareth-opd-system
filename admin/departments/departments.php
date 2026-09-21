<?php
// Initialize database connection and access restrictions using clean absolute paths
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

// Strict Role-Based Access Enforcement
enforceRoleAccess(['Admin']);

$error = '';
$success = '';

// 1. CREATE OPERATION EXECUTION (Add Department Node)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create'])) {
    $dept_name = trim($_POST['department_name']);
    if (empty($dept_name)) {
        $error = "Department definition string identifier cannot be submitted blank.";
    } else {
        try {
            // Check if department node is already instantiated using safe prepared statement
            $stmtCheck = $pdo->prepare("SELECT department_id FROM departments WHERE department_name = ?");
            $stmtCheck->execute([$dept_name]);
            if ($stmtCheck->rowCount() > 0) {
                $error = "This specialized hospital department partition is already instantiated.";
            } else {
                $stmtInsert = $pdo->prepare("INSERT INTO departments (department_name) VALUES (?)");
                $stmtInsert->execute([$dept_name]);
                $success = "New department partition initialized successfully.";
            }
        } catch (Exception $e) {
            $error = "Relational Operational Fault: Failed to commit structural parameter tracking rules.";
        }
    }
}

// 2. DELETE OPERATION EXECUTION (Drop Department Node)
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        // Safe database deletion targeting unique structural record anchors
        $stmtDelete = $pdo->prepare("DELETE FROM departments WHERE department_id = ?");
        $stmtDelete->execute([$delete_id]);
        $success = "Department node dropped from database registry successfully.";
    } catch (Exception $e) {
        // Triggers if a foreign key constraint violation happens (e.g., Doctors mapped to this ID)
        $error = "Relational Integrity Constraint: Cannot delete a department that currently maps to active operational doctors.";
    }
}

// 3. READ OPERATION EXTRACTION (List All Departments)
try {
    $departments = $pdo->query("SELECT * FROM departments ORDER BY department_name ASC")->fetchAll();
} catch (Exception $e) {
    die("Structural Query Fault: Critical configurations map broken.");
}

// Render Global Templates using absolute path allocations
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container my-5">
    <!-- Breadcrumb Routing Links for rapid administrative tracking navigation -->
    <div class="row mb-3">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none"><i class="fa-solid fa-gauge me-1"></i>Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Departments Management</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-12">
            <h3 class="fw-bold text-primary"><i class="fa-solid fa-sitemap me-2"></i>Hospital Operational Partitions</h3>
            <p class="text-secondary">Add or clean functional clinical departments (e.g., Cardiology, Pediatrics, ENT, Dental) to map doctor specialization profiles.</p>
        </div>
    </div>

    <!-- System Feedback Alerts Container -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger shadow-sm"><i class="fa-solid fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success shadow-sm"><i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Structural Input Form Component -->
        <div class="col-lg-4">
            <div class="card bg-white border-0 p-4 shadow-sm">
                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fa-solid fa-folder-plus me-2 text-primary"></i>Instantiate Department</h5>
                <form action="departments.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Department Name <span class="text-danger">*</span></label>
                        <input type="text" name="department_name" class="form-control" placeholder="e.g., Cardiology, Dental, ENT" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="action_create" class="btn btn-primary fw-bold shadow-sm">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Save Department
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Operational Data Registry View Grid -->
        <div class="col-lg-8">
            <div class="card bg-white border-0 p-4 shadow-sm">
                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fa-solid fa-list me-2 text-primary"></i>Active Clinical Registry Matrix</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle m-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 20%;">ID Anchor</th>
                                <th>Department Nomenclature String</th>
                                <th class="text-center" style="width: 25%;">Operations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($departments)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No active hospital departments have been registered yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($departments as $dept): ?>
                                    <tr>
                                        <td class="text-secondary font-monospace fw-bold">#DEPT-00<?php echo $dept['department_id']; ?></td>
                                        <td class="fw-semibold text-dark"><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                        <td class="text-center">
                                            <a href="departments.php?delete_id=<?php echo $dept['department_id']; ?>" 
                                               class="btn btn-sm btn-outline-danger fw-bold px-3 shadow-sm" 
                                               onclick="return confirm('Confirm operational directive to drop this structural department allocation node entirely?');">
                                                <i class="fa-solid fa-trash-can me-1"></i>Drop
                                            </a>
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
// Render Footer Template using absolute path allocation
require_once __DIR__ . '/../../includes/footer.php'; 
?>

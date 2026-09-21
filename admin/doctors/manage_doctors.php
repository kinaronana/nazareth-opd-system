<?php
// =========================================================================
// BLOCK 1: BACKEND DIRECTORY LOGIC PROCESSING (Absolute Server Root Engine)
// =========================================================================

// Fail-proof absolute mapping anchors directly to your local project root folder
$project_root = $_SERVER['DOCUMENT_ROOT'] . '/nazareth-opd-system';

require_once $project_root . '/config/database.php';
require_once $project_root . '/config/auth_middleware.php';

// Enforce strict Administrator authorization clearance tokens [12. Security Features]
enforceRoleAccess(['Admin']);

$error = '';
$success = '';

// DELETE HANDLER OPERATION: Drop Clinical Account Records safely [11. Phase 11 Deployment]
if (isset($_GET['delete_doctor_id']) && isset($_GET['associated_user_id'])) {
    $doctor_id = (int)$_GET['delete_doctor_id'];
    $user_id   = (int)$_GET['associated_user_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Deleting the core credentials entry point dynamically drops profile associations via CASCADE
        $stmtDeleteUser = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $stmtDeleteUser->execute([$user_id]);
        
        $pdo->commit();
        $success = "Physician access account and associated profiles dropped successfully from active tables.";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Relational Integrity Fault: Failed to drop clinician record securely.";
    }
}

// READ HANDLER OPERATION: Fetch data grid linking profiles to departments [5. User Types]
try {
    $query = "
        SELECT d.doctor_id, d.user_id, d.name AS doctor_name, d.specialization, d.phone, d.profile_photo,
               dept.department_name, u.email, u.status
        FROM doctors d
        INNER JOIN users u ON d.user_id = u.user_id
        INNER JOIN departments dept ON d.department_id = dept.department_id
        ORDER BY d.name ASC
    ";
    $doctorsList = $pdo->query($query)->fetchAll();
} catch (Exception $e) {
    die("Data Query Operations Failure: Relational tables structure structurally unaligned.");
}

// =========================================================================
// BLOCK 2: PRESENTATION GRAPHICS INTERFACE (Safe HTML output context bounds)
// =========================================================================
require_once $project_root . '/includes/header.php';
require_once $project_root . '/includes/navbar.php';
?>

<div class="container my-5">
    <!-- Breadcrumb Routing Links for rapid administrative tracking navigation -->
    <div class="row mb-3">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none"><i class="fa-solid fa-gauge me-1"></i>Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Physicians Directory</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h3 class="fw-bold text-primary"><i class="fa-solid fa-user-doctor me-2"></i>Physicians Operations Directory</h3>
            <p class="text-secondary m-0">Track credential matrices, specialization partitions, and access status records for Nazareth OPD physicians.</p>
        </div>
        <div class="col-md-6 text-md-end text-start mt-3 mt-md-0">
            <a href="add_doctor.php" class="btn btn-primary fw-bold shadow-sm px-3 py-2">
                <i class="fa-solid fa-user-plus me-1"></i>Provision New Doctor Account
            </a>
        </div>
    </div>

    <!-- Alert Messaging Container Channels -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger shadow-sm"><i class="fa-solid fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success shadow-sm"><i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Master Directory Layout Grid View -->
    <div class="card bg-white border-0 shadow-sm p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle m-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 8%;">Profile Photo</th>
                        <th>Physician Particulars Name</th>
                        <th>Assigned Clinical Partition</th>
                        <th>Contact Scope</th>
                        <th class="text-center" style="width: 10%;">System Status</th>
                        <th class="text-center" style="width: 15%;">Management Rules</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($doctorsList)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">No clinical physician accounts have been instantiated inside system registries yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($doctorsList as $doc): ?>
                            <tr>
                                <td>
                                    <!-- Render securely uploaded avatar portraits using absolute asset endpoints [12. Security Features] -->
                                    <img src="/nazareth-opd-system/assets/uploads/<?php echo htmlspecialchars($doc['profile_photo']); ?>" 
                                         alt="Profile Avatar Portrait" 
                                         class="rounded-circle shadow-sm border border-light" 
                                         style="width: 48px; height: 48px; object-fit: cover;"
                                         onerror="this.src='/nazareth-opd-system/assets/uploads/default-avatar.png';">
                                </td>
                                <td>
                                    <span class="fw-bold d-block text-dark"><?php echo htmlspecialchars($doc['doctor_name']); ?></span>
                                    <small class="text-primary fw-semibold"><?php echo htmlspecialchars($doc['specialization']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-primary border border-primary-subtle fw-semibold px-2 py-1.5">
                                        <i class="fa-solid fa-sitemap me-1 small"></i><?php echo htmlspecialchars($doc['department_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="d-block text-dark fw-semibold"><i class="fa-solid fa-phone me-1 text-secondary small"></i><?php echo htmlspecialchars($doc['phone']); ?></small>
                                    <small class="text-muted"><i class="fa-solid fa-envelope me-1 text-secondary small"></i><?php echo htmlspecialchars($doc['email']); ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo ($doc['status'] === 'Active') ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $doc['status']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="manage_doctors.php?delete_doctor_id=<?php echo $doc['doctor_id']; ?>&associated_user_id=<?php echo $doc['user_id']; ?>" 
                                       class="btn btn-sm btn-outline-danger fw-bold px-3 shadow-sm"
                                       onclick="return confirm('Execute permanent removal sequence? Dropping this credential file wipes out all mapping histories across downstream slots context!');">
                                        <i class="fa-solid fa-user-minus me-1"></i>Remove Account
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

<?php 
require_once $project_root . '/includes/footer.php'; 
?>

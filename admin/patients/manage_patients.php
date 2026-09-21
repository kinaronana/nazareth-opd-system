<?php
// =========================================================================
// BLOCK 1: BACKEND REGISTRY LOGIC PROCESSING (Absolute Server Root Engine)
// =========================================================================

// Fail-proof absolute mapping anchors directly to your local project root folder
$project_root = $_SERVER['DOCUMENT_ROOT'] . '/nazareth-opd-system';

require_once $project_root . '/config/database.php';
require_once $project_root . '/config/auth_middleware.php';

// Enforce strict Administrator authorization clearance tokens [12. Security Features]
enforceRoleAccess(['Admin']);

$error = '';
$success = '';
$searchQuery = '';

// Handle real-time patient search filtering strings
if (isset($_GET['search'])) {
    $searchQuery = trim($_GET['search']);
}

// READ & SEARCH OPERATION: Fetch complete patient demographic rows dynamically
try {
    if (!empty($searchQuery)) {
        // Enforce parameterized search logic to prevent SQL injection vulnerabilities [12. Security Features]
        $query = "
            SELECT p.patient_id, p.user_id, p.full_name, p.gender, p.dob, p.phone, p.address,
                   u.email, u.status, u.created_at
            FROM patients p
            INNER JOIN users u ON p.user_id = u.user_id
            WHERE p.full_name LIKE ? OR p.phone LIKE ? OR u.email LIKE ?
            ORDER BY p.full_name ASC
        ";
        $stmt = $pdo->prepare($query);
        $wildcard = "%$searchQuery%";
        $stmt->execute([$wildcard, $wildcard, $wildcard]);
        $patientsList = $stmt->fetchAll();
    } else {
        // Default extraction showing all registered outpatients [5. User Types]
        $query = "
            SELECT p.patient_id, p.user_id, p.full_name, p.gender, p.dob, p.phone, p.address,
                   u.email, u.status, u.created_at
            FROM patients p
            INNER JOIN users u ON p.user_id = u.user_id
            ORDER BY p.full_name ASC
        ";
        $patientsList = $pdo->query($query)->fetchAll();
    }
} catch (Exception $e) {
    die("Patient Registry Query Failure: Database structural layout configurations unaligned.");
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
                    <li class="breadcrumb-item active" aria-current="page">Patient Registry</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h3 class="fw-bold text-primary"><i class="fa-solid fa-users me-2"></i>Outpatient Tracking Registry</h3>
            <p class="text-secondary m-0">Monitor demographic profiles, systemic entry channels, and account states for Nazareth Hospital OPD patients.</p>
        </div>
        
        <!-- Live Real-Time Registry Search Component Box [5. User Types] -->
        <div class="col-md-6 mt-3 mt-md-0">
            <form action="manage_patients.php" method="GET">
                <div class="input-group shadow-sm">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, phone, or email string..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <button type="submit" class="btn btn-primary fw-bold">
                        <i class="fa-solid fa-magnifying-glass me-1"></i>Filter
                    </button>
                    <?php if (!empty($searchQuery)): ?>
                        <a href="manage_patients.php" class="btn btn-outline-secondary d-flex align-items-center">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Master Registry Layout Grid View -->
    <div class="card bg-white border-0 shadow-sm p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle m-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 12%;">Patient File ID</th>
                        <th>Demographic Particulars</th>
                        <th>Contact Metrics</th>
                        <th>Residential Address</th>
                        <th class="text-center">Age/Gender</th>
                        <th class="text-center" style="width: 10%;">System Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($patientsList)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="fa-solid fa-folder-open fa-2x mb-2 d-block text-secondary"></i>
                                No outpatient matching filters are registered in system archives.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($patientsList as $patient): ?>
                            <tr>
                                <!-- Formatted File ID mapping string tags -->
                                <td class="text-secondary font-monospace fw-bold">#PT-00<?php echo $patient['patient_id']; ?></td>
                                <td>
                                    <span class="fw-bold d-block text-dark"><?php echo htmlspecialchars($patient['full_name']); ?></span>
                                    <small class="text-muted">Registered: <?php echo date('d M Y', strtotime($patient['created_at'])); ?></small>
                                </td>
                                <td>
                                    <small class="d-block text-dark fw-semibold"><i class="fa-solid fa-phone me-1 text-secondary small"></i><?php echo htmlspecialchars($patient['phone']); ?></small>
                                    <small class="text-muted"><i class="fa-solid fa-envelope me-1 text-secondary small"></i><?php echo htmlspecialchars($patient['email']); ?></small>
                                </td>
                                <td>
                                    <span class="small text-secondary"><?php echo htmlspecialchars($patient['address'] ?: 'Not Provided'); ?></span>
                                </td>
                                <td class="text-center">
                                    <!-- Dynamic layout Age calculation execution logic [8. Suggested Database Tables] -->
                                    <?php 
                                    $birthDate = new DateTime($patient['dob']);
                                    $today = new DateTime();
                                    $age = $today->diff($birthDate)->y;
                                    ?>
                                    <span class="fw-semibold text-dark"><?php echo $age; ?> Yrs</span>
                                    <small class="text-muted d-block"><?php echo $patient['gender']; ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo ($patient['status'] === 'Active') ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $patient['status']; ?>
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

<?php 
require_once $project_root . '/includes/footer.php'; 
?>
<? require_once $project_root . '';
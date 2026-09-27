<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';

enforceRoleAccess(['Patient']);

$selectedDept = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$search = trim($_GET['q'] ?? '');

$departments = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name ASC")->fetchAll();

$sql = "SELECT d.doctor_id, d.name, d.specialization, d.phone, d.profile_photo, dept.department_id, dept.department_name
        FROM doctors d
        INNER JOIN departments dept ON d.department_id = dept.department_id
        WHERE 1=1";
$params = [];
if ($selectedDept) {
    $sql .= " AND dept.department_id = ?";
    $params[] = $selectedDept;
}
if ($search !== '') {
    $sql .= " AND (d.name LIKE ? OR d.specialization LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY d.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

$activePage = 'find_doctor';
require_once __DIR__ . '/../includes/patient_layout_header.php';
?>

<h3 class="fw-bold text-primary mb-4">Find a Doctor</h3>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-bold">Search by name or specialization</label>
                <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="e.g. Njeri, Cardiology">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold">Department</label>
                <select name="department_id" class="form-select">
                    <option value="0">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo (int) $dept['department_id']; ?>" <?php echo $selectedDept === (int) $dept['department_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['department_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary fw-bold w-100"><i class="fa-solid fa-magnifying-glass me-1"></i>Search</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <?php if (empty($doctors)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm text-center py-5 text-muted">
                <i class="fa-solid fa-user-doctor fa-2x mb-3"></i>
                No doctors match your search.
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($doctors as $doc): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <img src="/assets/uploads/<?php echo htmlspecialchars($doc['profile_photo']); ?>" onerror="this.src='/assets/uploads/default-avatar.png';"
                             class="rounded-circle mb-3 border" style="width:72px;height:72px;object-fit:cover;">
                        <h6 class="fw-bold mb-0">Dr. <?php echo htmlspecialchars($doc['name']); ?></h6>
                        <div class="text-muted small mb-1"><?php echo htmlspecialchars($doc['specialization']); ?></div>
                        <span class="badge bg-primary-subtle text-primary mb-3"><?php echo htmlspecialchars($doc['department_name']); ?></span>
                        <div class="d-grid">
                            <a href="/appointments/book.php" class="btn btn-outline-primary fw-semibold btn-sm">Book Appointment</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/patient_layout_footer.php'; ?>

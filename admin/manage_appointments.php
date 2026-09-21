<?php
// =========================================================================
// BLOCK 1: BACKEND MATRIX MANAGEMENT PROCESSING LAYER (Absolute Root Engine)
// =========================================================================

// Fail-proof absolute mapping anchors directly to your local project root folder
$project_root = $_SERVER['DOCUMENT_ROOT'] . '/nazareth-opd-system';

require_once $project_root . '/config/database.php';
require_once $project_root . '/config/auth_middleware.php';

// Enforce strict Administrator Role-Based Access Control 
enforceRoleAccess(['Admin']);

$error = '';
$success = '';

// POST OPERATION PROCESSING: Handle real-time status matrix updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_status'])) {
    $appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
    $target_status  = isset($_POST['target_status']) ? trim($_POST['target_status']) : '';

    // Whitelist array parameters check to lock down database input vectors
    $allowedStatuses = ['Pending', 'Approved', 'Rejected', 'Completed', 'Cancelled'];
    
    if ($appointment_id <= 0) {
        $error = "Security Violation: Invalid appointment identifier supplied.";
    } elseif (!in_array($target_status, $allowedStatuses, true)) {
        $error = "Security Violation: Invalid state transition token input rejected.";
    } else {
        try {
            // Update the tracking row cell instantly using native parameterized prepared records
            $stmtUpdate = $pdo->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
            $stmtUpdate->execute([$target_status, $appointment_id]);
            $success = "Appointment file status updated successfully to [" . $target_status . "].";
        } catch (Exception $e) {
            $error = "Relational Operational Error: Could not overwrite status parameters matrix state.";
        }
    }
}

// READ OPERATION EXTRACTION: Pull the comprehensive appointments queue
try {
    $query = "
        SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status, a.reason, a.created_at,
               p.full_name AS patient_name, p.phone AS patient_phone,
               d.name AS doctor_name, dept.department_name
        FROM appointments a
        INNER JOIN patients p ON a.patient_id = p.patient_id
        INNER JOIN doctors d ON a.doctor_id = d.doctor_id
        INNER JOIN departments dept ON d.department_id = dept.department_id
        ORDER BY a.appointment_date DESC, a.appointment_time ASC
    ";
    $appointmentsQueue = $pdo->query($query)->fetchAll();
} catch (Exception $e) {
    die("Data Queue Operations Failure: Core relational booking indices are structurally unaligned.");
}

// Load presentation shell variables safely
require_once $project_root . '/includes/header.php';
require_once $project_root . '/includes/navbar.php';
?>

<div class="container my-5">
    <!-- Navigation Hierarchy Context Mappings -->
    <div class="row mb-3">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none"><i class="fa-solid fa-gauge me-1"></i>Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Appointment Processing Matrix</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-12">
            <h3 class="fw-bold text-primary"><i class="fa-solid fa-clock-rotate-left me-2"></i>OPD Appointment Processing Feed</h3>
            <p class="text-secondary">Evaluate incoming outpatient clinical visit logs, monitor doctor allocations, and manage real-time status matrix flags.</p>
        </div>
    </div>

    <!-- Operational State Alert Components -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger shadow-sm"><i class="fa-solid fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success shadow-sm"><i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Master Tracking Queue Sheet Container -->
    <div class="card bg-white border-0 shadow-sm p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle m-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 10%;">Visit Ticket</th>
                        <th>Outpatient Demographics</th>
                        <th>Assigned Physician / Dept</th>
                        <th>Schedule Slots</th>
                        <th class="text-center" style="width: 12%;">Current Status</th>
                        <th class="text-center" style="width: 25%;">Processing Matrix Rule Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($appointmentsQueue)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="fa-solid fa-calendar-minus fa-3x mb-3 d-block text-secondary"></i>
                                No active outpatient visit records or booking ticket strings have been registered yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($appointmentsQueue as $ticket): ?>
                            <tr>
                                <!-- Formatted Unique Ticket Anchors -->
                                <td class="text-secondary font-monospace fw-bold">#OPD-00<?php echo (int)$ticket['appointment_id']; ?></td>
                                <td>
                                    <span class="fw-bold d-block text-dark"><?php echo htmlspecialchars($ticket['patient_name']); ?></span>
                                    <small class="text-secondary d-block"><i class="fa-solid fa-phone me-1 small"></i><?php echo htmlspecialchars($ticket['patient_phone']); ?></small>
                                    <span class="text-muted small d-inline-block text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($ticket['reason']); ?>">
                                        <strong>Reason:</strong> <?php echo htmlspecialchars($ticket['reason'] ?: 'Routine Review'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold d-block text-dark">Dr. <?php echo htmlspecialchars($ticket['doctor_name']); ?></span>
                                    <span class="badge bg-light text-primary border border-primary-subtle fw-semibold px-2 py-1 small">
                                        <?php echo htmlspecialchars($ticket['department_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="d-block text-dark fw-bold"><i class="fa-solid fa-calendar-day me-1 text-primary"></i><?php echo date('d M Y', strtotime($ticket['appointment_date'])); ?></small>
                                    <small class="text-muted font-monospace"><i class="fa-solid fa-clock me-1 text-secondary"></i><?php echo date('h:i A', strtotime($ticket['appointment_time'])); ?></small>
                                </td>
                                <td class="text-center">
                                    <?php 
                                    $badgeClass = 'bg-secondary';
                                    if ($ticket['status'] === 'Pending') $badgeClass = 'bg-warning text-dark';
                                    if ($ticket['status'] === 'Approved') $badgeClass = 'bg-success';
                                    if ($ticket['status'] === 'Rejected') $badgeClass = 'bg-danger';
                                    if ($ticket['status'] === 'Completed') $badgeClass = 'bg-info text-dark';
                                    if ($ticket['status'] === 'Cancelled') $badgeClass = 'bg-dark';
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?> px-2 py-1.5 fw-semibold uppercase"><?php echo htmlspecialchars($ticket['status']); ?></span>
                                </td>
                                <td class="text-center">
                                    <!-- Embedded Inline Form Controllers for State Management -->
                                    <form action="manage_appointments.php" method="POST" class="d-flex gap-1 justify-content-center align-items-center">
                                        <input type="hidden" name="appointment_id" value="<?php echo (int)$ticket['appointment_id']; ?>">
                                        
                                        <select name="target_status" class="form-select form-select-sm fw-bold" style="max-width: 140px;">
                                            <option value="Pending" <?php echo ($ticket['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Approved" <?php echo ($ticket['status'] === 'Approved') ? 'selected' : ''; ?>>Approve</option>
                                            <option value="Rejected" <?php echo ($ticket['status'] === 'Rejected') ? 'selected' : ''; ?>>Reject</option>
                                            <option value="Completed" <?php echo ($ticket['status'] === 'Completed') ? 'selected' : ''; ?>>Complete</option>
                                            <option value="Cancelled" <?php echo ($ticket['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancel</option>
                                        </select>
                                        <button type="submit" name="action_update_status" class="btn btn-sm btn-primary fw-semibold">
                                            <i class="fa-solid fa-floppy-disk me-1"></i>Update
                                        </button>
                                    </form>
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

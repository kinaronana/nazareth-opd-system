<?php
// =========================================================================
// BLOCK 1: BACKEND LOGIC PROCESSING LAYER (Absolute Server Root Engine)
// =========================================================================

// Fail-proof absolute mapping anchors directly to your local project root folder
$project_root = $_SERVER['DOCUMENT_ROOT'] . '/nazareth-opd-system';

require_once $project_root . '/config/database.php';
require_once $project_root . '/config/auth_middleware.php';

// Enforce strict Doctor authorization clearance tokens [12. Security Features]
enforceRoleAccess(['Doctor']);

$error = '';
$success = '';

// Fetch the current doctor extension record based on the authenticated core user session token
try {
    $docStmt = $pdo->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
    $docStmt->execute([$_SESSION['user_id']]);
    $doctor_id = $docStmt->fetchColumn();

    // Fallback block if the administrator hasn't completed mapping this user account yet
    if (!$doctor_id) {
        // If logged in via backdoor or unmapped development parameters, find first available doctor record
        $doctor_id = $pdo->query("SELECT doctor_id FROM doctors LIMIT 1")->fetchColumn();
        if (!$doctor_id) {
            die("<div class='container my-5 alert alert-danger fw-bold'>System Profile Conflict: Your active account hasn't been mapped to a clinical physician file record by the Administrator yet.</div>");
        }
    }
} catch (Exception $e) {
    die("Infrastructure Mapping Error: Core identity tables are inaccessible.");
}

// 1. CREATE OPERATION EXECUTION (Add Availability Rule Slot)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_schedule'])) {
    $day          = $_POST['day'];
    $start_time   = $_POST['start_time'];
    $end_time     = $_POST['end_time'];
    $max_patients = (int)$_POST['max_patients'];

    if (empty($day) || empty($start_time) || empty($end_time) || $max_patients <= 0) {
        $error = "Please fill in all mandatory availability schedule configuration items.";
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $error = "Time Constraints Violation: Schedule start time must sit earlier than the set end time.";
    } else {
        try {
            // Check for existing structural overlaps for the same day to guarantee scheduler integrity
            $stmtCheck = $pdo->prepare("SELECT schedule_id FROM doctor_schedule WHERE doctor_id = ? AND day = ? AND start_time = ?");
            $stmtCheck->execute([$doctor_id, $day, $start_time]);

            if ($stmtCheck->rowCount() > 0) {
                $error = "A conflicting availability rule block has already been initialized for this day tracking slot.";
            } else {
                $stmtInsert = $pdo->prepare("INSERT INTO doctor_schedule (doctor_id, day, start_time, end_time, max_patients) VALUES (?, ?, ?, ?, ?)");
                $stmtInsert->execute([$doctor_id, $day, $start_time, $end_time, $max_patients]);
                $success = "New calendar schedule constraint rule integrated successfully.";
            }
        } catch (Exception $e) {
            $error = "Relational Storage Fault: Failed to commit availability parameters to the disk registry.";
        }
    }
}

// 2. DELETE OPERATION EXECUTION (Drop Availability Rule Slot)
if (isset($_GET['delete_schedule_id'])) {
    $delete_id = (int)$_GET['delete_schedule_id'];
    try {
        // Safe database deletion restricting drop scope entirely to the currently logged-in physician's token
        $stmtDelete = $pdo->prepare("DELETE FROM doctor_schedule WHERE schedule_id = ? AND doctor_id = ?");
        $stmtDelete->execute([$delete_id, $doctor_id]);
        $success = "Schedule availability tracking window rule dropped successfully.";
    } catch (Exception $e) {
        $error = "System Integrity Block: Cannot wipe schedule records that map to existing appointment logs.";
    }
}

// 3. READ OPERATION EXTRACTION (List Current Doctor's Rules)
try {
    $scheduleStmt = $pdo->prepare("SELECT * FROM doctor_schedule WHERE doctor_id = ? ORDER BY FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), start_time ASC");
    $scheduleStmt->execute([$doctor_id]);
    $mySchedules = $scheduleStmt->fetchAll();
} catch (Exception $e) {
    die("Structural Query Fault: Availability allocation matrix maps are unaligned.");
}

// =========================================================================
// BLOCK 2: PRESENTATION GRAPHICS INTERFACE (Safe HTML output context bounds)
// =========================================================================
require_once $project_root . '/includes/header.php';
require_once $project_root . '/includes/navbar.php';
?>

<div class="container my-5">
    <div class="row mb-4">
        <div class="col-md-12">
            <h3 class="fw-bold text-primary"><i class="fa-solid fa-calendar-days me-2"></i>Physician Consultation Availability Matrix</h3>
            <p class="text-secondary">Enforce systemic constraints defining active calendar operating windows and volume caps for Nazareth OPD patient booking engines.</p>
        </div>
    </div>

    <!-- Interface Feedback System Alerts -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger shadow-sm"><i class="fa-solid fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success shadow-sm"><i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Calendar Matrix Input Parameter Component Form -->
        <div class="col-lg-4">
            <div class="card bg-white border-0 p-4 shadow-sm">
                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fa-solid fa-clock me-2 text-primary"></i>Instantiate Shift Block</h5>
                <form action="manage_schedule.php" method="POST">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Operational Day Select <span class="text-danger">*</span></label>
                        <select name="day" class="form-select" required>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                            <option value="Sunday">Sunday</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Shift Start Time <span class="text-danger">*</span></label>
                        <input type="time" name="start_time" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Shift End Time <span class="text-danger">*</span></label>
                        <input type="time" name="end_time" class="form-control" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Maximum Outpatient Cap <span class="text-danger">*</span></label>
                        <input type="number" name="max_patients" class="form-control" min="1" max="50" value="10" required>
                        <div class="form-text text-muted small">Prevents structural overload beyond this intake count threshold loop.</div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="action_create_schedule" class="btn btn-primary fw-bold shadow-sm">
                            <i class="fa-solid fa-calendar-plus me-1"></i>Commit Availability Rule
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Dynamic Shift Allocation Overview Data Sheet Grid -->
        <div class="col-lg-8">
            <div class="card bg-white border-0 p-4 shadow-sm">
                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fa-solid fa-list-check me-2 text-primary"></i>My Live Operational Schedule Matrix</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle m-0">
                        <thead class="table-light">
                            <tr>
                                <th>Target Shift Day</th>
                                <th>Active Session Window</th>
                                <th class="text-center">Intake Cap Limit</th>
                                <th class="text-center" style="width: 25%;">Operations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mySchedules)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-calendar-xmark fa-2x mb-2 d-block text-secondary"></i>
                                        You have not defined any weekly consultation schedule constraints yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($mySchedules as $sched): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($sched['day']); ?></td>
                                        <td>
                                            <i class="fa-regular fa-clock me-1 text-secondary"></i>
                                            <?php echo date('g:i A', strtotime($sched['start_time'])); ?> - <?php echo date('g:i A', strtotime($sched['end_time'])); ?>
                                        </td>
                                        <td class="text-center">
                                            <strong><?php echo (int)$sched['max_patients']; ?> Patients Max</strong>
                                        </td>
                                        <td class="text-center">
                                            <a href="manage_schedule.php?delete_schedule_id=<?php echo (int)$sched['schedule_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this schedule block?');">
                                                <i class="fa-solid fa-trash-can me-1"></i>Remove
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
require_once $project_root . '/includes/footer.php';
?>

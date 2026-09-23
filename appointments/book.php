<?php
// =========================================================================
// BLOCK 1: BACKEND LOGIC PROCESSING LAYER (Absolute Server Root Engine)
// =========================================================================

// Fail-proof absolute mapping anchors directly to your local project root folder

$project_root = dirname(__DIR__);

require_once $project_root . '/config/database.php';
require_once $project_root . '/config/auth_middleware.php';

// Enforce strict Patient authorization clearance tokens
enforceRoleAccess(['Patient']);

$error = '';
$success = '';

// Fetch the current patient extension profile record ID based on the authenticated session user token
try {
    $patStmt = $pdo->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
    $patStmt->execute([$_SESSION['user_id']]);
    $patient_id = $patStmt->fetchColumn();

    if (!$patient_id) {
        die("<div class='container my-5 alert alert-danger fw-bold'>System Profile Conflict: Your active account has not been mapped to an outpatient profile file record yet.</div>");
    }
} catch (Exception $e) {
    die("Infrastructure Mapping Error: Core identity tables are inaccessible.");
}

// 1. AJAX ENDPOINT: Dynamically fetch assigned doctors based on the selected department ID
if (isset($_GET['fetch_doctors_by_dept'])) {
    $dept_id = (int)$_GET['fetch_doctors_by_dept'];
    try {
        $stmt = $pdo->prepare("SELECT doctor_id, name FROM doctors WHERE department_id = ?");
        $stmt->execute([$dept_id]);
        $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode($doctors);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        exit;
    }
}

// 2. AJAX ENDPOINT: Dynamically fetch active weekday schedule blocks for a selected doctor
if (isset($_GET['fetch_schedule_by_doc'])) {
    $doc_id = (int)$_GET['fetch_schedule_by_doc'];
    try {
        $stmt = $pdo->prepare("SELECT day, start_time, end_time FROM doctor_schedule WHERE doctor_id = ?");
        $stmt->execute([$doc_id]);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode($schedules);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        exit;
    }
}

// 3. APPOINTMENT POST OPERATION HANDLER: Register the booking record block
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_book_appointment'])) {
    $doctor_id        = (int)$_POST['doctor_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $reason           = trim($_POST['reason']);

    if (empty($doctor_id) || empty($appointment_date) || empty($appointment_time)) {
        $error = "Please fill in all mandatory appointment scheduling fields.";
    } else {
        try {
            // Verify if the slot matches the doctor's weekly shift settings parameters
            $dayOfWeek = date('l', strtotime($appointment_date));
            $stmtSched = $pdo->prepare("SELECT max_patients FROM doctor_schedule WHERE doctor_id = ? AND day = ?");
            $stmtSched->execute([$doctor_id, $dayOfWeek]);
            $scheduleDetails = $stmtSched->fetch();

            if (!$scheduleDetails) {
                $error = "The selected physician does not operate scheduled clinical blocks on " . $dayOfWeek . "s.";
            } else {
                // Check the volume cap limit for this date block to prevent structural overload
                $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status != 'Cancelled'");
                $stmtCount->execute([$doctor_id, $appointment_date]);
                $currentBookingsCount = $stmtCount->fetchColumn();

                if ($currentBookingsCount >= $scheduleDetails['max_patients']) {
                    $error = "The volume intake limit threshold for this target date block has been reached. Please choose another date.";
                } else {
                    // Check for pre-existing booking conflicts for this patient on the same date/time
                    $stmtConflict = $pdo->prepare("SELECT appointment_id FROM appointments WHERE patient_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'Cancelled'");
                    $stmtConflict->execute([$patient_id, $appointment_date, $appointment_time]);

                    if ($stmtConflict->rowCount() > 0) {
                        $error = "You already possess a concurrent active booking block scheduled for this time slot.";
                    } else {
                        // Persist appointment request record into MySQL [12. Suggested Database Tables]
                        $stmtInsert = $pdo->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
                        $stmtInsert->execute([$patient_id, $doctor_id, $appointment_date, $appointment_time, $reason]);
                        $success = "Your OPD clinical visit booking has been registered successfully! Pending confirmation.";
                    }
                }
            }
        } catch (Exception $e) {
            $error = "Relational Operational Fault: Failed to commit booking parameters to the system registry.";
        }
    }
}

// Extract active hospital partitions from database to seed the cascading selection entries dropdown
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
    <div class="row mb-4">
        <div class="col-md-12">
            <h3 class="fw-bold text-primary"><i class="fa-solid fa-calendar-check me-2"></i>Outpatient OPD Booking Matrix</h3>
            <p class="text-secondary">Avoid waiting lines completely. Follow the cascading matrix below to log your formal medical visit slots smoothly.</p>
        </div>
    </div>

    <!-- Feedback Alerts Container Channels -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger shadow-sm"><i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success shadow-sm"><i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card bg-white border-0 p-4 shadow-sm">
                <form action="book.php" method="POST">
                    
                    <!-- Step 1: Partition selection -->
                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="fa-solid fa-sitemap me-2 text-primary"></i>1. Select Medical Department Area <span class="text-danger">*</span></label>
                        <select id="department_select" class="form-select" required>
                            <option value="" disabled selected>Choose active department...</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo (int)$dept['department_id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Step 2: Physician assignment -->
                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="fa-solid fa-user-doctor me-2 text-primary"></i>2. Choose Assigned Specialist <span class="text-danger">*</span></label>
                        <select id="doctor_select" name="doctor_id" class="form-select" disabled required>
                            <option value="" disabled selected>Select department first...</option>
                        </select>
                    </div>

                    <!-- Step 3: Date picking window -->
                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="fa-solid fa-calendar-day me-2 text-primary"></i>3. Select Consultation Visit Date <span class="text-danger">*</span></label>
                        <input type="date" id="appointment_date" name="appointment_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" disabled required>
                        <!-- Dynamic operational feed info box area -->
                        <div id="schedule_info_box" class="form-text mt-2 p-2 alert alert-light border border-light-subtle d-none"></div>
                    </div>

                    <!-- Step 4: Time block mapping -->
                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="fa-solid fa-clock me-2 text-primary"></i>4. Select Target Time Slot <span class="text-danger">*</span></label>
                        <input type="time" name="appointment_time" class="form-control" disabled id="appointment_time" required>
                    </div>

                    <!-- Step 5: Clinical reason -->
                    <div class="mb-4">
                            <label class="form-label fw-bold"><i class="fa-solid fa-clipboard-question me-2 text-primary"></i>5. Chief Complaint / Reason for Visit</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Briefly describe the reason for your visit."></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="action_book_appointment" id="submit_booking_btn" class="btn btn-primary fw-bold shadow-sm" disabled>
                            <i class="fa-solid fa-calendar-check me-2"></i>Finalize Booking Allocation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const deptSelect = document.getElementById('department_select');
    const docSelect = document.getElementById('doctor_select');
    const dateInput = document.getElementById('appointment_date');
    const timeInput = document.getElementById('appointment_time');
    const infoBox = document.getElementById('schedule_info_box');
    const submitBtn = document.getElementById('submit_booking_btn');

    const resetDoctorSelect = function (message) {
        docSelect.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.disabled = true;
        option.selected = true;
        option.textContent = message;
        docSelect.appendChild(option);
    };

    const resetBookingControls = function () {
        dateInput.value = '';
        timeInput.value = '';
        dateInput.disabled = true;
        timeInput.disabled = true;
        submitBtn.disabled = true;
        infoBox.classList.add('d-none');
    };

    // 1. Department Selection Event Trigger listener
    deptSelect.addEventListener('change', async function () {
        const deptId = this.value;
        resetDoctorSelect('Loading active physicians...');
        docSelect.disabled = true;
        resetBookingControls();

        try {
            const response = await fetch(`book.php?fetch_doctors_by_dept=${encodeURIComponent(deptId)}`);
            if (!response.ok) {
                throw new Error('Unable to load physicians.');
            }

            const data = await response.json();
            resetDoctorSelect('Choose active physician...');

            if (!Array.isArray(data) || data.length === 0) {
                resetDoctorSelect('No physicians mapped to this partition.');
                return;
            }

            data.forEach(function (doc) {
                const option = document.createElement('option');
                option.value = doc.doctor_id;
                option.textContent = `Dr. ${doc.name}`;
                docSelect.appendChild(option);
            });
            docSelect.disabled = false;
        } catch (error) {
            resetDoctorSelect('Unable to load physicians.');
            infoBox.textContent = 'Unable to retrieve physicians for this department. Please try again.';
            infoBox.classList.remove('d-none');
        }
    });

    // 2. Doctor Selection Event Trigger listener
    docSelect.addEventListener('change', async function () {
        const docId = this.value;
        resetBookingControls();

        try {
            const response = await fetch(`book.php?fetch_schedule_by_doc=${encodeURIComponent(docId)}`);
            if (!response.ok) {
                throw new Error('Unable to load schedule.');
            }

            const data = await response.json();
            if (!Array.isArray(data) || data.length === 0) {
                infoBox.textContent = 'This physician has not declared weekly operational availability schedule rules yet.';
                infoBox.classList.remove('d-none');
                return;
            }

            const scheduleList = document.createElement('ul');
            scheduleList.className = 'mb-0 ps-3';
            data.forEach(function (sched) {
                const item = document.createElement('li');
                item.textContent = `${sched.day}s (${sched.start_time} - ${sched.end_time})`;
                scheduleList.appendChild(item);
            });
            infoBox.replaceChildren(document.createTextNode('Physician Operating Weekly Schedule Rules:'), scheduleList);
            infoBox.classList.remove('d-none');
            dateInput.disabled = false;
            timeInput.disabled = false;
            submitBtn.disabled = false;
        } catch (error) {
            infoBox.textContent = 'Unable to retrieve this physician’s schedule. Please try again.';
            infoBox.classList.remove('d-none');
        }
    });
});
</script>

<?php
require_once $project_root . '/includes/footer.php';
?>

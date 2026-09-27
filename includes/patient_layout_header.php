<?php
// =========================================================================
// Shared sidebar + topbar chrome for every patient-facing page.
// Expects: $pdo (PDO connection), active session, and optionally
// $activePage (string) set by the including page to highlight its nav item.
// =========================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || strtolower((string)($_SESSION['role_name'] ?? '')) !== 'patient') {
    header('Location: /login.php');
    exit;
}

$activePage = $activePage ?? '';

// Pull the patient's display name for the welcome header.
$stmt = $pdo->prepare("SELECT full_name FROM patients WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$patientName = $stmt->fetchColumn() ?: $_SESSION['username'];

// Unread notification count for the bell icon.
$unreadNotifications = 0;
try {
    $notifStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $notifStmt->execute([$_SESSION['user_id']]);
    $unreadNotifications = (int) $notifStmt->fetchColumn();
} catch (Exception $e) {
    $unreadNotifications = 0;
}

// Unread message count for the sidebar badge.
$unreadMessages = 0;
try {
    $msgStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $msgStmt->execute([$_SESSION['user_id']]);
    $unreadMessages = (int) $msgStmt->fetchColumn();
} catch (Exception $e) {
    $unreadMessages = 0;
}

$navItems = [
    ['key' => 'dashboard',   'icon' => 'house',            'label' => 'Dashboard',         'href' => '/patient/dashboard.php'],
    ['key' => 'book',        'icon' => 'calendar-plus',    'label' => 'Book Appointment',   'href' => '/appointments/book.php'],
    ['key' => 'find_doctor', 'icon' => 'stethoscope',      'label' => 'Find a Doctor',      'href' => '/patient/find_doctor.php'],
    ['key' => 'appointments','icon' => 'calendar-days',    'label' => 'My Appointments',    'href' => '/patient/appointments.php'],
    ['key' => 'records',     'icon' => 'folder-open',      'label' => 'Medical Records',    'href' => '/patient/medical_records.php'],
    ['key' => 'prescriptions','icon' => 'prescription-bottle-medical', 'label' => 'Prescriptions', 'href' => '/patient/prescriptions.php'],
    ['key' => 'results',     'icon' => 'flask',            'label' => 'Test Results',       'href' => '/patient/test_results.php'],
    ['key' => 'billing',     'icon' => 'file-invoice-dollar','label' => 'Billing & Payments','href' => '/patient/billing.php'],
    ['key' => 'messages',    'icon' => 'envelope',         'label' => 'Messages',           'href' => '/patient/messages.php', 'badge' => $unreadMessages],
    ['key' => 'notifications','icon' => 'bell',            'label' => 'Notifications',      'href' => '/patient/notifications.php'],
    ['key' => 'settings',    'icon' => 'gear',             'label' => 'Settings',           'href' => '/change_password.php'],
    ['key' => 'help',        'icon' => 'circle-question',  'label' => 'Help & Support',     'href' => '/patient/help.php'],
];
?>
<!DOCTYPE html>
<html lang="en" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nazareth Hospital OPD Appointment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body { background: #f4f6f9; }
        .patient-shell { display: flex; min-height: 100vh; }
        .patient-sidebar {
            width: 250px; flex-shrink: 0; background: #14213d; color: #cfd6e4;
            display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto;
        }
        .patient-sidebar .brand { padding: 1.25rem 1.25rem 1rem; color: #fff; }
        .patient-sidebar .brand strong { color: #4dabf7; }
        .patient-sidebar .nav-link {
            color: #cfd6e4; padding: 0.65rem 1.25rem; border-radius: 0; display: flex; align-items: center; gap: 0.6rem;
            font-size: 0.92rem; font-weight: 500;
        }
        .patient-sidebar .nav-link i { width: 18px; text-align: center; }
        .patient-sidebar .nav-link:hover { background: #1c2b4d; color: #fff; }
        .patient-sidebar .nav-link.active { background: #1c7ed6; color: #fff; }
        .patient-sidebar .quote { margin-top: auto; padding: 1.25rem; font-style: italic; color: #7c8aa8; font-size: 0.85rem; text-align: center; }
        .patient-main { flex-grow: 1; min-width: 0; }
        .patient-topbar {
            background: #fff; border-bottom: 1px solid #e9ecef; padding: 0.75rem 1.5rem;
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        }
        .patient-topbar .search-box { max-width: 420px; width: 100%; }
        .badge-dot {
            position: absolute; top: -4px; right: -6px; font-size: 0.65rem; padding: 0.15rem 0.4rem;
        }
    </style>
</head>
<body class="h-100">
<div class="patient-shell">
    <aside class="patient-sidebar">
        <div class="brand">
            <div class="fw-bold fs-5"><strong>Nazareth</strong> Hospital</div>
            <div class="small text-secondary">Compassionate Care for a Healthier Tomorrow</div>
        </div>
        <nav class="nav flex-column mt-2">
            <?php foreach ($navItems as $item): ?>
                <a class="nav-link <?php echo $activePage === $item['key'] ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($item['href']); ?>">
                    <i class="fa-solid fa-<?php echo $item['icon']; ?>"></i>
                    <span><?php echo htmlspecialchars($item['label']); ?></span>
                    <?php if (!empty($item['badge'])): ?>
                        <span class="badge bg-danger rounded-pill ms-auto"><?php echo (int) $item['badge']; ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
            <a class="nav-link" href="/logout.php">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Sign Out</span>
            </a>
        </nav>
        <div class="quote">&ldquo;Your Health<br>Our Priority&rdquo;</div>
    </aside>

    <main class="patient-main">
        <div class="patient-topbar">
            <div class="search-box">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" placeholder="Search doctors, departments, appointments...">
                </div>
            </div>
            <div class="d-flex align-items-center gap-4">
                <a href="/patient/notifications.php" class="position-relative text-dark" style="font-size: 1.15rem;">
                    <i class="fa-regular fa-bell"></i>
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="badge bg-danger rounded-pill badge-dot"><?php echo $unreadNotifications; ?></span>
                    <?php endif; ?>
                </a>
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px;">
                        <?php echo htmlspecialchars(strtoupper(substr($patientName, 0, 1))); ?>
                    </div>
                    <div class="small">
                        <div class="text-muted" style="line-height:1;">Welcome,</div>
                        <div class="fw-bold" style="line-height:1.2;"><?php echo htmlspecialchars($patientName); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-4">

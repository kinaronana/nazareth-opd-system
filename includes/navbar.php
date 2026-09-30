<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = strtolower((string) ($_SESSION['role_name'] ?? ''));
$dashboardRoutes = [
    'admin' => '/admin/dashboard.php',
    'doctor' => '/doctor/dashboard.php',
    'patient' => '/patient/dashboard.php',
];
$dashboardUrl = $dashboardRoutes[$role] ?? '/index.php';

$inboxRoutes = [
    'doctor' => '/doctor/messages.php',
    'admin' => '/admin/messages.php',
];
$unreadMessageCount = 0;
if (isset($_SESSION['user_id']) && isset($inboxRoutes[$role])) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unreadMessageCount = (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        $unreadMessageCount = 0;
    }
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand fw-bold" href="/index.php">Nazareth OPD</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavigation" aria-controls="mainNavigation" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNavigation">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="/index.php">Home</a></li>
        <?php if (isset($_SESSION['user_id'])): ?>
          <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8'); ?>">My Dashboard</a></li>
          <?php if ($role === 'admin'): ?>
            <li class="nav-item"><a class="nav-link" href="/admin/doctors/manage_doctors.php">Doctors</a></li>
            <li class="nav-item"><a class="nav-link" href="/admin/manage_appointments.php">Appointments</a></li>
          <?php endif; ?>
          <?php if (isset($inboxRoutes[$role])): ?>
            <li class="nav-item">
              <a class="nav-link position-relative" href="<?php echo htmlspecialchars($inboxRoutes[$role]); ?>">
                Messages
                <?php if ($unreadMessageCount > 0): ?>
                  <span class="badge bg-danger rounded-pill ms-1"><?php echo $unreadMessageCount; ?></span>
                <?php endif; ?>
              </a>
            </li>
          <?php endif; ?>
          <li class="nav-item"><a class="nav-link" href="/change_password.php">Change Password</a></li>
          <li class="nav-item"><a class="nav-link" href="/logout.php">Sign out</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="/register.php">Book Appointment</a></li>
          <li class="nav-item"><a class="nav-link" href="/login.php">Sign in</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<?php if (isset($_SESSION['user_id'])): ?>
<div class="bg-white border-bottom px-3 py-2">
    <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold" onclick="if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) { history.back(); } else { window.location.href = '<?php echo htmlspecialchars($dashboardUrl); ?>'; }">
        <i class="fa-solid fa-arrow-left me-1"></i>Back
    </button>
</div>
<?php endif; ?>

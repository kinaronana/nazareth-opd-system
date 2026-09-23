<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = strtolower((string) ($_SESSION['role_name'] ?? ''));
$dashboardRoutes = [
    'admin' => '/admin/dashboard.php',
    'doctor' => '/doctor/dashboard.php',
    'patient' => '/appointments/book.php',
];
$dashboardUrl = $dashboardRoutes[$role] ?? '/index.php';
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
          <li class="nav-item"><a class="nav-link" href="/logout.php">Sign out</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="/register.php">Book Appointment</a></li>
          <li class="nav-item"><a class="nav-link" href="/login.php">Sign in</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

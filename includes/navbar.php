<?php
// Dynamically resolve the absolute root URL of the site whether on localhost or InfinityFree
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/";
?>
<!-- Your navbar structure with injected dynamic bases -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?php echo $base_url; ?>admin/dashboard.php">Nazareth OPD</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="<?php echo $base_url; ?>admin/dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="<?php echo $base_url; ?>admin/doctors/add_doctor.php">Add Doctor</a></li>
        <li class="nav-item"><a class="nav-link" href="<?php echo $base_url; ?>admin/doctors/manage_doctors.php">Manage Doctors</a></li>
        <li class="nav-item"><a class="nav-link" href="<?php echo $base_url; ?>admin/departments/departments.php">Departments</a></li>
        <li class="nav-item"><a class="nav-link" href="<?php echo $base_url; ?>admin/patients/manage_patients.php">Patients</a></li>
        <li class="nav-item"><a class="nav-link" href="<?php echo $base_url; ?>admin/dashboard_reports.php">Reports</a></li>
        <li class="nav-item"><a class="nav-link" href="<?php echo $base_url; ?>admin/manage_appointments.php">Appointments</a></li>
      </ul>
    </div>
  </div>
</nav>

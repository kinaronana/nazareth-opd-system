<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <!-- Main Hospital Gateway Brand Anchor -->
        <a class="navbar-brand fw-bold" href="/nazareth-opd-system/index.php">
            <i class="fa-solid fa-hospital-user me-2"></i>Nazareth Hospital OPD
        </a>
        
        <!-- Mobile Layout Responsive Hamburger Toggle -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nazarethOPDNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="nazarethOPDNav">
            <!-- Left Side Core Navigation Links -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="/nazareth-opd-system/index.php">
                        <i class="fa-solid fa-house-medical me-1"></i>Home
                    </a>
                </li>
                
                <?php if (isset($_SESSION['role_name'])): ?>
                    <!-- Contextual Link Injector based on active role permissions matrices -->
                    <?php if ($_SESSION['role_name'] === 'Admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/departments/departments.php">
                                <i class="fa-solid fa-sitemap me-1"></i>Departments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/doctors/manage_doctors.php">
                                <i class="fa-solid fa-user-doctor me-1"></i>Physicians
                            </a>
                        </li>
                    <?php elseif ($_SESSION['role_name'] === 'Doctor'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/nazareth-opd-system/doctor/schedule/manage_schedule.php">
                                <i class="fa-solid fa-calendar-days me-1"></i>My Schedule
                            </a>
                        </li>
                    <?php elseif ($_SESSION['role_name'] === 'Patient'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/nazareth-opd-system/patient/booking/book.php">
                                <i class="fa-solid fa-calendar-check me-1"></i>Book Appointment
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <!-- Right Side Security Access Indicators -->
            <ul class="navbar-nav ms-auto align-items-center">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item me-lg-3 mb-2 mb-lg-0">
                        <a class="btn btn-sm btn-warning fw-bold text-dark px-3 shadow-sm" href="/admin/dashboard.php">
                            <i class="fa-solid fa-gauge me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item me-lg-3 mb-2 mb-lg-0">
                        <span class="navbar-text text-white">
                            <i class="fa-solid fa-circle-user me-1 text-light"></i>
                            User: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-sm btn-outline-light px-3" href="/nazareth-opd-system/logout.php">
                            <i class="fa-solid fa-power-off me-1"></i>Logout
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/nazareth-opd-system/login.php">
                            <i class="fa-solid fa-right-to-bracket me-1"></i>Sign In
                        </a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-sm btn-light text-primary fw-bold px-3 shadow-sm" href="/nazareth-opd-system/register.php">
                            <i class="fa-solid fa-user-plus me-1"></i>Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

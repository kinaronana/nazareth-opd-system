<?php 
require_once 'includes/header.php'; 
require_once 'includes/navbar.php'; 
?>

<div class="container my-5">
    <!-- Hero Header Banner Section -->
    <div class="row p-4 pb-0 pe-lg-0 pt-lg-5 align-items-center rounded-3 border shadow-sm bg-white">
        <div class="col-lg-7 p-3 p-lg-5 pt-lg-3">
            <h1 class="display-5 fw-bold lh-1 text-primary">Nazareth Hospital OPD Appointment Gateway</h1>
            <p class="lead mt-3 text-secondary">Avoid waiting lines completely. Book clinical appointments online with our specialists, track live physical schedules, and manage medical visits easily from your home dashboard.</p>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-start mb-4 mb-lg-3 mt-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- If a user is already signed in, provide a direct routing link back to their portal -->
                    <a href="/nazareth-opd-system/<?php echo strtolower($_SESSION['role_name']); ?>/dashboard.php" class="btn btn-primary btn-lg px-4 me-md-2 fw-bold">
                        <i class="fa-solid fa-gauge me-2"></i>Go to My Dashboard
                    </a>
                <?php else: ?>
                    <!-- If a user is a guest, show standard onboarding links -->
                    <a href="/nazareth-opd-system/register.php" class="btn btn-primary btn-lg px-4 me-md-2 fw-bold shadow">
                        <i class="fa-solid fa-calendar-check me-2"></i>Book an Appointment
                    </a>
                    <a href="/nazareth-opd-system/login.php" class="btn btn-outline-secondary btn-lg px-4">
                        <i class="fa-solid fa-right-to-bracket me-2"></i>Sign In
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sidebar Informational Card Anchor -->
        <div class="col-lg-4 offset-lg-1 p-0 overflow-hidden shadow rounded-3 mb-4 bg-primary text-white d-flex flex-column justify-content-center align-items-center text-center mx-auto" style="min-height: 320px;">
            <div class="p-4">
                <i class="fa-solid fa-user-doctor fa-4x mb-3 text-warning"></i>
                <h4 class="fw-bold">Outpatient Care Fast-Track</h4>
                <p class="small px-2 m-0 opacity-75">Fulfilling design specifications for streamlined, computerized operational workflow control at Nazareth Hospital OPD.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

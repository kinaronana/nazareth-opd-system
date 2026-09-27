<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    $destinations = [
        'Admin' => '/admin/dashboard.php',
        'Doctor' => '/doctor/dashboard.php',
        'Patient' => '/patient/dashboard.php',
    ];
    header('Location: ' . ($destinations[$_SESSION['role_name'] ?? ''] ?? '/login.php'));
    exit;
}

header('Location: /login.php');
exit;

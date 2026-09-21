<?php
// STEP 1: INITIALIZE SECURE STATE TRACKING SESSIONS
// Starts the global session wrapper if it has not already been initialized on the server
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nazareth Hospital OPD Appointment System</title>
    
    <!-- Bootstrap 5 CSS via secure Content Delivery Network (CDN) -->
    <link href="https://jsdelivr.net" rel="stylesheet">
    
    <!-- FontAwesome 6 for visual dashboard anchors and vector icons -->
    <link href="https://cloudflare.com" rel="stylesheet">
    
    <!-- Custom application override stylesheet layout link -->
    <link rel="stylesheet" href="/nazareth-opd-system/assets/css/style.css">
</head>
<body class="d-flex flex-column h-100 bg-light">

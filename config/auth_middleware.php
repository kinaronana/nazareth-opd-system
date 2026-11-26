<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function enforceRoleAccess(array $allowedRoles): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }

    if (!isset($_SESSION['role_name']) || !in_array($_SESSION['role_name'], $allowedRoles, true)) {
        http_response_code(403);
        exit('Access denied. <a href="/index.php">Return to the homepage</a>');
    }
}

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Enforces Role-Based Access Control matrix bounds.
 * Blocks unauthenticated clients and redirects intruders to the sign-in hub.
 *
 * @param array $allowedRoles Array of structural strings containing permitted system clearance profiles
 */
function enforceRoleAccess(array $allowedRoles) {
    // If the tracking session context pointer doesn't exist, block entry points entirely
    if (!isset($_SESSION['user_id'])) {
        header("Location: /nazareth-opd-system/login.php");
        exit;
    }

    // Evaluate structural role permissions against authorization access array strings
    if (!in_array($_SESSION['role_name'], $allowedRoles)) {
        http_response_code(403);
        die("<div style='font-family: sans-serif; text-align: center; margin-top: 10%;'>
                <h1 style='color: #dc3545;'>Access Violation [403]</h1>
                <p style='color: #6c757d;'>Your active account profile lacks the cryptographic permissions required to parse this workspace path.</p>
                <a href='/nazareth-opd-system/index.php' style='text-decoration: none; color: #0d6efd; font-weight: bold;'>Return to Safe Public Gateway</a>
             </div>");
    }
}
?>

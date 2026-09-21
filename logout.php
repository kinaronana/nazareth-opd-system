<?php
// Initialize or catch the active session container safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset all active memory session indices cleanly
$_SESSION = array();

// If browser session cookie storage cookies are used, expire them explicitly
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear the server storage context path completely
session_destroy();

// Redirect the client back to the public landing index gateway page
header("Location: /nazareth-opd-system/index.php");
exit;
?>

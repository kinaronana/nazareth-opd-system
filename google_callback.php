<?php
// Step 2: Google sends the visitor back here with a one-time code.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/google_auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function googleFail(string $code): void
{
    header('Location: /login.php?google=' . $code);
    exit;
}

if (!googleConfigured()) {
    googleFail('unavailable');
}
if (isset($_GET['error'])) {
    googleFail('cancelled');
}

$state = $_GET['state'] ?? '';
$expected = $_SESSION['google_oauth_state'] ?? '';
unset($_SESSION['google_oauth_state']);
if ($state === '' || $expected === '' || !hash_equals($expected, $state) || empty($_GET['code'])) {
    googleFail('failed');
}

// Exchange the code for an access token (server to server).
$token = googleHttp('https://oauth2.googleapis.com/token', [
    'code'          => $_GET['code'],
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code',
]);
if (!$token || empty($token['access_token'])) {
    googleFail('failed');
}

// Ask Google who this is.
$profile = googleHttp('https://openidconnect.googleapis.com/v1/userinfo', null, $token['access_token']);
if (!$profile || empty($profile['email']) || empty($profile['email_verified'])) {
    googleFail('unverified');
}

$email = strtolower(trim($profile['email']));
$name = trim($profile['name'] ?? '');

try {
    $stmt = $pdo->prepare(
        "SELECT u.*, r.role_name
         FROM users u
         LEFT JOIN roles r ON u.role_id = r.role_id
         WHERE u.email = ?"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    googleFail('failed');
}

if ($user) {
    if ($user['status'] !== 'Active') {
        googleFail('inactive');
    }
    signInAndRedirect($pdo, $user);
}

// Brand-new person: collect the remaining patient details first.
$_SESSION['google_pending'] = ['email' => $email, 'name' => $name];
header('Location: /google_register.php');
exit;

<?php
// =========================================================================
// Shared helpers for the "Sign in with Google" flow.
// =========================================================================

/**
 * True only when config/google_config.php exists and has real (non-placeholder) values.
 */
function googleConfigured(): bool
{
    $file = __DIR__ . '/google_config.php';
    if (!is_file($file)) {
        return false;
    }
    require_once $file;

    return defined('GOOGLE_CLIENT_ID') && defined('GOOGLE_CLIENT_SECRET') && defined('GOOGLE_REDIRECT_URI')
        && GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '' && GOOGLE_REDIRECT_URI !== ''
        && strpos(GOOGLE_CLIENT_ID, 'PASTE_') === false
        && strpos(GOOGLE_CLIENT_SECRET, 'PASTE_') === false
        && strpos(GOOGLE_REDIRECT_URI, 'YOUR-DOMAIN') === false;
}

/**
 * Small HTTPS helper (server to server). Returns decoded JSON array or null on failure.
 */
function googleHttp(string $url, ?array $post = null, ?string $bearer = null): ?array
{
    $headers = ['Accept: application/json'];
    if ($bearer !== null) {
        $headers[] = 'Authorization: Bearer ' . $bearer;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $body = curl_exec($ch);
        curl_close($ch);
    } else {
        $opts = ['http' => ['method' => $post !== null ? 'POST' : 'GET', 'timeout' => 15, 'ignore_errors' => true]];
        if ($post !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $opts['http']['content'] = http_build_query($post);
        }
        $opts['http']['header'] = implode("\r\n", $headers);
        $body = @file_get_contents($url, false, stream_context_create($opts));
    }

    if (!is_string($body) || $body === '') {
        return null;
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

/**
 * Where each role lands after signing in.
 */
function roleDestination(?string $roleName): string
{
    $destinations = [
        'Admin'   => '/admin/dashboard.php',
        'Doctor'  => '/doctor/dashboard.php',
        'Patient' => '/patient/dashboard.php',
    ];
    return $destinations[$roleName] ?? '/index.php';
}

/**
 * Start an authenticated session for a user row (must include role_name) and redirect.
 */
function signInAndRedirect(PDO $pdo, array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role_name'] = $user['role_name'];

    require_once __DIR__ . '/helpers.php';
    logActivity($pdo, (int) $user['user_id'], 'Signed in with Google', 'right-to-bracket');

    header('Location: ' . roleDestination($user['role_name']));
    exit;
}

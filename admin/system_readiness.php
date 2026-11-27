<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth_middleware.php';
enforceRoleAccess(['Admin']);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$checks = [];
$checks['php_version'] = ['ok' => version_compare(PHP_VERSION, '8.2.0', '>='), 'value' => PHP_VERSION];
foreach (['pdo_mysql', 'curl', 'openssl', 'session', 'json'] as $extension) {
    $checks['extension_' . $extension] = ['ok' => extension_loaded($extension)];
}
$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$checks['https'] = ['ok' => $isHttps];
$checks['session_httponly'] = ['ok' => (bool) ini_get('session.cookie_httponly')];
$checks['session_secure'] = ['ok' => !$isHttps || (bool) ini_get('session.cookie_secure')];
$checks['session_samesite'] = ['ok' => strtolower((string) ini_get('session.cookie_samesite')) === 'lax'];
$sessionStorageOk = false;
if (session_status() === PHP_SESSION_ACTIVE) {
    $originalSessionId = session_id();
    $probeKey = '__opd_readiness_' . bin2hex(random_bytes(8));
    $probeValue = bin2hex(random_bytes(16));
    try {
        $_SESSION[$probeKey] = $probeValue;
        session_write_close();
        session_id($originalSessionId);
        if (session_start()) {
            $sessionStorageOk = isset($_SESSION[$probeKey])
                && is_string($_SESSION[$probeKey])
                && hash_equals($probeValue, $_SESSION[$probeKey]);
            unset($_SESSION[$probeKey]);
            session_write_close();
        }
    } catch (Throwable $e) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION[$probeKey]);
            session_write_close();
        }
        $sessionStorageOk = false;
    }
}
$checks['session_storage'] = ['ok' => $sessionStorageOk];
$productionConfig = dirname(__DIR__) . '/config/production.php';
$checks['production_config'] = ['ok' => is_readable($productionConfig)];
try {
    $pdo->query('SELECT 1');
    $checks['database'] = ['ok' => true];
} catch (Throwable $e) {
    $checks['database'] = ['ok' => false];
}

$key = trim((string) (getenv('GEMINI_API_KEY') ?: ''));
$model = trim((string) (getenv('GEMINI_MODEL') ?: 'gemini-3.5-flash-lite'));
$gemini = ['ok' => false, 'status' => 'not_configured'];
if ($key !== '' && function_exists('curl_init') && preg_match('/^[A-Za-z0-9._-]{1,100}$/', $model)) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
    $payload = json_encode([
        'contents' => [['role' => 'user', 'parts' => [['text' => 'Reply with the single word OK.']]]],
        'generationConfig' => ['maxOutputTokens' => 8],
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
        CURLOPT_POSTFIELDS => $payload,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($ch);
    curl_close($ch);
    $decoded = is_string($response) ? json_decode($response, true) : null;
    $ok = !$curlError && $status >= 200 && $status < 300 && isset($decoded['candidates'][0]);
    $gemini = ['ok' => $ok, 'status' => $ok ? 'connected' : ($curlError ? 'connection_failed' : 'http_' . $status), 'model' => $model];
}
$checks['gemini'] = $gemini;
$failed = array_filter($checks, static fn(array $check): bool => empty($check['ok']));
http_response_code($failed ? 503 : 200);
echo json_encode(['ready' => !$failed, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

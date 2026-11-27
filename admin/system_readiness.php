<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth_middleware.php';
enforceRoleAccess(['Admin']);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function readinessJson(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    readinessJson(405, ['error' => 'Method not allowed.']);
}

// A GET may inspect local readiness only. It must never make a billable external
// provider request. It also issues the session-bound token for an intentional POST.
if (empty($_SESSION['system_readiness_csrf'])) {
    $_SESSION['system_readiness_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['system_readiness_csrf'];

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
            // Resume the authenticated session for the remainder of this request.
            session_id($originalSessionId);
            session_start();
        }
    } catch (Throwable $e) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION[$probeKey]);
            session_write_close();
            session_id($originalSessionId);
            session_start();
        }
        $sessionStorageOk = false;
    }
}
$checks['session_storage'] = ['ok' => $sessionStorageOk];
$productionConfigPath = dirname(__DIR__) . '/config/production.php';
$productionConfigReadable = is_readable($productionConfigPath);
$checks['production_config'] = ['ok' => $productionConfigReadable];

// Inspect Gemini configuration without ever returning credential material.
// Use a distinct variable because config/database.php has already loaded its
// own production configuration before this endpoint executes.
$readinessProductionConfig = [];
if ($productionConfigReadable) {
    try {
        $loadedProductionConfig = require $productionConfigPath;
        if (is_array($loadedProductionConfig)) {
            $readinessProductionConfig = $loadedProductionConfig;
        }
    } catch (Throwable $e) {
        $readinessProductionConfig = [];
    }
}
$productionGeminiKey = $readinessProductionConfig['gemini_api_key'] ?? '';
$environmentGeminiKey = getenv('GEMINI_API_KEY');
$productionGeminiKeyPresent = is_string($productionGeminiKey) && trim($productionGeminiKey) !== '';
$environmentGeminiKeyPresent = is_string($environmentGeminiKey) && trim($environmentGeminiKey) !== '';
$configuredGeminiModel = trim((string) (getenv('GEMINI_MODEL') ?: ($readinessProductionConfig['gemini_model'] ?? 'gemini-3.5-flash-lite')));
$geminiModelValid = (bool) preg_match('/^[A-Za-z0-9._-]{1,100}$/', $configuredGeminiModel);
$checks['gemini_configuration'] = [
    'ok' => ($productionGeminiKeyPresent || $environmentGeminiKeyPresent) && $geminiModelValid,
    'key_in_production_config' => $productionGeminiKeyPresent,
    'key_in_environment' => $environmentGeminiKeyPresent,
    'model_valid' => $geminiModelValid,
];
try {
    $pdo->query('SELECT 1');
    $checks['database'] = ['ok' => true];
} catch (Throwable $e) {
    $checks['database'] = ['ok' => false];
}

// Capture local readiness before adding the optional external-provider result.
// An untested Gemini integration is informational on GET, not a local failure.
$localFailed = array_filter($checks, static fn(array $check): bool => empty($check['ok']));
$localReady = !$localFailed;

$gemini = ['ok' => false, 'status' => 'not_tested'];
if ($method === 'POST') {
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input) || !hash_equals($csrf, (string) ($input['csrf'] ?? ''))) {
        readinessJson(419, ['error' => 'Security token expired. Reload the readiness check and try again.']);
    }

    $lastTest = (int) ($_SESSION['system_readiness_gemini_last_test'] ?? 0);
    if ($lastTest > time() - 30) {
        readinessJson(429, ['error' => 'Please wait at least 30 seconds before testing Gemini again.']);
    }

    // Persist the throttle before making the external request.
    $_SESSION['system_readiness_gemini_last_test'] = time();
    session_write_close();
    // Resolve from the production config as a final fallback. Do not rely
    // solely on getenv(), which may be empty on shared-hosting SAPIs.
    $key = trim((string) (getenv('GEMINI_API_KEY') ?: ($readinessProductionConfig['gemini_api_key'] ?? '')));
    $model = trim((string) (getenv('GEMINI_MODEL') ?: ($readinessProductionConfig['gemini_model'] ?? 'gemini-3.5-flash-lite')));
    $gemini = ['ok' => false, 'status' => 'not_configured'];
    if ($key !== '' && function_exists('curl_init') && preg_match('/^[A-Za-z0-9._-]{1,100}$/', $model)) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
        $payload = json_encode([
            'contents' => [['role' => 'user', 'parts' => [['text' => 'Reply with the single word OK.']]]],
            'generationConfig' => ['maxOutputTokens' => 8],
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
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
        $gemini = [
            'ok' => $ok,
            'status' => $ok ? 'connected' : ($curlError ? 'connection_failed' : 'http_' . $status),
            'model' => $model,
        ];
    }
}

$checks['gemini'] = $gemini;
$overallReady = $localReady && ($method !== 'POST' || !empty($gemini['ok']));
$responseStatus = $overallReady ? 200 : 503;
readinessJson($responseStatus, [
    'ready' => $overallReady,
    'local_ready' => $localReady,
    'overall_ready' => $overallReady,
    'csrf' => $csrf,
    'checks' => $checks,
]);

<?php
declare(strict_types=1);

/**
 * Static checks for deployment safeguards. No production credentials,
 * FTP connection, hosted database, or Gemini request is used.
 */
function readinessCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("FAIL: {$message}");
    }
    echo "PASS: {$message}\n";
}

$root = dirname(__DIR__);
$deployWorkflow = file_get_contents($root . '/.github/workflows/deploy.yml');
$readinessEndpoint = file_get_contents($root . '/admin/system_readiness.php');

readinessCheck(
    preg_match('/^\s*protocol:\s*ftps\s*$/m', $deployWorkflow) === 1,
    'deployment transfers use explicit FTPS'
);
readinessCheck(
    preg_match('/^\s*protocol:\s*ftp\s*$/m', $deployWorkflow) !== 1,
    'unencrypted FTP is not configured'
);
readinessCheck(
    strpos($readinessEndpoint, 'session_write_close()') !== false
        && strpos($readinessEndpoint, 'session_start()') !== false,
    'session diagnostic performs a write-close-reopen cycle'
);
readinessCheck(
    strpos($readinessEndpoint, 'random_bytes(') !== false
        && strpos($readinessEndpoint, 'hash_equals(') !== false,
    'session diagnostic uses a random probe and constant-time verification'
);
readinessCheck(
    strpos($readinessEndpoint, "['REQUEST_METHOD']") !== false
        && strpos($readinessEndpoint, "'POST'") !== false
        && strpos($readinessEndpoint, "'GET'") !== false,
    'readiness endpoint distinguishes safe GET checks from explicit POST actions'
);
readinessCheck(
    strpos($readinessEndpoint, 'system_readiness_csrf') !== false
        && strpos($readinessEndpoint, 'hash_equals($csrf') !== false,
    'Gemini diagnostic requires a session-bound CSRF token'
);
readinessCheck(
    strpos($readinessEndpoint, 'system_readiness_gemini_last_test') !== false
        && strpos($readinessEndpoint, 'time() - 30') !== false,
    'Gemini diagnostic is rate-limited to one request per 30 seconds per session'
);
readinessCheck(
    strpos($readinessEndpoint, "'status' => 'not_tested'") !== false
        && strpos($readinessEndpoint, 'x-goog-api-key: ') !== false
        && strpos($readinessEndpoint, "'gemini_api_key'") === false,
    'GET does not call Gemini and POST keeps the API key out of diagnostic output'
);
readinessCheck(
    strpos($readinessEndpoint, '$localFailed = array_filter($checks') !== false
        && strpos($readinessEndpoint, '$localReady = !$localFailed;') !== false
        && strpos($readinessEndpoint, '$overallReady = $localReady && ($method !== \'POST\' || !empty($gemini[\'ok\']));') !== false,
    'untested Gemini status is excluded from GET local readiness but POST tests provider connectivity'
);

echo "All deployment-readiness safeguard checks passed.\n";

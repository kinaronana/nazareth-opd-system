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
    strpos($readinessEndpoint, "'gemini'") !== false
        && strpos($readinessEndpoint, "'model' => $model") !== false
        && strpos($readinessEndpoint, "'gemini_api_key'") === false,
    'Gemini diagnostics report connection metadata without returning the API key'
);

echo "All deployment-readiness safeguard checks passed.\n";

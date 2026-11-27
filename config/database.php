<?php
/**
 * Loads production database settings without committing credentials.
 * Environment variables take precedence so deployment secrets can rotate safely.
 */
function loadEnvironmentFile(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || substr($line, 0, 1) === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
        }
    }
}

loadEnvironmentFile(dirname(__DIR__) . '/.env');

$productionConfigPath = __DIR__ . '/production.php';
$productionConfig = is_readable($productionConfigPath) ? require $productionConfigPath : [];
if (!is_array($productionConfig)) {
    $productionConfig = [];
}

$settings = [
    'host' => getenv('OPD_DB_HOST') ?: '',
    'name' => getenv('OPD_DB_NAME') ?: '',
    'user' => getenv('OPD_DB_USERNAME') ?: '',
    'password' => getenv('OPD_DB_PASSWORD') ?: '',
];

if (in_array('', $settings, true)) {
    $settings = [
        'host' => $productionConfig['host'] ?? '',
        'name' => $productionConfig['name'] ?? '',
        'user' => $productionConfig['user'] ?? '',
        'password' => $productionConfig['password'] ?? '',
    ];
}

// Production secrets are read server-side only; never expose them to browser code.
// Some shared-hosting SAPIs expose unset variables as empty strings rather than
// false. Treat both forms as missing so the generated production config is used.
if (trim((string) (getenv('GEMINI_API_KEY') ?: '')) === '' && !empty($productionConfig['gemini_api_key'])) {
    putenv('GEMINI_API_KEY=' . $productionConfig['gemini_api_key']);
}
if (trim((string) (getenv('GEMINI_MODEL') ?: '')) === '' && !empty($productionConfig['gemini_model'])) {
    putenv('GEMINI_MODEL=' . $productionConfig['gemini_model']);
}

if (in_array('', $settings, true)) {
    http_response_code(503);
    exit('Service temporarily unavailable. The application database has not been configured.');
}

try {
    $pdo = new PDO(
        'mysql:host=' . $settings['host'] . ';dbname=' . $settings['name'] . ';charset=utf8mb4',
        $settings['user'],
        $settings['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    error_log('OPD database connection failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Service temporarily unavailable. Please try again later.');
}

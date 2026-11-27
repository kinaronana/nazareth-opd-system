<?php
declare(strict_types=1);

/** Generate a structured draft; never make a diagnosis or treatment decision. */
function generateDoctorAIDraft(string $type, string $source): string
{
    $allowed = ['History Summary', 'Consultation Note', 'Follow-up Summary'];
    if (!in_array($type, $allowed, true)) {
        throw new InvalidArgumentException('Unsupported draft type.');
    }
    $source = trim($source);
    if ($source === '' || strlen($source) > 12000) {
        throw new InvalidArgumentException('Provide source notes of up to 12,000 characters.');
    }
    $key = trim((string)(getenv('GEMINI_API_KEY') ?: ''));
    if ($key === '' || !function_exists('curl_init')) {
        return localDoctorDraft($type, $source);
    }
    $model = trim((string)(getenv('GEMINI_MODEL') ?: 'gemini-3.5-flash-lite'));
    if (!preg_match('/^[A-Za-z0-9._-]{1,100}$/', $model)) return localDoctorDraft($type, $source);
    // CI can point this service at its isolated local Gemini mock. Production
    // continues to use Google's HTTPS Gemini API unless an endpoint is explicitly set.
    $endpoint = trim((string)(getenv('OPD_GEMINI_API_URL') ?: ''));
    if ($endpoint === '') {
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
    }
    if (!filter_var($endpoint, FILTER_VALIDATE_URL)) return localDoctorDraft($type, $source);
    $scheme = (string)parse_url($endpoint, PHP_URL_SCHEME);
    $host = (string)parse_url($endpoint, PHP_URL_HOST);
    $isLocalTest = $scheme === 'http' && in_array($host, ['127.0.0.1', 'localhost'], true);
    if ($scheme !== 'https' && !$isLocalTest) return localDoctorDraft($type, $source);
    $instruction = "You are a documentation assistant for a fictional school-project OPD. Organise only the clinician-provided text into a clear {$type}. Do not infer missing facts, diagnose, recommend tests, prescribe or suggest treatment. Preserve uncertainty and label missing information as not provided. Return concise plain text. This is a draft that must be reviewed and edited by the doctor before approval.";
    $payload = [
        'systemInstruction' => ['parts' => [['text' => $instruction]]],
        'contents' => [['role' => 'user', 'parts' => [['text' => $source]]]],
        'generationConfig' => ['maxOutputTokens' => 700],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE']
        ]
    ];
    $ch = curl_init($endpoint);
    if (!$ch) return localDoctorDraft($type, $source);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_errno($ch);
    curl_close($ch);
    if (!is_string($raw) || $error || $status < 200 || $status >= 300) return localDoctorDraft($type, $source);
    $data = json_decode($raw, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!is_string($text) || trim($text) === '') return localDoctorDraft($type, $source);
    $text = trim(strip_tags($text));
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    return substr($text, 0, 10000) ?: localDoctorDraft($type, $source);
}

function localDoctorDraft(string $type, string $source): string
{
    return $type . "\n\nClinician-provided information (organised without clinical interpretation):\n" . trim($source)
        . "\n\nReview checklist: verify accuracy, add any missing information, and edit this draft before approval.";
}

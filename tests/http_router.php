<?php
declare(strict_types=1);

/** CI-only session bootstrap and Gemini mock for the disposable localhost server. */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path === '/__test/session') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $sid = (string)($input['sid'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $sid)) { http_response_code(400); exit('Invalid test session identifier.'); }
    session_id($sid);
    session_start();
    $_SESSION = [
        'user_id' => (int)($input['user_id'] ?? 0),
        'role_name' => (string)($input['role'] ?? ''),
        'queue_csrf' => 'ci-valid-csrf-token',
        'username' => 'CI ' . (string)($input['role'] ?? '') . ' user',
    ];
    header('Content-Type: application/json');
    echo json_encode(['ready' => true]);
    exit;
}
if ($path === '/__test/gemini') {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals('ci-mock-gemini-key', (string)($_SERVER['HTTP_X_GOOG_API_KEY'] ?? ''))) {
        http_response_code(401); echo json_encode(['error' => ['message' => 'Mock authentication failed']]); exit;
    }
    $payload = json_decode(file_get_contents('php://input'), true);
    $contents = is_array($payload) ? ($payload['contents'] ?? []) : [];
    $system = (string)($payload['systemInstruction']['parts'][0]['text'] ?? '');
    $isPatientAssistantPrompt = strpos($system, 'Never diagnose') !== false;
    $isDoctorDocumentationPrompt = strpos($system, 'documentation assistant for a fictional school-project OPD') !== false
        && strpos($system, 'Do not infer missing facts, diagnose') !== false;
    if (!is_array($contents) || !$contents || (!$isPatientAssistantPrompt && !$isDoctorDocumentationPrompt)) {
        http_response_code(400); echo json_encode(['error' => ['message' => 'Invalid mock request payload']]); exit;
    }
    $allText = '';
    foreach ($contents as $turn) $allText .= ' ' . (string)($turn['parts'][0]['text'] ?? '');
    $lastText = strtolower((string)($contents[count($contents) - 1]['parts'][0]['text'] ?? ''));
    if ($isDoctorDocumentationPrompt) {
        $reply = "CI MOCK DOCTOR DRAFT: Consultation note organised from clinician-provided fictional information only. No diagnosis or treatment advice added.";
    } elseif (strpos($lastText, 'mock blocked response') !== false) {
        echo json_encode(['candidates' => [['finishReason' => 'SAFETY', 'content' => ['parts' => []]]]]);
        exit;
    } elseif (strpos($lastText, 'mock unsafe response') !== false) {
        $reply = 'You likely have pneumonia. Take 2 tablets of ibuprofen now.';
    } else {
        $reply = count($contents) >= 3 && stripos($allText, 'How do I book an appointment?') !== false
            ? 'You previously asked how to book an appointment. Choose Book Appointment from the patient menu.'
            : (stripos($allText, 'book an appointment') !== false
                ? 'To book an appointment, sign in and choose Book Appointment from the patient menu.'
                : 'I can help you navigate the patient portal. Use fictional details only in this school project.');
    }
    echo json_encode(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => $reply]]]]]]);
    exit;
}
return false;

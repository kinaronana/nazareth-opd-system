<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';
require_once __DIR__ . '/../services/AdminIntelligenceService.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error'=>'Sign in is required.']); exit; }
if ((string)($_SESSION['role_name']??'') !== 'Admin') { http_response_code(403); echo json_encode(['error'=>'Administrator access is required.']); exit; }
try { echo json_encode(getAdminIntelligenceData($pdo),JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE); }
catch(Throwable $e) { error_log('Admin intelligence error: '.$e->getMessage()); http_response_code(503); echo json_encode(['error'=>'Operational intelligence is temporarily unavailable.']); }

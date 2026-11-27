<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';
require_once __DIR__ . '/../services/PatientAIService.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
function aiJson(int $status,array $data): void { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
$userId=(int)($_SESSION['user_id']??0);
if (!$userId || ($_SESSION['role_name']??'')!=='Patient') aiJson(403,['error'=>'Patient access is required.']);
$s=$pdo->prepare('SELECT patient_id FROM patients WHERE user_id=? LIMIT 1'); $s->execute([$userId]); $patientId=(int)$s->fetchColumn();
if (!$patientId) aiJson(403,['error'=>'Patient profile not found.']);
if (empty($_SESSION['patient_ai_csrf'])) $_SESSION['patient_ai_csrf']=bin2hex(random_bytes(32));
$csrf=(string)$_SESSION['patient_ai_csrf'];
try {
 if ($_SERVER['REQUEST_METHOD']==='GET') {
  $id=(int)($_GET['conversation_id']??0);
  if ($id) {
   $s=$pdo->prepare('SELECT conversation_id,title,created_at,updated_at FROM patient_ai_conversations WHERE conversation_id=? AND patient_id=? AND user_id=?');
   $s->execute([$id,$patientId,$userId]); $conversation=$s->fetch(PDO::FETCH_ASSOC);
   if (!$conversation) aiJson(404,['error'=>'Conversation not found.']);
   $s=$pdo->prepare('SELECT message_id,sender,message_text,safety_flag,created_at FROM patient_ai_messages WHERE conversation_id=? ORDER BY message_id');
   $s->execute([$id]); aiJson(200,['conversation'=>$conversation,'messages'=>$s->fetchAll(PDO::FETCH_ASSOC),'csrf'=>$csrf]);
  }
  $s=$pdo->prepare('SELECT conversation_id,title,updated_at FROM patient_ai_conversations WHERE patient_id=? AND user_id=? ORDER BY updated_at DESC LIMIT 50');
  $s->execute([$patientId,$userId]); aiJson(200,['conversations'=>$s->fetchAll(PDO::FETCH_ASSOC),'csrf'=>$csrf]);
 }
 if ($_SERVER['REQUEST_METHOD']!=='POST') { header('Allow: GET, POST'); aiJson(405,['error'=>'Method not allowed.']); }
 $input=json_decode(file_get_contents('php://input'),true);
 if (!is_array($input)||!hash_equals($csrf,(string)($input['csrf']??''))) aiJson(419,['error'=>'Security token expired. Refresh the page and try again.']);
 $action=(string)($input['action']??'');
 if ($action==='create') {
  $s=$pdo->prepare("INSERT INTO patient_ai_conversations(patient_id,user_id,title) VALUES(?,?,'New conversation')");
  $s->execute([$patientId,$userId]); aiJson(201,['conversation_id'=>(int)$pdo->lastInsertId()]);
 }
 $id=(int)($input['conversation_id']??0);
 $s=$pdo->prepare('SELECT conversation_id FROM patient_ai_conversations WHERE conversation_id=? AND patient_id=? AND user_id=?');
 $s->execute([$id,$patientId,$userId]); if (!$id||!$s->fetchColumn()) aiJson(404,['error'=>'Conversation not found.']);
 if ($action==='delete') { $s=$pdo->prepare('DELETE FROM patient_ai_conversations WHERE conversation_id=? AND patient_id=? AND user_id=?'); $s->execute([$id,$patientId,$userId]); aiJson(200,['message'=>'Conversation deleted.']); }
 if ($action!=='send') aiJson(422,['error'=>'Unsupported action.']);
 $message=trim((string)($input['message']??''));
 if ($message===''||strlen($message)>2000) aiJson(422,['error'=>'Enter a message of up to 2,000 characters.']);
 $now=time(); $_SESSION['patient_ai_recent_sends']=array_values(array_filter($_SESSION['patient_ai_recent_sends']??[],static fn($t)=>is_int($t)&&$t>$now-60));
 if (count($_SESSION['patient_ai_recent_sends'])>=12) aiJson(429,['error'=>'Please wait before sending another message.']);
 $_SESSION['patient_ai_recent_sends'][]=$now;

 // Fetch only the authenticated owner's recent conversation history.
 $s=$pdo->prepare('SELECT sender,message_text FROM patient_ai_messages WHERE conversation_id=? ORDER BY message_id DESC LIMIT 12');
 $s->execute([$id]); $history=array_reverse($s->fetchAll(PDO::FETCH_ASSOC));
 $reply=generatePatientSupportResponse($message,$history); $pdo->beginTransaction();
 try {
  $s=$pdo->prepare("INSERT INTO patient_ai_messages(conversation_id,sender,message_text) VALUES(?,'patient',?)"); $s->execute([$id,$message]);
  $s=$pdo->prepare("INSERT INTO patient_ai_messages(conversation_id,sender,message_text,safety_flag) VALUES(?,'assistant',?,?)"); $s->execute([$id,$reply['text'],$reply['safety_flag']]);
  $title=substr($message,0,110); $s=$pdo->prepare("UPDATE patient_ai_conversations SET title=CASE WHEN title='New conversation' THEN ? ELSE title END,updated_at=CURRENT_TIMESTAMP WHERE conversation_id=? AND patient_id=? AND user_id=?"); $s->execute([$title,$id,$patientId,$userId]);
  $pdo->commit();
 } catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
 aiJson(200,['reply'=>$reply['text'],'safety_flag'=>$reply['safety_flag']]);
} catch(Throwable $e) { error_log('Patient support assistant request failed.'); aiJson(500,['error'=>'The patient support assistant is temporarily unavailable.']); }

<?php
declare(strict_types=1);
require_once __DIR__ . '/AppointmentPredictionService.php';

function buildAdminOperationalAlerts(array $m): array {
 $alerts=[];
 if (($m['appointments']['pending_today']??0)>=10) $alerts[]=['level'=>'warning','title'=>'Appointment backlog','detail'=>'There are '.$m['appointments']['pending_today'].' pending bookings scheduled today. Review the booking backlog.'];
 if (($m['queue']['waiting']??0)>=8) $alerts[]=['level'=>'warning','title'=>'Queue pressure','detail'=>'The current waiting queue has '.$m['queue']['waiting'].' people. Review staffing and flow.'];
 if (empty($m['data_quality']['queue_available'])) $alerts[]=['level'=>'info','title'=>'Queue data unavailable','detail'=>'The queue table is not accessible. Apply the approved queue schema before relying on queue insights.'];
 if (!$alerts) $alerts[]=['level'=>'success','title'=>'No threshold alerts','detail'=>'No configured operational alert thresholds are currently triggered. Continue routine monitoring.'];
 return $alerts;
}
function buildLocalAdminManagementSummary(array $m): string {
 $a=$m['appointments']; $q=$m['queue'];
 $s='Today: '.$a['total_today'].' appointments, including '.$a['pending_today'].' pending, '.$a['approved_today'].' approved and '.$a['completed_today'].' completed. ';
 $s.='Patient activity: '.$m['patient_activity']['unique_patients_7d'].' distinct patients had appointment activity in the last seven days. ';
 $s.=!empty($m['data_quality']['queue_available'])?'Queue: '.$q['waiting'].' waiting, '.$q['called'].' called and '.$q['in_consultation'].' in consultation. ':'Queue figures are unavailable because the queue schema is not accessible. ';
 $s.='Highest doctor appointment load today: '.$m['workload']['highest_doctor_appointments_today'].' appointments. ';
 $s.=($m['forecast']['confidence']??'Insufficient data')==='Insufficient data'?'Historical coverage is limited; forecasts should not guide capacity decisions yet.':'The '.$m['forecast']['horizon_days'].'-day demand forecast is '.$m['forecast']['expected_appointments'].' appointments (an estimate, not a confirmed schedule).';
 return $s;
}
function generateAdminManagementSummary(array $m): array {
 $fallback=buildLocalAdminManagementSummary($m); $key=trim((string)(getenv('GEMINI_API_KEY')?:''));
 if ($key===''||!function_exists('curl_init')) return ['text'=>$fallback,'provider'=>'local'];
 $model=trim((string)(getenv('GEMINI_MODEL')?:'gemini-3.5-flash-lite'));
 if (!preg_match('/^[A-Za-z0-9._-]{1,100}$/',$model)) return ['text'=>$fallback,'provider'=>'local'];
 $url=trim((string)(getenv('OPD_GEMINI_API_URL')?:''));
 if ($url==='') $url='https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent';
 if (!filter_var($url,FILTER_VALIDATE_URL)) return ['text'=>$fallback,'provider'=>'local'];
 $scheme=parse_url($url,PHP_URL_SCHEME); $host=parse_url($url,PHP_URL_HOST);
 if ($scheme!=='https'&&!($scheme==='http'&&in_array($host,['127.0.0.1','localhost'],true))) return ['text'=>$fallback,'provider'=>'local'];
 $aggregate=['appointments'=>$m['appointments'],'patient_activity'=>$m['patient_activity'],'queue'=>$m['queue'],'workload'=>$m['workload'],'forecast'=>$m['forecast'],'alerts'=>array_map(static fn($a)=>['level'=>$a['level'],'title'=>$a['title']],$m['alerts'])];
 $payload=['systemInstruction'=>['parts'=>[['text'=>'You summarise operations for a fictional outpatient department. Use only supplied aggregate figures. Do not infer clinical meaning, diagnose, recommend treatment, assign urgency, invent causes or claim forecasts are certain. Give administrative suggestions only; label forecasts estimates and mention missing/sparse data.']]],'contents'=>[['role'=>'user','parts'=>[['text'=>json_encode($aggregate,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)]]]],'generationConfig'=>['maxOutputTokens'=>300,'temperature'=>0.2]];
 $body=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); if (!is_string($body)) return ['text'=>$fallback,'provider'=>'local'];
 $ch=curl_init($url); if (!$ch) return ['text'=>$fallback,'provider'=>'local'];
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','x-goog-api-key: '.$key],CURLOPT_POSTFIELDS=>$body,CURLOPT_MAXREDIRS=>0,CURLOPT_FOLLOWLOCATION=>false]);
 $raw=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_errno($ch); curl_close($ch);
 if (!is_string($raw)||$err!==0||$status<200||$status>=300) return ['text'=>$fallback,'provider'=>'local'];
 $d=json_decode($raw,true); $text=$d['candidates'][0]['content']['parts'][0]['text']??'';
 if (!is_string($text)||trim($text)===''||!empty($d['promptFeedback']['blockReason'])) return ['text'=>$fallback,'provider'=>'local'];
 $text=trim(strip_tags($text)); $text=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',$text)??'';
 $text=function_exists('mb_substr')?mb_substr($text,0,1800):substr($text,0,1800);
 return $text!==''?['text'=>$text,'provider'=>'gemini']:['text'=>$fallback,'provider'=>'local'];
}
function getAdminIntelligenceData(PDO $pdo): array {
 $a=$pdo->query("SELECT COUNT(*) total_today,COALESCE(SUM(status='Pending'),0) pending_today,COALESCE(SUM(status='Approved'),0) approved_today,COALESCE(SUM(status='Completed'),0) completed_today,COALESCE(SUM(status='Cancelled'),0) cancelled_today FROM appointments WHERE appointment_date=CURDATE()")->fetch(PDO::FETCH_ASSOC)?:[];
 $activity=(int)$pdo->query("SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE appointment_date>=DATE_SUB(CURDATE(),INTERVAL 7 DAY)")->fetchColumn();
 $doctors=(int)$pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
 $highest=(int)$pdo->query("SELECT COALESCE(MAX(n),0) FROM (SELECT COUNT(*) n FROM appointments WHERE appointment_date=CURDATE() GROUP BY doctor_id) loads")->fetchColumn();
 $q=['waiting'=>0,'called'=>0,'in_consultation'=>0,'completed_today'=>0,'total_today'=>0]; $queueAvailable=true;
 try {$row=$pdo->query("SELECT COALESCE(SUM(status='Waiting'),0) waiting,COALESCE(SUM(status='Called'),0) called,COALESCE(SUM(status='In Consultation'),0) in_consultation,COALESCE(SUM(status='Completed'),0) completed_today,COUNT(*) total_today FROM patient_queue WHERE queue_date=CURDATE()")->fetch(PDO::FETCH_ASSOC)?:[]; foreach($q as $k=>$v)$q[$k]=(int)($row[$k]??0);} catch(Throwable $e){$queueAvailable=false;}
 $f=['horizon_days'=>7,'expected_appointments'=>0.0,'estimated_cancellations'=>0.0,'confidence'=>'Insufficient data','observed_days'=>0,'busy_periods'=>[]];
 try {$p=getAppointmentPredictions($pdo,7);$f=['horizon_days'=>7,'expected_appointments'=>(float)($p['summary']['expected_appointments']??0),'estimated_cancellations'=>(float)($p['summary']['estimated_cancellations']??0),'confidence'=>(string)($p['data_quality']['confidence']??'Insufficient data'),'observed_days'=>(int)($p['data_quality']['observed_days']??0),'busy_periods'=>array_slice(array_map(static fn($x)=>['time_slot'=>(string)$x['time_slot'],'average_per_day'=>(float)$x['average_appointments_per_observed_day']],$p['busy_periods']??[]),0,3)];} catch(Throwable $e){}
 $m=['generated_at'=>date(DATE_ATOM),'appointments'=>['total_today'=>(int)($a['total_today']??0),'pending_today'=>(int)($a['pending_today']??0),'approved_today'=>(int)($a['approved_today']??0),'completed_today'=>(int)($a['completed_today']??0),'cancelled_today'=>(int)($a['cancelled_today']??0)],'patient_activity'=>['unique_patients_7d'=>$activity],'queue'=>$q,'workload'=>['doctors_total'=>$doctors,'highest_doctor_appointments_today'=>$highest],'forecast'=>$f,'data_quality'=>['queue_available'=>$queueAvailable]];
 $m['alerts']=buildAdminOperationalAlerts($m); $summary=generateAdminManagementSummary($m); $m['management_summary']=$summary['text']; $m['summary_provider']=$summary['provider']; return $m;
}

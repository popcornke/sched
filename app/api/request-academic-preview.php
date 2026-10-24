<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/shared/auth.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
function failRequest(int $code,string $message): never {http_response_code($code);echo json_encode(['success'=>false,'message'=>$message]);exit;}
authRequire(false,['ADMIN']);
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')failRequest(405,'POST required');
$body=json_decode(file_get_contents('php://input'),true);
if(!is_array($body)||!is_string($body['save_token']??null)||!authCsrfValid($body['csrf_token']??null))failRequest(403,'Invalid session or CSRF token');
// Read the authenticated user BEFORE switching to the separate solver preview session.
$adminId=(int)$_SESSION['auth_user_id'];
session_write_close();
// PHP retains the BCP_AUTH session ID after session_write_close().
// Explicitly select the separate scheduling-preview cookie rather than relying
// on PHP to discover the correct ID when changing session names mid-request.
$previewSid = $_COOKIE['BCP_SCHED_DEMO'] ?? null;
if (!is_string($previewSid) || !preg_match('/\A[A-Za-z0-9,-]{16,128}\z/D', $previewSid)) {
    failRequest(409, 'Generated preview session cookie is missing. Please generate a new timetable in this browser, without switching localhost and 127.0.0.1.');
}
session_name('BCP_SCHED_DEMO');
session_id($previewSid);
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','path'=>'/']);
if(!session_start())failRequest(500,'Preview session unavailable');
$preview=$_SESSION['bcp_schedule_preview']??null;
$previewHasToken=is_array($preview)&&is_string($preview['token']??null);
$tokenMatches=$previewHasToken&&hash_equals($preview['token'],$body['save_token']);
session_write_close();
if(!$previewHasToken) failRequest(409, 'Generated preview was not found in this browser session. Generate again and send immediately. Keep localhost and 127.0.0.1 consistent.');
if(!$tokenMatches) failRequest(409, 'Generated preview token differs from the last saved generation. Generate again and avoid multiple concurrent preview tabs.');
if(time()-(int)($preview['created_at']??0)>1200)failRequest(409,'Preview expired. Generate again.');
$result=$preview['result']??[];if(($result['success']??false)!==true||($result['audit']['passed']??false)!==true||($result['status']??'')!=='DEMO_PREVIEW_GENERATED'||($result['existing_snapshot_constraints_applied']??false)!==true||!is_array($result['assignments']??null))failRequest(422,'Only audited generated previews can be submitted.');
$program=$preview['program'];$period=$preview['academic_period'];
if(!in_array($program['program_code']??'', ['BSIT','BSOA'],true)||($period['period_status']??'')!=='DEMO')failRequest(403,'This preview flow is only enabled for supported DEMO programs.');
try{$db=authDb();$db->beginTransaction();$p=(int)$program['program_id'];$a=(int)$period['academic_period_id'];
$active=$db->prepare("SELECT batch_id FROM schedule_batches WHERE program_id=? AND academic_period_id=? AND status='ACTIVE' LIMIT 1 FOR UPDATE");$active->execute([$p,$a]);if($active->fetch())throw new RuntimeException('This program already has an active schedule. Use its existing replacement workflow.');
$last=$db->prepare('SELECT request_id,status FROM academic_preview_requests WHERE program_id=? AND academic_period_id=? ORDER BY request_id DESC LIMIT 1 FOR UPDATE');$last->execute([$p,$a]);$previous=$last->fetch();if($previous&&$previous['status']==='PENDING_REVIEW')throw new RuntimeException('There is already a pending Academic Office request for this program.');
$payload=json_encode($preview,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
$q=$db->prepare('INSERT INTO academic_preview_requests (academic_period_id,program_id,submitted_by,payload_json,payload_hash,input_hash) VALUES(?,?,?,?,?,?)');$q->execute([$a,$p,$adminId,$payload,hash('sha256',$payload),(string)$preview['input_hash']]);$id=(int)$db->lastInsertId();$db->commit();
echo json_encode(['success'=>true,'request_id'=>$id,'status'=>'PENDING_REVIEW','message'=>'Preview sent to Academic Office. No schedule batch or meetings have been saved.']);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();failRequest(409,$e->getMessage());}

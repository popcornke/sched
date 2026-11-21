<?php
declare(strict_types=1);
require_once __DIR__.'/exam-global-common.php';
require_once dirname(__DIR__, 2).'/shared/academic-notifications.php';
try {
 exGuard('POST');
 $body=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
 $preview=$_SESSION['bcp_global_exam_preview']??null;
 if(!is_array($preview)||!is_string($body['preview_token']??null)||!hash_equals((string)$preview['token'],(string)$body['preview_token'])||time()-(int)$preview['created_at']>3600) exFail(409,'PREVIEW_EXPIRED','Generate a new school-wide preview.');
 $pdo=getDatabase();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $period=(int)$preview['period_id'];
 $pdo->beginTransaction();
 exGlobalCheckExisting($pdo,$period);
 $q=$pdo->prepare("SELECT request_id FROM academic_exam_requests WHERE academic_period_id=? AND status='PENDING_REVIEW' LIMIT 1 FOR UPDATE");$q->execute([$period]);
 if($q->fetch())exFail(409,'ALREADY_PENDING','This academic period has a pending Academic Office exam approval request.');
 $codes=array_column(exGlobalPrograms($pdo,$period),'program_code');
 if($codes!==array_keys($preview['programs']))exFail(409,'PROGRAMS_CHANGED','Program list changed. Generate again.');
 // Refuse submission when a room has become Unavailable/Maintenance, or when
 // teacher, room windows, section mappings or other academic inputs have changed.
 // This is read-only validation; no exam batch or meeting is saved by Admin.
 $baseline=[];
 foreach($preview['programs'] as $code=>$item){
  $snapshot=exBuildInput($pdo,$period,$preview['dates'],(string)$preview['window'],null,(string)$code);
  if((int)$snapshot['class_batch_id']!==(int)$item['class_batch_id'] || !hash_equals((string)$item['input_hash'],exJsonHash($snapshot))){
   exFail(409,'PREVIEW_STALE',$code.' room availability or scheduling data changed. Regenerate ALL Programs before submitting to Academic Office.');
  }
  $snapshot['existing_exams']=array_merge($snapshot['existing_exams'],$baseline);
  $snapshot['input']['existing_exams']=$snapshot['existing_exams'];
  exValidate($snapshot,$item['result']);
  $baseline=exGlobalSynthetic($snapshot,$item['result']['assignments'],$baseline);
 }
 // Store only server-created audited preview; the browser cannot supply assignments.
 $payload=json_encode($preview,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
 $q=$pdo->prepare('INSERT INTO academic_exam_requests(academic_period_id,submitted_by,payload_json,payload_hash) VALUES(?,?,?,?)');
 $q->execute([$period,(int)$_SESSION['auth_user_id'],$payload,hash('sha256',$payload)]);
 $id=(int)$pdo->lastInsertId();$pdo->commit();academicNotifyReviewers($pdo, 'EXAM', $id, 'School-wide examination timetable');auditLog($pdo,'EXAM_REQUEST_SUBMITTED','EXAM_SCHEDULING','EXAM_REQUEST',(string)$id,'School-wide exam preview submitted for Academic Office review.',['period_id'=>$period]);unset($_SESSION['bcp_global_exam_preview']);
 exReply(200,['success'=>true,'status'=>'PENDING_REVIEW','request_id'=>$id,'message'=>'School-wide exam preview sent to Academic Office. No exam batches or meetings saved.']);
} catch(ExamFailure $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();exReply($e->http,['success'=>false,'status'=>$e->errorCode,'message'=>$e->getMessage()]);}
catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();error_log('Submit global exams: '.$e->getMessage());exReply(500,['success'=>false,'message'=>'Exam submission failed. Check migration 029 and server logs.']);}

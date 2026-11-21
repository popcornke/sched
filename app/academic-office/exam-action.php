<?php
declare(strict_types=1);
require_once __DIR__.'/shared.php';
require_once dirname(__DIR__).'/shared/academic-notifications.php';
require_once dirname(__DIR__).'/modules/04-exam-timetable-generator/exam-global-common.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit('POST required');}
if(!authCsrfValid($_POST['csrf_token']??null)){http_response_code(403);exit('Invalid CSRF');}
$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$decision=(string)($_POST['decision']??'');$remarks=trim((string)($_POST['remarks']??''));
if(!$id||!in_array($decision,['APPROVED','REJECTED'],true)||strlen($remarks)>3000||($decision==='REJECTED'&&$remarks==='')){http_response_code(422);exit('Invalid decision or missing rejection reason');}
$db=acadDb();$actor=(int)$_SESSION['auth_user_id'];$locked=false;$lockName='';
try {
 $q=$db->prepare('SELECT academic_period_id FROM academic_exam_requests WHERE request_id=?');$q->execute([$id]);$periodId=(int)$q->fetchColumn();if(!$periodId)throw new RuntimeException('Exam request not found');
 $lockName='bcp_exam_save_period_'.$periodId;$q=$db->prepare('SELECT GET_LOCK(?,10)');$q->execute([$lockName]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Another exam approval is in progress');$locked=true;
 $db->beginTransaction();
 $q=$db->prepare('SELECT * FROM academic_exam_requests WHERE request_id=? FOR UPDATE');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
 if(!$r||$r['status']!=='PENDING_REVIEW')throw new RuntimeException('Exam request is already finalized');
 if($actor===(int)$r['submitted_by'])throw new RuntimeException('You cannot approve your own exam submission');
 if(!hash_equals((string)$r['payload_hash'],hash('sha256',(string)$r['payload_json'])))throw new RuntimeException('Exam preview integrity check failed');
 if($decision==='REJECTED'){
  $q=$db->prepare("UPDATE academic_exam_requests SET status='REJECTED',reviewed_by=?,reviewed_at=NOW(),remarks=? WHERE request_id=?");$q->execute([$actor,$remarks,$id]);$db->commit();academicNotifyExamDecision($db, $id, (int)$r['submitted_by'], $actor, $decision);auditLog($db, $decision==='REJECTED'?'EXAM_REJECTED':'EXAM_APPROVED', 'EXAM_APPROVAL', 'EXAM_REQUEST', (string)$id, 'Academic Office exam decision committed.', ['decision'=>$decision]);header('Location: history.php?type=exam',true,303);exit;
 }
 $preview=json_decode((string)$r['payload_json'],true,512,JSON_THROW_ON_ERROR);
 if((int)($preview['period_id']??0)!==$periodId||!is_array($preview['programs']??null)||!is_array($preview['dates']??null))throw new RuntimeException('Invalid school-wide preview scope');
 exGlobalCheckExisting($db,$periodId);
 $codes=array_column(exGlobalPrograms($db,$periodId),'program_code');
 if($codes!==array_keys($preview['programs']))throw new RuntimeException('Programs changed since request. Reject and regenerate.');
 $baseline=[];$snapshots=[];
 foreach($preview['programs'] as $code=>$item){
  $snapshot=exBuildInput($db,$periodId,$preview['dates'],(string)$preview['window'],null,(string)$code);
  if((int)$snapshot['class_batch_id']!==(int)$item['class_batch_id']||!hash_equals((string)$item['input_hash'],exJsonHash($snapshot)))throw new RuntimeException($code.' data has changed. Reject and regenerate.');
  $snapshot['existing_exams']=array_merge($snapshot['existing_exams'],$baseline);$snapshot['input']['existing_exams']=$snapshot['existing_exams'];
  exValidate($snapshot,$item['result']);
  $baseline=exGlobalSynthetic($snapshot,$item['result']['assignments'],$baseline);
  $snapshots[$code]=$snapshot;
 }
 $batch=$db->prepare("INSERT INTO exam_batches (academic_period_id,program_id,class_batch_id,exam_label,data_origin,exam_day_1,exam_day_2,exam_day_3,status) VALUES (:period,:program,:class_batch,'DEMO-EXAM','DEMO',:d1,:d2,:d3,'ACTIVE')");
 $meeting=$db->prepare('INSERT INTO exam_meetings (exam_batch_id,section_subject_id,proctor_id,room_id,exam_day,exam_date,start_time,end_time) VALUES (:batch,:subject,:proctor,:room,:day,:date,:start,:end)');
 $total=0;
 foreach($preview['programs'] as $code=>$item){
  $s=$snapshots[$code];$dates=$preview['dates'];$batch->execute(['period'=>$periodId,'program'=>$s['program']['program_id'],'class_batch'=>$s['class_batch_id'],'d1'=>$dates[0],'d2'=>$dates[1],'d3'=>$dates[2]]);$batchId=(int)$db->lastInsertId();
  foreach($item['result']['assignments'] as $row){$meeting->execute(['batch'=>$batchId,'subject'=>$row['section_subject_id'],'proctor'=>$row['proctor_id'],'room'=>$row['room_id'],'day'=>$row['exam_day'],'date'=>$row['exam_date'],'start'=>$row['start_time'],'end'=>$row['end_time']]);$total++;}
 }
 $q=$db->prepare("UPDATE academic_exam_requests SET status='APPROVED',reviewed_by=?,reviewed_at=NOW(),remarks=? WHERE request_id=? AND status='PENDING_REVIEW'");$q->execute([$actor,$remarks,$id]);if($q->rowCount()!==1)throw new RuntimeException('Request status changed');
 $db->commit();academicNotifyExamDecision($db, $id, (int)$r['submitted_by'], $actor, $decision);auditLog($db, $decision==='REJECTED'?'EXAM_REJECTED':'EXAM_APPROVED', 'EXAM_APPROVAL', 'EXAM_REQUEST', (string)$id, 'Academic Office exam decision committed.', ['decision'=>$decision]);header('Location: history.php?type=exam',true,303);exit;
} catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Exam approval failed: '.$e->getMessage());http_response_code(409);acadLayout('Exam Approval Failed');echo '<h1>Exam approval was not saved</h1><p>'.acadH($e->getMessage()).'</p><a class="btn secondary" href="exam-requests.php?id='.(int)$id.'">Return to exam review</a>';acadEnd();}
finally{if($locked){try{$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lockName]);}catch(Throwable $e){error_log($e->getMessage());}}}

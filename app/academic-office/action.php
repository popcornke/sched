<?php
declare(strict_types=1);require_once __DIR__.'/shared.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit('POST required');}
if(!authCsrfValid($_POST['csrf_token']??null)){http_response_code(403);exit('Invalid CSRF token');}
$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$decision=(string)($_POST['decision']??'');$remarks=trim((string)($_POST['remarks']??''));
if(!$id||!in_array($decision,['APPROVED','RETURNED'],true)||strlen($remarks)>3000||($decision==='RETURNED'&&$remarks==='')){http_response_code(422);exit('Invalid approval decision or missing return remarks.');}
$db=acadDb();$actor=(int)$_SESSION['auth_user_id'];
try{$db->beginTransaction();$q=$db->prepare('SELECT * FROM academic_approval_requests WHERE approval_id=? FOR UPDATE');$q->execute([$id]);$a=$q->fetch();if(!$a)throw new RuntimeException('Approval request not found.');
if((int)$a['submitted_by']===$actor)throw new RuntimeException('Cannot approve or publish your own submitted request.');
$type=$a['schedule_type'];$batch=(int)($type==='CLASS'?$a['class_batch_id']:$a['exam_batch_id']);$b=acadBatch($db,$type,$batch);
if(!$b||$b['status']!=='ACTIVE'||(int)$b['program_id']!==(int)$a['program_id']||(int)$b['academic_period_id']!==(int)$a['academic_period_id']||!hash_equals($a['snapshot_hash'],acadFingerprint($db,$type,$batch)))throw new RuntimeException('The saved timetable changed or became inactive. Submission cannot be finalized.');
$expected='PENDING_REVIEW';if($a['status']!==$expected)throw new RuntimeException('This request is no longer in the required review state.');
if($decision==='APPROVED'){
 // Approval makes the exact reviewed batch the official publication atomically.
 $scope=$db->prepare('SELECT publication_id,approval_id FROM academic_schedule_publications WHERE schedule_type=? AND academic_period_id=? AND program_id=? FOR UPDATE');
 $scope->execute([$type,$a['academic_period_id'],$a['program_id']]);$old=$scope->fetch();
 if($old){
   $oldId=(int)$old['approval_id'];
   if($oldId!==$id){$u=$db->prepare("UPDATE academic_approval_requests SET status='SUPERSEDED' WHERE approval_id=? AND status IN('PUBLISHED','APPROVED')");$u->execute([$oldId]);acadLog($db,$oldId,$actor,'SUPERSEDED','Replaced by newly approved request #'.$id);}
   $up=$db->prepare('UPDATE academic_schedule_publications SET approval_id=?,published_by=?,published_at=NOW() WHERE publication_id=?');$up->execute([$id,$actor,$old['publication_id']]);
 }else{
   $up=$db->prepare('INSERT INTO academic_schedule_publications(schedule_type,academic_period_id,program_id,approval_id,published_by) VALUES(?,?,?,?,?)');$up->execute([$type,$a['academic_period_id'],$a['program_id'],$id,$actor]);
 }
 $up=$db->prepare("UPDATE academic_approval_requests SET status='PUBLISHED',reviewed_by=?,reviewed_at=NOW(),reviewer_remarks=? WHERE approval_id=?");$up->execute([$actor,$remarks,$id]);
 acadLog($db,$id,$actor,'APPROVED',$remarks);acadLog($db,$id,$actor,'PUBLISHED','Automatically made official upon Academic Office approval');
}else{
 $up=$db->prepare("UPDATE academic_approval_requests SET status='RETURNED',reviewed_by=?,reviewed_at=NOW(),reviewer_remarks=? WHERE approval_id=?");$up->execute([$actor,$remarks,$id]);
 acadLog($db,$id,$actor,'RETURNED',$remarks);
}
$db->commit();header('Location: review.php?id='.$id,true,303);exit;
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Academic approval: '.$e->getMessage());http_response_code(409);echo '<h1>Decision could not be completed</h1><p>'.acadH($e->getMessage()).'</p><a href="review.php?id='.(int)$id.'">Back to review</a>';}

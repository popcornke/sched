<?php
declare(strict_types=1);
require_once __DIR__.'/shared.php';
require_once dirname(__DIR__).'/config/python.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit('POST required');}
if(!authCsrfValid($_POST['csrf_token']??null)){http_response_code(403);exit('Invalid CSRF');}
$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$decision=(string)($_POST['decision']??'');$remarks=trim((string)($_POST['remarks']??''));
if(!$id||!in_array($decision,['APPROVED','REJECTED'],true)||strlen($remarks)>3000||($decision==='REJECTED'&&$remarks==='')){http_response_code(422);exit('Invalid action or missing rejection reason');}
function apiForApproval(string $url,?array $payload=null):array{
 if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL unavailable');
 $ch=curl_init($url);$opt=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>45,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_HTTPHEADER=>['Accept: application/json']];
 if($payload!==null){$opt[CURLOPT_POST]=true;$opt[CURLOPT_HTTPHEADER]=['Content-Type: application/json','Accept: application/json'];$opt[CURLOPT_POSTFIELDS]=json_encode($payload,JSON_THROW_ON_ERROR);}
 curl_setopt_array($ch,$opt);$raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
 if($code!==200||!is_string($raw))throw new RuntimeException('Scheduling validation unavailable; no schedule saved. '.$error);
 $decoded=json_decode($raw,true);if(!is_array($decoded))throw new RuntimeException('Invalid scheduling validation response');return $decoded;
}
function minuteForApproval(string $time):int{
 if(!preg_match('/^(\d{2}):(\d{2})(?::00)?$/',$time,$m))throw new RuntimeException('Invalid meeting time');
 $h=(int)$m[1];$min=(int)$m[2];if($h>23||$min>59)throw new RuntimeException('Invalid meeting time');return $h*60+$min;
}
$db=acadDb();$actor=(int)$_SESSION['auth_user_id'];$locked=false;$lockName='';
try {
 $head=$db->prepare('SELECT academic_period_id FROM academic_preview_requests WHERE request_id=?');$head->execute([$id]);$period=$head->fetchColumn();if(!$period)throw new RuntimeException('Request does not exist');
 $lockName='BCP_SCHED_SAVE_PERIOD_'.(int)$period;
 $l=$db->prepare('SELECT GET_LOCK(?,10)');$l->execute([$lockName]);if((int)$l->fetchColumn()!==1)throw new RuntimeException('Schedule approval is busy. Try again.');$locked=true;
 $db->beginTransaction();
 $q=$db->prepare('SELECT * FROM academic_preview_requests WHERE request_id=? FOR UPDATE');$q->execute([$id]);$r=$q->fetch();if(!$r||$r['status']!=='PENDING_REVIEW')throw new RuntimeException('This request is already finalized');
 if($actor===(int)$r['submitted_by'])throw new RuntimeException('Cannot approve own submission');
 if(!hash_equals($r['payload_hash'],hash('sha256',$r['payload_json'])))throw new RuntimeException('Stored preview integrity check failed');
 if($decision==='REJECTED'){
  $q=$db->prepare("UPDATE academic_preview_requests SET status='REJECTED',reviewed_by=?,reviewed_at=NOW(),reason=? WHERE request_id=?");$q->execute([$actor,$remarks,$id]);$db->commit();header('Location: preview-review.php?id='.$id,true,303);exit;
 }
 $preview=json_decode($r['payload_json'],true,512,JSON_THROW_ON_ERROR);
 $result=$preview['result'];$program=$preview['program'];$periodInfo=$preview['academic_period'];
 if((int)$program['program_id']!==(int)$r['program_id']||(int)$periodInfo['academic_period_id']!==(int)$r['academic_period_id'])throw new RuntimeException('Preview scope mismatch');
 if(($result['status']??'')!=='DEMO_PREVIEW_GENERATED'||($result['audit']['passed']??false)!==true||($result['existing_snapshot_constraints_applied']??false)!==true)throw new RuntimeException('Unaudited preview cannot be saved');
 $active=$db->prepare("SELECT batch_id FROM schedule_batches WHERE academic_period_id=? AND program_id=? AND status='ACTIVE' FOR UPDATE");$active->execute([(int)$r['academic_period_id'],(int)$r['program_id']]);if($active->fetch())throw new RuntimeException('There is already an active saved timetable. Use the existing replacement process.');
 $forwarded=strtolower(trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))[0]));
 $scheme=$forwarded==='https'||(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
 $host=$_SERVER['HTTP_HOST']??'localhost';
 // PHP application URL is resolved from current Academic Office endpoint.
 $inputUrl=$scheme.'://'.$host.rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])),'/').'/api/scheduling-input.php?'.http_build_query(['program'=>$program['program_code'],'academic_year'=>$periodInfo['academic_year'],'semester'=>$periodInfo['semester']]);
 $current=apiForApproval($inputUrl);
 if(($current['success']??false)!==true||($current['status']??'')!=='BASIC_INPUT_READY'||($current['data_origin']??'')!=='DEMO'||(int)($current['program']['program_id']??-1)!==(int)$r['program_id']||(int)($current['academic_period']['academic_period_id']??-1)!==(int)$r['academic_period_id'])throw new RuntimeException('Current scheduling input is not ready');
 $fresh=$current['scheduling_input'];$hash=hash('sha256',json_encode($fresh,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR));
 if(!hash_equals($r['input_hash'],$hash))throw new RuntimeException('Scheduling input changed after submission. Reject and generate a new preview.');
 $audit=apiForApproval(pythonBaseUrl().'/api/schedules/audit',['input'=>$current,'result'=>$result]);
 if(($audit['passed']??false)!==true||($audit['status']??'')!=='AUDIT_PASSED'||(int)($audit['required_meetings']??-1)!==(int)($result['required_meetings']??-2)||(int)($audit['returned_meetings']??-1)!==count($result['assignments']??[]))throw new RuntimeException('Independent timetable audit failed. No schedule saved.');
 $saved=$db->prepare("SELECT m.meeting_id,m.teacher_id,m.room_id,m.day_of_week,m.start_time,m.end_time,ss.section_id,ss.subject_id FROM schedule_meetings m JOIN schedule_batches b ON b.batch_id=m.batch_id JOIN section_subjects ss ON ss.section_subject_id=m.section_subject_id WHERE b.academic_period_id=? AND b.status='ACTIVE' FOR UPDATE");$saved->execute([(int)$r['academic_period_id']]);$existing=$saved->fetchAll(PDO::FETCH_ASSOC);
 if(count($existing)!==count($fresh['existing_meetings']))throw new RuntimeException('Other active schedules changed. Generate again.');
 $map=[];foreach($fresh['section_subjects'] as $sub){$k=(int)$sub['section_id'].':'.(int)$sub['subject_id'];if(isset($map[$k]))throw new RuntimeException('Duplicate section-subject mapping');$map[$k]=(int)$sub['section_subject_id'];}
 $meetings=[];$used=[];
 foreach($result['assignments'] as $m){
  $sec=(int)$m['section_id'];$sub=(int)$m['subject_id'];$k=$sec.':'.$sub;if(!isset($map[$k]))throw new RuntimeException('Invalid section-subject mapping');
  $mode=(string)$m['delivery_mode'];$day=(string)$m['day_of_week'];$teacher=(int)$m['teacher_id'];$room=$m['room_id']===null?null:(int)$m['room_id'];$start=minuteForApproval((string)$m['start_time']);$end=minuteForApproval((string)$m['end_time']);
  if($start>=$end)throw new RuntimeException('Invalid meeting interval');
  $unique=$map[$k].':'.$mode;if(isset($used[$unique]))throw new RuntimeException('Duplicate section meeting');$used[$unique]=true;
  foreach($existing as $old){if($day!==$old['day_of_week'])continue;$os=minuteForApproval((string)$old['start_time']);$oe=minuteForApproval((string)$old['end_time']);if($start>=$oe||$os>=$end)continue;
   if($teacher===(int)$old['teacher_id']||$sec===(int)$old['section_id']||$sub===(int)$old['subject_id']||($room!==null&&$old['room_id']!==null&&$room===(int)$old['room_id']))throw new RuntimeException('Conflict with existing timetable. No schedule saved.');
  }
  $meetings[]=['ss'=>$map[$k],'teacher'=>$teacher,'room'=>$room,'mode'=>$mode,'day'=>$day,'start'=>sprintf('%02d:%02d:00',intdiv($start,60),$start%60),'end'=>sprintf('%02d:%02d:00',intdiv($end,60),$end%60)];
 }
 if(count($meetings)!==(int)$result['required_meetings'])throw new RuntimeException('Incomplete timetable');
 $batch=$db->prepare("INSERT INTO schedule_batches(academic_period_id,program_id,data_origin,status) VALUES (?,?,'DEMO','ACTIVE')");$batch->execute([(int)$r['academic_period_id'],(int)$r['program_id']]);$batchId=(int)$db->lastInsertId();
 $insert=$db->prepare('INSERT INTO schedule_meetings (batch_id,section_subject_id,teacher_id,room_id,delivery_mode,day_of_week,start_time,end_time) VALUES (?,?,?,?,?,?,?,?)');
 foreach($meetings as $m)$insert->execute([$batchId,$m['ss'],$m['teacher'],$m['room'],$m['mode'],$m['day'],$m['start'],$m['end']]);
 $count=$db->prepare('SELECT COUNT(*) FROM schedule_meetings WHERE batch_id=?');$count->execute([$batchId]);if((int)$count->fetchColumn()!==count($meetings))throw new RuntimeException('Saved meeting count mismatch');
 $up=$db->prepare("UPDATE academic_preview_requests SET status='APPROVED',reviewed_by=?,reviewed_at=NOW(),reason=?,batch_id=? WHERE request_id=? AND status='PENDING_REVIEW'");$up->execute([$actor,$remarks,$batchId,$id]);if($up->rowCount()!==1)throw new RuntimeException('Approval state changed');
 $db->commit();header('Location: preview-review.php?id='.$id,true,303);exit;
} catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Academic preview approval failed: '.$e->getMessage());http_response_code(409);echo '<h1>Approval could not be completed</h1><p>'.acadH($e->getMessage()).'</p><p>No new schedule is saved if this approval transaction failed.</p><a href="preview-review.php?id='.(int)$id.'">Back to review</a>';}
finally{if($locked){try{$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lockName]);}catch(Throwable $e){error_log($e->getMessage());}}}

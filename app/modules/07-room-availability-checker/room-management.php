<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/shared/auth.php';
authRequire(false,['ADMIN']);
require_once dirname(__DIR__,2).'/config/database.php';
$db=getDatabase();
function eh(mixed $s):string{return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$message='';$error='';
$rooms=$db->query("SELECT room_id,room_name,program_id,capacity,room_type,status FROM rooms WHERE data_origin='DEMO' ORDER BY room_name")->fetchAll();
$selected=(int)($_POST['room_id']??$_GET['room_id']??0);
if(!$selected&&$rooms)$selected=(int)$rooms[0]['room_id'];
$roomIndex=[];foreach($rooms as $room)$roomIndex[(int)$room['room_id']]=$room;
$periods=$db->query("SELECT academic_period_id,academic_year,semester FROM academic_periods WHERE period_status='DEMO' ORDER BY academic_period_id DESC")->fetchAll();
$period=(int)($_POST['period_id']??$_GET['period_id']??($periods[0]['academic_period_id']??0));
$embedded=($_GET['embed']??$_POST['embed']??'')==='1';
$chosenStatus=(string)($_POST['status']??$_GET['status']??'');
// Room maintenance restoration is tracked per saved meeting, not inferred from current assignment.
$maintenanceTable='room_maintenance_moves';
$maintenanceHistory=[];
try {
 $historyQ=$db->prepare("SELECT mm.meeting_id,mm.original_room_id,mm.temporary_room_id,mm.academic_period_id,mm.section_id,s.section_code,r.room_name AS temporary_name FROM room_maintenance_moves mm JOIN sections s ON s.section_id=mm.section_id JOIN rooms r ON r.room_id=mm.temporary_room_id WHERE mm.original_room_id=? AND mm.restored_at IS NULL ORDER BY s.section_code,mm.meeting_id");
 $historyQ->execute([$selected]);$maintenanceHistory=$historyQ->fetchAll();
} catch(PDOException $e) { $maintenanceHistory=[]; }

$affected=[];$suggestions=[];$sectionMeetings=[];
if(isset($roomIndex[$selected])&&$period>0){
 $q=$db->prepare("SELECT m.meeting_id,m.room_id,m.day_of_week,m.start_time,m.end_time,ss.section_id,s.section_code,s.student_count,b.program_id,b.batch_id FROM schedule_meetings m JOIN schedule_batches b ON b.batch_id=m.batch_id JOIN section_subjects ss ON ss.section_subject_id=m.section_subject_id JOIN sections s ON s.section_id=ss.section_id WHERE b.status='ACTIVE' AND b.academic_period_id=? AND m.room_id=? ORDER BY s.section_code,m.day_of_week,m.start_time");
 // student_count may not exist on all schema versions; use section population from students instead.
 try{$q->execute([$period,$selected]);$affected=$q->fetchAll();}
 catch(PDOException $e){$q=$db->prepare("SELECT m.meeting_id,m.room_id,m.day_of_week,m.start_time,m.end_time,ss.section_id,s.section_code,0 AS student_count,b.program_id,b.batch_id FROM schedule_meetings m JOIN schedule_batches b ON b.batch_id=m.batch_id JOIN section_subjects ss ON ss.section_subject_id=m.section_subject_id JOIN sections s ON s.section_id=ss.section_id WHERE b.status='ACTIVE' AND b.academic_period_id=? AND m.room_id=? ORDER BY s.section_code,m.day_of_week,m.start_time");$q->execute([$period,$selected]);$affected=$q->fetchAll();}
 $occupiedQ=$db->prepare("SELECT m.room_id,m.day_of_week,m.start_time,m.end_time FROM schedule_meetings m JOIN schedule_batches b ON b.batch_id=m.batch_id WHERE b.status='ACTIVE' AND b.academic_period_id=? AND m.room_id IS NOT NULL");$occupiedQ->execute([$period]);$occupied=$occupiedQ->fetchAll();
 $rulesQ=$db->prepare("SELECT room_id,day_of_week,start_time,end_time,availability_status FROM room_availability WHERE academic_period_id=? AND data_origin='DEMO'");$rulesQ->execute([$period]);$rules=$rulesQ->fetchAll();
 $bySection=[];foreach($affected as $m)$bySection[(int)$m['section_id']]['meetings'][]=$m;
 $sectionMeetings=$bySection;
 foreach($bySection as $sectionId=>$record){
  $meetings=$record['meetings'];$example=$meetings[0];$options=[];
  foreach($rooms as $candidate){
   $rid=(int)$candidate['room_id'];if($rid===$selected||$candidate['status']!=='AVAILABLE')continue;
   if($candidate['program_id']!==null&&(int)$candidate['program_id']!==(int)$example['program_id'])continue;
   if((int)$candidate['capacity']<(int)$example['student_count'])continue;
   if($candidate['room_type']!==$roomIndex[$selected]['room_type'])continue;
   $ok=true;
   foreach($meetings as $m){
    $start=substr($m['start_time'],0,5);$end=substr($m['end_time'],0,5);$day=$m['day_of_week'];$windows=[];
    foreach($rules as $rule){if((int)$rule['room_id']!==$rid||$rule['day_of_week']!==$day)continue;$a=substr($rule['start_time'],0,5);$b=substr($rule['end_time'],0,5);if($rule['availability_status']!=='AVAILABLE'&&$start<$b&&$a<$end){$ok=false;break;}if($rule['availability_status']==='AVAILABLE')$windows[]=[$a,$b];}
    if(!$ok)break;
    usort($windows,static fn($a,$b)=>strcmp($a[0],$b[0]));$until=$start;foreach($windows as [$a,$b]){if($a>$until)break;if($b>$until)$until=$b;}
    if($until<$end){$ok=false;break;}
    foreach($occupied as $booking){if((int)$booking['room_id']===$rid&&$booking['day_of_week']===$day&&$start<substr($booking['end_time'],0,5)&&substr($booking['start_time'],0,5)<$end){$ok=false;break;}}
    if(!$ok)break;
   }
   if($ok)$options[$rid]=$candidate['room_name'];
  }
  $suggestions[]=['id'=>$sectionId,'section'=>$example['section_code'],'meetings'=>count($meetings),'options'=>$options];
 }
}
// Apply selections and room status together; never strand an active section in a closed room.
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
 try{
  if(!authCsrfValid($_POST['csrf_token']??null))throw new RuntimeException('Invalid security token. Refresh and try again.');
  $status=(string)($_POST['status']??'');
  if(!in_array($status,['AVAILABLE','UNAVAILABLE','MAINTENANCE'],true)||!isset($roomIndex[$selected]))throw new RuntimeException('Invalid room status.');
  // Maintenance must be restored rather than silently converted into another closure.
  if(($roomIndex[$selected]['status']??'')==='MAINTENANCE' && $status==='UNAVAILABLE')throw new RuntimeException('Restore temporary maintenance assignments first, then set the room unavailable if needed.');
  if(($roomIndex[$selected]['status']??'')==='MAINTENANCE' && $status==='MAINTENANCE')throw new RuntimeException('Room is already under maintenance.');
  $needsMove=$status!=='AVAILABLE' && count($suggestions)>0;
  $choices=is_array($_POST['relocations']??null)?$_POST['relocations']:[];
  $moves=[];
  if($needsMove){
   foreach($suggestions as $entry){
    $sid=(int)$entry['id'];$target=filter_var($choices[$sid]??null,FILTER_VALIDATE_INT);
    if(!$target||!array_key_exists((int)$target,$entry['options']))throw new RuntimeException('Please select a verified replacement room for Section '.$entry['section'].'.');
    $moves[$sid]=(int)$target;
   }
  }
  // Two different sections cannot be moved into the same room during overlapping meeting hours.
  foreach($moves as $sid=>$target){foreach($moves as $otherSid=>$otherTarget){if($sid >= $otherSid||$target!==$otherTarget)continue;
   foreach($sectionMeetings[$sid]['meetings'] as $m){foreach($sectionMeetings[$otherSid]['meetings'] as $other){
    if($m['day_of_week']===$other['day_of_week']&&$m['start_time']<$other['end_time']&&$other['start_time']<$m['end_time'])throw new RuntimeException('Two selected sections overlap in '.$roomIndex[$target]['room_name'].'. Choose a different room.');
   }}
  }}
  $db->beginTransaction();
  // Locks force concurrent relocations through this endpoint to see current room/meeting state.
  $lockRooms=$db->query("SELECT room_id,status FROM rooms WHERE data_origin='DEMO' ORDER BY room_id FOR UPDATE")->fetchAll();
  $lockedStatus=[];foreach($lockRooms as $r)$lockedStatus[(int)$r['room_id']]=$r['status'];
  if(!isset($lockedStatus[$selected]))throw new RuntimeException('Room no longer exists.');
  if($lockedStatus[$selected]!==($roomIndex[$selected]['status']??null))throw new RuntimeException('Room status changed during review. Reload and try again.');
  foreach($moves as $target)if(($lockedStatus[$target]??'')!=='AVAILABLE')throw new RuntimeException('A replacement room is no longer available. Refresh and try again.');
  $lock=$db->prepare("SELECT m.meeting_id,m.room_id,m.day_of_week,m.start_time,m.end_time,ss.section_id FROM schedule_meetings m JOIN schedule_batches b ON b.batch_id=m.batch_id JOIN section_subjects ss ON ss.section_subject_id=m.section_subject_id WHERE b.status='ACTIVE' AND b.academic_period_id=? AND m.room_id IS NOT NULL ORDER BY m.meeting_id FOR UPDATE");
  $lock->execute([$period]);$current=$lock->fetchAll();
  $source=[];foreach($current as $m)if((int)$m['room_id']===$selected)$source[(int)$m['section_id']][]=$m;
  if($needsMove&&count($source)!==count($moves))throw new RuntimeException('The affected sections changed. Reload the modal and choose rooms again.');
  if($status!=='AVAILABLE' && count($source)>0 && !$moves)throw new RuntimeException('Relocate all active sections before closing this room.');
  foreach(($needsMove?$source:[]) as $sid=>$meetings){
   if(!isset($moves[$sid]))throw new RuntimeException('A section was not assigned a replacement room.');
   if(count($meetings)!==count($sectionMeetings[$sid]['meetings']??[]))throw new RuntimeException('Schedule changed while reviewing. Reload the modal.');
  }
  // Restore original rooms when maintenance ends. All checks and writes stay in ONE transaction.
  $restored=0;
  if($status==='AVAILABLE' && $lockedStatus[$selected]==='MAINTENANCE'){
   $h=$db->prepare("SELECT meeting_id,academic_period_id,section_id,temporary_room_id FROM room_maintenance_moves WHERE original_room_id=? AND restored_at IS NULL ORDER BY meeting_id FOR UPDATE");
   $h->execute([$selected]);$hist=$h->fetchAll();
   if($hist){
    $ids=array_map(static fn($r)=>(int)$r['meeting_id'],$hist);
    $placeholders=implode(',',array_fill(0,count($ids),'?'));
    $check=$db->prepare("SELECT m.meeting_id,m.room_id,m.day_of_week,m.start_time,m.end_time,b.academic_period_id,b.status,ss.section_id FROM schedule_meetings m JOIN schedule_batches b ON b.batch_id=m.batch_id JOIN section_subjects ss ON ss.section_subject_id=m.section_subject_id WHERE m.meeting_id IN ($placeholders) FOR UPDATE");
    $check->execute($ids);$meetingMap=[];foreach($check->fetchAll() as $row)$meetingMap[(int)$row['meeting_id']]=$row;
    $rulesAll=$db->prepare("SELECT day_of_week,start_time,end_time,availability_status FROM room_availability WHERE room_id=? AND academic_period_id=? FOR UPDATE");
    $bookAll=$db->prepare("SELECT m.meeting_id,m.day_of_week,m.start_time,m.end_time FROM schedule_meetings m JOIN schedule_batches b ON b.batch_id=m.batch_id WHERE b.status='ACTIVE' AND b.academic_period_id=? AND m.room_id=? FOR UPDATE");
    $periodCache=[];
    foreach($hist as $entry){
     $id=(int)$entry['meeting_id'];$periodId=(int)$entry['academic_period_id'];$m=$meetingMap[$id]??null;
     if(!$m || $m['status']!=='ACTIVE' || (int)$m['academic_period_id']!==$periodId || (int)$m['room_id']!==(int)$entry['temporary_room_id'] || (int)$m['section_id']!==(int)$entry['section_id'])throw new RuntimeException('A temporarily relocated class was changed or superseded. Review the saved schedule before restoring the room.');
     if(!isset($periodCache[$periodId])){
      $bookAll->execute([$periodId,$selected]);$books=$bookAll->fetchAll();$bookAll->closeCursor();
      $rulesAll->execute([$selected,$periodId]);$availableRules=$rulesAll->fetchAll();$rulesAll->closeCursor();
      $periodCache[$periodId]=[$books,$availableRules];
     }
     [$books,$availableRules]=$periodCache[$periodId];
     foreach($books as $b){if((int)$b['meeting_id']!==$id && $b['day_of_week']===$m['day_of_week'] && $m['start_time']<$b['end_time'] && $b['start_time']<$m['end_time'])throw new RuntimeException('Cannot restore: original room has an overlapping class. No records were changed.');}
     $start=substr($m['start_time'],0,5);$end=substr($m['end_time'],0,5);$windows=[];
     foreach($availableRules as $r){if($r['day_of_week']!==$m['day_of_week'])continue;$a=substr($r['start_time'],0,5);$b=substr($r['end_time'],0,5);if($r['availability_status']!=='AVAILABLE' && $start<$b && $a<$end)throw new RuntimeException('Cannot restore: original room is blocked in its availability calendar.');if($r['availability_status']==='AVAILABLE')$windows[]=[$a,$b];}
     usort($windows,static fn($a,$b)=>strcmp($a[0],$b[0]));$until=$start;foreach($windows as [$a,$b]){if($a>$until)break;if($b>$until)$until=$b;}
     if($until<$end)throw new RuntimeException('Cannot restore: original room does not cover all class hours.');
    }
    $restoreMeeting=$db->prepare("UPDATE schedule_meetings SET room_id=? WHERE meeting_id=? AND room_id=?");
    $finish=$db->prepare("UPDATE room_maintenance_moves SET restored_at=NOW() WHERE original_room_id=? AND meeting_id=? AND restored_at IS NULL");
    foreach($hist as $entry){$restoreMeeting->execute([$selected,(int)$entry['meeting_id'],(int)$entry['temporary_room_id']]);if($restoreMeeting->rowCount()!==1)throw new RuntimeException('Restoration failed. No changes saved.');$finish->execute([$selected,(int)$entry['meeting_id']]);$restored++;}
   }
  }
  // Check selected targets against all current bookings, including any newly saved batches.
  foreach($moves as $sid=>$target){foreach($source[$sid] as $m){foreach($current as $book){
   if((int)$book['room_id']!==$target)continue;
   if($book['day_of_week']===$m['day_of_week']&&$m['start_time']<$book['end_time']&&$book['start_time']<$m['end_time'])throw new RuntimeException('A selected replacement room now has a conflicting booking. Refresh and choose again.');
  }}}
  // Re-check room availability windows under the transaction, not only at page load.
  $qRules=$db->prepare("SELECT room_id,day_of_week,start_time,end_time,availability_status FROM room_availability WHERE academic_period_id=? AND data_origin='DEMO' FOR UPDATE");$qRules->execute([$period]);$liveRules=$qRules->fetchAll();
  foreach($moves as $sid=>$target){foreach($source[$sid] as $m){
   $start=substr($m['start_time'],0,5);$end=substr($m['end_time'],0,5);$windows=[];
   foreach($liveRules as $r){if((int)$r['room_id']!==$target||$r['day_of_week']!==$m['day_of_week'])continue;
    $x=substr($r['start_time'],0,5);$y=substr($r['end_time'],0,5);
    if($r['availability_status']!=='AVAILABLE'&&$start<$y&&$x<$end)throw new RuntimeException('Replacement room availability changed. Reload and try again.');
    if($r['availability_status']==='AVAILABLE')$windows[]=[$x,$y];
   }
   usort($windows,static fn($x,$y)=>strcmp($x[0],$y[0]));$until=$start;
   foreach($windows as [$x,$y]){if($x>$until)break;if($y>$until)$until=$y;}
   if($until<$end)throw new RuntimeException('Replacement room no longer covers all scheduled hours.');
  }}
  if($status==='MAINTENANCE' && $moves){
   $remember=$db->prepare("INSERT INTO room_maintenance_moves (meeting_id,academic_period_id,section_id,original_room_id,temporary_room_id) VALUES (?,?,?,?,?)");
   foreach($moves as $sid=>$target){foreach($source[$sid] as $m)$remember->execute([(int)$m['meeting_id'],$period,$sid,$selected,$target]);}
  }
  $update=$db->prepare("UPDATE schedule_meetings SET room_id=? WHERE meeting_id=? AND room_id=?");
  $changed=0;foreach($moves as $sid=>$target){foreach($source[$sid] as $m){$update->execute([$target,(int)$m['meeting_id'],$selected]);if($update->rowCount()!==1)throw new RuntimeException('Unable to relocate all meetings. No changes were saved.');$changed++;}}
  $updateRoom=$db->prepare("UPDATE rooms SET status=? WHERE room_id=? AND data_origin='DEMO'");$updateRoom->execute([$status,$selected]);
  $db->commit();
  $message=$restored>0?('Maintenance completed: restored '.$restored.' class meetings to their original room.') : ($changed>0?('Relocated '.$changed.' active meetings across '.count($moves).' sections, then marked room '.$status.'.'):'Room status updated successfully.');
  // Refresh read-only suggestions from the updated DB after PRG.
  $qs=http_build_query(['embed'=>$embedded?'1':'0','room_id'=>$selected,'period_id'=>$period,'success'=>$message]);
  header('Location: room-management.php?'.$qs);exit;
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$error=$e->getMessage();}
}
if(isset($_GET['success']))$message=substr((string)$_GET['success'],0,220);
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Room Management</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--navy:#142a4e;--blue:#1c4ea3;--border:#e4eaf4;--muted:#657694;--bg:#f6f8fc;--shadow:0 5px 18px rgba(20,42,78,.045)}
*{box-sizing:border-box}html,body{margin:0;min-height:100%;font-family:'Plus Jakarta Sans',system-ui,sans-serif;color:var(--navy);font-size:13px}body{background:#fff}button,select{font:inherit}button{cursor:pointer}main{padding:24px;max-width:1200px;margin:auto}.intro{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:20px}.eyebrow{color:var(--blue);font-weight:800;font-size:10px;letter-spacing:.12em;text-transform:uppercase;margin-bottom:7px}.intro h1{font-size:21px;letter-spacing:-.045em;margin:0 0 6px;font-weight:800}.sub{font-size:12px;line-height:1.7;color:var(--muted);margin:0;max-width:700px}.tiny-tag{flex-shrink:0;background:#eff5ff;color:#25529a;border:1px solid #dbe8ff;padding:8px 11px;border-radius:9px;font-weight:700;font-size:11px}.toolbar{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:13px;margin-bottom:18px}.panel{background:#fff;border:1px solid var(--border);border-radius:14px;padding:17px;box-shadow:var(--shadow)}.panel-title{display:flex;align-items:center;gap:8px;margin:0 0 13px;font-size:12px;font-weight:800}.num{display:grid;place-items:center;background:#eaf1ff;border-radius:8px;color:var(--blue);width:26px;height:26px;font-size:12px}.fields{display:flex;align-items:end;flex-wrap:wrap;gap:10px}.field{flex:1;min-width:125px}.field label{display:block;color:#64748b;font-size:10px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;margin-bottom:6px}select{width:100%;min-height:40px;border:1px solid #d9e3f2;border-radius:9px;padding:0 32px 0 11px;color:#263d5f;background:#fff;outline:none}select:focus{border-color:#4e7ad1;box-shadow:0 0 0 3px #dceaff}.btn{border:0;border-radius:9px;min-height:40px;padding:0 15px;font-weight:800;font-size:11px;white-space:nowrap}.btn-primary{background:#174a9b;color:white}.btn-primary:hover{background:#113d84}.btn-secondary{background:#eaf1fd;color:#1c4ea3}.btn:focus-visible{outline:3px solid #90b4f8;outline-offset:2px}.status-inline{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.pill{display:inline-flex;align-items:center;gap:6px;border:1px solid #dbe6f4;background:#f8fafc;border-radius:99px;padding:7px 10px;color:#52708f;font-size:10px;font-weight:800}.pill.available{border-color:#b1ecd0;color:#0b7857;background:#edfff6}.pill.unavailable{border-color:#ffcccc;color:#ae3737;background:#fff4f4}.pill.maintenance{border-color:#f4dcad;color:#996016;background:#fffbeb}.section-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:10px 0 12px}.section-heading h2{font-size:15px;letter-spacing:-.025em;margin:0}.count{border-radius:50px;background:#eef3fb;color:#365a98;font-size:10px;padding:6px 10px;font-weight:800}.info{display:flex;gap:11px;border:1px solid #dbe9ff;background:#f2f7ff;color:#385777;border-radius:12px;padding:13px 15px;font-size:11px;line-height:1.7;margin-bottom:14px}.info-icon{color:#2458aa;font-size:17px;flex:0 0 auto}.section-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.section-card{border:1px solid var(--border);border-radius:13px;padding:15px;background:#fff;min-width:0}.section-card__head{display:flex;justify-content:space-between;gap:10px;align-items:center;border-bottom:1px solid #eef1f7;padding-bottom:11px;margin-bottom:12px}.section-code{font-size:15px;font-weight:800}.meeting-label{font-size:10px;font-weight:700;color:#667894}.candidate-label{font-size:10px;letter-spacing:.04em;text-transform:uppercase;color:#7b8ca7;font-weight:800;margin-bottom:9px}.candidates{display:flex;flex-wrap:wrap;gap:7px}.candidate{display:inline-flex;align-items:center;min-height:29px;background:#f4f7fc;border:1px solid #e1eafa;border-radius:8px;color:#2c538d;font-size:11px;padding:5px 9px;font-weight:700}.empty{border:1px dashed #d5e1ef;background:#f8fafd;border-radius:14px;text-align:center;padding:29px 15px;color:#64748b}.empty strong{display:block;color:var(--navy);font-size:14px;margin-bottom:5px}.empty-inline{font-size:11px;color:#9b5b2c;background:#fff8ea;border:1px solid #f2deba;padding:10px;border-radius:8px}.notice{padding:12px 15px;background:#ebfaf3;border:1px solid #bcebd4;color:#126747;border-radius:10px;margin:0 0 15px;font-size:12px}.error{padding:12px 15px;background:#fff1f1;border:1px solid #ffcbcb;color:#ad3030;border-radius:10px;margin:0 0 15px;font-size:12px}.footnote{font-size:10px;line-height:1.7;color:#8090aa;margin-top:17px;padding-top:13px;border-top:1px solid var(--border)}form{margin:0}.back{display:inline-block;margin-bottom:12px;color:var(--blue);text-decoration:none;font-size:11px}.icon{font-size:14px}.toolbar .muted{font-size:10px;color:#8190a8;line-height:1.5;margin-top:9px}
@media(max-width:850px){main{padding:17px}.toolbar,.section-grid{grid-template-columns:1fr}.intro h1{font-size:19px}}@media(max-width:520px){main{padding:14px}.intro{display:block}.tiny-tag{display:inline-flex;margin-top:10px}.panel{padding:13px}.fields{flex-direction:column;align-items:stretch}.field,.btn{width:100%}.section-heading{align-items:flex-start}.section-card{padding:12px}.intro h1{font-size:18px}}@media(prefers-reduced-motion:no-preference){button,.candidate{transition:background .15s,border-color .15s}} 
.pick{cursor:pointer;position:relative;padding:0;overflow:hidden}.pick input{position:absolute;opacity:0;width:1px;height:1px}.pick span{display:block;padding:9px 12px}.pick:has(input:checked){background:#e7f0ff;border-color:#1c4ea3;color:#143e81;box-shadow:0 0 0 1px #1c4ea3}.pick:has(input:focus-visible){outline:3px solid #80abf5;outline-offset:2px}.btn:disabled{cursor:not-allowed;opacity:.5}.candidate{min-height:36px}.selection-progress{font-size:11px;font-weight:800;color:#365a98;margin-top:10px}
</style></head><body><main>
<?php if(!$embedded):?><a class="back" href="room-availability-checker.php">← Back to Room Availability</a><?php endif;?>
<div class="intro"><div><div class="eyebrow">Room Availability / Management</div><h1>Room Status &amp; Relocation</h1><p class="sub">Update room availability and review compatible vacant rooms for affected classes. For unavailable or maintenance rooms, relocate every affected section before applying the room status.</p></div><span class="tiny-tag">ADMIN CONTROL</span></div>
<?php if($message):?><div class="notice" role="status"><?=eh($message)?></div><?php endif;?><?php if($error):?><div class="error" role="alert"><?=eh($error)?></div><?php endif;?>
<div class="toolbar"><section class="panel"><h2 class="panel-title"><span class="num">01</span> Selected room &amp; academic period</h2><form method="get"><input type="hidden" name="embed" value="<?=$embedded?'1':'0'?>"><div class="fields"><div class="field"><label for="room">Room</label><select id="room" name="room_id"><?php foreach($rooms as $r):?><option value="<?=(int)$r['room_id']?>" <?=$selected===(int)$r['room_id']?'selected':''?>><?=eh($r['room_name'])?></option><?php endforeach;?></select></div><div class="field"><label for="period">Academic period</label><select id="period" name="period_id"><?php foreach($periods as $p):?><option value="<?=(int)$p['academic_period_id']?>" <?=$period===(int)$p['academic_period_id']?'selected':''?>><?=eh($p['academic_year'])?> · S<?=(int)$p['semester']?></option><?php endforeach;?></select></div><button class="btn btn-secondary" type="submit">View room</button></div></form></section>
<section class="panel"><h2 class="panel-title"><span class="num">02</span> Change room status <?php if(isset($roomIndex[$selected])):?><span class="pill <?=strtolower(eh($roomIndex[$selected]['status']))?>"><?=eh($roomIndex[$selected]['status'])?></span><?php endif;?></h2><?php if(isset($roomIndex[$selected])):?><form id="relocationForm" method="post" onsubmit="return confirm('Apply all selected section relocations and update this room status?')"><input type="hidden" name="embed" value="<?=$embedded?'1':'0'?>"><input type="hidden" name="period_id" value="<?=$period?>"><input type="hidden" name="csrf_token" value="<?=eh(authCsrf())?>"><input type="hidden" name="room_id" value="<?=$selected?>"><div class="fields"><div class="field"><label for="status">Set status</label><select id="status" name="status"><?php foreach(['AVAILABLE','UNAVAILABLE','MAINTENANCE'] as $st):?><option value="<?=$st?>" <?=($chosenStatus!==''?$chosenStatus:$roomIndex[$selected]['status'])===$st?'selected':''?>><?=ucfirst(strtolower($st))?></option><?php endforeach;?></select></div><button id="applyRelocation" type="submit" class="btn btn-primary">Apply status &amp; relocate</button></div></form><div class="muted">Maintenance keeps a temporary relocation record. On Set Available, original assignments are restored after conflict checks.</div><?php endif;?></section></div>
<div class="section-heading"><h2>Affected sections &amp; recommended rooms</h2><span class="count"><?=count($affected)?> active meetings · <?=count($suggestions)?> sections</span></div>
<div class="info"><span class="info-icon" aria-hidden="true">ⓘ</span><span>Recommendations check room type, capacity, availability windows, and conflicting meetings across active schedules. All meeting times of each affected section must fit the recommended room. <strong>Choose one room for each section. Changes are saved together only after validation.</strong></span></div>
<?php if($maintenanceHistory && ($roomIndex[$selected]['status']??'')==='MAINTENANCE'):?>
<div class="notice"><strong>Temporary maintenance moves: <?=count($maintenanceHistory)?> meetings.</strong> When you choose <strong>Available</strong>, all saved original room assignments will be restored together if their original time slots are still free. If there is any conflict, nothing is changed.</div>
<?php endif;?>
<?php if(!$suggestions):?><div class="empty"><strong>No affected active classes</strong>There are no active class meetings in this room for the selected academic period.</div><?php else:?><div class="section-grid"><?php foreach($suggestions as $entry):?><article class="section-card"><div class="section-card__head"><div><div class="section-code">Section <?=eh($entry['section'])?></div><div class="meeting-label"><?=(int)$entry['meetings']?> face-to-face meetings need relocation</div></div><span class="pill available">● Active</span></div><div class="candidate-label">Select one compatible vacant room · <?=count($entry['options'])?> found</div><?php if($entry['options']):?><div class="candidates" role="radiogroup" aria-label="Replacement room for section <?=eh($entry['section'])?>"><?php foreach($entry['options'] as $candidateId=>$candidateName):?><label class="candidate pick"><input type="radio" name="relocations[<?=(int)$entry['id']?>]" value="<?=(int)$candidateId?>" form="relocationForm"><span><?=eh($candidateName)?></span></label><?php endforeach;?></div><?php else:?><div class="empty-inline">No verified replacement is available. This room cannot be closed until a suitable room is available.</div><?php endif;?></article><?php endforeach;?></div><?php endif;?>
<div class="footnote">Applying Unavailable or Maintenance updates all affected active meeting room assignments in one database transaction, then closes the original room. Confirm the replacement rooms with the Academic Office before applying.</div>
<script>
(function(){const form=document.getElementById('relocationForm');if(!form)return;
 const select=document.getElementById('status'),button=document.getElementById('applyRelocation');
 const groups=[...document.querySelectorAll('.section-card .candidates')];
 const count=<?=count($suggestions)?>;const noOptions=<?=count(array_filter($suggestions,static fn($e)=>count($e['options'])===0))?>;
 const progress=document.createElement('div');progress.className='selection-progress';form.appendChild(progress);
 function update(){const closing=select.value!=='AVAILABLE';const picked=groups.filter(g=>g.querySelector('input:checked')).length;
  button.disabled=closing&&(noOptions>0||picked!==count);
  button.textContent=closing?'Apply status & relocate':'Set room available';
  progress.textContent=closing?(picked+' / '+count+' sections assigned'+(noOptions?' · Some sections have no verified replacement':'')):(<?=json_encode(($roomIndex[$selected]['status']??'')==='MAINTENANCE')?>?'Restore all original class rooms, then set Available.':'No relocation needed when making the room available.');
 }select.addEventListener('change',update);document.querySelectorAll('.pick input').forEach(el=>el.addEventListener('change',update));update();
})();
</script></main></body></html>
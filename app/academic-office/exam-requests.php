<?php
declare(strict_types=1);
require_once __DIR__.'/shared.php';
$db=acadDb();$historyEmbed=(($_GET['history_embed']??'')==='1');$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
try {
 if($id){$q=$db->prepare('SELECT r.*,ap.academic_year,ap.semester,u.username FROM academic_exam_requests r JOIN academic_periods ap ON ap.academic_period_id=r.academic_period_id JOIN auth_users u ON u.user_id=r.submitted_by WHERE r.request_id=?');$q->execute([$id]);$item=$q->fetch(PDO::FETCH_ASSOC);if(!$item){http_response_code(404);exit('Request not found');}}
 else $rows=$db->query("SELECT r.request_id,r.status,r.submitted_at,r.reviewed_at,ap.academic_year,ap.semester,u.username FROM academic_exam_requests r JOIN academic_periods ap ON ap.academic_period_id=r.academic_period_id JOIN auth_users u ON u.user_id=r.submitted_by WHERE r.status='PENDING_REVIEW' ORDER BY r.request_id DESC LIMIT 150")->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e){http_response_code(503);acadLayout('Exam Approvals');echo '<h1>Exam approval setup needed</h1><p>Import database/migrations/029_academic_exam_approval.sql into bcp_scheduling.</p>';acadEnd();exit;}
if ($id && !$historyEmbed && (string)$item['status'] !== 'PENDING_REVIEW') { header('Location: history.php?type=exam', true, 303); exit; }
if ($id) { require_once dirname(__DIR__) . '/shared/audit.php'; auditLog($db,'EXAM_REQUEST_VIEWED','EXAM_APPROVAL','EXAM_REQUEST',(string)$id,'Academic Office viewed exam request.'); }
if ($historyEmbed && $id) {
    if (!in_array((string)$item['status'], ['APPROVED','REJECTED'], true)) {
        http_response_code(403);exit('This exam request is not in approval history.');
    }
    echo '<!doctype html><html lang="en"><head><link rel="icon" type="image/png" href="../assets/images/BCP_LOGO.png?v=20261121b"><link rel="shortcut icon" type="image/png" href="../assets/images/BCP_LOGO.png?v=20261121b"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Exam History #'.(int)$id.'</title><link rel="stylesheet" href="assets/office.css?v=9"><link rel="stylesheet" href="assets/exam-matrix-review.css?v=1"><style>body{margin:0;background:#f4f6fb;padding:18px;min-width:0}.panel{margin:0 0 14px}.ao-exam-review{padding:15px}.ao-exam-program{margin-bottom:12px}.ao-exam-program-body{overflow:visible}@media print{body{background:white;padding:0}.ao-exam-program{break-inside:avoid}.ao-exam-sheet{break-inside:avoid}.ao-exam-scroll{overflow:visible}.ao-exam-matrix{min-width:0!important}}</style></head><body class="acad-history-embedded">';
} else {
    acadLayout('Exam Approval Requests');
}
?>
<?php if (!$historyEmbed): ?><div class="eyebrow">ACADEMIC OFFICE · EXAMINATION APPROVAL</div><h1>Exam Timetable Approval</h1><p>Pending school-wide exam timetables only. Completed decisions are available in <a href="history.php?type=exam">Approval History → Exam Schedule</a>.</p>
<?php endif; ?>
<?php if(!$id): ?>
<div class="panel scroll"><table><thead><tr><th>Request</th><th>Academic Period</th><th>Submitted By</th><th>Status</th><th>Submitted</th><th>Action</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td>#<?=(int)$r['request_id']?></td><td><?=acadH($r['academic_year'])?> · Semester <?=(int)$r['semester']?></td><td><?=acadH($r['username'])?></td><td><span class="tag <?=acadH($r['status'])?>"><?=acadH(str_replace('_',' ',$r['status']))?></span></td><td><?=acadH($r['submitted_at'])?></td><td><a class="btn secondary" href="exam-requests.php?id=<?=(int)$r['request_id']?>">Review</a></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="6">No pending exam approval requests. Approved and rejected decisions are in Approval History → Exam Schedule.</td></tr><?php endif;?></tbody></table></div>
<?php else: $preview=json_decode((string)$item['payload_json'],true);$total=0;foreach(($preview['programs']??[]) as $code=>$itemPreview)$total+=count($itemPreview['result']['assignments']??[]);?>
<div class="panel"><?php if (!$historyEmbed): ?><a class="btn secondary" href="exam-requests.php">← All Exam Requests</a><?php endif; ?><h2>School-wide Exam Request #<?=(int)$id?></h2><p><b><?=acadH($item['academic_year'])?> · Semester <?=(int)$item['semester']?></b> | Status: <b><?=acadH($item['status'])?></b></p><p>Submitted by <?=acadH($item['username'])?> on <?=acadH($item['submitted_at'])?></p><p>Exam dates: <b><?=acadH(implode(' / ', $preview['dates']??[]))?></b> · <?=count($preview['programs']??[])?> programs · <?=$total?> examinations</p></div>
<div class="panel scroll"><h2>Programs included in the approval</h2><table><thead><tr><th>Program</th><th>Exams</th><th>Class Batch</th></tr></thead><tbody><?php foreach(($preview['programs']??[]) as $code=>$itemPreview):?><tr><td><b><?=acadH($code)?></b></td><td><?=count($itemPreview['result']['assignments']??[])?></td><td>#<?=(int)($itemPreview['class_batch_id']??0)?></td></tr><?php endforeach;?></tbody></table></div>
<?php
// Render the ORIGINAL submitted preview; do not regenerate or persist schedules while reviewing.
$q=$db->query('SELECT ss.section_subject_id,sec.section_id,sec.section_code,sec.section_type,sec.year_level,sub.subject_code,sub.subject_title FROM section_subjects ss JOIN sections sec ON sec.section_id=ss.section_id JOIN subjects sub ON sub.subject_id=ss.subject_id');
$subjectNames=[];foreach($q as $subjectRow)$subjectNames[(int)$subjectRow['section_subject_id']]=$subjectRow;
$roomNames=$db->query('SELECT room_id,room_name FROM rooms')->fetchAll(PDO::FETCH_KEY_PAIR);
$teacherNames=$db->query('SELECT teacher_id,teacher_name FROM teachers')->fetchAll(PDO::FETCH_KEY_PAIR);
function acadExamClock($time):string {
 $time=(string)$time;
 return preg_match('/^(\d{1,2}):(\d{2})/', $time,$m)?sprintf('%02d:%s',$m[1],$m[2]):$time;
}
function acadExamMajor(array $a):bool {
 return (int)$a['year_level']===4 && strtoupper((string)$a['section_type'])==='MAJOR' && (int)$a['exam_day']===3 && !in_array(strtoupper((string)$a['subject_code']),['ITE4','CAP101','PRAC101'],true);
}
function acadExamSlotKey(array $a):string {return acadExamClock($a['start_time']).'|'.acadExamClock($a['end_time']);}
function acadExamTimeBand(array $slots):string {
 $first=explode('|',$slots[0]);$last=explode('|',$slots[count($slots)-1]);return $first[0].' – '.$last[1];
}
function acadExamMatrix(array $assignments,array $dates):void {
 // Three one-hour slots per shift, including 4th shift; use actual solver times and rooms.
 $slots=[];foreach($assignments as $a)$slots[acadExamSlotKey($a)]=true;
 $slots=array_keys($slots);sort($slots,SORT_STRING);
 if(!$slots){echo '<p>No exam assignments found in this request.</p>';return;}
 $bands=array_chunk($slots,3);
 $groups=[];
 foreach($assignments as $a){
  $key=(string)$a['room_name'].'|'.(string)$a['section_code'];
  if(!isset($groups[$key]))$groups[$key]=['room'=>$a['room_name'],'section'=>$a['section_code'],'year'=>(int)$a['year_level'],'items'=>[]];
  $groups[$key]['items'][]=$a;
 }
 $allRows=array_values($groups);
 usort($allRows,static fn($a,$b)=>$a['year']<=>$b['year']?:strnatcasecmp($a['section'],$b['section'])?:strnatcasecmp($a['room'],$b['room']));
 $ordinal=['1ST','2ND','3RD','4TH'];
 foreach($bands as $bandIndex=>$band){
  $bandKeys=array_fill_keys($band,true);$rows=[];$majorByRoom=[];
  foreach($allRows as $row){
   $normal=[];
   foreach($row['items'] as $a){
    if(!isset($bandKeys[acadExamSlotKey($a)]))continue;
    if(acadExamMajor($a))$majorByRoom[(string)$a['room_name']][]=$a;
    else $normal[]=$a;
   }
   if($normal){$row['items']=$normal;$row['majors']=[];$rows[]=$row;}
  }
  // Standalone Day 3 majors stay in their actual scheduled room/shift.
  foreach($majorByRoom as $room=>$majors){
   $ix=null;
   foreach($rows as $i=>$row)if((string)$row['room']===$room){$ix=$i;break;}
   if($ix!==null)$rows[$ix]['majors']=array_merge($rows[$ix]['majors'],$majors);
   else $rows[]=['room'=>$room,'section'=>'4TH YEAR MAJOR','year'=>4,'items'=>[],'majors'=>$majors,'major_only'=>true];
  }
  if(!$rows)continue;
  $cluster=0;$previousYear=null;$clusterSize=0;
  foreach($rows as &$row){
   if(!empty($row['major_only'])){$row['cluster']='MAJOR';continue;}
   if($previousYear!==$row['year']||$clusterSize===3){$cluster++;$clusterSize=0;}
   $row['cluster']='CLUSTER '.$cluster;$clusterSize++;$previousYear=$row['year'];
  }unset($row);
  echo '<section class="ao-exam-sheet"><div class="ao-exam-sheet-top"><div class="ao-exam-shift"><strong>'.acadH($ordinal[$bandIndex]??(($bandIndex+1).'TH')).' SHIFT</strong><small>'.acadH(acadExamTimeBand($band)).'</small></div><div class="ao-exam-dates">EXAM DATES: '.acadH(implode(' · ',$dates)).'</div></div>';
  echo '<div class="ao-exam-scroll"><table class="ao-exam-matrix"><colgroup><col class="ao-cluster-col"><col class="ao-room-col"><col class="ao-section-col">';for($i=0;$i<9;$i++)echo '<col>';echo '</colgroup><thead><tr>';
  foreach(['CLUSTER','ROOM','COURSE / SECTION'] as $heading)echo '<th rowspan="2">'.acadH($heading).'</th>';
  for($day=1;$day<=3;$day++)echo '<th colspan="3" class="ao-day-head ao-day-'.$day.'">DAY '.$day.' · '.acadH($dates[$day-1]??'').'</th>';
  echo '</tr><tr>';
  for($day=1;$day<=3;$day++)for($i=0;$i<3;$i++)echo '<th>'.acadH(isset($band[$i])?str_replace('|',' – ',$band[$i]):'—').'</th>';
  echo '</tr></thead><tbody>';
  $clusterSpans=[];foreach($rows as $row){$clusterSpans[$row['cluster']]=($clusterSpans[$row['cluster']]??0)+1;}
  $printedClusters=[];foreach($rows as $row){
   echo '<tr>';
   if(!isset($printedClusters[$row['cluster']])){echo '<th class="ao-cluster" rowspan="'.(int)$clusterSpans[$row['cluster']].'">'.acadH($row['cluster']).'</th>';$printedClusters[$row['cluster']]=true;}
   echo '<td class="ao-room">'.acadH($row['room']).'</td><td class="ao-section">'.acadH($row['section']).'</td>';
   for($day=1;$day<=3;$day++)for($i=0;$i<3;$i++){
    $hits=[];$majors=[];
    if(isset($band[$i])){
     foreach($row['items'] as $a)if((int)$a['exam_day']===$day&&acadExamSlotKey($a)===$band[$i])$hits[]=$a;
     if($day===3)foreach($row['majors'] as $a)if(acadExamSlotKey($a)===$band[$i])$majors[]=$a;
    }
    $cls=($hits||$majors)?'ao-filled':'ao-empty';
    echo '<td class="ao-exam-cell '.$cls.'">';
    foreach(array_merge($hits,$majors) as $a){
     $tip=implode(' · ',array_filter([$a['section_code'],$a['subject_code'],$a['subject_title'],$a['proctor_name']]));
     echo '<div class="ao-exam-entry" title="'.acadH($tip).'">';
     if(acadExamMajor($a))echo '<span class="ao-major-section">'.acadH($a['section_code']).'</span>';
     echo '<strong>'.acadH($a['subject_code']).'</strong><small>'.acadH($a['proctor_name']).'</small></div>';
    }
    if(!$hits&&!$majors)echo '—';echo '</td>';
   }
   echo '</tr>';
  }
  echo '</tbody></table></div><div class="ao-exam-sheet-note">'.acadH(($ordinal[$bandIndex]??($bandIndex+1).'TH').' shift').' · '.count($rows).' room / section rows · DRAFT FOR ACADEMIC OFFICE REVIEW</div></section>';
 }
}
?>
<link rel="stylesheet" href="assets/exam-matrix-review.css?v=1">
<div class="panel ao-exam-review"><div class="ao-exam-review-header"><div><h2>Examination Timetable for Review</h2><p>Staff-style examination matrix using the actual submitted draft assignments. Review Day 1–3, room, section, subject and proctor before approving.</p></div><span class="ao-exam-draft">DRAFT · NOT YET ACTIVE</span></div>
<?php foreach(($preview['programs']??[]) as $code=>$itemPreview):
 $assignments=[];
 foreach(($itemPreview['result']['assignments']??[]) as $a){
  $ss=$subjectNames[(int)($a['section_subject_id']??0)]??[];
  $assignments[]=[
   'section_code'=>(string)($ss['section_code']??($a['section_id']??'Unknown')),
   'section_type'=>(string)($ss['section_type']??''),
   'year_level'=>(int)($ss['year_level']??0),
   'subject_code'=>(string)($ss['subject_code']??'Unknown'),
   'subject_title'=>(string)($ss['subject_title']??''),
   'exam_day'=>(int)($a['exam_day']??0),
   'start_time'=>(string)($a['start_time']??''),
   'end_time'=>(string)($a['end_time']??''),
   'room_name'=>(string)($roomNames[(int)($a['room_id']??0)]??'Unknown room'),
   'proctor_name'=>(string)($teacherNames[(int)($a['proctor_id']??0)]??'Unknown proctor'),
  ];
 }
?>
<details class="ao-exam-program" <?=($historyEmbed || count($preview['programs']??[])===1)?'open':''?>><summary><span><?=acadH($code)?> · Examination Timetable</span><span class="ao-exam-program-count"><?=count($assignments)?> examinations <span class="ao-exam-chevron">⌄</span></span></summary>
<div class="ao-exam-program-body"><p class="ao-exam-matrix-hint">↔ Scroll horizontally on smaller screens. Subject codes display the proctor below; hover over a subject for its full title.</p>
<?php acadExamMatrix($assignments,(array)($preview['dates']??[])); ?>
</div></details>
<?php endforeach;?></div>
<?php if($historyEmbed): ?>
<div class="panel"><b>Decision:</b> <?=acadH($item['status'])?> · <b>Remarks:</b> <?=acadH($item['remarks']??'None')?><p>Reviewed: <?=acadH($item['reviewed_at']??'')?></p></div>
<?php elseif($item['status']==='PENDING_REVIEW'):?>
<div class="panel"><h2>Decision</h2><p>Approval rechecks current class schedules, rooms, proctors and conflicts for every program before saving anything.</p><form method="post" action="exam-action.php"><input type="hidden" name="csrf_token" value="<?=acadH(authCsrf())?>"><input type="hidden" name="id" value="<?=(int)$id?>"><label for="examRemarks">Remarks / rejection reason</label><p><textarea id="examRemarks" name="remarks" rows="3" maxlength="3000" style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:9px" placeholder="Remarks for the Admin; required for rejection"></textarea></p><button class="btn warn" type="submit" name="decision" value="REJECTED" onclick="return this.form.remarks.value.trim() && confirm('Reject this school-wide exam timetable?')">Reject</button> <button class="btn" type="submit" name="decision" value="APPROVED" onclick="return confirm('Approve and save ALL exam programs?')">Approve &amp; Save All</button></form></div>
<?php else:?><div class="panel"><b>Decision remarks:</b> <?=acadH($item['remarks']??'None')?><p>Reviewed: <?=acadH($item['reviewed_at']??'')?></p></div><?php endif;?>
<?php endif; ?>
<?php if ($historyEmbed && $id) echo '</body></html>'; else acadEnd();

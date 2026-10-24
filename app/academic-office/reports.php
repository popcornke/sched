<?php
/** Academic Office-only, immutable custom date-range reporting snapshots. */
declare(strict_types=1);
require_once __DIR__.'/shared.php';
$db=acadDb();
$tz=new DateTimeZone('Asia/Manila');
$today=new DateTimeImmutable('today',$tz);
$embed=(($_GET['embed']??$_POST['embed']??'')==='1');
$todayStr=$today->format('Y-m-d');
$startStr=(string)($_POST['start_date']??$_GET['start_date']??$today->modify('first day of this month')->format('Y-m-d'));
$endStr=(string)($_POST['end_date']??$_GET['end_date']??$todayStr);
function acadReportParseDate(string $value, DateTimeZone $tz): ?DateTimeImmutable {
 if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value))return null;
 $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,$tz);
 return $date && $date->format('Y-m-d')===$value ? $date : null;
}
$start=acadReportParseDate($startStr,$tz);
$end=acadReportParseDate($endStr,$tz);
$error=null;$success=null;$saved=[];$selectedReport=null;
$programOptions=[];
try{$programOptions=$db->query("SELECT program_id,program_code,program_name FROM programs ORDER BY program_code")->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){error_log('Academic report program list: '.$e->getMessage());}
$requestedScope=(string)($_POST['program_id']??$_GET['program_id']??'all');
$scopeId=$requestedScope==='all'?null:filter_var($requestedScope,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$scopeName='All Departments (All Programs)';
if($requestedScope!=='all' && $scopeId!==false){foreach($programOptions as $program){if((int)$program['program_id']===$scopeId){$scopeName=$program['program_code'].' — '.$program['program_name'];break;}}}

function reportJson(string $s):array{ $data=json_decode($s,true);return is_array($data)?$data:[]; }
// Current saved meetings exclude superseded batches and soft-deleted approval history.
// This is deliberately a LIVE operational metric; submitted/decision counts stay snapshotted.
function acadCurrentActiveMeetings(PDO $db,string $startDate,string $endDate,?int $programId):int {
 $sql="SELECT COUNT(*) FROM schedule_meetings m
 JOIN schedule_batches b ON b.batch_id=m.batch_id
 WHERE b.status='ACTIVE' AND EXISTS (
 SELECT 1 FROM academic_preview_requests r
 WHERE r.batch_id=b.batch_id AND r.status='APPROVED'
 AND r.deleted_at IS NULL AND r.submitted_at>=? AND r.submitted_at<?";
 $params=[$startDate.' 00:00:00',(new DateTimeImmutable($endDate))->modify('+1 day')->format('Y-m-d').' 00:00:00'];
 if($programId!==null){$sql.=' AND r.program_id=?';$params[]=$programId;}
 $sql.=')';
 $q=$db->prepare($sql);$q->execute($params);return (int)$q->fetchColumn();
}

// Capture the exact current approved timetable contents when a report is saved.
// Legacy reports (without this snapshot) can display a live compatibility breakdown.
function acadReportSchedules(PDO $db,string $startDate,string $endDate,?int $programId):array {
 $sql="SELECT p.program_code,ap.academic_year,ap.semester,b.batch_id,sec.section_code,
 m.meeting_id,su.subject_code,su.subject_title,t.teacher_name,rm.room_name,m.delivery_mode,m.day_of_week,m.start_time,m.end_time, 1 AS meeting_count
 FROM academic_preview_requests ar
 JOIN schedule_batches b ON b.batch_id=ar.batch_id AND b.status='ACTIVE'
 JOIN programs p ON p.program_id=b.program_id
 JOIN academic_periods ap ON ap.academic_period_id=b.academic_period_id
 JOIN schedule_meetings m ON m.batch_id=b.batch_id
 JOIN section_subjects ss ON ss.section_subject_id=m.section_subject_id
 JOIN sections sec ON sec.section_id=ss.section_id
 JOIN subjects su ON su.subject_id=ss.subject_id
 JOIN teachers t ON t.teacher_id=m.teacher_id
 LEFT JOIN rooms rm ON rm.room_id=m.room_id
 WHERE ar.status='APPROVED' AND ar.deleted_at IS NULL
 AND ar.submitted_at>=? AND ar.submitted_at<?";
 $params=[$startDate.' 00:00:00',(new DateTimeImmutable($endDate))->modify('+1 day')->format('Y-m-d').' 00:00:00'];
 if($programId!==null){$sql.=' AND ar.program_id=?';$params[]=$programId;}
 $sql.=" ORDER BY p.program_code,sec.section_code,FIELD(m.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),m.start_time,m.meeting_id";
 $q=$db->prepare($sql);$q->execute($params);
 return $q->fetchAll(PDO::FETCH_ASSOC);
}

try {
 if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
  if(!authCsrfValid($_POST['csrf_token']??null))throw new RuntimeException('Invalid security token. Reload and try again.');
  if(($_POST['operation']??'')!=='save')throw new RuntimeException('Invalid report operation.');
  if(!$start || !$end)throw new RuntimeException('Enter valid start and end dates.');
  if($start>$end)throw new RuntimeException('End Date must be the same as or later than Start Date.');
  if($end>$today)throw new RuntimeException('End Date cannot be in the future.');
  if($start->diff($end)->days>366)throw new RuntimeException('Choose a date range of 367 days or less.');
  if($requestedScope!=='all' && ($scopeId===false || $scopeName==='All Departments (All Programs)'))throw new RuntimeException('Select a valid department / program.');
  $from=$startStr.' 00:00:00';$until=$end->modify('+1 day')->format('Y-m-d').' 00:00:00';
  $scopeSql=$scopeId===null?'':' AND r.program_id=?';
  $filters=$scopeId===null?[$from,$until]:[$from,$until,$scopeId];
  // Counts are scoped by submission time; decisions are current as of report creation.
  $q=$db->prepare("SELECT r.status,COUNT(*) total FROM academic_preview_requests r WHERE r.submitted_at>=? AND r.submitted_at<?".$scopeSql." GROUP BY r.status");
  $q->execute($filters);$metrics=['requests'=>0,'pending'=>0,'approved'=>0,'rejected'=>0,'programs'=>0,'meetings'=>0];
  foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){$n=(int)$row['total'];$metrics['requests']+=$n;$key=['PENDING_REVIEW'=>'pending','APPROVED'=>'approved','REJECTED'=>'rejected'][$row['status']]??null;if($key)$metrics[$key]+=$n;}
  // Don't count meetings multiple times when associated with more than one submission.
  $q=$db->prepare("SELECT p.program_code,COUNT(*) requests,SUM(r.status='APPROVED') approved,SUM(r.status='REJECTED') rejected,SUM(r.status='PENDING_REVIEW') pending FROM academic_preview_requests r JOIN programs p ON p.program_id=r.program_id WHERE r.submitted_at>=? AND r.submitted_at<?".$scopeSql." GROUP BY p.program_id,p.program_code ORDER BY p.program_code");
  $q->execute($filters);$breakdown=$q->fetchAll(PDO::FETCH_ASSOC);$metrics['programs']=count($breakdown);
  if($metrics['requests']===0)throw new RuntimeException('No schedule requests found for the selected department and date range. Nothing was saved.');
  $metrics['schedules']=acadReportSchedules($db,$startStr,$endStr,$scopeId===null?null:(int)$scopeId);
  $metrics['meetings']=array_sum(array_map(static fn($r)=>(int)$r['meeting_count'],$metrics['schedules']));
  $q=$db->prepare('INSERT INTO academic_office_saved_reports(report_type,period_start,period_end,created_by,metrics_json,breakdown_json,scope_program_id,scope_label) VALUES (?,?,?,?,?,?,?,?)');
  $q->execute(['CUSTOM',$startStr,$endStr,(int)$_SESSION['auth_user_id'],json_encode($metrics,JSON_THROW_ON_ERROR),json_encode($breakdown,JSON_THROW_ON_ERROR),$scopeId,$scopeName]);
  header('Location: reports.php?report_id='.(int)$db->lastInsertId().'&saved=1'.($embed?'&embed=1':''),true,303);exit;
 }
 $saved=$db->query("SELECT s.report_id,s.report_type,s.period_start,s.period_end,s.created_at,s.metrics_json,s.scope_label,u.username FROM academic_office_saved_reports s JOIN auth_users u ON u.user_id=s.created_by ORDER BY s.created_at DESC,s.report_id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
 $id=filter_var($_GET['report_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if($id){$q=$db->prepare('SELECT s.*,u.username FROM academic_office_saved_reports s JOIN auth_users u ON u.user_id=s.created_by WHERE s.report_id=?');$q->execute([$id]);$selectedReport=$q->fetch(PDO::FETCH_ASSOC)?:null;}
 if($selectedReport){
  // A prior saved report remains immutable; display today's correct active meeting count.
  $scope=$selectedReport['scope_program_id']===null?null:(int)$selectedReport['scope_program_id'];
  $selectedReport['current_active_meetings']=acadCurrentActiveMeetings($db,(string)$selectedReport['period_start'],(string)$selectedReport['period_end'],$scope);
  $priorMetrics=reportJson((string)$selectedReport['metrics_json']);
  $selectedReport['schedule_rows']=isset($priorMetrics['schedules']) && is_array($priorMetrics['schedules'])
   ?$priorMetrics['schedules']
   :acadReportSchedules($db,(string)$selectedReport['period_start'],(string)$selectedReport['period_end'],$scope);
  $selectedReport['schedule_rows_legacy']=!isset($priorMetrics['schedules']);
 }
 $success=($_GET['saved']??'')==='1'?'Report snapshot saved successfully.':null;
} catch(Throwable $e){error_log('Academic reports: '.$e->getMessage());$error=($e instanceof PDOException)?'Reporting table is unavailable. Please import migration 024 in the local database.':$e->getMessage();}
if(!$embed){acadLayout('Saved Reports');}else{echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Saved Reports | Academic Office</title><link rel="stylesheet" href="assets/office.css?v=9"><link rel="stylesheet" href="assets/admin-consistency.css?v=9"></head><body class="acad-shell acad-report-embedded"><main class="acad-content">';}
?>
<link rel="stylesheet" href="assets/reports.css?v=20">
<div class="eyebrow">BCP CLASS SCHEDULING SYSTEM · ACADEMIC OFFICE</div>
<div class="report-header"><div><h1 class="report-title">Saved Reports<span class="office-dot">.</span></h1><p>Save a permanent snapshot for your selected start and end dates.</p></div><?php if(!$embed):?><a class="btn secondary report-no-print" href="dashboard.php">← Back to Dashboard</a><?php endif;?></div>
<?php if($error):?><div class="note error"><?=acadH($error)?></div><?php endif;?>
<?php if($success):?><div class="note"><?=acadH($success)?></div><?php endif;?>
<section class="panel report-no-print"><h2>Generate Report Snapshot</h2><p>Choose the exact date range to include in your approval report. Both dates are included.</p><form class="report-form" method="post"><?php if($embed):?><input type="hidden" name="embed" value="1"><?php endif;?><input type="hidden" name="csrf_token" value="<?=acadH(authCsrf())?>"><input type="hidden" name="operation" value="save"><label>Department / Program<select name="program_id" required><option value="all" <?=$requestedScope==='all'?'selected':''?>>All Departments (All Programs)</option><?php foreach($programOptions as $program):?><option value="<?=(int)$program['program_id']?>" <?=$requestedScope!=='all' && (int)$scopeId===(int)$program['program_id']?'selected':''?>><?=acadH($program['program_code'].' — '.$program['program_name'])?></option><?php endforeach;?></select></label><label>Start Date<input type="date" name="start_date" max="<?=acadH($todayStr)?>" value="<?=acadH($startStr)?>" required></label><label>End Date<input type="date" name="end_date" max="<?=acadH($todayStr)?>" value="<?=acadH($endStr)?>" required></label><button type="submit" onclick="return confirm('Save an approval report for the selected date range?')">Save Report</button></form><p class="report-hint">Both start and end dates are included. Requests are selected by submission date. Approval statuses reflect the database at the moment of saving; older snapshots will not change when new decisions occur.</p></section>
<?php if($selectedReport):$m=reportJson($selectedReport['metrics_json']);$b=reportJson($selectedReport['breakdown_json']);?>
<div class="report-modal office-modal" id="reportModal" role="dialog" aria-modal="true" aria-labelledby="reportModalTitle">
 <div class="office-modal-backdrop"></div><div class="report-dialog office-modal-shell">
  <div class="report-modal-header office-modal-top"><div><strong id="reportModalTitle">Official Academic Report Preview</strong><p>Review the saved report. Print or save it as PDF.</p></div><button type="button" class="report-modal-dismiss office-modal-close" aria-label="Close" onclick="closeReport()">×</button></div>
  <div class="report-modal-body office-modal-frame"><article class="report-paper acad-report-document" id="report-document">
   <div class="report-doc-heading"><div class="eyebrow">BESTLINK COLLEGE OF THE PHILIPPINES</div><h2>Academic Approval Report #<?=(int)$selectedReport['report_id']?></h2><div class="report-doc-meta"><span><?=acadH($selectedReport['scope_label']??'All Departments (All Programs)')?></span><span><?=acadH($selectedReport['period_start'])?> to <?=acadH($selectedReport['period_end'])?></span><span>Prepared by <?=acadH($selectedReport['username'])?></span><span>Saved <?=acadH($selectedReport['created_at'])?></span></div></div>
   <?php $scheduleRows=$selectedReport['schedule_rows']??[];$programLabels=array_unique(array_column($scheduleRows,'program_code'));$reportedMeetings=array_sum(array_map(static fn($v)=>(int)($v['meeting_count']??0),$scheduleRows));?>
   <div class="report-pills report-pills-compact">
    <div class="report-pill"><small>Program</small><b><?=acadH($programLabels?implode(', ',$programLabels):'No active schedules')?></b></div>
    <div class="report-pill"><small>Current Active Meetings</small><b><?=number_format((int)($selectedReport['current_active_meetings']??0))?></b></div>
   </div>
   <div class="report-document-caption">Reporting period: <?=acadH($selectedReport['period_start'])?> to <?=acadH($selectedReport['period_end'])?> · <?=acadH($selectedReport['scope_label']??'All Departments (All Programs)')?></div>
   <?php include __DIR__.'/report-schedule-document.php'; ?>
   <p class="report-current-note"><?=!empty($selectedReport['schedule_rows_legacy'])?'Older report: the schedule shown uses currently active approved records.':'The schedule rows above were saved in this report snapshot.'?> Current Active Meetings is a live count.</p>
  </article></div>
  <div class="report-modal-actions office-modal-actions"><button type="button" class="btn secondary" onclick="closeReport()">← Back</button><button type="button" class="btn report-print" onclick="printReport()">▣ Print / Save as PDF</button></div>
 </div>
</div>
<?php endif;?>
<section class="panel report-no-print"><div class="office-panel-top"><div><h2>Report Archive</h2><p>Saved date-range reports remain available for future review.</p></div></div><div class="scroll report-table"><table><thead><tr><th>Report</th><th>Period</th><th>Department / Program</th><th>Requests</th><th>Approved</th><th>Prepared by</th><th>Saved at</th><th></th></tr></thead><tbody><?php foreach($saved as $r):$m=reportJson($r['metrics_json']);?><tr><td><b>#<?=(int)$r['report_id']?></b> · <?=acadH($r['report_type']==="CUSTOM"?"Date Range":ucfirst(strtolower($r['report_type'])))?></td><td><?=acadH($r['period_start'])?> – <?=acadH($r['period_end'])?></td><td><?=acadH($r['scope_label']??'All Departments (All Programs)')?></td><td><?=(int)($m['requests']??0)?></td><td><?=(int)($m['approved']??0)?></td><td><?=acadH($r['username'])?></td><td><?=acadH($r['created_at'])?></td><td><a class="btn secondary" href="reports.php?report_id=<?=(int)$r['report_id']?><?=$embed?'&amp;embed=1':''?>">View</a></td></tr><?php endforeach;if(!$saved):?><tr><td colspan="8">No saved reports yet. Create your first snapshot above.</td></tr><?php endif;?></tbody></table></div></section>
<script src="assets/reports.js?v=20" defer></script>
<?php if($embed){echo '</main></body></html>';}else{acadEnd();} ?>

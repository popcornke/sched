<?php
/** Section-by-section, read-only report document. Uses captured report rows when available. */
$reportRows = $selectedReport['schedule_rows'] ?? [];
$reportPrograms = [];
$reportSections = [];
foreach ($reportRows as $r) {
    $code=(string)($r['section_code']??'');
    $program=(string)($r['program_code']??'');
    if ($code==='') continue;
    $reportPrograms[$program]=true;
    $key=$program.'|'.(string)($r['academic_year']??'').'|'.(string)($r['semester']??'').'|'.$code;
    if (!isset($reportSections[$key])) $reportSections[$key]=['code'=>$code,'program'=>$program,'academic_year'=>(string)($r['academic_year']??''),'semester'=>(int)($r['semester']??1),'subjects'=>[]];
    $subKey=(string)($r['subject_code']??'').'|'.(string)($r['subject_title']??'');
    if (!isset($reportSections[$key]['subjects'][$subKey])) $reportSections[$key]['subjects'][$subKey]=['code'=>(string)($r['subject_code']??''),'title'=>(string)($r['subject_title']??''),'teacher'=>(string)($r['teacher_name']??'—'),'f2f'=>null,'online'=>null];
    $mode=strtoupper((string)($r['delivery_mode']??''));
    $detail=['day'=>(string)($r['day_of_week']??'—'),'start'=>(string)($r['start_time']??''),'end'=>(string)($r['end_time']??''),'room'=>(string)($r['room_name']??'—')];
    $slot=($mode==='ONLINE')?'online':'f2f';
    $reportSections[$key]['subjects'][$subKey][$slot]=$detail;
    $reportSections[$key]['subjects'][$subKey]['teacher']=(string)($r['teacher_name']??'—');
}
ksort($reportSections,SORT_NATURAL);
$sectionCodes=array_values(array_unique(array_map(static fn($g)=>$g['code'],$reportSections)));
$yearByCode=[];
if($sectionCodes){
    $marks=implode(',',array_fill(0,count($sectionCodes),'?'));
    $stmt=$db->prepare("SELECT section_code,year_level FROM sections WHERE section_code IN ($marks)");
    $stmt->execute($sectionCodes);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $sec) $yearByCode[(string)$sec['section_code']]=(int)$sec['year_level'];
}
$programNames=[];
if($reportPrograms){
    $marks=implode(',',array_fill(0,count($reportPrograms),'?'));
    $stmt=$db->prepare("SELECT program_code,program_name FROM programs WHERE program_code IN ($marks)");
    $stmt->execute(array_keys($reportPrograms));
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $program) $programNames[(string)$program['program_code']]=(string)$program['program_name'];
}
function acadReportYear(int $year):string {
    if ($year<1 || $year>20) return $year>0?$year.'th Year':'—';
    $suffix=(($year%100>=11)&&($year%100<=13))?'th':match($year%10){1=>'st',2=>'nd',3=>'rd',default=>'th'};
    return $year.$suffix.' Year';
}
function acadReportTime(string $time):string {
    if($time==='')return '—';
    $value=strtotime('2000-01-01 '.$time);
    return $value?date('g:i A',$value):acadH($time);
}
function acadReportSpan(?array $entry):string {return $entry ? acadReportTime($entry['start']).' – '.acadReportTime($entry['end']):'—';}
?>
<div class="acad-document-list report-official-list">
<?php if (!$reportSections): ?>
    <section class="acad-document"><div class="acad-document-school"><img src="../assets/images/BCP_LOGO.png" alt="BCP logo"><strong>BESTLINK COLLEGE OF THE PHILIPPINES</strong><b>Academic Schedule Report</b></div><p>No active approved schedules for this report scope.</p></section>
<?php endif; ?>
<?php foreach ($reportSections as $section): ?>
<section class="acad-document">
    <div class="acad-document-school"><img src="../assets/images/BCP_LOGO.png" alt="BCP logo"><strong>BESTLINK COLLEGE OF THE PHILIPPINES</strong><b>Official Class Schedule — Saved Report</b></div>
    <div class="acad-document-details">
      <span><b>Program:</b> <?=acadH($programNames[$section['program']]??$section['program'])?></span>
      <span><b>Section:</b> <?=acadH($section['code'])?></span>
      <span><b>Year Level:</b> <?=acadH(acadReportYear($yearByCode[$section['code']]??(int)substr($section['code'],0,1)))?></span>
      <span><b>Academic Year:</b> <?=acadH($section['academic_year'])?></span>
      <span><b>Semester:</b> <?=$section['semester']===1?'First':($section['semester']===2?'Second':acadH((string)$section['semester']))?> Semester</span>
    </div>
    <div class="acad-table-scroll"><table class="acad-document-table"><thead><tr><th rowspan="2">No.</th><th rowspan="2">Subject Code</th><th rowspan="2">Description</th><th colspan="3">FACE-TO-FACE</th><th colspan="2">ONLINE</th><th rowspan="2">Teacher</th></tr><tr><th>Day</th><th>Time</th><th>Room</th><th>Day</th><th>Time</th></tr></thead><tbody>
    <?php $subjectRows=array_values($section['subjects']);$week=['Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,'Thursday'=>4,'Friday'=>5,'Saturday'=>6];usort($subjectRows,static function($a,$b)use($week){$aa=$a['f2f']??$a['online'];$bb=$b['f2f']??$b['online'];return ($week[$aa['day']??'']??9)<=>($week[$bb['day']??'']??9) ?:strcmp((string)($aa['start']??''),(string)($bb['start']??''));}); ?>
    <?php foreach($subjectRows as $index=>$sub):$f=$sub['f2f'];$o=$sub['online'];?><tr><td><?=$index+1?></td><td><b><?=acadH($sub['code'])?></b></td><td><?=acadH($sub['title'])?></td><td><?=acadH($f['day']??'—')?></td><td><?=acadH(acadReportSpan($f))?></td><td><?=acadH($f['room']??'—')?></td><td><?=acadH($o['day']??'—')?></td><td><?=acadH(acadReportSpan($o))?></td><td><?=acadH($sub['teacher'])?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php endforeach; ?>
</div>

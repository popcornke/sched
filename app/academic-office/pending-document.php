<?php
declare(strict_types=1);
require_once __DIR__.'/shared.php';
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id){http_response_code(400);exit('Invalid request');}
$db=acadDb();
$q=$db->prepare("SELECT ar.*, p.program_code,p.program_name,ap.academic_year,ap.semester FROM academic_preview_requests ar JOIN programs p ON p.program_id=ar.program_id JOIN academic_periods ap ON ap.academic_period_id=ar.academic_period_id WHERE ar.request_id=? AND ar.status='PENDING_REVIEW' AND ar.deleted_at IS NULL");
$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
if(!$r){http_response_code(404);exit('Pending request not available');}
$preview=json_decode((string)$r['payload_json'],true);
$meetings=$preview['result']['assignments']??[];
if(!is_array($meetings))$meetings=[];
$subjectMap=[];$sectionMap=[];$teacherMap=[];$roomMap=[];
$sectionIds=[];$subjectIds=[];$teacherIds=[];$roomIds=[];
foreach($meetings as $m){
 if(!is_array($m))continue;
 foreach(['section_id'=>'sectionIds','subject_id'=>'subjectIds','teacher_id'=>'teacherIds','room_id'=>'roomIds'] as $key=>$list){if(!empty($m[$key]))${$list}[(int)$m[$key]]=(int)$m[$key];}
}
function acadLookup(PDO $db,string $table,string $idCol,array $ids,string $cols):array{
 if(!$ids)return [];
 $params=array_values($ids);$qs=implode(',',array_fill(0,count($params),'?'));
 $stmt=$db->prepare("SELECT $cols FROM $table WHERE $idCol IN ($qs)");$stmt->execute($params);
 $out=[];foreach($stmt as $row)$out[(int)$row[$idCol]]=$row;return $out;
}
$sectionMap=acadLookup($db,'sections','section_id',$sectionIds,'section_id,section_code,year_level');
$subjectMap=acadLookup($db,'subjects','subject_id',$subjectIds,'subject_id,subject_code,subject_title');
$teacherMap=acadLookup($db,'teachers','teacher_id',$teacherIds,'teacher_id,teacher_name');
$roomMap=acadLookup($db,'rooms','room_id',$roomIds,'room_id,room_name');
$groups=[];
foreach($meetings as $m){
 $sid=(int)($m['section_id']??0);$s=$sectionMap[$sid]??[];$code=(string)($s['section_code']??$sid);
 $subjectId=(int)($m['subject_id']??0);$sub=$subjectMap[$subjectId]??[];
 $key=$sid.':'.$subjectId;
 if(!isset($groups[$code]))$groups[$code]=['year'=>$s['year_level']??substr($code,0,1),'subjects'=>[]];
 if(!isset($groups[$code]['subjects'][$key]))$groups[$code]['subjects'][$key]=['code'=>$sub['subject_code']??(string)$subjectId,'title'=>$sub['subject_title']??'—','teacher'=>'—','f2f'=>null,'online'=>null];
 $mode=strtoupper((string)($m['delivery_mode']??''));
 $item=['day'=>$m['day_of_week']??'—','start'=>$m['start_time']??null,'end'=>$m['end_time']??null,'room'=>$roomMap[(int)($m['room_id']??0)]['room_name']??'—'];
 $groups[$code]['subjects'][$key][$mode==='ONLINE'?'online':'f2f']=$item;
 $groups[$code]['subjects'][$key]['teacher']=$teacherMap[(int)($m['teacher_id']??0)]['teacher_name']??'—';
}
ksort($groups,SORT_NATURAL);
function tm(?string $time):string{if(!$time)return '—';$t=strtotime('2000-01-01 '.$time);return $t?date('g:i A',$t):'—';}
function spanTime(?array $m):string{return $m?tm($m['start']).' – '.tm($m['end']):'—';}
if(($_GET['embedded']??'')==='1'){echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Official Schedule Review</title><link rel="stylesheet" href="assets/office.css"></head><body class="embedded-document"><main class="embedded-document-main">';}else{acadLayout('View Pending Schedule');}
if(($_GET['embedded']??'')!=='1')echo '<div class="actions no-print"><a class="btn secondary" href="preview-review.php">← Back to Approval History</a><button type="button" onclick="window.print()">Print / Save as PDF</button></div>'; 
echo '<h1>Official-Format Schedule Preview</h1><p>Read-only display of the submitted timetable · Request #'.(int)$id.' · '.acadH($r['status']).'. Read-only. Use the browser print dialog to save as PDF.</p>';
echo '<div class="acad-document-list">';
foreach($groups as $code=>$data){
 echo '<section class="acad-document"><div class="acad-document-school"><img src="../assets/images/BCP_LOGO.png" alt="BCP logo"><strong>BESTLINK COLLEGE OF THE PHILIPPINES</strong><b>Generated Class Schedule — For Approval</b></div>';
 echo '<div class="acad-document-details"><span><b>Program:</b> '.acadH($r['program_name']).'</span><span><b>Section:</b> '.acadH($code).'</span><span><b>Year Level:</b> '.acadH(match ((int)$data['year']) {1=>'1st Year',2=>'2nd Year',3=>'3rd Year',4=>'4th Year',default=>(string)$data['year'].' Year'}).'</span><span><b>Academic Year:</b> '.acadH($r['academic_year']).'</span><span><b>Semester:</b> '.((int)$r['semester']===1?'First':'Second').' Semester</span></div>';
 echo '<div class="acad-table-scroll"><table class="acad-document-table"><thead><tr><th rowspan="2">No.</th><th rowspan="2">Subject Code</th><th rowspan="2">Description</th><th colspan="3">FACE-TO-FACE</th><th colspan="2">ONLINE</th><th rowspan="2">Teacher</th></tr><tr><th>Day</th><th>Time</th><th>Room</th><th>Day</th><th>Time</th></tr></thead><tbody>';
 $subjects=array_values($data['subjects']);$days=['Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,'Thursday'=>4,'Friday'=>5,'Saturday'=>6];
 usort($subjects,static function($a,$b)use($days){$ma=$a['f2f']??$a['online'];$mb=$b['f2f']??$b['online'];return (($days[$ma['day']??'']??9)<=>($days[$mb['day']??'']??9))?:strcmp((string)($ma['start']??''),(string)($mb['start']??''));});
 foreach($subjects as $i=>$sub){$f=$sub['f2f'];$o=$sub['online'];echo '<tr><td>'.($i+1).'</td><td><b>'.acadH((string)$sub['code']).'</b></td><td>'.acadH((string)$sub['title']).'</td><td>'.acadH((string)($f['day']??'—')).'</td><td>'.acadH(spanTime($f)).'</td><td>'.acadH((string)($f['room']??'—')).'</td><td>'.acadH((string)($o['day']??'—')).'</td><td>'.acadH(spanTime($o)).'</td><td>'.acadH((string)$sub['teacher']).'</td></tr>';}
 echo '</tbody></table></div></section>';
}
if(!$groups)echo '<div class="panel">No submitted meetings were found in this request.</div>';
echo '</div>';if(($_GET['embedded']??'')==='1')echo '</main></body></html>';else acadEnd();

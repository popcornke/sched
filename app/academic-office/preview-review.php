<?php
declare(strict_types=1);
require_once __DIR__.'/shared.php';
$db=acadDb();
// Gracefully explain an unapplied migration instead of showing a PDO fatal error.
try {
    $exists = $db->query("SHOW TABLES LIKE 'academic_preview_requests'")->fetchColumn();
} catch (PDOException $e) {
    error_log('Academic preview schema check failed: '.$e->getMessage());
    http_response_code(503);
    acadLayout('Preview Requests');
    echo '<h1>Database setup is incomplete</h1><p>Unable to verify the Academic Office preview request table. Check the MariaDB connection and apply migration 021.</p>';
    acadEnd();
    exit;
}
if (!$exists) {
    http_response_code(503);
    acadLayout('Preview Requests');
    echo '<h1>Academic Office setup required</h1><p>Import <code>database/migrations/021_academic_preview_requests.sql</code> into your <strong>bcp_scheduling</strong> database, or run <code>C:\\xampp\\php\\php.exe tools\\install-academic-preview.php</code> from the project folder.</p><p>This setup creates the pending review table without deleting saved schedules.</p>';
    acadEnd();
    exit;
}
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id){$rows=$db->query("SELECT r.request_id,r.status,r.submitted_at,p.program_code,ap.academic_year,ap.semester FROM academic_preview_requests r JOIN programs p ON p.program_id=r.program_id JOIN academic_periods ap ON ap.academic_period_id=r.academic_period_id WHERE r.status='PENDING_REVIEW' ORDER BY r.request_id DESC LIMIT 200")->fetchAll();acadLayout('Generated Preview Requests');echo '<h1>Generated Timetables Awaiting Approval</h1><p>Only pending requests appear here. Approved and rejected decisions are found in <a href="history.php">Approval History</a>.</p><div class="panel scroll"><table><tr><th>Request</th><th>Program</th><th>Period</th><th>Status</th><th>Submitted</th><th>Review</th></tr>';foreach($rows as $r)echo '<tr><td>#'.(int)$r['request_id'].'</td><td>'.acadH($r['program_code']).'</td><td>'.acadH($r['academic_year']).' Sem '.(int)$r['semester'].'</td><td>'.acadH($r['status']).'</td><td>'.acadH($r['submitted_at']).'</td><td><a class="btn secondary" href="preview-review.php?id='.(int)$r['request_id'].'">Review</a></td></tr>';echo '</table></div>';acadEnd();exit;}
$q=$db->prepare('SELECT r.*,p.program_code,ap.academic_year,ap.semester,u.username FROM academic_preview_requests r JOIN programs p ON p.program_id=r.program_id JOIN academic_periods ap ON ap.academic_period_id=r.academic_period_id JOIN auth_users u ON u.user_id=r.submitted_by WHERE r.request_id=?');$q->execute([$id]);$r=$q->fetch();if(!$r){http_response_code(404);exit('Request not found');}
$preview=json_decode($r['payload_json'],true);$assignments=$preview['result']['assignments']??[];$q=$db->prepare('SELECT ss.section_id,ss.subject_id,sec.section_code,s.subject_code,s.subject_title FROM section_subjects ss JOIN sections sec ON sec.section_id=ss.section_id JOIN subjects s ON s.subject_id=ss.subject_id WHERE sec.program_id=?');$q->execute([$r['program_id']]);$names=[];foreach($q as $row)$names[$row['section_id'].':'.$row['subject_id']]=$row;
acadLayout('Preview Requests');
?>
<div class="eyebrow">ACADEMIC OFFICE · TIMETABLE VALIDATION</div>
<h1>Generated Timetables Awaiting Approval</h1>
<p>Review the generated timetable before making an approval decision.</p>
<div class="panel"><a class="btn secondary" href="preview-review.php">← All Pending Requests</a> <strong style="margin-left:15px"><?=acadH($r['program_code'])?> · Request #<?=(int)$id?></strong></div>
<div class="office-modal" id="acadPendingModal" role="dialog" aria-modal="true" aria-labelledby="acadPendingTitle">
 <div class="office-modal-backdrop"></div>
 <div class="office-modal-shell">
  <div class="office-modal-top"><div><strong id="acadPendingTitle">Generated Class Schedule — Approval Review</strong><p>Read the complete timetable, then approve or reject the request.</p></div><a class="office-modal-close" href="preview-review.php" aria-label="Close preview" style="text-decoration:none;text-align:center">×</a></div>
  <iframe class="office-modal-frame" id="acadPendingFrame" title="Generated class schedule" src="pending-document.php?id=<?=(int)$id?>&amp;embedded=1"></iframe>
  <div class="office-modal-actions" style="flex-wrap:wrap;align-items:center">
   <a class="btn secondary" href="preview-review.php">← Back</a>
   <?php if($r['status']==='PENDING_REVIEW'): ?>
   <form method="post" action="preview-action.php" style="display:flex;flex:1;justify-content:flex-end;gap:9px;align-items:center;flex-wrap:wrap;margin:0">
    <input type="hidden" name="csrf_token" value="<?=acadH(authCsrf())?>">
    <input type="hidden" name="id" value="<?=(int)$id?>">
    <input type="text" name="remarks" maxlength="3000" placeholder="Rejection reason (required when rejecting)" aria-label="Decision remarks" style="min-width:230px;flex:1;max-width:360px;padding:10px;border:1px solid #cfd9ea;border-radius:9px">
    <button type="submit" name="decision" value="REJECTED" class="btn warn" onclick="return this.form.remarks.value.trim()?confirm('Reject this timetable? Admin must generate again.'): (alert('Enter a rejection reason first.'),false)">Reject</button>
    <button type="submit" name="decision" value="APPROVED" class="btn" onclick="return confirm('Approve and save this timetable?')">Approve &amp; Save</button>
   </form>
   <?php endif; ?>
  </div>
 </div>
</div>
<link rel="stylesheet" href="assets/preview-review.css?v=20">
<?php acadEnd();

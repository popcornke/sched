<?php
declare(strict_types=1);
require_once __DIR__.'/shared.php';
$db=acadDb();
$stats=['pending'=>0,'approved'=>0,'rejected'=>0,'archived'=>0,'programs'=>0,'meetings'=>0];
$recent=[];$error=null;
try {
 $counts=$db->query("SELECT status,COUNT(*) n FROM academic_preview_requests WHERE deleted_at IS NULL GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
 foreach($counts as $c){$key=['PENDING_REVIEW'=>'pending','APPROVED'=>'approved','REJECTED'=>'rejected'][$c['status']]??null;if($key)$stats[$key]+=(int)$c['n'];}
 $stats['archived']=(int)$db->query("SELECT COUNT(*) FROM academic_preview_requests WHERE archived_at IS NOT NULL AND deleted_at IS NULL")->fetchColumn();
 $stats['programs']=(int)$db->query("SELECT COUNT(DISTINCT program_id) FROM academic_preview_requests WHERE deleted_at IS NULL")->fetchColumn();
 $stats['meetings']=(int)$db->query("SELECT COUNT(*) FROM schedule_meetings m JOIN academic_preview_requests r ON r.batch_id=m.batch_id WHERE r.status='APPROVED' AND r.deleted_at IS NULL")->fetchColumn();
 $recent=$db->query("SELECT r.request_id,r.status,r.submitted_at,r.reviewed_at,r.batch_id,p.program_code,ap.academic_year,ap.semester FROM academic_preview_requests r JOIN programs p ON p.program_id=r.program_id JOIN academic_periods ap ON ap.academic_period_id=r.academic_period_id WHERE r.deleted_at IS NULL ORDER BY r.submitted_at DESC,r.request_id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e){error_log('Academic dashboard: '.$e->getMessage());$error='Cannot load live dashboard data. Check that migrations 020–023 are installed and MariaDB is running.';}
acadLayout('Dashboard');
?>
<link rel="stylesheet" href="assets/dashboard.css?v=20">
<div class="office-hero"><div><div class="eyebrow">BESTLINK COLLEGE OF THE PHILIPPINES · ACADEMIC OFFICE</div><h1>Academic Office Dashboard<span class="office-dot">.</span></h1><p>Live review overview of submitted class timetables and official approvals.</p></div></div>
<?php if($error):?><div class="note error"><?=acadH($error)?></div><?php endif;?>
<div class="office-kpi-grid">
 <a class="office-kpi" href="preview-review.php"><span class="kpi-icon">◷</span><small>Pending Requests</small><strong><?=number_format($stats['pending'])?></strong><span>Awaiting Academic Office decision ↗</span></a>
 <a class="office-kpi" href="history.php"><span class="kpi-icon green">✓</span><small>Approved Schedules</small><strong><?=number_format($stats['approved'])?></strong><span>Approved requests ↗</span></a>
 <a class="office-kpi" href="history.php"><span class="kpi-icon red">↻</span><small>Rejected Requests</small><strong><?=number_format($stats['rejected'])?></strong><span>Require Admin regeneration ↗</span></a>
 <a class="office-kpi" href="history.php?archived=1"><span class="kpi-icon">▤</span><small>Archived History</small><strong><?=number_format($stats['archived'])?></strong><span>Archived review entries ↗</span></a>
</div>
<div class="acad-view-choices" role="tablist" aria-label="Dashboard records">
 <button class="acad-choice active" type="button" id="acadRecentChoice" role="tab" aria-selected="true" aria-controls="acadRecentPanel"><span aria-hidden="true">▤</span> Recent Schedule Requests</button>
 <button class="acad-choice" type="button" id="acadReportsChoice" role="tab" aria-selected="false" aria-controls="acadReportsPanel"><span aria-hidden="true">▧</span> Saved Reports</button>
</div>
<div id="acadRecentPanel" class="acad-tab-panel" role="tabpanel" aria-labelledby="acadRecentChoice">
<section class="panel office-table-panel"><div class="office-panel-top"><div><h2>Recent Schedule Requests</h2><p>Most recent submitted timetables · Live database data</p></div><a class="btn secondary" href="history.php">Approval History →</a></div><div class="scroll"><table><thead><tr><th>Request</th><th>Program</th><th>Academic Period</th><th>Decision</th><th>Submitted</th></tr></thead><tbody><?php foreach($recent as $r):?><tr><td><b>#<?=(int)$r['request_id']?></b></td><td><?=acadH($r['program_code'])?></td><td><?=acadH($r['academic_year'])?> · Sem <?=(int)$r['semester']?></td><td><span class="tag <?=acadH($r['status'])?>"><?=acadH(str_replace('_',' ',$r['status']))?></span></td><td><?=acadH($r['submitted_at'])?></td></tr><?php endforeach;if(!$recent):?><tr><td colspan="5">No schedule requests have been submitted yet.</td></tr><?php endif;?></tbody></table></div></section>
</div>
<div id="acadReportsPanel" class="acad-tab-panel" role="tabpanel" aria-labelledby="acadReportsChoice" hidden>
<section class="panel acad-reports-panel"><iframe class="acad-inline-report" id="acadReportsFrame" title="Saved Reports, custom date ranges" data-src="reports.php?embed=1" loading="lazy"></iframe></section>
</div>
<div class="office-shortcuts office-actions-grid"><a class="office-shortcut" href="preview-review.php"><span class="office-shortcut-icon">▤</span><span><strong>Review Pending Requests</strong><small>Check submitted schedules awaiting your decision</small></span><span class="office-shortcut-arrow">→</span></a><a class="office-shortcut" href="history.php"><span class="office-shortcut-icon">◷</span><span><strong>Open Approval History</strong><small>View approved, rejected and archived decisions</small></span><span class="office-shortcut-arrow">→</span></a></div>

<script src="assets/dashboard.js?v=20" defer></script>
<?php acadEnd();

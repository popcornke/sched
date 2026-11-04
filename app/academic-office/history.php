<?php
declare(strict_types=1);
require_once __DIR__.'/shared.php';
require_once __DIR__.'/history-retention.php';
$db=acadDb();
$view=($_GET['view']??'')==='deleted'?'deleted':((($_GET['archived']??'')==='1')?'archived':'current');
try {
    acadExpireDeletedHistory($db);
    $predicate=match($view){
        'deleted'=>"r.deleted_at IS NOT NULL AND r.purged_at IS NULL AND r.deleted_at > DATE_SUB(NOW(), INTERVAL 15 DAY)",
        'archived'=>"r.deleted_at IS NULL AND r.purged_at IS NULL AND r.archived_at IS NOT NULL",
        default=>"r.deleted_at IS NULL AND r.purged_at IS NULL AND r.archived_at IS NULL"
    };
    $query="SELECT r.request_id,r.status,r.reason,r.batch_id,r.submitted_at,r.reviewed_at,r.archived_at,r.deleted_at,
                   p.program_code,ap.academic_year,ap.semester,ru.username AS reviewer
            FROM academic_preview_requests r
            JOIN programs p ON p.program_id=r.program_id
            JOIN academic_periods ap ON ap.academic_period_id=r.academic_period_id
            LEFT JOIN auth_users ru ON ru.user_id=r.reviewed_by
            WHERE r.status IN ('APPROVED','REJECTED') AND $predicate
            ORDER BY ".($view==='deleted'?'r.deleted_at DESC,':'r.reviewed_at DESC,')."r.request_id DESC LIMIT 500";
    $rows=$db->query($query)->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {
    error_log('Approval history: '.$e->getMessage());http_response_code(503);
    acadLayout('Approval History');echo '<h1>Approval History unavailable</h1><p>Check that database migrations 022, 023 and 027 are applied.</p>';acadEnd();exit;
}
function acadHistoryAction(int $id,string $action,string $label,string $confirmation,bool $danger=false):string {
    return '<form method="post" action="preview-archive.php" data-history-confirm="'.acadH($confirmation).'">'
      .'<input type="hidden" name="csrf_token" value="'.acadH(authCsrf()).'">'
      .'<input type="hidden" name="id" value="'.$id.'">'
      .'<input type="hidden" name="action" value="'.acadH($action).'">'
      .'<button type="submit" class="btn secondary'.($danger?' acad-delete-btn':'').'">'.acadH($label).'</button></form>';
}
acadLayout('Approval History');
echo '<link rel="stylesheet" href="assets/history.css?v=27">'
    .'<div class="eyebrow">ACADEMIC OFFICE · DECISION RECORDS</div><h1>Approval History</h1>'
    .'<p class="acad-history-note">Manage approved and rejected requests. Deleted History keeps entries recoverable for 15 days. After that, entries permanently disappear from office history; approved schedules and internal audit evidence remain intact.</p>'
    .'<div class="actions acad-history-tabs">';
foreach (['current'=>['Current history','history.php'],'archived'=>['Archived history','history.php?archived=1'],'deleted'=>['Deleted history','history.php?view=deleted']] as $key=>$item) {
    echo '<a class="btn '.($view===$key?'acad-tab-active':'secondary').'" href="'.$item[1].'">'.$item[0].'</a>';
}
echo '</div><div class="panel scroll"><table><thead><tr><th>Request</th><th>Program</th><th>Period</th><th>Decision</th><th>Saved batch</th><th>Reviewer</th><th>Reason / notes</th><th>'.($view==='deleted'?'Recover until':'Date').'</th><th>Actions</th></tr></thead><tbody>';
foreach ($rows as $r) {
    $id=(int)$r['request_id'];
    $date=$view==='deleted' ? date('M j, Y g:i A',strtotime((string)$r['deleted_at'].' +15 days')) : (string)($r['reviewed_at']??'');
    $actions='<div class="acad-history-actions">';
    if ($view==='deleted') {
        $actions.=acadHistoryAction($id,'retrieve','Retrieve','Restore this entry to Current history?');
        $actions.=acadHistoryAction($id,'permanent_delete','Delete Permanently','Permanently remove this entry from Academic Office history? It cannot be retrieved afterward. Approved schedule data and audit evidence remain preserved.',true);
    } else {
        $actions.='<a class="btn secondary" href="document-view.php?id='.$id.'" data-document-view="'.$id.'">View</a>';
        $actions.=acadHistoryAction($id,$view==='archived'?'restore':'archive',$view==='archived'?'Restore':'Archive','Move this entry '.($view==='archived'?'back to Current history':'to Archived history').'?');
        $actions.=acadHistoryAction($id,'delete','Delete','Move this entry to Deleted History? You can retrieve it within 15 days.',true);
    }
    $actions.='</div>';
    echo '<tr><td>#'.$id.'</td><td>'.acadH($r['program_code']).'</td><td>'.acadH($r['academic_year']).' Sem '.(int)$r['semester'].'</td><td>'.acadH($r['status']).'</td><td>'.acadH($r['batch_id']??'Not saved').'</td><td>'.acadH($r['reviewer']??'').'</td><td>'.acadH($r['reason']??'').'</td><td>'.acadH($date).'</td><td>'.$actions.'</td></tr>';
}
if (!$rows) echo '<tr><td colspan="9">No '.($view==='deleted'?'recoverable deleted':($view==='archived'?'archived':'completed')).' requests.</td></tr>';
echo '</tbody></table></div><script src="assets/history.js?v=27" defer></script>';
if ($view!=='deleted') include __DIR__.'/document-modal.php';
acadEnd();

<?php
declare(strict_types=1);
require_once __DIR__.'/shared.php';
require_once __DIR__.'/history-retention.php';
$db=acadDb();
$type=(($_GET['type']??'')==='exam')?'exam':'class';
$view=($_GET['view']??'')==='deleted'?'deleted':((($_GET['archived']??'')==='1')?'archived':'current');
try {
    acadExpireDeletedHistory($db);
    $table=$type==='exam'?'academic_exam_requests':'academic_preview_requests';
    $predicate=match($view){
        'deleted'=>"r.deleted_at IS NOT NULL AND r.purged_at IS NULL AND r.deleted_at > DATE_SUB(NOW(), INTERVAL 15 DAY)",
        'archived'=>"r.deleted_at IS NULL AND r.purged_at IS NULL AND r.archived_at IS NOT NULL",
        default=>"r.deleted_at IS NULL AND r.purged_at IS NULL AND r.archived_at IS NULL"
    };
    if ($type==='exam') {
        $query="SELECT r.request_id,r.status,r.remarks AS reason,NULL AS batch_id,r.submitted_at,r.reviewed_at,r.archived_at,r.deleted_at,
                       ap.academic_year,ap.semester,ru.username AS reviewer
                FROM academic_exam_requests r
                JOIN academic_periods ap ON ap.academic_period_id=r.academic_period_id
                LEFT JOIN auth_users ru ON ru.user_id=r.reviewed_by
                WHERE r.status IN ('APPROVED','REJECTED') AND $predicate
                ORDER BY ".($view==='deleted'?'r.deleted_at DESC,':'r.reviewed_at DESC,')."r.request_id DESC LIMIT 500";
    } else {
        $query="SELECT r.request_id,r.status,r.reason,r.batch_id,r.submitted_at,r.reviewed_at,r.archived_at,r.deleted_at,
                       p.program_code,ap.academic_year,ap.semester,ru.username AS reviewer
                FROM academic_preview_requests r
                JOIN programs p ON p.program_id=r.program_id
                JOIN academic_periods ap ON ap.academic_period_id=r.academic_period_id
                LEFT JOIN auth_users ru ON ru.user_id=r.reviewed_by
                WHERE r.status IN ('APPROVED','REJECTED') AND $predicate
                ORDER BY ".($view==='deleted'?'r.deleted_at DESC,':'r.reviewed_at DESC,')."r.request_id DESC LIMIT 500";
    }
    $rows=$db->query($query)->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {
    error_log('Approval history: '.$e->getMessage());http_response_code(503);
    acadLayout('Approval History');echo '<h1>Approval History unavailable</h1><p>Check that the history migrations (022, 023, 027 and 030) are applied.</p>';acadEnd();exit;
}
function acadHistoryAction(int $id,string $action,string $label,string $confirmation,bool $danger=false,string $type='class'):string {
    return '<form method="post" action="preview-archive.php" data-history-confirm="'.acadH($confirmation).'">'
      .'<input type="hidden" name="csrf_token" value="'.acadH(authCsrf()).'">'
      .'<input type="hidden" name="id" value="'.$id.'">'
      .'<input type="hidden" name="request_type" value="'.acadH($type).'">'
      .'<input type="hidden" name="action" value="'.acadH($action).'">'
      .'<button type="submit" class="btn secondary'.($danger?' acad-delete-btn':'').'">'.acadH($label).'</button></form>';
}
acadLayout('Approval History');
$counts=['APPROVED'=>0,'REJECTED'=>0];
foreach($rows as $row){$state=strtoupper((string)$row['status']);if(isset($counts[$state]))$counts[$state]++;}
$viewName=['current'=>'Current','archived'=>'Archived','deleted'=>'Deleted'][$view];
$typeName=$type==='exam'?'Exam Schedule':'Class Schedule';
echo '<link rel="stylesheet" href="assets/history.css?v=20261121history3">';
echo '<section class="ah-head"><div><div class="eyebrow">ACADEMIC OFFICE / DECISION RECORDS</div><h1>Approval History</h1><p>Review completed decisions and find the right schedule without scrolling through unnecessary columns.</p></div><div class="ah-head-count"><strong>'.count($rows).'</strong><span>'.acadH($viewName).' records</span></div></section>';
echo '<div class="ah-type-tabs" role="navigation" aria-label="Schedule type">';
foreach(['class'=>['Class Schedule','Decisions by program'],'exam'=>['Exam Schedule','All-program exam decisions']] as $key=>$item){
  echo '<a class="ah-type-tab'.($type===$key?' is-active':'').'" href="history.php?type='.$key.'" '.($type===$key?'aria-current="page"':'').'><span>'.acadH($item[0]).'</span><small>'.acadH($item[1]).'</small></a>';
}
echo '</div>';
echo '<section class="ah-workspace"><div class="ah-toolbar"><div class="ah-history-tabs" role="navigation" aria-label="History category">';
foreach(['current'=>['Current',''],'archived'=>['Archived','&archived=1'],'deleted'=>['Deleted','&view=deleted']] as $key=>$item){
  echo '<a class="ah-history-tab'.($view===$key?' is-active':'').'" href="history.php?type='.$type.$item[1].'" '.($view===$key?'aria-current="page"':'').'>'.acadH($item[0]).'</a>';
}
echo '</div><span class="ah-context">'.acadH($typeName).' / '.acadH($viewName).' History</span></div>';
if($view==='deleted')echo '<p class="ah-explain">Deleted entries may be retrieved within 15 days. Permanent removal hides the history entry; it does not delete approved schedules or audit evidence.</p>';
elseif($view==='archived')echo '<p class="ah-explain">Archived records are kept for reference. Restore an entry to bring it back to Current History.</p>';
else echo '<p class="ah-explain">Approved and rejected requests are stored here after Academic Office review. Click View to inspect the original schedule.</p>';
echo '<div class="ah-filter-bar"><label class="ah-search-label" for="ahSearch">Search history<input id="ahSearch" type="search" placeholder="Search request, program, reviewer, notes..." autocomplete="off"></label><label for="ahDecision">Decision<select id="ahDecision"><option value="all">All decisions</option><option value="APPROVED">Approved</option><option value="REJECTED">Rejected</option></select></label><span class="ah-result-count" id="ahResultCount" aria-live="polite">'.count($rows).' records</span></div>';
echo '<div class="ah-table-scroll"><table class="ah-table" id="ahTable"><thead><tr><th>Request</th><th>Schedule / Period</th><th>Decision</th><th>Reviewed</th><th>Actions</th></tr></thead><tbody>';
foreach($rows as $r){
    $id=(int)$r['request_id'];
    $status=strtoupper((string)$r['status']);
    $program=$type==='exam'?'All Programs':(string)($r['program_code']??'');
    $period=(string)($r['academic_year']??'').' · Semester '.(int)($r['semester']??0);
    $reviewed=(string)($r['reviewed_at']??'');
    $stamp=$reviewed!==''?strtotime($reviewed):false;
    $displayStamp=$stamp?date('M j, Y', $stamp):'Not recorded';
    $reviewer=trim((string)($r['reviewer']??''));
    $reason=trim((string)($r['reason']??''));
    $batch=$type==='exam'?'School-wide':(string)($r['batch_id']??'Not saved');
    $due=$view==='deleted' && !empty($r['deleted_at'])?strtotime((string)$r['deleted_at'].' +15 days'):false;
    $searchText='#'.$id.' '.$program.' '.$period.' '.$status.' '.$reviewer.' '.$reason.' '.$batch;
    echo '<tr class="ah-data-row" data-search="'.acadH(strtolower($searchText)).'" data-decision="'.acadH($status).'">';
    echo '<td><strong class="ah-request-id">#'.$id.'</strong><small>'.($type==='exam'?'Exam request':'Class request').'</small></td>';
    echo '<td><strong>'.acadH($program).'</strong><small>'.acadH($period).'</small></td>';
    echo '<td><span class="ah-decision '.($status==='APPROVED'?'ah-approved':'ah-rejected').'">'.acadH(ucfirst(strtolower($status))).'</span></td>';
    echo '<td><strong>'.acadH($displayStamp).'</strong><small>By '.acadH($reviewer!==''?$reviewer:'Unknown reviewer').'</small></td>';
    echo '<td><div class="ah-row-actions">';
    if($view!=='deleted'){
      echo $type==='exam'?'<a class="ah-view" href="exam-requests.php?id='.$id.'&amp;history_embed=1" data-exam-history-view="'.$id.'">View Matrix</a>':'<a class="ah-view" href="document-view.php?id='.$id.'" data-document-view="'.$id.'">View</a>';
    }
    echo '<details class="ah-more"><summary aria-label="More actions for request '.$id.'">More <span aria-hidden="true">⌄</span></summary><div class="ah-more-menu">';
    if($view==='deleted'){
      echo acadHistoryAction($id,'retrieve','Retrieve','Restore this entry to Current history?',false,$type);
      echo acadHistoryAction($id,'permanent_delete','Delete permanently','Permanently hide this entry from Academic Office history? Approved schedules and audit evidence will remain.',true,$type);
    } else {
      echo acadHistoryAction($id,$view==='archived'?'restore':'archive',$view==='archived'?'Restore to Current':'Archive','Move this entry '.($view==='archived'?'back to Current history':'to Archived history').'?',false,$type);
      echo acadHistoryAction($id,'delete','Move to Deleted','Move this entry to Deleted History? You can retrieve it within 15 days.',true,$type);
    }
    echo '</div></details></div></td></tr>';
    echo '<tr class="ah-extra-row" hidden><td colspan="5"><div class="ah-extra-content"><span><b>Request:</b> #'.$id.'</span><span><b>Batch:</b> '.acadH($batch).'</span><span><b>Reviewed:</b> '.acadH($reviewed!==''?$reviewed:'Not recorded').'</span><span><b>Remarks:</b> '.acadH($reason!==''?$reason:'None provided').'</span>'.($due?'<span><b>Recover before:</b> '.acadH(date('M j, Y g:i A',$due)).'</span>':'').'</div></td></tr>';
}
echo '</tbody></table><div class="ah-empty" id="ahEmpty" hidden><strong>No matching records</strong><p>Try a different search or select All decisions.</p></div></div>';
echo '<div class="ah-bottom"><span id="ahPageInfo">'.count($rows).' total records</span><nav class="ah-pagination" aria-label="History pages"><button type="button" id="ahPrevious">Previous</button><span id="ahPageNumber">1</span><button type="button" id="ahNext">Next</button></nav></div>';
echo '</section>';
if($view!=='deleted'){
  if($type==='class') include __DIR__.'/document-modal.php';
  else include __DIR__.'/exam-history-modal.php';
}
echo '<script src="assets/history.js?v=20261121history3" defer></script>';
acadEnd();

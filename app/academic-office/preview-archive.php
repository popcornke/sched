<?php
declare(strict_types=1);
require_once __DIR__.'/shared.php';
require_once __DIR__.'/history-retention.php';
if (($_SERVER['REQUEST_METHOD']??'') !== 'POST') { http_response_code(405); exit('POST required'); }
if (!authCsrfValid($_POST['csrf_token']??null)) { http_response_code(403); exit('Invalid CSRF'); }
$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$action=(string)($_POST['action']??'');
if (!$id || !in_array($action,['archive','restore','delete','retrieve','permanent_delete'],true)) { http_response_code(422); exit('Invalid request'); }
$db=acadDb();
try {
    acadExpireDeletedHistory($db);
    $userId=(int)($_SESSION['auth_user_id']??0);
    if ($action==='delete') {
        $q=$db->prepare("UPDATE academic_preview_requests SET deleted_at=NOW(),deleted_by=?,purged_at=NULL,purged_by=NULL WHERE request_id=? AND deleted_at IS NULL AND purged_at IS NULL AND status IN ('APPROVED','REJECTED')");
        $q->execute([$userId,$id]);
    } elseif ($action==='retrieve') {
        // Once the 15-day deadline has passed, the entry cannot be restored.
        $q=$db->prepare("UPDATE academic_preview_requests SET deleted_at=NULL,deleted_by=NULL,archived_at=NULL,archived_by=NULL WHERE request_id=? AND deleted_at>DATE_SUB(NOW(),INTERVAL 15 DAY) AND purged_at IS NULL AND status IN ('APPROVED','REJECTED')");
        $q->execute([$id]);
    } elseif ($action==='permanent_delete') {
        // Purge from UI without deleting referenced schedules or approval evidence.
        $q=$db->prepare("UPDATE academic_preview_requests SET purged_at=NOW(),purged_by=? WHERE request_id=? AND deleted_at IS NOT NULL AND purged_at IS NULL AND status IN ('APPROVED','REJECTED')");
        $q->execute([$userId,$id]);
    } elseif ($action==='archive') {
        $q=$db->prepare("UPDATE academic_preview_requests SET archived_at=NOW(),archived_by=? WHERE request_id=? AND deleted_at IS NULL AND purged_at IS NULL AND archived_at IS NULL AND status IN ('APPROVED','REJECTED')");
        $q->execute([$userId,$id]);
    } else {
        $q=$db->prepare("UPDATE academic_preview_requests SET archived_at=NULL,archived_by=NULL WHERE request_id=? AND deleted_at IS NULL AND purged_at IS NULL AND archived_at IS NOT NULL AND status IN ('APPROVED','REJECTED')");
        $q->execute([$id]);
    }
    if ($q->rowCount()!==1) { http_response_code(409); exit('This entry is not available for the selected action. Refresh the history page.'); }
    $dest=match($action){'delete','permanent_delete','retrieve'=>'?view=deleted','restore'=>'?archived=1',default=>''};
    header('Location: history.php'.$dest, true, 303);
    exit;
} catch (Throwable $e) {
    error_log('Academic Office history action failed: '.$e->getMessage());
    http_response_code(503);exit('History unavailable. Verify migration 027 has been applied.');
}

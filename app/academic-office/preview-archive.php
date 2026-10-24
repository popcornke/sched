<?php
declare(strict_types=1);
require_once __DIR__.'/shared.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit('POST required');}
if(!authCsrfValid($_POST['csrf_token']??null)){http_response_code(403);exit('Invalid CSRF');}
$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$action=(string)($_POST['action']??'');
if(!$id||!in_array($action,['archive','restore','delete'],true)){http_response_code(422);exit('Invalid request');}
$db=acadDb();
try{
 if($action==='delete'){$q=$db->prepare("UPDATE academic_preview_requests SET deleted_at=NOW(),deleted_by=? WHERE request_id=? AND deleted_at IS NULL AND status IN ('APPROVED','REJECTED')");$q->execute([(int)$_SESSION['auth_user_id'],$id]);}
 elseif($action==='archive'){$q=$db->prepare("UPDATE academic_preview_requests SET archived_at=NOW(),archived_by=? WHERE request_id=? AND deleted_at IS NULL AND archived_at IS NULL AND status IN ('APPROVED','REJECTED')");$q->execute([(int)$_SESSION['auth_user_id'],$id]);}
 else {$q=$db->prepare("UPDATE academic_preview_requests SET archived_at=NULL,archived_by=NULL WHERE request_id=? AND deleted_at IS NULL AND archived_at IS NOT NULL AND status IN ('APPROVED','REJECTED')");$q->execute([$id]);}
 header('Location: history.php'.($action==='restore'?'?archived=1':''),true,303);exit;
}catch(Throwable $e){error_log('Archive action failed: '.$e->getMessage());http_response_code(503);exit('History archive not available. Apply migration 022.');}

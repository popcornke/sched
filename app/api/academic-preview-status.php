<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/shared/auth.php';
authRequire(false,['ADMIN']);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$p=filter_input(INPUT_GET,'program_id',FILTER_VALIDATE_INT);$a=filter_input(INPUT_GET,'academic_period_id',FILTER_VALIDATE_INT);
if(!$p||!$a){http_response_code(422);echo json_encode(['success'=>false,'message'=>'Invalid program or academic period']);exit;}
try{$q=authDb()->prepare('SELECT request_id,status,reason,submitted_at,reviewed_at,batch_id FROM academic_preview_requests WHERE program_id=? AND academic_period_id=? ORDER BY request_id DESC LIMIT 1');$q->execute([$p,$a]);$row=$q->fetch(PDO::FETCH_ASSOC);echo json_encode(['success'=>true,'request'=>$row?:null]);}
catch(Throwable $e){error_log('Academic preview status: '.$e->getMessage());http_response_code(503);echo json_encode(['success'=>false,'message'=>'Approval status unavailable']);}

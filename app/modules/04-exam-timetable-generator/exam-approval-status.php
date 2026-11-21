<?php
declare(strict_types=1);
require_once __DIR__.'/exam-global-common.php';
try {
 exGuard('GET');$period=filter_var($_GET['period_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if(!$period)exFail(422,'PERIOD_REQUIRED','Select an academic period.');
 $pdo=getDatabase();$q=$pdo->prepare('SELECT request_id,status,remarks,submitted_at,reviewed_at FROM academic_exam_requests WHERE academic_period_id=? ORDER BY request_id DESC LIMIT 1');$q->execute([$period]);$item=$q->fetch(PDO::FETCH_ASSOC);
 exReply(200,['success'=>true,'request'=>$item?:null]);
}catch(ExamFailure $e){exReply($e->http,['success'=>false,'message'=>$e->getMessage()]);}
catch(Throwable $e){exReply(500,['success'=>false,'message'=>'Exam approval status unavailable. Check migration 029.']);}

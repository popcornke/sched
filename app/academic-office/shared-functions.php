<?php
declare(strict_types=1);
function acadDb(): PDO {return authDb();}
function acadH($s): string {return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function acadFingerprint(PDO $db,string $type,int $batch): string {
 $table=$type==='CLASS'?'schedule_meetings':'exam_meetings';
 $key=$type==='CLASS'?'batch_id':'exam_batch_id';
 $id=$type==='CLASS'?'meeting_id':'exam_meeting_id';
 $q=$db->prepare("SELECT * FROM $table WHERE $key=? ORDER BY $id");$q->execute([$batch]);
 $rows=$q->fetchAll(PDO::FETCH_ASSOC);
 if (!$rows) throw new RuntimeException('Batch contains no meetings.');
 return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
}
function acadBatch(PDO $db,string $type,int $batch): ?array {
 $table=$type==='CLASS'?'schedule_batches':'exam_batches';$key=$type==='CLASS'?'batch_id':'exam_batch_id';
 $q=$db->prepare("SELECT $key AS batch_id,academic_period_id,program_id,status FROM $table WHERE $key=?");$q->execute([$batch]);return $q->fetch(PDO::FETCH_ASSOC)?:null;
}
function acadLog(PDO $db,int $approval,int $actor,string $action,string $remarks=''):void {
 $q=$db->prepare('INSERT INTO academic_approval_actions(approval_id,actor_id,action_type,remarks) VALUES(?,?,?,?)');$q->execute([$approval,$actor,$action,$remarks]);
}

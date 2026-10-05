<?php
declare(strict_types=1);

require_once __DIR__ . '/conflict-repair-common.php';
require_once __DIR__ . '/conflict-engine.php';

$pdo = null;
$held = false;
$lockName = '';
$committed = false;
set_time_limit(180);

try {
    ccrGuardPost();
    $body = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($body) || ($body['confirm'] ?? false) !== true || !is_string($body['repair_token'] ?? null)) {
        ccrReject(400, 'CONFIRMATION_REQUIRED', 'Confirm the server-side conflict repair preview first.');
    }

    ccrSession();
    $preview = $_SESSION['conflict_repair_preview'] ?? null;
    if (!is_array($preview) || !hash_equals((string)($preview['token'] ?? ''), (string)$body['repair_token'])) {
        ccrReject(403, 'INVALID_REPAIR_TOKEN', 'Conflict repair preview does not match this session.');
    }
    if (time() - (int)($preview['created_at'] ?? 0) > 900) {
        ccrReject(409, 'REPAIR_PREVIEW_EXPIRED', 'Conflict repair preview expired. Run Solve again.');
    }
    session_write_close();

    $periodId = (int)$preview['period_id'];
    $programCode = (string)$preview['program_code'];
    $batchId = (int)$preview['batch_id'];
    $assignments = $preview['assignments'] ?? null;
    if (!is_array($assignments)) ccrReject(409, 'REPAIR_PREVIEW_INVALID', 'Stored repair preview is incomplete.');

    $pdo = getDatabase();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $lockName = 'BCP_CONFLICT_REPAIR_PERIOD_' . $periodId;
    $lock = $pdo->prepare('SELECT GET_LOCK(:name,10)');
    $lock->execute(['name'=>$lockName]);
    if ((int)$lock->fetchColumn() !== 1) ccrReject(409, 'REPAIR_BUSY', 'Another timetable write is in progress. Retry.');
    $held = true;
    $pdo->beginTransaction();

    $fresh = ccrLoadInput($pdo, $periodId, $programCode);
    if ((int)$fresh['batch_id'] !== $batchId || !hash_equals((string)$preview['baseline_sha256'], (string)$fresh['baseline_sha256'])) {
        ccrReject(409, 'STALE_REPAIR_PREVIEW', 'Saved timetable changed after Solve. Run the conflict check and Solve again.');
    }
    ccrValidateAssignments($fresh, $assignments);

    // Independent Python hard-conflict audit against current DB facts.
    $audit = ccrCallPython('/api/conflicts/repair/audit', ['input'=>$fresh,'assignments'=>$assignments], 90);
    if (($audit['passed'] ?? false) !== true || ($audit['status'] ?? '') !== 'REPAIR_AUDIT_PASSED') {
        ccrReject(422, 'FINAL_REPAIR_AUDIT_FAILED', 'Final conflict-repair audit failed. No timetable changes were saved.');
    }

    $update = $pdo->prepare("UPDATE schedule_meetings
        SET teacher_id=:teacher_id,room_id=:room_id,day_of_week=:day_of_week,start_time=:start_time,end_time=:end_time
        WHERE meeting_id=:meeting_id AND batch_id=:batch_id");
    foreach ($assignments as $m) {
        $update->execute([
            'teacher_id'=>(int)$m['teacher_id'],
            'room_id'=>$m['room_id']===null ? null : (int)$m['room_id'],
            'day_of_week'=>(string)$m['day_of_week'],
            'start_time'=>(string)$m['start_time'] . ':00',
            'end_time'=>(string)$m['end_time'] . ':00',
            'meeting_id'=>(int)$m['meeting_id'],
            'batch_id'=>$batchId,
        ]);
    }

    // Re-run the existing PHP school-wide saved-record audit BEFORE commit.
    $batchStmt = $pdo->prepare("SELECT b.batch_id,b.program_id,p.program_code,
        (SELECT COUNT(*) FROM schedule_meetings m WHERE m.batch_id=b.batch_id) AS meeting_count
        FROM schedule_batches b JOIN programs p ON p.program_id=b.program_id
        WHERE b.academic_period_id=:pid AND b.status='ACTIVE' AND b.data_origin='DEMO'
        ORDER BY b.program_id,b.batch_id");
    $batchStmt->execute(['pid'=>$periodId]);
    $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

    $meetStmt = $pdo->prepare("SELECT m.meeting_id,m.batch_id,b.academic_period_id,b.program_id AS batch_program_id,
        p.program_code,m.section_subject_id,ss.section_id,ss.subject_id,sec.section_code,sec.section_type,
        sec.program_id AS section_program_id,sec.academic_period_id AS section_period_id,sec.student_count AS section_student_count,
        subj.subject_code,subj.program_id AS subject_program_id,m.teacher_id,t.program_id AS teacher_program_id,
        m.room_id,r.program_id AS room_program_id,r.capacity AS room_capacity,m.delivery_mode,m.day_of_week,
        TIME_FORMAT(m.start_time,'%H:%i') AS start_time,TIME_FORMAT(m.end_time,'%H:%i') AS end_time
        FROM schedule_meetings m JOIN schedule_batches b ON b.batch_id=m.batch_id JOIN programs p ON p.program_id=b.program_id
        LEFT JOIN section_subjects ss ON ss.section_subject_id=m.section_subject_id
        LEFT JOIN sections sec ON sec.section_id=ss.section_id LEFT JOIN subjects subj ON subj.subject_id=ss.subject_id
        LEFT JOIN teachers t ON t.teacher_id=m.teacher_id LEFT JOIN rooms r ON r.room_id=m.room_id
        WHERE b.academic_period_id=:pid AND b.status='ACTIVE' AND b.data_origin='DEMO' ORDER BY m.meeting_id");
    $meetStmt->execute(['pid'=>$periodId]);
    $allMeetings = $meetStmt->fetchAll(PDO::FETCH_ASSOC);

    $expectedStmt = $pdo->prepare("SELECT b.batch_id,ss.section_subject_id
        FROM schedule_batches b JOIN sections sec ON sec.program_id=b.program_id AND sec.academic_period_id=b.academic_period_id
        JOIN section_subjects ss ON ss.section_id=sec.section_id
        WHERE b.academic_period_id=:pid AND b.status='ACTIVE' AND b.data_origin='DEMO'
          AND sec.is_active=1 AND sec.data_origin='DEMO' ORDER BY b.batch_id,ss.section_subject_id");
    $expectedStmt->execute(['pid'=>$periodId]);
    $expected = $expectedStmt->fetchAll(PDO::FETCH_ASSOC);

    $memberStmt = $pdo->prepare("SELECT student_id,home_section_id,major_section_id,program_id
        FROM students WHERE academic_period_id=:pid AND data_origin='DEMO'");
    $memberStmt->execute(['pid'=>$periodId]);
    $memberships = $memberStmt->fetchAll(PDO::FETCH_ASSOC);

    $phpAudit = ccAudit($allMeetings, $expected, $memberships, $batches, true);
    if ((int)$phpAudit['total_issues'] !== 0) {
        error_log('Conflict repair PHP audit: ' . json_encode($phpAudit['issues'], JSON_UNESCAPED_UNICODE));
        ccrReject(422, 'PHP_FINAL_AUDIT_FAILED', 'The repaired timetable still has saved-schedule conflicts. All changes were rolled back.');
    }

    $pdo->commit();
    $committed = true;

    try {
        ccrSession();
        if (hash_equals((string)($_SESSION['conflict_repair_preview']['token'] ?? ''), (string)$body['repair_token'])) {
            unset($_SESSION['conflict_repair_preview']);
        }
        session_write_close();
    } catch (Throwable $cleanupError) {
        error_log('Conflict repair token cleanup: ' . $cleanupError->getMessage());
    }

    ccrReply(200, [
        'success'=>true,
        'status'=>'CONFLICT_REPAIR_APPLIED',
        'program_code'=>$programCode,
        'batch_id'=>$batchId,
        'changed_meetings'=>count($preview['changes'] ?? []),
        'remaining_conflicts'=>0,
        'database_write'=>true,
        'message'=>'Conflict repair was applied to the existing ACTIVE timetable. Meeting identities and batch ID were preserved.',
    ]);
} catch (ConflictRepairRejected $e) {
    ccrReply($e->http, ['success'=>false,'status'=>$e->codeName,'message'=>$e->getMessage(),'database_write'=>$committed]);
} catch (Throwable $e) {
    error_log('Conflict repair apply failed: ' . $e->getMessage());
    ccrReply(500, ['success'=>false,'status'=>'CONFLICT_REPAIR_APPLY_FAILED','message'=>$committed ? 'Repair committed but a later response step failed; refresh the conflict checker.' : 'Repair was not committed. Existing timetable remains unchanged.','database_write'=>$committed]);
} finally {
    if ($pdo instanceof PDO) {
        if ($pdo->inTransaction()) {
            try { $pdo->rollBack(); } catch (Throwable $rollbackError) { error_log($rollbackError->getMessage()); }
        }
        if ($held) {
            try {
                $release = $pdo->prepare('SELECT RELEASE_LOCK(:name)');
                $release->execute(['name'=>$lockName]);
            } catch (Throwable $releaseError) { error_log($releaseError->getMessage()); }
        }
    }
}

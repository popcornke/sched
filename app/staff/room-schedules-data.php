<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/shared/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
function roomReply(int $code, array $data): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
try {
    authRequire(true, ['STAFF']);
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') roomReply(405, ['success' => false, 'message' => 'GET only.']);
    $pdo = getDatabase();
    $mode = (string)($_GET['mode'] ?? 'options');
    if ($mode === 'options') {
        $periods = $pdo->query("SELECT ap.academic_period_id, ap.academic_year, ap.semester FROM academic_periods ap WHERE EXISTS (SELECT 1 FROM schedule_batches b WHERE b.academic_period_id=ap.academic_period_id AND b.status='ACTIVE') ORDER BY ap.academic_year DESC,ap.semester DESC")->fetchAll();
        $departments = $pdo->query("SELECT p.program_id,p.program_code,p.program_name, COUNT(r.room_id) AS room_count FROM programs p INNER JOIN rooms r ON r.program_id=p.program_id GROUP BY p.program_id,p.program_code,p.program_name ORDER BY p.program_code")->fetchAll();
        roomReply(200, ['success'=>true, 'periods'=>$periods, 'departments'=>$departments]);
    }
    if ($mode !== 'schedule') roomReply(400, ['success'=>false,'message'=>'Invalid request.']);
    $periodId = filter_var($_GET['period_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $programId = filter_var($_GET['program_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (!$periodId || !$programId) roomReply(400, ['success'=>false,'message'=>'Choose an academic period and department.']);
    $st = $pdo->prepare('SELECT program_id,program_code,program_name FROM programs WHERE program_id=:program LIMIT 1');
    $st->execute(['program'=>$programId]);
    $department = $st->fetch();
    if (!$department) roomReply(404, ['success'=>false,'message'=>'Department not found.']);
    $st = $pdo->prepare('SELECT academic_year,semester FROM academic_periods WHERE academic_period_id=:period LIMIT 1');
    $st->execute(['period'=>$periodId]);
    $period = $st->fetch();
    if (!$period) roomReply(404, ['success'=>false,'message'=>'Academic period not found.']);
    // A printed room must be AVAILABLE and contain at least one ACTIVE F2F meeting
    // for the requested period (including classes from shared departments).
    $st = $pdo->prepare(<<<'SQL'
SELECT r.room_id,r.room_name,r.building,r.status
FROM rooms r
WHERE r.program_id = :program
  AND UPPER(TRIM(r.status)) = 'AVAILABLE'
  AND EXISTS (
    SELECT 1 FROM schedule_meetings m
    JOIN schedule_batches b ON b.batch_id = m.batch_id
    WHERE m.room_id = r.room_id
      AND b.academic_period_id = :period
      AND b.status = 'ACTIVE'
      AND m.delivery_mode = 'F2F'
  )
ORDER BY r.room_name,r.room_id
SQL);
    $st->execute(['program'=>$programId,'period'=>$periodId]);
    $rooms = $st->fetchAll();
    // Get physical meetings occupying any room owned by this department, regardless of the class's program.
    // A room can be shared, so intentionally do not filter b.program_id by selected department.
    $st = $pdo->prepare(<<<'SQL'
SELECT m.meeting_id,m.room_id,m.day_of_week,
       TIME_FORMAT(m.start_time,'%H:%i') AS start_time,
       TIME_FORMAT(m.end_time,'%H:%i') AS end_time,
       p.program_code,sec.section_code,sub.subject_code,sub.subject_title
FROM schedule_meetings m
JOIN rooms r ON r.room_id=m.room_id AND r.program_id=:program AND UPPER(TRIM(r.status))='AVAILABLE'
JOIN schedule_batches b ON b.batch_id=m.batch_id AND b.status='ACTIVE'
JOIN section_subjects ss ON ss.section_subject_id=m.section_subject_id
JOIN sections sec ON sec.section_id=ss.section_id AND sec.academic_period_id=b.academic_period_id AND sec.program_id=b.program_id
JOIN subjects sub ON sub.subject_id=ss.subject_id
JOIN programs p ON p.program_id=b.program_id
WHERE b.academic_period_id=:period AND m.delivery_mode='F2F'
ORDER BY r.room_name, FIELD(m.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),m.start_time,m.end_time,p.program_code,sec.section_code
SQL);
    $st->execute(['period'=>$periodId,'program'=>$programId]);
    $meetings = $st->fetchAll();
    roomReply(200, ['success'=>true,'department'=>$department,'rooms'=>$rooms,'period'=>$period,'meetings'=>$meetings,'database_write'=>false]);

} catch (Throwable $e) {
    error_log('Staff room schedules: '.$e->getMessage());
    roomReply(500, ['success'=>false,'message'=>'Unable to load room schedules. Check server logs.']);
}

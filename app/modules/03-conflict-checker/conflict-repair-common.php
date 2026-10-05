<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/python.php';
require_once dirname(__DIR__, 2) . '/shared/auth.php';

authRequire(true, ['ADMIN', 'SCHEDULER']);

final class ConflictRepairRejected extends RuntimeException
{
    public function __construct(
        public readonly int $http,
        public readonly string $codeName,
        string $message
    ) {
        parent::__construct($message);
    }
}

function ccrReject(int $http, string $status, string $message): never
{
    throw new ConflictRepairRejected($http, $status, $message);
}

function ccrReply(int $http, array $body): never
{
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function ccrGuardPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        ccrReject(405, 'METHOD_NOT_ALLOWED', 'POST only.');
    }
    if (!function_exists('curl_init')) {
        ccrReject(500, 'CURL_UNAVAILABLE', 'PHP cURL is required for the conflict repair solver.');
    }
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $origin = parse_url((string)$_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
        $host = strtolower((string)explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]);
        if (!is_string($origin) || strtolower($origin) !== $host) {
            ccrReject(403, 'CROSS_ORIGIN_REQUEST', 'Cross-origin write request denied.');
        }
    }
}

function ccrSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    session_name('BCP_CONFLICT_REPAIR');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Strict',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'path' => '/',
    ]);
    if (!session_start()) {
        ccrReject(500, 'SESSION_UNAVAILABLE', 'Unable to open conflict-repair session.');
    }
}

function ccrJsonHash(array $value): string
{
    return hash('sha256', json_encode(
        $value,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
    ));
}

function ccrCallPython(string $path, array $payload, int $timeout = 120): array
{
    $url = rtrim(pythonBaseUrl(), '/') . $path;
    $curl = curl_init($url);
    if ($curl === false) ccrReject(502, 'SOLVER_UNAVAILABLE', 'Unable to start conflict repair request.');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
    ]);
    $raw = curl_exec($curl);
    $http = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($raw === false) {
        error_log('Conflict repair solver connection failed: ' . $error);
        ccrReject(502, 'SOLVER_UNAVAILABLE', 'Python conflict repair request timed out or the solver connection failed.');
    }
    try {
        $data = json_decode((string)$raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        error_log('Conflict repair invalid Python JSON: ' . $raw);
        ccrReject(502, 'INVALID_SOLVER_RESPONSE', 'Python conflict repair solver returned invalid JSON.');
    }
    if (!is_array($data)) ccrReject(502, 'INVALID_SOLVER_RESPONSE', 'Python conflict repair solver returned an invalid response.');
    if ($http !== 200) {
        $message = $data['detail'] ?? $data['message'] ?? 'Conflict repair solver rejected the request.';
        ccrReject($http >= 400 && $http < 600 ? $http : 502, 'REPAIR_SOLVER_REJECTED', (string)$message);
    }
    return $data;
}

function ccrRows(PDO $pdo, string $sql, array $params = []): array
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function ccrLoadInput(PDO $pdo, int $periodId, string $programCode): array
{
    $programCode = strtoupper(trim($programCode));
    if (!preg_match('/^[A-Z0-9-]{2,30}$/', $programCode)) {
        ccrReject(400, 'INVALID_PROGRAM', 'Choose a valid repair program.');
    }

    $periodRows = ccrRows($pdo, "SELECT academic_period_id,academic_year,semester,period_status
        FROM academic_periods WHERE academic_period_id=:period AND period_status='DEMO' LIMIT 1", ['period'=>$periodId]);
    if (count($periodRows) !== 1) ccrReject(404, 'DEMO_PERIOD_NOT_FOUND', 'Selected DEMO academic period was not found.');

    $programRows = ccrRows($pdo, "SELECT program_id,program_code,program_name
        FROM programs WHERE program_code=:code AND is_active=1 LIMIT 1", ['code'=>$programCode]);
    if (count($programRows) !== 1) ccrReject(404, 'PROGRAM_NOT_FOUND', 'Selected program was not found or is inactive.');
    $program = $programRows[0];
    $programId = (int)$program['program_id'];

    $batches = ccrRows($pdo, "SELECT batch_id FROM schedule_batches
        WHERE academic_period_id=:period AND program_id=:program AND data_origin='DEMO' AND status='ACTIVE'
        ORDER BY batch_id", ['period'=>$periodId,'program'=>$programId]);
    if (count($batches) !== 1) {
        ccrReject(409, 'ONE_ACTIVE_BATCH_REQUIRED', 'Exactly one ACTIVE DEMO timetable is required for automatic repair.');
    }
    $batchId = (int)$batches[0]['batch_id'];

    $meetingSql = <<<'SQL'
SELECT m.meeting_id,m.batch_id,m.section_subject_id,ss.section_id,ss.subject_id,
       sec.section_code,sec.section_type,sec.year_level,sec.student_count AS section_student_count,
       subj.subject_code,subj.subject_title,
       m.teacher_id,t.teacher_name,
       m.room_id,r.room_name,
       m.delivery_mode,m.day_of_week,
       TIME_FORMAT(m.start_time,'%H:%i') AS start_time,
       TIME_FORMAT(m.end_time,'%H:%i') AS end_time
FROM schedule_meetings m
JOIN section_subjects ss ON ss.section_subject_id=m.section_subject_id
JOIN sections sec ON sec.section_id=ss.section_id
JOIN subjects subj ON subj.subject_id=ss.subject_id
JOIN teachers t ON t.teacher_id=m.teacher_id
LEFT JOIN rooms r ON r.room_id=m.room_id
WHERE m.batch_id=:batch
ORDER BY m.meeting_id
SQL;
    $meetings = ccrRows($pdo, $meetingSql, ['batch'=>$batchId]);
    if (!$meetings) ccrReject(409, 'EMPTY_ACTIVE_BATCH', 'The selected ACTIVE timetable has no saved meetings.');

    $otherMeetingSql = <<<'SQL'
SELECT m.meeting_id,b.program_id,m.section_subject_id,ss.section_id,ss.subject_id,
       m.teacher_id,m.room_id,m.delivery_mode,m.day_of_week,
       TIME_FORMAT(m.start_time,'%H:%i') AS start_time,
       TIME_FORMAT(m.end_time,'%H:%i') AS end_time
FROM schedule_meetings m
JOIN schedule_batches b ON b.batch_id=m.batch_id
JOIN section_subjects ss ON ss.section_subject_id=m.section_subject_id
WHERE b.academic_period_id=:period AND b.status='ACTIVE' AND b.data_origin='DEMO'
  AND b.program_id<>:program
ORDER BY m.meeting_id
SQL;
    $otherMeetings = ccrRows($pdo, $otherMeetingSql, ['period'=>$periodId,'program'=>$programId]);

    $teachers = ccrRows($pdo, "SELECT teacher_id,program_id,employee_no,teacher_name,max_daily_hours,max_weekly_hours,status
        FROM teachers WHERE program_id=:program AND status='ACTIVE' ORDER BY teacher_id", ['program'=>$programId]);
    $authorizations = ccrRows($pdo, "SELECT tsa.teacher_id,tsa.subject_id
        FROM teacher_subject_authorizations tsa
        JOIN teachers t ON t.teacher_id=tsa.teacher_id
        JOIN subjects s ON s.subject_id=tsa.subject_id
        WHERE t.program_id=:teacher_program
          AND s.program_id=:subject_program
          AND t.status='ACTIVE'
          AND s.is_active=1
        ORDER BY tsa.teacher_id,tsa.subject_id", [
            'teacher_program'=>$programId,
            'subject_program'=>$programId,
        ]);
    $teacherAvailability = ccrRows($pdo, "SELECT ta.teacher_id,ta.day_of_week,
        TIME_FORMAT(ta.start_time,'%H:%i') AS start_time,TIME_FORMAT(ta.end_time,'%H:%i') AS end_time,ta.availability_status AS status
        FROM teacher_availability ta JOIN teachers t ON t.teacher_id=ta.teacher_id
        WHERE ta.academic_period_id=:period AND t.program_id=:program AND t.status='ACTIVE'
        ORDER BY ta.teacher_id,FIELD(ta.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),ta.start_time",
        ['period'=>$periodId,'program'=>$programId]);

    $rooms = ccrRows($pdo, "SELECT room_id,program_id,room_name,building,capacity,room_type,status
        FROM rooms WHERE status='AVAILABLE' AND (program_id IS NULL OR program_id=:program) ORDER BY room_id", ['program'=>$programId]);
    $roomAvailability = ccrRows($pdo, "SELECT ra.room_id,ra.day_of_week,
        TIME_FORMAT(ra.start_time,'%H:%i') AS start_time,TIME_FORMAT(ra.end_time,'%H:%i') AS end_time,ra.availability_status AS status
        FROM room_availability ra JOIN rooms r ON r.room_id=ra.room_id
        WHERE ra.academic_period_id=:period AND r.status='AVAILABLE' AND (r.program_id IS NULL OR r.program_id=:program)
        ORDER BY ra.room_id,FIELD(ra.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),ra.start_time",
        ['period'=>$periodId,'program'=>$programId]);

    $timeSlots = ccrRows($pdo, "SELECT time_slot_id,day_of_week,day_pattern,
        TIME_FORMAT(start_time,'%H:%i') AS start_time,TIME_FORMAT(end_time,'%H:%i') AS end_time,is_active
        FROM time_slots WHERE data_origin='DEMO' AND is_active=1
        ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),start_time", []);

    $sharedPairs = ccrRows($pdo, "SELECT DISTINCT
            LEAST(home_section_id,major_section_id) AS section_a,
            GREATEST(home_section_id,major_section_id) AS section_b
        FROM students
        WHERE academic_period_id=:period AND data_origin='DEMO'
          AND major_section_id IS NOT NULL AND major_section_id<>home_section_id",
        ['period'=>$periodId]);

    $input = [
        'program' => [
            'program_id' => $programId,
            'program_code' => (string)$program['program_code'],
            'program_name' => (string)$program['program_name'],
        ],
        'period' => [
            'academic_period_id' => (int)$periodRows[0]['academic_period_id'],
            'academic_year' => (string)$periodRows[0]['academic_year'],
            'semester' => (int)$periodRows[0]['semester'],
        ],
        'batch_id' => $batchId,
        'meetings' => $meetings,
        'other_meetings' => $otherMeetings,
        'teachers' => $teachers,
        'teacher_authorizations' => $authorizations,
        'teacher_availability' => $teacherAvailability,
        'rooms' => $rooms,
        'room_availability' => $roomAvailability,
        'time_slots' => $timeSlots,
        'shared_section_pairs' => $sharedPairs,
    ];
    $input['baseline_sha256'] = ccrJsonHash($meetings);
    return $input;
}

function ccrValidateAssignments(array $input, array $assignments): void
{
    $old = [];
    foreach ($input['meetings'] as $meeting) $old[(int)$meeting['meeting_id']] = $meeting;
    if (count($assignments) !== count($old)) ccrReject(422, 'MEETING_COUNT_CHANGED', 'Repair preview changed the number of saved meetings.');
    $seen = [];
    foreach ($assignments as $meeting) {
        $id = (int)($meeting['meeting_id'] ?? 0);
        if ($id < 1 || !isset($old[$id]) || isset($seen[$id])) ccrReject(422, 'MEETING_IDENTITY_CHANGED', 'Repair preview changed meeting identity.');
        $seen[$id] = true;
        foreach (['section_subject_id','section_id','subject_id','delivery_mode','day_of_week'] as $field) {
            if ((string)($meeting[$field] ?? '') !== (string)($old[$id][$field] ?? '')) {
                ccrReject(422, 'PROTECTED_FIELD_CHANGED', 'Repair attempted to change a protected meeting field.');
            }
        }
    }
}

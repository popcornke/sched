<?php
declare(strict_types=1);

/** Module 4 DEMO shared DB loader + independent exam result validation. */
require_once __DIR__ . '/../../config/database.php';

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string)$_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}

final class ExamFailure extends RuntimeException {
    public function __construct(public readonly int $http, public readonly string $errorCode, string $message) {
        parent::__construct($message);
    }
}

function exFail(int $http, string $code, string $message): never {
    throw new ExamFailure($http, $code, $message);
}

function exReply(int $http, array $body): never {
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    exit;
}

function exGuard(string $method): void {
    if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
        exFail(403, 'LOCAL_DEMO_ONLY', 'DEMO exam management is limited to localhost.');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        exFail(405, 'METHOD_NOT_ALLOWED', $method . ' only.');
    }
}

function exRows(PDO $pdo, string $sql, array $params = []): array {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function exJsonHash(mixed $value): string {
    return hash('sha256', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));
}

function exTime(string $value): int {
    if (!preg_match('/^([01][0-9]|2[0-4]):([0-5][0-9])(?::00)?$/', $value, $m)) {
        exFail(422, 'INVALID_EXAM_TIME', 'Invalid exam time.');
    }
    
    $minute = (int)$m[1] * 60 + (int)$m[2];
    
    if ($minute > 1440 || ($minute === 1440 && (int)$m[2] !== 0)) {
        exFail(422, 'INVALID_EXAM_TIME', 'Invalid exam time.');
    }
    
    return $minute;
}

function exOverlap(int $a, int $b, int $c, int $d): bool {
    return $a < $d && $c < $b;
}

function exCovers(array $windows, int $id, string $day, int $start, int $end): bool {
    $available = false;
    foreach ($windows as $w) {
        if ((int)$w['id'] !== $id || $w['day_of_week'] !== $day) continue;
        
        $cross = exOverlap($start, $end, exTime($w['start_time']), exTime($w['end_time']));
        
        if ($w['availability_status'] === 'UNAVAILABLE' && $cross) {
            return false;
        }
        if ($w['availability_status'] === 'AVAILABLE' && exTime($w['start_time']) <= $start && $end <= exTime($w['end_time'])) {
            $available = true;
        }
    }
    return $available;
}

function exDates(array $raw): array {
    if (count($raw) !== 3) {
        exFail(400, 'INVALID_DATES', 'Select three examination dates.');
    }
    
    $result = [];
    $last = '';
    
    foreach ($raw as $v) {
        if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            exFail(400, 'INVALID_DATES', 'Invalid exam date.');
        }
        
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        
        if (!$d || $d->format('Y-m-d') !== $v || ($last !== '' && $last >= $v) || $d->format('N') === '7') {
            exFail(400, 'INVALID_DATES', 'Choose three ascending distinct Monday–Saturday examination dates.');
        }
        
        $result[] = $v;
        $last = $v;
    }
    
    return $result;
}

/** Build a stable snapshot exclusively from DB. No client-supplied faculty, sections, students or assignments. */
function exBuildInput(PDO $pdo, int $periodId, array $dates, string $window, ?int $replaceBatchId = null): array {
    $dates = exDates($dates);
    
    if (!in_array($window, ['06-21', '18-21'], true)) {
        exFail(400, 'INVALID_WINDOW', 'Invalid examination time window.');
    }
    
    $period = exRows($pdo, "
        SELECT academic_period_id, academic_year, semester, period_status 
        FROM academic_periods 
        WHERE academic_period_id = :id AND period_status = 'DEMO'
    ", ['id' => $periodId]);
    
    $program = exRows($pdo, "
        SELECT program_id, program_code, program_name 
        FROM programs 
        WHERE program_code = 'BSIT' AND is_active = 1 
        LIMIT 1
    ");
    
    if (count($period) !== 1 || count($program) !== 1) {
        exFail(404, 'PERIOD_OR_PROGRAM_MISSING', 'BSIT DEMO or selected period was not found.');
    }
    
    $programId = (int)$program[0]['program_id'];
    $common = ['program' => $programId, 'period' => $periodId];
    
    $batches = exRows($pdo, "
        SELECT batch_id 
        FROM schedule_batches 
        WHERE program_id = :program 
          AND academic_period_id = :period 
          AND status = 'ACTIVE' 
          AND data_origin = 'DEMO' 
        ORDER BY batch_id
    ", $common);
    
    if (count($batches) !== 1) {
        exFail(409, 'ACTIVE_CLASS_BATCH_REQUIRED', 'Exactly one ACTIVE BSIT DEMO class batch is required.');
    }
    
    $classBatch = (int)$batches[0]['batch_id'];
    
    $sections = exRows($pdo, "
        SELECT section_id, section_code, section_type, student_count, year_level, program_id 
        FROM sections 
        WHERE program_id = :program 
          AND academic_period_id = :period 
          AND data_origin = 'DEMO' 
          AND is_active = 1 
        ORDER BY year_level, section_code, section_id
    ", $common);
    
    $exams = exRows($pdo, "
        SELECT ss.section_subject_id, ss.section_id, ss.subject_id, s.subject_code, s.subject_title, m.teacher_id AS proctor_id 
        FROM section_subjects ss 
        JOIN sections sec ON sec.section_id = ss.section_id 
        JOIN subjects s ON s.subject_id = ss.subject_id 
        JOIN schedule_meetings m ON m.section_subject_id = ss.section_subject_id 
          AND m.batch_id = :batch 
          AND m.delivery_mode = 'F2F' 
        WHERE sec.program_id = :program 
          AND sec.academic_period_id = :period 
          AND sec.data_origin = 'DEMO' 
          AND sec.is_active = 1 
          AND ss.data_origin = 'DEMO' 
          AND s.program_id = sec.program_id 
          AND s.semester = :semester 
          AND s.is_active = 1 
        ORDER BY ss.section_subject_id
    ", ['batch' => $classBatch, 'program' => $programId, 'period' => $periodId, 'semester' => $period[0]['semester']]);
    
    $required = exRows($pdo, "
        SELECT COUNT(*) AS n 
        FROM section_subjects ss 
        JOIN sections sec ON sec.section_id = ss.section_id 
        WHERE sec.program_id = :program 
          AND sec.academic_period_id = :period 
          AND sec.data_origin = 'DEMO' 
          AND sec.is_active = 1 
          AND ss.data_origin = 'DEMO'
    ", $common);
    
    if (!$sections || !$exams || count($exams) !== (int)$required[0]['n']) {
        exFail(409, 'INCOMPLETE_EXAM_MAPPING', 'Every DEMO section-subject must have a saved F2F teacher.');
    }
    
    $teachers = exRows($pdo, "
        SELECT teacher_id, teacher_name, program_id 
        FROM teachers 
        WHERE program_id = :program 
          AND status = 'ACTIVE' 
          AND data_origin = 'DEMO' 
        ORDER BY teacher_id
    ", ['program' => $programId]);
    
    $rooms = exRows($pdo, "
        SELECT room_id, room_name, program_id, capacity 
        FROM rooms 
        WHERE status = 'AVAILABLE' 
          AND data_origin = 'DEMO' 
          AND (program_id = :program OR program_id IS NULL) 
        ORDER BY room_id
    ", ['program' => $programId]);
    
    $roomAvailability = exRows($pdo, "
        SELECT ra.room_id, ra.day_of_week, TIME_FORMAT(ra.start_time,'%H:%i') AS start_time, TIME_FORMAT(ra.end_time,'%H:%i') AS end_time, ra.availability_status 
        FROM room_availability ra 
        JOIN rooms r ON r.room_id = ra.room_id 
        WHERE ra.academic_period_id = :period 
          AND ra.data_origin = 'DEMO' 
          AND r.status = 'AVAILABLE' 
        ORDER BY ra.room_id, ra.day_of_week, ra.start_time, ra.end_time, ra.availability_status
    ", ['period' => $periodId]);
    
    $teacherAvailability = exRows($pdo, "
        SELECT teacher_id, day_of_week, TIME_FORMAT(start_time,'%H:%i') AS start_time, TIME_FORMAT(end_time,'%H:%i') AS end_time, availability_status 
        FROM teacher_availability 
        WHERE academic_period_id = :period 
          AND data_origin = 'DEMO' 
        ORDER BY teacher_id, day_of_week, start_time, end_time, availability_status
    ", ['period' => $periodId]);
    
    $slots = exRows($pdo, "
        SELECT day_of_week, TIME_FORMAT(start_time,'%H:%i') AS start_time, TIME_FORMAT(end_time,'%H:%i') AS end_time 
        FROM time_slots 
        WHERE is_active = 1 
          AND data_origin = 'DEMO' 
        ORDER BY day_of_week, start_time, end_time
    ");
    
    $students = exRows($pdo, "
        SELECT student_id, home_section_id, major_section_id 
        FROM students 
        WHERE academic_period_id = :period 
          AND program_id = :program 
          AND data_origin = 'DEMO' 
        ORDER BY student_id
    ", $common);
    
    // All ACTIVE class batches, including other programs, block rooms and proctors on matching weekdays.
    $existing = exRows($pdo, "
        SELECT m.teacher_id, m.room_id, m.day_of_week, TIME_FORMAT(m.start_time,'%H:%i') AS start_time, TIME_FORMAT(m.end_time,'%H:%i') AS end_time 
        FROM schedule_meetings m 
        JOIN schedule_batches b ON b.batch_id = m.batch_id 
        WHERE b.academic_period_id = :period 
          AND b.status = 'ACTIVE' 
        ORDER BY b.batch_id, m.meeting_id
    ", ['period' => $periodId]);
    
    $activeClassBatches = exRows($pdo, "
        SELECT batch_id, program_id, data_origin, status 
        FROM schedule_batches 
        WHERE academic_period_id = :period 
          AND status = 'ACTIVE' 
        ORDER BY batch_id
    ", ['period' => $periodId]);
    
    $activeExamBatches = exRows($pdo, "
        SELECT exam_batch_id, program_id, class_batch_id, exam_label, data_origin, exam_day_1, exam_day_2, exam_day_3 
        FROM exam_batches 
        WHERE academic_period_id = :period 
          AND status = 'ACTIVE' 
        ORDER BY exam_batch_id
    ", ['period' => $periodId]);
    
    $existingExams = exRows($pdo, "
        SELECT em.exam_meeting_id, eb.exam_batch_id, eb.program_id, s.year_level, ss.subject_id, em.section_subject_id, em.proctor_id, em.room_id, em.exam_date, TIME_FORMAT(em.start_time,'%H:%i') AS start_time, TIME_FORMAT(em.end_time,'%H:%i') AS end_time 
        FROM exam_meetings em 
        JOIN exam_batches eb ON eb.exam_batch_id = em.exam_batch_id 
        JOIN section_subjects ss ON ss.section_subject_id = em.section_subject_id 
        JOIN sections s ON s.section_id = ss.section_id 
        WHERE eb.academic_period_id = :period 
          AND eb.status = 'ACTIVE' 
        ORDER BY em.exam_meeting_id
    ", ['period' => $periodId]);
    
    $replacementBaseline = null;
    
    if ($replaceBatchId !== null) {
        $own = array_values(array_filter($activeExamBatches, static fn($b) =>
            (int)$b['program_id'] === $programId && $b['exam_label'] === 'DEMO-EXAM' && $b['data_origin'] === 'DEMO'
        ));
        
        if (count($own) !== 1 || (int)$own[0]['exam_batch_id'] !== $replaceBatchId) {
            exFail(409, 'EXAM_BASELINE_CHANGED', 'The active BSIT DEMO exam batch changed. Reload its saved timetable.');
        }
        
        if ((int)$own[0]['class_batch_id'] !== $classBatch) {
            exFail(409, 'CLASS_BATCH_CHANGED', 'The saved exams refer to an older regular class batch. Review before replacing exams.');
        }
        
        $replacementBaseline = array_values(array_filter($existingExams, static fn($e) =>
            (int)$e['exam_batch_id'] === $replaceBatchId
        ));
        
        if (!$replacementBaseline) {
            exFail(409, 'EMPTY_EXAM_BASELINE', 'The old exam batch has no stored meetings.');
        }
        
        $existingExams = array_values(array_filter($existingExams, static fn($e) =>
            (int)$e['exam_batch_id'] !== $replaceBatchId
        ));
    }
    
    $input = [
        'program_code' => 'BSIT',
        'program_id' => $programId,
        'data_origin' => 'DEMO',
        'exam_dates' => $dates,
        'start_hour' => $window === '06-21' ? 6 : 18,
        'end_hour' => 21,
        'sections' => $sections,
        'exams' => $exams,
        'teachers' => $teachers,
        'teacher_availability' => $teacherAvailability,
        'rooms' => $rooms,
        'room_availability' => $roomAvailability,
        'students' => $students,
        'existing_classes' => $existing,
        'time_slots' => $slots
    ];
    
    return [
        'period' => $period[0],
        'program' => $program[0],
        'class_batch_id' => $classBatch,
        'input' => $input,
        'active_class_batches' => $activeClassBatches,
        'active_exam_batches' => $activeExamBatches,
        'existing_exams' => $existingExams,
        'replacement_baseline' => $replacementBaseline
    ];
}

/** An independent PHP audit on concrete output (no OR-Tools dependency or browser-controlled assignments). */
function exValidate(array $snapshot, array $proposal): void {
    $input = $snapshot['input'];
    $rows = $proposal['assignments'] ?? null;
    
    if (($proposal['success'] ?? false) !== true 
        || ($proposal['status'] ?? '') !== 'EXAM_PREVIEW_READY' 
        || !is_array($rows)
        || ($proposal['gap_audit']['passed'] ?? false) !== true 
        || count($rows) !== count($input['exams'])
        || (int)($proposal['required_exams'] ?? -1) !== count($input['exams']) 
        || (int)($proposal['returned_exams'] ?? -1) !== count($rows)
    ) {
        exFail(422, 'EXAM_PREVIEW_INVALID', 'The stored preview is incomplete or did not pass its independent Python gap audit.');
    }
    
    $bySection = []; 
    foreach ($input['sections'] as $s) $bySection[(int)$s['section_id']] = $s;
    
    $byExam = []; 
    foreach ($input['exams'] as $e) $byExam[(int)$e['section_subject_id']] = $e;
    
    $byRoom = []; 
    foreach ($input['rooms'] as $r) $byRoom[(int)$r['room_id']] = $r;
    
    $byTeacher = []; 
    foreach ($input['teachers'] as $t) $byTeacher[(int)$t['teacher_id']] = $t;
    
    $slots = []; 
    foreach ($input['time_slots'] as $t) {
        $slots[$t['day_of_week']][exTime($t['start_time']) . '-' . exTime($t['end_time'])] = true;
    }
    
    $roomWin = []; 
    foreach ($input['room_availability'] as $w) {
        $w['id'] = $w['room_id'];
        $roomWin[] = $w;
    }
    
    $teacherWin = []; 
    foreach ($input['teacher_availability'] as $w) {
        $w['id'] = $w['teacher_id'];
        $teacherWin[] = $w;
    }
    
    $dates = $input['exam_dates'];
    $seen = [];
    $events = [];
    $sectionEvents = [];
    
    foreach ($rows as $r) {
        if (!is_array($r)) exFail(422, 'INVALID_EXAM_ROW', 'Malformed exam meeting.');
        
        $ss = (int)($r['section_subject_id'] ?? 0); 
        $sid = (int)($r['section_id'] ?? 0); 
        $rid = (int)($r['room_id'] ?? 0);
        $tid = (int)($r['proctor_id'] ?? 0);
        
        if (!isset($byExam[$ss], $bySection[$sid], $byRoom[$rid], $byTeacher[$tid]) || isset($seen[$ss])) {
            exFail(422, 'INVALID_EXAM_MAPPING', 'Duplicate or unknown section-subject, teacher, or room in exam preview.');
        }
        
        $seen[$ss] = true;
        $e = $byExam[$ss];
        $section = $bySection[$sid];
        $room = $byRoom[$rid];
        $teacher = $byTeacher[$tid];
        
        if ((int)$e['section_id'] !== $sid || (int)$e['subject_id'] !== (int)($r['subject_id'] ?? 0) || (int)$e['proctor_id'] !== $tid
            || (int)$section['program_id'] !== (int)$input['program_id'] || (int)$teacher['program_id'] !== (int)$input['program_id']
            || ($room['program_id'] !== null && (int)$room['program_id'] !== (int)$input['program_id']) 
            || (int)$room['capacity'] < (int)$section['student_count']
        ) {
            exFail(422, 'EXAM_MAPPING_CHANGED', 'Exam section, subject, proctor, or eligible room does not match the latest database records.');
        }
        
        $day = (int)($r['exam_day'] ?? 0);
        if ($day < 1 || $day > 3 || ($r['exam_date'] ?? '') !== $dates[$day - 1]) {
            exFail(422, 'EXAM_DATE_INVALID', 'Exam day/date does not match selected dates.');
        }
        
        $start = exTime((string)($r['start_time'] ?? ''));
        $end = exTime((string)($r['end_time'] ?? ''));
        
        if ($end - $start !== 60 || $start % 60 !== 0 || $start < $input['start_hour'] * 60 || $end > $input['end_hour'] * 60) {
            exFail(422, 'EXAM_TIME_INVALID', 'Exam duration or examination window is invalid.');
        }
        
        $weekday = (new DateTimeImmutable($dates[$day - 1]))->format('l');
        
        if (!isset($slots[$weekday][$start . '-' . ($start + 30)], $slots[$weekday][($start + 30) . '-' . $end])
            || !exCovers($roomWin, $rid, $weekday, $start, $end) 
            || !exCovers($teacherWin, $tid, $weekday, $start, $end)
        ) {
            exFail(422, 'EXAM_RESOURCE_UNAVAILABLE', 'An exam is not covered by active time slots, room availability, or teacher availability.');
        }
        
        foreach ($input['existing_classes'] as $c) {
            if ($c['day_of_week'] !== $weekday) continue;
            if (exOverlap($start, $end, exTime($c['start_time']), exTime($c['end_time']))
                && (($c['room_id'] !== null && (int)$c['room_id'] === $rid) || (int)$c['teacher_id'] === $tid)
            ) {
                exFail(422, 'REGULAR_CLASS_CONFLICT', 'Proposed exam conflicts with an ACTIVE regular class on the same weekday.');
            }
        }
        
        foreach ($snapshot['existing_exams'] as $old) {
            if ($old['exam_date'] !== $dates[$day - 1] || !exOverlap($start, $end, exTime($old['start_time']), exTime($old['end_time']))) {
                continue;
            }
            if ((int)$old['room_id'] === $rid || (int)$old['proctor_id'] === $tid
                || ((int)$old['program_id'] === (int)$input['program_id'] && (int)$old['year_level'] === (int)$section['year_level'])
                || ((int)$old['program_id'] === (int)$input['program_id'] && (int)$old['subject_id'] === (int)$e['subject_id'])
            ) {
                exFail(409, 'OTHER_SAVED_EXAM_CONFLICT', 'The proposed exam conflicts with an ACTIVE saved examination. Generate a fresh preview.');
            }
        }
        
        $event = [
            'section_id' => $sid,
            'subject_id' => (int)$e['subject_id'],
            'teacher_id' => $tid,
            'room_id' => $rid,
            'year_level' => (int)$section['year_level'],
            'exam_date' => $dates[$day - 1],
            'exam_day' => $day,
            'start' => $start,
            'end' => $end
        ];
        
        $events[] = $event;
        $sectionEvents[$sid][] = $event;
    }
    
    if (count($seen) !== count($byExam)) {
        exFail(422, 'MISSING_EXAMS', 'Some required section-subject examinations were not generated.');
    }
    
    for ($i = 0; $i < count($events); $i++) {
        for ($j = $i + 1; $j < count($events); $j++) {
            $a = $events[$i];
            $b = $events[$j];
            
            if ($a['exam_date'] !== $b['exam_date'] || !exOverlap($a['start'], $a['end'], $b['start'], $b['end'])) {
                continue;
            }
            if ($a['teacher_id'] === $b['teacher_id'] || $a['room_id'] === $b['room_id'] || $a['section_id'] === $b['section_id']
                || $a['subject_id'] === $b['subject_id'] || $a['year_level'] === $b['year_level']
            ) {
                exFail(422, 'EXAM_OVERLAP', 'Overlapping exams share a teacher, room, subject, section, or BSIT year level.');
            }
        }
    }
    
    $memberCounts = [];
    $groups = [];
    
    foreach ($input['students'] as $student) {
        $home = (int)$student['home_section_id'];
        $major = $student['major_section_id'] !== null ? (int)$student['major_section_id'] : null;
        
        if (!isset($bySection[$home]) || ($major !== null && !isset($bySection[$major]))) {
            exFail(422, 'STUDENT_MAPPING_INVALID', 'Student has an unknown Cluster, Major, or regular section.');
        }
        
        $set = [$home];
        if ($major !== null && $major !== $home) {
            $set[] = $major;
        }
        
        sort($set);
        $key = implode(':', $set);
        $groups[$key] = $set;
        
        foreach ($set as $s) {
            $memberCounts[$s][(string)$student['student_id']] = true;
        }
    }
    
    foreach ($bySection as $sid => $sec) {
        if (count($memberCounts[$sid] ?? []) !== (int)$sec['student_count']) {
            exFail(422, 'STUDENT_MEMBERSHIP_INCOMPLETE', 'Actual student membership does not match section count.');
        }
        
        $rowsInSection = $sectionEvents[$sid] ?? [];
        if (!$rowsInSection) {
            exFail(422, 'SECTION_EXAMS_MISSING', 'A section has no examinations.');
        }
        
        $sectionRoomIds = array_values(array_unique(array_map(static fn($event) => (int)$event['room_id'], $rowsInSection)));
        
        if (count($sectionRoomIds) !== 1) {
            exFail(422, 'SECTION_EXAM_ROOM_CHANGED', 'Every exam of a section from Day 1 through Day 3 must use one fixed room.');
        }
        
        exConsecutive($rowsInSection);
        
        if ((int)$sec['year_level'] === 4 && $sec['section_type'] === 'CLUSTER') {
            $days = array_unique(array_column($rowsInSection, 'exam_day'));
            if (count($rowsInSection) !== 3 || count($days) !== 1 || !in_array($days[0], [1, 2], true)) {
                exFail(422, 'CLUSTER_DAY_INVALID', 'Fourth-year Cluster must have three exams on the same Day 1 or 2.');
            }
        } elseif ((int)$sec['year_level'] === 4 && $sec['section_type'] === 'MAJOR') {
            if (count($rowsInSection) !== 1 || $rowsInSection[0]['exam_day'] !== 3) {
                exFail(422, 'MAJOR_DAY_INVALID', 'Fourth-year BSIT Major must have one exam on Day 3.');
            }
        }
    }
    
    if (!$groups) {
        exFail(422, 'STUDENTS_NOT_LOADED', 'Student memberships were not loaded.');
    }
    
    foreach ($groups as $set) {
        $combined = [];
        foreach ($set as $sid) {
            array_push($combined, ...$sectionEvents[$sid]);
        }
        
        $groupRoomIds = array_values(array_unique(array_map(static fn($event) => (int)$event['room_id'], $combined)));
        if (count($groupRoomIds) !== 1) {
            exFail(422, 'STUDENT_GROUP_EXAM_ROOM_CHANGED', 'A linked student group, including fourth-year Cluster/Major memberships, must keep one fixed room for the full three-day exam period.');
        }
        
        exConsecutive($combined);
    }
}

function exConsecutive(array $events): void {
    $byDate = [];
    foreach ($events as $e) {
        $byDate[$e['exam_date']][] = $e;
    }
    
    foreach ($byDate as $list) {
        if (count($list) > 3) {
            exFail(422, 'DAILY_EXAM_LIMIT', 'A section/student has more than three exams on one day.');
        }
        
        usort($list, static fn($a, $b) => $a['start'] <=> $b['start']);
        
        foreach (array_slice($list, 1) as $i => $e) {
            if ($list[$i]['end'] !== $e['start']) {
                exFail(422, 'EXAM_GAP_OR_OVERLAP', 'Exams must be consecutive, with no vacant time or overlap.');
            }
        }
    }
}
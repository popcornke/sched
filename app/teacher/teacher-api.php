<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/auth.php';
authRequire(true, ['TEACHER']);
require_once dirname(__DIR__) . '/config/database.php';

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('X-Content-Type-Options: nosniff');

function tpReply(int $http, array $body): never
{
    http_response_code($http);
    echo json_encode($body + ['database_write' => false], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function tpRows(PDO $db, string $sql, array $params = []): array
{
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function tpOne(PDO $db, string $sql, array $params = []): ?array
{
    $rows = tpRows($db, $sql, $params);
    return $rows[0] ?? null;
}

function tpMinutes(string $time): int
{
    $parts = explode(':', $time);
    return ((int)($parts[0] ?? 0) * 60) + (int)($parts[1] ?? 0);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    tpReply(405, ['success' => false, 'status' => 'METHOD_NOT_ALLOWED', 'message' => 'GET only.']);
}

try {
    $db = getDatabase();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $userId = (int)($_SESSION['auth_user_id'] ?? 0);
    if ($userId < 1) {
        tpReply(401, ['success' => false, 'status' => 'AUTH_SESSION_MISSING', 'message' => 'Authenticated user ID is missing.']);
    }

    $teacher = tpOne($db, "
        SELECT
            au.user_id,
            au.username,
            au.role,
            ta.must_change_password,
            ta.password_changed_at,
            t.teacher_id,
            t.employee_no,
            t.teacher_name,
            t.max_daily_hours,
            t.max_weekly_hours,
            t.status AS teacher_status,
            t.data_origin,
            p.program_id,
            p.program_code,
            p.program_name
        FROM auth_users au
        JOIN teacher_accounts ta ON ta.user_id = au.user_id
        JOIN teachers t ON t.teacher_id = ta.teacher_id
        JOIN programs p ON p.program_id = t.program_id
        WHERE au.user_id = :user_id
          AND au.role = 'TEACHER'
          AND au.is_active = 1
          AND t.status = 'ACTIVE'
        LIMIT 1
    ", ['user_id' => $userId]);

    if (!$teacher) {
        tpReply(403, [
            'success' => false,
            'status' => 'TEACHER_ACCOUNT_NOT_MAPPED',
            'message' => 'This teacher login is not linked to an active teacher record.'
        ]);
    }

    if ((int)$teacher['must_change_password'] === 1) {
        tpReply(403, [
            'success' => false,
            'status' => 'PASSWORD_CHANGE_REQUIRED',
            'message' => 'Change your assigned default password before opening teacher schedule data.',
            'change_password_url' => './teacher-change-password.php'
        ]);
    }

    $periods = tpRows($db, "
        SELECT academic_period_id, academic_year, semester, period_status
        FROM academic_periods
        ORDER BY academic_period_id DESC
    ");

    if (!$periods) {
        tpReply(409, ['success' => false, 'status' => 'NO_ACADEMIC_PERIOD', 'message' => 'No academic period is configured.']);
    }

    $requested = filter_var($_GET['period_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $period = null;
    if ($requested !== false && $requested !== null) {
        foreach ($periods as $item) {
            if ((int)$item['academic_period_id'] === (int)$requested) {
                $period = $item;
                break;
            }
        }
    }
    $period ??= $periods[0];

    $periodId = (int)$period['academic_period_id'];
    $teacherId = (int)$teacher['teacher_id'];
    $params = ['period' => $periodId, 'teacher' => $teacherId];

    $meetings = tpRows($db, "
        SELECT
            m.meeting_id,
            m.batch_id,
            m.delivery_mode,
            m.day_of_week,
            TIME_FORMAT(m.start_time, '%H:%i') AS start_time,
            TIME_FORMAT(m.end_time, '%H:%i') AS end_time,
            ss.section_subject_id,
            sec.section_id,
            sec.section_code,
            sec.year_level,
            sec.section_type,
            sub.subject_id,
            sub.subject_code,
            sub.subject_title,
            r.room_id,
            r.room_name,
            r.building
        FROM schedule_meetings m
        JOIN schedule_batches b ON b.batch_id = m.batch_id
        JOIN section_subjects ss ON ss.section_subject_id = m.section_subject_id
        JOIN sections sec ON sec.section_id = ss.section_id
        JOIN subjects sub ON sub.subject_id = ss.subject_id
        LEFT JOIN rooms r ON r.room_id = m.room_id
        WHERE b.academic_period_id = :period
          AND b.status = 'ACTIVE'
          AND m.teacher_id = :teacher
        ORDER BY FIELD(m.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), m.start_time, sec.section_code, sub.subject_code
    ", $params);

    $weeklyMinutes = 0;
    $dayMinutes = [];
    foreach ($meetings as &$m) {
        $duration = max(0, tpMinutes($m['end_time']) - tpMinutes($m['start_time']));
        $m['duration_minutes'] = $duration;
        $weeklyMinutes += $duration;
        $dayMinutes[$m['day_of_week']] = ($dayMinutes[$m['day_of_week']] ?? 0) + $duration;
    }
    unset($m);

    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $todayName = $now->format('l');
    $todayDate = $now->format('Y-m-d');
    $nowMinutes = ((int)$now->format('H') * 60) + (int)$now->format('i');
    $todayMeetings = array_values(array_filter($meetings, static fn(array $m): bool => $m['day_of_week'] === $todayName));

    $nextClass = null;
    foreach ($todayMeetings as $m) {
        if (tpMinutes($m['end_time']) > $nowMinutes) {
            $nextClass = $m;
            break;
        }
    }

    $availability = tpRows($db, "
        SELECT day_of_week,
               TIME_FORMAT(start_time, '%H:%i') AS start_time,
               TIME_FORMAT(end_time, '%H:%i') AS end_time,
               availability_status
        FROM teacher_availability
        WHERE academic_period_id = :period AND teacher_id = :teacher
        ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), start_time
    ", $params);

    $examDuties = tpRows($db, "
        SELECT
            em.exam_meeting_id,
            eb.exam_batch_id,
            em.exam_date,
            em.exam_day,
            TIME_FORMAT(em.start_time, '%H:%i') AS start_time,
            TIME_FORMAT(em.end_time, '%H:%i') AS end_time,
            sec.section_code,
            sec.year_level,
            sub.subject_code,
            sub.subject_title,
            r.room_name
        FROM exam_meetings em
        JOIN exam_batches eb ON eb.exam_batch_id = em.exam_batch_id
        JOIN section_subjects ss ON ss.section_subject_id = em.section_subject_id
        JOIN sections sec ON sec.section_id = ss.section_id
        JOIN subjects sub ON sub.subject_id = ss.subject_id
        JOIN rooms r ON r.room_id = em.room_id
        WHERE eb.academic_period_id = :period
          AND eb.status = 'ACTIVE'
          AND em.proctor_id = :teacher
        ORDER BY em.exam_date, em.start_time
    ", $params);

    $substituteDuties = tpRows($db, "
        SELECT
            sa.substitute_assignment_id,
            sa.duty_date,
            sa.reason,
            sa.status,
            m.day_of_week,
            TIME_FORMAT(m.start_time, '%H:%i') AS start_time,
            TIME_FORMAT(m.end_time, '%H:%i') AS end_time,
            sec.section_code,
            sub.subject_code,
            sub.subject_title,
            r.room_name,
            ot.teacher_name AS original_teacher_name
        FROM substitute_assignments sa
        JOIN schedule_meetings m ON m.meeting_id = sa.meeting_id
        JOIN section_subjects ss ON ss.section_subject_id = m.section_subject_id
        JOIN sections sec ON sec.section_id = ss.section_id
        JOIN subjects sub ON sub.subject_id = ss.subject_id
        LEFT JOIN rooms r ON r.room_id = m.room_id
        JOIN teachers ot ON ot.teacher_id = sa.original_teacher_id
        WHERE sa.academic_period_id = :period
          AND sa.substitute_teacher_id = :teacher
          AND sa.status = 'ACTIVE'
        ORDER BY sa.duty_date, m.start_time
    ", $params);

    $specialClasses = tpRows($db, "
        SELECT
            scm.special_meeting_id,
            scm.meeting_date,
            TIME_FORMAT(scm.start_time, '%H:%i') AS start_time,
            TIME_FORMAT(scm.end_time, '%H:%i') AS end_time,
            scm.delivery_mode,
            sc.class_type,
            sc.recurrence,
            sub.subject_code,
            sub.subject_title,
            r.room_name
        FROM special_class_meetings scm
        JOIN special_classes sc ON sc.special_class_id = scm.special_class_id
        JOIN subjects sub ON sub.subject_id = sc.subject_id
        LEFT JOIN rooms r ON r.room_id = scm.room_id
        WHERE sc.academic_period_id = :period
          AND scm.teacher_id = :teacher
          AND sc.status = 'ACTIVE'
          AND scm.status = 'SCHEDULED'
        ORDER BY scm.meeting_date, scm.start_time
    ", $params);

    $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    $dailyLoad = [];
    foreach ($days as $day) {
        $minutes = (int)($dayMinutes[$day] ?? 0);
        $dailyLoad[] = [
            'day_of_week' => $day,
            'minutes' => $minutes,
            'hours' => round($minutes / 60, 2),
        ];
    }

    tpReply(200, [
        'success' => true,
        'status' => 'TEACHER_PORTAL_READY',
        'teacher' => [
            'teacher_id' => $teacherId,
            'employee_no' => $teacher['employee_no'],
            'teacher_name' => $teacher['teacher_name'],
            'program_code' => $teacher['program_code'],
            'program_name' => $teacher['program_name'],
            'max_daily_hours' => (int)$teacher['max_daily_hours'],
            'max_weekly_hours' => (int)$teacher['max_weekly_hours'],
        ],
        'periods' => $periods,
        'period' => [
            'academic_period_id' => $periodId,
            'academic_year' => $period['academic_year'],
            'semester' => (int)$period['semester'],
            'period_status' => $period['period_status'],
        ],
        'server_time' => $now->format(DATE_ATOM),
        'today' => ['date' => $todayDate, 'day_name' => $todayName],
        'summary' => [
            'today_classes' => count($todayMeetings),
            'weekly_meetings' => count($meetings),
            'weekly_minutes' => $weeklyMinutes,
            'weekly_hours' => round($weeklyMinutes / 60, 2),
            'exam_duties' => count($examDuties),
            'substitute_duties' => count($substituteDuties),
            'special_classes' => count($specialClasses),
        ],
        'next_class' => $nextClass,
        'today_schedule' => $todayMeetings,
        'weekly_schedule' => $meetings,
        'daily_load' => $dailyLoad,
        'exam_duties' => $examDuties,
        'substitute_duties' => $substituteDuties,
        'special_classes' => $specialClasses,
        'availability' => $availability,
    ]);
} catch (Throwable $e) {
    error_log('BCP teacher portal API: ' . $e->getMessage());
    tpReply(500, [
        'success' => false,
        'status' => 'TEACHER_PORTAL_ERROR',
        'message' => 'Unable to load the teacher portal. Check the PHP error log.'
    ]);
}

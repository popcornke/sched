<?php
declare(strict_types=1);

require_once __DIR__ . '/substitute-common.php';

$pdo = null;
$locked = false;
$lockName = '';

function subRefreshRequestStatus(PDO $pdo, int $requestId): void
{
    $request = subOne(
        $pdo,
        "SELECT status FROM substitute_requests WHERE request_id=:request FOR UPDATE",
        ['request' => $requestId]
    );

    if (!$request || $request['status'] === 'CANCELLED') {
        return;
    }

    $counts = subOne(
        $pdo,
        "SELECT
            COUNT(*) AS total,
            SUM(item_status='ASSIGNED') AS assigned,
            SUM(item_status='PENDING') AS pending
         FROM substitute_request_items
         WHERE request_id=:request",
        ['request' => $requestId]
    );

    $total = (int)($counts['total'] ?? 0);
    $assigned = (int)($counts['assigned'] ?? 0);

    $status = 'PENDING';
    if ($total > 0 && $assigned >= $total) {
        $status = 'ASSIGNED';
    } elseif ($assigned > 0) {
        $status = 'PARTIALLY_ASSIGNED';
    }

    $stmt = $pdo->prepare(
        "UPDATE substitute_requests SET status=:status WHERE request_id=:request"
    );
    $stmt->execute(['status' => $status, 'request' => $requestId]);
}

function subRequestItem(PDO $pdo, int $requestItemId, int $period, bool $forUpdate = false): array
{
    $sql = "SELECT
                sri.request_item_id,
                sri.request_id,
                sri.meeting_id,
                sri.class_batch_id,
                sri.duty_date,
                sri.item_status,
                sr.academic_period_id,
                sr.program_id,
                sr.original_teacher_id,
                sr.status AS request_status,
                sr.coverage_type,
                sr.leave_start_date,
                sr.leave_end_date
            FROM substitute_request_items sri
            JOIN substitute_requests sr ON sr.request_id=sri.request_id
            WHERE sri.request_item_id=:item
              AND sr.academic_period_id=:period";

    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $item = subOne($pdo, $sql, [
        'item' => $requestItemId,
        'period' => $period
    ]);

    if (!$item) {
        subFail(404, 'REQUEST_ITEM_NOT_FOUND', 'The requested class coverage item was not found.');
    }

    return $item;
}

function subCandidateLoad(PDO $pdo, array $meeting, string $date, int $teacherId): array
{
    $period = (int)$meeting['academic_period_id'];
    $day = subWeekday($date);
    $planned = max(0, subClock((string)$meeting['end_time']) - subClock((string)$meeting['start_time']));

    $regular = subRows(
        $pdo,
        "SELECT
            m.day_of_week,
            SUM(TIME_TO_SEC(TIMEDIFF(m.end_time,m.start_time))/60) AS minutes
         FROM schedule_meetings m
         JOIN schedule_batches sb ON sb.batch_id=m.batch_id
         WHERE sb.academic_period_id=:period
           AND sb.status='ACTIVE'
           AND m.teacher_id=:teacher
         GROUP BY m.day_of_week",
        ['period' => $period, 'teacher' => $teacherId]
    );

    $daily = 0.0;
    $weekly = 0.0;
    foreach ($regular as $row) {
        $minutes = (float)($row['minutes'] ?? 0);
        $weekly += $minutes;
        if ((string)$row['day_of_week'] === $day) {
            $daily += $minutes;
        }
    }

    $weekStart = (new DateTimeImmutable($date))->modify('monday this week')->format('Y-m-d');
    $weekEnd = (new DateTimeImmutable($date))->modify('sunday this week')->format('Y-m-d');

    $duties = subRows(
        $pdo,
        "SELECT
            sa.duty_date,
            TIME_TO_SEC(TIMEDIFF(m.end_time,m.start_time))/60 AS minutes
         FROM substitute_assignments sa
         JOIN schedule_meetings m ON m.meeting_id=sa.meeting_id
         JOIN schedule_batches sb ON sb.batch_id=m.batch_id AND sb.status='ACTIVE'
         WHERE sa.academic_period_id=:period
           AND sa.status='ACTIVE'
           AND sa.substitute_teacher_id=:teacher
           AND sa.duty_date BETWEEN :week_start AND :week_end",
        [
            'period' => $period,
            'teacher' => $teacherId,
            'week_start' => $weekStart,
            'week_end' => $weekEnd
        ]
    );

    foreach ($duties as $duty) {
        $minutes = (float)($duty['minutes'] ?? 0);
        $weekly += $minutes;
        if ((string)$duty['duty_date'] === $date) {
            $daily += $minutes;
        }
    }

    return [
        'current_daily_minutes' => (int)round($daily),
        'current_weekly_minutes' => (int)round($weekly),
        'projected_daily_minutes' => (int)round($daily + $planned),
        'projected_weekly_minutes' => (int)round($weekly + $planned),
    ];
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    subGuard($method === 'POST' ? 'POST' : 'GET');
    subSession();
    $pdo = subPdo();

    if ($method === 'GET') {
        $action = (string)($_GET['action'] ?? 'catalog');

        if ($action === 'catalog') {
            $periods = subRows(
                $pdo,
                "SELECT academic_period_id,academic_year,semester,period_status
                 FROM academic_periods
                 ORDER BY academic_period_id DESC"
            );

            $period = isset($_GET['period_id']) && $_GET['period_id'] !== ''
                ? subInt($_GET['period_id'], 'period')
                : (int)($periods[0]['academic_period_id'] ?? 0);

            if (!$period) {
                subFail(404, 'NO_ACADEMIC_PERIOD', 'No academic period exists.');
            }

            // Inbox programs come from either incoming requests OR an ACTIVE timetable.
            // This keeps the page usable even when a program has no current request yet.
            $programs = subRows(
                $pdo,
                "SELECT DISTINCT p.program_id,p.program_code,p.program_name
                 FROM programs p
                 WHERE EXISTS (
                    SELECT 1
                    FROM substitute_requests sr
                    WHERE sr.program_id=p.program_id
                      AND sr.academic_period_id=:request_period
                 )
                 OR EXISTS (
                    SELECT 1
                    FROM schedule_batches sb
                    WHERE sb.program_id=p.program_id
                      AND sb.academic_period_id=:batch_period
                      AND sb.status='ACTIVE'
                 )
                 ORDER BY p.program_code",
                [
                    'request_period' => $period,
                    'batch_period' => $period
                ]
            );

            $programCode = (string)($_GET['program'] ?? ($programs[0]['program_code'] ?? ''));
            $selected = null;

            foreach ($programs as $program) {
                if ((string)$program['program_code'] === $programCode) {
                    $selected = $program;
                    break;
                }
            }

            $requests = [];
            $items = [];
            $history = [];

            if ($selected) {
                $programId = (int)$selected['program_id'];

                // Request source is intentionally outside this module's scope.
                // Therefore created_by_user_id may be NULL and is NOT used as a requirement.
                $requests = subRows(
                    $pdo,
                    "SELECT
                        sr.request_id,
                        sr.coverage_type,
                        sr.leave_start_date,
                        sr.leave_end_date,
                        sr.status,
                        sr.created_at,
                        t.teacher_id,
                        t.teacher_name,
                        t.employee_no,
                        COUNT(sri.request_item_id) AS total_items,
                        SUM(sri.item_status='PENDING') AS pending_items,
                        SUM(sri.item_status='ASSIGNED') AS assigned_items,
                        SUM(sri.item_status='CANCELLED') AS cancelled_items
                     FROM substitute_requests sr
                     JOIN teachers t ON t.teacher_id=sr.original_teacher_id
                     LEFT JOIN substitute_request_items sri ON sri.request_id=sr.request_id
                     WHERE sr.academic_period_id=:period
                       AND sr.program_id=:program
                     GROUP BY
                        sr.request_id,
                        sr.coverage_type,
                        sr.leave_start_date,
                        sr.leave_end_date,
                        sr.status,
                        sr.created_at,
                        t.teacher_id,
                        t.teacher_name,
                        t.employee_no
                     ORDER BY
                        FIELD(sr.status,'PENDING','PARTIALLY_ASSIGNED','ASSIGNED','CANCELLED'),
                        sr.created_at DESC,
                        sr.request_id DESC
                     LIMIT 150",
                    ['period' => $period, 'program' => $programId]
                );

                $items = subRows(
                    $pdo,
                    "SELECT
                        sri.request_item_id,
                        sri.request_id,
                        sri.meeting_id,
                        sri.duty_date,
                        sri.item_status,
                        m.delivery_mode,
                        m.day_of_week,
                        TIME_FORMAT(m.start_time,'%H:%i') AS start_time,
                        TIME_FORMAT(m.end_time,'%H:%i') AS end_time,
                        sec.section_code,
                        s.subject_code,
                        s.subject_title,
                        COALESCE(r.room_name,'ONLINE') AS room_name,
                        sa.substitute_assignment_id,
                        st.teacher_name AS substitute_teacher_name,
                        st.employee_no AS substitute_employee_no
                     FROM substitute_request_items sri
                     JOIN substitute_requests sr ON sr.request_id=sri.request_id
                     JOIN schedule_meetings m ON m.meeting_id=sri.meeting_id
                     JOIN section_subjects ss ON ss.section_subject_id=m.section_subject_id
                     JOIN sections sec ON sec.section_id=ss.section_id
                     JOIN subjects s ON s.subject_id=ss.subject_id
                     LEFT JOIN rooms r ON r.room_id=m.room_id
                     LEFT JOIN substitute_assignments sa
                       ON sa.request_item_id=sri.request_item_id
                      AND sa.status='ACTIVE'
                     LEFT JOIN teachers st ON st.teacher_id=sa.substitute_teacher_id
                     WHERE sr.academic_period_id=:period
                       AND sr.program_id=:program
                     ORDER BY
                        sri.duty_date,
                        m.start_time,
                        sec.section_code,
                        s.subject_code",
                    ['period' => $period, 'program' => $programId]
                );

                $history = subRows(
                    $pdo,
                    "SELECT
                        sa.substitute_assignment_id,
                        sa.request_item_id,
                        sa.duty_date,
                        sa.status,
                        sa.created_at,
                        sa.cancelled_at,
                        sec.section_code,
                        s.subject_code,
                        s.subject_title,
                        m.delivery_mode,
                        TIME_FORMAT(m.start_time,'%H:%i') AS start_time,
                        TIME_FORMAT(m.end_time,'%H:%i') AS end_time,
                        ot.teacher_name AS original_teacher_name,
                        st.teacher_name AS substitute_teacher_name,
                        CASE WHEN sb.status='ACTIVE' THEN 1 ELSE 0 END AS source_batch_active
                     FROM substitute_assignments sa
                     JOIN schedule_meetings m ON m.meeting_id=sa.meeting_id
                     JOIN schedule_batches sb ON sb.batch_id=sa.class_batch_id
                     JOIN section_subjects ss ON ss.section_subject_id=m.section_subject_id
                     JOIN sections sec ON sec.section_id=ss.section_id
                     JOIN subjects s ON s.subject_id=ss.subject_id
                     JOIN teachers ot ON ot.teacher_id=sa.original_teacher_id
                     JOIN teachers st ON st.teacher_id=sa.substitute_teacher_id
                     WHERE sa.academic_period_id=:period
                       AND sb.program_id=:program
                     ORDER BY sa.created_at DESC,sa.substitute_assignment_id DESC
                     LIMIT 150",
                    ['period' => $period, 'program' => $programId]
                );
            }

            subReply(200, [
                'success' => true,
                'status' => 'SUBSTITUTE_REQUEST_INBOX_READY',
                'periods' => $periods,
                'programs' => $programs,
                'selected_period_id' => $period,
                'selected_program' => $selected,
                'requests' => $requests,
                'request_items' => $items,
                'history' => $history,
                'csrf_token' => authCsrf(),
                'database_write' => false,
            ]);
        }

        if ($action === 'candidates') {
            $period = subInt($_GET['period_id'] ?? null, 'period');
            $requestItemId = subInt($_GET['request_item_id'] ?? null, 'request item');

            $item = subRequestItem($pdo, $requestItemId, $period);

            if ($item['request_status'] === 'CANCELLED' || $item['item_status'] === 'CANCELLED') {
                subFail(409, 'REQUEST_CANCELLED', 'This request has been cancelled.');
            }

            if ($item['item_status'] === 'ASSIGNED') {
                subFail(409, 'DUTY_ALREADY_ASSIGNED', 'This class already has a substitute assignment.');
            }

            $meeting = subMeeting($pdo, (int)$item['meeting_id'], $period);

            if ((int)$meeting['batch_id'] !== (int)$item['class_batch_id']) {
                subFail(
                    409,
                    'SOURCE_BATCH_CHANGED',
                    'The source class timetable changed after the request was recorded.'
                );
            }

            $date = (string)$item['duty_date'];

            $teachers = subRows(
                $pdo,
                "SELECT teacher_id,teacher_name,employee_no,max_daily_hours,max_weekly_hours
                 FROM teachers
                 WHERE program_id=:program
                   AND status='ACTIVE'
                 ORDER BY teacher_name",
                ['program' => $meeting['program_id']]
            );

            $candidates = [];

            foreach ($teachers as $teacher) {
                $teacherId = (int)$teacher['teacher_id'];
                $issues = subTeacherIssues($pdo, $meeting, $date, $teacherId);
                $load = subCandidateLoad($pdo, $meeting, $date, $teacherId);

                $candidates[] = [
                    'teacher_id' => $teacherId,
                    'teacher_name' => (string)$teacher['teacher_name'],
                    'employee_no' => (string)$teacher['employee_no'],
                    'eligible' => count($issues) === 0,
                    'issues' => $issues,
                    'max_daily_hours' => (int)$teacher['max_daily_hours'],
                    'max_weekly_hours' => (int)$teacher['max_weekly_hours'],
                    'projected_daily_minutes' => $load['projected_daily_minutes'],
                    'projected_weekly_minutes' => $load['projected_weekly_minutes'],
                    'recommended' => false,
                ];
            }

            usort($candidates, static function (array $a, array $b): int {
                if ($a['eligible'] !== $b['eligible']) {
                    return $a['eligible'] ? -1 : 1;
                }
                if ($a['projected_weekly_minutes'] !== $b['projected_weekly_minutes']) {
                    return $a['projected_weekly_minutes'] <=> $b['projected_weekly_minutes'];
                }
                if ($a['projected_daily_minutes'] !== $b['projected_daily_minutes']) {
                    return $a['projected_daily_minutes'] <=> $b['projected_daily_minutes'];
                }
                return strcasecmp($a['teacher_name'], $b['teacher_name']);
            });

            foreach ($candidates as &$candidate) {
                if ($candidate['eligible']) {
                    $candidate['recommended'] = true;
                    break;
                }
            }
            unset($candidate);

            subReply(200, [
                'success' => true,
                'status' => 'SUBSTITUTE_CANDIDATES_READY',
                'request_item' => $item,
                'meeting' => $meeting,
                'duty_date' => $date,
                'candidates' => $candidates,
                'available_count' => count(array_filter(
                    $candidates,
                    static fn(array $teacher): bool => $teacher['eligible']
                )),
                'database_write' => false,
            ]);
        }

        subFail(400, 'INVALID_ACTION', 'Unknown tracker action.');
    }

    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($input) || !authCsrfValid($input['csrf_token'] ?? null)) {
        subFail(403, 'INVALID_CSRF', 'Refresh the module and try again.');
    }

    $action = (string)($input['action'] ?? '');
    $period = subInt($input['period_id'] ?? null, 'period');

    $lockName = 'bcp_substitute_period_' . $period;
    $stmt = $pdo->prepare('SELECT GET_LOCK(:key,10)');
    $stmt->execute(['key' => $lockName]);

    if ((int)$stmt->fetchColumn() !== 1) {
        subFail(409, 'TRACKER_BUSY', 'Another substitution is being updated. Retry.');
    }

    $locked = true;
    $pdo->beginTransaction();

    subRows(
        $pdo,
        "SELECT batch_id,status
         FROM schedule_batches
         WHERE academic_period_id=:period
         ORDER BY batch_id
         FOR UPDATE",
        ['period' => $period]
    );

    if ($action === 'assign') {
        $requestItemId = subInt($input['request_item_id'] ?? null, 'request item');
        $teacherId = subInt($input['substitute_teacher_id'] ?? null, 'substitute professor');

        $item = subRequestItem($pdo, $requestItemId, $period, true);

        if ($item['request_status'] === 'CANCELLED' || $item['item_status'] === 'CANCELLED') {
            subFail(409, 'REQUEST_CANCELLED', 'This request has been cancelled.');
        }

        if ($item['item_status'] === 'ASSIGNED') {
            subFail(409, 'DUTY_ALREADY_ASSIGNED', 'This class already has a substitute assignment.');
        }

        $meeting = subMeeting($pdo, (int)$item['meeting_id'], $period);

        if ((int)$meeting['batch_id'] !== (int)$item['class_batch_id']) {
            subFail(409, 'SOURCE_BATCH_CHANGED', 'The source timetable changed. Refresh.');
        }

        if ((int)$meeting['original_teacher_id'] !== (int)$item['original_teacher_id']) {
            subFail(409, 'ORIGINAL_TEACHER_CHANGED', 'The original professor changed. Refresh.');
        }

        $date = (string)$item['duty_date'];

        $exists = subOne(
            $pdo,
            "SELECT substitute_assignment_id
             FROM substitute_assignments
             WHERE meeting_id=:meeting
               AND duty_date=:date
               AND status='ACTIVE'
             FOR UPDATE",
            [
                'meeting' => $meeting['meeting_id'],
                'date' => $date
            ]
        );

        if ($exists) {
            subFail(409, 'DUTY_ALREADY_ASSIGNED', 'A substitute is already assigned for this class/date.');
        }

        $issues = subTeacherIssues($pdo, $meeting, $date, $teacherId);

        if ($issues) {
            subFail(422, 'SUBSTITUTE_NOT_ELIGIBLE', implode(' ', $issues));
        }

        $stmt = $pdo->prepare(
            "INSERT INTO substitute_assignments
                (meeting_id,class_batch_id,academic_period_id,request_item_id,duty_date,
                 original_teacher_id,substitute_teacher_id,status)
             VALUES
                (:meeting,:batch,:period,:request_item,:date,:original,:teacher,'ACTIVE')"
        );

        $stmt->execute([
            'meeting' => $meeting['meeting_id'],
            'batch' => $meeting['batch_id'],
            'period' => $period,
            'request_item' => $requestItemId,
            'date' => $date,
            'original' => $meeting['original_teacher_id'],
            'teacher' => $teacherId,
        ]);

        $assignmentId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare(
            "UPDATE substitute_request_items
             SET item_status='ASSIGNED'
             WHERE request_item_id=:item
               AND item_status='PENDING'"
        );
        $stmt->execute(['item' => $requestItemId]);

        if ($stmt->rowCount() !== 1) {
            subFail(409, 'REQUEST_ITEM_CHANGED', 'This request item changed. Refresh.');
        }

        subRefreshRequestStatus($pdo, (int)$item['request_id']);

        $pdo->commit();
        $pdo->prepare('SELECT RELEASE_LOCK(:key)')->execute(['key' => $lockName]);
        $locked = false;

        subReply(200, [
            'success' => true,
            'status' => 'SUBSTITUTE_ASSIGNED',
            'substitute_assignment_id' => $assignmentId,
            'request_item_id' => $requestItemId,
            'duty_date' => $date,
            'database_write' => true,
        ]);
    }

    if ($action === 'cancel_assignment') {
        $assignmentId = subInt($input['substitute_assignment_id'] ?? null, 'substitute assignment');

        $assignment = subOne(
            $pdo,
            "SELECT substitute_assignment_id,request_item_id,status
             FROM substitute_assignments
             WHERE substitute_assignment_id=:id
               AND academic_period_id=:period
             FOR UPDATE",
            ['id' => $assignmentId, 'period' => $period]
        );

        if (!$assignment || $assignment['status'] !== 'ACTIVE') {
            subFail(409, 'ASSIGNMENT_NOT_ACTIVE', 'The substitute assignment is already cancelled.');
        }

        $stmt = $pdo->prepare(
            "UPDATE substitute_assignments
             SET status='CANCELLED',cancelled_at=CURRENT_TIMESTAMP
             WHERE substitute_assignment_id=:id
               AND status='ACTIVE'"
        );
        $stmt->execute(['id' => $assignmentId]);

        if ($stmt->rowCount() !== 1) {
            subFail(409, 'CANCEL_CONFLICT', 'This assignment was changed. Refresh.');
        }

        $requestItemId = (int)($assignment['request_item_id'] ?? 0);

        if ($requestItemId > 0) {
            $item = subRequestItem($pdo, $requestItemId, $period, true);

            if ($item['request_status'] !== 'CANCELLED') {
                $pdo->prepare(
                    "UPDATE substitute_request_items
                     SET item_status='PENDING'
                     WHERE request_item_id=:item"
                )->execute(['item' => $requestItemId]);

                subRefreshRequestStatus($pdo, (int)$item['request_id']);
            }
        }

        $pdo->commit();
        $pdo->prepare('SELECT RELEASE_LOCK(:key)')->execute(['key' => $lockName]);
        $locked = false;

        subReply(200, [
            'success' => true,
            'status' => 'SUBSTITUTE_CANCELLED',
            'substitute_assignment_id' => $assignmentId,
            'database_write' => true,
        ]);
    }

    subFail(
        400,
        'INVALID_ACTION',
        'Supported actions: assign, cancel_assignment.'
    );

} catch (SubstituteError $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($pdo instanceof PDO && $locked) {
        try {
            $pdo->prepare('SELECT RELEASE_LOCK(:key)')->execute(['key' => $lockName]);
        } catch (Throwable) {
        }
    }

    subReply($e->httpCode, [
        'success' => false,
        'status' => $e->apiStatus,
        'message' => $e->getMessage(),
        'database_write' => false,
    ]);

} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($pdo instanceof PDO && $locked) {
        try {
            $pdo->prepare('SELECT RELEASE_LOCK(:key)')->execute(['key' => $lockName]);
        } catch (Throwable) {
        }
    }

    error_log('BCP Substitute Tracker: ' . $e->getMessage());

    subReply(500, [
        'success' => false,
        'status' => 'SUBSTITUTE_API_ERROR',
        'message' => 'Substitute action failed; check the PHP error log. No partial update was saved.',
        'database_write' => false,
    ]);
}

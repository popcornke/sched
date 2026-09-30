<?php

declare(strict_types=1);

require_once __DIR__ . '/substitute-common.php';

$pdo = null;
$locked = false;
$lockName = '';

try {

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    subGuard(
        $method === 'POST'
            ? 'POST'
            : 'GET'
    );

    subSession();

    $pdo = subPdo();

    /*
     * ============================================================
     * GET REQUESTS
     * ============================================================
     */
    if ($method === 'GET') {

        $action = (string)($_GET['action'] ?? 'catalog');

        /*
         * ========================================================
         * CATALOG
         *
         * Only academic periods that already have at least one
         * ACTIVE DEMO class timetable are valid for substitution.
         *
         * This prevents the module from defaulting to a newer
         * academic period that has setup data but no saved timetable.
         * ========================================================
         */
        if ($action === 'catalog') {

            /*
             * Only expose periods that actually have an ACTIVE
             * saved DEMO class timetable.
             */
            $periods = subRows(
                $pdo,
                "
                SELECT DISTINCT
                    ap.academic_period_id,
                    ap.academic_year,
                    ap.semester
                FROM academic_periods ap
                INNER JOIN schedule_batches sb
                    ON sb.academic_period_id = ap.academic_period_id
                WHERE ap.period_status = 'DEMO'
                  AND sb.status = 'ACTIVE'
                  AND sb.data_origin = 'DEMO'
                ORDER BY ap.academic_period_id DESC
                "
            );

            if (!$periods) {
                subFail(
                    404,
                    'NO_ACTIVE_DEMO_TIMETABLE',
                    'No ACTIVE DEMO class timetable is available for substitute assignment.'
                );
            }

            /*
             * Default to newest timetable-ready academic period.
             */
            $period = (int)$periods[0]['academic_period_id'];

            /*
             * If the browser requests a period, use it only when
             * that period is included in the timetable-ready list.
             *
             * If it is stale/invalid, safely fall back instead of
             * failing the entire catalog request.
             */
            if (
                isset($_GET['period_id'])
                && $_GET['period_id'] !== ''
            ) {

                $requestedPeriod = subInt(
                    $_GET['period_id'],
                    'period'
                );

                foreach ($periods as $availablePeriod) {

                    if (
                        (int)$availablePeriod['academic_period_id']
                        === $requestedPeriod
                    ) {
                        $period = $requestedPeriod;
                        break;
                    }
                }
            }

            /*
 * Show ALL active College programs in the dropdown.
 *
 * A program does NOT need an ACTIVE timetable just to be visible.
 * has_active_timetable only tells the UI whether substitute
 * assignments can currently be performed for that program.
 */
$programs = subRows(
    $pdo,
    "
    SELECT
        p.program_id,
        p.program_code,
        p.program_name,

        CASE
            WHEN EXISTS (
                SELECT 1
                FROM schedule_batches sb
                WHERE sb.program_id = p.program_id
                  AND sb.academic_period_id = :period
                  AND sb.status = 'ACTIVE'
                  AND sb.data_origin = 'DEMO'
            )
            THEN 1
            ELSE 0
        END AS has_active_timetable

    FROM programs p

    WHERE p.is_active = 1
      AND p.education_level = 'College'

    ORDER BY p.program_code
    ",
    [
        'period' => $period
    ]
);

if (!$programs) {
    subFail(
        404,
        'NO_PROGRAMS',
        'No active College programs are available.'
    );
}

/*
 * On first load, prefer a program that already has
 * an ACTIVE saved timetable so the page immediately
 * shows useful class meetings.
 */
$selected = null;

foreach ($programs as $availableProgram) {

    if (
        (int)$availableProgram['has_active_timetable'] === 1
    ) {
        $selected = $availableProgram;
        break;
    }
}

/*
 * If no program currently has a timetable,
 * still show the first College program.
 */
if ($selected === null) {
    $selected = $programs[0];
}

/*
 * If user explicitly selected a program,
 * honor that selection whether or not it already
 * has an ACTIVE timetable.
 */
if (
    isset($_GET['program'])
    && trim((string)$_GET['program']) !== ''
) {

    $requestedProgram = strtoupper(
        trim((string)$_GET['program'])
    );

    foreach ($programs as $availableProgram) {

        if (
            strtoupper(
                (string)$availableProgram['program_code']
            ) === $requestedProgram
        ) {
            $selected = $availableProgram;
            break;
        }
    }
}

$program = (string)$selected['program_code'];

$programReady =
    (int)$selected['has_active_timetable'] === 1;

            /*
             * Selected calendar date.
             */
            $date = subDate(
                (string)(
                    $_GET['date']
                    ?? date('Y-m-d')
                )
            );

            $day = subWeekday($date);

            /*
             * ACTIVE saved class meetings for the selected program,
             * academic period and weekday.
             *
             * Existing ACTIVE one-day substitute assignments are
             * joined without modifying the original class timetable.
             */
            $meetings = subRows(
                $pdo,
                "
                SELECT
                    m.meeting_id,
                    m.batch_id,
                    m.day_of_week,

                    TIME_FORMAT(
                        m.start_time,
                        '%H:%i'
                    ) AS start_time,

                    TIME_FORMAT(
                        m.end_time,
                        '%H:%i'
                    ) AS end_time,

                    m.delivery_mode,

                    sec.section_code,
                    sec.section_type,

                    s.subject_code,
                    s.subject_title,

                    t.teacher_name
                        AS original_teacher_name,

                    COALESCE(
                        r.room_name,
                        'ONLINE'
                    ) AS room_name,

                    sa.substitute_assignment_id,

                    st.teacher_name
                        AS substitute_teacher_name

                FROM schedule_meetings m

                INNER JOIN schedule_batches sb
                    ON sb.batch_id = m.batch_id

                INNER JOIN section_subjects ss
                    ON ss.section_subject_id =
                       m.section_subject_id

                INNER JOIN sections sec
                    ON sec.section_id = ss.section_id

                INNER JOIN subjects s
                    ON s.subject_id = ss.subject_id

                INNER JOIN teachers t
                    ON t.teacher_id = m.teacher_id

                LEFT JOIN rooms r
                    ON r.room_id = m.room_id

                LEFT JOIN substitute_assignments sa
                    ON sa.meeting_id = m.meeting_id
                   AND sa.duty_date = :duty_date
                   AND sa.status = 'ACTIVE'

                LEFT JOIN teachers st
                    ON st.teacher_id =
                       sa.substitute_teacher_id

                WHERE sb.academic_period_id = :period
                  AND sb.program_id = :program
                  AND sb.status = 'ACTIVE'
                  AND sb.data_origin = 'DEMO'
                  AND m.day_of_week = :day

                ORDER BY
                    m.start_time,
                    sec.section_code,
                    s.subject_code,
                    m.meeting_id
                ",
                [
                    'duty_date' => $date,
                    'period' => $period,
                    'program' => $selected['program_id'],
                    'day' => $day
                ]
            );

            /*
             * Substitute assignment history.
             *
             * Historical assignments remain visible even when the
             * source class timetable batch has been superseded.
             */
            $history = subRows(
                $pdo,
                "
                SELECT
                    sa.substitute_assignment_id,
                    sa.duty_date,
                    sa.status,
                    sa.reason,
                    sa.cancellation_reason,
                    sa.created_at,
                    sa.cancelled_at,

                    sec.section_code,
                    s.subject_code,

                    m.delivery_mode,

                    TIME_FORMAT(
                        m.start_time,
                        '%H:%i'
                    ) AS start_time,

                    TIME_FORMAT(
                        m.end_time,
                        '%H:%i'
                    ) AS end_time,

                    ot.teacher_name
                        AS original_teacher_name,

                    st.teacher_name
                        AS substitute_teacher_name,

                    CASE
                        WHEN sb.status = 'ACTIVE'
                        THEN 1
                        ELSE 0
                    END AS source_batch_active

                FROM substitute_assignments sa

                INNER JOIN schedule_meetings m
                    ON m.meeting_id = sa.meeting_id

                INNER JOIN schedule_batches sb
                    ON sb.batch_id =
                       sa.class_batch_id

                INNER JOIN section_subjects ss
                    ON ss.section_subject_id =
                       m.section_subject_id

                INNER JOIN sections sec
                    ON sec.section_id = ss.section_id

                INNER JOIN subjects s
                    ON s.subject_id = ss.subject_id

                INNER JOIN teachers ot
                    ON ot.teacher_id =
                       sa.original_teacher_id

                INNER JOIN teachers st
                    ON st.teacher_id =
                       sa.substitute_teacher_id

                WHERE sa.academic_period_id = :period
                  AND sb.program_id = :program

                ORDER BY
                    sa.created_at DESC,
                    sa.substitute_assignment_id DESC

                LIMIT 150
                ",
                [
                    'period' => $period,
                    'program' => $selected['program_id']
                ]
            );

            /*
             * Successful catalog response.
             */
            subReply(
                200,
                [
                    'success' => true,
                    'status' => 'SUBSTITUTE_CATALOG_READY',

                    'periods' => $periods,
                    'programs' => $programs,

                    'selected_period_id' => $period,
                    'selected_program' => $selected,

                    'selected_date' => $date,
                    'selected_weekday' => $day,

                    'meetings' => $meetings,
                    'history' => $history,

                    'csrf_token' =>
                        $_SESSION['sub_csrf'],

                    'database_write' => false
                ]
            );
        }

        /*
         * ========================================================
         * SUBSTITUTE CANDIDATES
         * ========================================================
         */
        if ($action === 'candidates') {

            $period = subInt(
                $_GET['period_id'] ?? null,
                'period'
            );

            $meetingId = subInt(
                $_GET['meeting_id'] ?? null,
                'meeting'
            );

            $date = subDate(
                $_GET['date'] ?? null
            );

            /*
             * Read latest meeting facts from DB.
             */
            $meeting = subMeeting(
                $pdo,
                $meetingId,
                $period
            );

            /*
             * One-day substitute assignment must match the recurring
             * weekday of the original saved class.
             */
            if (
                $meeting['day_of_week']
                !== subWeekday($date)
            ) {
                subFail(
                    400,
                    'DAY_MISMATCH',
                    'The selected date does not match the class weekday.'
                );
            }

            /*
             * Check if the meeting/date already has an ACTIVE
             * substitute.
             */
            $already = subOne(
                $pdo,
                "
                SELECT
                    substitute_assignment_id
                FROM substitute_assignments
                WHERE meeting_id = :meeting
                  AND duty_date = :date
                  AND status = 'ACTIVE'
                ",
                [
                    'meeting' => $meetingId,
                    'date' => $date
                ]
            );

            /*
             * Candidate pool:
             * same program, ACTIVE faculty, DEMO data.
             */
            $teachers = subRows(
                $pdo,
                "
                SELECT
                    teacher_id,
                    teacher_name,
                    employee_no
                FROM teachers
                WHERE program_id = :program
                  AND status = 'ACTIVE'
                  AND data_origin = 'DEMO'
                ORDER BY teacher_name
                ",
                [
                    'program' =>
                        $meeting['program_id']
                ]
            );

            $candidates = [];

            foreach ($teachers as $teacherRow) {

                $teacherId =
                    (int)$teacherRow['teacher_id'];

                /*
                 * Server-side eligibility checks include:
                 * authorization,
                 * teacher availability,
                 * saved class conflicts,
                 * exam conflicts,
                 * other substitute duties, etc.
                 */
                $issues = subTeacherIssues(
                    $pdo,
                    $meeting,
                    $date,
                    $teacherId
                );

                if ($already) {
                    $issues[] =
                        'This class already has an ACTIVE substitute on the selected date.';
                }

                $candidates[] = [
                    'teacher_id' => $teacherId,

                    'teacher_name' =>
                        $teacherRow['teacher_name'],

                    'employee_no' =>
                        $teacherRow['employee_no'],

                    'eligible' =>
                        count($issues) === 0,

                    'issues' => $issues
                ];
            }

            subReply(
                200,
                [
                    'success' => true,

                    'status' =>
                        'SUBSTITUTE_CANDIDATES_READY',

                    'meeting' => $meeting,
                    'duty_date' => $date,

                    'candidates' => $candidates,

                    'available_count' =>
                        count(
                            array_filter(
                                $candidates,
                                static fn(array $candidate): bool =>
                                    $candidate['eligible'] === true
                            )
                        ),

                    'database_write' => false
                ]
            );
        }

        /*
         * Unknown GET action.
         */
        subFail(
            400,
            'INVALID_ACTION',
            'Unknown tracker action.'
        );
    }

    /*
     * ============================================================
     * POST REQUESTS
     * ============================================================
     */

    /*
     * All writes remain local DEMO writes.
     * Browser assignments are still independently revalidated on
     * the server before persistence.
     */
    $input = json_decode(
        file_get_contents('php://input'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    /*
     * Session CSRF protection.
     */
    if (
        !is_array($input)
        || !is_string(
            $input['csrf_token'] ?? null
        )
        || !hash_equals(
            (string)$_SESSION['sub_csrf'],
            $input['csrf_token']
        )
    ) {
        subFail(
            403,
            'INVALID_CSRF',
            'Refresh the module and try again.'
        );
    }

    $action = (string)(
        $input['action'] ?? ''
    );

    $period = subInt(
        $input['period_id'] ?? null,
        'period'
    );

    /*
     * Period-specific database mutex.
     */
    $lockName =
        'bcp_substitute_period_' . $period;

    $stmt = $pdo->prepare(
        'SELECT GET_LOCK(:key,10)'
    );

    $stmt->execute([
        'key' => $lockName
    ]);

    if (
        (int)$stmt->fetchColumn() !== 1
    ) {
        subFail(
            409,
            'TRACKER_BUSY',
            'Another substitution is being updated. Retry.'
        );
    }

    $locked = true;

    $pdo->beginTransaction();

    /*
     * Lock all class schedule batches in the period.
     * This detects timetable replacement while the substitute
     * assignment is being processed.
     */
    subRows(
        $pdo,
        "
        SELECT
            batch_id,
            status
        FROM schedule_batches
        WHERE academic_period_id = :period
        ORDER BY batch_id
        FOR UPDATE
        ",
        [
            'period' => $period
        ]
    );

    /*
     * ============================================================
     * ASSIGN SUBSTITUTE
     * ============================================================
     */
    if ($action === 'assign') {

        $meetingId = subInt(
            $input['meeting_id'] ?? null,
            'meeting'
        );

        $teacher = subInt(
            $input['substitute_teacher_id']
                ?? null,
            'substitute professor'
        );

        $date = subDate(
            $input['duty_date'] ?? null
        );

        $reason = trim(
            (string)(
                $input['reason'] ?? ''
            )
        );

        if (
            subTextLength($reason) < 5
            || subTextLength($reason) > 500
        ) {
            subFail(
                400,
                'INVALID_REASON',
                'Enter a brief reason (5–500 characters).'
            );
        }

        /*
         * Re-read meeting from current DB.
         */
        $meeting = subMeeting(
            $pdo,
            $meetingId,
            $period
        );

        if (
            $meeting['day_of_week']
            !== subWeekday($date)
        ) {
            subFail(
                400,
                'DAY_MISMATCH',
                'Selected date does not match the regular class day.'
            );
        }

        /*
         * Protect against duplicate ACTIVE assignment.
         */
        $exists = subRows(
            $pdo,
            "
            SELECT
                substitute_assignment_id
            FROM substitute_assignments
            WHERE meeting_id = :meeting
              AND duty_date = :date
              AND status = 'ACTIVE'
            FOR UPDATE
            ",
            [
                'meeting' => $meetingId,
                'date' => $date
            ]
        );

        if ($exists) {
            subFail(
                409,
                'DUTY_ALREADY_ASSIGNED',
                'A substitute is already assigned for this meeting/date.'
            );
        }

        /*
         * Full current eligibility validation.
         */
        $issues = subTeacherIssues(
            $pdo,
            $meeting,
            $date,
            $teacher
        );

        if ($issues) {
            subFail(
                422,
                'SUBSTITUTE_NOT_ELIGIBLE',
                implode(' ', $issues)
            );
        }

        /*
         * Save one-day temporary assignment.
         *
         * Original schedule meeting remains unchanged.
         */
        $stmt = $pdo->prepare(
            "
            INSERT INTO substitute_assignments
            (
                meeting_id,
                class_batch_id,
                academic_period_id,
                duty_date,
                original_teacher_id,
                substitute_teacher_id,
                reason,
                status
            )
            VALUES
            (
                :meeting,
                :batch,
                :period,
                :date,
                :original,
                :teacher,
                :reason,
                'ACTIVE'
            )
            "
        );

        $stmt->execute([
            'meeting' => $meetingId,
            'batch' => $meeting['batch_id'],
            'period' => $period,
            'date' => $date,

            'original' =>
                $meeting['original_teacher_id'],

            'teacher' => $teacher,
            'reason' => $reason
        ]);

        $newId = (int)$pdo->lastInsertId();

        $pdo->commit();

        /*
         * Release mutex before response.
         */
        $pdo
            ->prepare(
                'SELECT RELEASE_LOCK(:key)'
            )
            ->execute([
                'key' => $lockName
            ]);

        $locked = false;

        subReply(
            200,
            [
                'success' => true,
                'status' =>
                    'SUBSTITUTE_ASSIGNED',

                'substitute_assignment_id' =>
                    $newId,

                'meeting_id' =>
                    $meetingId,

                'duty_date' =>
                    $date,

                'database_write' => true
            ]
        );
    }

    /*
     * ============================================================
     * CANCEL SUBSTITUTE ASSIGNMENT
     * ============================================================
     */
    if ($action === 'cancel') {

        $id = subInt(
            $input['substitute_assignment_id']
                ?? null,
            'substitute assignment'
        );

        $reason = trim(
            (string)(
                $input['cancellation_reason']
                ?? ''
            )
        );

        if (
            subTextLength($reason) < 5
            || subTextLength($reason) > 500
        ) {
            subFail(
                400,
                'INVALID_REASON',
                'Enter a cancellation reason (5–500 characters).'
            );
        }

        /*
         * Lock current assignment record.
         */
        $record = subOne(
            $pdo,
            "
            SELECT
                sa.substitute_assignment_id,
                sa.status,
                sa.class_batch_id
            FROM substitute_assignments sa
            WHERE
                sa.substitute_assignment_id = :id
                AND sa.academic_period_id =
                    :period
            FOR UPDATE
            ",
            [
                'id' => $id,
                'period' => $period
            ]
        );

        if (
            !$record
            || $record['status'] !== 'ACTIVE'
        ) {
            subFail(
                409,
                'ASSIGNMENT_NOT_ACTIVE',
                'The substitute assignment is already cancelled or unavailable.'
            );
        }

        /*
         * Preserve assignment historically.
         * Never delete the record.
         */
        $stmt = $pdo->prepare(
            "
            UPDATE substitute_assignments
            SET
                status = 'CANCELLED',
                cancelled_at =
                    CURRENT_TIMESTAMP,
                cancellation_reason =
                    :reason
            WHERE
                substitute_assignment_id = :id
                AND status = 'ACTIVE'
            "
        );

        $stmt->execute([
            'reason' => $reason,
            'id' => $id
        ]);

        if ($stmt->rowCount() !== 1) {
            subFail(
                409,
                'CANCEL_CONFLICT',
                'This assignment was changed. Refresh.'
            );
        }

        $pdo->commit();

        $pdo
            ->prepare(
                'SELECT RELEASE_LOCK(:key)'
            )
            ->execute([
                'key' => $lockName
            ]);

        $locked = false;

        subReply(
            200,
            [
                'success' => true,

                'status' =>
                    'SUBSTITUTE_CANCELLED',

                'substitute_assignment_id' =>
                    $id,

                'database_write' => true
            ]
        );
    }

    /*
     * Unknown POST action.
     */
    subFail(
        400,
        'INVALID_ACTION',
        'Only assign or cancel is supported.'
    );

} catch (SubstituteError $e) {

    /*
     * Roll back any unfinished transaction.
     */
    if (
        $pdo instanceof PDO
        && $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    /*
     * Always release database mutex.
     */
    if (
        $pdo instanceof PDO
        && $locked
    ) {
        try {

            $pdo
                ->prepare(
                    'SELECT RELEASE_LOCK(:key)'
                )
                ->execute([
                    'key' => $lockName
                ]);

        } catch (Throwable) {
            // Nothing else should override
            // the original API error.
        }
    }

    subReply(
        $e->httpCode,
        [
            'success' => false,

            'status' =>
                $e->apiStatus,

            'message' =>
                $e->getMessage(),

            'database_write' => false
        ]
    );

} catch (Throwable $e) {

    /*
     * Roll back any unfinished transaction.
     */
    if (
        $pdo instanceof PDO
        && $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    /*
     * Release named lock even after unexpected failure.
     */
    if (
        $pdo instanceof PDO
        && $locked
    ) {
        try {

            $pdo
                ->prepare(
                    'SELECT RELEASE_LOCK(:key)'
                )
                ->execute([
                    'key' => $lockName
                ]);

        } catch (Throwable) {
            // Ignore secondary cleanup error.
        }
    }

    error_log(
        'BCP Substitute Tracker: '
        . $e->getMessage()
    );

    subReply(
        500,
        [
            'success' => false,

            'status' =>
                'SUBSTITUTE_API_ERROR',

            'message' =>
                'Substitute action failed; see PHP error log. No partial update should be saved.',

            'database_write' => false
        ]
    );
}
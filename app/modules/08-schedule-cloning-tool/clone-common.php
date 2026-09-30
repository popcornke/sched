<?php
declare(strict_types=1);

/**
 * BCP Module 8
 * Schedule Cloning Tool
 *
 * Shared clone-preview / clone-save logic.
 *
 * IMPORTANT:
 * - This file does NOT write to the database.
 * - It does NOT create schedule batches.
 * - It does NOT insert schedule meetings.
 * - It only loads, maps and validates data.
 */


/* =========================================================
   DATABASE HELPERS
========================================================= */

function cloneRows(
    PDO $db,
    string $sql,
    array $params = []
): array {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );
}


function cloneRow(
    PDO $db,
    string $sql,
    array $params = []
): ?array {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $row = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    return $row === false
        ? null
        : $row;
}


function cloneCount(
    PDO $db,
    string $sql,
    array $params = []
): int {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}


/* =========================================================
   BASIC HELPERS
========================================================= */

function cloneTime(
    ?string $value
): string {
    if ($value === null) {
        return '';
    }

    return substr(
        $value,
        0,
        8
    );
}


function cloneOverlap(
    string $startA,
    string $endA,
    string $startB,
    string $endB
): bool {
    return (
        $startA < $endB
        && $startB < $endA
    );
}


function cloneAddIssue(
    array &$issues,
    string $code,
    string $message,
    string $severity = 'HARD'
): void {
    $issues[] = [
        'severity' => $severity,
        'code' => $code,
        'message' => $message,
    ];
}


/* =========================================================
   SOURCE / TARGET CONTEXT
========================================================= */

function cloneLoadContext(
    PDO $db,
    int $sourceBatchId,
    int $targetPeriodId
): array {

    $source = cloneRow(
        $db,
        "
            SELECT
                b.batch_id,
                b.academic_period_id,
                b.program_id,
                b.data_origin,
                b.status,
                ap.academic_year,
                ap.semester,
                ap.period_status,
                p.program_code,
                p.program_name
            FROM schedule_batches b

            INNER JOIN academic_periods ap
                ON ap.academic_period_id =
                   b.academic_period_id

            INNER JOIN programs p
                ON p.program_id =
                   b.program_id

            WHERE b.batch_id = :batch
            LIMIT 1
        ",
        [
            'batch' => $sourceBatchId,
        ]
    );


    if ($source === null) {
        throw new RuntimeException(
            'The selected source schedule batch does not exist.'
        );
    }


    if (
        strtoupper(
            (string) $source['status']
        ) !== 'ACTIVE'
    ) {
        throw new RuntimeException(
            'Only an ACTIVE source timetable can be cloned.'
        );
    }


    $target = cloneRow(
        $db,
        "
            SELECT
                academic_period_id,
                academic_year,
                semester,
                period_status
            FROM academic_periods
            WHERE academic_period_id = :period
            LIMIT 1
        ",
        [
            'period' => $targetPeriodId,
        ]
    );


    if ($target === null) {
        throw new RuntimeException(
            'The selected target academic period does not exist.'
        );
    }


    if (
        (int) $source['academic_period_id']
        === $targetPeriodId
    ) {
        throw new RuntimeException(
            'The source and target academic periods must be different.'
        );
    }


    return [
        'source' => [
            'batch_id' =>
                (int) $source['batch_id'],

            'academic_period_id' =>
                (int) $source['academic_period_id'],

            'program_id' =>
                (int) $source['program_id'],

            'program_code' =>
                (string) $source['program_code'],

            'program_name' =>
                (string) $source['program_name'],

            'academic_year' =>
                (string) $source['academic_year'],

            'semester' =>
                (int) $source['semester'],

            'status' =>
                (string) $source['status'],

            'data_origin' =>
                (string) $source['data_origin'],
        ],

        'target' => [
            'academic_period_id' =>
                (int) $target['academic_period_id'],

            'academic_year' =>
                (string) $target['academic_year'],

            'semester' =>
                (int) $target['semester'],

            'period_status' =>
                (string) $target['period_status'],
        ],
    ];
}


/* =========================================================
   SOURCE MEETINGS
========================================================= */

function cloneLoadSourceMeetings(
    PDO $db,
    int $sourceBatchId
): array {

    $rows = cloneRows(
        $db,
        "
            SELECT
                m.meeting_id,
                m.batch_id,
                m.section_subject_id,
                m.teacher_id,
                m.room_id,
                m.delivery_mode,
                m.day_of_week,
                m.start_time,
                m.end_time,

                ss.section_id,
                ss.subject_id,

                s.section_code,
                s.year_level,
                s.section_type,
                s.student_count,

                sub.subject_code,
                sub.subject_title,

                t.employee_no,
                t.teacher_name,
                t.status AS teacher_status,

                r.room_name,
                r.room_type,
                r.capacity AS room_capacity,
                r.status AS room_status

            FROM schedule_meetings m

            INNER JOIN section_subjects ss
                ON ss.section_subject_id =
                   m.section_subject_id

            INNER JOIN sections s
                ON s.section_id =
                   ss.section_id

            INNER JOIN subjects sub
                ON sub.subject_id =
                   ss.subject_id

            INNER JOIN teachers t
                ON t.teacher_id =
                   m.teacher_id

            LEFT JOIN rooms r
                ON r.room_id =
                   m.room_id

            WHERE m.batch_id = :batch

            ORDER BY
                FIELD(
                    m.day_of_week,
                    'Monday',
                    'Tuesday',
                    'Wednesday',
                    'Thursday',
                    'Friday',
                    'Saturday'
                ),
                m.start_time,
                s.section_code,
                sub.subject_code,
                m.meeting_id
        ",
        [
            'batch' => $sourceBatchId,
        ]
    );


    foreach (
        $rows
        as &$row
    ) {
        foreach (
            [
                'meeting_id',
                'batch_id',
                'section_subject_id',
                'teacher_id',
                'section_id',
                'subject_id',
                'year_level',
                'student_count',
            ]
            as $field
        ) {
            $row[$field] =
                (int) $row[$field];
        }


        $row['room_id'] =
            $row['room_id'] === null
            ? null
            : (int) $row['room_id'];


        $row['room_capacity'] =
            $row['room_capacity'] === null
            ? null
            : (int) $row['room_capacity'];


        $row['start_time'] =
            cloneTime(
                $row['start_time']
            );


        $row['end_time'] =
            cloneTime(
                $row['end_time']
            );
    }

    unset($row);


    return $rows;
}


/* =========================================================
   TARGET SECTION-SUBJECT MAPPING
========================================================= */

function cloneBuildTargetPairMap(
    PDO $db,
    int $targetPeriodId,
    int $programId
): array {

    $rows = cloneRows(
        $db,
        "
            SELECT
                ss.section_subject_id,
                ss.subject_id,

                s.section_id,
                s.section_code,
                s.year_level,
                s.section_type,
                s.student_count,

                sub.subject_code

            FROM section_subjects ss

            INNER JOIN sections s
                ON s.section_id =
                   ss.section_id

            INNER JOIN subjects sub
                ON sub.subject_id =
                   ss.subject_id

            WHERE s.academic_period_id = :period
              AND s.program_id = :program
              AND s.is_active = 1

            ORDER BY
                s.section_code,
                sub.subject_code
        ",
        [
            'period' => $targetPeriodId,
            'program' => $programId,
        ]
    );


    $map = [];


    foreach (
        $rows
        as $row
    ) {

        $key = implode(
            '|',
            [
                $row['section_code'],
                $row['year_level'],
                $row['section_type'],
                $row['subject_code'],
            ]
        );


        $map[$key][] = [
            'target_section_subject_id' =>
                (int) $row['section_subject_id'],

            'target_section_id' =>
                (int) $row['section_id'],

            'target_subject_id' =>
                (int) $row['subject_id'],

            'section_code' =>
                (string) $row['section_code'],

            'year_level' =>
                (int) $row['year_level'],

            'section_type' =>
                (string) $row['section_type'],

            'subject_code' =>
                (string) $row['subject_code'],

            'student_count' =>
                (int) $row['student_count'],
        ];
    }


    return $map;
}


/* =========================================================
   TARGET STUDENT INVENTORY
========================================================= */

function cloneTargetStudentCount(
    PDO $db,
    int $targetPeriodId,
    int $programId
): int {

    return cloneCount(
        $db,
        "
            SELECT COUNT(*)
            FROM students
            WHERE academic_period_id = :period
              AND program_id = :program
        ",
        [
            'period' => $targetPeriodId,
            'program' => $programId,
        ]
    );
}


/* =========================================================
   TARGET CLUSTER / MAJOR LINKS
========================================================= */

function cloneTargetMajorLinkCount(
    PDO $db,
    int $targetPeriodId,
    int $programId
): int {

    return cloneCount(
        $db,
        "
            SELECT COUNT(*)

            FROM section_major_links sml

            INNER JOIN sections home
                ON home.section_id =
                   sml.home_section_id

            INNER JOIN sections major
                ON major.section_id =
                   sml.major_section_id

            WHERE home.academic_period_id = :period
              AND major.academic_period_id = :period2
              AND home.program_id = :program
              AND major.program_id = :program2
        ",
        [
            'period' => $targetPeriodId,
            'period2' => $targetPeriodId,
            'program' => $programId,
            'program2' => $programId,
        ]
    );
}


/* =========================================================
   TEACHER AUTHORIZATION
========================================================= */

function cloneTeacherAuthorized(
    PDO $db,
    int $teacherId,
    int $subjectId
): bool {

    return cloneCount(
        $db,
        "
            SELECT COUNT(*)

            FROM teacher_subject_authorizations tsa

            INNER JOIN teachers t
                ON t.teacher_id =
                   tsa.teacher_id

            WHERE tsa.teacher_id = :teacher
              AND tsa.subject_id = :subject
              AND t.status = 'ACTIVE'
        ",
        [
            'teacher' => $teacherId,
            'subject' => $subjectId,
        ]
    ) > 0;
}


/* =========================================================
   TEACHER AVAILABILITY
========================================================= */

function cloneTeacherAvailable(
    PDO $db,
    int $teacherId,
    int $targetPeriodId,
    string $day,
    string $start,
    string $end
): bool {

    return cloneCount(
        $db,
        "
            SELECT COUNT(*)

            FROM teacher_availability

            WHERE teacher_id = :teacher
              AND academic_period_id = :period
              AND day_of_week = :day
              AND availability_status = 'AVAILABLE'
              AND start_time <= :start
              AND end_time >= :end
        ",
        [
            'teacher' => $teacherId,
            'period' => $targetPeriodId,
            'day' => $day,
            'start' => $start,
            'end' => $end,
        ]
    ) > 0;
}


/* =========================================================
   ROOM AVAILABILITY
========================================================= */

function cloneRoomAvailable(
    PDO $db,
    int $roomId,
    int $targetPeriodId,
    string $day,
    string $start,
    string $end
): bool {

    return cloneCount(
        $db,
        "
            SELECT COUNT(*)

            FROM room_availability ra

            INNER JOIN rooms r
                ON r.room_id =
                   ra.room_id

            WHERE ra.room_id = :room
              AND ra.academic_period_id = :period
              AND ra.day_of_week = :day
              AND ra.availability_status = 'AVAILABLE'
              AND ra.start_time <= :start
              AND ra.end_time >= :end
              AND r.status = 'AVAILABLE'
        ",
        [
            'room' => $roomId,
            'period' => $targetPeriodId,
            'day' => $day,
            'start' => $start,
            'end' => $end,
        ]
    ) > 0;
}


/* =========================================================
   TIME-SLOT COVERAGE
========================================================= */

function cloneTimeSlotCovered(
    PDO $db,
    string $day,
    string $start,
    string $end
): bool {

    $slots = cloneRows(
        $db,
        "
            SELECT
                start_time,
                end_time

            FROM time_slots

            WHERE day_of_week = :day
              AND is_active = 1
              AND start_time >= :start
              AND end_time <= :end

            ORDER BY start_time
        ",
        [
            'day' => $day,
            'start' => $start,
            'end' => $end,
        ]
    );


    if ($slots === []) {
        return false;
    }


    $cursor =
        $start;


    foreach (
        $slots
        as $slot
    ) {

        $slotStart =
            cloneTime(
                $slot['start_time']
            );

        $slotEnd =
            cloneTime(
                $slot['end_time']
            );


        if (
            $slotStart !== $cursor
        ) {
            return false;
        }


        if (
            $slotEnd <= $slotStart
        ) {
            return false;
        }


        $cursor =
            $slotEnd;


        if (
            $cursor === $end
        ) {
            return true;
        }


        if (
            $cursor > $end
        ) {
            return false;
        }
    }


    return (
        $cursor === $end
    );
}


/* =========================================================
   TARGET ACTIVE CLASS CONFLICTS
========================================================= */

function cloneFindTargetClassConflicts(
    PDO $db,
    int $targetPeriodId,
    int $targetSectionId,
    int $teacherId,
    ?int $roomId,
    string $deliveryMode,
    string $day,
    string $start,
    string $end
): array {

    $rows = cloneRows(
        $db,
        "
            SELECT
                m.meeting_id,
                m.teacher_id,
                m.room_id,
                m.delivery_mode,
                m.day_of_week,
                m.start_time,
                m.end_time,

                s.section_id,
                s.section_code,

                sub.subject_code,

                b.batch_id,
                b.program_id

            FROM schedule_meetings m

            INNER JOIN schedule_batches b
                ON b.batch_id =
                   m.batch_id

            INNER JOIN section_subjects ss
                ON ss.section_subject_id =
                   m.section_subject_id

            INNER JOIN sections s
                ON s.section_id =
                   ss.section_id

            INNER JOIN subjects sub
                ON sub.subject_id =
                   ss.subject_id

            WHERE b.academic_period_id = :period
              AND b.status = 'ACTIVE'
              AND m.day_of_week = :day
              AND m.start_time < :end
              AND m.end_time > :start
        ",
        [
            'period' => $targetPeriodId,
            'day' => $day,
            'start' => $start,
            'end' => $end,
        ]
    );


    $conflicts = [];


    foreach (
        $rows
        as $row
    ) {

        $reasons = [];


        if (
            (int) $row['section_id']
            === $targetSectionId
        ) {
            $reasons[] =
                'SECTION_OVERLAP';
        }


        if (
            (int) $row['teacher_id']
            === $teacherId
        ) {
            $reasons[] =
                'TEACHER_OVERLAP';
        }


        if (
            strtoupper($deliveryMode)
            === 'F2F'
            && $roomId !== null
            && $row['room_id'] !== null
            && (int) $row['room_id']
               === $roomId
        ) {
            $reasons[] =
                'ROOM_OVERLAP';
        }


        if (
            $reasons !== []
        ) {

            $conflicts[] = [
                'meeting_id' =>
                    (int) $row['meeting_id'],

                'batch_id' =>
                    (int) $row['batch_id'],

                'section_code' =>
                    (string) $row['section_code'],

                'subject_code' =>
                    (string) $row['subject_code'],

                'day_of_week' =>
                    (string) $row['day_of_week'],

                'start_time' =>
                    cloneTime(
                        $row['start_time']
                    ),

                'end_time' =>
                    cloneTime(
                        $row['end_time']
                    ),

                'reasons' =>
                    $reasons,
            ];
        }
    }


    return $conflicts;
}


/* =========================================================
   TARGET EXAM INVENTORY
========================================================= */

function cloneTargetExamCount(
    PDO $db,
    int $targetPeriodId
): int {

    return cloneCount(
        $db,
        "
            SELECT COUNT(*)

            FROM exam_meetings em

            INNER JOIN exam_batches eb
                ON eb.exam_batch_id =
                   em.exam_batch_id

            WHERE eb.academic_period_id = :period
              AND eb.status = 'ACTIVE'
        ",
        [
            'period' => $targetPeriodId,
        ]
    );
}


/* =========================================================
   TARGET SPECIAL-CLASS INVENTORY
========================================================= */

function cloneTargetSpecialMeetingCount(
    PDO $db,
    int $targetPeriodId
): int {

    return cloneCount(
        $db,
        "
            SELECT COUNT(*)

            FROM special_class_meetings scm

            INNER JOIN special_classes sc
                ON sc.special_class_id =
                   scm.special_class_id

            WHERE sc.academic_period_id = :period
              AND sc.status = 'ACTIVE'
              AND scm.status = 'SCHEDULED'
        ",
        [
            'period' => $targetPeriodId,
        ]
    );
}


/* =========================================================
   BUILD TARGET PROPOSALS
========================================================= */

function cloneBuildProposals(
    PDO $db,
    array $context
): array {

    $source =
        $context['source'];

    $target =
        $context['target'];


    $sourceMeetings =
        cloneLoadSourceMeetings(
            $db,
            (int) $source['batch_id']
        );


    $targetPairMap =
        cloneBuildTargetPairMap(
            $db,
            (int) $target['academic_period_id'],
            (int) $source['program_id']
        );


    $proposals = [];

    $globalIssues = [];


    if (
        (int) $source['semester']
        !== (int) $target['semester']
    ) {
        cloneAddIssue(
            $globalIssues,
            'SEMESTER_MISMATCH',
            'Source and target semesters are different.'
        );
    }


    $targetStudentCount =
        cloneTargetStudentCount(
            $db,
            (int) $target['academic_period_id'],
            (int) $source['program_id']
        );


    if (
        $targetStudentCount === 0
    ) {
        cloneAddIssue(
            $globalIssues,
            'TARGET_STUDENTS_NOT_PREPARED',
            (
                'The target period has no student '
                . 'membership records for this program.'
            )
        );
    }


    $majorLinkCount =
        cloneTargetMajorLinkCount(
            $db,
            (int) $target['academic_period_id'],
            (int) $source['program_id']
        );


    $examCount =
        cloneTargetExamCount(
            $db,
            (int) $target['academic_period_id']
        );


    $specialMeetingCount =
        cloneTargetSpecialMeetingCount(
            $db,
            (int) $target['academic_period_id']
        );


    foreach (
        $sourceMeetings
        as $meeting
    ) {

        $issues = [];


        $mappingKey = implode(
            '|',
            [
                $meeting['section_code'],
                $meeting['year_level'],
                $meeting['section_type'],
                $meeting['subject_code'],
            ]
        );


        $matches =
            $targetPairMap[
                $mappingKey
            ] ?? [];


        $targetMapping =
            count($matches) === 1
            ? $matches[0]
            : null;


        if (
            $targetMapping === null
        ) {

            cloneAddIssue(
                $issues,
                'TARGET_MAPPING_MISSING',
                (
                    'No unique target section-subject '
                    . 'mapping exists for '
                    . $meeting['section_code']
                    . ' / '
                    . $meeting['subject_code']
                    . '.'
                )
            );

        } else {

            if (
                !cloneTeacherAuthorized(
                    $db,
                    (int) $meeting['teacher_id'],
                    (int) $meeting['subject_id']
                )
            ) {
                cloneAddIssue(
                    $issues,
                    'TEACHER_NOT_AUTHORIZED',
                    (
                        $meeting['teacher_name']
                        . ' is not authorized for '
                        . $meeting['subject_code']
                        . '.'
                    )
                );
            }


            if (
                !cloneTeacherAvailable(
                    $db,
                    (int) $meeting['teacher_id'],
                    (int) $target['academic_period_id'],
                    (string) $meeting['day_of_week'],
                    (string) $meeting['start_time'],
                    (string) $meeting['end_time']
                )
            ) {
                cloneAddIssue(
                    $issues,
                    'TEACHER_TARGET_AVAILABILITY_MISSING',
                    (
                        'Target-period teacher availability '
                        . 'does not cover this meeting.'
                    )
                );
            }


            if (
                strtoupper(
                    (string) $meeting['delivery_mode']
                ) === 'F2F'
            ) {

                if (
                    $meeting['room_id'] === null
                ) {
                    cloneAddIssue(
                        $issues,
                        'F2F_ROOM_MISSING',
                        (
                            'Face-to-face meeting '
                            . 'has no room.'
                        )
                    );

                } elseif (
                    !cloneRoomAvailable(
                        $db,
                        (int) $meeting['room_id'],
                        (int) $target['academic_period_id'],
                        (string) $meeting['day_of_week'],
                        (string) $meeting['start_time'],
                        (string) $meeting['end_time']
                    )
                ) {
                    cloneAddIssue(
                        $issues,
                        'ROOM_TARGET_AVAILABILITY_MISSING',
                        (
                            'Target-period room availability '
                            . 'does not cover this meeting.'
                        )
                    );
                }

            } elseif (
                $meeting['room_id'] !== null
            ) {

                cloneAddIssue(
                    $issues,
                    'ONLINE_ROOM_SHOULD_BE_NULL',
                    (
                        'Online meeting still contains '
                        . 'a room assignment.'
                    )
                );
            }


            if (
                !cloneTimeSlotCovered(
                    $db,
                    (string) $meeting['day_of_week'],
                    (string) $meeting['start_time'],
                    (string) $meeting['end_time']
                )
            ) {
                cloneAddIssue(
                    $issues,
                    'TIME_SLOT_NOT_COVERED',
                    (
                        'The proposed meeting interval '
                        . 'is not fully covered by ACTIVE '
                        . 'database time slots.'
                    )
                );
            }


            $classConflicts =
                cloneFindTargetClassConflicts(
                    $db,
                    (int) $target['academic_period_id'],
                    (int) $targetMapping['target_section_id'],
                    (int) $meeting['teacher_id'],
                    $meeting['room_id'] === null
                        ? null
                        : (int) $meeting['room_id'],
                    (string) $meeting['delivery_mode'],
                    (string) $meeting['day_of_week'],
                    (string) $meeting['start_time'],
                    (string) $meeting['end_time']
                );


            foreach (
                $classConflicts
                as $conflict
            ) {

                cloneAddIssue(
                    $issues,
                    'TARGET_ACTIVE_CLASS_CONFLICT',
                    (
                        'Conflicts with target ACTIVE '
                        . 'meeting #'
                        . $conflict['meeting_id']
                        . ' ('
                        . implode(
                            ', ',
                            $conflict['reasons']
                        )
                        . ').'
                    )
                );
            }
        }


        $proposals[] = [
            'source_meeting_id' =>
                (int) $meeting['meeting_id'],

            'source_section_subject_id' =>
                (int) $meeting['section_subject_id'],

            'target_section_subject_id' =>
                $targetMapping === null
                ? null
                : (int) $targetMapping[
                    'target_section_subject_id'
                ],

            'target_section_id' =>
                $targetMapping === null
                ? null
                : (int) $targetMapping[
                    'target_section_id'
                ],

            'section_code' =>
                (string) $meeting['section_code'],

            'year_level' =>
                (int) $meeting['year_level'],

            'section_type' =>
                (string) $meeting['section_type'],

            'subject_id' =>
                (int) $meeting['subject_id'],

            'subject_code' =>
                (string) $meeting['subject_code'],

            'subject_title' =>
                (string) $meeting['subject_title'],

            'teacher_id' =>
                (int) $meeting['teacher_id'],

            'teacher_name' =>
                (string) $meeting['teacher_name'],

            'room_id' =>
                $meeting['room_id'],

            'room_name' =>
                $meeting['room_name'],

            'delivery_mode' =>
                (string) $meeting['delivery_mode'],

            'day_of_week' =>
                (string) $meeting['day_of_week'],

            'start_time' =>
                (string) $meeting['start_time'],

            'end_time' =>
                (string) $meeting['end_time'],

            'validation_status' =>
                $issues === []
                ? 'VALID'
                : 'BLOCKED',

            'issues' =>
                $issues,
        ];
    }


    $hardConflictCount = 0;

    $validCount = 0;

    $blockedCount = 0;


    foreach (
        $proposals
        as $proposal
    ) {

        if (
            $proposal['validation_status']
            === 'VALID'
        ) {
            $validCount++;

        } else {
            $blockedCount++;
        }


        foreach (
            $proposal['issues']
            as $issue
        ) {
            if (
                $issue['severity']
                === 'HARD'
            ) {
                $hardConflictCount++;
            }
        }
    }


    foreach (
        $globalIssues
        as $issue
    ) {
        if (
            $issue['severity']
            === 'HARD'
        ) {
            $hardConflictCount++;
        }
    }


    return [
        'source_meeting_count' =>
            count($sourceMeetings),

        'proposal_count' =>
            count($proposals),

        'valid_proposals' =>
            $validCount,

        'blocked_proposals' =>
            $blockedCount,

        'hard_conflict_count' =>
            $hardConflictCount,

        'target_student_count' =>
            $targetStudentCount,

        'target_major_link_count' =>
            $majorLinkCount,

        'target_active_exam_meetings' =>
            $examCount,

        'target_active_special_meetings' =>
            $specialMeetingCount,

        'global_issues' =>
            $globalIssues,

        'proposals' =>
            $proposals,

        'preview_passed' =>
            (
                $hardConflictCount === 0
                && count($proposals) > 0
            ),

        'database_write' =>
            false,
    ];
}
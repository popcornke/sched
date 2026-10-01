<?php
declare(strict_types=1);

/**
 * BCP Module 8
 * Atomic Schedule Clone Save
 *
 * IMPORTANT:
 * - Rebuilds the preview from current DB state.
 * - Revalidates before every save.
 * - Creates a NEW target schedule batch.
 * - Inserts NEW schedule meetings.
 * - Never changes the source batch.
 * - Uses one DB transaction.
 * - Rolls back on any failure.
 */

require_once __DIR__ . '/../../shared/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/clone-common.php';

authRequire(
    true,
    ['ADMIN', 'SCHEDULER']
);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');


/* =========================================================
   SAVE ERROR
========================================================= */

final class CloneSaveError extends RuntimeException
{
    public function __construct(
        public int $httpStatus,
        public string $statusCode,
        string $message
    ) {
        parent::__construct($message);
    }
}


/* =========================================================
   RESPONSE HELPERS
========================================================= */

function csFail(
    int $http,
    string $code,
    string $message
): never {
    throw new CloneSaveError(
        $http,
        $code,
        $message
    );
}


function csReply(
    int $http,
    array $payload
): never {
    http_response_code($http);

    echo json_encode(
        array_merge(
            [
                'module' =>
                    'SCHEDULE_CLONING_TOOL',

                'phase' =>
                    'ATOMIC_SAVE',
            ],
            $payload
        ),
        JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_THROW_ON_ERROR
    );

    exit;
}


/* =========================================================
   INPUT HELPERS
========================================================= */

function csPositiveId(
    mixed $raw,
    string $label
): int {
    if (
        !is_string($raw)
        && !is_int($raw)
    ) {
        csFail(
            400,
            'INVALID_ID',
            'Select a valid ' . $label . '.'
        );
    }

    $raw =
        (string) $raw;

    if (
        !preg_match(
            '/^[1-9][0-9]{0,9}$/D',
            $raw
        )
    ) {
        csFail(
            400,
            'INVALID_ID',
            'Select a valid ' . $label . '.'
        );
    }

    $value =
        (int) $raw;

    if (
        $value > 2147483647
    ) {
        csFail(
            400,
            'INVALID_ID',
            'Select a valid ' . $label . '.'
        );
    }

    return $value;
}


/* =========================================================
   FINAL PROPOSAL AUDIT
========================================================= */

function csAuditProposals(
    array $result
): void {

    $sourceCount =
        (int) $result[
            'source_meeting_count'
        ];

    $proposalCount =
        (int) $result[
            'proposal_count'
        ];


    if (
        $sourceCount <= 0
    ) {
        csFail(
            422,
            'SOURCE_HAS_NO_MEETINGS',
            'The source batch contains no meetings.'
        );
    }


    if (
        $proposalCount
        !== $sourceCount
    ) {
        csFail(
            422,
            'INCOMPLETE_CLONE_PREVIEW',
            (
                'The proposed clone does not contain '
                . 'the same number of meetings as the source.'
            )
        );
    }


    if (
        (int) $result[
            'hard_conflict_count'
        ] !== 0
    ) {
        csFail(
            422,
            'FINAL_VALIDATION_FAILED',
            (
                'The latest database state now contains '
                . 'one or more hard conflicts. '
                . 'Nothing was saved.'
            )
        );
    }


    if (
        $result[
            'preview_passed'
        ] !== true
    ) {
        csFail(
            422,
            'FINAL_VALIDATION_FAILED',
            (
                'Clone validation did not pass. '
                . 'Nothing was saved.'
            )
        );
    }


    $seen =
        [];


    foreach (
        $result['proposals']
        as $proposal
    ) {

        if (
            $proposal[
                'validation_status'
            ] !== 'VALID'
        ) {
            csFail(
                422,
                'BLOCKED_PROPOSAL_FOUND',
                (
                    'A proposed meeting became blocked '
                    . 'during final validation.'
                )
            );
        }


        if (
            $proposal[
                'target_section_subject_id'
            ] === null
        ) {
            csFail(
                422,
                'TARGET_MAPPING_MISSING',
                (
                    'A target section-subject mapping '
                    . 'is missing.'
                )
            );
        }


        if (
            strtoupper(
                (string) $proposal[
                    'delivery_mode'
                ]
            ) === 'F2F'
            && $proposal[
                'room_id'
            ] === null
        ) {
            csFail(
                422,
                'F2F_ROOM_MISSING',
                (
                    'A face-to-face proposed meeting '
                    . 'has no room.'
                )
            );
        }


        if (
            strtoupper(
                (string) $proposal[
                    'delivery_mode'
                ]
            ) === 'ONLINE'
            && $proposal[
                'room_id'
            ] !== null
        ) {
            csFail(
                422,
                'ONLINE_ROOM_NOT_NULL',
                (
                    'An online proposed meeting '
                    . 'contains a room assignment.'
                )
            );
        }


        /*
         * Mirrors DB unique rule:
         * batch_id + section_subject_id + delivery_mode
         */

        $uniqueKey =
            (
                (string) $proposal[
                    'target_section_subject_id'
                ]
                . '|'
                . strtoupper(
                    (string) $proposal[
                        'delivery_mode'
                    ]
                )
            );


        if (
            isset(
                $seen[$uniqueKey]
            )
        ) {
            csFail(
                422,
                'DUPLICATE_TARGET_MEETING',
                (
                    'Final audit found a duplicate '
                    . 'target section-subject and '
                    . 'delivery-mode combination.'
                )
            );
        }


        $seen[$uniqueKey] =
            true;
    }
}


/* =========================================================
   MAIN
========================================================= */

$db = null;

try {

    /*
|--------------------------------------------------------------------------
| Clone Save access safeguard
|--------------------------------------------------------------------------
*/

$cloneRemoteEnabled = filter_var(
    getenv('SCHEDULE_CLONING_REMOTE_ENABLED') ?: 'false',
    FILTER_VALIDATE_BOOLEAN
);

$isLocalRequest = in_array(
    $_SERVER['REMOTE_ADDR'] ?? '',
    [
        '127.0.0.1',
        '::1',
    ],
    true
);

if (
    !$isLocalRequest
    && !$cloneRemoteEnabled
) {
    csFail(
        403,
        'REMOTE_CLONING_DISABLED',
        'Schedule clone saving is not enabled for remote access.'
    );
}


    /* -----------------------------------------------------
       POST only
    ----------------------------------------------------- */

    if (
        ($_SERVER[
            'REQUEST_METHOD'
        ] ?? 'GET') !== 'POST'
    ) {
        csFail(
            405,
            'METHOD_NOT_ALLOWED',
            'Clone Save accepts POST requests only.'
        );
    }


    /* -----------------------------------------------------
       JSON request
    ----------------------------------------------------- */

    $rawBody =
        file_get_contents(
            'php://input'
        );


    if (
        $rawBody === false
        || trim($rawBody) === ''
    ) {
        csFail(
            400,
            'EMPTY_REQUEST',
            'Clone Save request is empty.'
        );
    }


    try {

        $body =
            json_decode(
                $rawBody,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

    } catch (
        JsonException $error
    ) {

        csFail(
            400,
            'INVALID_JSON',
            (
                'Clone Save request contains '
                . 'invalid JSON.'
            )
        );
    }


    if (
        !is_array($body)
    ) {
        csFail(
            400,
            'INVALID_REQUEST',
            (
                'Clone Save request must '
                . 'be a JSON object.'
            )
        );
    }


    /* -----------------------------------------------------
       Explicit save confirmation
    ----------------------------------------------------- */

    if (
        ($body['confirmation'] ?? null)
        !== 'SAVE_CLONE'
    ) {
        csFail(
            400,
            'SAVE_CONFIRMATION_REQUIRED',
            (
                'Explicit clone-save confirmation '
                . 'was not provided.'
            )
        );
    }


    $sourceBatchId =
        csPositiveId(
            $body[
                'source_batch_id'
            ] ?? null,
            'source timetable'
        );


    $targetPeriodId =
        csPositiveId(
            $body[
                'target_period_id'
            ] ?? null,
            'target academic period'
        );


    /* -----------------------------------------------------
       Database
    ----------------------------------------------------- */

    $db =
        getDatabase();


    $db->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );


    /*
     * IMPORTANT:
     * Everything below runs inside one transaction.
     */

    $db->beginTransaction();


    /* -----------------------------------------------------
       Lock target academic period
    ----------------------------------------------------- */

    $targetLock =
        cloneRow(
            $db,
            "
                SELECT
                    academic_period_id
                FROM academic_periods
                WHERE academic_period_id = :period
                FOR UPDATE
            ",
            [
                'period' =>
                    $targetPeriodId,
            ]
        );


    if (
        $targetLock === null
    ) {
        csFail(
            404,
            'TARGET_PERIOD_NOT_FOUND',
            (
                'The selected target academic '
                . 'period no longer exists.'
            )
        );
    }


    /* -----------------------------------------------------
       Reload context from latest DB state
    ----------------------------------------------------- */

    $context =
        cloneLoadContext(
            $db,
            $sourceBatchId,
            $targetPeriodId
        );


    if (
        (int) $context['source'][
            'semester'
        ]
        !==
        (int) $context['target'][
            'semester'
        ]
    ) {
        csFail(
            422,
            'SEMESTER_MISMATCH',
            (
                'Source and target semesters '
                . 'must match.'
            )
        );
    }


    /* -----------------------------------------------------
       Target cannot already have ACTIVE schedule
    ----------------------------------------------------- */

    $existingActiveBatch =
        cloneRow(
            $db,
            "
                SELECT
                    batch_id
                FROM schedule_batches
                WHERE academic_period_id = :period
                  AND program_id = :program
                  AND status = 'ACTIVE'
                ORDER BY batch_id DESC
                LIMIT 1
                FOR UPDATE
            ",
            [
                'period' =>
                    (int) $context[
                        'target'
                    ][
                        'academic_period_id'
                    ],

                'program' =>
                    (int) $context[
                        'source'
                    ][
                        'program_id'
                    ],
            ]
        );


    if (
        $existingActiveBatch !== null
    ) {
        csFail(
            409,
            'TARGET_ALREADY_HAS_ACTIVE_BATCH',
            (
                'The target program already has '
                . 'an ACTIVE timetable. '
                . 'Nothing was overwritten.'
            )
        );
    }


    /* -----------------------------------------------------
       Rebuild everything from current DB state
    ----------------------------------------------------- */

    $result =
        cloneBuildProposals(
            $db,
            $context
        );


    /* -----------------------------------------------------
       Independent final audit
    ----------------------------------------------------- */

    csAuditProposals(
        $result
    );


    /* -----------------------------------------------------
       Create NEW target schedule batch
    ----------------------------------------------------- */

    $targetDataOrigin =
        strtoupper(
            (string) $context[
                'target'
            ][
                'period_status'
            ]
        );


    if (
        !in_array(
            $targetDataOrigin,
            [
                'DEMO',
                'OFFICIAL',
            ],
            true
        )
    ) {
        csFail(
            422,
            'INVALID_TARGET_DATA_ORIGIN',
            (
                'Target academic period has '
                . 'an unsupported status.'
            )
        );
    }


    $batchInsert =
        $db->prepare(
            "
                INSERT INTO schedule_batches (
                    academic_period_id,
                    program_id,
                    data_origin,
                    status
                )
                VALUES (
                    :period,
                    :program,
                    :origin,
                    'ACTIVE'
                )
            "
        );


    $batchInsert->execute(
        [
            'period' =>
                (int) $context[
                    'target'
                ][
                    'academic_period_id'
                ],

            'program' =>
                (int) $context[
                    'source'
                ][
                    'program_id'
                ],

            'origin' =>
                $targetDataOrigin,
        ]
    );


    $newBatchId =
        (int) $db->lastInsertId();


    if (
        $newBatchId <= 0
    ) {
        csFail(
            500,
            'BATCH_CREATION_FAILED',
            (
                'The target schedule batch '
                . 'could not be created.'
            )
        );
    }


    /* -----------------------------------------------------
       Insert proposed meetings
    ----------------------------------------------------- */

    $meetingInsert =
        $db->prepare(
            "
                INSERT INTO schedule_meetings (
                    batch_id,
                    section_subject_id,
                    teacher_id,
                    room_id,
                    delivery_mode,
                    day_of_week,
                    start_time,
                    end_time
                )
                VALUES (
                    :batch,
                    :section_subject,
                    :teacher,
                    :room,
                    :mode,
                    :day,
                    :start_time,
                    :end_time
                )
            "
        );


    $insertedMeetings =
        0;


    foreach (
        $result['proposals']
        as $proposal
    ) {

        $roomId =
            strtoupper(
                (string) $proposal[
                    'delivery_mode'
                ]
            ) === 'ONLINE'
            ? null
            : $proposal[
                'room_id'
            ];


        $meetingInsert->bindValue(
            ':batch',
            $newBatchId,
            PDO::PARAM_INT
        );


        $meetingInsert->bindValue(
            ':section_subject',
            (int) $proposal[
                'target_section_subject_id'
            ],
            PDO::PARAM_INT
        );


        $meetingInsert->bindValue(
            ':teacher',
            (int) $proposal[
                'teacher_id'
            ],
            PDO::PARAM_INT
        );


        if (
            $roomId === null
        ) {

            $meetingInsert->bindValue(
                ':room',
                null,
                PDO::PARAM_NULL
            );

        } else {

            $meetingInsert->bindValue(
                ':room',
                (int) $roomId,
                PDO::PARAM_INT
            );
        }


        $meetingInsert->bindValue(
            ':mode',
            (string) $proposal[
                'delivery_mode'
            ],
            PDO::PARAM_STR
        );


        $meetingInsert->bindValue(
            ':day',
            (string) $proposal[
                'day_of_week'
            ],
            PDO::PARAM_STR
        );


        $meetingInsert->bindValue(
            ':start_time',
            (string) $proposal[
                'start_time'
            ],
            PDO::PARAM_STR
        );


        $meetingInsert->bindValue(
            ':end_time',
            (string) $proposal[
                'end_time'
            ],
            PDO::PARAM_STR
        );


        $meetingInsert->execute();


        $insertedMeetings++;
    }


    /* -----------------------------------------------------
       Verify inserted count
    ----------------------------------------------------- */

    if (
        $insertedMeetings
        !==
        (int) $result[
            'proposal_count'
        ]
    ) {
        csFail(
            500,
            'INSERT_COUNT_MISMATCH',
            (
                'Not all proposed meetings '
                . 'were inserted.'
            )
        );
    }


    $databaseMeetingCount =
        cloneCount(
            $db,
            "
                SELECT COUNT(*)
                FROM schedule_meetings
                WHERE batch_id = :batch
            ",
            [
                'batch' =>
                    $newBatchId,
            ]
        );


    if (
        $databaseMeetingCount
        !== $insertedMeetings
    ) {
        csFail(
            500,
            'DATABASE_COUNT_MISMATCH',
            (
                'Saved meeting verification failed.'
            )
        );
    }


    /* -----------------------------------------------------
       Verify batch itself
    ----------------------------------------------------- */

    $savedBatch =
        cloneRow(
            $db,
            "
                SELECT
                    batch_id,
                    academic_period_id,
                    program_id,
                    data_origin,
                    status,
                    created_at
                FROM schedule_batches
                WHERE batch_id = :batch
                LIMIT 1
            ",
            [
                'batch' =>
                    $newBatchId,
            ]
        );


    if (
        $savedBatch === null
        || strtoupper(
            (string) $savedBatch[
                'status'
            ]
        ) !== 'ACTIVE'
    ) {
        csFail(
            500,
            'SAVED_BATCH_VERIFICATION_FAILED',
            (
                'Target batch verification failed.'
            )
        );
    }


    /* -----------------------------------------------------
       Create immutable reference audit batch

       IMPORTANT:
       - This is written only after final validation passed.
       - It is part of the SAME transaction as the target batch.
       - If snapshot creation or verification fails, EVERYTHING
         rolls back, including the new target timetable.
    ----------------------------------------------------- */

    $referenceBatchInsert =
        $db->prepare(
            "
                INSERT INTO schedule_reference_batches (
                    source_batch_id,
                    target_batch_id,
                    source_academic_period_id,
                    target_academic_period_id,
                    program_id,
                    reference_status,
                    source_academic_year_snapshot,
                    source_semester_snapshot,
                    target_academic_year_snapshot,
                    target_semester_snapshot,
                    total_reference_meetings
                )
                VALUES (
                    :source_batch,
                    :target_batch,
                    :source_period,
                    :target_period,
                    :program,
                    'DRAFT',
                    :source_year,
                    :source_semester,
                    :target_year,
                    :target_semester,
                    0
                )
            "
        );


    $referenceBatchInsert->execute(
        [
            'source_batch' =>
                $sourceBatchId,

            'target_batch' =>
                $newBatchId,

            'source_period' =>
                (int) $context['source'][
                    'academic_period_id'
                ],

            'target_period' =>
                (int) $context['target'][
                    'academic_period_id'
                ],

            'program' =>
                (int) $context['source'][
                    'program_id'
                ],

            'source_year' =>
                (string) $context['source'][
                    'academic_year'
                ],

            'source_semester' =>
                (int) $context['source'][
                    'semester'
                ],

            'target_year' =>
                (string) $context['target'][
                    'academic_year'
                ],

            'target_semester' =>
                (int) $context['target'][
                    'semester'
                ],
        ]
    );


    $referenceBatchId =
        (int) $db->lastInsertId();


    if (
        $referenceBatchId <= 0
    ) {
        csFail(
            500,
            'REFERENCE_BATCH_CREATION_FAILED',
            (
                'The clone reference audit batch '
                . 'could not be created.'
            )
        );
    }


    /* -----------------------------------------------------
       Snapshot the SOURCE meetings exactly as referenced
    ----------------------------------------------------- */

    $referenceMeetingInsert =
        $db->prepare(
            "
                INSERT INTO schedule_reference_meetings (
                    reference_batch_id,
                    source_meeting_id,
                    section_code_snapshot,
                    year_level_snapshot,
                    section_type_snapshot,
                    source_subject_id,
                    subject_code_snapshot,
                    subject_title_snapshot,
                    units_snapshot,
                    f2f_hours_snapshot,
                    online_hours_snapshot,
                    source_teacher_id,
                    teacher_employee_no_snapshot,
                    teacher_name_snapshot,
                    teacher_was_authorized_snapshot,
                    source_room_id,
                    room_name_snapshot,
                    building_snapshot,
                    room_type_snapshot,
                    room_capacity_snapshot,
                    delivery_mode,
                    day_of_week,
                    start_time,
                    end_time
                )
                SELECT
                    :reference_batch,
                    m.meeting_id,
                    s.section_code,
                    s.year_level,
                    s.section_type,
                    ss.subject_id,
                    sub.subject_code,
                    sub.subject_title,
                    sub.units,
                    sub.f2f_hours,
                    sub.online_hours,
                    m.teacher_id,
                    t.employee_no,
                    t.teacher_name,
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM teacher_subject_authorizations tsa
                            WHERE tsa.teacher_id = m.teacher_id
                              AND tsa.subject_id = ss.subject_id
                        ) THEN 1
                        ELSE 0
                    END,
                    m.room_id,
                    r.room_name,
                    r.building,
                    r.room_type,
                    r.capacity,
                    m.delivery_mode,
                    m.day_of_week,
                    m.start_time,
                    m.end_time
                FROM schedule_meetings m
                INNER JOIN section_subjects ss
                    ON ss.section_subject_id = m.section_subject_id
                INNER JOIN sections s
                    ON s.section_id = ss.section_id
                INNER JOIN subjects sub
                    ON sub.subject_id = ss.subject_id
                INNER JOIN teachers t
                    ON t.teacher_id = m.teacher_id
                LEFT JOIN rooms r
                    ON r.room_id = m.room_id
                WHERE m.batch_id = :source_batch
                ORDER BY m.meeting_id
            "
        );


    $referenceMeetingInsert->execute(
        [
            'reference_batch' =>
                $referenceBatchId,

            'source_batch' =>
                $sourceBatchId,
        ]
    );


    $referenceMeetingCount =
        cloneCount(
            $db,
            "
                SELECT COUNT(*)
                FROM schedule_reference_meetings
                WHERE reference_batch_id = :reference_batch
            ",
            [
                'reference_batch' =>
                    $referenceBatchId,
            ]
        );


    if (
        $referenceMeetingCount
        !==
        (int) $result['source_meeting_count']
    ) {
        csFail(
            500,
            'REFERENCE_SNAPSHOT_COUNT_MISMATCH',
            (
                'The source reference snapshot is incomplete. '
                . 'The entire clone transaction was rolled back.'
            )
        );
    }


    /* -----------------------------------------------------
       Finalize reference as USED only after snapshot passes
    ----------------------------------------------------- */

    $referenceFinalize =
        $db->prepare(
            "
                UPDATE schedule_reference_batches
                SET
                    reference_status = 'USED',
                    total_reference_meetings = :meeting_count
                WHERE reference_batch_id = :reference_batch
                  AND source_batch_id = :source_batch
                  AND target_batch_id = :target_batch
            "
        );


    $referenceFinalize->execute(
        [
            'meeting_count' =>
                $referenceMeetingCount,

            'reference_batch' =>
                $referenceBatchId,

            'source_batch' =>
                $sourceBatchId,

            'target_batch' =>
                $newBatchId,
        ]
    );


    if (
        $referenceFinalize->rowCount() !== 1
    ) {
        csFail(
            500,
            'REFERENCE_FINALIZATION_FAILED',
            (
                'The clone reference audit record '
                . 'could not be finalized.'
            )
        );
    }


    $savedReference =
        cloneRow(
            $db,
            "
                SELECT
                    reference_batch_id,
                    source_batch_id,
                    target_batch_id,
                    reference_status,
                    total_reference_meetings
                FROM schedule_reference_batches
                WHERE reference_batch_id = :reference_batch
                LIMIT 1
            ",
            [
                'reference_batch' =>
                    $referenceBatchId,
            ]
        );


    if (
        $savedReference === null
        || (int) $savedReference['source_batch_id'] !== $sourceBatchId
        || (int) $savedReference['target_batch_id'] !== $newBatchId
        || strtoupper(
            (string) $savedReference['reference_status']
        ) !== 'USED'
        || (int) $savedReference[
            'total_reference_meetings'
        ] !== $referenceMeetingCount
    ) {
        csFail(
            500,
            'REFERENCE_VERIFICATION_FAILED',
            (
                'The saved clone reference audit '
                . 'record failed verification.'
            )
        );
    }


    /* -----------------------------------------------------
       Commit
    ----------------------------------------------------- */

    $db->commit();


    /* -----------------------------------------------------
       Success
    ----------------------------------------------------- */

    csReply(
        201,
        [
            'success' =>
                true,

            'status' =>
                'CLONE_SAVED',

            'database_write' =>
                true,

            'source_batch_id' =>
                $sourceBatchId,

            'new_batch_id' =>
                $newBatchId,

            'reference_batch_id' =>
                $referenceBatchId,

            'reference_meetings' =>
                $referenceMeetingCount,

            'target_academic_period_id' =>
                (int) $context[
                    'target'
                ][
                    'academic_period_id'
                ],

            'target_academic_year' =>
                (string) $context[
                    'target'
                ][
                    'academic_year'
                ],

            'semester' =>
                (int) $context[
                    'target'
                ][
                    'semester'
                ],

            'program_id' =>
                (int) $context[
                    'source'
                ][
                    'program_id'
                ],

            'program_code' =>
                (string) $context[
                    'source'
                ][
                    'program_code'
                ],

            'saved_meetings' =>
                $insertedMeetings,

            'hard_conflicts' =>
                0,

            'message' =>
                (
                    'Schedule clone saved successfully. '
                    . 'The source timetable was not modified.'
                ),
        ]
    );

} catch (
    CloneSaveError $error
) {

    if (
        $db instanceof PDO
        && $db->inTransaction()
    ) {
        $db->rollBack();
    }


    csReply(
        $error->httpStatus,
        [
            'success' =>
                false,

            'status' =>
                $error->statusCode,

            'database_write' =>
                false,

            'message' =>
                $error->getMessage(),
        ]
    );

} catch (
    Throwable $error
) {

    if (
        $db instanceof PDO
        && $db->inTransaction()
    ) {
        $db->rollBack();
    }


    error_log(
        (
            'BCP Module 8 clone save: '
            . $error->getMessage()
        )
    );


    csReply(
        500,
        [
            'success' =>
                false,

            'status' =>
                'CLONE_SAVE_ERROR',

            'database_write' =>
                false,

            'message' =>
                (
                    'Schedule clone could not be saved. '
                    . 'The transaction was rolled back.'
                ),
        ]
    );
}
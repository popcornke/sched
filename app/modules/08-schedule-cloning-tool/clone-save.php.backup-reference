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

    /* -----------------------------------------------------
       Local DEMO safeguard
    ----------------------------------------------------- */

    if (
        !in_array(
            $_SERVER[
                'REMOTE_ADDR'
            ] ?? '',
            [
                '127.0.0.1',
                '::1',
            ],
            true
        )
    ) {
        csFail(
            403,
            'LOCAL_DEMO_ONLY',
            (
                'Schedule clone saving is currently '
                . 'available only from localhost.'
            )
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
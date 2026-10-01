<?php
declare(strict_types=1);

/**
 * BCP Module 8
 * Clone Preview Endpoint
 *
 * Purpose:
 * - Receive source batch + target academic period
 * - Build proposed cloned meetings
 * - Run read-only validation
 * - Return JSON
 *
 * IMPORTANT:
 * - NO database write
 * - NO schedule batch creation
 * - NO schedule meeting insert
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


final class ClonePreviewError extends RuntimeException
{
    public function __construct(
        public int $httpStatus,
        public string $statusCode,
        string $message
    ) {
        parent::__construct($message);
    }
}


function cpFail(
    int $http,
    string $code,
    string $message
): never {
    throw new ClonePreviewError(
        $http,
        $code,
        $message
    );
}


function cpReply(
    int $http,
    array $payload
): never {
    http_response_code($http);

    echo json_encode(
        array_merge(
            [
                'module' => 'SCHEDULE_CLONING_TOOL',
                'phase' => 'CLONE_PREVIEW',
                'database_write' => false,
                'saving_enabled' => false,
            ],
            $payload
        ),
        JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_THROW_ON_ERROR
    );

    exit;
}


function cpPositiveId(
    mixed $raw,
    string $label
): int {
    if (
        !is_string($raw)
        && !is_int($raw)
    ) {
        cpFail(
            400,
            'INVALID_ID',
            'Select a valid ' . $label . '.'
        );
    }

    $raw = (string) $raw;

    if (
        !preg_match(
            '/^[1-9][0-9]{0,9}$/D',
            $raw
        )
    ) {
        cpFail(
            400,
            'INVALID_ID',
            'Select a valid ' . $label . '.'
        );
    }

    $value = (int) $raw;

    if (
        $value > 2147483647
    ) {
        cpFail(
            400,
            'INVALID_ID',
            'Select a valid ' . $label . '.'
        );
    }

    return $value;
}


try {

    /*
|--------------------------------------------------------------------------
| Clone Preview access safeguard
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
    cpFail(
        403,
        'REMOTE_CLONING_DISABLED',
        'Clone Preview is not enabled for remote access.'
    );
}


    /*
    |--------------------------------------------------------------------------
    | POST only
    |--------------------------------------------------------------------------
    */

    if (
        ($_SERVER['REQUEST_METHOD'] ?? 'GET')
        !== 'POST'
    ) {
        cpFail(
            405,
            'METHOD_NOT_ALLOWED',
            'Clone Preview accepts POST requests only.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Read JSON body
    |--------------------------------------------------------------------------
    */

    $rawBody =
        file_get_contents(
            'php://input'
        );

    if (
        $rawBody === false
        || trim($rawBody) === ''
    ) {
        cpFail(
            400,
            'EMPTY_REQUEST',
            'Clone Preview request is empty.'
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

        cpFail(
            400,
            'INVALID_JSON',
            'Clone Preview request contains invalid JSON.'
        );
    }


    if (
        !is_array($body)
    ) {
        cpFail(
            400,
            'INVALID_REQUEST',
            'Clone Preview request must be a JSON object.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | IDs
    |--------------------------------------------------------------------------
    */

    $sourceBatchId =
        cpPositiveId(
            $body['source_batch_id'] ?? null,
            'source timetable'
        );


    $targetPeriodId =
        cpPositiveId(
            $body['target_period_id'] ?? null,
            'target academic period'
        );


    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */

    $db =
        getDatabase();

    $db->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );


    /*
    |--------------------------------------------------------------------------
    | Build source/target context
    |--------------------------------------------------------------------------
    */

    $context =
        cloneLoadContext(
            $db,
            $sourceBatchId,
            $targetPeriodId
        );


    /*
    |--------------------------------------------------------------------------
    | Basic clone-preview blockers
    |--------------------------------------------------------------------------
    */

    if (
        (int) $context['source']['semester']
        !== (int) $context['target']['semester']
    ) {
        cpFail(
            422,
            'SEMESTER_MISMATCH',
            'Source and target semesters must match before Clone Preview.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Target must not already contain ACTIVE timetable
    |--------------------------------------------------------------------------
    */

    $targetActiveBatchCount =
        cloneCount(
            $db,
            "
                SELECT COUNT(*)
                FROM schedule_batches
                WHERE academic_period_id = :period
                  AND program_id = :program
                  AND status = 'ACTIVE'
            ",
            [
                'period' =>
                    (int) $context['target']['academic_period_id'],

                'program' =>
                    (int) $context['source']['program_id'],
            ]
        );


    if (
        $targetActiveBatchCount > 0
    ) {
        cpFail(
            422,
            'TARGET_ALREADY_HAS_ACTIVE_BATCH',
            (
                'The selected target program already '
                . 'contains an ACTIVE timetable. '
                . 'Clone Preview is blocked until a '
                . 'separate replacement workflow exists.'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Build proposals
    |--------------------------------------------------------------------------
    */

    $result =
        cloneBuildProposals(
            $db,
            $context
        );


    /*
    |--------------------------------------------------------------------------
    | Summary
    |--------------------------------------------------------------------------
    */

    $summary = [
        'source_meetings' =>
            (int) $result['source_meeting_count'],

        'proposed_meetings' =>
            (int) $result['proposal_count'],

        'valid_proposals' =>
            (int) $result['valid_proposals'],

        'blocked_proposals' =>
            (int) $result['blocked_proposals'],

        'hard_conflicts' =>
            (int) $result['hard_conflict_count'],

        'target_students' =>
            (int) $result['target_student_count'],

        'target_major_links' =>
            (int) $result['target_major_link_count'],

        'target_active_exam_meetings' =>
            (int) $result['target_active_exam_meetings'],

        'target_active_special_meetings' =>
            (int) $result['target_active_special_meetings'],
    ];


    /*
    |--------------------------------------------------------------------------
    | Final preview response
    |--------------------------------------------------------------------------
    */

    cpReply(
        200,
        [
            'success' => true,

            'status' =>
                $result['preview_passed']
                ? 'CLONE_PREVIEW_VALID'
                : 'CLONE_PREVIEW_BLOCKED',

            'preview_passed' =>
                (bool) $result['preview_passed'],

            'source' =>
                $context['source'],

            'target' =>
                $context['target'],

            'summary' =>
                $summary,

            'global_issues' =>
                $result['global_issues'],

            'proposals' =>
                $result['proposals'],

            'notice' =>
                (
                    'Clone Preview only. '
                    . 'No schedule batch or meeting '
                    . 'was written to the database.'
                ),
        ]
    );

} catch (
    ClonePreviewError $error
) {

    cpReply(
        $error->httpStatus,
        [
            'success' => false,
            'status' =>
                $error->statusCode,
            'message' =>
                $error->getMessage(),
        ]
    );

} catch (
    Throwable $error
) {

    error_log(
        'BCP Module 8 clone preview: '
        . $error->getMessage()
    );


    cpReply(
        500,
        [
            'success' => false,
            'status' =>
                'CLONE_PREVIEW_ERROR',

            'message' =>
                (
                    'Unable to build Clone Preview. '
                    . 'No database changes were made.'
                ),
        ]
    );
}
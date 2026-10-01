<?php
declare(strict_types=1);

/**
 * BCP Module 8
 * Schedule Cloning Tool
 *
 * Phase 1:
 * - Authenticated catalog
 * - Read-only source/target readiness
 * - Section mapping
 * - Section-subject mapping
 *
 * IMPORTANT:
 * This file DOES NOT clone or save any timetable yet.
 */

require_once __DIR__ . '/../../shared/auth.php';
require_once __DIR__ . '/../../config/database.php';

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| API mode = true so authentication failures return JSON instead of HTML
| redirects. Only ADMIN and SCHEDULER roles are allowed.
|
*/

authRequire(
    true,
    ['ADMIN', 'SCHEDULER']
);

/*
|--------------------------------------------------------------------------
| Response headers
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');


/*
|--------------------------------------------------------------------------
| Module-specific exception
|--------------------------------------------------------------------------
*/

final class CloneCheckError extends RuntimeException
{
    public function __construct(
        public int $httpStatus,
        public string $statusCode,
        string $message
    ) {
        parent::__construct($message);
    }
}


/*
|--------------------------------------------------------------------------
| Response helpers
|--------------------------------------------------------------------------
*/

function ccFail(
    int $http,
    string $code,
    string $message
): never {
    throw new CloneCheckError(
        $http,
        $code,
        $message
    );
}


function ccReply(
    int $http,
    array $payload
): never {
    http_response_code($http);

    $response = array_merge(
        [
            'read_only' => true,
            'database_write' => false,
            'saving_enabled' => false,
            'module' => 'SCHEDULE_CLONING_TOOL',
            'phase' => 'READINESS',
        ],
        $payload
    );

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_THROW_ON_ERROR
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Database helper
|--------------------------------------------------------------------------
*/

function ccRows(
    PDO $db,
    string $sql,
    array $bind = []
): array {
    $query = $db->prepare($sql);

    $query->execute(
        $bind
    );

    return $query->fetchAll(
        PDO::FETCH_ASSOC
    );
}


/*
|--------------------------------------------------------------------------
| ID validation
|--------------------------------------------------------------------------
*/

function ccPositive(
    mixed $raw,
    string $name
): int {
    if (
        !is_string($raw)
        && !is_int($raw)
    ) {
        ccFail(
            400,
            'INVALID_ID',
            'Select a valid ' . $name . '.'
        );
    }

    $raw = (string) $raw;

    if (
        !preg_match(
            '/^[1-9][0-9]{0,9}$/D',
            $raw
        )
    ) {
        ccFail(
            400,
            'INVALID_ID',
            'Select a valid ' . $name . '.'
        );
    }

    $number = (int) $raw;

    if (
        $number > 2147483647
    ) {
        ccFail(
            400,
            'INVALID_ID',
            'Select a valid ' . $name . '.'
        );
    }

    return $number;
}


/*
|--------------------------------------------------------------------------
| Catalog
|--------------------------------------------------------------------------
|
| Loads:
| - DEMO academic periods
| - Active college programs
| - ACTIVE DEMO saved timetable batches
|
| No database changes happen here.
|
*/

function ccCatalog(
    PDO $db
): array {
    $periods = ccRows(
        $db,
        "
            SELECT
                academic_period_id,
                academic_year,
                semester,
                period_status
            FROM academic_periods
            WHERE period_status = 'DEMO'
            ORDER BY academic_period_id DESC
        "
    );

    $programs = ccRows(
        $db,
        "
            SELECT
                program_id,
                program_code,
                program_name
            FROM programs
            WHERE is_active = 1
              AND education_level = 'College'
            ORDER BY program_code
        "
    );

    $batches = ccRows(
        $db,
        "
            SELECT
                b.batch_id,
                b.academic_period_id,
                b.program_id,
                p.program_code,
                p.program_name,
                ap.academic_year,
                ap.semester,
                b.status,
                b.data_origin,
                COUNT(m.meeting_id) AS meeting_count
            FROM schedule_batches b
            INNER JOIN academic_periods ap
                ON ap.academic_period_id =
                   b.academic_period_id
            INNER JOIN programs p
                ON p.program_id =
                   b.program_id
            LEFT JOIN schedule_meetings m
                ON m.batch_id =
                   b.batch_id
            WHERE b.status = 'ACTIVE'
              AND b.data_origin = 'DEMO'
              AND ap.period_status = 'DEMO'
            GROUP BY
                b.batch_id,
                b.academic_period_id,
                b.program_id,
                p.program_code,
                p.program_name,
                ap.academic_year,
                ap.semester,
                b.status,
                b.data_origin
            ORDER BY b.batch_id DESC
        "
    );

    foreach (
        $periods
        as &$period
    ) {
        $period['academic_period_id'] =
            (int) $period['academic_period_id'];

        $period['semester'] =
            (int) $period['semester'];
    }

    unset($period);

    foreach (
        $programs
        as &$program
    ) {
        $program['program_id'] =
            (int) $program['program_id'];
    }

    unset($program);

    foreach (
        $batches
        as &$batch
    ) {
        foreach (
            [
                'batch_id',
                'academic_period_id',
                'program_id',
                'semester',
                'meeting_count',
            ]
            as $field
        ) {
            $batch[$field] =
                (int) $batch[$field];
        }
    }

    unset($batch);

    return [
        'periods' => $periods,
        'programs' => $programs,
        'source_batches' => $batches,
    ];
}


/*
|--------------------------------------------------------------------------
| Readiness assessment
|--------------------------------------------------------------------------
|
| This compares a saved source timetable with an already-prepared target
| academic period.
|
| It DOES NOT:
| - create a new period
| - create sections
| - create section-subject records
| - copy meetings
| - create schedule_batches
| - change student memberships
|
*/

function ccAssessment(
    PDO $db,
    array $catalog,
    int $sourceId,
    int $targetPeriodId
): array {

    /*
    |--------------------------------------------------------------------------
    | Resolve source batch
    |--------------------------------------------------------------------------
    */

    $source = null;

    foreach (
        $catalog['source_batches']
        as $row
    ) {
        if (
            (int) $row['batch_id']
            === $sourceId
        ) {
            $source = $row;
            break;
        }
    }

    if (
        $source === null
    ) {
        ccFail(
            404,
            'SOURCE_NOT_FOUND',
            'Select an existing ACTIVE DEMO source batch.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Resolve target academic period
    |--------------------------------------------------------------------------
    */

    $target = null;

    foreach (
        $catalog['periods']
        as $row
    ) {
        if (
            (int) $row['academic_period_id']
            === $targetPeriodId
        ) {
            $target = $row;
            break;
        }
    }

    if (
        $target === null
    ) {
        ccFail(
            404,
            'TARGET_NOT_FOUND',
            (
                'The target DEMO academic period '
                . 'does not exist.'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Source and target cannot be same period
    |--------------------------------------------------------------------------
    */

    if (
        $targetPeriodId
        === (int) $source['academic_period_id']
    ) {
        ccFail(
            422,
            'SAME_PERIOD_NOT_ALLOWED',
            (
                'The source and target academic '
                . 'period must be different.'
            )
        );
    }

    $programId =
        (int) $source['program_id'];

    $issues = [];


    /*
    |--------------------------------------------------------------------------
    | Basic source checks
    |--------------------------------------------------------------------------
    */

    if (
        (int) $source['meeting_count']
        < 1
    ) {
        $issues[] = [
            'code' => 'EMPTY_SOURCE',
            'message' => (
                'The selected source batch '
                . 'has no saved meetings.'
            ),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Semester compatibility
    |--------------------------------------------------------------------------
    */

    if (
        (int) $target['semester']
        !== (int) $source['semester']
    ) {
        $issues[] = [
            'code' => 'SEMESTER_MISMATCH',
            'message' => (
                'Target semester differs from the '
                . 'source semester. Automatic cloning '
                . 'is blocked because curriculum and '
                . 'subject requirements may differ.'
            ),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Check whether target already has an ACTIVE timetable
    |--------------------------------------------------------------------------
    */

    $targetActiveBatches = ccRows(
        $db,
        "
            SELECT
                batch_id,
                status,
                data_origin
            FROM schedule_batches
            WHERE academic_period_id = :period
              AND program_id = :program
              AND status = 'ACTIVE'
            ORDER BY batch_id
        ",
        [
            'period' => $targetPeriodId,
            'program' => $programId,
        ]
    );

    foreach (
        $targetActiveBatches
        as &$targetBatch
    ) {
        $targetBatch['batch_id'] =
            (int) $targetBatch['batch_id'];
    }

    unset($targetBatch);

    if (
        $targetActiveBatches
    ) {
        $issues[] = [
            'code' => 'TARGET_ALREADY_HAS_ACTIVE_BATCH',
            'message' => (
                'The selected target program already '
                . 'has an ACTIVE timetable. The clone '
                . 'tool will not silently replace it.'
            ),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Target sections
    |--------------------------------------------------------------------------
    */

    $targetSections = ccRows(
        $db,
        "
            SELECT
                section_id,
                section_code,
                year_level,
                section_type,
                is_active
            FROM sections
            WHERE academic_period_id = :period
              AND program_id = :program
              AND data_origin = 'DEMO'
            ORDER BY
                section_code,
                section_type,
                section_id
        ",
        [
            'period' => $targetPeriodId,
            'program' => $programId,
        ]
    );

    foreach (
        $targetSections
        as &$section
    ) {
        $section['section_id'] =
            (int) $section['section_id'];

        $section['year_level'] =
            (int) $section['year_level'];

        $section['is_active'] =
            (int) $section['is_active'];
    }

    unset($section);

    if (
        !$targetSections
    ) {
        $issues[] = [
            'code' => 'NO_TARGET_SECTIONS',
            'message' => (
                'No official sections exist for this '
                . 'program in the target academic period.'
            ),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Source sections used by saved timetable
    |--------------------------------------------------------------------------
    */

    $sourceSections = ccRows(
        $db,
        "
            SELECT DISTINCT
                s.section_id,
                s.section_code,
                s.year_level,
                s.section_type
            FROM schedule_meetings m
            INNER JOIN section_subjects ss
                ON ss.section_subject_id =
                   m.section_subject_id
            INNER JOIN sections s
                ON s.section_id =
                   ss.section_id
            WHERE m.batch_id = :batch
            ORDER BY
                s.section_code,
                s.section_type,
                s.section_id
        ",
        [
            'batch' => $sourceId,
        ]
    );

    foreach (
        $sourceSections
        as &$section
    ) {
        $section['section_id'] =
            (int) $section['section_id'];

        $section['year_level'] =
            (int) $section['year_level'];
    }

    unset($section);


    /*
    |--------------------------------------------------------------------------
    | Build target section lookup
    |--------------------------------------------------------------------------
    |
    | Section membership is NOT copied.
    |
    | Matching is based on:
    | - section_code
    | - year_level
    | - section_type
    |
    */

    $targetBySignature = [];

    foreach (
        $targetSections
        as $section
    ) {
        $signature = implode(
            '|',
            [
                $section['section_code'],
                $section['year_level'],
                $section['section_type'],
            ]
        );

        $targetBySignature[
            $signature
        ][] = $section;
    }


    /*
    |--------------------------------------------------------------------------
    | Section mapping report
    |--------------------------------------------------------------------------
    */

    $sectionMap = [];

    foreach (
        $sourceSections
        as $section
    ) {
        $signature = implode(
            '|',
            [
                $section['section_code'],
                $section['year_level'],
                $section['section_type'],
            ]
        );

        $matches = array_values(
            array_filter(
                $targetBySignature[
                    $signature
                ] ?? [],
                static function (
                    array $row
                ): bool {
                    return (
                        (int) $row['is_active']
                        === 1
                    );
                }
            )
        );

        $matchCount =
            count($matches);

        $sectionMap[] = [
            'source_section_id' =>
                (int) $section['section_id'],

            'section_code' =>
                $section['section_code'],

            'year_level' =>
                (int) $section['year_level'],

            'section_type' =>
                $section['section_type'],

            'target_section_id' =>
                $matchCount === 1
                ? (int) $matches[0]['section_id']
                : null,

            'mapping_status' =>
                $matchCount === 1
                ? 'EXACT_CODE_TYPE_YEAR_MATCH'
                : (
                    $matchCount === 0
                    ? 'MISSING'
                    : 'AMBIGUOUS'
                ),
        ];
    }

    $unmatchedSections = count(
        array_filter(
            $sectionMap,
            static function (
                array $row
            ): bool {
                return (
                    $row['mapping_status']
                    !== 'EXACT_CODE_TYPE_YEAR_MATCH'
                );
            }
        )
    );

    if (
        $unmatchedSections > 0
    ) {
        $issues[] = [
            'code' => 'SECTION_MAPPING_INCOMPLETE',
            'message' => (
                $unmatchedSections
                . ' source section(s) have no unique '
                . 'matching ACTIVE target section with '
                . 'the same code, type, and year level.'
            ),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Source section-subject pairs
    |--------------------------------------------------------------------------
    */

    $sourcePairs = ccRows(
        $db,
        "
            SELECT DISTINCT
                ss.section_subject_id,
                s.section_id,
                s.section_code,
                s.section_type,
                s.year_level,
                sub.subject_code,
                sub.subject_id
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
            WHERE m.batch_id = :batch
            ORDER BY
                s.section_code,
                sub.subject_code,
                ss.section_subject_id
        ",
        [
            'batch' => $sourceId,
        ]
    );

    foreach (
        $sourcePairs
        as &$pair
    ) {
        $pair['section_subject_id'] =
            (int) $pair['section_subject_id'];

        $pair['section_id'] =
            (int) $pair['section_id'];

        $pair['subject_id'] =
            (int) $pair['subject_id'];

        $pair['year_level'] =
            (int) $pair['year_level'];
    }

    unset($pair);


    /*
    |--------------------------------------------------------------------------
    | Target section-subject inventory
    |--------------------------------------------------------------------------
    */

    $targetPairs = ccRows(
        $db,
        "
            SELECT
                ss.section_subject_id,
                s.section_id,
                s.section_code,
                s.section_type,
                s.year_level,
                sub.subject_code,
                sub.subject_id
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
              AND s.data_origin = 'DEMO'
            ORDER BY
                s.section_code,
                sub.subject_code,
                ss.section_subject_id
        ",
        [
            'period' => $targetPeriodId,
            'program' => $programId,
        ]
    );

    foreach (
        $targetPairs
        as &$pair
    ) {
        $pair['section_subject_id'] =
            (int) $pair['section_subject_id'];

        $pair['section_id'] =
            (int) $pair['section_id'];

        $pair['subject_id'] =
            (int) $pair['subject_id'];

        $pair['year_level'] =
            (int) $pair['year_level'];
    }

    unset($pair);


    /*
    |--------------------------------------------------------------------------
    | Target section-subject lookup
    |--------------------------------------------------------------------------
    */

    $targetPairLookup = [];

    foreach (
        $targetPairs
        as $pair
    ) {
        $key = implode(
            '|',
            [
                $pair['section_code'],
                $pair['section_type'],
                $pair['year_level'],
                $pair['subject_code'],
            ]
        );

        $targetPairLookup[
            $key
        ][] = $pair;
    }


    /*
    |--------------------------------------------------------------------------
    | Section-subject mapping report
    |--------------------------------------------------------------------------
    */

    $pairMap = [];

    foreach (
        $sourcePairs
        as $pair
    ) {
        $key = implode(
            '|',
            [
                $pair['section_code'],
                $pair['section_type'],
                $pair['year_level'],
                $pair['subject_code'],
            ]
        );

        $matching = (
            $targetPairLookup[
                $key
            ] ?? []
        );

        $matchCount =
            count($matching);

        $pairMap[] = [
            'source_section_subject_id' =>
                (int) $pair['section_subject_id'],

            'section_code' =>
                $pair['section_code'],

            'subject_code' =>
                $pair['subject_code'],

            'target_section_subject_id' =>
                $matchCount === 1
                ? (int) $matching[0]['section_subject_id']
                : null,

            'mapping_status' =>
                $matchCount === 1
                ? 'EXACT_CODE_MATCH'
                : (
                    $matchCount === 0
                    ? 'MISSING'
                    : 'AMBIGUOUS'
                ),
        ];
    }

    $missingPairs = count(
        array_filter(
            $pairMap,
            static function (
                array $row
            ): bool {
                return (
                    $row['mapping_status']
                    !== 'EXACT_CODE_MATCH'
                );
            }
        )
    );

    if (
        $missingPairs > 0
    ) {
        $issues[] = [
            'code' => 'SUBJECT_MAPPING_INCOMPLETE',
            'message' => (
                $missingPairs
                . ' source section-subject pair(s) '
                . 'have no unique equivalent in the '
                . 'target academic period.'
            ),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Requirements reserved for Clone Preview
    |--------------------------------------------------------------------------
    |
    | These are NOT claimed as validated yet.
    |
    */

    $unverifiedRequirements = [
        'Teacher authorization for the target period',
        'Teacher availability and teaching load',
        'Room availability and capacity',
        'Program-specific room restrictions',
        'Target student section memberships',
        'Fourth-year Cluster and Major memberships',
        'Database time-slot compatibility',
        'Existing ACTIVE timetable conflicts',
        'Existing examination conflicts',
        'Existing special-class conflicts',
        'Target curriculum requirements',
        'School calendar and target-period policies',
        'Independent final audit before database save',
        'Atomic save transaction and rollback',
    ];


    /*
    |--------------------------------------------------------------------------
    | Readiness summary
    |--------------------------------------------------------------------------
    */

    $mappingReady = (
        $unmatchedSections === 0
        && $missingPairs === 0
    );

    $readinessPassed = (
        count($issues) === 0
    );


    return [
        'success' => true,

        'status' =>
            'CLONE_READINESS_CHECKED',

        'source' =>
            $source,

        'target_period' =>
            $target,

        'target_active_batches' =>
            $targetActiveBatches,

        'source_meeting_count' =>
            (int) $source['meeting_count'],

        'source_section_count' =>
            count($sourceSections),

        'source_section_subject_count' =>
            count($sourcePairs),

        'target_section_count' =>
            count($targetSections),

        'target_section_subject_count' =>
            count($targetPairs),

        'section_mapping' =>
            $sectionMap,

        'section_subject_mapping' =>
            $pairMap,

        'blockers' =>
            $issues,

        'unverified_requirements' =>
            $unverifiedRequirements,

        'mapping_inventory_complete' =>
            $mappingReady,

        'readiness_passed' =>
            $readinessPassed,

        /*
         * These stay false in Phase 1.
         *
         * Clone Preview and saving will be implemented
         * in the next phases.
         */
        'clone_authorized' => false,
        'clone_preview_available' => false,

        'notice' => (
            'Read-only target readiness completed. '
            . 'No timetable was cloned or saved.'
        ),
    ];
}


/*
|--------------------------------------------------------------------------
| Request execution
|--------------------------------------------------------------------------
*/

try {

   /*
|--------------------------------------------------------------------------
| Schedule cloning access safeguard
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
    ccFail(
        403,
        'REMOTE_CLONING_DISABLED',
        'Schedule cloning is not enabled for remote access.'
    );
}


    /*
    |--------------------------------------------------------------------------
    | GET only during readiness phase
    |--------------------------------------------------------------------------
    */

    if (
        ($_SERVER['REQUEST_METHOD'] ?? 'GET')
        !== 'GET'
    ) {
        ccFail(
            405,
            'METHOD_NOT_ALLOWED',
            (
                'The current readiness API '
                . 'accepts GET requests only.'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Action
    |--------------------------------------------------------------------------
    */

    $action = (string) (
        $_GET['action']
        ?? 'catalog'
    );

    if (
        !in_array(
            $action,
            [
                'catalog',
                'assess',
            ],
            true
        )
    ) {
        ccFail(
            400,
            'INVALID_ACTION',
            (
                'Supported actions are '
                . 'catalog and assess.'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */

    $db = getDatabase();

    $db->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );


    /*
    |--------------------------------------------------------------------------
    | Catalog
    |--------------------------------------------------------------------------
    */

    $catalog = ccCatalog(
        $db
    );

    if (
        $action === 'catalog'
    ) {
        ccReply(
            200,
            array_merge(
                [
                    'success' => true,
                    'status' => 'CLONE_CATALOG_READY',

                    'target_periods_available' =>
                        count(
                            $catalog['periods']
                        ) > 1,

                    'clone_authorized' => false,
                    'clone_preview_available' => false,
                ],
                $catalog
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Readiness assessment
    |--------------------------------------------------------------------------
    */

    $sourceId = ccPositive(
        $_GET['source_batch_id']
        ?? null,
        'source timetable'
    );

    $targetId = ccPositive(
        $_GET['target_period_id']
        ?? null,
        'target academic period'
    );

    $assessment = ccAssessment(
        $db,
        $catalog,
        $sourceId,
        $targetId
    );

    ccReply(
        200,
        $assessment
    );

} catch (
    CloneCheckError $error
) {

    ccReply(
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
        'BCP Module 8 clone readiness: '
        . $error->getMessage()
    );

    ccReply(
        500,
        [
            'success' => false,
            'status' =>
                'CLONE_READINESS_ERROR',

            'message' => (
                'Unable to check the schedule '
                . 'cloning readiness. No database '
                . 'changes were made.'
            ),
        ]
    );
}
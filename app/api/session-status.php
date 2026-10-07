<?php

declare(strict_types=1);

require_once dirname(__DIR__)
    . '/shared/auth.php';

authStart();
authNoCache();

header(
    'Content-Type: application/json; charset=utf-8'
);

function sessionGuardRespond(
    int $httpStatus,
    array $payload
): never {
    http_response_code(
        $httpStatus
    );

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

function sessionGuardInput(): array
{
    $contentType = strtolower(
        (string) (
            $_SERVER['CONTENT_TYPE']
            ?? ''
        )
    );

    if (
        str_contains(
            $contentType,
            'application/json'
        )
    ) {
        $raw =
            file_get_contents(
                'php://input'
            );

        $decoded =
            is_string($raw)
                ? json_decode(
                    $raw,
                    true
                )
                : null;

        return is_array($decoded)
            ? $decoded
            : [];
    }

    return $_POST;
}

function sessionGuardStatus(
    string $reason
): string {
    return match ($reason) {
        'IDLE_TIMEOUT' =>
            'SESSION_IDLE_TIMEOUT',

        'MAX_SESSION_LIFETIME' =>
            'SESSION_MAX_LIFETIME',

        'SESSION_REVOKED' =>
            'SESSION_REVOKED',

        default =>
            'AUTH_REQUIRED',
    };
}

function sessionGuardStatePayload(
    array $state
): array {
    return [
        'active' =>
            (bool) (
                $state['active']
                ?? false
            ),

        'reason' =>
            (string) (
                $state['reason']
                ?? 'NO_SESSION'
            ),

        'server_time' =>
            (int) (
                $state['server_time']
                ?? time()
            ),

        'login_time' =>
            (int) (
                $state['login_time']
                ?? 0
            ),

        'last_activity' =>
            (int) (
                $state['last_activity']
                ?? 0
            ),

        'idle_remaining_seconds' =>
            (int) (
                $state['idle_remaining']
                ?? 0
            ),

        'max_remaining_seconds' =>
            (int) (
                $state['max_remaining']
                ?? 0
            ),

        'expires_in_seconds' =>
            (int) (
                $state['expires_in']
                ?? 0
            ),

        'idle_limit_seconds' =>
            SESSION_IDLE_LIMIT,

        'max_session_seconds' =>
            SESSION_MAX_LIFETIME,
    ];
}

$method = strtoupper(
    (string) (
        $_SERVER['REQUEST_METHOD']
        ?? 'GET'
    )
);

if (
    !in_array(
        $method,
        ['GET', 'POST'],
        true
    )
) {
    header(
        'Allow: GET, POST'
    );

    sessionGuardRespond(
        405,
        [
            'success' => false,
            'status' =>
                'METHOD_NOT_ALLOWED',
        ]
    );
}

$state =
    authSessionState(false);

if (
    !($state['active'] ?? false)
) {
    $reason = (string) (
        $state['reason']
        ?? 'NO_SESSION'
    );

    $payload = [
        'success' => false,
        'status' =>
            sessionGuardStatus(
                $reason
            ),
        'reason' => $reason,
        'message' =>
            authSessionReasonMessage(
                $reason
            ),
        'login_url' =>
            authLoginUrl(),
        'session' =>
            sessionGuardStatePayload(
                $state
            ),
    ];

    authClear();

    sessionGuardRespond(
        401,
        $payload
    );
}

$userId = (int) (
    $_SESSION['auth_user_id']
    ?? 0
);

$db = authDb();

$stmt = $db->prepare(
    'SELECT
        role,
        is_active
     FROM auth_users
     WHERE user_id = :user_id
     LIMIT 1'
);

$stmt->execute([
    'user_id' => $userId,
]);

$user =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );

if (
    !$user
    || (int) $user['is_active'] !== 1
) {
    authClear();

    sessionGuardRespond(
        401,
        [
            'success' => false,
            'status' =>
                'SESSION_REVOKED',
            'reason' =>
                'SESSION_REVOKED',
            'message' =>
                authSessionReasonMessage(
                    'SESSION_REVOKED'
                ),
            'login_url' =>
                authLoginUrl(),
        ]
    );
}

$role = (string) (
    $user['role']
    ?? ''
);

if (
    !in_array(
        $role,
        [
            'ADMIN',
            'SCHEDULER',
        ],
        true
    )
) {
    sessionGuardRespond(
        403,
        [
            'success' => false,
            'status' =>
                'ACCESS_DENIED',
        ]
    );
}

if ($method === 'POST') {
    $input =
        sessionGuardInput();

    if (
        !authCsrfValid(
            $input['csrf_token']
            ?? null
        )
    ) {
        sessionGuardRespond(
            403,
            [
                'success' => false,
                'status' =>
                    'INVALID_CSRF',
                'message' =>
                    'Your session token is invalid. Refresh the page.',
            ]
        );
    }

    if (
        (string) (
            $input['action']
            ?? ''
        )
        !== 'activity'
    ) {
        sessionGuardRespond(
            400,
            [
                'success' => false,
                'status' =>
                    'INVALID_ACTION',
            ]
        );
    }

    if (!authTouchActivity()) {
        $expired =
            authSessionState(false);

        $reason = (string) (
            $expired['reason']
            ?? 'NO_SESSION'
        );

        authClear();

        sessionGuardRespond(
            401,
            [
                'success' => false,
                'status' =>
                    sessionGuardStatus(
                        $reason
                    ),
                'reason' => $reason,
                'message' =>
                    authSessionReasonMessage(
                        $reason
                    ),
                'login_url' =>
                    authLoginUrl(),
            ]
        );
    }

    $state =
        authSessionState(false);

    sessionGuardRespond(
        200,
        [
            'success' => true,
            'status' =>
                'SESSION_ACTIVITY_RECORDED',
            'session' =>
                sessionGuardStatePayload(
                    $state
                ),
        ]
    );
}

/*
 * Passive GET never refreshes auth_last_activity.
 */
sessionGuardRespond(
    200,
    [
        'success' => true,
        'status' =>
            'SESSION_ACTIVE',
        'session' =>
            sessionGuardStatePayload(
                $state
            ),
    ]
);

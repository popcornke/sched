<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/auth.php';
require_once dirname(__DIR__) . '/shared/notifications.php';

authRequire(true, ['ADMIN', 'SCHEDULER']);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');

function notificationReply(int $httpStatus, array $payload): never
{
    http_response_code($httpStatus);

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

$userId = (int) ($_SESSION['auth_user_id'] ?? 0);

if ($userId <= 0) {
    notificationReply(401, [
        'success' => false,
        'status' => 'AUTH_REQUIRED',
        'message' => 'Please log in again.',
    ]);
}

try {
    $pdo = authDb();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower(trim((string) ($_GET['action'] ?? 'list')));

        if ($action !== 'list') {
            notificationReply(400, [
                'success' => false,
                'status' => 'INVALID_ACTION',
                'message' => 'Unsupported notification action.',
            ]);
        }

        $limit = filter_var(
            $_GET['limit'] ?? 30,
            FILTER_VALIDATE_INT,
            ['options' => ['default' => 30, 'min_range' => 1, 'max_range' => 50]]
        );

        $result = notificationList($pdo, $userId, (int) $limit);

        notificationReply(200, [
            'success' => true,
            'status' => 'NOTIFICATIONS_LOADED',
            'notifications' => $result['items'],
            'unread_count' => $result['unread_count'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');

        notificationReply(405, [
            'success' => false,
            'status' => 'METHOD_NOT_ALLOWED',
            'message' => 'Use GET or POST for notifications.',
        ]);
    }

    $request = json_decode((string) file_get_contents('php://input'), true);

    if (!is_array($request)) {
        notificationReply(400, [
            'success' => false,
            'status' => 'INVALID_JSON',
            'message' => 'Request body must be valid JSON.',
        ]);
    }

    if (!authCsrfValid($request['csrf_token'] ?? null)) {
        notificationReply(403, [
            'success' => false,
            'status' => 'INVALID_CSRF',
            'message' => 'Security token is invalid or expired. Refresh the page and try again.',
        ]);
    }

    $action = strtolower(trim((string) ($request['action'] ?? '')));

    if ($action === 'create') {
        $item = notificationCreate(
            $pdo,
            $userId,
            (string) ($request['title'] ?? ''),
            (string) ($request['message'] ?? ''),
            (string) ($request['type'] ?? 'info'),
            isset($request['url']) ? (string) $request['url'] : null
        );

        notificationReply(201, [
            'success' => true,
            'status' => 'NOTIFICATION_CREATED',
            'notification' => $item,
        ]);
    }

    if ($action === 'read') {
        $notificationId = filter_var(
            $request['notification_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($notificationId === false || $notificationId === null) {
            notificationReply(400, [
                'success' => false,
                'status' => 'INVALID_NOTIFICATION_ID',
                'message' => 'A valid notification ID is required.',
            ]);
        }

        notificationMarkRead($pdo, $userId, (int) $notificationId);

        notificationReply(200, [
            'success' => true,
            'status' => 'NOTIFICATION_READ',
            'notification_id' => (int) $notificationId,
        ]);
    }

    if ($action === 'read_all') {
        $updated = notificationMarkAllRead($pdo, $userId);

        notificationReply(200, [
            'success' => true,
            'status' => 'ALL_NOTIFICATIONS_READ',
            'updated' => $updated,
        ]);
    }

    notificationReply(400, [
        'success' => false,
        'status' => 'INVALID_ACTION',
        'message' => 'Unsupported notification action.',
    ]);
} catch (InvalidArgumentException $exception) {
    notificationReply(422, [
        'success' => false,
        'status' => 'INVALID_NOTIFICATION',
        'message' => $exception->getMessage(),
    ]);
} catch (Throwable $exception) {
    error_log('BCP notifications: ' . $exception->getMessage());

    notificationReply(500, [
        'success' => false,
        'status' => 'NOTIFICATION_STORAGE_UNAVAILABLE',
        'message' => 'Notification storage is unavailable. Make sure database migration 015_notifications.sql has been applied.',
    ]);
}

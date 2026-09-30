<?php

declare(strict_types=1);

/**
 * Shared database helpers for in-app notifications.
 * The caller is responsible for authenticating the request/user.
 * Module 08 (Schedule Cloning Tool) is intentionally excluded from automatic
 * application-event notifications until that module is completed.
 */

function notificationNormaliseType(string $type): string
{
    $type = strtolower(trim($type));

    return in_array($type, ['success', 'info', 'warning', 'error'], true)
        ? $type
        : 'info';
}

function notificationSafeUrl(?string $url): ?string
{
    $url = trim((string) $url);

    if ($url === '') {
        return null;
    }

    // Notifications may only point back to this application.
    // Browser callers send pathname + query/hash, never an external URL.
    if (!str_starts_with($url, '/') || str_contains($url, '://')) {
        return null;
    }

    if (strlen($url) > 500) {
        return null;
    }

    return $url;
}

function notificationCreate(
    PDO $pdo,
    int $userId,
    string $title,
    string $message,
    string $type = 'info',
    ?string $url = null
): array {
    $title = trim($title);
    $message = trim($message);

    if ($userId <= 0) {
        throw new InvalidArgumentException('Invalid notification user.');
    }

    if ($title === '' || strlen($title) > 150) {
        throw new InvalidArgumentException('Notification title must contain 1 to 150 characters.');
    }

    if (strlen($message) > 1000) {
        throw new InvalidArgumentException('Notification message must be 1000 characters or fewer.');
    }

    $type = notificationNormaliseType($type);
    $url = notificationSafeUrl($url);

    $stmt = $pdo->prepare(
        'INSERT INTO notifications
            (user_id, title, message, type, url, is_read)
         VALUES
            (:user_id, :title, :message, :type, :url, 0)'
    );

    $stmt->execute([
        'user_id' => $userId,
        'title' => $title,
        'message' => $message,
        'type' => $type,
        'url' => $url,
    ]);

    $notificationId = (int) $pdo->lastInsertId();

    $fetch = $pdo->prepare(
        'SELECT
            notification_id AS id,
            title,
            message,
            type,
            url,
            is_read,
            UNIX_TIMESTAMP(created_at) AS created_unix
         FROM notifications
         WHERE notification_id = :id
           AND user_id = :user_id
         LIMIT 1'
    );

    $fetch->execute([
        'id' => $notificationId,
        'user_id' => $userId,
    ]);

    $row = $fetch->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException('Notification was created but could not be reloaded.');
    }

    return notificationFormatRow($row);
}

function notificationList(PDO $pdo, int $userId, int $limit = 30): array
{
    $limit = max(1, min(50, $limit));

    $stmt = $pdo->prepare(
        'SELECT
            notification_id AS id,
            title,
            message,
            type,
            url,
            is_read,
            UNIX_TIMESTAMP(created_at) AS created_unix
         FROM notifications
         WHERE user_id = :user_id
         ORDER BY notification_id DESC
         LIMIT :limit_value'
    );

    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $items = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $items[] = notificationFormatRow($row);
    }

    $countStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM notifications
         WHERE user_id = :user_id
           AND is_read = 0'
    );
    $countStmt->execute(['user_id' => $userId]);

    return [
        'items' => $items,
        'unread_count' => (int) $countStmt->fetchColumn(),
    ];
}

function notificationMarkRead(PDO $pdo, int $userId, int $notificationId): bool
{
    if ($notificationId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare(
        'UPDATE notifications
         SET
            is_read = 1,
            read_at = COALESCE(read_at, CURRENT_TIMESTAMP)
         WHERE notification_id = :id
           AND user_id = :user_id'
    );

    $stmt->execute([
        'id' => $notificationId,
        'user_id' => $userId,
    ]);

    return $stmt->rowCount() > 0;
}

function notificationMarkAllRead(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare(
        'UPDATE notifications
         SET
            is_read = 1,
            read_at = COALESCE(read_at, CURRENT_TIMESTAMP)
         WHERE user_id = :user_id
           AND is_read = 0'
    );

    $stmt->execute(['user_id' => $userId]);

    return $stmt->rowCount();
}

function notificationFormatRow(array $row): array
{
    $createdUnix = isset($row['created_unix']) ? (int) $row['created_unix'] : time();

    return [
        'id' => (int) ($row['id'] ?? 0),
        'title' => (string) ($row['title'] ?? 'Notification'),
        'message' => (string) ($row['message'] ?? ''),
        'type' => notificationNormaliseType((string) ($row['type'] ?? 'info')),
        'url' => notificationSafeUrl(isset($row['url']) ? (string) $row['url'] : null),
        'read' => (int) ($row['is_read'] ?? 0) === 1,
        'createdAt' => gmdate('c', $createdUnix),
    ];
}

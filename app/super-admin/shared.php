<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/auth.php';
require_once dirname(__DIR__) . '/shared/audit.php';

function saLoginUrl(): string
{
    return authBasePath() . '/app/super-admin/login.php';
}

function saDashboardUrl(): string
{
    return authBasePath() . '/app/super-admin/dashboard.php';
}

function saRequire(bool $api = false): void
{
    authRequire($api, ['SUPER_ADMIN'], false, saLoginUrl());
}

function saEsc(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function saQueryAll(PDO $db, string $sql, array $params = []): array
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function saQueryOne(PDO $db, string $sql, array $params = []): ?array
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function saCount(PDO $db, string $sql, array $params = []): int
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function saTableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $stmt->execute(['table_name' => $table]);
    return (int) $stmt->fetchColumn() > 0;
}

function saColumnExists(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $stmt->execute(['table_name' => $table, 'column_name' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function saFlash(string $type, string $message): void
{
    $_SESSION['sa_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function saTakeFlash(): ?array
{
    $flash = $_SESSION['sa_flash'] ?? null;
    unset($_SESSION['sa_flash']);
    return is_array($flash) ? $flash : null;
}

function saPageStart(string $title, string $active = ''): void
{
    $username = (string) ($_SESSION['auth_username'] ?? 'Super Admin');
    $initial = strtoupper(substr($username, 0, 1));
    $base = authBasePath();
    $flash = saTakeFlash();
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= saEsc($title) ?> | BCP Super Admin</title>
    <link rel="icon" href="<?= saEsc($base) ?>/app/assets/images/BCP_LOGO.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= saEsc($base) ?>/app/assets/css/super-admin.css">
</head>
<body class="sa-body">
    <?php
        $APP_ROOT = '../';
        $ACTIVE_NAV = $active === '' ? '' : 'sa_' . $active;
        require dirname(__DIR__) . '/includes/sidebar.php';
    ?>

    <div class="sa-shell">
        <header class="sa-topbar">
            <button class="sa-menu" type="button" id="hamburgerBtn" aria-label="Toggle navigation"><i class="fa-solid fa-bars"></i></button>
            <div class="sa-topbar-spacer"></div>
            <div class="sa-role"><i class="fa-solid fa-shield-halved"></i> SUPER ADMIN</div>
            <div class="sa-avatar" title="<?= saEsc($username) ?>"><?= saEsc($initial) ?></div>
        </header>
        <main class="sa-content">
            <?php if ($flash): ?>
                <div class="sa-alert sa-alert--<?= saEsc((string) ($flash['type'] ?? 'info')) ?>">
                    <?= saEsc((string) ($flash['message'] ?? '')) ?>
                </div>
            <?php endif; ?>
    <?php
}

function saPageEnd(): void
{
    ?>
        </main>
    </div>
</body>
</html>
    <?php
}

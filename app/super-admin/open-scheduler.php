<?php

declare(strict_types=1);

require_once __DIR__ . '/shared.php';
saRequire();

$type = strtolower(trim((string) ($_GET['type'] ?? '')));
$base = authBasePath();
$targets = [
    'class' => $base . '/app/section/schedule-preview.php',
    'exam' => $base . '/app/modules/04-exam-timetable-generator/exam-timetable-generator.php',
];

if (!isset($targets[$type])) {
    http_response_code(400);
    exit('Invalid scheduler target.');
}

try {
    auditLog(
        authDb(),
        'SCHEDULER_TOOL_OPENED',
        'SCHEDULING_CONTROL',
        'SCHEDULER',
        strtoupper($type),
        'Super Administrator opened the ' . $type . ' schedule generator.'
    );
} catch (Throwable $e) {
    error_log('BCP scheduler access audit: ' . $e->getMessage());
}

header('Location: ' . $targets[$type], true, 303);
exit;

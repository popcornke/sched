<?php

declare(strict_types=1);
require_once __DIR__ . '/shared.php';
saRequire();
// One dashboard for Admin and Super Admin: retain original administrator metrics and design.
header('Location: ' . authBasePath() . '/app/dashboard/dashboard.php', true, 302);
exit;

<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/shared/auth.php';
authRequire(false, ['STAFF']);
$username = (string)($_SESSION['auth_username'] ?? 'Staff');
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Staff · Room Schedules | BCP</title>
  <link rel="stylesheet" href="../assets/css/examsstaff.css">
  <link rel="stylesheet" href="assets/css/room-schedules.css">
</head>
<body>
<div class="workspace">
 <aside class="staff-sidebar no-print" id="staffSidebar" aria-label="Staff navigation">
  <div class="sidebar-brand"><div class="brand-emblem">BCP</div><div><strong>BESTLINK COLLEGE</strong><small>STAFF WORKSPACE</small></div></div>
  <div class="nav-caption">WORKSPACE</div>
  <a class="staff-nav-link" href="exams.php"><span class="nav-icon">▦</span><span>Examination Timetable</span></a>
  <a class="staff-nav-link active" href="room-schedules.php" aria-current="page"><span class="nav-icon">▤</span><span>Room Schedules</span></a>
  <div class="sidebar-bottom"><span class="sidebar-readonly">● VIEW & PRINT ACCESS</span><form action="../../logout.php" method="post" class="sidebar-logout-form"><input type="hidden" name="csrf_token" value="<?= $esc(authCsrf()) ?>"><button type="submit" class="sidebar-logout">↪ &nbsp; Sign out</button></form></div>
 </aside>
 <div class="staff-content">
  <header class="staff-topbar no-print"><div class="topbar-left"><button class="sidebar-toggle" type="button" id="sidebarToggle" aria-label="Toggle staff navigation" aria-controls="staffSidebar" aria-expanded="true">☰</button><div class="breadcrumb">Staff Workspace <span>/</span> <strong>Room Schedules</strong></div></div><div class="topbar-user"><span class="role-chip">STAFF</span><span class="user-avatar" aria-hidden="true"><?= $esc(strtoupper(substr($username,0,1))) ?></span><span class="username"><?= $esc($username) ?></span></div></header>
  <main>
   <div class="page-heading"><div><span class="eyebrow">CLASS SCHEDULES / SAVED RECORDS</span><h1>Room Schedules</h1><p class="intro">View available rooms with saved section schedules in one department. All schedules are read-only.</p></div><div class="page-tag">VIEW ONLY</div></div>
   <section class="panel selectors no-print"><div class="filter-heading"><span class="filter-symbol">⌕</span><div><strong>Find department timetables</strong><p>Select an academic period and department to display only available rooms with scheduled classes.</p></div></div><div class="filter-fields"><label>Academic Period<select id="period"><option value="">Loading periods…</option></select></label><label>Department<select id="department"><option value="">Loading departments…</option></select></label><button class="action" id="load" type="button">View Department Schedules →</button></div></section>
   <p id="status" role="status" aria-live="polite"></p>
   <section class="panel room-report" id="report" hidden>
    <div class="print-brand print-only"><strong>BESTLINK COLLEGE OF THE PHILIPPINES</strong><div>DEPARTMENT ROOM CLASS SCHEDULES · STAFF VIEW</div></div>
    <div class="report-top"><div><span class="kicker no-print">SAVED CLASS SCHEDULES · STAFF VIEW</span><h2 id="heading"></h2><p id="subtitle"></p></div><div class="report-actions no-print"><button class="action secondary" id="print" type="button">▤ &nbsp; Print / Save as PDF</button></div></div>
    <p class="room-summary" id="summary"></p>
    <div class="scroll-hint no-print">Only time ranges with scheduled classes appear. Scroll sideways on smaller screens. Print uses landscape pages.</div>
    <div id="roomReports" class="department-room-list"></div>
    <p class="room-footnote">Only AVAILABLE rooms with ACTIVE face-to-face meetings are displayed. Room ownership determines which rooms appear. Classes from other departments using those rooms are also shown. Blank cells mean the room is free at a time used on another day.</p>
   </section>
  </main>
 </div>
</div>
<script src="assets/js/room-schedules.js" defer></script>
</body></html>

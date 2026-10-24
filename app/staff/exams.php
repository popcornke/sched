<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/shared/auth.php';
authRequire(false, ['STAFF']);
$user = htmlspecialchars((string)($_SESSION['auth_username'] ?? 'Staff'), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Staff · Examination Matrix | BCP</title>
  <link rel="stylesheet" href="../assets/css/examsstaff.css">
</head>

<body>
  <div class="workspace">
    <aside class="staff-sidebar no-print" id="staffSidebar" aria-label="Staff navigation">
      <div class="sidebar-brand">
        <div class="brand-emblem">BCP</div>
        <div><strong>BESTLINK COLLEGE</strong><small>STAFF WORKSPACE</small></div>
      </div>
      <div class="nav-caption">WORKSPACE</div>
      <div class="nav-current"><span class="nav-icon">▦</span><span>Examination Timetable</span></div>
      <div class="sidebar-bottom"><span class="sidebar-readonly">● VIEW & PRINT ACCESS</span><form action="../../logout.php" method="post" class="sidebar-logout-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(authCsrf(), ENT_QUOTES, 'UTF-8') ?>"><button type="submit" class="sidebar-logout">↪ &nbsp; Sign out</button></form></div>
    </aside>
    <div class="staff-content">
      <header class="staff-topbar no-print">
        <div class="topbar-left"><button class="sidebar-toggle" type="button" id="sidebarToggle" aria-label="Toggle staff navigation" aria-controls="staffSidebar" aria-expanded="true">☰</button>
          <div class="breadcrumb">Staff Workspace <span>/</span> <strong>Examination Timetable</strong></div>
        </div>
        <div class="topbar-user"><span class="role-chip">STAFF</span><span class="user-avatar" aria-hidden="true"><?= strtoupper(substr((string)($_SESSION['auth_username'] ?? 'S'), 0, 1)) ?></span><span class="username"><?= $user ?></span></div>
      </header>
      <main>
        <div class="page-heading">
          <div><span class="eyebrow">EXAMINATIONS / SAVED RECORDS</span>
            <h1>Examination Timetable</h1>
            <p class="intro">View saved examination schedules and prepare a clean printable copy. All records are read-only.</p>
          </div>
          <div class="page-tag">VIEW ONLY</div>
        </div>
        <div class="panel selectors no-print">
          <div class="filter-heading"><span class="filter-symbol">⌕</span>
            <div><strong>Find a saved examination</strong>
              <p>Select an academic period and program to view its timetable.</p>
            </div>
          </div>
          <div class="filter-fields"><label>Academic Period<select id="period">
                <option value="">Loading periods…</option>
              </select></label><label>Program<select id="program">
                <option value="">Loading programs…</option>
              </select></label><button class="action" id="load" type="button">View Saved Exams →</button></div>
        </div>
        <p id="status" role="status" aria-live="polite"></p>
        <section class="panel report-panel" id="report" hidden>
          <div class="print-brand print-only"><strong>BESTLINK COLLEGE OF THE PHILIPPINES</strong>
            <div>EXAMINATION TIMETABLE · STAFF VIEW</div>
          </div>
          <div class="report-top">
            <div><span class="kicker no-print">SAVED EXAMINATIONS · STAFF VIEW</span>
              <h2 id="heading"></h2>
              <p id="subtitle"></p>
            </div>
            <div class="report-actions no-print"><button class="action secondary" id="print" type="button">▤ &nbsp; Print / Save as PDF</button></div>
          </div>
          <div class="legend" id="legend"></div>
          <p id="group-note" class="group-note"></p>
          <div class="scroll-hint no-print">↔ Scroll horizontally to explore the full examination timetable on smaller screens.</div>
          <div id="sheets"></div>
          <div class="signatures"><span class="signature">Prepared by</span><span class="signature">Checked by</span><span class="signature">Approved by</span></div>
        </section>
      </main>
    </div>
  </div>
  <script src="assets/js/exams.js" defer></script>
</body>

</html>
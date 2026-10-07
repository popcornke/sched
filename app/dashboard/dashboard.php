<?php

declare(strict_types=1);

/** BCP Scheduling dashboard — read-only; uses existing auth and shared navigation. */
require_once dirname(__DIR__) . '/shared/auth.php';
authRequire();
date_default_timezone_set('Asia/Manila');

function dashEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dashRows(PDO $db, string $sql, array $params = []): array
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function dashCount(PDO $db, string $sql, array $params = []): int
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

$periods = [];
$programs = [];
$programOverview = [];
$selectedPeriod = null;
$selectedProgram = null;
$stats = null;
$loadError = false;
$today = date('Y-m-d');

try {
    $db = authDb();
    $periods = dashRows(
        $db,
        "SELECT academic_period_id, academic_year, semester, period_status
         FROM academic_periods ORDER BY academic_year DESC, semester DESC, academic_period_id DESC"
    );
    $programs = dashRows(
        $db,
        "SELECT program_id, program_code, program_name
         FROM programs WHERE is_active = 1 AND education_level = 'College'
         ORDER BY program_code"
    );

    $requestedPeriod = filter_input(INPUT_GET, 'period', FILTER_VALIDATE_INT);
    $requestedProgram = filter_input(INPUT_GET, 'program', FILTER_VALIDATE_INT);
    $periodId = 0;
    foreach ($periods as $period) {
        if ($requestedPeriod && (int) $period['academic_period_id'] === $requestedPeriod) {
            $selectedPeriod = $period;
            break;
        }
    }
    if ($selectedPeriod === null && $periods !== []) {
        $selectedPeriod = $periods[0];
    }

    foreach ($programs as $program) {
        if ($requestedProgram && (int) $program['program_id'] === $requestedProgram) {
            $selectedProgram = $program;
            break;
        }
    }

    if ($selectedPeriod !== null) {
        $periodId = (int) $selectedPeriod['academic_period_id'];
        $programId = $selectedProgram === null ? 0 : (int) $selectedProgram['program_id'];
        $params = ['period' => $periodId];
        if ($programId > 0) {
            $params['program'] = $programId;
        }
        $onlyProgram = $programId > 0 ? ' AND program_id = :program' : '';
        $sectionsFilter = $programId > 0 ? ' AND s.program_id = :program' : '';
        $batchFilter = $programId > 0 ? ' AND b.program_id = :program' : '';
        $examFilter = $programId > 0 ? ' AND e.program_id = :program' : '';
        $roomFilter = $programId > 0 ? ' AND (program_id = :program OR program_id IS NULL)' : '';

        $stats = [
            'students' => dashCount(
                $db,
                'SELECT COUNT(*) FROM students WHERE academic_period_id = :period' . $onlyProgram,
                $params
            ),
            'sections' => dashCount(
                $db,
                'SELECT COUNT(*) FROM sections WHERE academic_period_id = :period AND is_active = 1' . $onlyProgram,
                $params
            ),
            'offerings' => dashCount(
                $db,
                'SELECT COUNT(*) FROM section_subjects ss JOIN sections s ON s.section_id = ss.section_id
                 WHERE s.academic_period_id = :period AND s.is_active = 1' . $sectionsFilter,
                $params
            ),
            'faculty' => dashCount(
                $db,
                "SELECT COUNT(*) FROM teachers WHERE status = 'ACTIVE'" .
                    ($programId > 0 ? ' AND program_id = :program' : ''),
                $programId > 0 ? ['program' => $programId] : []
            ),
            'rooms' => dashCount(
                $db,
                "SELECT COUNT(*) FROM rooms WHERE status = 'AVAILABLE'" . $roomFilter,
                $programId > 0 ? ['program' => $programId] : []
            ),
            'class_batches' => dashCount(
                $db,
                "SELECT COUNT(*) FROM schedule_batches b WHERE b.academic_period_id = :period AND b.status = 'ACTIVE'" . $batchFilter,
                $params
            ),
            'class_meetings' => dashCount(
                $db,
                "SELECT COUNT(*) FROM schedule_meetings m
                 JOIN schedule_batches b ON b.batch_id = m.batch_id
                 WHERE b.academic_period_id = :period AND b.status = 'ACTIVE'" . $batchFilter,
                $params
            ),
            'exam_batches' => dashCount(
                $db,
                "SELECT COUNT(*) FROM exam_batches e WHERE e.academic_period_id = :period AND e.status = 'ACTIVE'" . $examFilter,
                $params
            ),
            'exam_meetings' => dashCount(
                $db,
                "SELECT COUNT(*) FROM exam_meetings m
                 JOIN exam_batches e ON e.exam_batch_id = m.exam_batch_id
                 WHERE e.academic_period_id = :period AND e.status = 'ACTIVE'" . $examFilter,
                $params
            ),
            'substitutes_today' => dashCount(
                $db,
                "SELECT COUNT(*) FROM substitute_assignments a
                 JOIN schedule_batches b ON b.batch_id = a.class_batch_id
                 WHERE a.academic_period_id = :period AND a.duty_date = :duty_date
                   AND a.status = 'ACTIVE' AND b.status = 'ACTIVE'" . $batchFilter,
                $params + ['duty_date' => $today]
            ),
        ];

        foreach ($programs as $program) {
            $p = (int) $program['program_id'];
            $scope = ['period' => $periodId, 'program' => $p];
            $program['sections_total'] = dashCount(
                $db,
                'SELECT COUNT(*) FROM sections WHERE academic_period_id = :period AND program_id = :program AND is_active = 1',
                $scope
            );
            $program['students_total'] = dashCount(
                $db,
                'SELECT COUNT(*) FROM students WHERE academic_period_id = :period AND program_id = :program',
                $scope
            );
            $program['classes_total'] = dashCount(
                $db,
                "SELECT COUNT(*) FROM schedule_batches WHERE academic_period_id = :period AND program_id = :program AND status = 'ACTIVE'",
                $scope
            );
            $program['exams_total'] = dashCount(
                $db,
                "SELECT COUNT(*) FROM exam_batches WHERE academic_period_id = :period AND program_id = :program AND status = 'ACTIVE'",
                $scope
            );
            $programOverview[] = $program;
        }
    }
} catch (Throwable $error) {
    error_log('BCP dashboard read error: ' . $error->getMessage());
    $loadError = true;
    $stats = null;
    $programOverview = [];
}

$base = '/';
$links = [
    'generate' => $base . 'app/section/schedule-preview.php',
    'faculty' => $base . 'app/modules/02-teacher-schedule-mapping/teacher-schedule-mapping.php',
    'conflicts' => $base . 'app/modules/03-conflict-checker/conflict-checker.php',
    'exams' => $base . 'app/modules/04-exam-timetable-generator/exam-timetable-generator.php',
    'substitutes' => $base . 'app/modules/05-substitute-assignment-tracker/substitute-assignment-tracker.php',
    'rooms' => $base . 'app/modules/07-room-availability-checker/room-availability-checker.php',
    'time' => $base . 'app/modules/09-time-block-customizer/time-block-customizer.php',
    'calendar' => $base . 'app/modules/10-calendar-integration/calendar-integration.php',
];

$activeProgramCode = $selectedProgram === null ? 'All college programs' : (string) $selectedProgram['program_code'];
$username = (string) ($_SESSION['auth_username'] ?? 'Scheduler');
$role = (string) ($_SESSION['auth_role'] ?? 'Admin');
$initial = strtoupper(substr($username, 0, 1));
$ACTIVE_NAV = 'dashboard';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | BCP Scheduling</title>
    <link rel="icon" href="../assets/images/BCP_LOGO.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <!-- Chart.js CDN for Badass Visuals -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bcp-dashboard-page">

    <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

    <div class="main">
        <!-- TOPBAR COMPONENT -->
        <div class="topbar">
            <button class="hamburger" id="hamburgerBtn" aria-label="Toggle sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
            <span class="topbar-spacer"></span>
            <div class="topbar-right">
                <span class="role-badge">
                    <i class="fa-solid fa-user-tie" style="color:#1a3a8c;"></i>
                    <?= dashEsc($role) ?>
                </span>
                <a href="../auth/account.php" class="avatar" title="Account Settings">
                    <?= dashEsc($initial) ?>
                </a>
            </div>
        </div>

        <!-- DASHBOARD CONTENT -->
        <main class="content bcp-dash" id="dashboardContent">
            <header class="bcp-dash__header">
                <div class="bcp-dash__intro">
                    <span class="bcp-dash__eyebrow"><span class="bcp-dash__eyebrow-dot"></span> BCP CLASS SCHEDULING SYSTEM</span>
                    <h1>Scheduling overview<span class="bcp-dash__title-dot">.</span></h1>
                    <p>Welcome, <?= dashEsc($username) ?>. Review live scheduling records and continue from the next task.</p>
                </div>
                <a class="bcp-dash__refresh" href="<?= dashEsc($_SERVER['SCRIPT_NAME'] . ($selectedPeriod === null ? '' : '?period=' . (int) $selectedPeriod['academic_period_id'] . '&program=' . (int) ($selectedProgram['program_id'] ?? 0))) ?>" title="Reload current database counts">
                    <span aria-hidden="true">↻</span> Refresh data
                </a>
            </header>

            <section class="bcp-dash__toolbar" aria-label="Dashboard scope">
                <div class="bcp-dash__toolbar-copy">
                    <span class="bcp-dash__label">CURRENT VIEW</span>
                    <strong>Academic period &amp; program</strong>
                    <span>Figures below refer to the selected scope, not a live conflict audit.</span>
                </div>
                <form method="get" class="bcp-dash__filters" id="dashFilters">
                    <div class="bcp-dash__filter-group">
                        <label for="periodSelect">Academic period</label>
                        <select name="period" id="periodSelect" aria-label="Academic period">
                            <?php foreach ($periods as $p): ?>
                                <option value="<?= (int) $p['academic_period_id'] ?>" <?= $selectedPeriod !== null && (int) $p['academic_period_id'] === (int) $selectedPeriod['academic_period_id'] ? 'selected' : '' ?>>
                                    <?= dashEsc($p['academic_year'] . ' · Sem ' . $p['semester'] . ' · ' . $p['period_status']) ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if ($periods === []): ?><option value="">No period available</option><?php endif; ?>
                        </select>
                    </div>

                    <div class="bcp-dash__filter-group">
                        <label for="programSelect">Program</label>
                        <select name="program" id="programSelect" aria-label="Program">
                            <option value="0" <?= $selectedProgram === null ? 'selected' : '' ?>>All college programs</option>
                            <?php foreach ($programs as $p): ?>
                                <option value="<?= (int) $p['program_id'] ?>" <?= $selectedProgram !== null && (int) $p['program_id'] === (int) $selectedProgram['program_id'] ? 'selected' : '' ?>><?= dashEsc($p['program_code']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <noscript><button type="submit" class="bcp-dash__filter-submit">Apply filters</button></noscript>
                </form>
            </section>

            <?php if ($loadError): ?>
                <div class="bcp-dash__alert" role="alert">Unable to load dashboard data. No database records were changed. Check the PHP error log for “BCP dashboard read error”.</div>
            <?php elseif ($selectedPeriod === null || $stats === null): ?>
                <div class="bcp-dash__alert" role="status">No academic period is configured yet. Add the period through the existing authorized workflow first.</div>
            <?php else: ?>

                <!-- TOP METRICS (THE BADASS NUMBERS) -->
                <section class="bcp-dash__metrics" aria-label="Top Level Statistics">
                    <article class="bcp-dash__metric">
                        <div class="bcp-dash__metric-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                        <span>Total Students</span>
                        <strong><?= number_format($stats['students']) ?></strong>
                        <small>Enrolled in selected period</small>
                    </article>
                    <article class="bcp-dash__metric">
                        <div class="bcp-dash__metric-icon"><i class="fa-solid fa-layer-group"></i></div>
                        <span>Active Sections</span>
                        <strong><?= number_format($stats['sections']) ?></strong>
                        <small><?= number_format($stats['offerings']) ?> section-subject offerings</small>
                    </article>
                    <article class="bcp-dash__metric">
                        <div class="bcp-dash__metric-icon"><i class="fa-solid fa-door-open"></i></div>
                        <span>Available Rooms</span>
                        <strong><?= number_format($stats['rooms']) ?></strong>
                        <small>Ready for class/exam usage</small>
                    </article>
                    <article class="bcp-dash__metric">
                        <div class="bcp-dash__metric-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
                        <span>Active Faculty</span>
                        <strong><?= number_format($stats['faculty']) ?></strong>
                        <small>Eligible for assignments</small>
                    </article>
                </section>

                <!-- VISUAL ANALYTICS (CHARTS) -->
                <section class="bcp-dash__charts" aria-label="Visual Analytics">
                    <article class="bcp-dash__chart-card">
                        <div class="bcp-dash__chart-header">
                            <h3>Student Population by Program</h3>
                        </div>
                        <div class="bcp-dash__chart-container">
                            <canvas id="programChart"></canvas>
                        </div>
                    </article>
                    <article class="bcp-dash__chart-card">
                        <div class="bcp-dash__chart-header">
                            <h3>Resource Allocation Overview</h3>
                        </div>
                        <div class="bcp-dash__chart-container">
                            <canvas id="resourceChart"></canvas>
                        </div>
                    </article>
                </section>

                <section class="bcp-dash__hero" aria-label="Current scheduling status">
                    <div>
                        <div class="bcp-dash__hero-tag"><?= dashEsc($selectedPeriod['period_status']) ?> PERIOD · <?= dashEsc($activeProgramCode) ?></div>
                        <h2><?= $stats['class_batches'] > 0 ? 'Timetable generation active' : 'Ready to build schedules?' ?></h2>
                        <p><?= $stats['class_batches'] > 0
                                ? 'You have ACTIVE class batches. Proceed to Conflict Checker for full validation before finalizing any exam schedules.'
                                : 'No ACTIVE class batch found. Use the generator to automate section, subject, faculty, and room time slots.' ?></p>
                        <div class="bcp-dash__hero-actions">
                            <a class="bcp-dash__primary" href="<?= dashEsc($links['generate']) ?>"><?= $stats['class_batches'] > 0 ? 'Open class timetable' : 'Generate class schedule' ?> <span aria-hidden="true">↗</span></a>
                            <a class="bcp-dash__secondary" href="<?= dashEsc($links['conflicts']) ?>">Run conflict check</a>
                        </div>
                    </div>
                    <div class="bcp-dash__hero-aside">
                        <span class="bcp-dash__hero-small">SAVED WORKFLOW STATUS</span>
                        <div><span class="bcp-dash__step <?= $stats['class_batches'] > 0 ? 'is-done' : '' ?>">01</span><span>Class timetable</span><strong><?= $stats['class_batches'] > 0 ? 'ACTIVE saved' : 'Not saved' ?></strong></div>
                        <div><span class="bcp-dash__step">02</span><span>Conflict checker</span><strong>Run audit ↗</strong></div>
                        <div><span class="bcp-dash__step <?= $stats['exam_batches'] > 0 ? 'is-done' : '' ?>">03</span><span>Exam timetable</span><strong><?= $stats['exam_batches'] > 0 ? 'ACTIVE saved' : 'Not saved' ?></strong></div>
                    </div>
                </section>

                <div class="bcp-dash__columns">
                    <section class="bcp-dash__panel bcp-dash__panel--work" aria-labelledby="dashWorkflow">
                        <div class="bcp-dash__panel-heading">
                            <div><span class="bcp-dash__label">WORKFLOW</span>
                                <h2 id="dashWorkflow">Your next steps</h2>
                            </div><span class="bcp-dash__muted">Current scope</span>
                        </div>
                        <div class="bcp-dash__task">
                            <div class="bcp-dash__task-icon">01</div>
                            <div><strong><?= $stats['class_batches'] ? 'Review the saved class timetable' : 'Generate your class timetable' ?></strong>
                                <p><?= $stats['class_batches'] ? 'View saved meetings and confirm the intended program and academic period.' : 'Check official sections, faculty eligibility, time slots, and classrooms before preview and save.' ?></p>
                            </div>
                            <a href="<?= dashEsc($links['generate']) ?>" aria-label="Open class scheduling">↗</a>
                        </div>
                        <div class="bcp-dash__task">
                            <div class="bcp-dash__task-icon">02</div>
                            <div><strong>Check saved scheduling conflicts</strong>
                                <p>An ACTIVE batch is not proof of zero conflicts. Run the independent saved-schedule checker.</p>
                            </div><a href="<?= dashEsc($links['conflicts']) ?>" aria-label="Open conflict checker">↗</a>
                        </div>
                        <div class="bcp-dash__task">
                            <div class="bcp-dash__task-icon">03</div>
                            <div><strong><?= $stats['exam_batches'] ? 'Review saved examinations' : 'Prepare the exam timetable' ?></strong>
                                <p><?= $stats['exam_batches'] ? 'Check saved exam dates, proctors, and room assignments.' : 'Generate only after the appropriate class timetable is saved and validated.' ?></p>
                            </div><a href="<?= dashEsc($links['exams']) ?>" aria-label="Open exam timetable generator">↗</a>
                        </div>
                        <div class="bcp-dash__task">
                            <div class="bcp-dash__task-icon">04</div>
                            <div><strong>Review today’s substitute coverage</strong>
                                <p><?= number_format($stats['substitutes_today']) ?> ACTIVE substitute assignment(s) for <?= dashEsc(date('M j, Y')) ?> in this view.</p>
                            </div><a href="<?= dashEsc($links['substitutes']) ?>" aria-label="Open substitute tracker">↗</a>
                        </div>
                    </section>
                    <aside class="bcp-dash__aside" aria-label="Resources and shortcuts">
                        <section class="bcp-dash__panel">
                            <div class="bcp-dash__panel-heading">
                                <div><span class="bcp-dash__label">SAVED SCHEDULES</span>
                                    <h2>Database Records</h2>
                                </div>
                            </div>
                            <div class="bcp-dash__resource"><span>Class meetings</span><strong><?= number_format($stats['class_meetings']) ?></strong></div>
                            <div class="bcp-dash__resource"><span>Exam meetings</span><strong><?= number_format($stats['exam_meetings']) ?></strong></div>
                            <p class="bcp-dash__hint">Reflects meetings associated with ACTIVE batches only.</p>
                        </section>
                        <section class="bcp-dash__panel">
                            <div class="bcp-dash__panel-heading">
                                <div><span class="bcp-dash__label">QUICK ACCESS</span>
                                    <h2>Open a tool</h2>
                                </div>
                            </div>
                            <div class="bcp-dash__quick"><a href="<?= dashEsc($links['faculty']) ?>">Faculty mapping <span><i class="fa-solid fa-arrow-right"></i></span></a><a href="<?= dashEsc($links['calendar']) ?>">Academic calendar <span><i class="fa-solid fa-arrow-right"></i></span></a><a href="<?= dashEsc($links['time']) ?>">Time block inventory <span><i class="fa-solid fa-arrow-right"></i></span></a></div>
                            <p class="bcp-dash__hint">Special Class Scheduler and Schedule Cloning remain on hold pending required policies or target-period data.</p>
                        </section>
                    </aside>
                </div>

                <section class="bcp-dash__panel bcp-dash__programs" aria-labelledby="dashPrograms">
                    <div class="bcp-dash__panel-heading">
                        <div><span class="bcp-dash__label">PROGRAM INVENTORY</span>
                            <h2 id="dashPrograms">Saved timetable status by program</h2>
                        </div><span class="bcp-dash__muted">Only ACTIVE saved batches are counted</span>
                    </div>
                    <div class="bcp-dash__table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Program</th>
                                    <th>Active sections</th>
                                    <th>Students</th>
                                    <th>Class timetable</th>
                                    <th>Exam timetable</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($programOverview as $p): ?>
                                    <tr>
                                        <td>
                                            <div class="bcp-dash__program-name"><span class="bcp-dash__program-initial"><?= dashEsc(substr($p['program_code'], 0, 2)) ?></span><span><strong><?= dashEsc($p['program_code']) ?></strong><small><?= dashEsc($p['program_name']) ?></small></span></div>
                                        </td>
                                        <td><?= number_format((int) $p['sections_total']) ?></td>
                                        <td><?= number_format((int) $p['students_total']) ?></td>
                                        <td><span class="bcp-dash__pill <?= (int) $p['classes_total'] > 0 ? 'is-active' : '' ?>"><?= (int) $p['classes_total'] > 0 ? 'ACTIVE · ' . (int) $p['classes_total'] : 'Not saved' ?></span></td>
                                        <td><span class="bcp-dash__pill <?= (int) $p['exams_total'] > 0 ? 'is-active' : '' ?>"><?= (int) $p['exams_total'] > 0 ? 'ACTIVE · ' . (int) $p['exams_total'] : 'Not saved' ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($programOverview === []): ?><tr>
                                        <td colspan="5" class="bcp-dash__empty">No active college programs found.</td>
                                    </tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <!-- FOOTER COMPONENT -->
            <footer class="bcp-dash__footer">Read-only overview · Data loaded from MySQL on page refresh · <?= dashEsc(date('M j, Y · g:i A')) ?> · Asia/Manila</footer>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerBtn = document.getElementById('hamburgerBtn');
            const sidebar = document.getElementById('sidebar');
            if (hamburgerBtn && sidebar) {
                hamburgerBtn.addEventListener('click', () => {
                    sidebar.classList.toggle('collapsed');
                });
            }

            /* --- PREMIUM CUSTOM SELECT DROPDOWN LOGIC --- */
            function upgradeSelects() {
                document.querySelectorAll('.bcp-dash__filters select').forEach(selectElem => {
                    if (selectElem.parentElement.classList.contains('bcp-custom-select-initialized')) return;

                    selectElem.style.display = 'none';

                    const wrapper = document.createElement("div");
                    wrapper.className = "bcp-custom-select-wrapper bcp-custom-select-initialized";
                    selectElem.parentNode.insertBefore(wrapper, selectElem);
                    wrapper.appendChild(selectElem);

                    const trigger = document.createElement("div");
                    trigger.className = "bcp-custom-select-trigger";

                    const triggerText = document.createElement("span");
                    triggerText.className = "bcp-custom-select-text";

                    const arrow = document.createElement("i");
                    arrow.className = "fa-solid fa-chevron-down bcp-custom-select-arrow";
                    trigger.append(triggerText, arrow);

                    const optionsList = document.createElement("div");
                    optionsList.className = "bcp-custom-select-options";
                    wrapper.append(trigger, optionsList);

                    function sync() {
                        optionsList.innerHTML = '';
                        wrapper.classList.toggle('is-disabled', selectElem.disabled);

                        if (selectElem.options.length === 0) {
                            triggerText.textContent = "Loading...";
                            return;
                        }

                        let selectedLabel = "";
                        Array.from(selectElem.options).forEach(opt => {
                            if (opt.selected) selectedLabel = opt.text;
                            const item = document.createElement("div");
                            item.className = "bcp-custom-select-option";
                            item.textContent = opt.text;
                            if (opt.selected) item.classList.add('is-selected');

                            item.addEventListener('click', (e) => {
                                e.stopPropagation();
                                if (selectElem.value !== opt.value) {
                                    selectElem.value = opt.value;

                                    // Trigger form submission manually since it's hidden
                                    if (selectElem.form) {
                                        selectElem.form.requestSubmit();
                                    }
                                }
                                closeAllCustomSelects();
                            });
                            optionsList.appendChild(item);
                        });
                        triggerText.textContent = selectedLabel || "Select an option";
                    }

                    const observer = new MutationObserver(sync);
                    observer.observe(selectElem, {
                        childList: true,
                        attributes: true,
                        attributeFilter: ['disabled']
                    });
                    selectElem.addEventListener('change', sync);

                    trigger.addEventListener('click', (e) => {
                        if (selectElem.disabled) return;
                        e.stopPropagation();
                        const isOpen = optionsList.classList.contains('is-open');
                        closeAllCustomSelects();
                        if (!isOpen) {
                            optionsList.classList.add('is-open');
                            trigger.classList.add('is-active');
                            const selected = optionsList.querySelector('.is-selected');
                            if (selected) optionsList.scrollTop = selected.offsetTop - 10;
                        }
                    });
                    sync();
                });
            }

            function closeAllCustomSelects() {
                document.querySelectorAll('.bcp-custom-select-options').forEach(elem => elem.classList.remove('is-open'));
                document.querySelectorAll('.bcp-custom-select-trigger').forEach(elem => elem.classList.remove('is-active'));
            }
            document.addEventListener('click', closeAllCustomSelects);

            // Initialize Custom Selects
            upgradeSelects();
            /* ------------------------------------------- */

            <?php if (!$loadError && $stats !== null && !empty($programOverview)): ?>
                // Parse PHP Data securely for Charts
                const programLabels = <?= json_encode(array_column($programOverview, 'program_code')) ?>;
                const programData = <?= json_encode(array_column($programOverview, 'students_total')) ?>;

                const statsLabels = ['Sections', 'Rooms', 'Faculty'];
                const statsData = [<?= $stats['sections'] ?>, <?= $stats['rooms'] ?>, <?= $stats['faculty'] ?>];

                // 1. Doughnut Chart: Student Population by Program
                const ctxProgram = document.getElementById('programChart').getContext('2d');
                new Chart(ctxProgram, {
                    type: 'doughnut',
                    data: {
                        labels: programLabels,
                        datasets: [{
                            data: programData,
                            backgroundColor: [
                                '#1a3a8c', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6'
                            ],
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    boxWidth: 12,
                                    font: {
                                        family: 'Plus Jakarta Sans',
                                        size: 12
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: '#102d63',
                                titleFont: {
                                    family: 'Plus Jakarta Sans'
                                },
                                bodyFont: {
                                    family: 'Plus Jakarta Sans'
                                },
                                padding: 12,
                                cornerRadius: 8
                            }
                        },
                        cutout: '65%'
                    }
                });

                // 2. Bar Chart: Resource Allocation
                const ctxResource = document.getElementById('resourceChart').getContext('2d');
                new Chart(ctxResource, {
                    type: 'bar',
                    data: {
                        labels: statsLabels,
                        datasets: [{
                            label: 'Total Count',
                            data: statsData,
                            backgroundColor: ['#3b82f6', '#10b981', '#f59e0b'],
                            borderRadius: 6,
                            barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: '#102d63',
                                titleFont: {
                                    family: 'Plus Jakarta Sans'
                                },
                                bodyFont: {
                                    family: 'Plus Jakarta Sans'
                                },
                                padding: 12,
                                cornerRadius: 8
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    borderDash: [4, 4],
                                    drawBorder: false
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            <?php endif; ?>
        });
    </script>
</body>

</html>
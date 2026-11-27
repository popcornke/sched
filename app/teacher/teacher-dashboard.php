<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/auth.php';
authRequire(false, ['TEACHER']);

// Teacher accounts that still use the assigned default password must change it
// before schedule/person-level data is shown.
try {
    $tpAuthDb = authDb();
    $tpPasswordState =$tpAuthDb->prepare(
        "SELECT must_change_password
           FROM teacher_accounts
          WHERE user_id = :user_id
          LIMIT 1"
    );
    $tpPasswordState->execute(['user_id' => (int)($_SESSION['auth_user_id'] ?? 0)]);
    $tpPasswordRow =$tpPasswordState->fetch(PDO::FETCH_ASSOC);
    if (!$tpPasswordRow) {
        http_response_code(403);
        exit('Teacher account mapping is missing.');
    }
    if ((int)$tpPasswordRow['must_change_password'] === 1) {$_SESSION['teacher_must_change_password'] = 1;
        header('Location: teacher-change-password.php', true, 303);
        exit;
    }
    $_SESSION['teacher_must_change_password'] = 0; } catch (Throwable$e) {
    error_log('BCP Teacher dashboard password guard: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to verify teacher account security.');
}

function tpEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Kukunin ang unang letra ng First Name at unang letra ng Last Name
$teacherName = trim((string)($_SESSION['teacher_name'] ?? 'Teacher'));
$nameParts = explode(' ', preg_replace('/\s+/', ' ',$teacherName));
if (count($nameParts) >= 2) {$initial = strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1));
} else {
    $initial = strtoupper(substr($teacherName, 0, 2));
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Schedule | BCP Teacher Portal</title>
    <link rel="icon" href="../assets/images/BCP_LOGO.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/teacher-dashboard.css?v=20261127-printmatrix1">
</head>
<body class="tp-page">
<header class="tp-topbar">
    <a class="tp-brand" href="./teacher-dashboard.php" aria-label="BCP Teacher Portal home">
        <img src="../assets/images/BCP_LOGO.png" alt="BCP Logo">
        <span><strong>BCP</strong><small>Teacher Portal</small></span>
    </a>
    <nav class="tp-topnav" aria-label="Teacher portal shortcuts">
        <button type="button" data-scroll="todaySection" class="tp-tip" data-tip="Today’s schedule · Shortcut: 1" aria-keyshortcuts="1"><i class="fa-regular fa-calendar-check"></i><span>Today</span></button>
        <button type="button" data-scroll="weeklySection" class="tp-tip" data-tip="Weekly timetable · Shortcut: 2" aria-keyshortcuts="2"><i class="fa-solid fa-calendar-week"></i><span>Weekly</span></button>
        <button type="button" id="tpPrint" class="tp-tip" data-tip="Print schedule · Shortcut: P" aria-keyshortcuts="P"><i class="fa-solid fa-print"></i><span>Print</span></button>
    </nav>
    <div class="tp-export" id="tpExportWrap">
        <button type="button" class="tp-export-trigger tp-tip" id="tpExport" data-tip="Export PDF or CSV · Shortcut: E" aria-keyshortcuts="E" aria-haspopup="true" aria-expanded="false" aria-controls="tpExportMenu">
            <i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i><span>Export</span><i class="fa-solid fa-chevron-down tp-export-chevron" aria-hidden="true"></i>
        </button>
        <div class="tp-export-menu" id="tpExportMenu" hidden>
            <p class="tp-export-eyebrow">FACULTY SCHEDULE</p>
            <button type="button" id="tpExportPdf" title="Export PDF using Save as PDF"><i class="fa-regular fa-file-pdf" aria-hidden="true"></i><span><strong>Export as PDF</strong><small>Official print-ready schedule</small></span></button>
            <button type="button" id="tpExportCsv" title="Download weekly schedule as CSV"><i class="fa-solid fa-file-csv" aria-hidden="true"></i><span><strong>Export as CSV</strong><small>Weekly classes · spreadsheet ready</small></span></button>
            <p class="tp-export-footnote">Select Save as PDF in the print window.</p>
        </div>
    </div>
    <div class="tp-account">
        <span class="tp-role">TEACHER</span>
        <a class="tp-password-link tp-tip" href="./teacher-change-password.php" data-tip="Change account password"><i class="fa-solid fa-key"></i><span>Password</span></a>
        <span class="tp-avatar" aria-label="Logged in as <?= tpEsc($teacherName) ?>"><?= tpEsc($initial) ?></span>
        <form method="POST" action="./teacher-logout.php">
            <input type="hidden" name="csrf_token" value="<?= tpEsc(function_exists('authCsrf') ? authCsrf() : '') ?>">
            <button type="submit" class="tp-logout tp-tip tp-tip-end" data-tip="Sign out of Teacher Portal"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></button>
        </form>
    </div>
</header>

<main class="tp-main" id="tpMain" aria-busy="true">
    <!-- HIDDEN PRINT SECTION (Lalabas lang sa papel bilang pormal na table) -->
    <div class="tp-print-only" aria-hidden="true">
        <div class="tp-print-header">
            <img src="../assets/images/BCP_LOGO.png" alt="BCP Logo">
            <div>
                <h1>BESTLINK COLLEGE OF THE PHILIPPINES</h1>
                <h2>OFFICIAL FACULTY SCHEDULE</h2>
                <p id="tpPrintIdentity">Loading name...</p>
                <p id="tpPrintPeriod">Loading period...</p>
            </div>
        </div>
        <div id="tpPrintTimetables"></div>
        <div class="tp-print-signatures">
            <div>
                <p>Prepared by:</p>
                <div class="sig-line"></div>
                <strong>Department Scheduler</strong>
            </div>
            <div>
                <p>Received by:</p>
                <div class="sig-line"></div>
                <strong id="tpPrintSignName">Faculty Member</strong>
            </div>
        </div>
    </div>

    <section class="tp-hero">
        <div>
            <p class="tp-eyebrow"><span></span> BCP CLASS SCHEDULING SYSTEM · READ ONLY</p>
            <h1 id="tpGreeting">My teaching schedule<span>.</span></h1>
            <p id="tpIdentity">Loading your faculty profile and current timetable…</p>
        </div>
        <label class="tp-period">Academic period
            <select id="tpPeriod" disabled><option>Loading…</option></select>
        </label>
    </section>

    <div class="tp-status" id="tpStatus" role="status" aria-live="polite">
        <i class="fa-solid fa-circle-notch fa-spin"></i><span>Loading teacher portal…</span>
    </div>

    <section class="tp-metrics" aria-label="Teacher schedule summary">
        <article><span class="tp-metric-icon"><i class="fa-regular fa-calendar-check"></i></span><div><small>Classes today</small><strong id="tpTodayCount">—</strong><p id="tpTodayLabel">Current weekday</p></div></article>
        <article><span class="tp-metric-icon"><i class="fa-solid fa-clock"></i></span><div><small>Weekly teaching load</small><strong id="tpWeeklyHours">—</strong><p id="tpWeeklyMeetings">Saved meetings</p></div></article>
        <article><span class="tp-metric-icon"><i class="fa-solid fa-file-signature"></i></span><div><small>Exam duties</small><strong id="tpExamCount">—</strong><p>Active exam assignments</p></div></article>
        <article><span class="tp-metric-icon"><i class="fa-solid fa-user-group"></i></span><div><small>Substitute duties</small><strong id="tpSubCount">—</strong><p>Active substitute coverage</p></div></article>
    </section>

    <section class="tp-next" aria-labelledby="tpNextTitle">
        <div class="tp-next-copy">
            <p class="tp-section-kicker">NEXT CLASS</p>
            <h2 id="tpNextTitle">Checking your next class…</h2>
            <p id="tpNextMeta">Your next saved meeting for today will appear here.</p>
        </div>
        <div class="tp-next-time"><small id="tpNextMode">—</small><strong id="tpNextTime">—</strong><span id="tpNextRoom">—</span></div>
    </section>

    <section class="tp-panel" id="todaySection">
        <div class="tp-panel-head"><div><p class="tp-section-kicker">TODAY</p><h2>Today’s schedule</h2></div><span class="tp-hint">Shortcut: <kbd>1</kbd></span></div>
        <div id="tpToday" class="tp-agenda"></div>
    </section>

    <section class="tp-panel" id="weeklySection">
        <div class="tp-panel-head"><div><p class="tp-section-kicker">WEEK AT A GLANCE</p><h2>Weekly timetable</h2></div><span class="tp-hint">Shortcut: <kbd>2</kbd></span></div>
        <div id="tpWeekly" class="tp-week"></div>
    </section>

    <div class="tp-grid-2">
        <section class="tp-panel">
            <div class="tp-panel-head"><div><p class="tp-section-kicker">ASSESSMENT</p><h2>Exam duties</h2></div><span id="tpExamBadge" class="tp-count">0</span></div>
            <div id="tpExams" class="tp-list"></div>
        </section>
        <section class="tp-panel">
            <div class="tp-panel-head"><div><p class="tp-section-kicker">COVERAGE</p><h2>Substitute duties</h2></div><span id="tpSubBadge" class="tp-count">0</span></div>
            <div id="tpSubs" class="tp-list"></div>
        </section>
    </div>

    <div class="tp-grid-2">
        <section class="tp-panel">
            <div class="tp-panel-head"><div><p class="tp-section-kicker">ADDITIONAL CLASSES</p><h2>Special classes</h2></div><span id="tpSpecialBadge" class="tp-count">0</span></div>
            <div id="tpSpecial" class="tp-list"></div>
        </section>
        <section class="tp-panel">
            <div class="tp-panel-head"><div><p class="tp-section-kicker">FACULTY RECORD</p><h2>Availability</h2></div><span class="tp-hint">Read only</span></div>
            <div id="tpAvailability" class="tp-availability"></div>
        </section>
    </div>

    <footer class="tp-footer">
        <span><i class="fa-solid fa-lock"></i> Read-only teacher portal</span>
        <span>Schedule data comes from ACTIVE saved timetable records.</span>
        <span>Keyboard: <kbd>E</kbd> export · <kbd>R</kbd> refresh · <kbd>P</kbd> print · <kbd>1</kbd> today · <kbd>2</kbd> weekly</span>
    </footer>
</main>

<script>
(() => {
    'use strict';
    const $ = id => document.getElementById(id);
    const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    let data = null;
    let busy = false;

    const esc = value => String(value ?? '');
    const fmtTime = value => {
        if (!value) return '—';
        const [h,m] = String(value).split(':').map(Number);
        return `${h % 12 || 12}:${String(m).padStart(2,'0')} ${h >= 12 ? 'PM' : 'AM'}`;
    };
    const fmtDate = value => value ? new Date(value + 'T12:00:00').toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'}) : '—';
    const node = (tag, text, cls='') => {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined) n.textContent = esc(text);
        return n;
    };
    function setStatus(message, state='info') {
        const root = $('tpStatus');
        root.dataset.state = state;
        root.replaceChildren();
        const icon = node('i');
        icon.className = state === 'loading' ? 'fa-solid fa-circle-notch fa-spin' : state === 'error' ? 'fa-solid fa-triangle-exclamation' : 'fa-solid fa-circle-check';
        root.append(icon, node('span', message));
    }
    function empty(text) {
        const p = node('p', text, 'tp-empty');
        return p;
    }
    function meetingCard(m) {
        const card = node('article', undefined, 'tp-meeting');
        const time = node('div', undefined, 'tp-meeting-time');
        time.append(node('strong', fmtTime(m.start_time)), node('span', fmtTime(m.end_time)));
        const body = node('div', undefined, 'tp-meeting-body');
        body.append(node('strong', `${m.subject_code} · ${m.subject_title}`), node('span', `${m.section_code} · Year ${m.year_level}`));
        const meta = node('div', undefined, 'tp-meeting-meta');
        const place = m.delivery_mode === 'ONLINE' ? 'Online · No room' : `${m.room_name || 'Room TBA'}${m.building ? ' · '+m.building : ''}`;
        meta.append(node('span', m.delivery_mode), node('span', place));
        body.append(meta);
        card.append(time, body);
        return card;
    }
    function renderToday() {
        const root = $('tpToday'); root.replaceChildren();
        if (!data.today_schedule.length) { root.append(empty('No saved class meetings for today.')); return; }
        data.today_schedule.forEach(m => root.append(meetingCard(m)));
    }
    function renderWeekly() {
        const root = $('tpWeekly'); root.replaceChildren();
        days.forEach(day => {
            const col = node('section', undefined, 'tp-day');
            const rows = data.weekly_schedule.filter(m => m.day_of_week === day);
            const head = node('div', undefined, 'tp-day-head');
            head.append(node('strong', day), node('span', `${rows.length} class${rows.length===1?'':'es'}`));
            col.append(head);
            if (!rows.length) col.append(empty('No class'));
            rows.forEach(m => {
                const item = node('div', undefined, 'tp-day-item');
                item.append(node('strong', `${fmtTime(m.start_time)} – ${fmtTime(m.end_time)}`), node('span', m.subject_code), node('small', `${m.section_code} · ${m.delivery_mode}${m.room_name ? ' · '+m.room_name : ''}`));
                col.append(item);
            });
            root.append(col);
        });
    }
    function renderDutyList(id, rows, renderer) {
        const root = $(id); root.replaceChildren();
        if (!rows.length) { root.append(empty('No active records for this academic period.')); return; }
        rows.forEach(row => root.append(renderer(row)));
    }
    function dutyCard(title, line1, line2, badge) {
        const card = node('article', undefined, 'tp-duty');
        const body = node('div');
        body.append(node('strong', title), node('span', line1), node('small', line2));
        card.append(body, node('span', badge, 'tp-duty-badge'));
        return card;
    }
    function renderAvailability() {
        const root = $('tpAvailability'); root.replaceChildren();
        if (!data.availability.length) { root.append(empty('No availability record for this academic period.')); return; }
        days.forEach(day => {
            const rows = data.availability.filter(a => a.day_of_week === day);
            if (!rows.length) return;
            const box = node('div', undefined, 'tp-avail-row');
            box.append(node('strong', day));
            const ranges = node('div');
            rows.forEach(a => ranges.append(node('span', `${fmtTime(a.start_time)} – ${fmtTime(a.end_time)} · ${a.availability_status}`, a.availability_status === 'AVAILABLE' ? 'is-ok' : 'is-no')));
            box.append(ranges); root.append(box);
        });
    }
    function renderNext() {
        const n = data.next_class;
        if (!n) {
            $('tpNextTitle').textContent = 'No remaining class today';$('tpNextMeta').textContent = data.today_schedule.length ? 'Your saved classes for today are finished.' : 'No saved class meetings are assigned today.';
            $('tpNextMode').textContent = 'DONE'; $('tpNextTime').textContent = '—';$('tpNextRoom').textContent = '—';
            return;
        }
        $('tpNextTitle').textContent = `${n.subject_code} · ${n.subject_title}`;
        $('tpNextMeta').textContent = `${n.section_code} · Year ${n.year_level}`;
        $('tpNextMode').textContent = n.delivery_mode;
        $('tpNextTime').textContent = `${fmtTime(n.start_time)} – ${fmtTime(n.end_time)}`;
        $('tpNextRoom').textContent = n.delivery_mode === 'ONLINE' ? 'Online · No room' : (n.room_name || 'Room TBA');
    }
    function renderAll() {
        const t = data.teacher, s = data.summary;
        
        // --- PRINT HEADER DATA ---
        $('tpPrintIdentity').textContent = `${t.teacher_name} · Employee No: ${t.employee_no}`;
        $('tpPrintPeriod').textContent = `${data.period.academic_year} · Semester ${data.period.semester}`;
        $('tpPrintSignName').textContent = t.teacher_name; 
        
        // Match the Staff direct-range matrix: days vertically, actual time ranges horizontally.
        const root = $('tpPrintTimetables');
        root.replaceChildren();
        const minutes = value => {
            const [h, m] = String(value ?? '').split(':').map(Number);
            return h * 60 + m;
        };
        const meetings = data.weekly_schedule.filter(m => days.includes(m.day_of_week) && minutes(m.end_time) > minutes(m.start_time));
        const rangeMap = new Map();
        meetings.forEach(m => {
            const start = minutes(m.start_time), end = minutes(m.end_time);
            rangeMap.set(`${start}-${end}`, {start, end, label: `${fmtTime(m.start_time)} – ${fmtTime(m.end_time)}`});
        });
        const ranges = [...rangeMap.values()].sort((a,b) => a.start-b.start || a.end-b.end);
        if (!ranges.length) root.append(node('p', 'No schedule available for this period.'));
        // Limit columns per matrix to keep dense schedules readable on A4 landscape.
        for (let offset = 0; offset < ranges.length; offset += 8) {
            const group = ranges.slice(offset, offset + 8);
            const table = node('table', undefined, 'tp-print-table');
            table.setAttribute('aria-label', 'Weekly faculty timetable');
            const cols = node('colgroup');
            cols.append(node('col', undefined, 'tp-print-day-width'));
            group.forEach(() => cols.append(node('col')));
            table.append(cols);
            const thead = node('thead'), head = node('tr');
            const dayHeading = node('th', 'DAY / TIME'); dayHeading.scope = 'col'; head.append(dayHeading);
            group.forEach(range => { const th = node('th', range.label); th.scope = 'col'; head.append(th); });
            thead.append(head); table.append(thead);
            const tbody = node('tbody');
            days.forEach(day => {
                const tr = node('tr'), label = node('th', day); label.scope = 'row'; tr.append(label);
                group.forEach(range => {
                    const rows = meetings.filter(m => m.day_of_week === day && minutes(m.start_time) === range.start && minutes(m.end_time) === range.end);
                    const td = node('td', undefined, rows.length ? 'tp-print-meeting' : 'tp-print-empty');
                    if (!rows.length) td.textContent = '—';
                    rows.forEach(m => {
                        const block = node('div', undefined, 'tp-print-class');
                        block.append(node('strong', m.subject_code), node('span', `Section ${m.section_code}`), node('span', m.delivery_mode === 'ONLINE' ? 'ONLINE · No room' : `${m.room_name || 'Room TBA'} · ${m.delivery_mode}`));
                        td.append(block);
                    });
                    tr.append(td);
                });
                tbody.append(tr);
            });
            table.append(tbody); root.append(table);
        }

        // --- REGULAR UI DATA ---
        $('tpGreeting').innerHTML = `Welcome, ${esc(t.teacher_name.split(' ')[0])}<span>.</span>`;
        $('tpIdentity').textContent = `${t.teacher_name} · ${t.employee_no} · ${t.program_code} — ${t.program_name}`;
        $('tpTodayCount').textContent = s.today_classes;
        $('tpTodayLabel').textContent = `${data.today.day_name} · ${fmtDate(data.today.date)}`;
        $('tpWeeklyHours').textContent = `${s.weekly_hours} hrs`;
        $('tpWeeklyMeetings').textContent = `${s.weekly_meetings} saved meeting(s)`;
        $('tpExamCount').textContent = s.exam_duties;
        $('tpSubCount').textContent = s.substitute_duties;
        $('tpExamBadge').textContent = s.exam_duties;
        $('tpSubBadge').textContent = s.substitute_duties;
        $('tpSpecialBadge').textContent = s.special_classes;
        
        renderNext(); renderToday(); renderWeekly(); renderAvailability();
        renderDutyList('tpExams', data.exam_duties, e => dutyCard(`${e.subject_code} · ${e.section_code}`, `${fmtDate(e.exam_date)} · ${fmtTime(e.start_time)} – ${fmtTime(e.end_time)}`, `${e.subject_title} · ${e.room_name}`, `DAY ${e.exam_day}`));
        renderDutyList('tpSubs', data.substitute_duties, s => dutyCard(`${s.subject_code} · ${s.section_code}`, `${fmtDate(s.duty_date)} · ${fmtTime(s.start_time)} – ${fmtTime(s.end_time)}`, `For ${s.original_teacher_name} · ${s.reason}`, 'SUBSTITUTE'));
        renderDutyList('tpSpecial', data.special_classes, s => dutyCard(`${s.subject_code} · ${s.subject_title}`, `${fmtDate(s.meeting_date)} · ${fmtTime(s.start_time)} – ${fmtTime(s.end_time)}`, `${s.class_type} · ${s.delivery_mode}${s.room_name ? ' · '+s.room_name : ''}`, 'SPECIAL'));
    }
    function populatePeriods(periods, selected) {
        const select = $('tpPeriod'); select.replaceChildren();
        periods.forEach(p => select.add(new Option(`${p.academic_year} · Semester ${p.semester} · ${p.period_status}`, String(p.academic_period_id))));
        select.value = String(selected); select.disabled = periods.length === 0;
    }
    async function load(periodId='') {
        if (busy) return;
        busy = true; $('tpMain').setAttribute('aria-busy','true');$('tpPeriod').disabled = true; setStatus('Loading your current teacher schedule…','loading');
        try {
            const q = periodId ? `?period_id=${encodeURIComponent(periodId)}` : '';
            const response = await fetch(`./teacher-api.php${q}`, {credentials:'same-origin',cache:'no-store'});
            let json;
            try { json = await response.json(); } catch { throw new Error('Teacher API returned invalid JSON. Check the PHP error log.'); }
            if (!response.ok || json.success !== true) throw new Error(json.message || json.status || 'Unable to load teacher portal.');
            data = json;
            populatePeriods(data.periods, data.period.academic_period_id);
            renderAll();
            setStatus(`${data.period.academic_year} · Semester ${data.period.semester} loaded. All information is read only.`, 'success');
        } catch (error) {
            setStatus(error.message, 'error');
        } finally {
            busy = false; $('tpMain').setAttribute('aria-busy','false');$('tpPeriod').disabled = false;
        }
    }
    function closeExport() {
        $('tpExportMenu').hidden = true;
        $('tpExport').setAttribute('aria-expanded','false');
    }
    function toggleExport() {
        const willOpen = $('tpExportMenu').hidden;
        $('tpExportMenu').hidden = !willOpen;
        $('tpExport').setAttribute('aria-expanded', String(willOpen));
    }
    function printSchedule() {
        closeExport();
        if (!data || busy) { setStatus('Load a schedule before exporting a PDF.', 'error'); return; }
        window.print(); // Browser print dialog provides Save as PDF (no server upload).
    }
    function csvCell(value) {
        // Prevent spreadsheet formula injection on opening CSVs.
        let valueText = String(value ?? '');
        if (/^[\s]*[=+@\-\t\r]/.test(valueText)) valueText = "'" + valueText;
        return '"' + valueText.replace(/"/g, '""') + '"';
    }
    function exportCsv() {
        closeExport();
        if (!data || busy) { setStatus('Load a schedule before exporting CSV.', 'error'); return; }
        const header = ['Academic Year','Semester','Employee Number','Teacher','Program','Day','Start Time','End Time','Subject Code','Subject Title','Section','Year Level','Room','Building','Delivery Mode'];
        const rows = (data.weekly_schedule || []).map(m => [
            data.period.academic_year, data.period.semester, data.teacher.employee_no,
            data.teacher.teacher_name, data.teacher.program_code, m.day_of_week,
            m.start_time, m.end_time, m.subject_code, m.subject_title,
            m.section_code, m.year_level, m.room_name || '', m.building || '', m.delivery_mode
        ]);
        const contents = '\uFEFF' + [header, ...rows].map(row => row.map(csvCell).join(',')).join('\r\n') + '\r\n';
        const blob = new Blob([contents], {type:'text/csv;charset=utf-8;'});
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        const safe = v => String(v ?? 'unknown').replace(/[^a-zA-Z0-9_-]+/g,'_').slice(0,65);
        link.href = url;
        link.download = `BCP_Faculty_Schedule_${safe(data.teacher.employee_no)}_${safe(data.period.academic_year)}_S${safe(data.period.semester)}.csv`;
        document.body.appendChild(link); link.click(); link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
        setStatus(`CSV exported: ${rows.length} weekly class meeting(s).`, 'success');
    }
    $('tpPeriod').addEventListener('change', () => {closeExport(); load($('tpPeriod').value);});
    $('tpPrint').addEventListener('click', printSchedule);
    $('tpExport').addEventListener('click', toggleExport);
    $('tpExportPdf').addEventListener('click', printSchedule);
    $('tpExportCsv').addEventListener('click', exportCsv);
    document.addEventListener('click', event => { if (!$('tpExportWrap').contains(event.target)) closeExport(); });
    document.querySelectorAll('[data-scroll]').forEach(btn => btn.addEventListener('click', () => $(btn.dataset.scroll)?.scrollIntoView({behavior:'smooth',block:'start'})));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') { closeExport(); return; }
        if (['INPUT','TEXTAREA','SELECT'].includes(document.activeElement?.tagName) || document.activeElement?.isContentEditable || event.ctrlKey || event.altKey || event.metaKey || event.repeat) return;
        const key = event.key.toLowerCase();
        if (key === 'r') { event.preventDefault(); load($('tpPeriod').value); }
        if (key === 'p') { event.preventDefault(); printSchedule(); }
        if (key === 'e') { event.preventDefault(); toggleExport(); }
        if (key === '1') { event.preventDefault(); $('todaySection').scrollIntoView({behavior:'smooth'}); }
        if (key === '2') { event.preventDefault(); $('weeklySection').scrollIntoView({behavior:'smooth'}); }
    });
    load();
})();
</script>
</body>
</html>
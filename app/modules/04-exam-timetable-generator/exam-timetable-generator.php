<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/auth.php';

authRequire();

/** Module 4: DEMO examination preview, guarded save, stored timetable viewing and printing. */

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Safe escape helper function
function dashEsc(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$APP_ROOT = '../../';
$ACTIVE_NAV = 'exam_generator';

// Safe variables for Topbar
$role = (string) ($_SESSION['auth_role'] ?? $_SESSION['role'] ?? 'Admin');
$username = trim((string) ($_SESSION['auth_username'] ?? $_SESSION['first_name'] ?? 'Admin'));
$initial = strtoupper(substr($username !== '' ? $username : 'A', 0, 1));
$dashboardDate = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Exam Timetable Generator | BCP</title>
  <link rel="icon" href="../../assets/images/BCP_LOGO.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <!-- CACHE BUSTER ADDED -->
  <link rel="stylesheet" href="../../assets/css/exam-timetable-generator.css?v=<?= time() ?>">
</head>

<body class="bcp-exam-page">

  <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

  <div class="main">
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
        <a href="../../auth/account.php" class="avatar" title="Account Settings">
          <?= dashEsc($initial) ?>
        </a>
      </div>
    </div>

    <main class="content bcp-exam">
      <div class="bcp-exam__shell">
        <header class="bcp-exam__header">
          <div>
            <p class="bcp-exam__eyebrow"><span class="bcp-exam__eyebrow-dot"></span> BCP CLASS SCHEDULING SYSTEM</p>
            <h1>Exam Timetable Generator<span class="bcp-exam__title-dot">.</span></h1>
            <p>Set one BCP-wide three-day examination period. Generate one examination timetable for ALL active college programs. The Program dropdown is for viewing saved schedules only. Changes to examination dates apply across the school.</p>
          </div>
          <span class="bcp-exam__chip" title="Examination Management">DEMO · EXAM MANAGEMENT</span>
        </header>

        <section class="bcp-exam__panel" aria-label="Examination configuration">
          <div class="bcp-exam__controls">
            <div class="bcp-exam__control">
              <label for="examPeriod">Academic year / semester</label>
              <select id="examPeriod" required>
                <option value="">Loading…</option>
              </select>
            </div>
            <div class="bcp-exam__control">
              <label for="examProgram">Program · View saved exams only</label>
              <select id="examProgram" required>
                <option value="">Loading…</option>
              </select>
            </div>
            <div class="bcp-exam__control">
              <label for="examWindow">Exam hours</label>
              <select id="examWindow">
                <option value="06-18">Fixed shifts · 6:00 AM – 6:00 PM</option>
              </select>
            </div>
            <div class="bcp-exam__control">
              <label for="examDate1">Day 1 · Date</label>
              <input id="examDate1" type="date" required>
            </div>
            <div class="bcp-exam__control">
              <label for="examDate2">Day 2 · Date</label>
              <input id="examDate2" type="date" required>
            </div>
            <div class="bcp-exam__control">
              <label for="examDate3">Day 3 · Date</label>
              <input id="examDate3" type="date" required>
            </div>
          </div>

          <div class="bcp-exam__actions">
            <button type="button" class="bcp-exam__btn-secondary" id="examSetDates" disabled><i class="fa-solid fa-calendar-check"></i> Set Unified Exam Dates</button>
            <button type="button" class="bcp-exam__btn-primary" id="examGenerate" disabled><i class="fa-solid fa-wand-magic-sparkles"></i> Generate ALL Programs</button>
            <button type="button" class="bcp-exam__btn-secondary bcp-exam__btn-success" id="examSave" disabled><i class="fa-solid fa-floppy-disk"></i> Send ALL to Academic Office</button>

          </div>
          <p class="bcp-exam__preview-help" id="examPreviewHelp" hidden>DRAFT ONLY · The generated schedules are displayed below automatically. Review the sections, subjects, rooms, proctors, dates and times. Use Program above to inspect another course. Nothing becomes ACTIVE until Academic Office approves.</p>
          <p id="examStatus" class="bcp-exam__status" role="status" aria-live="polite"><i class="fa-solid fa-circle-notch fa-spin bcp-exam__status-icon"></i> Loading academic periods…</p>
        </section>

        <section id="examReport" hidden>
          <!-- Inline schedule preview appears immediately after school-wide generation. -->
          <div class="bcp-exam__report-heading" style="background:#fff; padding:24px; border-radius:18px; border:1px solid #e5eaf3; margin-bottom:20px;">
            <div>
              <p class="bcp-exam__eyebrow">BESTLINK COLLEGE OF THE PHILIPPINES · EXAMINATION TIMETABLE</p>
              <h2 id="reportTitle">Examination Schedule</h2>
              <p id="reportInfo"></p>
            </div>
            <span class="bcp-exam__chip bcp-exam__chip--warning" id="examReportBadge">UNSAVED DEMO PREVIEW</span>
          </div>

          <!-- PREMIUM BADASS CARDS -->
          <div class="bcp-exam__stats">
            <article class="bcp-exam__metric">
              <div class="bcp-exam__metric-icon"><i class="fa-solid fa-layer-group"></i></div>
              <span>Required Exams</span>
              <strong id="examRequired">—</strong>
              <small>Total sections to map</small>
            </article>
            <article class="bcp-exam__metric">
              <div class="bcp-exam__metric-icon"><i class="fa-solid fa-calendar-check"></i></div>
              <span>Scheduled Exams</span>
              <strong id="examAssigned">—</strong>
              <small>Generated mappings</small>
            </article>
            <article class="bcp-exam__metric">
              <div class="bcp-exam__metric-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
              <span>Audit Issues</span>
              <strong id="examIssues">—</strong>
              <small>Validation warnings</small>
            </article>
            <article class="bcp-exam__metric">
              <div class="bcp-exam__metric-icon"><i class="fa-solid fa-shield-halved"></i></div>
              <span>Solver Status</span>
              <strong id="examSolver">—</strong>
              <small>OR-Tools result</small>
            </article>
            <article class="bcp-exam__metric">
              <div class="bcp-exam__metric-icon"><i class="fa-solid fa-stopwatch"></i></div>
              <span>Solve Time</span>
              <strong id="examSolveTime">—</strong>
              <small>OR-Tools wall time</small>
            </article>
          </div>

          <!-- NEW FILTERS AND SEARCH FOR EXAM LIST -->
          <div class="bcp-exam__filter-bar">
            <div class="bcp-exam__filter-actions">
              <div class="bcp-exam__filter-dropdown">
                <select id="examTypeFilter">
                  <option value="ALL">All Section Types</option>
                  <option value="REGULAR">Regular</option>
                  <option value="CLUSTER">Cluster</option>
                  <option value="MAJOR">Major</option>
                </select>
              </div>
              <div class="bcp-exam__search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="examSearchInput" placeholder="Search section, e.g. 11001" autocomplete="off">
              </div>
              <button type="button" id="examExpandAll" class="bcp-exam__btn-tertiary">Expand All</button>
              <button type="button" id="examCollapseAll" class="bcp-exam__btn-tertiary">Collapse All</button>
              <button type="button" class="bcp-exam__btn-secondary" id="examPrint" disabled><i class="fa-solid fa-print"></i> Print Official Report</button>
            </div>
          </div>

          <div id="examSections" class="bcp-exam__sections"></div>

          <footer class="bcp-exam__print-footer">
            <span>Prepared by: ________________________________</span>
            <span>Checked by: ________________________________</span>
            <span>Approved by: ________________________________</span>
          </footer>
        </section>
      </div>
    </main>

    <div class="footer">
      Scheduling System &copy; <?= $dashboardDate->format('Y') ?> Bestlink College of the Philippines
    </div>
  </div>

  <script>
    (() => {
      'use strict';

      const hamburgerBtn = document.getElementById('hamburgerBtn');
      const sidebar = document.getElementById('sidebar');
      if (hamburgerBtn && sidebar) {
        hamburgerBtn.addEventListener('click', () => {
          sidebar.classList.toggle('collapsed');
        });
      }

      const $ = id => document.getElementById(id);
      let result = null;
      let globalPreview = null;
      let pending = false;
      let hasSaved = false;
      let activeExamBatchId = null;
      let unifiedConfigured = false;
      let unifiedLocked = false;
      let examDatesDirty = false;
      let unifiedActivePrograms = [];
      let unifiedActiveBatchCount = 0;
      let savedUnifiedDates = [];
      let calendarConstraints = null;

      const make = (tag, text, cls) => {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined) n.textContent = String(text);
        return n;
      };
      const format = time => {
        const [hh, mm] = String(time).split(':').map(Number);
        return `${hh%12||12}:${String(mm).padStart(2,'0')} ${hh>=12?'PM':'AM'}`;
      };
      const niceDate = iso => new Date(iso + 'T12:00:00').toLocaleDateString('en-PH', {
        month: 'long',
        day: 'numeric',
        year: 'numeric'
      });

      const dateInputValue = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
      const nextSchoolDay = iso => {
        const d = new Date(iso + 'T12:00:00');
        do {
          d.setDate(d.getDate() + 1);
        } while (d.getDay() === 0);
        return dateInputValue(d);
      };

      function upgradeSelects() {
        document.querySelectorAll('select').forEach(selectElem => {
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
                selectElem.value = opt.value;
                selectElem.dispatchEvent(new Event('change'));
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

      function setStatus(message, kind = 'info') {
        const statusEl = $('examStatus');
        const iconMap = {
          success: "fa-circle-check",
          error: "fa-triangle-exclamation",
          warning: "fa-triangle-exclamation",
          info: "fa-circle-info",
          loading: "fa-circle-notch fa-spin"
        };
        statusEl.dataset.kind = kind;
        statusEl.innerHTML = `<i class="fa-solid ${iconMap[kind] || iconMap.info} bcp-exam__status-icon"></i> ${message}`;
      }

      const currentDates = () => [1, 2, 3].map(i => $('examDate' + i).value);
      const isSchoolDay = iso => {
        const d = new Date(iso + 'T12:00:00');
        return !Number.isNaN(d.getTime()) && d.getDay() !== 0;
      };
      const datesValid = dates => !dates.some(x => !x) && new Set(dates).size === 3 && dates[0] < dates[1] && dates[1] < dates[2] && dates.every(isSchoolDay) && (!calendarConstraints?.min_date || dates.every(x => x >= calendarConstraints.min_date)) && (!calendarConstraints?.max_date || dates.every(x => x <= calendarConstraints.max_date));

      function applyDateLock() {
        for (let i = 1; i <= 3; i++) $('examDate' + i).disabled = pending;
        $('examSetDates').disabled = pending;
        $('examSetDates').innerHTML = unifiedConfigured ? '<i class="fa-solid fa-calendar-pen"></i> Update Unified Exam Dates' : '<i class="fa-solid fa-calendar-plus"></i> Set Unified Exam Dates';
      }

      function applyCalendarConstraints() {
        for (let i = 1; i <= 3; i++) {
          const input = $('examDate' + i);
          if (calendarConstraints?.min_date) input.min = calendarConstraints.min_date;
          else input.removeAttribute('min');
          if (calendarConstraints?.max_date) input.max = calendarConstraints.max_date;
          else input.removeAttribute('max');
        }
      }

      function invalidatePreview(message = 'Examination configuration changed. Generate a fresh preview before saving.') {
        if (result?.status === 'UNSAVED_GLOBAL_EXAM_PREVIEW' || result?.preview_token) {
          result = null;
          $('examReport').hidden = true;
          $('examPreviewHelp').hidden = true;
          $('examSave').disabled = true;
          $('examPrint').disabled = true;
        }
        $('examGenerate').disabled = pending;
        if (message) setStatus(message, 'info');
      }

      async function api(endpoint, method, body) {
        const r = await fetch('./' + endpoint, {
          method,
          credentials: 'same-origin',
          cache: 'no-store',
          headers: {
            'Content-Type': 'application/json'
          },
          body: body ? JSON.stringify(body) : undefined
        });
        let data;
        try {
          data = await r.json();
        } catch {
          throw Error('Endpoint returned invalid JSON. Check PHP error log.');
        }
        if (!r.ok || data.success !== true) throw Error(data.message || data.status || 'Exam request failed.');
        return data;
      }

      async function loadUnifiedPeriod() {
        globalPreview = null;
        $('examPreviewHelp').hidden = true;
        unifiedConfigured = false;
        unifiedLocked = false;
        examDatesDirty = false;
        unifiedActivePrograms = [];
        unifiedActiveBatchCount = 0;
        savedUnifiedDates = [];
        calendarConstraints = null;
        $('examSetDates').disabled = true;
        $('examGenerate').disabled = true;
        const periodId = $('examPeriod').value;
        if (!periodId) return;
        try {
          const data = await api('exam-period.php?period_id=' + encodeURIComponent(periodId), 'GET');
          unifiedConfigured = Boolean(data.configured);
          unifiedLocked = false;
          unifiedActivePrograms = data.active_programs || [];
          unifiedActiveBatchCount = Number(data.active_exam_batch_count || 0);
          calendarConstraints = data.calendar_constraints || null;
          if (Array.isArray(data.exam_dates) && data.exam_dates.length === 3) {
            savedUnifiedDates = [...data.exam_dates];
            for (let i = 1; i <= 3; i++) $('examDate' + i).value = data.exam_dates[i - 1];
          } else {
            savedUnifiedDates = [];
            const today = dateInputValue(new Date());
            const first = calendarConstraints?.min_date && today < calendarConstraints.min_date
              ? calendarConstraints.min_date : today;
            const begin = new Date(first + 'T12:00:00');
            while (begin.getDay() === 0) begin.setDate(begin.getDate() + 1);
            const d1 = dateInputValue(begin);
            const d2 = nextSchoolDay(d1);
            const d3 = nextSchoolDay(d2);
            const withinRange = !calendarConstraints?.max_date || d3 <= calendarConstraints.max_date;
            const defaults = withinRange ? [d1, d2, d3] : ['', '', ''];
            for (let i = 1; i <= 3; i++) $('examDate' + i).value = defaults[i - 1];
            examDatesDirty = withinRange;
          }
          applyCalendarConstraints();
          applyDateLock();
          $('examGenerate').disabled = false;
          if (unifiedConfigured) {
            const programs = unifiedActivePrograms.length ? ` Programs: ${unifiedActivePrograms.join(', ')}.` : '';
            if (unifiedActiveBatchCount > 0) setStatus(`Unified BCP exam dates are set. ${unifiedActiveBatchCount} ACTIVE exam timetable(s) currently use these dates.${programs} If you change only the dates, the system will keep the saved room, proctor, time, subject, and Exam Day assignments, remap them to the new dates, and revalidate before saving.`, 'info');
            else setStatus('Unified BCP exam dates are set. You may update them even if other programs have not generated schedules yet.', 'info');
          } else {
            setStatus('Choose Day 1, review the auto-filled Day 2 and Day 3, then click Set Unified Exam Dates before generating.', 'info');
          }
        } catch (e) {
          applyDateLock();
          setStatus(e.message, 'error');
        }
      }

      async function saveUnifiedPeriod() {
        if (pending) return false;
        const dates = currentDates();
        if (!datesValid(dates)) {
          const range = calendarConstraints?.min_date && calendarConstraints?.max_date ? ` Dates must also be within ${calendarConstraints.min_date} to ${calendarConstraints.max_date}.` : '';
          setStatus('Choose three different Monday–Saturday dates in ascending order.' + range, 'error');
          return false;
        }
        const changing = !savedUnifiedDates.length || dates.some((d, i) => d !== savedUnifiedDates[i]);
        let confirmRetime = false;
        if (changing && unifiedActiveBatchCount > 0) {
          const programs = unifiedActivePrograms.length ? ` (${unifiedActivePrograms.join(', ')})` : '';
          const ok = window.confirm(`Change the unified examination dates for ${unifiedActiveBatchCount} ACTIVE exam timetable(s)${programs}? The system will KEEP the existing room, proctor, time, subject, section, and Exam Day 1/2/3 assignments. Only the actual dates will move, then every saved timetable will be revalidated. No solver regeneration is performed when validation passes.`);
          if (!ok) return false;
          confirmRetime = true;
        }
        pending = true;
        $('examSetDates').disabled = true;
        $('examGenerate').disabled = true;
        setStatus(changing && unifiedActiveBatchCount > 0 ? 'Moving the existing saved exam timetable(s) to the new dates and revalidating all assignments…' : (changing ? 'Saving the BCP-wide examination dates for this academic period…' : 'Checking the saved examination calendar…'), 'loading');
        try {
          const data = await api('exam-period.php', 'POST', {
            period_id: Number($('examPeriod').value),
            exam_dates: dates,
            confirm_retime: confirmRetime
          });
          unifiedConfigured = true;
          unifiedLocked = false;
          examDatesDirty = false;
          unifiedActivePrograms = data.active_programs || [];
          unifiedActiveBatchCount = Number(data.active_exam_batch_count || 0);
          calendarConstraints = data.calendar_constraints || calendarConstraints;
          if (Array.isArray(data.exam_dates)) {
            savedUnifiedDates = [...data.exam_dates];
            for (let i = 1; i <= 3; i++) $('examDate' + i).value = data.exam_dates[i - 1];
          }
          applyCalendarConstraints();
          applyDateLock();
          $('examGenerate').disabled = false;
          if (Number(data.retimed_exam_batch_count || 0) > 0) {
            const affected = (data.retimed_programs || []).length ? ` Programs: ${(data.retimed_programs||[]).join(', ')}.` : '';
            if (hasSaved && result && Array.isArray(result.assignments)) {
              result.exam_dates = [...data.exam_dates];
              for (const assignment of result.assignments) {
                const day = Number(assignment.exam_day || 0);
                if (day >= 1 && day <= 3) assignment.exam_date = data.exam_dates[day - 1];
              }
              render(result);
              $('examReportBadge').textContent = 'SAVED DEMO · BATCH #' + activeExamBatchId;
              $('examReportBadge').classList.remove('bcp-exam__chip--warning');
              $('examPrint').disabled = false;
            }
            setStatus(`Exam dates updated successfully for ALL existing ACTIVE exam programs. ${data.retimed_exam_batch_count} saved timetable(s) kept the SAME batch, room, proctor, time, subject, section, and Exam Day assignments; only the actual dates changed.${affected} No exam data was deleted or regenerated.`, 'success');
          } else if (data.status === 'UNIFIED_EXAM_DATES_UNCHANGED') {
            setStatus('Unified BCP examination dates are unchanged and valid for this academic period.', 'success');
          } else {
            setStatus('Unified BCP examination dates saved. Selecting a program will automatically use these dates.', 'success');
          }
          return true;
        } catch (e) {
          setStatus(e.message, 'error');
          return false;
        } finally {
          pending = false;
          applyDateLock();
          $('examGenerate').disabled = pending;
        }
      }

      async function viewGeneratedPreview(scrollIntoView = false) {
        if (!globalPreview?.preview_token) return;
        const selected = $('examProgram').value;
        const data = await api('exam-global-view.php?preview_token=' + encodeURIComponent(globalPreview.preview_token) + '&program=' + encodeURIComponent(selected), 'GET');
        result = data;
        render(data);
        $('examReportBadge').textContent = 'DRAFT · NOT YET APPROVED';
        $('examReportBadge').classList.add('bcp-exam__chip--warning');
        $('examPrint').disabled = false;
        $('examPreviewHelp').hidden = false;
        if (scrollIntoView) $('examReport').scrollIntoView({behavior:'smooth',block:'start'});
      }

      async function loadSaved() {
        result = null;
        hasSaved = false;
        activeExamBatchId = null;
        $('examReport').hidden = true;
        $('examPrint').disabled = true;
        $('examSave').disabled = true;
        $('examPreviewHelp').hidden = !globalPreview;
        $('examGenerate').disabled = pending;
        $('examGenerate').innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> Generate ALL Programs';
        $('examSave').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Send ALL to Academic Office';
        const program = $('examProgram').value;
        if (!program) {
          setStatus('Choose a program.', 'info');
          return;
        }
        try {
          const data = await api('exam-saved.php?period_id=' + encodeURIComponent($('examPeriod').value) + '&program=' + encodeURIComponent(program), 'GET');
          if (data.has_saved_exams) {
            hasSaved = true;
            activeExamBatchId = Number(data.exam_batch_id);
            result = data;
            data.solver_status = 'SAVED';
            const authoritativeDates = savedUnifiedDates.length === 3 ? savedUnifiedDates : data.exam_dates;
            if (savedUnifiedDates.length === 3 && Array.isArray(data.exam_dates) && data.exam_dates.some((d, i) => d !== savedUnifiedDates[i])) throw Error('Saved exam batch dates do not match the current academic-period exam calendar. Reload the page and review the database before continuing.');
            if (!examDatesDirty) {
              for (let i = 1; i <= 3; i++) {
                $('examDate' + i).value = authoritativeDates[i - 1];
              }
            }
            data.exam_dates = [...authoritativeDates];
            render(data);
            $('examReportBadge').textContent = 'SAVED DEMO · BATCH #' + data.exam_batch_id;
            $('examReportBadge').classList.remove('bcp-exam__chip--warning');
            $('examGenerate').disabled = false;
            $('examGenerate').innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> Generate ALL Programs';
            $('examSave').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Send ALL to Academic Office';
            setStatus(`Loaded ACTIVE DEMO exam batch #${data.exam_batch_id}: ${data.returned_exams} saved exams. You may print it or generate a replacement preview; the current batch will remain unchanged until confirmation.`, 'success');
          } else {
            $('examGenerate').disabled = pending;
            if (unifiedConfigured) setStatus(unifiedActiveBatchCount > 0 ? 'Unified dates are ready. Date-only changes preserve and revalidate existing saved assignments; regeneration is not required when the saved timetable remains valid on the new dates.' : 'Unified dates are ready. Generate all programs when ready.', 'info');
          }
        } catch (e) {
          setStatus(e.message, 'error');
        }
      }

      async function init() {
        try {
          const data = await api('exam-preview.php', 'GET');
          const p = $('examPeriod');
          p.replaceChildren();
          for (const item of data.periods || []) {
            const opt = new Option(`${item.academic_year} · Semester ${item.semester} (DEMO)`, item.academic_period_id);
            p.add(opt);
          }
          const programs = $('examProgram');
          programs.replaceChildren();
          for (const item of data.programs || []) {
            programs.add(new Option(`${item.program_code} · ${item.program_name}`, item.program_code));
          }
          if (!(data.periods || []).length) throw Error('No DEMO academic period is available.');
          if (!(data.programs || []).length) throw Error('No active college program is available.');
          if ([...programs.options].some(o => o.value === 'BSIT')) programs.value = 'BSIT';
          upgradeSelects();
          await loadUnifiedPeriod();
          await loadSaved();
          await showExamApprovalStatus();
        } catch (e) {
          setStatus(e.message, 'error');
        }
      }

      function toggleAccordion(header, body, icon, forceState) {
        const isExpanded = header.getAttribute("aria-expanded") === "true";
        const newState = forceState !== undefined ? forceState : !isExpanded;
        header.setAttribute("aria-expanded", newState);
        if (newState) {
          icon.classList.remove("fa-chevron-down");
          icon.classList.add("fa-chevron-up");
        } else {
          icon.classList.remove("fa-chevron-up");
          icon.classList.add("fa-chevron-down");
        }
      }

      function printSection(section, entries, dates) {
        const paper = make('article', undefined, 'bcp-exam__sheet');

        // ACCORDION HEADER
        const title = make('div', undefined, 'bcp-exam__sheet-top');
        title.setAttribute("tabindex", "0");
        title.setAttribute("role", "button");
        title.setAttribute("aria-expanded", "false"); // Default to false (collapsed)

        const titleContent = make('div', undefined, 'bcp-exam__sheet-top-content');
        titleContent.append(make('h3', section.print_title || `Section ${section.section_code} · Year ${section.year_level}`), make('span', section.section_type));
        const accIcon = make("i", undefined, "fa-solid fa-chevron-down bcp-exam__sheet-icon");
        title.append(titleContent, accIcon);
        paper.append(title);

        // ACCORDION BODY (CSS Grid Wrap)
        const bodyWrap = make('div', undefined, 'bcp-exam__sheet-body');
        const bodyInner = make('div', undefined, 'bcp-exam__sheet-inner');
        const grid = make('div', undefined, 'bcp-exam__sheet-content');

        const dayGrid = make('div', undefined, 'bcp-exam__day-grid');

        for (let d = 1; d <= 3; d++) {
          const day = make('section', undefined, 'bcp-exam__day');
          day.append(make('h4', `DAY ${d} · ${niceDate(dates[d-1])}`));
          const rows = entries.filter(x => x.exam_day === d).sort((a, b) => a.start_time.localeCompare(b.start_time));
          if (!rows.length) {
            day.append(make('p', 'No examination for this section.', 'bcp-exam__empty-day'));
            dayGrid.append(day);
            continue;
          }

          const wrap = make('div', undefined, 'bcp-exam__tablewrap');
          const table = make('table');
          const thead = make('thead');
          const tr = make('tr');
          for (const heading of ['Time', 'Subject', 'Room', 'Proctor']) tr.append(make('th', heading));
          thead.append(tr);
          table.append(thead);
          const tbody = make('tbody');
          for (const e of rows) {
            const t = make('tr');
            for (const value of [`${format(e.start_time)} – ${format(e.end_time)}`, `${e.subject_code} · ${e.subject_title}`, e.room_name, e.proctor_name]) t.append(make('td', value));
            tbody.append(t);
          }
          table.append(tbody);
          wrap.append(table);
          day.append(wrap);
          dayGrid.append(day);
        }
        grid.append(dayGrid);
        grid.append(make('p', 'Each subject is a 1-hour exam. Every section keeps the SAME fixed shift on all three exam days (6–9 AM, 9 AM–12 PM, 12–3 PM, or 3–6 PM). Up to three subjects run consecutively per day. Fourth-year students must also check their individual Cluster and Major membership.', 'bcp-exam__sheet-note'));

        bodyInner.append(grid);
        bodyWrap.append(bodyInner);
        paper.append(bodyWrap);

        title.addEventListener("click", () => toggleAccordion(title, bodyWrap, accIcon));
        title.addEventListener("keydown", (e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            toggleAccordion(title, bodyWrap, accIcon);
          }
        });

        return paper;
      }

      let redrawExamSections = null;
      function render(data) {
        $('examReport').hidden = false;
        $('reportTitle').textContent = `${data.program||$('examProgram').value} · Examination Timetable`;
        $('reportInfo').textContent = `${data.period.academic_year} · Semester ${data.period.semester} · Class batch #${data.class_batch_id} · ${data.exam_dates.map((x,i)=>`Day ${i+1}:${niceDate(x)}`).join('  |  ')}`;
        $('examRequired').textContent = data.required_exams;
        $('examAssigned').textContent = data.returned_exams;

        $('examIssues').textContent = (data.issues || []).length;

        $('examSolver').textContent = data.solver_status || 'SAVED';
        const solveSeconds = Number(data.solve_seconds);
        $('examSolveTime').textContent = Number.isFinite(solveSeconds) ? `${solveSeconds.toFixed(2)} s` : '—';

        // Realtime Render logic based on filters
        function drawSections() {
          const q = $('examSearchInput').value.trim().toLowerCase();
          const typeFilt = $('examTypeFilter').value;

          const groups = new Map();
          for (const row of data.assignments) {
            if (typeFilt !== "ALL" && String(row.section_type).toUpperCase() !== String(typeFilt).toUpperCase()) continue;
            if (q && !String(row.section_code).toLowerCase().includes(q)) continue;

            if (!groups.has(row.section_id)) groups.set(row.section_id, {
              section_code: row.section_code,
              section_type: row.section_type,
              year_level: row.year_level,
              rows: []
            });
            groups.get(row.section_id).rows.push(row);
          }

          const target = $('examSections');
          target.replaceChildren();
          if (groups.size === 0) {
            target.append(make('p', 'No matching sections found for the current filters.', 'bcp-exam__no-results'));
            return;
          }

          const ordered = [...groups.values()].sort((a, b) => a.year_level - b.year_level || a.section_code.localeCompare(b.section_code));
          for (const group of ordered) target.append(printSection(group, group.rows, data.exam_dates));
        }

        redrawExamSections = drawSections;
        drawSections(); // initial draw
        $('examPrint').disabled = false;
      }

      // One set of controls is shared by saved schedules and the inline draft.
      $('examSearchInput').addEventListener('input', () => redrawExamSections?.());
      $('examTypeFilter').addEventListener('change', () => redrawExamSections?.());
      $('examExpandAll').addEventListener('click', () => {
        document.querySelectorAll('.bcp-exam__sheet-top').forEach(hdr => {
          toggleAccordion(hdr, hdr.nextElementSibling, hdr.querySelector('.bcp-exam__sheet-icon'), true);
        });
      });
      $('examCollapseAll').addEventListener('click', () => {
        document.querySelectorAll('.bcp-exam__sheet-top').forEach(hdr => {
          toggleAccordion(hdr, hdr.nextElementSibling, hdr.querySelector('.bcp-exam__sheet-icon'), false);
        });
      });

      async function showExamApprovalStatus() {
        try {
          const period = Number($('examPeriod').value);
          if (!period) return;
          const data = await api('exam-approval-status.php?period_id=' + encodeURIComponent(period), 'GET');
          if (!data.request) return;
          const r = data.request;
          const note = r.remarks ? ' Academic Office remarks: ' + r.remarks : '';
          if (r.status === 'PENDING_REVIEW') setStatus('Exam request #' + r.request_id + ' is awaiting Academic Office approval. No exam schedules saved yet.', 'info');
          else if (r.status === 'REJECTED') setStatus('Exam request #' + r.request_id + ' was rejected. Generate again.' + note, 'error');
          else if (r.status === 'APPROVED') setStatus('Exam request #' + r.request_id + ' was approved and saved.' + note, 'success');
        } catch (e) { /* Keep exam generator usable if approval metadata is unavailable. */ }
      }

      $('examGenerate').addEventListener('click', async () => {
        if (pending) return;
        const dates = currentDates();
        if (!datesValid(dates)) {
          setStatus('Choose three valid exam dates within the academic period.', 'error');
          return;
        }
        if (unifiedActiveBatchCount > 0) {
          setStatus('Existing ACTIVE exams were found. Back up and reset those exam schedules before generating a new school-wide timetable. Program selection is view-only.', 'error');
          return;
        }
        if (!unifiedConfigured || examDatesDirty || dates.some((d,i) => d !== savedUnifiedDates[i])) {
          const ok = await saveUnifiedPeriod();
          if (!ok) return;
        }
        pending = true;
        globalPreview = null;
        $('examPreviewHelp').hidden = true;
        $('examSave').disabled = true;
        $('examGenerate').disabled = true;
        $('examReport').hidden = true;
        $('examPrint').disabled = true;
        setStatus('Generating ALL active college programs and checking cross-program room and proctor conflicts. Nothing will be saved until you confirm.', 'loading');
        try {
          const data = await api('exam-global-preview.php','POST',{
            period_id:Number($('examPeriod').value),
            exam_dates:dates,
            exam_window:$('examWindow').value
          });
          if (data.status !== 'GLOBAL_EXAM_PREVIEW_READY' || !data.preview_token || data.required_exams !== data.returned_exams) {
            throw Error('School-wide preview failed its completeness audit.');
          }
          globalPreview = data;
          $('examSave').disabled = true;
          $('examPreviewHelp').hidden = false;
          await viewGeneratedPreview(true);
          $('examSave').disabled = false;
          setStatus(`School-wide preview ready: ${data.program_count} programs and ${data.returned_exams}/${data.required_exams} exams passed the checks. Previewing ${$('examProgram').value}; change Program to view other generated schedules, then send ALL to Academic Office. Nothing has been saved.`, 'success');
        } catch(e) {
          $('examSave').disabled = true;
          $('examReport').hidden = true;
          setStatus(e.message,'error');
        } finally {
          pending = false;
          $('examGenerate').disabled = false;
          applyDateLock();
        }
      });

      $('examPeriod').addEventListener('change', async () => {
        if (!pending) {
          await loadUnifiedPeriod();
          await loadSaved();
          await showExamApprovalStatus();
        }
      });
      $('examProgram').addEventListener('change', async () => {
        if (!pending) {
          // Unified dates are shared across courses. Keep the administrator's
          // date selections until they are explicitly saved or generated.
          if (globalPreview) {
            try {
              await viewGeneratedPreview();
              $('examSave').disabled = false;
            } catch (e) {
              $('examSave').disabled = true;
              setStatus(e.message, 'error');
            }
          } else await loadSaved();
        }
      });
      $('examSetDates').addEventListener('click', saveUnifiedPeriod);
      $('examDate1').addEventListener('change', () => {
        if (pending) return;
        const d1 = $('examDate1').value;
        if (d1) {
          const d2 = nextSchoolDay(d1);
          $('examDate2').value = d2;
          $('examDate3').value = nextSchoolDay(d2);
        }
        examDatesDirty = true;
        globalPreview = null;
        $('examPreviewHelp').hidden = true;
        $('examSave').disabled = true;
        invalidatePreview('Day 2 and Day 3 were auto-filled. Review the dates, then save the unified BCP examination period.');
      });
      for (const id of ['examDate2', 'examDate3']) {
        $(id).addEventListener('change', () => {
          if (pending) return;
          examDatesDirty = true;
          globalPreview = null;
            $('examPreviewHelp').hidden = true;
          $('examSave').disabled = true;
          invalidatePreview('Unified examination dates changed. Save them before generating.');
        });
      }
      $('examWindow').addEventListener('change', () => {
        if (!pending) { globalPreview = null; $('examPreviewHelp').hidden = true; $('examSave').disabled = true; invalidatePreview('Exam hours changed. Generate a fresh preview before saving.'); }
      });

      $('examSave').addEventListener('click', async () => {
        if (pending || !globalPreview?.preview_token) return;
        if (!window.confirm(`Submit the complete preview for ${globalPreview.program_count} programs and ${globalPreview.returned_exams} exams to Academic Office? No exams are saved until approved.`)) return;
        pending = true;
        $('examSave').disabled = true;
        $('examGenerate').disabled = true;
        setStatus('Sending school-wide exam preview to Academic Office for approval…','loading');
        try {
          const saved = await api('exam-global-submit.php','POST',{preview_token:globalPreview.preview_token});
          if (saved.status !== 'PENDING_REVIEW') throw Error('Academic Office submission did not finish.');
          globalPreview = null;
            $('examPreviewHelp').hidden = true;
          $('examReport').hidden = true;
          await loadUnifiedPeriod();
          await loadSaved();
          setStatus(`Exam request #${saved.request_id} sent to Academic Office. Await approval before exam schedules become ACTIVE.`, 'success');
        } catch (e) {
          setStatus(e.message, 'error');
          $('examSave').disabled = false;
        } finally {
          pending = false;
          $('examGenerate').disabled = false;
          applyDateLock();
        }
      });

      $('examPrint').addEventListener('click', () => {
        if (result && !pending) {
          // Expand everything before printing to ensure all tables are visible to the browser
          document.querySelectorAll('.bcp-exam__sheet-top').forEach(hdr => {
            toggleAccordion(hdr, hdr.nextElementSibling, hdr.querySelector('.bcp-exam__sheet-icon'), true);
          });
          // Add slight delay to allow CSS grid animation to open before triggering print dialog
          setTimeout(() => window.print(), 100);
        }
      });
      init();
    })();
  </script>
</body>

</html>
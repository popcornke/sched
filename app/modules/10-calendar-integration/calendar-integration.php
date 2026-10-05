<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/auth.php';

authRequire();
/** Module 10: independent, read-only local DEMO calendar. */

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$APP_ROOT = '../../';
$ACTIVE_NAV = 'calendar_integration';

// Safe variables for Topbar
$role = htmlspecialchars($_SESSION['role'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$initial = strtoupper(substr($_SESSION['first_name'] ?? 'U', 0, 1));
$dashboardDate = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Calendar Integration | BCP</title>
  <link rel="icon" href="../../images/BCP_LOGO.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <link rel="stylesheet" href="../../assets/css/calendar-integration.css?v=<?= time() ?>">
</head>

<body>

  <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

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
          <?= $role ?>
        </span>
        <a href="../../auth/account.php" class="avatar" title="Account Settings">
          <?= $initial ?>
        </a>
      </div>
    </div>

    <!-- CALENDAR CONTENT -->
    <div class="calendar-app__container">
        <main id="bcCalendar" class="content calendar-app">
        <header class="calendar-hero">
            <div>
            <p class="calendar-eyebrow">BCP CLASS SCHEDULING SYSTEM · MODULE 10</p>
            <h1>Calendar Integration</h1>
            <p class="calendar-muted">One view for saved class meetings, examination dates, faculty duties, and substitutes.</p>
            </div><span class="calendar-badge">READ-ONLY · DEMO</span>
        </header>

        <section class="calendar-panel calendar-filters" aria-label="Calendar filters">
            <div class="calendar-filter-grid">
            <label>Academic period<select id="ciPeriod" disabled>
                <option>Loading periods…</option>
                </select></label>
            <label>Program<select id="ciProgram" disabled>
                <option>Loading programs…</option>
                </select></label>
            <label>Professor / proctor<select id="ciTeacher" disabled>
                <option>Loading faculty…</option>
                </select></label>
            <label>Show<select id="ciKind">
                <option value="ALL">All saved events</option>
                <option value="REGULAR_CLASS">Regular classes</option>
                <option value="SUBSTITUTE_CLASS">Substitute duties</option>
                <option value="EXAM">Examinations</option>
                <option value="SPECIAL_CLASS">Saved special classes</option>
                </select></label>
            <label>Find section / subject / room<input id="ciSearch" type="search" maxlength="100" placeholder="e.g. 11001, IM101..."></label>
            </div>
            <div class="calendar-toolbar">
            <div class="calendar-navigation"><button type="button" id="ciPrev" aria-label="Previous month">←</button>
                <button type="button" id="ciToday">Today</button>
                <button type="button" id="ciNext" aria-label="Next month">→</button>
                <strong id="ciMonth" aria-live="polite">Loading calendar…</strong>
            </div>
            <div class="calendar-actions"><button type="button" id="ciRefresh">Refresh saved records</button>
                <button type="button" id="ciPrint" disabled>Print selected day</button>
            </div>
            </div>
            <p id="ciStatus" role="status" aria-live="polite">Loading saved calendar…</p>
            <p class="calendar-warning">Weekly class meetings are expanded from saved patterns. Examination schedules naturally override and hide regular classes on the same day and time. Holidays, cancellations and school-calendar dates are not verified. No booking or changes are made.</p>
        </section>

        <section class="calendar-overview" aria-label="Visible month summary">
            <div><span>Regular class occurrences</span><strong id="ciRegular">—</strong></div>
            <div><span>Substitute occurrences</span><strong id="ciSubs">—</strong></div>
            <div><span>Saved examinations</span><strong id="ciExams">—</strong></div>
            <div><span>Saved special classes</span><strong id="ciSpecial">—</strong></div>
        </section>

        <div class="calendar-workspace">
            <section class="calendar-panel calendar-month-panel" aria-labelledby="ciMonthTitle">
            <div class="calendar-section-title">
                <div><span class="calendar-step">01</span>
                <h2 id="ciMonthTitle">Monthly view</h2>
                </div>
                <span class="calendar-muted">Select a date to inspect its saved meetings.</span>
            </div>
            <div class="calendar-legend"><span class="calendar-legend-regular">Class</span><span class="calendar-legend-exam">Exam</span>
                <span class="calendar-legend-sub">Substitute</span><span class="calendar-legend-special">Special</span>
            </div>
            <div class="calendar-week-head" aria-hidden="true"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div>
            <div class="calendar-month-grid" id="ciGrid" aria-label="Calendar days"></div>
            <p class="calendar-muted calendar-grid-note">Month counts reflect selected filters. The first/last row may include adjacent-month dates.</p>
            </section>
            <section class="calendar-panel calendar-agenda" aria-labelledby="ciAgendaTitle">
            <div class="calendar-section-title">
                <div><span class="calendar-step">02</span>
                <h2 id="ciAgendaTitle">Day schedule</h2>
                </div>
                <span class="calendar-badge" id="ciDayCount">0 events</span>
            </div>
            <h3 id="ciSelected">Choose a date</h3>
            <div id="ciDayEvents" class="calendar-day-events" aria-live="polite"></div>
            </section>
        </div>

        </main>

        <!-- ============================================================
         PRINT / REPORT PREVIEW MODAL
         ============================================================ -->
        <div id="bcpPrintModal" class="bcp-preview-modal" role="dialog" aria-modal="true" hidden>
            <div class="bcp-preview-modal__backdrop" id="bcpPrintModalBackdrop" aria-hidden="true"></div>
            <div class="bcp-preview-modal__content" tabindex="-1">
                <div class="bcp-preview-modal__header">
                    <div>
                        <h2 id="bcpPrintModalTitle"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Official Report Preview</h2>
                        <p id="bcpPrintModalDescription">Review the official daily calendar layout before printing.</p>
                    </div>
                    <button type="button" id="bcpPrintCloseBtn" class="bcp-preview-modal__close" aria-label="Close report preview" title="Close Modal (Esc)">&times;</button>
                </div>

                <div id="bcpPrintableArea" class="bcp-print-document">
                    <div id="bcpPrintContent" class="bcp-print-body">
                        <!-- Official print layout injected here via JS -->
                    </div>
                </div>

                <div class="bcp-preview-modal__footer">
                    <button type="button" id="bcpPrintCancelBtn" class="calendar-toolbar button" style="background:#f1f5f9;border:1px solid transparent;padding:0 20px;border-radius:11px;font-weight:700;cursor:pointer;">Cancel</button>
                    <button type="button" id="bcpPrintConfirmBtn" class="calendar-toolbar button" style="background:#1a3a8c;color:#fff;border:1px solid #1a3a8c;padding:0 20px;border-radius:11px;font-weight:700;cursor:pointer;"><i class="fa-solid fa-print" aria-hidden="true"></i> Print Document</button>
                </div>
            </div>
        </div>

    </div>

    <!-- FOOTER COMPONENT -->
    <div class="footer">
      Scheduling System &copy; <?= $dashboardDate->format('Y') ?> Bestlink College of the Philippines
    </div>
  </div>

  <script>
    (() => {
      'use strict';

      // Sidebar Hamburger Logic
      const hamburgerBtn = document.getElementById('hamburgerBtn');
      const sidebar = document.getElementById('sidebar');
      if (hamburgerBtn && sidebar) {
        hamburgerBtn.addEventListener('click', () => {
          sidebar.classList.toggle('collapsed');
        });
      }

      const root = document.getElementById('bcCalendar');
      const $ = id => document.getElementById(id);
      const create = (tag, content, cls) => {
        const el = document.createElement(tag);
        if (content !== undefined && content !== null) el.textContent = String(content);
        if (cls) el.className = cls;
        return el;
      };
      const weekday = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
      const types = {
        REGULAR_CLASS: 'Regular Class',
        SUBSTITUTE_CLASS: 'Substitute Duty',
        EXAM: 'Examination',
        SPECIAL_CLASS: 'Special Class'
      };
      const pad = n => String(n).padStart(2, '0');
      const fmt = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
      const local = (date) => {
        const [y, m, d] = date.split('-').map(Number);
        return new Date(y, m - 1, d);
      };
      const longDate = (date) => local(date).toLocaleDateString('en-PH', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric'
      });
      const clock = (t) => {
        if (!t) return '—';
        const [h, m] = t.split(':').map(Number);
        return `${h%12||12}:${pad(m)} ${h>=12?'PM':'AM'}`;
      };
      
      const timeToMinutes = (t) => {
          if (!t) return 0;
          const [h, m] = t.split(':').map(Number);
          return h * 60 + m;
      };

      let selected = fmt(new Date()),
        month = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
      let all = [],
        visible = [],
        loading = 0,
        periods = [],
        teachers = [],
        programs = [];

      function status(message, error = false) {
        $('ciStatus').textContent = message;
        $('ciStatus').dataset.error = error ? 'yes' : 'no';
      }
      async function api(params) {
        const url = new URL('calendar-api.php', location.href);
        for (const [k, v] of Object.entries(params)) url.searchParams.set(k, String(v));
        const res = await fetch(url, {
          credentials: 'same-origin',
          cache: 'no-store',
          headers: {
            Accept: 'application/json'
          }
        });
        let body;
        try {
          body = await res.json();
        } catch {
          throw Error('PHP did not return JSON. Check the Apache error log.');
        }
        if (!res.ok || body.success !== true) throw Error(body.message || body.status || 'Calendar request failed.');
        return body;
      }

      function option(select, value, text) {
        select.add(new Option(text, String(value)));
      }

      function range() {
        const first = new Date(month.getFullYear(), month.getMonth(), 1);
        const mondayIndex = (first.getDay() + 6) % 7;
        const start = new Date(month.getFullYear(), month.getMonth(), 1 - mondayIndex);
        const daysInMonth = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();
        const cells = Math.ceil((mondayIndex + daysInMonth) / 7) * 7;
        const end = new Date(start.getFullYear(), start.getMonth(), start.getDate() + cells - 1);
        return {
          start: fmt(start),
          end: fmt(end),
          cells
        };
      }

      function eventText(e) {
        return `${e.start_time}–${e.end_time} · ${e.subject_code||'Special class'} · ${e.section_code||'Individual participants'}`;
      }

      function match(e) {
        const kind = $('ciKind').value;
        if (kind !== 'ALL' && e.type !== kind) return false;
        const q = $('ciSearch').value.trim().toLocaleLowerCase();
        if (!q) return true;
        return [e.subject_code, e.subject_title, e.section_code, e.room_name, e.teacher_name, e.program_code, e.original_teacher_name]
          .some(x => String(x ?? '').toLocaleLowerCase().includes(q));
      }

      function effective() {
        // OVERLAP FILTERING RULE: "Dapat pag exam, exam lang, walang nakapatong na regular class."
        
        const exams = all.filter(e => e.type === 'EXAM');
        const others = all.filter(e => e.type !== 'EXAM');
        
        const cleanOthers = others.filter(o => {
            const hasOverlappingExam = exams.some(ex => {
                if (ex.date !== o.date || ex.section_code !== o.section_code) return false;
                
                const examStart = timeToMinutes(ex.start_time);
                const examEnd = timeToMinutes(ex.end_time);
                const classStart = timeToMinutes(o.start_time);
                const classEnd = timeToMinutes(o.end_time);
                
                return Math.max(examStart, classStart) < Math.min(examEnd, classEnd);
            });
            
            return !hasOverlappingExam;
        });
        
        const processedAll = [...exams, ...cleanOthers];
        visible = processedAll.filter(match);
        
        const within = visible.filter(e => e.date.slice(0, 7) === fmt(month).slice(0, 7));
        for (const [id, type] of [
            ['ciRegular', 'REGULAR_CLASS'],
            ['ciSubs', 'SUBSTITUTE_CLASS'],
            ['ciExams', 'EXAM'],
            ['ciSpecial', 'SPECIAL_CLASS']
          ])
          $(id).textContent = String(within.filter(e => e.type === type).length);
        renderGrid();
        renderDay();
      }

      function renderGrid() {
        const r = range();
        $('ciMonth').textContent = month.toLocaleDateString('en-PH', {
          month: 'long',
          year: 'numeric'
        });
        const grid = $('ciGrid');
        grid.replaceChildren();
        const byDate = new Map();
        
        const sortedVisible = [...visible].sort((a,b) => timeToMinutes(a.start_time) - timeToMinutes(b.start_time));
        
        for (const e of sortedVisible) {
          if (!byDate.has(e.date)) byDate.set(e.date, []);
          byDate.get(e.date).push(e);
        }
        for (let i = 0; i < r.cells; i++) {
          const d = new Date(...[local(r.start).getFullYear(), local(r.start).getMonth(), local(r.start).getDate() + i]);
          const key = fmt(d);
          const items = byDate.get(key) || [];
          const button = create('button', undefined, 'calendar-day');
          button.type = 'button';
          button.dataset.date = key;
          button.setAttribute('aria-label', `${longDate(key)}, ${items.length} saved events`);
          if (d.getMonth() !== month.getMonth()) button.classList.add('calendar-outside');
          if (key === selected) {
            button.classList.add('calendar-selected');
            button.setAttribute('aria-pressed', 'true');
          } else button.setAttribute('aria-pressed', 'false');
          if (key === fmt(new Date())) button.classList.add('calendar-current');
          const top = create('span', undefined, 'calendar-day-top');
          top.append(create('strong', String(d.getDate())));
          if (items.length) top.append(create('small', `${items.length} event${items.length===1?'':'s'}`));
          button.append(top);
          for (const e of items.slice(0, 3)) {
            const tag = create('span', `${clock(e.start_time)} ${e.subject_code||e.type}`, `calendar-chip calendar-${e.type.toLowerCase()}`);
            tag.title = `${types[e.type]} · ${eventText(e)}`;
            button.append(tag);
          }
          if (items.length > 3) button.append(create('small', `+${items.length-3} more`, 'calendar-more'));
          button.addEventListener('click', () => {
            selected = key;
            if (d.getMonth() !== month.getMonth()) {
              month = new Date(d.getFullYear(), d.getMonth(), 1);
              load();
            } else {
              renderGrid();
              renderDay();
            }
          });
          grid.append(button);
        }
      }

      function eventCard(e) {
        const card = create('article', undefined, `calendar-event calendar-event-${e.type.toLowerCase()}`);
        const head = create('div', undefined, 'calendar-event-head');
        head.append(create('span', types[e.type], 'calendar-event-type'));
        head.append(create('strong', `${clock(e.start_time)} – ${clock(e.end_time)}`));
        card.append(head);
        card.append(create('h4', `${e.subject_code||'Special'} · ${e.subject_title||''}`));
        
        const locP = create('p', '');
        locP.innerHTML = `<i class="fa-solid fa-users"></i> ${e.program_code||'—'} · ${e.section_code||e.class_type||'Individual participants'} · ${e.delivery_mode||'—'}`;
        card.append(locP);
        
        const profP = create('p', '');
        profP.innerHTML = `<i class="fa-solid fa-user-tie"></i> Professor: ${e.teacher_name||'—'}${e.original_teacher_name?' <span style="color:#64748b">(Sub for '+e.original_teacher_name+')</span>':''}`;
        card.append(profP);
        
        const roomP = create('p', '');
        roomP.innerHTML = `<i class="fa-solid fa-door-open"></i> Room: ${e.room_name||'Online / not assigned'}`;
        card.append(roomP);
        
        if (e.note) {
            const noteP = create('p', '', 'calendar-event-note');
            noteP.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> ${e.note}`;
            card.append(noteP);
        }
        return card;
      }

      function renderDay() {
        const list = visible.filter(e => e.date === selected);
        list.sort((a,b) => timeToMinutes(a.start_time) - timeToMinutes(b.start_time));
        
        $('ciSelected').textContent = longDate(selected);$('ciDayCount').textContent = `${list.length} event${list.length===1?'':'s'}`;
        
        const day = $('ciDayEvents');
        day.replaceChildren();
        
        if (!list.length) {
          day.append(create('p', 'No matching saved meetings for this date and filters. This does not confirm that the school is closed.', 'calendar-empty'));
        }
        for (const e of list) {
          day.append(eventCard(e));
        }
      }

      // ==============================================
      // OFFICIAL PRINT MODAL LOGIC
      // ==============================================
      function openPrintModal() {
        const list = visible.filter(e => e.date === selected);
        list.sort((a,b) => timeToMinutes(a.start_time) - timeToMinutes(b.start_time));

        if (!list.length) {
            status("No matching events for this date to print.", true);
            return;
        }

        const printContent = $('bcpPrintContent');
        printContent.innerHTML = '';

        let rowsHtml = '';
        list.forEach((e, idx) => {
            rowsHtml += `
                <tr>
                    <td class="text-center">${idx + 1}</td>
                    <td class="text-center">${clock(e.start_time)} – ${clock(e.end_time)}</td>
                    <td><strong>${e.subject_code||'Special'}</strong></td>
                    <td>${e.subject_title||'—'}</td>
                    <td class="text-center">${e.section_code||e.class_type||'—'}</td>
                    <td class="text-center">${e.room_name||'Online/TBA'}</td>
                    <td>${e.teacher_name||'—'}</td>
                    <td class="text-center">${types[e.type]}</td>
                </tr>
            `;
        });

        const periodText = $('ciPeriod').selectedOptions[0]?.textContent || '—';
        const programText = $('ciProgram').selectedOptions[0]?.textContent || 'All Programs';
        const teacherText = $('ciTeacher').selectedOptions[0]?.textContent || 'All Faculty';

        const sectionWrapper = document.createElement('section');
        sectionWrapper.className = 'print-section';
        sectionWrapper.innerHTML = `
            <div class="print-official-header">
                <img src="../../assets/images/BCP_LOGO.png" alt="BCP Logo" class="print-logo">
                <div class="print-school-name">BESTLINK COLLEGE OF THE PHILIPPINES</div>
                <div class="print-doc-title">Daily Calendar & Event Schedule</div>
            </div>
            <div class="print-section-info">
                <div class="print-info-grid">
                    <div><strong>Date:</strong> ${longDate(selected)}</div>
                    <div><strong>Program:</strong> ${programText}</div>
                    <div><strong>Period:</strong> ${periodText}</div>
                    <div><strong>Faculty:</strong> ${teacherText}</div>
                </div>
            </div>
            <div class="print-table-wrap">
                <table class="print-schedule-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Time</th>
                            <th>Code</th>
                            <th>Description</th>
                            <th>Section</th>
                            <th>Room</th>
                            <th>Instructor</th>
                            <th>Event Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
            </div>
            <div class="print-signatures">
                <div class="sig-block"><p>Prepared by:</p><div class="sig-line"></div><p class="sig-title">Scheduling Administrator</p></div>
                <div class="sig-block"><p>Checked by:</p><div class="sig-line"></div><p class="sig-title">Program Head</p></div>
                <div class="sig-block"><p>Approved by:</p><div class="sig-line"></div><p class="sig-title">Authorized School Official</p></div>
            </div>
        `;

        printContent.appendChild(sectionWrapper);
        $('bcpPrintModal').hidden = false;
        document.body.classList.add("bcp-modal-open");
      }

      function closePrintModal() {
        $('bcpPrintModal').hidden = true;
        document.body.classList.remove("bcp-modal-open");
      }

      $('bcpPrintCloseBtn')?.addEventListener('click', closePrintModal);$('bcpPrintCancelBtn')?.addEventListener('click', closePrintModal);
      $('bcpPrintModalBackdrop')?.addEventListener('click', closePrintModal);$('bcpPrintConfirmBtn')?.addEventListener('click', () => window.print());

      document.addEventListener("keydown", event => {
        if (!$('bcpPrintModal').hidden && event.key === "Escape") {
            closePrintModal();
        }
      });
      // ==============================================


      async function load(notifyUser = false) {
        const token = ++loading;
        status('Loading saved meetings…');
        $('ciRefresh').disabled = true;
        $('ciPrint').disabled = true;
        const r = range();
        try {
          const body = await api({
            action: 'events',
            period_id: $('ciPeriod').value,
            program_id: $('ciProgram').value,
            teacher_id: $('ciTeacher').value,
            start_date: r.start,
            end_date: r.end
          });
          if (token !== loading) return;
          all = body.events || [];
          effective();
          const warning = (body.warnings || []).join(' ');
          status(`Loaded ${body.event_count} saved event occurrences. ${warning}`);
          if (notifyUser) {
            window.BCPNotifications?.notify({
              type: (body.warnings || []).length ? 'warning' : 'success',
              title: 'Calendar records refreshed',
              message: `${body.event_count} saved event occurrence(s) loaded for the visible calendar range.${(body.warnings || []).length ? ` ${(body.warnings || []).length} warning(s) reported.` : ''}`,
              url: `${window.location.pathname}${window.location.search}`
            });
          }
        } catch (err) {
          if (token !== loading) return;
          all = [];
          effective();
          status(err.message, true);
        } finally {
          if (token === loading) {
            $('ciRefresh').disabled = false;
            $('ciPrint').disabled = false;
          }
        }
      }

      function teacherOptions() {
        const select = $('ciTeacher'),
          old = select.value;
        select.replaceChildren();
        option(select, 0, 'All faculty / proctors');
        for (const t of teachers) {
          option(select, t.teacher_id, `${t.teacher_name} (#${t.teacher_id})`);
        }
        if ([...select.options].some(o => o.value === old)) select.value = old;
      }
      async function init() {
        try {
          const c = await api({
            action: 'catalog'
          });
          periods = c.periods || [];
          programs = c.programs || [];
          teachers = c.teachers || [];
          const p = $('ciPeriod'),
            pr = $('ciProgram');
          p.replaceChildren();
          pr.replaceChildren();
          for (const row of periods) option(p, row.academic_period_id, `${row.academic_year} · Semester ${row.semester} (${row.period_status})`);
          option(pr, 0, 'All college programs');
          for (const row of programs) option(pr, row.program_id, `${row.program_code} · ${row.program_name}`);
          if (!periods.length) {
            status('No DEMO academic period exists; the calendar cannot load.', true);
            return;
          }
          p.disabled = false;
          pr.disabled = false;
          $('ciTeacher').disabled = false;
          teacherOptions();
          p.addEventListener('change', load);
          pr.addEventListener('change', () => {
            teacherOptions();
            load();
          });
          $('ciTeacher').addEventListener('change', load);$('ciKind').addEventListener('change', effective);
          $('ciSearch').addEventListener('input', effective);$('ciPrev').addEventListener('click', () => {
            month = new Date(month.getFullYear(), month.getMonth() - 1, 1);
            selected = fmt(month);
            load();
          });
          $('ciNext').addEventListener('click', () => {
            month = new Date(month.getFullYear(), month.getMonth() + 1, 1);
            selected = fmt(month);
            load();
          });
          $('ciToday').addEventListener('click', () => {
            selected = fmt(new Date());
            month = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
            load();
          });
          $('ciRefresh').addEventListener('click', () => load(true));$('ciPrint').addEventListener('click', openPrintModal);

          document.addEventListener('keydown', e => {
            if (e.altKey && !e.ctrlKey && !e.metaKey && e.key === 'ArrowLeft') {
              $('ciPrev').click();
              e.preventDefault();
            }
            if (e.altKey && !e.ctrlKey && !e.metaKey && e.key === 'ArrowRight') {
              $('ciNext').click();
              e.preventDefault();
            }
          });
          await load();
        } catch (err) {
          status(err.message, true);
        }
      }
      init();
    })();
  </script>
</body>

</html>
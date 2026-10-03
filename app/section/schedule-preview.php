<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/auth.php';

authRequire(false, ['ADMIN', 'SCHEDULER']);

function previewEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$role = (string) ($_SESSION['auth_role'] ?? 'SCHEDULER');
$username = trim((string) ($_SESSION['auth_username'] ?? 'Scheduler'));
$initial = strtoupper(substr($username !== '' ? $username : 'S', 0, 1));
$dashboardDate = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Class Timetable | BCP</title>
    <link rel="icon" href="../assets/images/BCP_LOGO.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- External CSS -->
    <link rel="stylesheet" href="../assets/css/schedule-preview.css">
</head>

<body class="bcp-schedule-preview-page">

    <!-- SIDEBAR INJECTED HERE -->
    <?php
    $ACTIVE_NAV = 'schedule_generator';
    require_once __DIR__ . '/../includes/sidebar.php';
    ?>

    <div class="main">
        <!-- ============================================================
         TOP BAR COMPONENT
         ============================================================ -->
        <div class="topbar">
            <button class="hamburger" id="hamburgerBtn" aria-label="Toggle sidebar" title="Toggle Sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
            <span class="topbar-spacer"></span>
            <div class="topbar-right">
                <span class="role-badge">
                    <i class="fa-solid fa-user-tie role-badge__icon" aria-hidden="true"></i>
                    <?= previewEsc($role) ?>
                </span>
                <a href="../auth/account.php" class="avatar" title="Account Settings" aria-label="Open account settings">
                    <?= previewEsc($initial) ?>
                </a>
            </div>
        </div>

        <!-- ============================================================
         TIMETABLE PREVIEW CONTENT
         ============================================================ -->
        <main class="content bcp-preview">
            <div class="bcp-preview__container">

                <header class="bcp-preview__header">
                    <div class="bcp-preview__title-group">
                        <span class="bcp-preview__eyebrow"><span class="bcp-preview__eyebrow-dot"></span> BCP CLASS SCHEDULING SYSTEM</span>
                        <h1>Class Timetable<span class="bcp-preview__title-dot">.</span></h1>
                        <p id="bcpPeriodDescription">Choose a program and academic period.</p>
                    </div>
                    <div class="bcp-preview__badge" title="This module is currently in testing mode">DEMO ENVIRONMENT</div>
                </header>

                <!-- SELECTORS -->
                <section class="bcp-preview__panel bcp-preview__panel--filters" aria-label="Timetable selection">
                    <div class="bcp-preview__selector-grid">
                        <div class="bcp-preview__field">
                            <label for="bcpPeriodSelect">Academic year & semester</label>
                            <select id="bcpPeriodSelect" disabled>
                                <option value="">Loading periods…</option>
                            </select>
                        </div>
                        <div class="bcp-preview__field">
                            <label for="bcpProgramSelect">Program</label>
                            <select id="bcpProgramSelect" disabled>
                                <option value="">Loading programs…</option>
                            </select>
                        </div>
                        <div class="bcp-preview__field-action">
                            <button type="button" id="bcpRefreshButton" class="bcp-preview__btn-secondary" title="Reload academic programs" disabled>
                                <i class="fa-solid fa-rotate-right"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <p id="bcpProgramNote" class="bcp-preview__helper">Checking available schedules…</p>
                </section>

                <!-- ACTIONS -->
                <section class="bcp-preview__panel bcp-preview__panel--actions">
                    <div class="bcp-preview__toolbar">
                        <div class="bcp-preview__toolbar-text">
                            <h2 id="bcpActionTitle">Timetable Workspace</h2>
                            <p id="bcpActionDescription">Choose a program to view a saved timetable or generate a DEMO preview.</p>
                        </div>
                        <div class="bcp-preview__btn-group">
                            <button type="button" id="bcpRegenerateButton" class="bcp-preview__btn-tertiary" title="Create a new preview without overriding the active batch" hidden disabled>
                                <i class="fa-solid fa-code-compare"></i> Regenerate Preview
                            </button>
                            <button type="button" id="bcpGenerateButton" class="bcp-preview__btn-primary" title="Run the automated scheduling engine" hidden disabled>
                                <i class="fa-solid fa-wand-magic-sparkles"></i> Generate Schedule
                            </button>
                            <button type="button" id="bcpSaveButton" class="bcp-preview__btn-primary" title="Commit this timetable to the database" hidden disabled>
                                <i class="fa-solid fa-floppy-disk"></i> Save DEMO Schedule
                            </button>
                            <button type="button" id="bcpReplaceButton" class="bcp-preview__btn-primary bcp-preview__btn-warning" title="Overwrite the currently active batch" hidden disabled>
                                <i class="fa-solid fa-triangle-exclamation"></i> Confirm &amp; Replace DEMO
                            </button>
                        </div>
                    </div>

                    <div id="bcpGenerationStatus" class="bcp-preview__status" role="status" aria-live="polite">
                        <i class="fa-solid fa-circle-info bcp-preview__status-icon"></i>
                        <span>Loading program catalog…</span>
                    </div>
                </section>

                <!-- SUMMARY -->
                <section id="bcpSummary" class="bcp-preview__metrics" aria-label="Schedule Metrics" hidden>
                    <article class="bcp-preview__metric">
                        <div class="bcp-preview__metric-icon"><i class="fa-solid fa-layer-group"></i></div>
                        <span>Sections</span>
                        <strong id="bcpSectionCount">—</strong>
                    </article>
                    <article class="bcp-preview__metric">
                        <div class="bcp-preview__metric-icon"><i class="fa-solid fa-users-viewfinder"></i></div>
                        <span>Class meetings</span>
                        <strong id="bcpMeetingCount">—</strong>
                    </article>
                    <article class="bcp-preview__metric">
                        <div class="bcp-preview__metric-icon"><i class="fa-solid fa-shield-halved"></i></div>
                        <span>Timetable status</span>
                        <strong id="bcpAuditStatus" class="bcp-status-badge">—</strong>
                    </article>
                    <article class="bcp-preview__metric">
                        <div class="bcp-preview__metric-icon"><i class="fa-solid fa-stopwatch"></i></div>
                        <span>Solving time / batch</span>
                        <strong id="bcpSolveTime">—</strong>
                    </article>
                </section>

                <!-- COMPARISON -->
                <section id="bcpReplacementComparison" class="bcp-preview__panel bcp-preview__panel--warning-alert" aria-label="Replacement Warning" hidden>
                    <div class="bcp-preview__warning-header">
                        <i class="fa-solid fa-scale-balanced bcp-preview__warning-icon"></i>
                        <h2>Replacement comparison · BSIT Years 1–3 Online gaps</h2>
                    </div>
                    <p id="bcpReplacementGapComparison" class="bcp-preview__helper-strong"></p>
                    <p class="bcp-preview__helper">Existing ACTIVE batch stays unchanged until you confirm and the final audit and database transaction succeed. A new preview may change teachers and times; saved REGULAR/CLUSTER semester rooms remain fixed.</p>
                </section>

                <!-- FILTER & PRINT CONTROLS -->
                <section id="bcpFilterPanel" class="bcp-preview__panel bcp-preview__panel--transparent" aria-label="Results filter" hidden>
                    <div class="bcp-preview__filter-bar">
                        <div class="bcp-preview__filter-info">
                            <h2>Section Timetables</h2>
                            <p>Face-to-Face and Online classes are cleanly unified per subject.</p>
                        </div>
                        <div class="bcp-preview__filter-actions">

                            <!-- DROPDOWN FILTERS -->
                            <div class="bcp-preview__field-inline">
                                <select id="bcpYearFilter" class="bcp-inline-select">
                                    <option value="ALL">All Year Levels</option>
                                    <option value="1">1st Year</option>
                                    <option value="2">2nd Year</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                            </div>
                            <div class="bcp-preview__field-inline">
                                <select id="bcpTypeFilter" class="bcp-inline-select">
                                    <option value="ALL">All Section Types</option>
                                    <option value="REGULAR">Regular</option>
                                    <option value="CLUSTER">Cluster</option>
                                    <option value="MAJOR">Major</option>
                                </select>
                            </div>

                            <!-- SEARCH BAR WITH AUTOCOMPLETE -->
                            <div class="bcp-preview__search">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="search" id="bcpSectionSearch" placeholder="Search section or subject..." aria-label="Search section timetable" autocomplete="off">
                                <div id="bcpSearchSuggestions" class="bcp-autocomplete-dropdown" hidden></div>
                            </div>

                            <button type="button" id="bcpExpandAllBtn" class="bcp-preview__btn-tertiary" title="Expand all section accordions">Expand All</button>
                            <button type="button" id="bcpCollapseAllBtn" class="bcp-preview__btn-tertiary" title="Collapse all section accordions">Collapse All</button>
                            <button type="button" id="bcpPrintTriggerBtn" class="bcp-preview__btn-secondary" title="Open official print preview format (Ctrl+P support)">
                                <i class="fa-solid fa-print"></i> Print Report
                            </button>
                        </div>
                    </div>
                </section>

                <!-- RESULTS LIST (Accordion Container) -->
                <div id="bcpTimetableResults" class="bcp-preview__results"></div>

                <!-- EMPTY STATE -->
                <div id="bcpEmptyState" class="bcp-preview__empty" aria-live="polite">
                    <div class="bcp-preview__empty-icon"><i class="fa-regular fa-calendar-xmark"></i></div>
                    <h2>Select a program</h2>
                    <p id="bcpEmptyMessage">Saved schedules will appear here. Programs with complete DEMO scheduling inputs can be generated and reviewed here.</p>
                </div>
            </div>
        </main>

        <!-- ============================================================
         PRINT / REPORT PREVIEW MODAL
         ============================================================ -->
        <div id="bcpPrintModal"
            class="bcp-preview-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="bcpPrintModalTitle"
            aria-describedby="bcpPrintModalDescription"
            hidden>
            <div class="bcp-preview-modal__backdrop" data-bcp-modal-close aria-hidden="true"></div>
            <div class="bcp-preview-modal__content" tabindex="-1">
                <div class="bcp-preview-modal__header">
                    <div>
                        <h2 id="bcpPrintModalTitle"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Official Report Preview</h2>
                        <p id="bcpPrintModalDescription">Review the official layout before generating the PDF or printing. Each section prints on a new sheet.</p>
                    </div>
                    <button type="button" id="bcpPrintCloseBtn" class="bcp-preview-modal__close" aria-label="Close report preview" title="Close Modal (Esc)">&times;</button>
                </div>

                <div id="bcpPrintableArea" class="bcp-print-document">
                    <div id="bcpPrintContent" class="bcp-print-body">
                        <!-- Official dedicated print sections are dynamically generated here -->
                    </div>
                </div>

                <div class="bcp-preview-modal__footer">
                    <button type="button" id="bcpPrintCancelBtn" class="bcp-preview__btn-secondary">Cancel</button>
                    <button type="button" id="bcpPrintConfirmBtn" class="bcp-preview__btn-primary"><i class="fa-solid fa-print" aria-hidden="true"></i> Print Document</button>
                </div>
            </div>
        </div>

        <!-- ============================================================
         FOOTER COMPONENT
         ============================================================ -->
        <div class="footer">
            Scheduling System &copy; <?= $dashboardDate->format('Y') ?> Bestlink College of the Philippines
        </div>
    </div>

    <script>
        (() => {
            "use strict";

            const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
            const el = id => document.getElementById(id);
            const periodSelect = el("bcpPeriodSelect");
            const programSelect = el("bcpProgramSelect");
            const refreshButton = el("bcpRefreshButton");
            const generateButton = el("bcpGenerateButton");
            const saveButton = el("bcpSaveButton");
            const regenerateButton = el("bcpRegenerateButton");
            const replaceButton = el("bcpReplaceButton");
            const searchInput = el("bcpSectionSearch");
            const searchSuggestions = el("bcpSearchSuggestions");

            const yearFilterSelect = el("bcpYearFilter");
            const typeFilterSelect = el("bcpTypeFilter");

            const expandAllBtn = el("bcpExpandAllBtn");
            const collapseAllBtn = el("bcpCollapseAllBtn");
            const results = el("bcpTimetableResults");

            let catalog = null;
            let assignments = [];
            let saveToken = null;
            let replaceToken = null;
            let originalBatch = null;
            let originalAssignments = [];
            let busy = false;
            let requestVersion = 0;
            let selectedProgram = null;

            function node(tag, className = "", value = undefined) {
                const e = document.createElement(tag);
                if (className) e.className = className;
                if (value !== undefined && value !== null) e.innerHTML = String(value);
                return e;
            }

            /* --- PREMIUM CUSTOM SELECT DROPDOWN LOGIC --- */
            function upgradeSelects() {
                document.querySelectorAll('.bcp-preview__field select, .bcp-inline-select').forEach(select => {
                    select.style.display = 'none';

                    const wrapper = node("div", "bcp-custom-select-wrapper");
                    select.parentNode.insertBefore(wrapper, select);
                    wrapper.appendChild(select);

                    const trigger = node("div", "bcp-custom-select-trigger");
                    const triggerText = node("span", "bcp-custom-select-text");
                    const arrow = node("i", "fa-solid fa-chevron-down bcp-custom-select-arrow");
                    trigger.append(triggerText, arrow);

                    const optionsList = node("div", "bcp-custom-select-options");
                    wrapper.append(trigger, optionsList);

                    function sync() {
                        optionsList.innerHTML = '';
                        wrapper.classList.toggle('is-disabled', select.disabled);

                        if (select.options.length === 0) {
                            triggerText.textContent = "Loading...";
                            return;
                        }

                        let selectedLabel = "";
                        Array.from(select.options).forEach(opt => {
                            if (opt.selected) selectedLabel = opt.text;
                            const item = node("div", "bcp-custom-select-option", opt.text);
                            if (opt.selected) item.classList.add('is-selected');

                            item.addEventListener('click', (e) => {
                                e.stopPropagation();
                                select.value = opt.value;
                                select.dispatchEvent(new Event('change'));
                                closeAllCustomSelects();
                            });
                            optionsList.appendChild(item);
                        });
                        triggerText.textContent = selectedLabel || "Select an option";
                    }

                    const observer = new MutationObserver(sync);
                    observer.observe(select, {
                        childList: true,
                        attributes: true,
                        attributeFilter: ['disabled']
                    });
                    select.addEventListener('change', sync);

                    trigger.addEventListener('click', (e) => {
                        if (select.disabled) return;
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
                document.querySelectorAll('.bcp-custom-select-options').forEach(el => el.classList.remove('is-open'));
                document.querySelectorAll('.bcp-custom-select-trigger').forEach(el => el.classList.remove('is-active'));
            }
            document.addEventListener('click', closeAllCustomSelects);
            /* ------------------------------------------- */

            function setStatus(message, type = "info") {
                const status = el("bcpGenerationStatus");
                const iconMap = {
                    success: ["fa-circle-check"],
                    error: ["fa-triangle-exclamation"],
                    loading: ["fa-circle-notch", "fa-spin"],
                    info: ["fa-circle-info"]
                };
                const icon = node("i", "fa-solid bcp-preview__status-icon");
                for (const className of (iconMap[type] || iconMap.info)) icon.classList.add(className);
                icon.setAttribute("aria-hidden", "true");

                const text = node("span", "", message);
                status.replaceChildren(icon, text);
                status.dataset.status = type;
                status.setAttribute("role", type === "error" ? "alert" : "status");
                status.setAttribute("aria-live", type === "error" ? "assertive" : "polite");
            }

            function readableError(error, fallback) {
                console.error("BCP schedule preview request failed:", error);
                const message = String(error?.message || "").trim();
                const technicalPattern = /HTTP\s*\d+|SQLSTATE|Undefined\s+(index|variable)|NullPointerException|invalid JSON/i;
                return (!message || technicalPattern.test(message)) ? fallback : message;
            }

            function setEmpty(message) {
                el("bcpEmptyMessage").textContent = message;
                el("bcpEmptyState").hidden = false;
            }

            function clearPreview() {
                assignments = [];
                results.replaceChildren();
                searchInput.value = "";
                if (searchSuggestions) searchSuggestions.hidden = true;
                el("bcpSummary").hidden = true;
                el("bcpFilterPanel").hidden = true;
                el("bcpEmptyState").hidden = true;
                saveToken = null;
                replaceToken = null;
                originalBatch = null;
                originalAssignments = [];
                el("bcpReplacementComparison").hidden = true;
                regenerateButton.hidden = true;
                regenerateButton.disabled = true;
                replaceButton.hidden = true;
                replaceButton.disabled = true;
                saveButton.hidden = true;
                saveButton.disabled = true;
                generateButton.hidden = true;
                generateButton.disabled = true;
            }

            function setBusy(value) {
                busy = value;
                periodSelect.disabled = value || !catalog?.periods?.length;
                programSelect.disabled = value || !catalog?.programs?.length;
                refreshButton.disabled = value;
                generateButton.disabled = value || !selectedProgram?.can_generate_demo;
                saveButton.disabled = value || !saveToken;
                regenerateButton.disabled = value || !originalBatch || selectedProgram?.program_code !== "BSIT";
                replaceButton.disabled = value || !replaceToken;
                document.querySelector(".bcp-preview")?.setAttribute("aria-busy", value ? "true" : "false");
                results.setAttribute("aria-busy", value ? "true" : "false");
            }

            async function getJson(url, options = undefined) {
                const requestOptions = {
                    cache: "no-store",
                    credentials: "same-origin",
                    ...options,
                    headers: {
                        "Accept": "application/json",
                        ...(options?.headers || {})
                    }
                };
                const response = await fetch(url, requestOptions);
                let data;
                try {
                    data = await response.json();
                } catch {
                    throw new Error("The server returned an unreadable response.");
                }
                if (!response.ok || data.success !== true) {
                    const message = (Array.isArray(data.audit?.errors) && data.audit.errors.length) ? data.audit.errors.slice(0, 3).join(" | ") : (data.message || data.status || "The request could not be completed.");
                    throw new Error(message);
                }
                return data;
            }

            function fillSelect(select, values, makeValue, makeLabel, selectedValue) {
                select.replaceChildren();
                for (const value of values) {
                    const option = document.createElement("option");
                    option.textContent = makeLabel(value);
                    option.value = String(makeValue(value));
                    select.appendChild(option);
                }
                if (selectedValue !== undefined && values.length) select.value = String(selectedValue);
            }

            function selectedPeriod() {
                return catalog?.periods.find(p => String(p.academic_period_id) === periodSelect.value) || null;
            }

            function program() {
                return catalog?.programs.find(p => p.program_code === programSelect.value) || null;
            }

            function updateHeading() {
                const p = selectedPeriod();
                const prog = program();
                el("bcpPeriodDescription").textContent = p ? `${prog?.program_code || "Select program"} · AY ${p.academic_year} · Semester ${p.semester}` : "Choose a program and academic period.";
            }

            function showSummary(count, meetings, state, reference) {
                el("bcpSectionCount").textContent = String(count);
                el("bcpMeetingCount").textContent = String(meetings);
                el("bcpAuditStatus").textContent = state;
                el("bcpSolveTime").textContent = reference;
                el("bcpSummary").hidden = false;
            }

            function formatTime(time) {
                if (!time || time === '—') return '—';
                const [h, m] = String(time).split(":").map(Number);
                if (!Number.isFinite(h) || !Number.isFinite(m)) return String(time);
                return `${h % 12 || 12}:${String(m).padStart(2, "0")} ${h >= 12 ? "PM" : "AM"}`;
            }

            function getYearSuffix(yearLvl) {
                const y = parseInt(yearLvl);
                if (isNaN(y)) return "Unknown Year";
                if (y === 1) return "1st Year";
                if (y === 2) return "2nd Year";
                if (y === 3) return "3rd Year";
                if (y === 4) return "4th Year";
                return y + "th Year";
            }

            // ==============================================
            // ANIMATED ACCORDION TOGGLE
            // ==============================================
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

            expandAllBtn?.addEventListener('click', () => {
                document.querySelectorAll('.bcp-accordion__header').forEach(hdr => {
                    const body = hdr.nextElementSibling;
                    const icon = hdr.querySelector('.bcp-accordion__icon');
                    toggleAccordion(hdr, body, icon, true);
                });
            });

            collapseAllBtn?.addEventListener('click', () => {
                document.querySelectorAll('.bcp-accordion__header').forEach(hdr => {
                    const body = hdr.nextElementSibling;
                    const icon = hdr.querySelector('.bcp-accordion__icon');
                    toggleAccordion(hdr, body, icon, false);
                });
            });

            // ==============================================
            // SEARCH SUGGESTIONS & AUTOCOMPLETE
            // ==============================================
            function renderSearchSuggestions() {
                if (!assignments || assignments.length === 0) {
                    searchSuggestions.hidden = true;
                    return;
                }

                const q = searchInput.value.trim().toLowerCase();
                if (!q) {
                    searchSuggestions.hidden = true;
                    searchSuggestions.replaceChildren();
                    return;
                }

                const matchedSections = new Map();
                const matchedSubjects = new Map();

                for (const r of assignments) {
                    const code = String(r.section_code);
                    const subjCode = String(r.subject_code || '');
                    const subjTitle = String(r.subject_title || '');
                    const yLvl = r.year_level || code.charAt(0);
                    const sType = r.section_type || "REGULAR";

                    if (code.toLowerCase().includes(q) && !matchedSections.has(code)) {
                        matchedSections.set(code, {
                            title: `Section ${code}`,
                            meta: `${getYearSuffix(yLvl)} • ${sType}`,
                            val: code,
                            icon: "fa-layer-group"
                        });
                    }

                    if ((subjCode.toLowerCase().includes(q) || subjTitle.toLowerCase().includes(q)) && !matchedSubjects.has(subjCode)) {
                        matchedSubjects.set(subjCode, {
                            title: subjCode,
                            meta: subjTitle,
                            val: subjCode,
                            icon: "fa-book-open"
                        });
                    }
                }

                const combined = [...matchedSections.values(), ...matchedSubjects.values()].slice(0, 8);

                if (combined.length === 0) {
                    searchSuggestions.hidden = true;
                    searchSuggestions.replaceChildren();
                    return;
                }

                searchSuggestions.replaceChildren();

                combined.forEach(item => {
                    const div = document.createElement("div");
                    div.className = "bcp-autocomplete-item";
                    div.setAttribute("role", "button");
                    div.setAttribute("tabindex", "0");
                    div.innerHTML = `
                        <strong><i class="fa-solid ${item.icon}"></i> ${item.title}</strong>
                        <small>${item.meta}</small>
                    `;

                    div.addEventListener("click", () => {
                        searchInput.value = item.val;
                        searchSuggestions.hidden = true;
                        render();
                        if (expandAllBtn) expandAllBtn.click();
                    });

                    searchSuggestions.appendChild(div);
                });

                searchSuggestions.hidden = false;
            }

            function createUnifiedTable(rows) {
                const subjects = new Map();
                for (const r of rows) {
                    if (!subjects.has(r.subject_code)) subjects.set(r.subject_code, {
                        code: r.subject_code,
                        title: r.subject_title,
                        f2f: null,
                        online: null,
                        teacher: '—'
                    });
                    const subj = subjects.get(r.subject_code);
                    if (r.delivery_mode === 'F2F') subj.f2f = r;
                    if (r.delivery_mode === 'ONLINE') subj.online = r;
                    if (r.teacher_name && r.teacher_name !== '—') subj.teacher = r.teacher_name;
                }

                const wrap = node("div", "bcp-timetable__table-wrap");
                const table = node("table", "bcp-timetable__table");
                const thead = node("thead");
                const tr1 = node("tr");
                const thSubj = node("th", "", "Subject");
                thSubj.rowSpan = 2;
                tr1.appendChild(thSubj);
                const thF2F = node("th", "bcp-timetable__th-group bcp-timetable__th-f2f", "FACE-TO-FACE");
                thF2F.colSpan = 3;
                tr1.appendChild(thF2F);
                const thOnline = node("th", "bcp-timetable__th-group bcp-timetable__th-online", "ONLINE");
                thOnline.colSpan = 2;
                tr1.appendChild(thOnline);
                const thTeacher = node("th", "", "Instructor");
                thTeacher.rowSpan = 2;
                tr1.appendChild(thTeacher);

                const tr2 = node("tr");
                ['Day', 'Time', 'Room', 'Day', 'Time'].forEach(text => tr2.appendChild(node("th", "bcp-timetable__th-sub", text)));
                thead.append(tr1, tr2);
                table.appendChild(thead);

                const tbody = node("tbody");

                // SORT BY CHRONOLOGICAL DAY & TIME (Monday -> Saturday)
                const sortedSubjects = [...subjects.values()].sort((a, b) => {
                    const dayA = a.f2f ? a.f2f.day_of_week : (a.online ? a.online.day_of_week : "");
                    const timeA = a.f2f ? a.f2f.start_time : (a.online ? a.online.start_time : "24:00:00");
                    const idxA = DAYS.indexOf(dayA) !== -1 ? DAYS.indexOf(dayA) : 99;

                    const dayB = b.f2f ? b.f2f.day_of_week : (b.online ? b.online.day_of_week : "");
                    const timeB = b.f2f ? b.f2f.start_time : (b.online ? b.online.start_time : "24:00:00");
                    const idxB = DAYS.indexOf(dayB) !== -1 ? DAYS.indexOf(dayB) : 99;

                    if (idxA !== idxB) return idxA - idxB;
                    return timeA.localeCompare(timeB);
                });

                for (const s of sortedSubjects) {
                    const tr = node("tr", "bcp-timetable__row");
                    const tdSubj = node("td", "bcp-timetable__td-subject");
                    tdSubj.innerHTML = `<strong>${s.code}</strong><span>${s.title}</span>`;
                    tr.appendChild(tdSubj);
                    const tdF2FDay = node("td", "bcp-timetable__td-day", s.f2f ? s.f2f.day_of_week : "—");
                    const tdF2FTime = node("td", "bcp-timetable__td-time", s.f2f ? `${formatTime(s.f2f.start_time)} – ${formatTime(s.f2f.end_time)}` : "—");
                    const tdF2FRoom = node("td", "bcp-timetable__td-room", s.f2f ? (s.f2f.room_name || '—') : "—");
                    const tdOnlDay = node("td", "bcp-timetable__td-day bcp-timetable__td-onl-day", s.online ? s.online.day_of_week : "—");
                    const tdOnlTime = node("td", "bcp-timetable__td-time", s.online ? `${formatTime(s.online.start_time)} – ${formatTime(s.online.end_time)}` : "—");

                    tdF2FDay.setAttribute("data-label", "F2F Day");
                    tdF2FTime.setAttribute("data-label", "F2F Time");
                    tdF2FRoom.setAttribute("data-label", "F2F Room");
                    tdOnlDay.setAttribute("data-label", "Online Day");
                    tdOnlTime.setAttribute("data-label", "Online Time");

                    if (!s.f2f) {
                        tdF2FDay.classList.add("bcp-timetable__td-empty");
                        tdF2FTime.classList.add("bcp-timetable__td-empty");
                        tdF2FRoom.classList.add("bcp-timetable__td-empty");
                    }
                    if (!s.online) {
                        tdOnlDay.classList.add("bcp-timetable__td-empty");
                        tdOnlTime.classList.add("bcp-timetable__td-empty");
                    }

                    tr.append(tdF2FDay, tdF2FTime, tdF2FRoom, tdOnlDay, tdOnlTime);
                    const tdTeacher = node("td", "bcp-timetable__td-teacher", s.teacher);
                    tdTeacher.setAttribute("data-label", "Instructor");
                    tr.appendChild(tdTeacher);
                    tbody.appendChild(tr);
                }
                table.appendChild(tbody);
                wrap.appendChild(table);
                return wrap;
            }

            function render() {
                results.replaceChildren();
                const groups = new Map();

                const q = searchInput.value.trim().toLowerCase();
                const yf = yearFilterSelect.value;
                const tf = typeFilterSelect.value;

                for (const r of assignments) {
                    const code = String(r.section_code);
                    const yLvl = r.year_level || String(code).charAt(0);
                    const sType = r.section_type || "REGULAR";
                    const subjCode = String(r.subject_code || '').toLowerCase();
                    const subjTitle = String(r.subject_title || '').toLowerCase();

                    if (yf !== "ALL" && String(yLvl) !== yf) continue;
                    if (tf !== "ALL" && String(sType).toUpperCase() !== String(tf).toUpperCase()) continue;

                    const matchesQuery = !q ||
                        code.toLowerCase().includes(q) ||
                        subjCode.includes(q) ||
                        subjTitle.includes(q);

                    if (!matchesQuery) continue;

                    if (!groups.has(code)) groups.set(code, []);
                    groups.get(code).push(r);
                }

                if (groups.size === 0) {
                    results.appendChild(node("p", "bcp-preview__no-results", "No matching sections or subjects found."));
                    return;
                }

                for (const code of [...groups.keys()].sort()) {
                    const rows = groups.get(code);
                    const uniqueSubjects = new Set(rows.map(r => r.subject_code)).size;

                    const yearLvl = rows[0].year_level || String(code).charAt(0);
                    const secType = rows[0].section_type || "REGULAR";

                    const accItem = node("div", "bcp-accordion__item");
                    const accHeader = node("div", "bcp-accordion__header");
                    accHeader.setAttribute("tabindex", "0");
                    accHeader.setAttribute("role", "button");
                    accHeader.setAttribute("aria-expanded", "false");

                    const titleGroup = node("div", "bcp-accordion__title");
                    titleGroup.innerHTML = `<h3>${code}</h3><p>${getYearSuffix(yearLvl)} • ${secType} • ${uniqueSubjects} Subjects</p>`;

                    const accIcon = node("i", "fa-solid fa-chevron-down bcp-accordion__icon");
                    accHeader.append(titleGroup, accIcon);

                    const accBody = node("div", "bcp-accordion__body");
                    const accInner = node("div", "bcp-accordion__inner");
                    const accContent = node("div", "bcp-accordion__content");

                    accContent.appendChild(createUnifiedTable(rows));
                    accInner.appendChild(accContent);
                    accBody.appendChild(accInner);

                    accItem.append(accHeader, accBody);

                    accHeader.addEventListener("click", () => toggleAccordion(accHeader, accBody, accIcon));
                    accHeader.addEventListener("keydown", (e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            toggleAccordion(accHeader, accBody, accIcon);
                        }
                    });

                    results.appendChild(accItem);
                }
            }

            function showAssignments(rows) {
                assignments = rows;
                el("bcpEmptyState").hidden = true;
                el("bcpFilterPanel").hidden = false;
                render();
            }

            async function loadSelection() {
                if (busy) return;
                const version = ++requestVersion;
                clearPreview();
                selectedProgram = program();
                updateHeading();
                if (!selectedProgram || !selectedPeriod()) {
                    setStatus("No program or academic period available.", "error");
                    setEmpty("Choose an available program and academic period.");
                    return;
                }
                el("bcpActionTitle").textContent = `${selectedProgram.program_code} Timetable`;
                el("bcpActionDescription").textContent = "Checking saved timetable for the selected program and academic period.";
                setStatus("Checking saved timetable…", "loading");
                setBusy(true);
                try {
                    const p = selectedPeriod();
                    // FIXED: URLSearchParams
                    const query = new URLSearchParams({
                        program: selectedProgram.program_code,
                        academic_year: p.academic_year,
                        semester: String(p.semester)
                    });
                    const saved = await getJson(`../api/saved-schedule.php?${query}`);
                    if (version !== requestVersion) return;
                    if (saved.status === "SAVED_SCHEDULE_LOADED") {
                        if (!Array.isArray(saved.assignments) || saved.assignments.length !== saved.saved_meetings) throw new Error("Saved timetable is incomplete. Please review the database.");
                        showAssignments(saved.assignments);
                        originalBatch = Number(saved.batch.batch_id);
                        originalAssignments = saved.assignments;
                        const replacementAvailable = selectedProgram.program_code === "BSIT" && saved.batch.data_origin === "DEMO" && selectedPeriod()?.period_status === "DEMO";
                        regenerateButton.hidden = !replacementAvailable;
                        showSummary(saved.sections, saved.saved_meetings, "SAVED · DEMO", `Batch #${saved.batch.batch_id}`);
                        el("bcpActionDescription").textContent = replacementAvailable ? "Saved timetable is shown. You can create a replacement preview without changing the ACTIVE batch." : "Saved timetable is shown in read-only mode.";
                        el("bcpProgramNote").textContent = `ACTIVE batch #${saved.batch.batch_id} · ${saved.saved_meetings} stored meetings · read-only view.`;
                        setStatus(`Loaded existing ${selectedProgram.program_code} timetable (batch #${saved.batch.batch_id}).`, "success");
                    } else if (saved.status === "NO_SAVED_SCHEDULE") {
                        const eligible = selectedProgram.can_generate_demo === true;
                        generateButton.hidden = !eligible;
                        el("bcpProgramNote").textContent = eligible ? `${selectedProgram.demo_sections} DEMO sections · ${selectedProgram.program_code} scheduling configuration is available.` : "No saved timetable. Generation is disabled until inputs are ready.";
                        el("bcpActionDescription").textContent = eligible ? `Generate a new ${selectedProgram.program_code} DEMO timetable, review it, then confirm saving.` : "Saved timetable viewing is available. Generation is not configured yet.";
                        setEmpty(eligible ? "Click Generate Schedule to create a new preview." : "No saved timetable.");
                        setStatus(eligible ? "Ready to generate schedule." : "No schedule available.", "info");
                    } else {
                        throw new Error(`Unexpected saved timetable status: ${saved.status}`);
                    }
                } catch (err) {
                    if (version !== requestVersion) return;
                    clearPreview();
                    el("bcpProgramNote").textContent = "Unable to verify saved timetable. Generation is disabled.";
                    setEmpty("Could not verify saved timetable. Refresh and check the API error.");
                    setStatus(readableError(err, "Could not verify the saved timetable. Refresh and try again."), "error");
                    generateButton.hidden = true;
                } finally {
                    if (version === requestVersion) setBusy(false);
                }
            }

            async function loadCatalog(periodId = null, programCode = null, force = false) {
                if (busy && !force) return;
                ++requestVersion;
                clearPreview();
                catalog = null;
                selectedProgram = null;
                setBusy(true);
                setStatus("Loading programs and academic periods…", "loading");
                try {
                    const query = periodId ? `?period_id=${encodeURIComponent(periodId)}` : "";
                    const data = await getJson(`../api/program-catalog.php${query}`);
                    catalog = data;
                    if (!data.periods?.length || !data.programs?.length || !data.selected_period) {
                        setEmpty("No available academic periods or active college programs.");
                        setStatus("There are no selectable programs and academic periods.", "info");
                        return;
                    }
                    fillSelect(periodSelect, data.periods, p => p.academic_period_id, p => `${p.academic_year} · Semester ${p.semester} (${p.period_status})`, data.selected_period.academic_period_id);
                    const desiredCode = data.programs.some(p => p.program_code === programCode) ? programCode : data.programs.some(p => p.program_code === "BSIT") ? "BSIT" : data.programs[0].program_code;
                    fillSelect(programSelect, data.programs, p => p.program_code, p => `${p.program_code} — ${p.program_name}`, desiredCode);
                } catch (err) {
                    setEmpty("Could not load the database program list.");
                    setStatus(readableError(err, "Could not load programs and academic periods. Refresh and try again."), "error");
                    return;
                } finally {
                    setBusy(false);
                }
                await loadSelection();
            }

            async function generateSchedule() {
                if (busy || selectedProgram?.can_generate_demo !== true) return;
                const p = selectedPeriod();
                const code = selectedProgram.program_code;
                clearPreview();
                setBusy(true);
                generateButton.hidden = false;
                generateButton.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin"></i> Generating…`;
                setStatus("Starting the scheduling optimizer…", "loading");
                try {
                    const check = await getJson(`../api/program-catalog.php?period_id=${encodeURIComponent(p.academic_period_id)}`);
                    const current = check.programs.find(value => value.program_code === code);
                    if (!current?.can_generate_demo) throw new Error("This program already has an ACTIVE timetable or is not ready.");
                    const query = new URLSearchParams({
                        program: code,
                        academic_year: p.academic_year,
                        semester: String(p.semester)
                    });
                    const started = await getJson(`../api/generate.php?${query}`);
                    if (started.status !== "SCHEDULE_JOB_QUEUED" || typeof started.job_id !== "string") throw new Error("The scheduling job could not be started.");
                    const jobId = started.job_id;
                    let result = null;
                    let transientFailures = 0;
                    const maxTransientFailures = 12;

                    while (true) {
                        await new Promise(resolve => setTimeout(resolve, 5000));
                        let response;
                        try {
                            response = await fetch(`../api/generate-status.php?job_id=${encodeURIComponent(jobId)}`, {
                                cache: "no-store",
                                credentials: "same-origin",
                                headers: {
                                    "Accept": "application/json"
                                }
                            });
                        } catch (error) {
                            transientFailures++;
                            if (transientFailures > maxTransientFailures) throw new Error("Unable to reach the scheduling status service after repeated retries.");
                            setStatus("The scheduler is still running. Reconnecting to the status service…", "loading");
                            continue;
                        }
                        if (response.status === 502 || response.status === 503 || response.status === 504) {
                            transientFailures++;
                            if (transientFailures > maxTransientFailures) throw new Error("The scheduling status service remained unavailable after repeated retries.");
                            setStatus("OR-Tools is still optimizing. Waiting for the server to become available…", "loading");
                            continue;
                        }
                        let statusObj;
                        try {
                            statusObj = await response.json();
                        } catch {
                            transientFailures++;
                            if (transientFailures > maxTransientFailures) throw new Error("The scheduling status service repeatedly returned an invalid response.");
                            setStatus("OR-Tools is still optimizing. Reconnecting…", "loading");
                            continue;
                        }
                        if (!response.ok || statusObj.success !== true) throw new Error(statusObj.message || statusObj.status || "The scheduling status request failed.");
                        transientFailures = 0;
                        if (statusObj.status === "SCHEDULE_JOB_RUNNING") {
                            setStatus(statusObj.job_status === "QUEUED" ? "Scheduling job is queued…" : "OR-Tools is optimizing the timetable…", "loading");
                            continue;
                        }
                        result = statusObj;
                        break;
                    }
                    if (result.status !== "DEMO_PREVIEW_GENERATED" || result.audit?.passed !== true) throw new Error(result.message || "Generated timetable failed required preview checks.");
                    showAssignments(result.assignments);
                    showSummary(result.sections, `${result.returned_meetings} / ${result.required_meetings}`, "AUDIT_PASSED", `${Number(result.solve_seconds).toFixed(1)}s`);
                    if (result.save_ready_demo === true && typeof result.save_token === "string") {
                        saveToken = result.save_token;
                        saveButton.hidden = false;
                        setStatus("Timetable generated and audited. Review before saving.", "success");
                    }
                    generateButton.hidden = true;
                } catch (err) {
                    clearPreview();
                    setEmpty("Could not generate a new timetable.");
                    setStatus(readableError(err, "Could not generate a timetable. Review the selected inputs and try again."), "error");
                    generateButton.hidden = false;
                } finally {
                    generateButton.innerHTML = `<i class="fa-solid fa-wand-magic-sparkles"></i> Generate Schedule`;
                    setBusy(false);
                }
            }

            async function saveSchedule() {
                if (busy || !saveToken || !selectedProgram) return;
                if (!window.confirm("Save this reviewed DEMO timetable?")) return;
                const token = saveToken;
                const periodId = selectedPeriod()?.academic_period_id;
                const programCode = selectedProgram.program_code;
                setBusy(true);
                saveButton.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin"></i> Saving…`;
                setStatus("Saving the DEMO timetable…", "loading");
                try {
                    const saved = await getJson("../api/save-schedule.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            save_token: token,
                            confirm: true
                        })
                    });
                    if (saved.status !== "DEMO_SCHEDULE_SAVED") throw new Error("Save failed.");
                    saveToken = null;
                    saveButton.hidden = true;
                    await loadCatalog(periodId, programCode, true);
                    setStatus(`Saved DEMO batch #${saved.batch_id} successfully. The ACTIVE saved timetable is now displayed.`, "success");
                } catch (err) {
                    setStatus(readableError(err, "The timetable could not be saved. The preview remains unsaved."), "error");
                } finally {
                    saveButton.innerHTML = `<i class="fa-solid fa-floppy-disk"></i> Save DEMO Schedule`;
                    setBusy(false);
                }
            }

            async function regeneratePreview() {
                if (busy || !originalBatch || selectedProgram?.program_code !== "BSIT") return;
                const p = selectedPeriod();
                const batchId = originalBatch;
                const before = originalAssignments;
                const comparison = el("bcpReplacementComparison");
                replaceToken = null;
                replaceButton.hidden = true;
                comparison.hidden = true;
                setBusy(true);
                regenerateButton.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin"></i> Generating replacement…`;
                setStatus("Generating a read-only replacement preview…", "loading");
                try {
                    const replacement = await getJson("../api/replacement-generate.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            program: "BSIT",
                            academic_year: p.academic_year,
                            semester: p.semester,
                            batch_id: batchId
                        })
                    });
                    if (replacement.status !== "DEMO_PREVIEW_GENERATED") throw new Error("Replacement failed.");
                    replaceToken = replacement.replace_token;
                    showAssignments(replacement.assignments);
                    showSummary(replacement.sections, replacement.returned_meetings, "REPLACEMENT PREVIEW · NOT SAVED", `${Number(replacement.solve_seconds).toFixed(1)}s`);

                    const minute = value => {
                        const [h, m] = String(value).split(":").map(Number);
                        return h * 60 + m;
                    };

                    function onlineGapMinutes(rows) {
                        const groups = new Map();
                        for (const r of rows) {
                            if (r.delivery_mode !== "ONLINE" || r.section_type !== "REGULAR") continue;
                            const year = Number(String(r.section_code)[0]);
                            if (![1, 2, 3].includes(year)) continue;
                            const key = `${r.section_code}|${r.day_of_week}`;
                            if (!groups.has(key)) groups.set(key, []);
                            groups.get(key).push(r);
                        }
                        let total = 0;
                        for (const day of groups.values()) {
                            const start = Math.min(...day.map(r => minute(r.start_time)));
                            const end = Math.max(...day.map(r => minute(r.end_time)));
                            const teaching = day.reduce((n, r) => n + minute(r.end_time) - minute(r.start_time), 0);
                            total += Math.max(0, end - start - teaching);
                        }
                        return total;
                    }
                    const oldGap = onlineGapMinutes(before);
                    const newGap = onlineGapMinutes(replacement.assignments);
                    el("bcpReplacementGapComparison").textContent = `Previous ACTIVE batch #${batchId}: ${oldGap} min total Online gaps. Proposed preview: ${newGap} min. Difference: ${newGap - oldGap} min.`;
                    comparison.hidden = false;
                    replaceButton.hidden = false;
                    setStatus(`Replacement preview audited. Batch #${batchId} remains ACTIVE.`, "success");
                } catch (err) {
                    setStatus(readableError(err, "Could not create a replacement preview. The ACTIVE batch was not changed."), "error");
                } finally {
                    regenerateButton.innerHTML = `<i class="fa-solid fa-code-compare"></i> Regenerate Preview`;
                    setBusy(false);
                }
            }

            async function confirmReplacement() {
                if (busy || !replaceToken || !originalBatch || selectedProgram?.program_code !== "BSIT") return;
                if (!window.confirm(`Replace ACTIVE batch #${originalBatch} with this preview?`)) return;
                const token = replaceToken;
                const periodId = selectedPeriod()?.academic_period_id;
                setBusy(true);
                replaceButton.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin"></i> Replacing…`;
                setStatus("Committing the DEMO replacement…", "loading");
                try {
                    const saved = await getJson("../api/replacement-save.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            replace_token: token,
                            confirm: true
                        })
                    });
                    if (saved.status !== "DEMO_SCHEDULE_REPLACED") throw new Error("Replacement API failed.");
                    replaceToken = null;
                    await loadCatalog(periodId, "BSIT", true);
                    setStatus("Replacement saved successfully. The new ACTIVE BSIT timetable is now displayed.", "success");
                } catch (err) {
                    setStatus(readableError(err, "The replacement could not be saved. The previous ACTIVE batch remains unchanged."), "error");
                } finally {
                    replaceButton.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> Confirm & Replace DEMO`;
                    setBusy(false);
                }
            }

            const printModal = el("bcpPrintModal");
            const printTrigger = el("bcpPrintTriggerBtn");
            let lastFocusedBeforeModal = null;

            function focusableInModal() {
                return [...printModal.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')].filter(item => !item.hidden && item.offsetParent !== null);
            }

            // OFFICIAL PRINT PREVIEW GENERATION
            function buildPrintRows(rows) {
                const subjects = new Map();
                for (const r of rows) {
                    if (!subjects.has(r.subject_code)) subjects.set(r.subject_code, {
                        code: r.subject_code,
                        title: r.subject_title,
                        f2f: null,
                        online: null,
                        teacher: '—'
                    });
                    const subj = subjects.get(r.subject_code);
                    if (r.delivery_mode === 'F2F') subj.f2f = r;
                    if (r.delivery_mode === 'ONLINE') subj.online = r;
                    if (r.teacher_name && r.teacher_name !== '—') subj.teacher = r.teacher_name;
                }

                let html = '';
                let index = 1;

                // SORT BY CHRONOLOGICAL DAY & TIME FOR PRINTING
                const sorted = [...subjects.values()].sort((a, b) => {
                    const dayA = a.f2f ? a.f2f.day_of_week : (a.online ? a.online.day_of_week : "");
                    const timeA = a.f2f ? a.f2f.start_time : (a.online ? a.online.start_time : "24:00:00");
                    const idxA = DAYS.indexOf(dayA) !== -1 ? DAYS.indexOf(dayA) : 99;

                    const dayB = b.f2f ? b.f2f.day_of_week : (b.online ? b.online.day_of_week : "");
                    const timeB = b.f2f ? b.f2f.start_time : (b.online ? b.online.start_time : "24:00:00");
                    const idxB = DAYS.indexOf(dayB) !== -1 ? DAYS.indexOf(dayB) : 99;

                    if (idxA !== idxB) return idxA - idxB;
                    return timeA.localeCompare(timeB);
                });

                for (const s of sorted) {
                    const f2fDay = s.f2f ? s.f2f.day_of_week : '—';
                    const f2fTime = s.f2f ? `${formatTime(s.f2f.start_time)} – ${formatTime(s.f2f.end_time)}` : '—';
                    const room = s.f2f ? (s.f2f.room_name || '—') : '—';

                    const onlDay = s.online ? s.online.day_of_week : '—';
                    const onlTime = s.online ? `${formatTime(s.online.start_time)} – ${formatTime(s.online.end_time)}` : '—';

                    html += `
                        <tr>
                            <td class="text-center">${index++}</td>
                            <td><strong>${s.code}</strong></td>
                            <td>${s.title}</td>
                            <td class="text-center">${f2fDay}</td>
                            <td class="text-center">${f2fTime}</td>
                            <td class="text-center">${room}</td>
                            <td class="text-center">${onlDay}</td>
                            <td class="text-center">${onlTime}</td>
                            <td>${s.teacher}</td>
                        </tr>
                    `;
                }
                return html;
            }

            function openPrintModal() {
                if (!assignments || assignments.length === 0) {
                    setStatus("No timetable data available to print.", "info");
                    return;
                }
                const printContent = el("bcpPrintContent");
                printContent.innerHTML = "";

                const groups = new Map();
                const q = searchInput.value.trim().toLowerCase();
                const yf = yearFilterSelect.value;
                const tf = typeFilterSelect.value;

                for (const r of assignments) {
                    const code = String(r.section_code);
                    const yLvl = r.year_level || String(code).charAt(0);
                    const sType = r.section_type || "REGULAR";

                    // Apply filters again for print format
                    if (yf !== "ALL" && String(yLvl) !== yf) continue;
                    if (tf !== "ALL" && String(sType).toUpperCase() !== String(tf).toUpperCase()) continue;

                    // Search matching
                    const subjCode = String(r.subject_code || '').toLowerCase();
                    const subjTitle = String(r.subject_title || '').toLowerCase();
                    const matchesQuery = !q || code.toLowerCase().includes(q) || subjCode.includes(q) || subjTitle.includes(q);

                    if (!matchesQuery) continue;

                    if (!groups.has(code)) groups.set(code, []);
                    groups.get(code).push(r);
                }

                if (groups.size === 0) {
                    setStatus("No matching sections to print.", "info");
                    searchInput.focus();
                    return;
                }

                const p = selectedPeriod();
                const ay = p ? p.academic_year : "—";
                const sem = p ? (p.semester == 1 ? "First Semester" : "Second Semester") : "—";
                const progName = program() ? program().program_name : "—";

                for (const code of [...groups.keys()].sort()) {
                    const rows = groups.get(code);
                    const yearLvl = rows[0].year_level || String(code).charAt(0);

                    const sectionWrapper = document.createElement('section');
                    sectionWrapper.className = 'print-section';

                    sectionWrapper.innerHTML = `
                        <div class="print-official-header">
                            <img src="../assets/images/BCP_LOGO.png" alt="BCP Logo" class="print-logo">
                            <div class="print-school-name">BESTLINK COLLEGE OF THE PHILIPPINES</div>
                            <div class="print-doc-title">Official Class Schedule</div>
                        </div>
                        <div class="print-section-info">
                            <div class="print-info-grid">
                                <div><strong>Program:</strong> ${progName}</div>
                                <div><strong>Section:</strong> ${code}</div>
                                <div><strong>Year Level:</strong> ${getYearSuffix(yearLvl)}</div>
                                <div><strong>Academic Year:</strong> ${ay}</div>
                                <div><strong>Semester:</strong> ${sem}</div>
                            </div>
                        </div>
                        <div class="print-table-wrap">
                            <table class="print-schedule-table">
                                <thead>
                                    <tr>
                                        <th rowspan="2">No.</th>
                                        <th rowspan="2">Subject Code</th>
                                        <th rowspan="2">Description</th>
                                        <th colspan="3" class="th-group th-f2f">FACE-TO-FACE</th>
                                        <th colspan="2" class="th-group th-online">ONLINE</th>
                                        <th rowspan="2">Teacher</th>
                                    </tr>
                                    <tr>
                                        <th>Day</th>
                                        <th>Time</th>
                                        <th>Room</th>
                                        <th>Day</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${buildPrintRows(rows)}
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
                }

                lastFocusedBeforeModal = document.activeElement;
                printModal.hidden = false;
                document.body.classList.add("bcp-modal-open");
                requestAnimationFrame(() => el("bcpPrintCloseBtn")?.focus());
            }

            function closePrintModal() {
                if (!printModal || printModal.hidden) return;
                printModal.hidden = true;
                document.body.classList.remove("bcp-modal-open");
                if (lastFocusedBeforeModal instanceof HTMLElement) lastFocusedBeforeModal.focus();
            }

            printTrigger?.addEventListener("click", openPrintModal);
            el("bcpPrintCloseBtn")?.addEventListener("click", closePrintModal);
            el("bcpPrintCancelBtn")?.addEventListener("click", closePrintModal);
            printModal?.querySelector("[data-bcp-modal-close]")?.addEventListener("click", closePrintModal);
            el("bcpPrintConfirmBtn")?.addEventListener("click", () => window.print());

            document.addEventListener("keydown", event => {
                if (!printModal || printModal.hidden) return;
                if (event.key === "Escape") {
                    event.preventDefault();
                    closePrintModal();
                    return;
                }
                if (event.key.toLowerCase() === 'p' && (event.ctrlKey || event.metaKey)) return;
                if (event.key === "Tab") {
                    const focusable = focusableInModal();
                    if (focusable.length === 0) {
                        event.preventDefault();
                        return;
                    }
                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];
                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                }
            });

            document.addEventListener("keydown", (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'p') {
                    if (printModal && printModal.hidden && assignments.length > 0) {
                        e.preventDefault();
                        openPrintModal();
                    }
                }
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') {
                    if (!el("bcpFilterPanel").hidden) {
                        e.preventDefault();
                        searchInput.focus();
                    }
                }
            });

            periodSelect.addEventListener("change", () => {
                if (!busy) loadCatalog(periodSelect.value, programSelect.value);
            });
            programSelect.addEventListener("change", loadSelection);
            refreshButton.addEventListener("click", () => {
                if (!busy) loadCatalog(periodSelect.value, programSelect.value);
            });
            generateButton.addEventListener("click", generateSchedule);
            saveButton.addEventListener("click", saveSchedule);
            regenerateButton.addEventListener("click", regeneratePreview);
            replaceButton.addEventListener("click", confirmReplacement);

            // Re-bind listeners including search suggestion
            searchInput.addEventListener("input", () => {
                renderSearchSuggestions();
                render();
            });
            searchInput.addEventListener("focus", renderSearchSuggestions);
            document.addEventListener("click", (e) => {
                if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
                    searchSuggestions.hidden = true;
                }
            });
            searchInput.addEventListener("keydown", (e) => {
                if (e.key === "Escape") searchSuggestions.hidden = true;
            });

            yearFilterSelect?.addEventListener("change", render);
            typeFilterSelect?.addEventListener("change", render);

            upgradeSelects();
            loadCatalog();
        })();
    </script>
</body>

</html>
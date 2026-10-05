<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/auth.php';
authRequire(false, ['ADMIN', 'SCHEDULER']);

date_default_timezone_set('Asia/Manila');

function subPageEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$APP_ROOT = '../../';
$ACTIVE_NAV = 'substitute_tracker';
$role = (string)($_SESSION['auth_role'] ?? 'ADMIN');
$username = trim((string)($_SESSION['auth_username'] ?? 'Admin'));
$initial = strtoupper(substr($username !== '' ? $username : 'U', 0, 1));
$generatedAt = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Substitute Requests | BCP</title>
<link rel="icon" href="../../assets/images/BCP_LOGO.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../../assets/css/substitute-assignment-tracker.css">
</head>
<body class="bcp-sub-page">

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
                <?= subPageEsc($role) ?>
            </span>
            <a href="../../auth/account.php" class="avatar" title="Account Settings"><?= subPageEsc($initial) ?></a>
        </div>
    </div>

    <main class="content bcp-sub" id="subApp">
        <div class="bcp-sub__shell">
            <header class="bcp-sub__header">
                <div>
                    <p class="bcp-sub__eyebrow"><span class="bcp-sub__eyebrow-dot"></span> BCP CLASS SCHEDULING SYSTEM</p>
                    <h1>Substitute Requests<span class="bcp-sub__title-dot">.</span></h1>
                    <p>Review incoming faculty requests and assign a conflict-safe substitute. Request creation is outside this module.</p>
                </div>
                <span class="bcp-sub__chip"><i class="fa-solid fa-list-check"></i> REQUEST TO-DO</span>
            </header>

            <section class="bcp-sub__panel">
                <div class="bcp-sub__controls">
                    <div class="bcp-sub__control">
                        <label for="subPeriod">Academic period</label>
                        <select id="subPeriod"></select>
                    </div>

                    <div class="bcp-sub__control">
                        <label for="subProgram">Program</label>
                        <select id="subProgram"></select>
                    </div>

                    <div class="bcp-sub__actions" style="display: flex; gap: 12px;">
                        <button type="button" id="subRefresh" class="bcp-sub__btn-secondary">
                            <i class="fa-solid fa-rotate-right"></i> Refresh
                        </button>
                        <button type="button" id="subPrint" class="bcp-sub__btn-secondary">
                            <i class="fa-solid fa-print"></i> Print
                        </button>
                    </div>
                </div>

                <div class="bcp-sub__status" id="subStatus" role="status" aria-live="polite">
                    <i class="fa-solid fa-circle-notch fa-spin bcp-sub__status-icon"></i>
                    Loading request inbox…
                </div>
            </section>

            <section class="bcp-sub__summary">
                <div class="bcp-sub__summary-card">
                    <span>Pending requests</span>
                    <strong id="metricPendingRequests">—</strong>
                </div>
                <div class="bcp-sub__summary-card">
                    <span>Classes to assign</span>
                    <strong id="metricPendingClasses">—</strong>
                </div>
                <div class="bcp-sub__summary-card">
                    <span>Assigned duties</span>
                    <strong id="metricAssigned">—</strong>
                </div>
            </section>

            <section class="bcp-sub__panel">
                <div class="bcp-sub__section-head">
                    <div>
                        <h2>Pending requests / To-do</h2>
                        <p>Open a request to see its affected class schedule and the system's substitute suggestions.</p>
                    </div>
                    
                    <div style="display: flex; flex-direction: row; gap: 12px; align-items: center; flex-wrap: wrap; flex: 1 1 auto; justify-content: flex-end;">
                        <div style="width: 160px; flex-shrink: 0; min-width: 0; position: relative; display: flex; align-items: center; height: 44px;">
                            <select id="subStatusFilter" class="bcp-inline-select">
                                <option value="ALL">All Statuses</option>
                                <option value="PENDING">Pending</option>
                                <option value="PARTIALLY_ASSIGNED">Partially Assigned</option>
                                <option value="ASSIGNED">Assigned</option>
                            </select>
                        </div>
                        
                        <div class="bcp-sub__search-box" style="flex: 1 1 200px; max-width: 350px; position: relative; display: flex; align-items: center; height: 44px;">
                            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #9ba7ba; font-size: 14px; z-index: 10;"></i>
                            <input id="subSearch" type="search" placeholder="Search professor, section or subject" autocomplete="off" style="width: 100%; height: 44px; padding: 0 16px 0 38px; border: 1px solid #dae3f0; border-radius: 11px; background: #f9fbff; font-family: inherit; font-size: 13px; outline: none; transition: all 0.2s; box-sizing: border-box; margin: 0;">
                        </div>
                    </div>
                </div>

                <div id="requestInbox" class="subreq-grid">
                    <div class="bcp-sub__empty">Loading…</div>
                </div>
            </section>

            <section class="bcp-sub__panel">
                <div class="bcp-sub__section-head">
                    <div>
                        <h2>Assignment history</h2>
                        <p>Temporary assignments remain separate from the original saved timetable.</p>
                    </div>

                    <!-- ALIGNED FILTER BAR & SEARCH BOX FOR HISTORY -->
                    <div style="display: flex; flex-direction: row; gap: 12px; align-items: center; flex-wrap: wrap; flex: 1 1 auto; justify-content: flex-end;">
                        <div style="width: 160px; flex-shrink: 0; min-width: 0; position: relative; display: flex; align-items: center; height: 44px;">
                            <select id="historyStatusFilter" class="bcp-inline-select">
                                <option value="ALL">All Statuses</option>
                                <option value="ACTIVE">Active</option>
                                <option value="CANCELLED">Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="bcp-sub__search-box" style="flex: 1 1 200px; max-width: 350px; position: relative; display: flex; align-items: center; height: 44px;">
                            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #9ba7ba; font-size: 14px; z-index: 10;"></i>
                            <input id="historySearch" type="search" placeholder="Search professor, class or date" autocomplete="off" style="width: 100%; height: 44px; padding: 0 16px 0 38px; border: 1px solid #dae3f0; border-radius: 11px; background: #f9fbff; font-family: inherit; font-size: 13px; outline: none; transition: all 0.2s; box-sizing: border-box; margin: 0;">
                        </div>
                    </div>
                </div>

                <div class="bcp-sub__history-scroll">
                    <table class="bcp-sub__history-table">
                        <thead>
                        <tr>
                            <th>Duty date</th>
                            <th>Class</th>
                            <th>Original professor</th>
                            <th>Substitute professor</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody id="subHistory"></tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- MODAL -->
        <div class="bcp-sub-modal subreq-modal" id="requestModal" hidden>
            <div class="bcp-sub-modal__shade" data-close-request></div>

            <section class="bcp-sub-modal__dialog subreq-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="requestModalTitle">
                <div class="bcp-sub-modal__header">
                    <div>
                        <p class="bcp-sub__eyebrow">REQUEST REVIEW</p>
                        <h2 id="requestModalTitle">Faculty substitute request</h2>
                    </div>
                    <button class="bcp-sub-modal__close" type="button" data-close-request aria-label="Close request">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="subreq-modal__body">
                    <aside class="subreq-request-pane">
                        <div id="requestSummary" class="subreq-request-summary"></div>

                        <div class="subreq-pane-title">
                            <span>Affected classes</span>
                            <small>Choose a pending class</small>
                        </div>

                        <div id="requestItems" class="subreq-item-list"></div>
                    </aside>

                    <section class="subreq-suggestion-pane">
                        <div class="subreq-suggestion-head">
                            <div>
                                <p class="bcp-sub__eyebrow">SYSTEM SUGGESTIONS</p>
                                <h3 id="suggestionTitle">Select a requested class</h3>
                            </div>
                            <span id="suggestionCount" class="bcp-sub__chip">—</span>
                        </div>

                        <div id="suggestionContext" class="subreq-context">
                            Open a request and the system will immediately check who can cover the selected schedule.
                        </div>

                        <div id="candidateSuggestions" class="subreq-candidates">
                            <div class="bcp-sub__empty">No class selected.</div>
                        </div>
                    </section>
                </div>

                <div class="bcp-sub-modal__footer">
                    <span class="subreq-footer-note">
                        Authorization, availability, schedule conflicts, exam duties, existing substitute duties and teaching load are rechecked before save.
                    </span>
                    <button type="button" class="bcp-sub__btn-secondary" data-close-request>Close</button>
                </div>
            </section>
        </div>

        <div id="subPrintDocument" class="bcp-sub-print-document">
            <div class="bcp-sub-print-header">
                <img src="../../assets/images/BCP_LOGO.png" alt="BCP Logo" class="bcp-sub-print-logo">
                <h3>BESTLINK COLLEGE OF THE PHILIPPINES</h3>
                <h1>Substitute Request To-Do Report</h1>
                <div class="bcp-sub-print-meta">
                    <span><strong>Program:</strong> <span id="printProgram">—</span></span>
                    <span><strong>Academic Period:</strong> <span id="printPeriod">—</span></span>
                    <span><strong>Generated:</strong> <?= subPageEsc($generatedAt->format('M j, Y · g:i A')) ?></span>
                </div>
            </div>

            <table class="bcp-sub-print-table">
                <thead>
                <tr>
                    <th>Request</th>
                    <th>Professor</th>
                    <th>Date</th>
                    <th>Class</th>
                    <th>Time</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody id="printTableBody"></tbody>
            </table>
        </div>
    </main>

    <div class="footer">
        Scheduling System &copy; <?= $generatedAt->format('Y') ?> Bestlink College of the Philippines
    </div>
</div>

<script>
(() => {
'use strict';

const el = id => document.getElementById(id);

const state = {
    period: null,
    program: null,
    requests: [],
    items: [],
    history: [],
    csrf: '',
    currentRequest: null,
    currentItem: null,
    busy: false,
    candidateAbort: null,
};

const sidebar = el('sidebar');
el('hamburgerBtn')?.addEventListener('click', () => sidebar?.classList.toggle('collapsed'));

const fmtTime = value => {
    const [h,m] = String(value || '00:00').split(':').map(Number);
    return `${h % 12 || 12}:${String(m).padStart(2,'0')} ${h >= 12 ? 'PM' : 'AM'}`;
};

const fmtDate = value => value
    ? new Date(value + 'T12:00:00').toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'})
    : '—';

const node = (tag,text,cls='') => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = String(text);
    return n;
};

/* --- PREMIUM CUSTOM SELECT DROPDOWN LOGIC --- */
function upgradeSelects() {
    document.querySelectorAll('select').forEach(select => {
        if (select.parentElement.classList.contains('bcp-custom-select-initialized')) return;
        
        select.style.display = 'none';
        
        const wrapper = document.createElement("div");
        wrapper.className = "bcp-custom-select-wrapper bcp-custom-select-initialized";
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);

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
            wrapper.classList.toggle('is-disabled', select.disabled);
            
            if (select.options.length === 0) {
                triggerText.textContent = "Loading...";
                return;
            }
            
            let selectedLabel = "";
            Array.from(select.options).forEach(opt => {
                if (opt.selected) selectedLabel = opt.text;
                const item = document.createElement("div");
                item.className = "bcp-custom-select-option";
                item.textContent = opt.text;
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
        observer.observe(select, { childList: true, attributes: true, attributeFilter: ['disabled'] });
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
    document.querySelectorAll('.bcp-custom-select-options').forEach(elem => elem.classList.remove('is-open'));
    document.querySelectorAll('.bcp-custom-select-trigger').forEach(elem => elem.classList.remove('is-active'));
}
document.addEventListener('click', closeAllCustomSelects);
/* ------------------------------------------- */

function setStatus(message, kind='info') {
    const target = el('subStatus');
    const icons = {
        loading:'fa-circle-notch fa-spin',
        success:'fa-circle-check',
        error:'fa-triangle-exclamation',
        info:'fa-circle-info'
    };
    target.dataset.kind = kind;
    target.innerHTML = `<i class="fa-solid ${icons[kind] || icons.info} bcp-sub__status-icon"></i> ${message}`;
}

async function api(method, params, signal=null) {
    const url = new URL('./substitute-api.php', window.location.href);

    if (method === 'GET') {
        Object.entries(params).forEach(([key,value]) => {
            if (value !== null && value !== undefined && value !== '') {
                url.searchParams.set(key, value);
            }
        });
    }

    const response = await fetch(url, {
        method,
        credentials:'same-origin',
        cache:'no-store',
        signal,
        headers: method === 'POST' ? {'Content-Type':'application/json'} : {},
        body: method === 'POST' ? JSON.stringify(params) : undefined
    });

    let data;
    try {
        data = await response.json();
    } catch {
        throw new Error('API returned invalid JSON. Check the PHP error log.');
    }

    if (!response.ok || !data.success) {
        throw new Error(data.message || data.status || 'Request failed.');
    }

    return data;
}

function fillSelect(select, rows, valueField, labelFn, selected) {
    select.replaceChildren();

    rows.forEach(row => select.add(new Option(labelFn(row), row[valueField])));

    if (selected !== undefined && rows.some(row => String(row[valueField]) === String(selected))) {
        select.value = String(selected);
    } else if (rows.length) {
        select.selectedIndex = 0;
    }
}

function itemsForRequest(requestId) {
    return state.items.filter(item => Number(item.request_id) === Number(requestId));
}

function requestSearchText(request) {
    const items = itemsForRequest(request.request_id);
    return [
        request.teacher_name,
        request.employee_no,
        request.status,
        ...items.flatMap(item => [item.section_code,item.subject_code,item.subject_title,item.duty_date])
    ].join(' ').toLowerCase();
}

function renderMetrics() {
    const open = state.requests.filter(r => ['PENDING','PARTIALLY_ASSIGNED'].includes(r.status)).length;
    const pendingClasses = state.items.filter(i => i.item_status === 'PENDING').length;
    const assigned = state.items.filter(i => i.item_status === 'ASSIGNED').length;

    el('metricPendingRequests').textContent = open;
    el('metricPendingClasses').textContent = pendingClasses;
    el('metricAssigned').textContent = assigned;
}

function renderInbox() {
    const root = el('requestInbox');
    root.replaceChildren();

    const q = el('subSearch').value.trim().toLowerCase();
    const statFilt = el('subStatusFilter').value;

    const requests = state.requests.filter(request => {
        if (q && !requestSearchText(request).includes(q)) return false;
        if (statFilt !== 'ALL' && request.status !== statFilt) return false;
        if (statFilt === 'ALL' && request.status === 'CANCELLED') return false; 
        return true;
    });

    if (!requests.length) {
        const empty = node('div', undefined, 'subreq-empty');
        empty.innerHTML = `
            <i class="fa-regular fa-circle-check"></i>
            <strong>No matching faculty requests</strong>
            <span>Check your filters or wait for new incoming requests.</span>
        `;
        root.append(empty);
        return;
    }

    requests.forEach(request => {
        const items = itemsForRequest(request.request_id);
        const pending = items.filter(item => item.item_status === 'PENDING').length;
        const assigned = items.filter(item => item.item_status === 'ASSIGNED').length;

        const card = node('article', undefined, 'subreq-card');
        if (pending > 0) card.classList.add('is-pending');

        const head = node('div', undefined, 'subreq-card__head');
        const identity = node('div');
        identity.append(
            node('span', `REQUEST #${request.request_id}`, 'subreq-card__eyebrow'),
            node('strong', request.teacher_name),
            node('small', request.employee_no)
        );

        const badge = node(
            'span',
            request.status.replaceAll('_',' '),
            `subreq-status subreq-status--${request.status.toLowerCase()}`
        );

        head.append(identity,badge);

        const dates = request.leave_start_date === request.leave_end_date
            ? fmtDate(request.leave_start_date)
            : `${fmtDate(request.leave_start_date)} – ${fmtDate(request.leave_end_date)}`;

        const meta = node('div', undefined, 'subreq-card__meta');
        meta.innerHTML = `
            <span><i class="fa-regular fa-calendar"></i>${dates}</span>
            <span><i class="fa-solid fa-layer-group"></i>${items.length} affected class${items.length===1?'':'es'}</span>
            <span><i class="fa-solid fa-list-check"></i>${pending} to assign · ${assigned} assigned</span>
        `;

        const preview = node('div', undefined, 'subreq-card__preview');
        items.slice(0,2).forEach(item => {
            preview.append(node(
                'span',
                `${fmtDate(item.duty_date)} · ${item.section_code} · ${item.subject_code} · ${fmtTime(item.start_time)}–${fmtTime(item.end_time)}`
            ));
        });
        if (items.length > 2) preview.append(node('span', `+${items.length - 2} more class(es)`));

        const footer = node('div', undefined, 'subreq-card__footer');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'bcp-sub__btn-primary';
        button.innerHTML = pending > 0
            ? '<i class="fa-solid fa-wand-magic-sparkles"></i> Open & Suggest'
            : '<i class="fa-solid fa-eye"></i> View Request';
        button.addEventListener('click', () => openRequest(request));

        footer.append(node('span', pending > 0 ? 'Needs scheduling action' : 'Coverage complete', 'subreq-card__hint'), button);
        card.append(head,meta,preview,footer);
        root.append(card);
    });
}

function renderHistory() {
    const body = el('subHistory');
    body.replaceChildren();

    const q = el('historySearch')?.value.trim().toLowerCase() || '';
    const statFilt = el('historyStatusFilter')?.value || 'ALL';

    const filteredHistory = state.history.filter(h => {
        if (statFilt !== 'ALL' && h.status !== statFilt) return false;
        
        if (q) {
            const searchStr = [
                fmtDate(h.duty_date),
                h.section_code,
                h.subject_code,
                h.original_teacher_name,
                h.substitute_teacher_name
            ].join(' ').toLowerCase();
            if (!searchStr.includes(q)) return false;
        }
        return true;
    });

    if (!filteredHistory.length) {
        const tr = node('tr');
        const td = node('td', q || statFilt !== 'ALL' ? 'No matching assignments found.' : 'No substitute assignments recorded yet.', 'bcp-sub__empty-cell');
        td.colSpan = 6;
        tr.append(td);
        body.append(tr);
        return;
    }

    filteredHistory.forEach(h => {
        const tr = node('tr');
        [
            fmtDate(h.duty_date),
            `${h.section_code} · ${h.subject_code} · ${fmtTime(h.start_time)}–${fmtTime(h.end_time)}`,
            h.original_teacher_name,
            h.substitute_teacher_name
        ].forEach(value => tr.append(node('td', value)));

        const statusCell = node('td');
        statusCell.append(node('span', h.status, `bcp-sub__chip ${h.status === 'ACTIVE' ? 'bcp-sub__chip--covered' : ''}`));
        tr.append(statusCell);

        const action = node('td');
        if (h.status === 'ACTIVE') {
            const button = node('button','Cancel duty','bcp-sub__link-btn');
            button.type = 'button';
            button.addEventListener('click', () => cancelAssignment(h));
            action.append(button);
        } else {
            action.textContent = '—';
        }
        tr.append(action);
        body.append(tr);
    });
}

function renderAll() {
    renderMetrics();
    renderInbox();
    renderHistory();
}

async function load() {
    el('subRefresh').disabled = true;
    setStatus('Loading request inbox…','loading');

    try {
        const data = await api('GET', {
            action:'catalog',
            period_id:el('subPeriod').value,
            program:el('subProgram').value
        });

        state.period = data.selected_period_id;
        state.program = data.selected_program?.program_code || '';
        state.requests = data.requests || [];
        state.items = data.request_items || [];
        state.history = data.history || [];
        state.csrf = data.csrf_token || '';

        fillSelect(
            el('subPeriod'),
            data.periods || [],
            'academic_period_id',
            row => `${row.academic_year} · Semester ${row.semester}`,
            state.period
        );

        fillSelect(
            el('subProgram'),
            data.programs || [],
            'program_code',
            row => `${row.program_code} — ${row.program_name}`,
            state.program
        );
        
        upgradeSelects();
        renderAll();

        if (!data.selected_program) {
            setStatus('No active timetable or incoming substitute request exists for this academic period.','info');
        } else {
            const pending = state.items.filter(item => item.item_status === 'PENDING').length;
            setStatus(
                pending
                    ? `${pending} requested class${pending===1?'':'es'} waiting for substitute assignment.`
                    : 'Request inbox loaded. No class is waiting for assignment.',
                'success'
            );
        }
    } catch (error) {
        state.requests = [];
        state.items = [];
        state.history = [];
        renderAll();
        setStatus(error.message,'error');
    } finally {
        el('subRefresh').disabled = false;
    }
}

function closeRequest() {
    state.candidateAbort?.abort();
    state.candidateAbort = null;
    state.currentRequest = null;
    state.currentItem = null;
    el('requestModal').hidden = true;
}

function renderRequestItems(request) {
    const root = el('requestItems');
    root.replaceChildren();

    const items = itemsForRequest(request.request_id);

    if (!items.length) {
        root.append(node('div','No affected class item is attached to this request.','bcp-sub__empty'));
        return [];
    }

    items.forEach(item => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `subreq-item subreq-item--${item.item_status.toLowerCase()}`;

        const top = node('div', undefined, 'subreq-item__top');
        top.append(
            node('strong', `${item.section_code} · ${item.subject_code}`),
            node('span', item.item_status, `subreq-mini-status subreq-mini-status--${item.item_status.toLowerCase()}`)
        );

        const meta = node(
            'span',
            `${fmtDate(item.duty_date)} · ${fmtTime(item.start_time)}–${fmtTime(item.end_time)} · ${item.delivery_mode} · ${item.room_name}`
        );

        button.append(top, meta);

        if (item.item_status === 'ASSIGNED' && item.substitute_teacher_name) {
            button.append(node('small', `Assigned: ${item.substitute_teacher_name}`));
        }

        if (item.item_status === 'PENDING') {
            button.addEventListener('click', () => selectRequestItem(item));
        } else {
            button.disabled = true;
        }

        root.append(button);
    });

    return items;
}

async function openRequest(request) {
    state.currentRequest = request;
    el('requestModal').hidden = false;

    const dates = request.leave_start_date === request.leave_end_date
        ? fmtDate(request.leave_start_date)
        : `${fmtDate(request.leave_start_date)} – ${fmtDate(request.leave_end_date)}`;

    el('requestSummary').innerHTML = `
        <span class="subreq-request-summary__id">REQUEST #${request.request_id}</span>
        <strong>${request.teacher_name}</strong>
        <small>${request.employee_no}</small>
        <div>
            <span><i class="fa-regular fa-calendar"></i>${dates}</span>
            <span><i class="fa-solid fa-layer-group"></i>${request.coverage_type.replaceAll('_',' ')}</span>
        </div>
    `;

    const items = renderRequestItems(request);

    if (!items.length) {
        el('suggestionTitle').textContent = 'Request data incomplete';
        el('suggestionCount').textContent = 'NO CLASS DATA';
        el('suggestionContext').textContent =
            'This request exists, but no affected class schedule is attached. It cannot be assigned until its request item data is repaired.';
        el('candidateSuggestions').replaceChildren(
            node('div','No affected class is attached to this request. Repair or replace this sample request first.','bcp-sub__empty')
        );
        return;
    }

    const firstPending = items.find(item => item.item_status === 'PENDING');

    if (firstPending) {
        await selectRequestItem(firstPending);
    } else {
        const allAssigned = items.every(item => item.item_status === 'ASSIGNED');
        el('suggestionTitle').textContent = allAssigned ? 'Request coverage complete' : 'No pending class available';
        el('suggestionCount').textContent = allAssigned ? 'ASSIGNED' : request.status.replaceAll('_',' ');
        el('suggestionContext').textContent = allAssigned
            ? 'All requested classes already have substitute assignments.'
            : 'This request has no pending class available for assignment.';
        el('candidateSuggestions').replaceChildren(
            node(
                'div',
                allAssigned
                    ? 'No pending class remains in this request.'
                    : 'No pending class can be processed from this request.',
                'bcp-sub__empty'
            )
        );
    }
}

async function selectRequestItem(item) {
    state.currentItem = item;

    document.querySelectorAll('.subreq-item').forEach(button => button.classList.remove('is-selected'));
    const itemButtons = [...document.querySelectorAll('.subreq-item')];
    const index = itemsForRequest(state.currentRequest.request_id)
        .filter(i => i.item_status !== 'CANCELLED')
        .findIndex(i => Number(i.request_item_id) === Number(item.request_item_id));
    if (index >= 0 && itemButtons[index]) itemButtons[index].classList.add('is-selected');

    el('suggestionTitle').textContent = `${item.section_code} · ${item.subject_code}`;
    el('suggestionCount').textContent = 'CHECKING';
    el('suggestionContext').textContent =
        `${fmtDate(item.duty_date)} · ${fmtTime(item.start_time)}–${fmtTime(item.end_time)} · ${item.delivery_mode} · ${item.room_name}`;

    const root = el('candidateSuggestions');
    root.innerHTML = `
        <div class="subreq-loading">
            <i class="fa-solid fa-circle-notch fa-spin"></i>
            <strong>Checking eligible professors…</strong>
            <span>Authorization, availability and conflicts are being evaluated.</span>
        </div>
    `;

    state.candidateAbort?.abort();
    state.candidateAbort = new AbortController();

    try {
        const data = await api('GET', {
            action:'candidates',
            period_id:state.period,
            request_item_id:item.request_item_id
        }, state.candidateAbort.signal);

        if (Number(state.currentItem?.request_item_id) !== Number(item.request_item_id)) return;

        const eligible = data.candidates.filter(candidate => candidate.eligible);
        el('suggestionCount').textContent = `${eligible.length} AVAILABLE`;

        root.replaceChildren();

        if (!eligible.length) {
            const empty = node('div', undefined, 'subreq-empty');
            empty.innerHTML = `
                <i class="fa-solid fa-user-slash"></i>
                <strong>No eligible substitute found</strong>
                <span>All candidates failed one or more authorization, availability, conflict or load checks.</span>
            `;
            root.append(empty);
            return;
        }

        eligible.forEach((candidate, position) => {
            const card = node('article', undefined, 'subreq-candidate');
            if (candidate.recommended) card.classList.add('is-recommended');

            const head = node('div', undefined, 'subreq-candidate__head');
            const person = node('div');
            person.append(
                node('strong', candidate.teacher_name),
                node('small', candidate.employee_no)
            );

            const badge = node(
                'span',
                candidate.recommended ? 'RECOMMENDED' : `OPTION ${position + 1}`,
                candidate.recommended ? 'subreq-recommended' : 'subreq-option'
            );

            head.append(person,badge);

            const load = node('div', undefined, 'subreq-candidate__load');
            const dailyHours = (Number(candidate.projected_daily_minutes) / 60).toFixed(1);
            const weeklyHours = (Number(candidate.projected_weekly_minutes) / 60).toFixed(1);

            load.innerHTML = `
                <span><i class="fa-solid fa-circle-check"></i> Authorized & available</span>
                <span><i class="fa-regular fa-clock"></i> Projected day: ${dailyHours} / ${candidate.max_daily_hours} hrs</span>
                <span><i class="fa-solid fa-calendar-week"></i> Projected week: ${weeklyHours} / ${candidate.max_weekly_hours} hrs</span>
            `;

            const assign = document.createElement('button');
            assign.type = 'button';
            assign.className = candidate.recommended ? 'bcp-sub__btn-primary' : 'bcp-sub__btn-secondary';
            assign.innerHTML = candidate.recommended
                ? '<i class="fa-solid fa-user-check"></i> Assign recommended'
                : '<i class="fa-solid fa-user-plus"></i> Assign';
            assign.addEventListener('click', () => assignCandidate(candidate, assign));

            card.append(head,load,assign);
            root.append(card);
        });
    } catch (error) {
        if (error.name === 'AbortError') return;
        root.replaceChildren(node('div',error.message,'bcp-sub__empty'));
        el('suggestionCount').textContent = 'ERROR';
    }
}

async function assignCandidate(candidate, button) {
    if (!state.currentItem || state.busy) return;

    if (!window.confirm(
        `Assign ${candidate.teacher_name} to ${state.currentItem.section_code} · ${state.currentItem.subject_code} on ${fmtDate(state.currentItem.duty_date)}?`
    )) return;

    state.busy = true;
    button.disabled = true;

    try {
        await api('POST', {
            action:'assign',
            period_id:state.period,
            request_item_id:state.currentItem.request_item_id,
            substitute_teacher_id:candidate.teacher_id,
            csrf_token:state.csrf
        });

        const requestId = state.currentRequest.request_id;
        await load();

        const refreshedRequest = state.requests.find(r => Number(r.request_id) === Number(requestId));
        if (refreshedRequest) {
            await openRequest(refreshedRequest);
        } else {
            closeRequest();
        }

        setStatus(`Substitute assigned successfully to request #${requestId}.`,'success');
    } catch (error) {
        setStatus(error.message,'error');
        button.disabled = false;
    } finally {
        state.busy = false;
    }
}

async function cancelAssignment(row) {
    if (state.busy) return;

    if (!window.confirm(
        `Cancel the active substitute duty of ${row.substitute_teacher_name} for ${row.section_code} · ${row.subject_code}?`
    )) return;

    state.busy = true;

    try {
        await api('POST', {
            action:'cancel_assignment',
            period_id:state.period,
            substitute_assignment_id:row.substitute_assignment_id,
            csrf_token:state.csrf
        });

        await load();
        setStatus('Substitute duty cancelled. The request item returned to pending.','success');
    } catch (error) {
        setStatus(error.message,'error');
    } finally {
        state.busy = false;
    }
}

function buildPrint() {
    const periodSelect = el('subPeriod');
    const programSelect = el('subProgram');

    el('printPeriod').textContent =
        periodSelect.options[periodSelect.selectedIndex]?.text || '—';
    el('printProgram').textContent =
        programSelect.options[programSelect.selectedIndex]?.text || '—';

    const body = el('printTableBody');
    body.replaceChildren();

    state.requests.forEach(request => {
        itemsForRequest(request.request_id).forEach(item => {
            const tr = document.createElement('tr');
            [
                `#${request.request_id}`,
                request.teacher_name,
                fmtDate(item.duty_date),
                `${item.section_code} · ${item.subject_code}`,
                `${fmtTime(item.start_time)}–${fmtTime(item.end_time)}`,
                item.item_status
            ].forEach(value => tr.append(node('td',value)));
            body.append(tr);
        });
    });
}

el('subRefresh').addEventListener('click', load);
el('subPeriod').addEventListener('change', load);
el('subProgram').addEventListener('change', load);

// Inbox filters
el('subStatusFilter').addEventListener('change', renderInbox);
el('subSearch').addEventListener('input', renderInbox);

// History filters
el('historyStatusFilter')?.addEventListener('change', renderHistory);
el('historySearch')?.addEventListener('input', renderHistory);

el('subPrint').addEventListener('click', () => {
    buildPrint();
    window.print();
});

document.querySelectorAll('[data-close-request]').forEach(button => {
    button.addEventListener('click', closeRequest);
});

document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !el('requestModal').hidden) closeRequest();
});

load();
})();
</script>
</body>
</html>
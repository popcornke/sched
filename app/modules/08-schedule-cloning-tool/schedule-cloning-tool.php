<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/auth.php';

authRequire();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$role = htmlspecialchars(
    $_SESSION['auth_role'] ?? $_SESSION['role'] ?? 'Scheduler',
    ENT_QUOTES,
    'UTF-8'
);

$initial = strtoupper(
    substr(
        $_SESSION['auth_username']
        ?? $_SESSION['first_name']
        ?? 'U',
        0,
        1
    )
);

$APP_ROOT = '../../';
$ACTIVE_NAV = 'schedule_cloning';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Schedule Cloning Tool | BCP</title>

    <link
        rel="icon"
        href="../../images/BCP_LOGO.png"
        type="image/png"
    >

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="../../assets/css/schedule-cloning-tool.css"
    >
</head>

<body>

<?php
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main">

    <div class="topbar">

        <button
            class="hamburger"
            id="hamburgerBtn"
            type="button"
            aria-label="Toggle sidebar"
        >
            <i class="fa-solid fa-bars"></i>
        </button>

        <span class="topbar-spacer"></span>

        <div class="topbar-right">

            <span class="role-badge">
                <i
                    class="fa-solid fa-user-tie"
                    style="color:#2563eb;"
                ></i>

                <?= $role ?>
            </span>

            <a
                href="../../auth/account.php"
                class="avatar"
                title="Account Settings"
            >
                <?= $initial ?>
            </a>

        </div>

    </div>


    <main
        class="content clone-page"
        id="clonePage"
    >

        <!-- =====================================================
             PAGE HEADER
        ====================================================== -->

        <section class="clone-page-header">

            <div class="clone-page-header__content">

                <div class="clone-page-header__eyebrow">
                    BCP CLASS SCHEDULING · MODULE 8
                </div>

                <h1 class="clone-page-header__title">
                    Schedule Cloning Tool
                </h1>

                <p class="clone-page-header__description">
                    Reuse a verified saved timetable as a template
                    for another prepared academic period.
                    The source timetable remains unchanged.
                </p>

            </div>

            <div class="clone-page-header__meta">

                <span class="clone-badge">
                    DEMO CLONING WORKFLOW
                </span>

            </div>

        </section>


        <!-- =====================================================
             PROGRESS
        ====================================================== -->

        <nav
            class="clone-progress"
            aria-label="Schedule cloning progress"
        >

            <div
                class="clone-progress__item is-active"
                data-step="1"
            >
                <span class="clone-progress__number">1</span>

                <span class="clone-progress__text">
                    <strong>Source &amp; Target</strong>
                    <small>Select records</small>
                </span>
            </div>


            <div
                class="clone-progress__item"
                data-step="2"
            >
                <span class="clone-progress__number">2</span>

                <span class="clone-progress__text">
                    <strong>Readiness</strong>
                    <small>Map records</small>
                </span>
            </div>


            <div
                class="clone-progress__item"
                data-step="3"
            >
                <span class="clone-progress__number">3</span>

                <span class="clone-progress__text">
                    <strong>Clone Preview</strong>
                    <small>Validate meetings</small>
                </span>
            </div>


            <div
                class="clone-progress__item"
                data-step="4"
            >
                <span class="clone-progress__number">4</span>

                <span class="clone-progress__text">
                    <strong>Save</strong>
                    <small>Atomic database save</small>
                </span>
            </div>

        </nav>


        <!-- =====================================================
             STEP 1
        ====================================================== -->

        <section class="clone-card">

            <header class="clone-card__header">

                <div>

                    <span class="clone-card__step">
                        STEP 01
                    </span>

                    <h2>
                        Select source and target
                    </h2>

                    <p>
                        Choose an ACTIVE saved timetable and a
                        different prepared academic period.
                    </p>

                </div>

            </header>


            <div class="clone-form-grid">

                <div class="clone-field">

                    <label for="cloneSource">
                        Source timetable
                    </label>

                    <select
                        id="cloneSource"
                        disabled
                    >
                        <option value="">
                            Loading saved timetables...
                        </option>
                    </select>

                    <small class="clone-field__hint">
                        Only ACTIVE timetable batches are available.
                    </small>

                </div>


                <div class="clone-field">

                    <label for="cloneTarget">
                        Target academic period
                    </label>

                    <select
                        id="cloneTarget"
                        disabled
                    >
                        <option value="">
                            Loading academic periods...
                        </option>
                    </select>

                    <small class="clone-field__hint">
                        The target must already contain compatible
                        sections and section-subject records.
                    </small>

                </div>

            </div>


            <div
                class="clone-status"
                id="cloneStatus"
                role="status"
                aria-live="polite"
            >

                <span class="clone-status__indicator"></span>

                <span id="cloneStatusText">
                    Loading saved timetable records...
                </span>

            </div>


            <div class="clone-actions">

                <button
                    type="button"
                    class="clone-button clone-button--primary"
                    id="cloneCheck"
                    disabled
                >
                    Check Target Readiness
                </button>

                <button
                    type="button"
                    class="clone-button clone-button--secondary"
                    id="clonePrint"
                    disabled
                >
                    Print Report
                </button>

            </div>

        </section>


        <!-- =====================================================
             SOURCE SUMMARY
        ====================================================== -->

        <section
            class="clone-card"
            id="cloneSourceSummary"
            hidden
        >

            <header class="clone-card__header">

                <div>

                    <span class="clone-card__step">
                        SOURCE
                    </span>

                    <h2>
                        Selected timetable
                    </h2>

                </div>

            </header>

            <div
                class="clone-summary-grid"
                id="cloneSourceStats"
            ></div>

        </section>


        <!-- =====================================================
             STEP 2
        ====================================================== -->

        <section
            class="clone-card"
            id="cloneResult"
            hidden
        >

            <header class="clone-card__header">

                <div>

                    <span class="clone-card__step">
                        STEP 02
                    </span>

                    <h2>
                        Target readiness
                    </h2>

                    <p id="cloneSubtitle">
                        Mapping inventory
                    </p>

                </div>

                <span
                    class="clone-result-badge"
                    id="cloneResultBadge"
                >
                    CHECKED
                </span>

            </header>


            <div
                class="clone-stats"
                id="cloneStats"
            ></div>


            <section class="clone-result-section">

                <div class="clone-result-section__header">

                    <h3>
                        Readiness findings
                    </h3>

                    <p>
                        Structural blockers must be resolved
                        before Clone Preview.
                    </p>

                </div>

                <div id="cloneFindings"></div>

            </section>


            <section class="clone-result-section">

                <div class="clone-result-section__header">

                    <h3>
                        Section mapping
                    </h3>

                    <p>
                        Source sections are mapped to their
                        target-period equivalents.
                    </p>

                </div>

                <div
                    class="clone-table-wrap"
                    id="cloneSectionMapping"
                ></div>

            </section>


            <section class="clone-result-section">

                <div class="clone-result-section__header">

                    <h3>
                        Section-subject mapping
                    </h3>

                    <p>
                        Each source section-subject must have
                        exactly one target equivalent.
                    </p>

                </div>

                <div
                    class="clone-table-wrap"
                    id="cloneSubjectMapping"
                ></div>

            </section>


            <div class="clone-next-stage">

                <div>

                    <strong>
                        Ready for Clone Preview
                    </strong>

                    <p id="clonePreviewHelp">
                        Complete readiness first.
                    </p>

                </div>

                <button
                    type="button"
                    class="clone-button clone-button--primary"
                    id="clonePreview"
                    disabled
                >
                    Generate Clone Preview
                </button>

            </div>

        </section>


        <!-- =====================================================
             STEP 3
        ====================================================== -->

        <section
            class="clone-card"
            id="clonePreviewResult"
            hidden
        >

            <header class="clone-card__header">

                <div>

                    <span class="clone-card__step">
                        STEP 03
                    </span>

                    <h2>
                        Clone Preview
                    </h2>

                    <p id="clonePreviewSubtitle">
                        Proposed target timetable
                    </p>

                </div>

                <span
                    class="clone-result-badge"
                    id="clonePreviewBadge"
                >
                    PREVIEW
                </span>

            </header>


            <div
                class="clone-stats"
                id="clonePreviewStats"
            ></div>


            <section class="clone-result-section">

                <div class="clone-result-section__header">

                    <h3>
                        Validation result
                    </h3>

                    <p>
                        Preview is read-only.
                        Nothing is saved until Step 04.
                    </p>

                </div>

                <div id="clonePreviewFindings"></div>

            </section>


            <section class="clone-result-section">

                <div class="clone-result-section__header">

                    <h3>
                        Target-period inventory
                    </h3>

                </div>

                <div
                    class="clone-summary-grid"
                    id="cloneTargetInventory"
                ></div>

            </section>


            <section class="clone-result-section">

                <div class="clone-result-section__header">

                    <h3>
                        Proposed cloned meetings
                    </h3>

                    <p id="cloneMeetingCount">
                        —
                    </p>

                </div>

                <div
                    class="clone-table-wrap"
                    id="cloneProposalTable"
                ></div>

            </section>


            <!-- =================================================
                 STEP 4 SAVE
            ================================================== -->

            <div class="clone-next-stage">

                <div>

                    <strong>
                        Confirm &amp; Save Clone
                    </strong>

                    <p id="cloneSaveHelp">
                        Generate a clean Clone Preview first.
                    </p>

                </div>

                <button
                    type="button"
                    class="clone-button clone-button--primary"
                    id="cloneSave"
                    disabled
                >
                    Confirm &amp; Save Clone
                </button>

            </div>


            <div
                id="cloneSaveResult"
                hidden
                style="margin-top:16px;"
            ></div>

        </section>

    </main>


    <div class="footer">
        Scheduling System &copy; <?= date('Y') ?>
        Bestlink College of the Philippines
    </div>

</div>


<script>
(() => {
    'use strict';


    /* ==========================================================
       DOM
    ========================================================== */

    const $ = id =>
        document.getElementById(id);


    const sourceSelect =
        $('cloneSource');

    const targetSelect =
        $('cloneTarget');

    const checkButton =
        $('cloneCheck');

    const previewButton =
        $('clonePreview');

    const saveButton =
        $('cloneSave');

    const printButton =
        $('clonePrint');

    const readinessResult =
        $('cloneResult');

    const previewResult =
        $('clonePreviewResult');

    const sourceSummary =
        $('cloneSourceSummary');

    const saveResult =
        $('cloneSaveResult');


    let catalog = null;
    let assessment = null;
    let preview = null;
    let saveCompleted = false;


    /* ==========================================================
       GENERIC HELPERS
    ========================================================== */

    function node(
        tag,
        text = null,
        css = null
    ) {
        const element =
            document.createElement(tag);

        if (text !== null) {
            element.textContent =
                String(text);
        }

        if (css) {
            element.className =
                css;
        }

        return element;
    }


    function setStatus(
        message,
        type = 'info'
    ) {
        $('cloneStatusText')
            .textContent =
            message;

        $('cloneStatus')
            .dataset.type =
            type;
    }


    function setProgress(
        current
    ) {
        document
            .querySelectorAll(
                '.clone-progress__item'
            )
            .forEach(
                item => {

                    const step =
                        Number(
                            item.dataset.step
                        );

                    item.classList.toggle(
                        'is-active',
                        step === current
                    );

                    item.classList.toggle(
                        'is-complete',
                        step < current
                    );
                }
            );
    }


    function periodLabel(
        period
    ) {
        return (
            period.academic_year
            + ' · Semester '
            + period.semester
        );
    }


    function timeLabel(
        value
    ) {
        if (!value) {
            return '—';
        }

        const parts =
            String(value)
                .split(':');

        const hour =
            Number(parts[0]);

        const minute =
            parts[1] ?? '00';

        const suffix =
            hour >= 12
            ? 'PM'
            : 'AM';

        const displayHour =
            hour % 12 || 12;

        return (
            displayHour
            + ':'
            + minute
            + ' '
            + suffix
        );
    }


    /* ==========================================================
       READINESS API
    ========================================================== */

    async function getApi(
        params
    ) {
        const url =
            new URL(
                'schedule-cloning-api.php',
                window.location.href
            );

        Object.entries(params)
            .forEach(
                ([key, value]) => {

                    url.searchParams.set(
                        key,
                        String(value)
                    );
                }
            );

        const response =
            await fetch(
                url,
                {
                    credentials:
                        'same-origin',

                    cache:
                        'no-store',

                    headers: {
                        Accept:
                            'application/json'
                    }
                }
            );

        let data;

        try {
            data =
                await response.json();

        } catch {
            throw new Error(
                'The readiness API returned invalid JSON.'
            );
        }

        if (
            !response.ok
            || data.success !== true
        ) {
            throw new Error(
                data.message
                || data.status
                || 'Readiness request failed.'
            );
        }

        return data;
    }


    /* ==========================================================
       PREVIEW API
    ========================================================== */

    async function previewApi(
        payload
    ) {
        const response =
            await fetch(
                'clone-preview.php',
                {
                    method:
                        'POST',

                    credentials:
                        'same-origin',

                    cache:
                        'no-store',

                    headers: {
                        'Accept':
                            'application/json',

                        'Content-Type':
                            'application/json'
                    },

                    body:
                        JSON.stringify(
                            payload
                        )
                }
            );

        let data;

        try {
            data =
                await response.json();

        } catch {
            throw new Error(
                'Clone Preview returned invalid JSON.'
            );
        }

        if (
            !response.ok
            || data.success !== true
        ) {
            throw new Error(
                data.message
                || data.status
                || 'Unable to generate Clone Preview.'
            );
        }

        return data;
    }


    /* ==========================================================
       SAVE API
    ========================================================== */

    async function saveApi(
        payload
    ) {
        const response =
            await fetch(
                'clone-save.php',
                {
                    method:
                        'POST',

                    credentials:
                        'same-origin',

                    cache:
                        'no-store',

                    headers: {
                        'Accept':
                            'application/json',

                        'Content-Type':
                            'application/json'
                    },

                    body:
                        JSON.stringify(
                            payload
                        )
                }
            );

        let data;

        try {
            data =
                await response.json();

        } catch {
            throw new Error(
                'Clone Save returned invalid JSON.'
            );
        }

        if (
            !response.ok
            || data.success !== true
        ) {
            throw new Error(
                data.message
                || data.status
                || 'Unable to save the schedule clone.'
            );
        }

        return data;
    }


    /* ==========================================================
       SOURCE SUMMARY
    ========================================================== */

    function renderSourceSummary() {

        const batch =
            catalog?.source_batches.find(
                item =>
                    String(
                        item.batch_id
                    )
                    === sourceSelect.value
            );

        if (!batch) {

            sourceSummary.hidden =
                true;

            return;
        }

        sourceSummary.hidden =
            false;

        const root =
            $('cloneSourceStats');

        root.replaceChildren();

        [
            [
                'Program',
                batch.program_code
            ],
            [
                'Academic Year',
                batch.academic_year
            ],
            [
                'Semester',
                batch.semester
            ],
            [
                'Batch',
                '#' + batch.batch_id
            ],
            [
                'Meetings',
                batch.meeting_count
            ],
            [
                'Status',
                batch.status
            ]
        ]
            .forEach(
                ([label, value]) => {

                    const box =
                        node(
                            'div',
                            null,
                            'clone-summary-item'
                        );

                    box.append(
                        node(
                            'span',
                            label
                        ),

                        node(
                            'strong',
                            value
                        )
                    );

                    root.append(box);
                }
            );
    }


    /* ==========================================================
       TARGET OPTIONS
    ========================================================== */

    function populateTargets() {

        if (saveCompleted) {
            return;
        }

        const source =
            catalog?.source_batches.find(
                batch =>
                    String(
                        batch.batch_id
                    )
                    === sourceSelect.value
            );

        targetSelect.replaceChildren(
            new Option(
                'Select target academic period',
                ''
            )
        );

        if (source) {

            catalog.periods.forEach(
                period => {

                    if (
                        Number(
                            period.academic_period_id
                        )
                        === Number(
                            source.academic_period_id
                        )
                    ) {
                        return;
                    }

                    targetSelect.add(
                        new Option(
                            periodLabel(
                                period
                            ),
                            String(
                                period.academic_period_id
                            )
                        )
                    );
                }
            );
        }

        targetSelect.disabled =
            targetSelect.options.length <= 1;

        readinessResult.hidden =
            true;

        previewResult.hidden =
            true;

        saveResult.hidden =
            true;

        assessment =
            null;

        preview =
            null;

        previewButton.disabled =
            true;

        saveButton.disabled =
            true;

        printButton.disabled =
            true;

        renderSourceSummary();

        updateReadinessButton();

        if (!source) {

            setStatus(
                'Select a source timetable.'
            );

            return;
        }

        if (
            targetSelect.disabled
        ) {

            setStatus(
                (
                    'No different academic period '
                    + 'is available.'
                ),
                'warning'
            );

            return;
        }

        setStatus(
            'Choose a target academic period.',
            'success'
        );
    }


    function updateReadinessButton() {

        checkButton.disabled =
            saveCompleted
            || !sourceSelect.value
            || !targetSelect.value
            || targetSelect.disabled;
    }


    /* ==========================================================
       STAT
    ========================================================== */

    function stat(
        root,
        label,
        value
    ) {

        const box =
            node(
                'div',
                null,
                'clone-stat'
            );

        box.append(
            node(
                'span',
                label
            ),

            node(
                'strong',
                value
            )
        );

        root.append(box);
    }


    /* ==========================================================
       TABLE
    ========================================================== */

    function buildTable(
        columns,
        rows
    ) {

        const table =
            node(
                'table',
                null,
                'clone-table'
            );

        const head =
            document.createElement(
                'thead'
            );

        const header =
            document.createElement(
                'tr'
            );

        columns.forEach(
            column => {

                header.append(
                    node(
                        'th',
                        column.label
                    )
                );
            }
        );

        head.append(header);

        const body =
            document.createElement(
                'tbody'
            );

        rows.forEach(
            row => {

                const tr =
                    document.createElement(
                        'tr'
                    );

                columns.forEach(
                    column => {

                        const td =
                            document.createElement(
                                'td'
                            );

                        if (
                            typeof column.render
                            === 'function'
                        ) {

                            const rendered =
                                column.render(row);

                            if (
                                rendered
                                instanceof Node
                            ) {
                                td.append(
                                    rendered
                                );

                            } else {
                                td.textContent =
                                    rendered ?? '—';
                            }

                        } else {
                            td.textContent =
                                row[column.key]
                                ?? '—';
                        }

                        tr.append(td);
                    }
                );

                body.append(tr);
            }
        );

        table.append(
            head,
            body
        );

        return table;
    }


    function mappingBadge(
        value
    ) {

        const badge =
            node(
                'span',
                String(value)
                    .replaceAll(
                        '_',
                        ' '
                    ),
                'clone-map-status'
            );

        badge.dataset.status =
            String(value)
                .startsWith(
                    'EXACT'
                )
            ? 'success'
            : 'problem';

        return badge;
    }


    /* ==========================================================
       READINESS RENDER
    ========================================================== */

    function renderReadiness(
        data
    ) {

        assessment =
            data;

        preview =
            null;

        saveCompleted =
            false;

        previewResult.hidden =
            true;

        saveResult.hidden =
            true;

        saveButton.disabled =
            true;

        setProgress(2);

        readinessResult.hidden =
            false;

        const stats =
            $('cloneStats');

        stats.replaceChildren();

        stat(
            stats,
            'Source meetings',
            data.source_meeting_count
        );

        stat(
            stats,
            'Source sections',
            data.source_section_count
        );

        stat(
            stats,
            'Section-subject pairs',
            data.source_section_subject_count
        );

        stat(
            stats,
            'Target sections',
            data.target_section_count
        );

        $('cloneSubtitle')
            .textContent =
            (
                data.source.program_code
                + ' · '
                + data.source.academic_year
                + ' S'
                + data.source.semester
                + ' → '
                + periodLabel(
                    data.target_period
                )
            );

        const findings =
            $('cloneFindings');

        findings.replaceChildren();

        if (
            data.blockers.length
            === 0
        ) {

            const success =
                node(
                    'div',
                    null,
                    'clone-alert clone-alert--success'
                );

            success.append(
                node(
                    'strong',
                    'Readiness mapping passed'
                ),

                node(
                    'p',
                    (
                        'Source sections and '
                        + 'section-subject records '
                        + 'have target equivalents.'
                    )
                )
            );

            findings.append(success);

            $('cloneResultBadge')
                .textContent =
                'MAPPING READY';

            $('cloneResultBadge')
                .dataset.status =
                'success';

            previewButton.disabled =
                false;

            $('clonePreviewHelp')
                .textContent =
                (
                    'Structural mapping passed. '
                    + 'Generate a read-only Clone Preview '
                    + 'to validate proposed meetings.'
                );

        } else {

            data.blockers.forEach(
                blocker => {

                    const alert =
                        node(
                            'div',
                            null,
                            'clone-alert clone-alert--warning'
                        );

                    alert.append(
                        node(
                            'strong',
                            blocker.code
                                .replaceAll(
                                    '_',
                                    ' '
                                )
                        ),

                        node(
                            'p',
                            blocker.message
                        )
                    );

                    findings.append(alert);
                }
            );

            $('cloneResultBadge')
                .textContent =
                (
                    data.blockers.length
                    + ' BLOCKER'
                    + (
                        data.blockers.length === 1
                        ? ''
                        : 'S'
                    )
                );

            $('cloneResultBadge')
                .dataset.status =
                'warning';

            previewButton.disabled =
                true;

            $('clonePreviewHelp')
                .textContent =
                (
                    'Resolve all readiness blockers '
                    + 'before generating Clone Preview.'
                );
        }

        $('cloneSectionMapping')
            .replaceChildren(
                buildTable(
                    [
                        {
                            label:
                                'Source Section',
                            key:
                                'section_code'
                        },
                        {
                            label:
                                'Year',
                            key:
                                'year_level'
                        },
                        {
                            label:
                                'Type',
                            key:
                                'section_type'
                        },
                        {
                            label:
                                'Target Section ID',
                            key:
                                'target_section_id'
                        },
                        {
                            label:
                                'Status',
                            render:
                                row =>
                                    mappingBadge(
                                        row.mapping_status
                                    )
                        }
                    ],
                    data.section_mapping
                )
            );

        $('cloneSubjectMapping')
            .replaceChildren(
                buildTable(
                    [
                        {
                            label:
                                'Section',
                            key:
                                'section_code'
                        },
                        {
                            label:
                                'Subject',
                            key:
                                'subject_code'
                        },
                        {
                            label:
                                'Target Pair ID',
                            key:
                                'target_section_subject_id'
                        },
                        {
                            label:
                                'Status',
                            render:
                                row =>
                                    mappingBadge(
                                        row.mapping_status
                                    )
                        }
                    ],
                    data.section_subject_mapping
                )
            );

        printButton.disabled =
            false;
    }


    /* ==========================================================
       READINESS REQUEST
    ========================================================== */

    async function checkReadiness() {

        if (
            saveCompleted
            || !sourceSelect.value
            || !targetSelect.value
        ) {
            return;
        }

        checkButton.disabled =
            true;

        previewButton.disabled =
            true;

        saveButton.disabled =
            true;

        previewResult.hidden =
            true;

        saveResult.hidden =
            true;

        setStatus(
            'Checking target mappings...',
            'loading'
        );

        try {

            const data =
                await getApi({
                    action:
                        'assess',

                    source_batch_id:
                        sourceSelect.value,

                    target_period_id:
                        targetSelect.value
                });

            renderReadiness(
                data
            );

            setStatus(
                data.blockers.length === 0
                ? (
                    'Readiness passed. '
                    + 'Clone Preview is available.'
                )
                : (
                    'Readiness completed with '
                    + data.blockers.length
                    + ' blocker(s).'
                ),
                data.blockers.length === 0
                ? 'success'
                : 'warning'
            );

        } catch (error) {

            readinessResult.hidden =
                true;

            previewResult.hidden =
                true;

            saveResult.hidden =
                true;

            setStatus(
                error.message,
                'error'
            );

        } finally {

            updateReadinessButton();
        }
    }


    /* ==========================================================
       PROPOSAL BADGES
    ========================================================== */

    function proposalStatus(
        proposal
    ) {

        const badge =
            node(
                'span',
                proposal.validation_status,
                'clone-map-status'
            );

        badge.dataset.status =
            proposal.validation_status
            === 'VALID'
            ? 'success'
            : 'problem';

        return badge;
    }


    function issueSummary(
        proposal
    ) {

        if (
            !proposal.issues
            || proposal.issues.length === 0
        ) {
            return 'No hard issue';
        }

        return proposal.issues
            .map(
                issue =>
                    issue.code
                        .replaceAll(
                            '_',
                            ' '
                        )
            )
            .join(', ');
    }


    /* ==========================================================
       PREVIEW RENDER
    ========================================================== */

    function renderPreview(
        data
    ) {

        preview =
            data;

        saveCompleted =
            false;

        saveResult.hidden =
            true;

        setProgress(3);

        previewResult.hidden =
            false;

        $('clonePreviewSubtitle')
            .textContent =
            (
                data.source.program_code
                + ' · '
                + data.source.academic_year
                + ' → '
                + data.target.academic_year
                + ' · Semester '
                + data.target.semester
            );


        const stats =
            $('clonePreviewStats');

        stats.replaceChildren();

        stat(
            stats,
            'Proposed meetings',
            data.summary.proposed_meetings
        );

        stat(
            stats,
            'Valid meetings',
            data.summary.valid_proposals
        );

        stat(
            stats,
            'Blocked meetings',
            data.summary.blocked_proposals
        );

        stat(
            stats,
            'Hard conflicts',
            data.summary.hard_conflicts
        );


        const findings =
            $('clonePreviewFindings');

        findings.replaceChildren();


        if (
            data.preview_passed
            && Number(
                data.summary.hard_conflicts
            ) === 0
            && Number(
                data.summary.blocked_proposals
            ) === 0
            && Number(
                data.summary.proposed_meetings
            ) > 0
        ) {

            const success =
                node(
                    'div',
                    null,
                    'clone-alert clone-alert--success'
                );

            success.append(
                node(
                    'strong',
                    'Clone Preview passed'
                ),

                node(
                    'p',
                    (
                        'All proposed meetings passed '
                        + 'the current hard validation checks. '
                        + 'Nothing has been saved yet.'
                    )
                )
            );

            findings.append(success);

            $('clonePreviewBadge')
                .textContent =
                '0 HARD CONFLICTS';

            $('clonePreviewBadge')
                .dataset.status =
                'success';

            saveButton.disabled =
                false;

            $('cloneSaveHelp')
                .textContent =
                (
                    'Preview passed. Saving will revalidate '
                    + 'the latest database state before '
                    + 'creating the target timetable.'
                );

        } else {

            const warning =
                node(
                    'div',
                    null,
                    'clone-alert clone-alert--warning'
                );

            warning.append(
                node(
                    'strong',
                    'Clone Preview blocked'
                ),

                node(
                    'p',
                    (
                        data.summary.hard_conflicts
                        + ' hard validation issue(s) '
                        + 'were found. The clone cannot '
                        + 'be saved.'
                    )
                )
            );

            findings.append(warning);

            data.global_issues
                .forEach(
                    issue => {

                        const alert =
                            node(
                                'div',
                                null,
                                'clone-alert clone-alert--warning'
                            );

                        alert.append(
                            node(
                                'strong',
                                issue.code
                                    .replaceAll(
                                        '_',
                                        ' '
                                    )
                            ),

                            node(
                                'p',
                                issue.message
                            )
                        );

                        findings.append(alert);
                    }
                );

            $('clonePreviewBadge')
                .textContent =
                (
                    data.summary.hard_conflicts
                    + ' HARD ISSUE'
                    + (
                        data.summary.hard_conflicts === 1
                        ? ''
                        : 'S'
                    )
                );

            $('clonePreviewBadge')
                .dataset.status =
                'warning';

            saveButton.disabled =
                true;

            $('cloneSaveHelp')
                .textContent =
                (
                    'Resolve all target-period hard blockers '
                    + 'before saving.'
                );
        }


        const inventory =
            $('cloneTargetInventory');

        inventory.replaceChildren();

        [
            [
                'Target students',
                data.summary.target_students
            ],
            [
                'Cluster / Major links',
                data.summary.target_major_links
            ],
            [
                'Active exam meetings',
                data.summary.target_active_exam_meetings
            ],
            [
                'Active special meetings',
                data.summary.target_active_special_meetings
            ]
        ]
            .forEach(
                ([label, value]) => {

                    const box =
                        node(
                            'div',
                            null,
                            'clone-summary-item'
                        );

                    box.append(
                        node(
                            'span',
                            label
                        ),

                        node(
                            'strong',
                            value
                        )
                    );

                    inventory.append(box);
                }
            );


        $('cloneMeetingCount')
            .textContent =
            (
                data.summary.proposed_meetings
                + ' proposed meeting(s)'
            );


        const table =
            buildTable(
                [
                    {
                        label:
                            'Source ID',
                        key:
                            'source_meeting_id'
                    },
                    {
                        label:
                            'Section',
                        key:
                            'section_code'
                    },
                    {
                        label:
                            'Subject',
                        key:
                            'subject_code'
                    },
                    {
                        label:
                            'Teacher',
                        key:
                            'teacher_name'
                    },
                    {
                        label:
                            'Day',
                        key:
                            'day_of_week'
                    },
                    {
                        label:
                            'Time',
                        render:
                            row =>
                                (
                                    timeLabel(
                                        row.start_time
                                    )
                                    + ' – '
                                    + timeLabel(
                                        row.end_time
                                    )
                                )
                    },
                    {
                        label:
                            'Mode',
                        key:
                            'delivery_mode'
                    },
                    {
                        label:
                            'Students',
                        render:
                            row =>
                                row.target_student_count === null
                                ? '—'
                                : (
                                    row.source_student_count
                                    + ' → '
                                    + row.target_student_count
                                )
                    },
                    {
                        label:
                            'Room',
                        render:
                            row => {
                                if (
                                    row.delivery_mode
                                    === 'ONLINE'
                                ) {
                                    return 'No room';
                                }

                                if (!row.room_name) {
                                    return '—';
                                }

                                return row.room_capacity === null
                                    ? row.room_name
                                    : (
                                        row.room_name
                                        + ' (cap '
                                        + row.room_capacity
                                        + ')'
                                    );
                            }
                    },
                    {
                        label:
                            'Status',
                        render:
                            row =>
                                proposalStatus(row)
                    },
                    {
                        label:
                            'Issue',
                        render:
                            row =>
                                issueSummary(row)
                    }
                ],
                data.proposals
            );

        $('cloneProposalTable')
            .replaceChildren(
                table
            );

        previewResult.scrollIntoView({
            behavior:
                'smooth',

            block:
                'start'
        });
    }


    /* ==========================================================
       GENERATE PREVIEW
    ========================================================== */

    async function generatePreview() {

        if (
            saveCompleted
            || !assessment
            || assessment.blockers.length > 0
            || !sourceSelect.value
            || !targetSelect.value
        ) {
            return;
        }

        previewButton.disabled =
            true;

        saveButton.disabled =
            true;

        saveResult.hidden =
            true;

        setStatus(
            'Building Clone Preview and validating proposed meetings...',
            'loading'
        );

        try {

            const data =
                await previewApi({
                    source_batch_id:
                        Number(
                            sourceSelect.value
                        ),

                    target_period_id:
                        Number(
                            targetSelect.value
                        )
                });

            renderPreview(
                data
            );

            setStatus(
                data.preview_passed
                ? (
                    'Clone Preview completed with '
                    + '0 hard conflicts.'
                )
                : (
                    'Clone Preview completed with '
                    + data.summary.hard_conflicts
                    + ' hard issue(s).'
                ),
                data.preview_passed
                ? 'success'
                : 'warning'
            );

        } catch (error) {

            previewResult.hidden =
                true;

            saveResult.hidden =
                true;

            preview =
                null;

            saveButton.disabled =
                true;

            setStatus(
                error.message,
                'error'
            );

        } finally {

            previewButton.disabled =
                saveCompleted
                || !assessment
                || assessment.blockers.length > 0;
        }
    }


    /* ==========================================================
       SAVE SUCCESS RENDER
    ========================================================== */

    function renderSaveSuccess(
        data
    ) {

        saveResult.hidden =
            false;

        saveResult.replaceChildren();

        const success =
            node(
                'div',
                null,
                'clone-alert clone-alert--success'
            );

        success.append(
            node(
                'strong',
                'Schedule clone saved successfully'
            ),

            node(
                'p',
                (
                    'New Batch #'
                    + data.new_batch_id
                    + ' was created for '
                    + data.target_academic_year
                    + ', Semester '
                    + data.semester
                    + '. '
                    + data.saved_meetings
                    + ' meetings were saved. '
                    + 'Source Batch #'
                    + data.source_batch_id
                    + ' was not modified.'
                )
            )
        );

        saveResult.append(success);

        $('clonePreviewBadge')
            .textContent =
            (
                'SAVED · BATCH #'
                + data.new_batch_id
            );

        $('clonePreviewBadge')
            .dataset.status =
            'success';

        $('cloneSaveHelp')
            .textContent =
            (
                'Clone completed successfully. '
                + 'The target timetable is now ACTIVE.'
            );

        saveButton.textContent =
            (
                'Saved · Batch #'
                + data.new_batch_id
            );

        saveButton.disabled =
            true;

        setProgress(4);
    }


    /* ==========================================================
       SAVE CLONE
    ========================================================== */

    async function saveClone() {

        if (
            saveCompleted
            || !preview
            || preview.preview_passed !== true
            || Number(
                preview.summary.hard_conflicts
            ) !== 0
            || Number(
                preview.summary.blocked_proposals
            ) !== 0
        ) {
            return;
        }


        const sourceBatchId =
            Number(
                sourceSelect.value
            );

        const targetPeriodId =
            Number(
                targetSelect.value
            );


        const confirmed =
            window.confirm(
                (
                    'Save this schedule clone?\n\n'
                    + 'Source Batch: #'
                    + sourceBatchId
                    + '\n'
                    + 'Target: '
                    + preview.target.academic_year
                    + ' · Semester '
                    + preview.target.semester
                    + '\n'
                    + 'Meetings: '
                    + preview.summary.proposed_meetings
                    + '\n\n'
                    + 'The backend will revalidate all data '
                    + 'before writing anything to the database.\n\n'
                    + 'The source timetable will remain unchanged.'
                )
            );


        if (!confirmed) {
            return;
        }


        saveButton.disabled =
            true;

        previewButton.disabled =
            true;

        checkButton.disabled =
            true;

        saveResult.hidden =
            true;


        setStatus(
            (
                'Revalidating latest database state '
                + 'and saving clone atomically...'
            ),
            'loading'
        );


        try {

            const data =
                await saveApi({
                    source_batch_id:
                        sourceBatchId,

                    target_period_id:
                        targetPeriodId,

                    confirmation:
                        'SAVE_CLONE'
                });


            saveCompleted =
                true;


            renderSaveSuccess(
                data
            );


            sourceSelect.disabled =
                true;

            targetSelect.disabled =
                true;

            checkButton.disabled =
                true;

            previewButton.disabled =
                true;

            saveButton.disabled =
                true;


            setStatus(
                (
                    'Schedule clone saved as Batch #'
                    + data.new_batch_id
                    + ' with '
                    + data.saved_meetings
                    + ' meetings.'
                ),
                'success'
            );


            /*
             * Refresh internal catalog so browser state
             * is aware of the new ACTIVE target batch.
             * We intentionally do not destroy the success
             * report currently visible to the user.
             */
            try {

                catalog =
                    await getApi({
                        action:
                            'catalog'
                    });

            } catch (refreshError) {

                console.warn(
                    'Catalog refresh after save failed:',
                    refreshError
                );
            }


            saveResult.scrollIntoView({
                behavior:
                    'smooth',

                block:
                    'center'
            });

        } catch (error) {

            saveCompleted =
                false;

            saveButton.disabled =
                false;

            previewButton.disabled =
                false;

            checkButton.disabled =
                false;


            const warning =
                node(
                    'div',
                    null,
                    'clone-alert clone-alert--warning'
                );

            warning.append(
                node(
                    'strong',
                    'Clone was not saved'
                ),

                node(
                    'p',
                    error.message
                )
            );

            saveResult.replaceChildren(
                warning
            );

            saveResult.hidden =
                false;


            setStatus(
                error.message,
                'error'
            );
        }
    }


    /* ==========================================================
       INITIALIZE
    ========================================================== */

    async function init() {

        setProgress(1);

        saveButton.disabled =
            true;

        try {

            catalog =
                await getApi({
                    action:
                        'catalog'
                });

            sourceSelect.replaceChildren(
                new Option(
                    'Select saved source timetable',
                    ''
                )
            );

            catalog.source_batches
                .forEach(
                    batch => {

                        sourceSelect.add(
                            new Option(
                                (
                                    batch.program_code
                                    + ' · '
                                    + batch.academic_year
                                    + ' · S'
                                    + batch.semester
                                    + ' · Batch #'
                                    + batch.batch_id
                                    + ' · '
                                    + batch.meeting_count
                                    + ' meetings'
                                ),
                                String(
                                    batch.batch_id
                                )
                            )
                        );
                    }
                );

            sourceSelect.disabled =
                catalog.source_batches.length
                === 0;


            sourceSelect.addEventListener(
                'change',
                populateTargets
            );


            targetSelect.addEventListener(
                'change',
                () => {

                    if (saveCompleted) {
                        return;
                    }

                    readinessResult.hidden =
                        true;

                    previewResult.hidden =
                        true;

                    saveResult.hidden =
                        true;

                    assessment =
                        null;

                    preview =
                        null;

                    previewButton.disabled =
                        true;

                    saveButton.disabled =
                        true;

                    printButton.disabled =
                        true;

                    updateReadinessButton();
                }
            );


            checkButton.addEventListener(
                'click',
                checkReadiness
            );


            previewButton.addEventListener(
                'click',
                generatePreview
            );


            saveButton.addEventListener(
                'click',
                saveClone
            );


            printButton.addEventListener(
                'click',
                () => {

                    if (
                        assessment
                        || preview
                        || saveCompleted
                    ) {
                        window.print();
                    }
                }
            );


            if (
                sourceSelect.disabled
            ) {

                setStatus(
                    'No ACTIVE source timetable exists.',
                    'warning'
                );

                return;
            }


            sourceSelect.value =
                String(
                    catalog
                        .source_batches[0]
                        .batch_id
                );


            populateTargets();

        } catch (error) {

            sourceSelect.disabled =
                true;

            targetSelect.disabled =
                true;

            checkButton.disabled =
                true;

            previewButton.disabled =
                true;

            saveButton.disabled =
                true;


            setStatus(
                error.message,
                'error'
            );
        }
    }


    /* ==========================================================
       SIDEBAR
    ========================================================== */

    const hamburgerBtn =
        $('hamburgerBtn');

    const sidebar =
        $('sidebar');


    if (
        hamburgerBtn
        && sidebar
    ) {

        hamburgerBtn.addEventListener(
            'click',
            () => {

                sidebar.classList.toggle(
                    'collapsed'
                );
            }
        );
    }


    init();

})();
</script>

</body>
</html>
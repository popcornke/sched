(() => {
    'use strict';

    const config =
        window.BCPSessionGuardConfig;

    if (
        !config
        || !config.sessionApi
        || !config.loginUrl
        || !config.csrfToken
        || !Number(config.userId)
        || !Number(config.loginTime)
    ) {
        return;
    }

    const modal =
        document.getElementById(
            'bcpSessionTimeout'
        );

    const title =
        document.getElementById(
            'bcpSessionTimeoutTitle'
        );

    const reasonText =
        document.getElementById(
            'bcpSessionTimeoutReason'
        );

    const countdownNode =
        document.getElementById(
            'bcpSessionTimeoutCountdown'
        );

    const loginNow =
        document.getElementById(
            'bcpSessionTimeoutLogin'
        );

    if (
        !modal
        || !title
        || !reasonText
        || !countdownNode
        || !loginNow
    ) {
        return;
    }

    const IDLE_LIMIT_MS =
        Number(
            config.idleLimitSeconds
        ) * 1000;

    const MAX_SESSION_MS =
        Number(
            config.maxSessionSeconds
        ) * 1000;

    const USER_ID =
        Number(config.userId);

    const LOGIN_TIME =
        Number(config.loginTime);

    const PREFIX =
        `bcp_session_guard:${USER_ID}:${LOGIN_TIME}`;

    const ACTIVITY_KEY =
        `${PREFIX}:activity`;

    const EXPIRED_KEY =
        `${PREFIX}:expired`;

    let latestActivity =
        Date.now();

    let lastActivityEventAt = 0;
    let lastHeartbeatAt = 0;

    let statusRequest = null;
    let heartbeatRequest = null;
    let resumeRequest = null;

    let resumeValidationRequired =
        document.hidden;

    let locked = false;
    let countdownFrame = null;

    function storageGet(key) {
        try {
            return localStorage.getItem(
                key
            );
        } catch (_) {
            return null;
        }
    }

    function storageSet(
        key,
        value
    ) {
        try {
            localStorage.setItem(
                key,
                value
            );
        } catch (_) {
            // Storage is optional.
        }
    }

    function storageRemove(key) {
        try {
            localStorage.removeItem(
                key
            );
        } catch (_) {
            // Storage is optional.
        }
    }

    function clearOldSessionGuardKeys() {
        try {
            const currentPrefix =
                `${PREFIX}:`;

            const remove = [];

            for (
                let i = 0;
                i < localStorage.length;
                i += 1
            ) {
                const key =
                    localStorage.key(i);

                if (
                    key
                    && key.startsWith(
                        'bcp_session_guard:'
                    )
                    && !key.startsWith(
                        currentPrefix
                    )
                ) {
                    remove.push(key);
                }
            }

            remove.forEach(
                key =>
                    localStorage.removeItem(
                        key
                    )
            );
        } catch (_) {
            // Cleanup is best effort only.
        }
    }

    clearOldSessionGuardKeys();

    function formatDuration(
        seconds
    ) {
        const total =
            Math.max(
                1,
                Math.round(
                    Number(seconds)
                    || 0
                )
            );

        if (
            total >= 3600
            && total % 3600 === 0
        ) {
            const hours =
                total / 3600;

            return `${hours} hour${hours === 1 ? '' : 's'}`;
        }

        if (
            total >= 60
            && total % 60 === 0
        ) {
            const minutes =
                total / 60;

            return `${minutes} minute${minutes === 1 ? '' : 's'}`;
        }

        return `${total} second${total === 1 ? '' : 's'}`;
    }

    const idleText =
        formatDuration(
            config.idleLimitSeconds
        );

    const maxText =
        formatDuration(
            config.maxSessionSeconds
        );

    const storedActivity =
        Number(
            storageGet(
                ACTIVITY_KEY
            )
        );

    if (
        Number.isFinite(
            storedActivity
        )
        && storedActivity > 0
    ) {
        latestActivity =
            Math.max(
                latestActivity,
                storedActivity
            );
    }

    storageSet(
        ACTIVITY_KEY,
        String(
            latestActivity
        )
    );

    loginNow.href =
        String(
            config.loginUrl
        );

    function copyFor(status) {
        switch (status) {
            case 'SESSION_IDLE_TIMEOUT':
            case 'IDLE_TIMEOUT':
                return {
                    title:
                        'Session Timeout',
                    message:
                        `Your session expired because there was no activity for ${idleText}. For your account security, please sign in again.`
                };

            case 'SESSION_MAX_LIFETIME':
            case 'MAX_SESSION_LIFETIME':
                return {
                    title:
                        'Maximum Session Reached',
                    message:
                        `Your session reached the maximum allowed duration of ${maxText}. Please sign in again to continue.`
                };

            case 'SESSION_REVOKED':
                return {
                    title:
                        'Session Ended',
                    message:
                        'Your account session is no longer active. Please sign in again to continue.'
                };

            default:
                return {
                    title:
                        'Session Expired',
                    message:
                        'Your session is no longer valid. Please sign in again to continue.'
                };
        }
    }

    function redirectLogin() {
        if (
            countdownFrame !== null
        ) {
            cancelAnimationFrame(
                countdownFrame
            );

            countdownFrame = null;
        }

        window.location.replace(
            String(
                config.loginUrl
            )
        );
    }

    function rememberExpired(
        status,
        message = ''
    ) {
        storageSet(
            EXPIRED_KEY,
            JSON.stringify({
                status,
                message,
                at: Date.now(),
            })
        );
    }

    function showTimeout(
        status,
        serverMessage = ''
    ) {
        if (locked) {
            return;
        }

        /*
         * Confirmed expiration may happen while hidden,
         * but the visual countdown must wait until return.
         */
        if (document.hidden) {
            rememberExpired(
                status,
                serverMessage
            );

            resumeValidationRequired =
                true;

            return;
        }

        locked = true;

        rememberExpired(
            status,
            serverMessage
        );

        const copy =
            copyFor(status);

        title.textContent =
            copy.title;

        reasonText.textContent =
            serverMessage
            || copy.message;

        modal.hidden = false;

        modal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.classList.add(
            'bcp-session-locked'
        );

        loginNow.focus();

        const counter =
            countdownNode.closest(
                '.bcp-session-timeout__counter'
            );

        const durationMs = 5000;

        countdownNode.textContent =
            '5';

        counter?.style.setProperty(
            '--bcp-session-progress',
            '1'
        );

        const startedAt =
            performance.now();

        function animate(now) {
            const remainingMs =
                Math.max(
                    0,
                    durationMs
                    - (
                        now - startedAt
                    )
                );

            const progress =
                remainingMs
                / durationMs;

            countdownNode.textContent =
                String(
                    Math.max(
                        0,
                        Math.ceil(
                            remainingMs
                            / 1000
                        )
                    )
                );

            counter?.style.setProperty(
                '--bcp-session-progress',
                String(
                    Math.max(
                        0,
                        Math.min(
                            1,
                            progress
                        )
                    )
                )
            );

            if (
                remainingMs <= 0
            ) {
                countdownNode.textContent =
                    '0';

                counter?.style.setProperty(
                    '--bcp-session-progress',
                    '0'
                );

                redirectLogin();
                return;
            }

            countdownFrame =
                requestAnimationFrame(
                    animate
                );
        }

        countdownFrame =
            requestAnimationFrame(
                animate
            );
    }

    loginNow.addEventListener(
        'click',
        event => {
            event.preventDefault();
            redirectLogin();
        }
    );

    async function parseResponse(
        response
    ) {
        let data = null;

        try {
            data =
                await response.json();
        } catch (_) {
            data = null;
        }

        const status =
            String(
                data?.status
                || ''
            );

        if (
            response.status === 401
            || [
                'SESSION_IDLE_TIMEOUT',
                'SESSION_MAX_LIFETIME',
                'SESSION_REVOKED',
                'AUTH_REQUIRED',
            ].includes(status)
        ) {
            showTimeout(
                status
                || 'AUTH_REQUIRED',
                String(
                    data?.message
                    || ''
                )
            );

            return null;
        }

        if (
            !response.ok
            || data?.success !== true
        ) {
            throw new Error(
                data?.message
                || data?.status
                || 'Session request failed.'
            );
        }

        /*
         * IMPORTANT:
         * A successful server response proves the session is active.
         * Any stale local expiration marker must be removed.
         */
        storageRemove(
            EXPIRED_KEY
        );

        return data;
    }

    async function checkStatus() {
        if (
            locked
            || statusRequest
        ) {
            return statusRequest;
        }

        statusRequest =
            (async () => {
                try {
                    const response =
                        await fetch(
                            config.sessionApi,
                            {
                                method:
                                    'GET',
                                credentials:
                                    'same-origin',
                                cache:
                                    'no-store',
                                headers: {
                                    'Accept':
                                        'application/json',
                                },
                            }
                        );

                    return await parseResponse(
                        response
                    );
                } catch (error) {
                    console.warn(
                        'BCP session status unavailable:',
                        error
                    );

                    return null;
                } finally {
                    statusRequest =
                        null;
                }
            })();

        return statusRequest;
    }

    async function sendActivity() {
        if (
            locked
            || heartbeatRequest
            || document.hidden
            || resumeValidationRequired
        ) {
            return heartbeatRequest;
        }

        lastHeartbeatAt =
            Date.now();

        heartbeatRequest =
            (async () => {
                try {
                    const response =
                        await fetch(
                            config.sessionApi,
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
                                        'application/json',
                                },
                                body:
                                    JSON.stringify({
                                        action:
                                            'activity',
                                        csrf_token:
                                            config.csrfToken,
                                    }),
                            }
                        );

                    return await parseResponse(
                        response
                    );
                } catch (error) {
                    console.warn(
                        'BCP activity heartbeat unavailable:',
                        error
                    );

                    return null;
                } finally {
                    heartbeatRequest =
                        null;
                }
            })();

        return heartbeatRequest;
    }

    function recordActivity() {
        if (
            locked
            || document.hidden
            || resumeValidationRequired
        ) {
            return;
        }

        const now =
            Date.now();

        if (
            now - lastActivityEventAt
            < 5000
        ) {
            return;
        }

        lastActivityEventAt =
            now;

        latestActivity =
            now;

        storageSet(
            ACTIVITY_KEY,
            String(now)
        );

        if (
            now - lastHeartbeatAt
            >= 30000
        ) {
            void sendActivity();
        }
    }

    [
        'pointerdown',
        'pointermove',
        'keydown',
        'touchstart',
        'scroll',
    ].forEach(
        eventName => {
            window.addEventListener(
                eventName,
                recordActivity,
                {
                    passive:
                        eventName
                        !== 'keydown',
                    capture: true,
                }
            );
        }
    );

    async function validateAfterReturn() {
        if (
            locked
            || document.hidden
            || resumeRequest
        ) {
            return resumeRequest;
        }

        resumeValidationRequired =
            true;

        resumeRequest =
            (async () => {
                try {
                    /*
                     * NEVER trust localStorage as proof of expiration.
                     * PHP must validate first.
                     */
                    const data =
                        await checkStatus();

                    if (
                        locked
                        || !data
                        || document.hidden
                    ) {
                        return;
                    }

                    resumeValidationRequired =
                        false;

                    latestActivity =
                        Date.now();

                    storageSet(
                        ACTIVITY_KEY,
                        String(
                            latestActivity
                        )
                    );

                    /*
                     * Returning to a still-valid application session
                     * counts as legitimate activity only AFTER PHP
                     * confirmed that it was not already expired.
                     */
                    await sendActivity();
                } finally {
                    resumeRequest =
                        null;
                }
            })();

        return resumeRequest;
    }

    document.addEventListener(
        'visibilitychange',
        () => {
            if (document.hidden) {
                resumeValidationRequired =
                    true;

                return;
            }

            void validateAfterReturn();
        }
    );

    window.addEventListener(
        'focus',
        () => {
            if (
                !document.hidden
                && resumeValidationRequired
                && !locked
            ) {
                void validateAfterReturn();
            }
        }
    );

    window.addEventListener(
        'storage',
        event => {
            if (
                event.key === ACTIVITY_KEY
                && event.newValue
            ) {
                const value =
                    Number(
                        event.newValue
                    );

                if (
                    Number.isFinite(
                        value
                    )
                    && value > 0
                ) {
                    latestActivity =
                        Math.max(
                            latestActivity,
                            value
                        );
                }

                return;
            }

            if (
                event.key === EXPIRED_KEY
                && event.newValue
            ) {
                /*
                 * Another same-site tab says it expired.
                 * Do NOT trust that marker by itself.
                 * Validate this tab's PHP session first.
                 */
                resumeValidationRequired =
                    true;

                if (!document.hidden) {
                    void validateAfterReturn();
                }
            }
        }
    );

    /*
     * Local prediction merely triggers a PHP validation.
     */
    window.setInterval(
        () => {
            if (
                locked
                || document.hidden
                || resumeValidationRequired
            ) {
                return;
            }

            const shared =
                Number(
                    storageGet(
                        ACTIVITY_KEY
                    )
                );

            const lastActivity =
                Number.isFinite(shared)
                && shared > 0
                    ? Math.max(
                        latestActivity,
                        shared
                    )
                    : latestActivity;

            const now =
                Date.now();

            const idleDue =
                now - lastActivity
                >= IDLE_LIMIT_MS;

            const maxDue =
                now
                >= (
                    LOGIN_TIME
                    * 1000
                    + MAX_SESSION_MS
                );

            if (
                idleDue
                || maxDue
            ) {
                void checkStatus();
            }
        },
        1000
    );

    /*
     * Passive status sync only while visible.
     */
    window.setInterval(
        () => {
            if (
                !locked
                && !document.hidden
                && !resumeValidationRequired
            ) {
                void checkStatus();
            }
        },
        15000
    );

    /*
     * Fresh page:
     * always ask PHP first. Never show a modal from stale storage.
     */
    if (document.hidden) {
        resumeValidationRequired =
            true;
    } else {
        resumeValidationRequired =
            false;

        void checkStatus();
    }

    window.BCPSessionGuard =
        Object.freeze({
            checkStatus,
            recordActivity,
        });
})();

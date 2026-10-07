# Session Timeout Loop Fix

This replaces the previous timeout package.

## Replace
- `app/shared/auth.php`
- `app/includes/sidebar.php`
- `app/api/session-status.php`
- `app/assets/js/session-guard.js`
- `app/assets/css/session-timeout.css`

## Add/replace at project root
- `session-expired.php`

## Important fix
The previous JS could trust a stale `localStorage` expiration marker and show
"Session Expired" before asking PHP whether the NEW login session was valid.

This version:
- always validates with PHP first on a fresh page;
- automatically removes stale session-guard localStorage keys;
- clears the current expiration marker whenever PHP confirms SESSION_ACTIVE;
- removes the timeout-reason cookie fallback that could cause redirect loops;
- never lets Ctrl+F5/localStorage decide authentication;
- preserves the background-tab rule and draining 5-second ring.

## One-time cleanup if the old broken version already ran
Open DevTools Console on the site and run:

```js
Object.keys(localStorage)
  .filter(key => key.startsWith('bcp_session_guard:'))
  .forEach(key => localStorage.removeItem(key));

location.href = '/sched/index.php';
```

On Railway, if the app is mounted at the domain root, use:

```js
Object.keys(localStorage)
  .filter(key => key.startsWith('bcp_session_guard:'))
  .forEach(key => localStorage.removeItem(key));

location.href = '/index.php';
```

After installing this fixed version, that manual cleanup should not be needed again.

## Quick test
Temporarily:
```php
const SESSION_IDLE_LIMIT = 20;
```

Then log in, switch tabs for >20 seconds, return to the app.
The timeout modal should start only after return.

Finally restore:
```php
const SESSION_IDLE_LIMIT = 600;
```

# BCP Admin Account Settings — Installation

This implementation is based on the current `popcornke/sched` authentication flow.

## Features implemented

- ADMIN-only Account Settings page.
- Existing dashboard avatar route `../auth/account.php` now becomes functional.
- Username change with current-password confirmation.
- Verified email change:
  - sends a 6-digit OTP to the NEW email;
  - does not replace the existing login email until verification succeeds;
  - uses the existing Resend mailer and `OTP_SECRET`.
- Password change with current-password confirmation.
- 30-day ADMIN password expiration.
- 7-day expiration warning on Account Settings.
- Forced Account Settings redirect after expiration.
- Expired ADMIN can access only Account Settings and logout; other protected pages/APIs are blocked.
- Last 3 prior password hashes cannot be reused.
- 10-minute idle timeout remains unchanged.
- 8-hour maximum session remains unchanged.
- CSRF protection, prepared statements, password hashing and session regeneration.

## Exact destination paths

Copy these files into the project:

1. `database/migrations/018_admin_account_security.sql`
2. `app/shared/auth.php` — REPLACE the existing file.
3. `app/auth/account.php` — NEW file/folder.
4. `app/api/account-settings.php` — NEW file.
5. `app/assets/css/account-settings.css` — NEW file.

No dashboard edit is required for the Account Settings link because the current dashboard already points its avatar to:

`../auth/account.php`

## IMPORTANT deployment order

### 1. Database first

Run:

`database/migrations/018_admin_account_security.sql`

in the Railway database and local `bcp_scheduling` database.

Do this BEFORE replacing `app/shared/auth.php`.

The migration gives existing ADMIN/SCHEDULER accounts a fresh 30-day window instead of expiring them immediately.

### 2. Copy the PHP/CSS files

After migration succeeds, copy the other four files.

### 3. Required Railway environment values

The existing login OTP already requires these. Email-change verification reuses them:

- `OTP_SECRET`
- `RESEND_API_KEY`
- `RESEND_FROM_EMAIL`

`OTP_SECRET` must be at least 32 characters.

## Test sequence

1. Login as ADMIN and finish normal email OTP.
2. Click the avatar in the dashboard topbar.
3. Confirm Account Settings loads.
4. Test username change using an intentionally wrong current password — it must fail.
5. Test username change using the real current password — it must succeed.
6. Request an email change:
   - current login email must remain unchanged before OTP confirmation;
   - new address must receive the 6-digit OTP;
   - after successful OTP verification, the new email becomes verified and saved.
7. Change password:
   - wrong current password must fail;
   - mismatched confirmation must fail;
   - weak password must fail;
   - current password reuse must fail;
   - recent password reuse must fail;
   - valid new password must succeed.
8. After password change, Account Settings redirects to Dashboard.
9. Confirm the new password works at next login.
10. Confirm 10-minute idle and 8-hour maximum-session behavior still works.

## Quick expiration test for defense

Do this only in a test/demo database:

```sql
UPDATE auth_users
SET password_changed_at = DATE_SUB(NOW(), INTERVAL 31 DAY)
WHERE role = 'ADMIN';
```

Logout and log back in. After OTP, any protected admin page should redirect to:

`app/auth/account.php?required=1`

After successfully changing the password, access is restored.

To test the warning state:

```sql
UPDATE auth_users
SET password_changed_at = DATE_SUB(NOW(), INTERVAL 24 DAY)
WHERE role = 'ADMIN';
```

That leaves roughly six days before expiration and shows the warning on Account Settings.

## Restore a fresh 30-day window

```sql
UPDATE auth_users
SET password_changed_at = NOW()
WHERE role = 'ADMIN';
```

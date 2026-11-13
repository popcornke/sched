ACADEMIC OFFICE EMAIL OTP PATCH (for sched 2026-11-11 ZIP)

Changes:
1. index.php: Academic Office no longer bypasses email OTP. Staff remains password-only.
2. app/shared/login-otp.php: allows ACADEMIC_OFFICE; redirects to its dashboard.
3. verify-login-otp.php: refuse completion if role changes after code issuance.
4. tools/create-academic-office.php: messaging updated; new accounts still require verified email provisioning.

LOCAL INSTALL (PowerShell, from C:\xampp\htdocs\sched):
- Back up original four files before replacing them.
- Copy patch folder files into the identical relative paths under sched.
- Use php -l to lint modified PHP files.
- Run SQL in phpMyAdmin to inspect Academic Office account email:
  SELECT user_id, username, email, email_verified_at, role, is_active FROM auth_users WHERE role='ACADEMIC_OFFICE';
- Set Academic Office recipient to authorized inbox only, after confirming ownership.
  Example for an actual account username 'acad_office' (replace with real username):
  UPDATE auth_users SET email='gptf135@gmail.com' WHERE username='acad_office' AND role='ACADEMIC_OFFICE';
- Ensure the email has been legitimately verified. Do not set email_verified_at blindly.
- Mail delivery uses existing RESEND_API_KEY and RESEND_FROM_EMAIL; OTP_SECRET minimum 32 chars is required.
- Log out then sign in via http://localhost/sched/index.php as Academic Office.

TEST: password -> 6-digit OTP email -> verify-login-otp.php -> Academic Office dashboard.
No database migration is needed if auth_login_otps exists already for Admin OTP.

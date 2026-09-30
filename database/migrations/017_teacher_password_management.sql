-- BCP Teacher Portal - teacher accounts + password management
-- Login username: teachers.employee_no
-- Initial password: Teacher1234567
-- SECURITY: plaintext passwords are NEVER stored. auth_users.password_hash stores a one-way bcrypt hash.
-- Existing teacher passwords are NOT reset when this migration is rerun.

-- 1) Ensure the authentication role supports TEACHER.
ALTER TABLE auth_users
    MODIFY role ENUM('ADMIN','SCHEDULER','TEACHER') NOT NULL DEFAULT 'SCHEDULER';

-- 2) Ensure teacher account mapping exists.
CREATE TABLE IF NOT EXISTS teacher_accounts (
    user_id INT(10) UNSIGNED NOT NULL,
    teacher_id INT(10) UNSIGNED NOT NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    password_changed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_teacher_account_teacher (teacher_id),
    CONSTRAINT fk_teacher_account_user
        FOREIGN KEY (user_id) REFERENCES auth_users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_teacher_account_teacher
        FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If teacher_accounts already existed from the first Teacher Portal migration,
-- add the password-management columns without rebuilding the table.
ALTER TABLE teacher_accounts
    ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 1 AFTER teacher_id,
    ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL AFTER must_change_password;

-- bcrypt hash for exactly: Teacher1234567
SET @teacher_default_password_hash = '$2y$12$gv/9F1OoQwdqbZTvlIOf9.cAHzU0pqOReqyVwZ/g7k.j2sO3kg2d6';

-- 3) Create only MISSING teacher login accounts.
-- Existing TEACHER account passwords are intentionally preserved.
INSERT INTO auth_users (
    username,
    password_hash,
    role,
    is_active,
    failed_attempts,
    locked_until
)
SELECT
    t.employee_no,
    @teacher_default_password_hash,
    'TEACHER',
    CASE WHEN t.status = 'ACTIVE' THEN 1 ELSE 0 END,
    0,
    NULL
FROM teachers t
LEFT JOIN auth_users au ON au.username = t.employee_no
WHERE au.user_id IS NULL;

-- 4) Link every TEACHER account to its exact teacher record if not linked yet.
INSERT INTO teacher_accounts (user_id, teacher_id, must_change_password, password_changed_at)
SELECT
    au.user_id,
    t.teacher_id,
    1,
    NULL
FROM teachers t
JOIN auth_users au
  ON au.username = t.employee_no
 AND au.role = 'TEACHER'
LEFT JOIN teacher_accounts ta_teacher ON ta_teacher.teacher_id = t.teacher_id
LEFT JOIN teacher_accounts ta_user ON ta_user.user_id = au.user_id
WHERE ta_teacher.teacher_id IS NULL
  AND ta_user.user_id IS NULL;

-- 5) Keep TEACHER account enabled state synchronized with teacher status.
-- This does NOT change password_hash.
UPDATE auth_users au
JOIN teacher_accounts ta ON ta.user_id = au.user_id
JOIN teachers t ON t.teacher_id = ta.teacher_id
SET
    au.role = 'TEACHER',
    au.is_active = CASE WHEN t.status = 'ACTIVE' THEN 1 ELSE 0 END
WHERE au.role = 'TEACHER';

-- 6) Verification.
-- password_hash should contain values beginning with $2y$ (bcrypt), never Teacher1234567 itself.
SELECT
    t.teacher_id,
    t.employee_no AS username,
    t.teacher_name,
    au.user_id,
    au.role,
    au.is_active,
    LEFT(au.password_hash, 7) AS password_hash_prefix,
    ta.must_change_password,
    ta.password_changed_at,
    CASE
        WHEN au.password_hash = 'Teacher1234567' THEN 'UNSAFE PLAINTEXT - FIX REQUIRED'
        WHEN au.password_hash LIKE '$2y$%' THEN 'PASSWORD HASH READY'
        ELSE 'CHECK PASSWORD HASH'
    END AS password_status
FROM teachers t
LEFT JOIN teacher_accounts ta ON ta.teacher_id = t.teacher_id
LEFT JOIN auth_users au ON au.user_id = ta.user_id
ORDER BY t.teacher_id;

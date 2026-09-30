-- BCP Teacher Portal - schema + initial teacher accounts
-- Username: teachers.employee_no
-- Initial login password for every teacher: Teacher1234567
-- IMPORTANT: The database stores only a bcrypt password hash, never the plaintext password.
-- This script is intended for initial/demo setup. Do not rerun it after teachers start changing passwords,
-- because it intentionally resets every mapped TEACHER account back to the initial password.

-- 1) Allow TEACHER accounts in the existing authentication table.
ALTER TABLE auth_users
    MODIFY role ENUM('ADMIN','SCHEDULER','TEACHER') NOT NULL DEFAULT 'SCHEDULER';

-- 2) One auth account maps to exactly one teacher record.
CREATE TABLE IF NOT EXISTS teacher_accounts (
    user_id INT(10) UNSIGNED NOT NULL,
    teacher_id INT(10) UNSIGNED NOT NULL,
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

-- bcrypt hash generated with PHP password_hash('Teacher1234567', PASSWORD_DEFAULT)
SET @teacher_default_password_hash = '$2y$12$gv/9F1OoQwdqbZTvlIOf9.cAHzU0pqOReqyVwZ/g7k.j2sO3kg2d6';

-- 3) Create a TEACHER auth account for every teacher that does not already have one.
--    employee_no is already unique in the teachers table and becomes the login username.
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
LEFT JOIN teacher_accounts ta
       ON ta.teacher_id = t.teacher_id
LEFT JOIN auth_users existing_user
       ON existing_user.username = t.employee_no
WHERE ta.teacher_id IS NULL
  AND existing_user.user_id IS NULL;

-- 4) Link every generated TEACHER login back to its exact teacher_id.
INSERT INTO teacher_accounts (user_id, teacher_id)
SELECT
    au.user_id,
    t.teacher_id
FROM teachers t
JOIN auth_users au
  ON au.username = t.employee_no
 AND au.role = 'TEACHER'
LEFT JOIN teacher_accounts ta_teacher
       ON ta_teacher.teacher_id = t.teacher_id
LEFT JOIN teacher_accounts ta_user
       ON ta_user.user_id = au.user_id
WHERE ta_teacher.teacher_id IS NULL
  AND ta_user.user_id IS NULL;

-- 5) Initial setup rule: all mapped teacher accounts use the requested default password.
--    ACTIVE teachers can log in; INACTIVE teachers keep an account but are disabled.
UPDATE auth_users au
JOIN teacher_accounts ta ON ta.user_id = au.user_id
JOIN teachers t ON t.teacher_id = ta.teacher_id
SET
    au.password_hash = @teacher_default_password_hash,
    au.role = 'TEACHER',
    au.is_active = CASE WHEN t.status = 'ACTIVE' THEN 1 ELSE 0 END,
    au.failed_attempts = 0,
    au.locked_until = NULL;

-- 6) Verification: every teacher should appear here with a linked TEACHER account.
SELECT
    t.teacher_id,
    t.employee_no AS username,
    t.teacher_name,
    t.status AS teacher_status,
    au.user_id,
    au.role,
    au.is_active,
    CASE
        WHEN ta.teacher_id IS NOT NULL AND au.user_id IS NOT NULL THEN 'ACCOUNT READY'
        ELSE 'ACCOUNT MISSING'
    END AS account_status
FROM teachers t
LEFT JOIN teacher_accounts ta ON ta.teacher_id = t.teacher_id
LEFT JOIN auth_users au ON au.user_id = ta.user_id
ORDER BY t.teacher_id;

-- 7) Collision check. Expected result: zero rows.
--    If a non-TEACHER account already uses a teacher employee_no, it is NOT overwritten automatically.
SELECT
    t.teacher_id,
    t.employee_no,
    t.teacher_name,
    au.user_id,
    au.role AS conflicting_role
FROM teachers t
JOIN auth_users au ON au.username = t.employee_no
LEFT JOIN teacher_accounts ta ON ta.teacher_id = t.teacher_id
WHERE ta.teacher_id IS NULL
   OR au.role <> 'TEACHER';

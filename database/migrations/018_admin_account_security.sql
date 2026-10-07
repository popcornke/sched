-- ============================================================
-- 018_admin_account_security.sql
-- BCP Admin Account Settings + 30-Day Password Rotation
--
-- Run this migration BEFORE deploying the modified auth.php.
-- Safe for existing ADMIN accounts: existing admin passwords
-- start a fresh 30-day window on first migration.
-- ============================================================

SET @schema_name := DATABASE();

-- ------------------------------------------------------------
-- 1. auth_users.password_changed_at
-- ------------------------------------------------------------

SET @has_password_changed_at := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'auth_users'
      AND COLUMN_NAME = 'password_changed_at'
);

SET @sql := IF(
    @has_password_changed_at = 0,
    'ALTER TABLE auth_users ADD COLUMN password_changed_at DATETIME NULL AFTER password_hash',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Existing ADMIN/SCHEDULER accounts receive a fresh 30-day window.
-- TEACHER password management stays in teacher_accounts.
UPDATE auth_users
SET password_changed_at = NOW()
WHERE role IN ('ADMIN', 'SCHEDULER')
  AND password_changed_at IS NULL;


-- ------------------------------------------------------------
-- 2. Password history
-- Keeps prior password hashes so recent passwords cannot be reused.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS auth_password_history (
    history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (history_id),
    KEY idx_auth_password_history_user (
        user_id,
        changed_at,
        history_id
    ),

    CONSTRAINT fk_auth_password_history_user
        FOREIGN KEY (user_id)
        REFERENCES auth_users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 3. Verified email-change OTPs
-- Admin email is used for login 2FA, so email changes must be
-- confirmed at the NEW address before auth_users is updated.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS auth_email_change_otps (
    email_change_otp_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    new_email VARCHAR(254) NOT NULL,
    otp_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    consumed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (email_change_otp_id),

    KEY idx_email_change_user_created (
        user_id,
        created_at
    ),

    KEY idx_email_change_active (
        user_id,
        consumed_at,
        expires_at
    ),

    CONSTRAINT fk_email_change_user
        FOREIGN KEY (user_id)
        REFERENCES auth_users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 4. Verification
-- ------------------------------------------------------------

SELECT
    user_id,
    username,
    email,
    role,
    is_active,
    password_changed_at,
    DATE_ADD(password_changed_at, INTERVAL 30 DAY) AS password_expires_at
FROM auth_users
WHERE role IN ('ADMIN', 'SCHEDULER')
ORDER BY user_id;

SHOW TABLES LIKE 'auth_password_history';
SHOW TABLES LIKE 'auth_email_change_otps';

-- Run once after 027_super_admin.sql. Per-user separate backup credentials.
CREATE TABLE IF NOT EXISTS super_admin_backup_security (
 user_id INT UNSIGNED NOT NULL PRIMARY KEY,
 password_hash VARCHAR(255) NOT NULL,
 failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 locked_until DATETIME NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_backup_sa_user FOREIGN KEY (user_id) REFERENCES auth_users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS super_admin_backup_reset_otps (
 user_id INT UNSIGNED NOT NULL PRIMARY KEY,
 code_hash VARCHAR(64) NOT NULL,
 expires_at DATETIME NOT NULL,
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_backup_otp_user FOREIGN KEY (user_id) REFERENCES auth_users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

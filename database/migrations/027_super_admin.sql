-- ============================================================
-- 027_super_admin.sql
-- Super Administrator role + global audit log foundation.
-- Apply once to LOCAL and Railway databases.
-- ============================================================

ALTER TABLE auth_users
    MODIFY COLUMN role ENUM(
        'ADMIN',
        'SCHEDULER',
        'TEACHER',
        'STAFF',
        'ACADEMIC_OFFICE',
        'SUPER_ADMIN'
    ) NOT NULL DEFAULT 'SCHEDULER';

CREATE TABLE IF NOT EXISTS system_audit_logs (
    audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id INT UNSIGNED NULL,
    actor_username_snapshot VARCHAR(80) NULL,
    actor_role_snapshot VARCHAR(40) NULL,
    action_type VARCHAR(80) NOT NULL,
    module VARCHAR(80) NOT NULL,
    target_type VARCHAR(80) NULL,
    target_id VARCHAR(120) NULL,
    description TEXT NULL,
    metadata_json LONGTEXT NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (audit_id),
    KEY idx_audit_created (created_at),
    KEY idx_audit_actor (actor_user_id, created_at),
    KEY idx_audit_action (action_type, created_at),
    KEY idx_audit_module (module, created_at),

    CONSTRAINT fk_system_audit_actor
        FOREIGN KEY (actor_user_id)
        REFERENCES auth_users(user_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Academic Office only: immutable reporting snapshots. No modifications to scheduling or Admin tables.
CREATE TABLE IF NOT EXISTS academic_office_saved_reports (
 report_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 report_type ENUM('WEEKLY','MONTHLY') NOT NULL,
 period_start DATE NOT NULL,
 period_end DATE NOT NULL,
 created_by INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 metrics_json LONGTEXT NOT NULL,
 breakdown_json LONGTEXT NOT NULL,
 CONSTRAINT fk_acad_report_user FOREIGN KEY (created_by) REFERENCES auth_users(user_id),
 INDEX idx_acad_reports_created (created_at),
 INDEX idx_acad_reports_period (report_type,period_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

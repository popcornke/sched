-- School-wide exam approvals. Does not alter or delete class/exam schedules.
CREATE TABLE IF NOT EXISTS academic_exam_requests (
 request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 academic_period_id INT UNSIGNED NOT NULL,
 submitted_by INT UNSIGNED NOT NULL,
 reviewed_by INT UNSIGNED NULL,
 status ENUM('PENDING_REVIEW','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING_REVIEW',
 payload_json LONGTEXT NOT NULL,
 payload_hash CHAR(64) NOT NULL,
 remarks TEXT NULL,
 submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_at TIMESTAMP NULL,
 FOREIGN KEY (academic_period_id) REFERENCES academic_periods(academic_period_id),
 FOREIGN KEY (submitted_by) REFERENCES auth_users(user_id),
 FOREIGN KEY (reviewed_by) REFERENCES auth_users(user_id),
 KEY idx_exam_requests_period (academic_period_id,status,request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

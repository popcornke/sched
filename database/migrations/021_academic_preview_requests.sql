-- Apply after 020_academic_office_approval.sql. No existing schedule data is deleted.
CREATE TABLE IF NOT EXISTS academic_preview_requests (
 request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 academic_period_id INT UNSIGNED NOT NULL,
 program_id INT UNSIGNED NOT NULL,
 submitted_by INT UNSIGNED NOT NULL,
 reviewed_by INT UNSIGNED NULL,
 status ENUM('PENDING_REVIEW','REJECTED','APPROVED') NOT NULL DEFAULT 'PENDING_REVIEW',
 payload_json LONGTEXT NOT NULL,
 payload_hash CHAR(64) NOT NULL,
 input_hash CHAR(64) NOT NULL,
 reason TEXT NULL,
 batch_id BIGINT UNSIGNED NULL,
 submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_at TIMESTAMP NULL,
 FOREIGN KEY (academic_period_id) REFERENCES academic_periods(academic_period_id),
 FOREIGN KEY (program_id) REFERENCES programs(program_id),
 FOREIGN KEY (submitted_by) REFERENCES auth_users(user_id),
 FOREIGN KEY (reviewed_by) REFERENCES auth_users(user_id),
 FOREIGN KEY (batch_id) REFERENCES schedule_batches(batch_id),
 KEY idx_preview_queue(status,academic_period_id,program_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

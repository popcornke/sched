-- Back up your database before applying. Run once in each environment.
ALTER TABLE auth_users MODIFY COLUMN role ENUM('ADMIN','SCHEDULER','TEACHER','STAFF','ACADEMIC_OFFICE') NOT NULL DEFAULT 'SCHEDULER';
CREATE TABLE IF NOT EXISTS academic_approval_requests (
 approval_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 schedule_type ENUM('CLASS','EXAM') NOT NULL,
 class_batch_id BIGINT UNSIGNED NULL,
 exam_batch_id BIGINT UNSIGNED NULL,
 academic_period_id INT UNSIGNED NOT NULL,
 program_id INT UNSIGNED NOT NULL,
 revision_no INT UNSIGNED NOT NULL DEFAULT 1,
 snapshot_hash CHAR(64) NOT NULL,
 status ENUM('PENDING_REVIEW','APPROVED','RETURNED','PUBLISHED','SUPERSEDED') NOT NULL DEFAULT 'PENDING_REVIEW',
 submitted_by INT UNSIGNED NOT NULL,
 reviewed_by INT UNSIGNED NULL,
 submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_at TIMESTAMP NULL,
 reviewer_remarks TEXT NULL,
 CONSTRAINT fk_aa_class FOREIGN KEY (class_batch_id) REFERENCES schedule_batches(batch_id),
 CONSTRAINT fk_aa_exam FOREIGN KEY (exam_batch_id) REFERENCES exam_batches(exam_batch_id),
 CONSTRAINT fk_aa_period FOREIGN KEY (academic_period_id) REFERENCES academic_periods(academic_period_id),
 CONSTRAINT fk_aa_program FOREIGN KEY (program_id) REFERENCES programs(program_id),
 CONSTRAINT fk_aa_submitter FOREIGN KEY (submitted_by) REFERENCES auth_users(user_id),
 CONSTRAINT fk_aa_reviewer FOREIGN KEY (reviewed_by) REFERENCES auth_users(user_id),
 CONSTRAINT chk_aa_batch CHECK ((schedule_type='CLASS' AND class_batch_id IS NOT NULL AND exam_batch_id IS NULL) OR (schedule_type='EXAM' AND exam_batch_id IS NOT NULL AND class_batch_id IS NULL)),
 KEY idx_aa_queue (status,academic_period_id,program_id),
 KEY idx_aa_batch_class(class_batch_id),
 KEY idx_aa_batch_exam(exam_batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS academic_approval_actions (
 action_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 approval_id BIGINT UNSIGNED NOT NULL,
 actor_id INT UNSIGNED NOT NULL,
 action_type ENUM('SUBMITTED','APPROVED','RETURNED','PUBLISHED','SUPERSEDED') NOT NULL,
 remarks TEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (approval_id) REFERENCES academic_approval_requests(approval_id),
 FOREIGN KEY (actor_id) REFERENCES auth_users(user_id),
 KEY idx_aa_history(approval_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS academic_schedule_publications (
 publication_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 schedule_type ENUM('CLASS','EXAM') NOT NULL,
 academic_period_id INT UNSIGNED NOT NULL,
 program_id INT UNSIGNED NOT NULL,
 approval_id BIGINT UNSIGNED NOT NULL,
 published_by INT UNSIGNED NOT NULL,
 published_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_publication_scope(schedule_type,academic_period_id,program_id),
 FOREIGN KEY (approval_id) REFERENCES academic_approval_requests(approval_id),
 FOREIGN KEY (published_by) REFERENCES auth_users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

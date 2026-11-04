-- Run once on LOCAL MySQL before testing the maintenance restoration workflow.
-- Existing permanent UNAVAILABLE relocations are not changed by this migration.
CREATE TABLE IF NOT EXISTS room_maintenance_moves (
 move_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 meeting_id BIGINT UNSIGNED NOT NULL,
 academic_period_id INT UNSIGNED NOT NULL,
 section_id INT UNSIGNED NOT NULL,
 original_room_id INT UNSIGNED NOT NULL,
 temporary_room_id INT UNSIGNED NOT NULL,
 moved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 restored_at TIMESTAMP NULL DEFAULT NULL,
 PRIMARY KEY (move_id),
 KEY idx_maintenance_meeting (meeting_id, restored_at),
 KEY idx_maintenance_origin (original_room_id, restored_at),
 KEY idx_maintenance_period (academic_period_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

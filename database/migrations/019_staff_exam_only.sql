-- Run once against the active database (local AND Railway independently).
-- Existing ADMIN / SCHEDULER / TEACHER roles remain unchanged.
ALTER TABLE auth_users MODIFY COLUMN role ENUM('ADMIN','SCHEDULER','TEACHER','STAFF') NOT NULL DEFAULT 'SCHEDULER';

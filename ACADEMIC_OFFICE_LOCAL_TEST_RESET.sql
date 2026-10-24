-- LOCAL TEST DATABASE ONLY. Back up bcp_scheduling before running.
-- RESET ACADEMIC OFFICE data across ALL programs/periods.
-- This deletes saved reports and all preview/saved-batch approval decisions.
-- It DOES NOT delete schedule_batches, schedule_meetings, exam_batches,
-- exam_meetings, student/teacher/room/subject/program records.
-- Do not run against Railway/production.
USE bcp_scheduling;
START TRANSACTION;

-- PART A: Saved Reports (including archived historical report #1)
DELETE FROM academic_office_saved_reports;

-- PART B: Academic Office official publication references
DELETE FROM academic_schedule_publications;

-- PART C: Saved-batch review audit and decisions
DELETE FROM academic_approval_actions;
DELETE FROM academic_approval_requests;

-- PART D: Generated preview approval requests, including archived/deleted
DELETE FROM academic_preview_requests;

COMMIT;

-- Reset only Academic Office test ID counters, once the tables are empty.
-- If other tables have external foreign keys to these IDs, stop and inspect.
ALTER TABLE academic_office_saved_reports AUTO_INCREMENT = 1;
ALTER TABLE academic_preview_requests AUTO_INCREMENT = 1;
ALTER TABLE academic_approval_actions AUTO_INCREMENT = 1;
ALTER TABLE academic_approval_requests AUTO_INCREMENT = 1;
ALTER TABLE academic_schedule_publications AUTO_INCREMENT = 1;

-- Verification: these should all return 0.
SELECT 'reports' item,COUNT(*) remaining FROM academic_office_saved_reports
UNION ALL SELECT 'preview requests',COUNT(*) FROM academic_preview_requests
UNION ALL SELECT 'saved batch requests',COUNT(*) FROM academic_approval_requests
UNION ALL SELECT 'approval actions',COUNT(*) FROM academic_approval_actions
UNION ALL SELECT 'publications',COUNT(*) FROM academic_schedule_publications;

-- OPTIONAL (run SEPARATELY only if you also want to retire BSIT class
-- schedules for AY 2026-2027 Sem 1 before generating another):
-- UPDATE schedule_batches SET status='SUPERSEDED'
-- WHERE program_id=4 AND academic_period_id=1 AND status='ACTIVE';
-- OPTIONAL separate exam retirement:
-- UPDATE exam_batches SET status='SUPERSEDED'
-- WHERE program_id=4 AND academic_period_id=1 AND status='ACTIVE';

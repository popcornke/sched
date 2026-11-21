-- LOCAL XAMPP / phpMyAdmin ONLY. Back up bcp_scheduling first.
-- Deletes DEMO exam batches/meetings (ACTIVE and SUPERSEDED) for 2026-2027 Semester 1.
-- Also deletes Academic Office EXAM approval/publication references to those batches,
-- because foreign keys otherwise prevent deletion. CLASS approvals remain untouched.
-- Does not delete any regular class timetable, sections, subjects, teachers, rooms or students.
-- Review the preview SELECT before executing the transaction below.
USE bcp_scheduling;

SELECT eb.exam_batch_id, p.program_code, eb.status, COUNT(em.exam_meeting_id) AS exams
FROM exam_batches eb
JOIN academic_periods ap ON ap.academic_period_id = eb.academic_period_id
JOIN programs p ON p.program_id = eb.program_id
LEFT JOIN exam_meetings em ON em.exam_batch_id = eb.exam_batch_id
WHERE ap.academic_year = '2026-2027' AND ap.semester = 1 AND eb.data_origin = 'DEMO'
GROUP BY eb.exam_batch_id, p.program_code, eb.status
ORDER BY p.program_code, eb.exam_batch_id;

START TRANSACTION;

CREATE TEMPORARY TABLE bcp_exam_reset_ids (exam_batch_id BIGINT UNSIGNED PRIMARY KEY) ENGINE=MEMORY;
INSERT INTO bcp_exam_reset_ids (exam_batch_id)
SELECT eb.exam_batch_id FROM exam_batches eb
JOIN academic_periods ap ON ap.academic_period_id = eb.academic_period_id
WHERE ap.academic_year='2026-2027' AND ap.semester=1 AND eb.data_origin='DEMO';

-- Retain all CLASS-related publication and approval records.
DELETE pub FROM academic_schedule_publications pub
JOIN academic_approval_requests req ON req.approval_id=pub.approval_id
JOIN bcp_exam_reset_ids target ON target.exam_batch_id=req.exam_batch_id
WHERE pub.schedule_type='EXAM' AND req.schedule_type='EXAM';

DELETE act FROM academic_approval_actions act
JOIN academic_approval_requests req ON req.approval_id=act.approval_id
JOIN bcp_exam_reset_ids target ON target.exam_batch_id=req.exam_batch_id
WHERE req.schedule_type='EXAM';

DELETE req FROM academic_approval_requests req
JOIN bcp_exam_reset_ids target ON target.exam_batch_id=req.exam_batch_id
WHERE req.schedule_type='EXAM';

DELETE em FROM exam_meetings em
JOIN bcp_exam_reset_ids target ON target.exam_batch_id=em.exam_batch_id;

DELETE eb FROM exam_batches eb
JOIN bcp_exam_reset_ids target ON target.exam_batch_id=eb.exam_batch_id;

SELECT COUNT(*) AS remaining_demo_exam_batches
FROM exam_batches eb
JOIN academic_periods ap ON ap.academic_period_id=eb.academic_period_id
WHERE ap.academic_year='2026-2027' AND ap.semester=1 AND eb.data_origin='DEMO';

COMMIT;
DROP TEMPORARY TABLE IF EXISTS bcp_exam_reset_ids;

-- Run after migration 025. Restricts saved report snapshots to an explicitly selected program.
-- "Department" in this module maps to the existing programs table (BSIT, BSOA, etc.).
ALTER TABLE academic_office_saved_reports
 ADD COLUMN scope_program_id INT NULL DEFAULT NULL,
 ADD COLUMN scope_label VARCHAR(255) NOT NULL DEFAULT 'All Departments (All Programs)';
CREATE INDEX idx_acad_report_scope ON academic_office_saved_reports(scope_program_id);

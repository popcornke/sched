-- Allow custom Start Date / End Date report snapshots while preserving historical reports.
-- Apply once after migration 024 (bcp_scheduling database).
ALTER TABLE academic_office_saved_reports
  MODIFY COLUMN report_type ENUM('WEEKLY','MONTHLY','CUSTOM') NOT NULL;

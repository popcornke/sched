-- Non-destructive archive metadata. Does NOT delete approved batches or meetings.
ALTER TABLE academic_preview_requests
  ADD COLUMN archived_at DATETIME NULL DEFAULT NULL,
  ADD COLUMN archived_by INT UNSIGNED NULL DEFAULT NULL,
  ADD INDEX idx_preview_archive (archived_at, status);

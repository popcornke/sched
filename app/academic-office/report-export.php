<?php
/** Secure download of saved Academic Office report data, using captured rows. */
declare(strict_types=1);
require_once __DIR__ . '/shared.php';
$db = acadDb();
$reportId = filter_var($_GET['report_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$format = strtolower((string)($_GET['format'] ?? 'csv'));
if (!$reportId || $format !== 'csv') { http_response_code(400); exit('Invalid report export.'); }
$q = $db->prepare('SELECT r.*, u.username FROM academic_office_saved_reports r JOIN auth_users u ON u.user_id=r.created_by WHERE r.report_id=?');
$q->execute([$reportId]);
$report = $q->fetch(PDO::FETCH_ASSOC);
if (!$report) { http_response_code(404); exit('Report not found.'); }
$metrics = json_decode((string)$report['metrics_json'], true);
$breakdown = json_decode((string)$report['breakdown_json'], true);
$metrics = is_array($metrics) ? $metrics : [];
$breakdown = is_array($breakdown) ? $breakdown : [];
$rows = isset($metrics['schedules']) && is_array($metrics['schedules']) ? $metrics['schedules'] : [];
// Historical reports lacking captured schedule rows cannot safely export mutable live data as a saved report.
if (!array_key_exists('schedules', $metrics)) { http_response_code(409); exit('This legacy report has no saved schedule rows. Create a new report to export accurate CSV data.'); }
require_once dirname(__DIR__) . '/shared/audit.php';
auditLog($db, 'REPORT_EXPORTED', 'ACADEMIC_REPORTS', 'SAVED_REPORT', (string)$reportId, 'Academic Office exported a saved academic report as CSV.', ['format'=>'CSV']);
$filename = 'BCP_Academic_Report_' . $reportId . '_' . date('Ymd') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
$out = fopen('php://output', 'wb');
fwrite($out, "\xEF\xBB\xBF"); // Excel UTF-8 BOM
function acadCsvCell($value): string {
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', ' ', (string)($value ?? '')) ?? '';
    // Avoid CSV formula injection when opened in spreadsheets.
    if (preg_match('/^\s*[=+@\-]/u', $value)) return "'".$value;
    return $value;
}
function acadCsvRow($out, array $cells): void { fputcsv($out, array_map('acadCsvCell', $cells), ',', '"', ''); }
acadCsvRow($out, ['BESTLINK COLLEGE OF THE PHILIPPINES']);
acadCsvRow($out, ['ACADEMIC SCHEDULE REPORT']);
acadCsvRow($out, ['Report ID', $reportId]);
acadCsvRow($out, ['Department / Program', $report['scope_label'] ?? 'All Departments']);
acadCsvRow($out, ['Reporting Period', $report['period_start'].' to '.$report['period_end']]);
acadCsvRow($out, ['Prepared By', $report['username']]);
acadCsvRow($out, ['Saved At', $report['created_at']]);
acadCsvRow($out, []);
acadCsvRow($out, ['APPROVAL SUMMARY']);
acadCsvRow($out, ['Total Requests', $metrics['requests'] ?? 0]);
acadCsvRow($out, ['Approved', $metrics['approved'] ?? 0]);
acadCsvRow($out, ['Pending', $metrics['pending'] ?? 0]);
acadCsvRow($out, ['Rejected', $metrics['rejected'] ?? 0]);
acadCsvRow($out, ['Captured Schedule Meetings', count($rows)]);
acadCsvRow($out, []);
acadCsvRow($out, ['DEPARTMENT BREAKDOWN']);
acadCsvRow($out, ['Program', 'Requests', 'Approved', 'Rejected', 'Pending']);
foreach ($breakdown as $entry) {
    acadCsvRow($out, [$entry['program_code'] ?? '', $entry['requests'] ?? 0, $entry['approved'] ?? 0, $entry['rejected'] ?? 0, $entry['pending'] ?? 0]);
}
acadCsvRow($out, []);
acadCsvRow($out, ['SAVED CLASS SCHEDULE DETAILS']);
acadCsvRow($out, ['Program', 'Academic Year', 'Semester', 'Batch ID', 'Section', 'Subject Code', 'Subject Title', 'Teacher', 'Delivery Mode', 'Day', 'Start Time', 'End Time', 'Room']);
foreach ($rows as $row) {
    acadCsvRow($out, [$row['program_code'] ?? '', $row['academic_year'] ?? '', $row['semester'] ?? '', $row['batch_id'] ?? '', $row['section_code'] ?? '', $row['subject_code'] ?? '', $row['subject_title'] ?? '', $row['teacher_name'] ?? '', $row['delivery_mode'] ?? '', $row['day_of_week'] ?? '', $row['start_time'] ?? '', $row['end_time'] ?? '', $row['room_name'] ?? 'ONLINE']);
}
fclose($out);
exit;

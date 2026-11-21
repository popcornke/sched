<?php
declare(strict_types=1);
require_once __DIR__ . '/notifications.php';
/** Best-effort notification delivery after a successful approval-request commit. */
function academicNotifyReviewers(PDO $db, string $kind, int $requestId, string $label): void {
    try {
        $users = $db->query("SELECT user_id FROM auth_users WHERE role='ACADEMIC_OFFICE' AND is_active=1")->fetchAll(PDO::FETCH_COLUMN);
        $kind = $kind === 'EXAM' ? 'EXAM' : 'CLASS';
        $destination = '/app/academic-office/' . ($kind === 'EXAM' ? 'exam-requests.php' : 'preview-review.php') . '?id=' . $requestId;
        // Notification links are absolute under the installed application base path.
        $base = authBasePath();
        foreach ($users as $userId) {
            notificationCreate($db, (int)$userId, $kind === 'EXAM' ? 'New exam timetable for approval' : 'New class timetable for approval', $label . ' — request #' . $requestId . ' is waiting for review.', 'info', $base . $destination);
        }
    } catch (Throwable $error) {
        error_log('Academic Office notification dispatch: ' . $error->getMessage());
    }
}

/** Notify the submitting Admin and reviewing Academic Office after a committed exam decision. */
function academicNotifyExamDecision(PDO $db, int $requestId, int $submitterId, int $reviewerId, string $decision): void {
    try {
        $approved = $decision === 'APPROVED';
        $status = $approved ? 'approved and activated' : 'rejected';
        $type = $approved ? 'success' : 'warning';
        $base = authBasePath();
        $adminUrl = $base . '/app/modules/04-exam-timetable-generator/exam-timetable-generator.php';
        $historyUrl = $base . '/app/academic-office/history.php?type=exam';
        // Pending notifications must not continue pointing at a finalized request.
        $pendingUrl = $base . '/app/academic-office/exam-requests.php?id=' . $requestId;
        $update = $db->prepare("UPDATE notifications SET url = ?, is_read = 1 WHERE url = ? AND user_id IN (SELECT user_id FROM auth_users WHERE role = 'ACADEMIC_OFFICE')");
        $update->execute([$historyUrl, $pendingUrl]);
        notificationCreate($db, $submitterId, 'Exam timetable ' . ($approved ? 'approved' : 'rejected'), 'School-wide exam request #' . $requestId . ' was ' . $status . ' by Academic Office.', $type, $adminUrl);
        if ($reviewerId > 0 && $reviewerId !== $submitterId) {
            notificationCreate($db, $reviewerId, 'Exam review completed', 'Request #' . $requestId . ' is ' . strtoupper($decision) . ' and is now in Exam Schedule Approval History.', $type, $historyUrl);
        }
    } catch (Throwable $error) {
        error_log('Exam decision notifications: ' . $error->getMessage());
    }
}

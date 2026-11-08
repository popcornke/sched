<?php

declare(strict_types=1);

require_once __DIR__ . '/shared.php';

function saReportCatalog(): array
{
    return [
        'accounts' => [
            'label' => 'System Accounts',
            'description' => 'User accounts, roles, status, and creation dates.',
        ],
        'class_batches' => [
            'label' => 'Class Schedule Batches',
            'description' => 'Saved class schedule batches by program and academic period.',
        ],
        'exam_batches' => [
            'label' => 'Exam Schedule Batches',
            'description' => 'Saved exam batches, dates, status, and program scope.',
        ],
        'approval_requests' => [
            'label' => 'Academic Office Requests',
            'description' => 'Class schedule requests and Academic Office review status.',
        ],
        'audit_logs' => [
            'label' => 'Audit Logs',
            'description' => 'Recorded system-control activity and Super Admin actions.',
        ],
        'rooms' => [
            'label' => 'Room Status',
            'description' => 'Room inventory, capacity, type, program, and availability status.',
        ],
    ];
}

function saReportData(PDO $db, string $type, ?string $from = null, ?string $to = null, int $limit = 5000): array
{
    $limit = max(1, min(10000, $limit));
    $dateClause = '';
    $params = [];
    if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $dateClause .= ' AND DATE({date_col}) >= :from_date';
        $params['from_date'] = $from;
    }
    if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $dateClause .= ' AND DATE({date_col}) <= :to_date';
        $params['to_date'] = $to;
    }

    if ($type === 'accounts') {
        $hasEmail = saColumnExists($db, 'auth_users', 'email');
        $selectEmail = $hasEmail ? ', COALESCE(email, "") AS Email' : '';
        $sql = 'SELECT user_id AS ID, username AS Username' . $selectEmail . ', role AS Role,
                       CASE WHEN is_active = 1 THEN "ACTIVE" ELSE "INACTIVE" END AS Status,
                       created_at AS Created
                FROM auth_users WHERE 1=1' . str_replace('{date_col}', 'created_at', $dateClause) . '
                ORDER BY user_id DESC LIMIT ' . $limit;
        $stmt = $db->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['title' => 'System Accounts Report', 'rows' => $rows];
    }

    if ($type === 'class_batches') {
        if (!saTableExists($db, 'schedule_batches')) return ['title' => 'Class Schedule Batches Report', 'rows' => []];
        $sql = 'SELECT b.batch_id AS Batch_ID, p.program_code AS Program,
                       CONCAT(ap.academic_year, " / Semester ", ap.semester) AS Academic_Period,
                       b.data_origin AS Data_Origin, b.status AS Status,
                       COUNT(m.meeting_id) AS Meetings, b.created_at AS Created
                FROM schedule_batches b
                JOIN programs p ON p.program_id = b.program_id
                JOIN academic_periods ap ON ap.academic_period_id = b.academic_period_id
                LEFT JOIN schedule_meetings m ON m.batch_id = b.batch_id
                WHERE 1=1' . str_replace('{date_col}', 'b.created_at', $dateClause) . '
                GROUP BY b.batch_id, p.program_code, ap.academic_year, ap.semester, b.data_origin, b.status, b.created_at
                ORDER BY b.batch_id DESC LIMIT ' . $limit;
        $stmt=$db->prepare($sql);$stmt->execute($params);return ['title'=>'Class Schedule Batches Report','rows'=>$stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    if ($type === 'exam_batches') {
        if (!saTableExists($db, 'exam_batches')) return ['title' => 'Exam Schedule Batches Report', 'rows' => []];
        $sql = 'SELECT e.exam_batch_id AS Exam_Batch_ID, p.program_code AS Program,
                       CONCAT(ap.academic_year, " / Semester ", ap.semester) AS Academic_Period,
                       e.exam_label AS Exam_Label, e.exam_day_1 AS Day_1, e.exam_day_2 AS Day_2, e.exam_day_3 AS Day_3,
                       e.status AS Status, COUNT(m.exam_meeting_id) AS Exams, e.created_at AS Created
                FROM exam_batches e
                JOIN programs p ON p.program_id = e.program_id
                JOIN academic_periods ap ON ap.academic_period_id = e.academic_period_id
                LEFT JOIN exam_meetings m ON m.exam_batch_id = e.exam_batch_id
                WHERE 1=1' . str_replace('{date_col}', 'e.created_at', $dateClause) . '
                GROUP BY e.exam_batch_id, p.program_code, ap.academic_year, ap.semester, e.exam_label, e.exam_day_1, e.exam_day_2, e.exam_day_3, e.status, e.created_at
                ORDER BY e.exam_batch_id DESC LIMIT ' . $limit;
        $stmt=$db->prepare($sql);$stmt->execute($params);return ['title'=>'Exam Schedule Batches Report','rows'=>$stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    if ($type === 'approval_requests') {
        if (!saTableExists($db, 'academic_preview_requests')) return ['title' => 'Academic Office Requests Report', 'rows' => []];
        $sql = 'SELECT r.request_id AS Request_ID, p.program_code AS Program,
                       CONCAT(ap.academic_year, " / Semester ", ap.semester) AS Academic_Period,
                       submitter.username AS Submitted_By,
                       COALESCE(reviewer.username, "") AS Reviewed_By,
                       r.status AS Status, COALESCE(r.reason, "") AS Remarks,
                       r.submitted_at AS Submitted, r.reviewed_at AS Reviewed
                FROM academic_preview_requests r
                JOIN programs p ON p.program_id = r.program_id
                JOIN academic_periods ap ON ap.academic_period_id = r.academic_period_id
                JOIN auth_users submitter ON submitter.user_id = r.submitted_by
                LEFT JOIN auth_users reviewer ON reviewer.user_id = r.reviewed_by
                WHERE 1=1' . str_replace('{date_col}', 'r.submitted_at', $dateClause) . '
                ORDER BY r.request_id DESC LIMIT ' . $limit;
        $stmt=$db->prepare($sql);$stmt->execute($params);return ['title'=>'Academic Office Requests Report','rows'=>$stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    if ($type === 'audit_logs') {
        if (!saTableExists($db, 'system_audit_logs')) return ['title' => 'Audit Logs Report', 'rows' => []];
        $sql = 'SELECT audit_id AS Audit_ID, COALESCE(actor_username_snapshot, "System") AS Actor,
                       COALESCE(actor_role_snapshot, "SYSTEM") AS Role, action_type AS Action,
                       module AS Module, COALESCE(target_type, "") AS Target_Type,
                       COALESCE(target_id, "") AS Target_ID, COALESCE(description, "") AS Description,
                       COALESCE(ip_address, "") AS IP_Address, created_at AS Created
                FROM system_audit_logs WHERE 1=1' . str_replace('{date_col}', 'created_at', $dateClause) . '
                ORDER BY audit_id DESC LIMIT ' . $limit;
        $stmt=$db->prepare($sql);$stmt->execute($params);return ['title'=>'Audit Logs Report','rows'=>$stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    if ($type === 'rooms') {
        if (!saTableExists($db, 'rooms')) return ['title' => 'Room Status Report', 'rows' => []];
        $hasProgramId = saColumnExists($db, 'rooms', 'program_id');
        $programJoin = $hasProgramId ? ' LEFT JOIN programs p ON p.program_id = r.program_id' : '';
        $programSelect = $hasProgramId ? ', COALESCE(p.program_code, "") AS Program' : '';
        $roomType = saColumnExists($db, 'rooms', 'room_type') ? 'r.room_type' : (saColumnExists($db, 'rooms', 'type') ? 'r.type' : '""');
        $roomName = saColumnExists($db, 'rooms', 'room_name') ? 'r.room_name' : (saColumnExists($db, 'rooms', 'room_code') ? 'r.room_code' : 'CAST(r.room_id AS CHAR)');
        $sql = 'SELECT r.room_id AS Room_ID, ' . $roomName . ' AS Room' . $programSelect . ', r.capacity AS Capacity,
                       ' . $roomType . ' AS Room_Type, r.status AS Status
                FROM rooms r' . $programJoin . ' ORDER BY Room LIMIT ' . $limit;
        $stmt=$db->query($sql);return ['title'=>'Room Status Report','rows'=>$stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    throw new InvalidArgumentException('Unsupported report type.');
}

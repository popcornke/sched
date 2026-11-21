<?php
declare(strict_types=1);
/** Read-only view of the server-generated, unsaved school-wide exam preview. */
require_once __DIR__ . '/exam-global-common.php';
try {
    exGuard('GET');
    $preview = $_SESSION['bcp_global_exam_preview'] ?? null;
    $token = (string)($_GET['preview_token'] ?? '');
    $code = strtoupper(trim((string)($_GET['program'] ?? '')));
    if (!is_array($preview) || $token === '' || !isset($preview['token'])
        || !hash_equals((string)$preview['token'], $token)
        || time() - (int)($preview['created_at'] ?? 0) > 3600) {
        exFail(409, 'PREVIEW_EXPIRED', 'The unsaved exam preview expired. Generate ALL Programs again.');
    }
    if (!preg_match('/^[A-Z0-9-]{2,30}$/', $code) || !isset($preview['programs'][$code])) {
        exFail(400, 'PROGRAM_NOT_IN_PREVIEW', 'Choose a program included in the generated timetable.');
    }
    $pdo = getDatabase();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $periodId = (int)$preview['period_id'];
    $item = $preview['programs'][$code];
    // Use a fresh input snapshot to retrieve readable names and to detect any
    // changes to rooms, availability or academic data before showing a draft.
    $snapshot = exBuildInput($pdo, $periodId, $preview['dates'], (string)$preview['window'], null, $code);
    if (!hash_equals((string)$item['input_hash'], exJsonHash($snapshot))) {
        exFail(409, 'PREVIEW_STALE', 'Room status, availability or schedule data changed. Generate ALL Programs again before submitting.');
    }
    $sections = []; foreach ($snapshot['input']['sections'] as $s) $sections[(int)$s['section_id']] = $s;
    $exams = []; foreach ($snapshot['input']['exams'] as $e) $exams[(int)$e['section_subject_id']] = $e;
    $rooms = []; foreach ($snapshot['input']['rooms'] as $r) $rooms[(int)$r['room_id']] = $r;
    $teachers = []; foreach ($snapshot['input']['teachers'] as $t) $teachers[(int)$t['teacher_id']] = $t;
    $assignments = [];
    foreach (($item['result']['assignments'] ?? []) as $a) {
        $sid = (int)($a['section_id'] ?? 0);
        $ss = (int)($a['section_subject_id'] ?? 0);
        $rid = (int)($a['room_id'] ?? 0);
        $tid = (int)($a['proctor_id'] ?? 0);
        if (!isset($sections[$sid], $exams[$ss], $rooms[$rid], $teachers[$tid])) {
            exFail(409, 'PREVIEW_STALE', 'A section, room or proctor is no longer eligible. Regenerate the school-wide preview.');
        }
        $s = $sections[$sid]; $e = $exams[$ss];
        $assignments[] = [
            'section_id'=>$sid, 'section_subject_id'=>$ss, 'section_code'=>$s['section_code'],
            'section_type'=>$s['section_type'], 'year_level'=>(int)$s['year_level'],
            'subject_id'=>(int)$e['subject_id'], 'subject_code'=>$e['subject_code'],
            'subject_title'=>$e['subject_title'], 'room_id'=>$rid, 'room_name'=>$rooms[$rid]['room_name'],
            'proctor_id'=>$tid, 'proctor_name'=>$teachers[$tid]['teacher_name'],
            'exam_day'=>(int)$a['exam_day'], 'exam_date'=>$a['exam_date'],
            'start_time'=>$a['start_time'], 'end_time'=>$a['end_time']
        ];
    }
    usort($assignments, static fn($a,$b)=>[$a['year_level'],$a['section_code'],$a['exam_day'],$a['start_time']] <=> [$b['year_level'],$b['section_code'],$b['exam_day'],$b['start_time']]);
    exReply(200, [
        'success'=>true, 'status'=>'UNSAVED_GLOBAL_EXAM_PREVIEW', 'database_write'=>false,
        'program'=>$code, 'period'=>$snapshot['period'], 'class_batch_id'=>(int)$item['class_batch_id'],
        'exam_dates'=>$preview['dates'], 'assignments'=>$assignments,
        'required_exams'=>(int)$item['result']['required_exams'],
        'returned_exams'=>(int)$item['result']['returned_exams'],
        'solver_status'=>(string)($item['result']['status'] ?? 'PREVIEW READY'),
        'solve_seconds'=>$item['result']['solve_seconds'] ?? null,
        'issues'=>[], 'program_count'=>count($preview['programs'])
    ]);
} catch (ExamFailure $e) {
    exReply($e->http, ['success'=>false,'status'=>$e->errorCode,'message'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log('School-wide exam view: '.$e->getMessage());
    exReply(500, ['success'=>false,'status'=>'PREVIEW_VIEW_FAILED','message'=>'Could not display the generated timetable. Check PHP error log.']);
}

<?php
declare(strict_types=1);
require_once __DIR__ . '/exam-common.php';

/** A single school-wide generation: only programs with an ACTIVE DEMO class batch are included. */
function exGlobalPrograms(PDO $pdo, int $periodId): array {
    return exRows($pdo, "SELECT p.program_code FROM programs p JOIN schedule_batches b ON b.program_id=p.program_id WHERE b.academic_period_id=:period AND b.status='ACTIVE' AND b.data_origin='DEMO' AND p.is_active=1 AND p.education_level='College' GROUP BY p.program_id,p.program_code ORDER BY p.program_code", ['period'=>$periodId]);
}

/** Add previously generated exam assignments to solver/validator occupied resources. */
function exGlobalSynthetic(array $snapshot, array $assignments, array $previous): array {
    $sections=[]; foreach ($snapshot['input']['sections'] as $s) $sections[(int)$s['section_id']]=$s;
    $subjects=[]; foreach ($snapshot['input']['exams'] as $e) $subjects[(int)$e['section_subject_id']]=$e;
    foreach ($assignments as $row) {
        $sid=(int)$row['section_id']; $ss=(int)$row['section_subject_id'];
        $previous[]=[
            'exam_meeting_id'=>0, 'exam_batch_id'=>0,
            'program_id'=>(int)$snapshot['program']['program_id'],
            'year_level'=>(int)$sections[$sid]['year_level'],
            'subject_id'=>(int)$subjects[$ss]['subject_id'],
            'section_subject_id'=>$ss,
            'proctor_id'=>(int)$row['proctor_id'], 'room_id'=>(int)$row['room_id'],
            'exam_date'=>$row['exam_date'], 'start_time'=>$row['start_time'], 'end_time'=>$row['end_time']
        ];
    }
    return $previous;
}

function exGlobalCheckExisting(PDO $pdo,int $periodId): void {
    $rows=exRows($pdo, "SELECT COUNT(*) AS n FROM exam_batches WHERE academic_period_id=:period AND status='ACTIVE'",['period'=>$periodId]);
    if ((int)$rows[0]['n']>0) exFail(409,'EXAMS_ALREADY_SAVED','This academic period already contains ACTIVE exam schedules. Back up and reset the existing exams before generating a new school-wide timetable.');
}

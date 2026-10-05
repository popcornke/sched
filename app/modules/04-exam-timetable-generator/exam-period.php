<?php
declare(strict_types=1);
/** Module 4: manage one editable BCP-wide exam date window per academic period. */
require_once __DIR__ . '/exam-common.php';

/**
 * Load one saved ACTIVE DEMO exam batch in the same row shape accepted by exValidate().
 * The date itself may already have been remapped inside the surrounding transaction.
 */
function exSavedAssignmentsForDateRevalidation(PDO $pdo, int $examBatchId): array
{
    $rows=exRows($pdo,"SELECT
            em.section_subject_id,
            ss.section_id,
            ss.subject_id,
            em.proctor_id,
            em.room_id,
            em.exam_day,
            DATE_FORMAT(em.exam_date,'%Y-%m-%d') AS exam_date,
            TIME_FORMAT(em.start_time,'%H:%i') AS start_time,
            TIME_FORMAT(em.end_time,'%H:%i') AS end_time
        FROM exam_meetings em
        JOIN section_subjects ss ON ss.section_subject_id=em.section_subject_id
        WHERE em.exam_batch_id=:batch
        ORDER BY em.exam_meeting_id",['batch'=>$examBatchId]);

    if (!$rows) {
        exFail(409,'EMPTY_ACTIVE_EXAMS','An ACTIVE exam batch has no stored meetings to revalidate.');
    }
    foreach($rows as &$row){
        foreach(['section_subject_id','section_id','subject_id','proctor_id','room_id','exam_day'] as $key){
            $row[$key]=(int)$row[$key];
        }
    }
    unset($row);
    return $rows;
}

$pdo=null;$lockName='';$held=false;
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        exGuard('GET');
        $periodId=filter_var($_GET['period_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$periodId) exFail(400,'PERIOD_REQUIRED','Choose an academic period.');
        $pdo=getDatabase();
        $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $state=exUnifiedExamPeriod($pdo,(int)$periodId);
        exReply(200,[
            'success'=>true,
            'status'=>'UNIFIED_EXAM_PERIOD_READY',
            'period'=>$state['period'],
            'exam_dates'=>$state['exam_dates'],
            'configured'=>$state['configured'],
            'locked'=>false,
            'source'=>$state['source'],
            'calendar_constraints'=>$state['calendar_constraints'],
            'date_change_revalidates_saved_timetables'=>$state['active_exam_batch_count']>0,
            'date_change_requires_regeneration'=>false,
            'active_exam_batch_count'=>$state['active_exam_batch_count'],
            'active_programs'=>$state['active_programs'],
            'database_write'=>false,
        ]);
    }

    exGuard('POST');
    $body=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
    $periodId=filter_var($body['period_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if (!$periodId || !is_array($body['exam_dates']??null)) {
        exFail(400,'INVALID_EXAM_PERIOD','Choose an academic period and three examination dates.');
    }

    $pdo=getDatabase();
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $dates=exValidateExamDatesForPeriod($pdo,(int)$periodId,$body['exam_dates']);

    $lockName='bcp_exam_save_period_'.(int)$periodId;
    $lock=$pdo->prepare('SELECT GET_LOCK(:name,10)');
    $lock->execute(['name'=>$lockName]);
    if ((int)$lock->fetchColumn()!==1) exFail(409,'EXAM_PERIOD_BUSY','Another exam save or date update is in progress. Retry.');
    $held=true;

    $pdo->beginTransaction();

    // Lock all ACTIVE exam batches so a calendar date change and its saved
    // timetables are updated atomically. Room/proctor/time assignments are preserved.
    $batchLock=$pdo->prepare("SELECT eb.exam_batch_id,eb.program_id,eb.class_batch_id,eb.exam_label,eb.data_origin,
            p.program_code,eb.exam_day_1,eb.exam_day_2,eb.exam_day_3,eb.status
        FROM exam_batches eb
        JOIN programs p ON p.program_id=eb.program_id
        WHERE eb.academic_period_id=:period
        ORDER BY eb.exam_batch_id FOR UPDATE");
    $batchLock->execute(['period'=>(int)$periodId]);
    $lockedBatches=$batchLock->fetchAll(PDO::FETCH_ASSOC);

    $state=exUnifiedExamPeriod($pdo,(int)$periodId);
    $oldDates=is_array($state['exam_dates']) ? $state['exam_dates'] : null;
    $datesChanged=$oldDates===null || $dates!==$oldDates;
    $activeRows=array_values(array_filter($lockedBatches,static fn($row)=>(string)$row['status']==='ACTIVE'));
    $activePrograms=array_values(array_unique(array_map(static fn($row)=>(string)$row['program_code'],$activeRows)));

    // Integrity guard: a date-only update must never add, delete, supersede, or
    // replace exam records. Snapshot the ACTIVE meeting count before touching dates.
    $meetingCountBefore=(int)(exRows($pdo,"SELECT COUNT(*) AS n
        FROM exam_meetings em
        JOIN exam_batches eb ON eb.exam_batch_id=em.exam_batch_id
        WHERE eb.academic_period_id=:period AND eb.status='ACTIVE'",['period'=>(int)$periodId])[0]['n']??0);

    if ($datesChanged && count($activeRows)>0 && (($body['confirm_retime']??false)!==true)) {
        exFail(409,'EXAM_DATE_CHANGE_CONFIRMATION_REQUIRED',
            'Changing the school-wide examination dates will keep the existing room, proctor, time, subject, and Exam Day 1/2/3 assignments. The system will only move them to the new dates and revalidate all saved timetables. Confirm the date update to continue.');
    }

    $exists=exRows($pdo,"SELECT academic_period_calendar_id FROM academic_period_calendars
        WHERE academic_period_id=:period LIMIT 1",['period'=>(int)$periodId]);

    if ($exists) {
        $st=$pdo->prepare("UPDATE academic_period_calendars
            SET exam_day_1=:d1,exam_day_2=:d2,exam_day_3=:d3
            WHERE academic_period_id=:period");
        $st->execute(['d1'=>$dates[0],'d2'=>$dates[1],'d3'=>$dates[2],'period'=>(int)$periodId]);
    } else {
        $st=$pdo->prepare("INSERT INTO academic_period_calendars
            (academic_period_id,exam_day_1,exam_day_2,exam_day_3,calendar_status)
            VALUES (:period,:d1,:d2,:d3,'PENDING')");
        $st->execute(['period'=>(int)$periodId,'d1'=>$dates[0],'d2'=>$dates[1],'d3'=>$dates[2]]);
    }

    $retimedCount=0;
    if ($datesChanged && count($activeRows)>0) {
        // The current Module 4 save/replace workflow creates DEMO-EXAM batches.
        // Refuse to silently rewrite unknown ACTIVE exam batch types.
        foreach($activeRows as $row){
            if ((string)$row['exam_label']!=='DEMO-EXAM' || (string)$row['data_origin']!=='DEMO') {
                exFail(409,'EXAM_DATE_RETIME_UNSUPPORTED_BATCH',
                    'An ACTIVE exam batch is not a DEMO-EXAM batch, so the date-only update was cancelled without changing the database.');
            }
        }

        // First remap the shared date snapshots for every ACTIVE batch.
        $batchDates=$pdo->prepare("UPDATE exam_batches
            SET exam_day_1=:d1,exam_day_2=:d2,exam_day_3=:d3
            WHERE academic_period_id=:period AND status='ACTIVE'");
        $batchDates->execute(['d1'=>$dates[0],'d2'=>$dates[1],'d3'=>$dates[2],'period'=>(int)$periodId]);

        // Then remap only exam_date. exam_day, room, proctor, time, subject and
        // section-subject assignment remain exactly as saved.
        $meetingDates=$pdo->prepare("UPDATE exam_meetings em
            JOIN exam_batches eb ON eb.exam_batch_id=em.exam_batch_id
            SET em.exam_date=CASE em.exam_day
                WHEN 1 THEN :d1
                WHEN 2 THEN :d2
                WHEN 3 THEN :d3
                ELSE em.exam_date
            END
            WHERE eb.academic_period_id=:period
              AND eb.status='ACTIVE'");
        $meetingDates->execute(['d1'=>$dates[0],'d2'=>$dates[1],'d3'=>$dates[2],'period'=>(int)$periodId]);

        $meetingCountAfter=(int)(exRows($pdo,"SELECT COUNT(*) AS n
            FROM exam_meetings em
            JOIN exam_batches eb ON eb.exam_batch_id=em.exam_batch_id
            WHERE eb.academic_period_id=:period AND eb.status='ACTIVE'",['period'=>(int)$periodId])[0]['n']??0);
        if($meetingCountAfter!==$meetingCountBefore){
            exFail(500,'EXAM_DATE_UPDATE_INTEGRITY_FAILED',
                'Date-only update changed the number of saved exam records. The transaction was cancelled and no database changes were kept.');
        }

        // Revalidate every saved program against the NEW weekdays and against
        // every other ACTIVE program after all meetings were remapped. Any
        // failure throws and the transaction rolls the calendar + dates back.
        foreach($activeRows as $row){
            $batchId=(int)$row['exam_batch_id'];
            $programCode=(string)$row['program_code'];
            $snapshot=exBuildInput($pdo,(int)$periodId,$dates,'06-21',$batchId,$programCode);
            $assignments=exSavedAssignmentsForDateRevalidation($pdo,$batchId);
            $proposal=[
                'success'=>true,
                'status'=>'EXAM_PREVIEW_READY',
                'assignments'=>$assignments,
                'required_exams'=>count($assignments),
                'returned_exams'=>count($assignments),
                'gap_audit'=>['passed'=>true],
            ];
            try {
                exValidate($snapshot,$proposal);
            } catch (ExamFailure $e) {
                exFail(409,'EXAM_DATE_REUSE_CONFLICT',
                    $programCode.' saved timetable cannot be moved to the selected dates without changing its assignments: '.$e->getMessage().
                    ' Nothing was changed. Choose different dates or intentionally regenerate that timetable.');
            }
            $retimedCount++;
        }
    }

    $pdo->commit();

    $after=exUnifiedExamPeriod($pdo,(int)$periodId);
    $reply=[
        'success'=>true,
        'status'=>($datesChanged || !$exists)
            ? ($retimedCount>0?'UNIFIED_EXAM_DATES_UPDATED_IN_PLACE':'UNIFIED_EXAM_DATES_SAVED')
            : 'UNIFIED_EXAM_DATES_UNCHANGED',
        'exam_dates'=>$dates,
        'configured'=>true,
        'locked'=>false,
        'calendar_constraints'=>$after['calendar_constraints'],
        'active_exam_batch_count'=>$after['active_exam_batch_count'],
        'active_programs'=>$after['active_programs'],
        'retimed_exam_batch_count'=>$retimedCount,
        'retimed_exam_meeting_count'=>$meetingCountBefore,
        'retimed_programs'=>$retimedCount>0?$activePrograms:[],
        'assignments_preserved'=>$retimedCount>0,
        'batch_ids_preserved'=>true,
        'exam_rows_preserved'=>true,
        'regeneration_required'=>false,
        'database_write'=>$datesChanged || !$exists,
    ];

    if ($held) {
        $release=$pdo->prepare('SELECT RELEASE_LOCK(:name)');
        $release->execute(['name'=>$lockName]);
        $held=false;
    }
    exReply(200,$reply);
} catch (ExamFailure $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    if ($held && $pdo instanceof PDO) {
        try{$release=$pdo->prepare('SELECT RELEASE_LOCK(:name)');$release->execute(['name'=>$lockName]);}catch(Throwable $ignored){}
    }
    exReply($e->http,[
        'success'=>false,
        'status'=>$e->errorCode,
        'message'=>$e->getMessage(),
        'database_write'=>false,
    ]);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    if ($held && $pdo instanceof PDO) {
        try{$release=$pdo->prepare('SELECT RELEASE_LOCK(:name)');$release->execute(['name'=>$lockName]);}catch(Throwable $ignored){}
    }
    error_log('BCP unified exam period: '.$e->getMessage());
    exReply(500,[
        'success'=>false,
        'status'=>'UNIFIED_EXAM_PERIOD_FAILED',
        'message'=>'Unable to read or update the unified examination period. Check the server log for details.',
        'database_write'=>false,
    ]);
}

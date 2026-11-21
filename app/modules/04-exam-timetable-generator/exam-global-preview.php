<?php
declare(strict_types=1);
require_once __DIR__ . '/exam-global-common.php';
require_once __DIR__ . '/../../config/python.php';
try {
    exGuard('POST');
    set_time_limit(0);
    $body=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
    $periodId=filter_var($body['period_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if (!$periodId || !is_array($body['exam_dates']??null)) exFail(400,'INVALID_DATES','Choose a period and three exam dates.');
    $dates=exDates($body['exam_dates']);
    $window=(string)($body['exam_window']??'06-18');
    $pdo=getDatabase(); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    exGlobalCheckExisting($pdo,(int)$periodId);
    $pending=$pdo->prepare("SELECT request_id FROM academic_exam_requests WHERE academic_period_id=? AND status='PENDING_REVIEW' LIMIT 1");
    $pending->execute([(int)$periodId]);
    if($pending->fetchColumn())exFail(409,'EXAM_AWAITING_APPROVAL','This period already has a pending Academic Office exam request. Wait for approval or rejection before generating again.');

    $programs=exGlobalPrograms($pdo,(int)$periodId);
    if (!$programs) exFail(409,'NO_ACTIVE_PROGRAMS','No college program has an ACTIVE DEMO class schedule in this academic period.');
    if (!function_exists('curl_init')) exFail(500,'CURL_UNAVAILABLE','PHP cURL is required for the exam solver.');
    $results=[];$baseline=[];$totalRequired=0;$totalReturned=0;$solved=0;
    foreach ($programs as $program) {
        $code=(string)$program['program_code'];
        $snapshot=exBuildInput($pdo,(int)$periodId,$dates,$window,null,$code);
        // All programs are part of one preview and cannot overlap in rooms or teachers.
        $snapshot['existing_exams']=array_merge($snapshot['existing_exams'],$baseline);
        $snapshot['input']['existing_exams']=$snapshot['existing_exams'];
        $ch=curl_init(pythonBaseUrl().'/api/exams/preview');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>130,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode($snapshot['input'],JSON_THROW_ON_ERROR)]);
        $raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
        if (!is_string($raw)||$http!==200) {
            error_log('Global exam solver '.$code.': '.$http.' '.$error.' '.substr((string)$raw,0,1000));
            exFail(502,'PROGRAM_SOLVER_FAILED',$code.' solver failed. No schedules were saved. Inspect the Python solver logs.');
        }
        $output=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        if (!is_array($output) || ($output['success']??false)!==true) {
            exFail(422,'PROGRAM_INFEASIBLE',$code.' could not produce a valid examination timetable. Nothing was saved.');
        }
        exValidate($snapshot,$output);
        $results[$code]=['input_hash'=>exJsonHash(exBuildInput($pdo,(int)$periodId,$dates,$window,null,$code)), 'class_batch_id'=>$snapshot['class_batch_id'],'result'=>$output];
        $baseline=exGlobalSynthetic($snapshot,$output['assignments'],$baseline);
        $totalRequired+=(int)$output['required_exams'];$totalReturned+=(int)$output['returned_exams'];
        $solved++;
    }
    $token=bin2hex(random_bytes(32));
    $_SESSION['bcp_global_exam_preview']=['token'=>$token,'created_at'=>time(),'period_id'=>(int)$periodId,'dates'=>$dates,'window'=>$window,'programs'=>$results];
    session_write_close();
    exReply(200,['success'=>true,'status'=>'GLOBAL_EXAM_PREVIEW_READY','preview_token'=>$token,'program_count'=>$solved,'required_exams'=>$totalRequired,'returned_exams'=>$totalReturned,'programs'=>array_keys($results),'exam_dates'=>$dates,'database_write'=>false]);
} catch (ExamFailure $e) {
    exReply($e->http,['success'=>false,'status'=>$e->errorCode,'message'=>$e->getMessage(),'database_write'=>false]);
} catch (Throwable $e) {
    error_log('Global exam preview: '.$e->getMessage());
    exReply(500,['success'=>false,'status'=>'GLOBAL_EXAM_PREVIEW_FAILED','message'=>'School-wide preview failed. No exam schedules were saved. Check PHP error logs.','database_write'=>false]);
}

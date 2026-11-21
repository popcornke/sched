<?php
declare(strict_types=1);
require_once __DIR__.'/exam-global-common.php';
exGuard('POST');
exReply(403,['success'=>false,'status'=>'ACADEMIC_APPROVAL_REQUIRED','message'=>'Exam schedules may only be saved by Academic Office approval. Submit the school-wide preview for review.']);

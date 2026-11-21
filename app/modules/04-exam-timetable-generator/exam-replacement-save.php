<?php
declare(strict_types=1);
require_once __DIR__.'/exam-common.php';
exGuard('POST');
exReply(403,['success'=>false,'status'=>'ACADEMIC_APPROVAL_REQUIRED','message'=>'Direct exam schedule saving is disabled. Submit the school-wide preview for Academic Office approval.']);

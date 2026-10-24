<?php
declare(strict_types=1);
// CLI ONLY: php scripts/create-exam-staff.php staffusername staff@example.com
if (PHP_SAPI !== 'cli') {http_response_code(404);exit;}
require_once dirname(__DIR__) . '/app/shared/auth.php';
$username = trim((string)($argv[1]??''));
$email = trim((string)($argv[2]??''));
if (!preg_match('/^[A-Za-z0-9_.-]{3,80}$/D', $username) || !filter_var($email,FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR,"Usage: php scripts/create-exam-staff.php <username> <email>\n");exit(1);
}
fwrite(STDOUT,"Enter new STAFF password (12+ characters): ");
$password = rtrim((string)fgets(STDIN),"\r\n");
if (strlen($password)<12 || strlen($password)>128) {fwrite(STDERR,"Password must be 12–128 characters.\n");exit(1);}
try {
    $pdo = authDb();
    $st = $pdo->prepare("INSERT INTO auth_users (username,email,email_verified_at,password_hash,role,is_active,failed_attempts,lockout_level,locked_until) VALUES (:username,:email,NULL,:password,'STAFF',1,0,0,NULL)");
    $st->execute(['username'=>$username,'email'=>$email,'password'=>password_hash($password,PASSWORD_DEFAULT)]);
    fwrite(STDOUT,"STAFF account created. OTP is not used for this role.\n");
} catch (Throwable $e) {fwrite(STDERR,"Unable to create staff. Check migration, DB connection and duplicate username/email.\n");error_log($e->getMessage());exit(1);}

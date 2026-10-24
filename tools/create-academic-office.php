<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require_once dirname(__DIR__).'/app/shared/auth.php';
if($argc!==3){fwrite(STDERR,"Usage: php tools/create-academic-office.php USERNAME EMAIL\n");exit(1);}
$username=trim($argv[1]);$email=trim($argv[2]);
if(!preg_match('/^[A-Za-z0-9_.-]{4,80}$/',$username)||!filter_var($email,FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"Invalid username or email.\n");exit(1);}
fwrite(STDOUT,"Enter a NEW Academic Office password (minimum 12 characters): ");
if(strncasecmp(PHP_OS,'WIN',3)!==0 && function_exists('shell_exec')){system('stty -echo');}$password=rtrim((string)fgets(STDIN),"\r\n");if(strncasecmp(PHP_OS,'WIN',3)!==0 && function_exists('shell_exec')){system('stty echo');echo "\n";}
if(strlen($password)<12||strlen($password)>128){fwrite(STDERR,"Password length must be 12–128 characters.\n");exit(1);}
try{$db=authDb();$q=$db->prepare("INSERT INTO auth_users(username,email,password_hash,role,is_active,email_verified_at,failed_attempts,locked_until) VALUES(?,?,?,'ACADEMIC_OFFICE',1,NULL,0,NULL)");$q->execute([$username,$email,password_hash($password,PASSWORD_DEFAULT)]);echo "Academic Office account created. Email OTP is not required for this role in this test build.\n";}catch(Throwable $e){fwrite(STDERR,"Account creation failed: ".$e->getMessage()."\n");exit(1);}finally{$password='';}

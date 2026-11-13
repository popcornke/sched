<?php
require_once 'app/config/database.php';

$db = getDatabase();

$stmt = $db->prepare(
    "SELECT password_hash FROM auth_users WHERE username = ?"
);
$stmt->execute(['superadmin']);

$hash = $stmt->fetchColumn();

if ($hash && password_verify('Superadmin!123', $hash)) {
    echo "PASSWORD MATCH\n";
} else {
    echo "PASSWORD MISMATCH\n";
}

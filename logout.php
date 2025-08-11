<?php
session_start();
$logFile = 'debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Đăng xuất người dùng ID {$_SESSION['user_id']}\n", FILE_APPEND);
session_destroy();
header('Location: index.php');
exit;
?>
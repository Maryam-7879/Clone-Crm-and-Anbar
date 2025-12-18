<?php
session_start();
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';

// اگر کاربر لاگین نکرده است، به صفحه لاگین هدایت شود
if (!isLoggedIn()) {
    header('Location: public/login.php');
    exit();
}

// اگر کاربر لاگین کرده است، به داشبورد هدایت شود
header('Location: public/dashboard.php');
exit();
?>
<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/auth.php';

// خروج کاربر
logoutUser();

// هدایت به صفحه لاگین
header('Location: login.php');
exit();
?>

<?php
// تنظیمات اصلی سیستم
define('APP_NAME', 'سیستم مدیریت ارتباط با مشتری');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/new-crm/');

// تنظیمات دیتابیس
define('DB_HOST', 'localhost');
define('DB_NAME', 'crm_system_new');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// تنظیمات امنیتی
define('ENCRYPTION_KEY', 'CRM_SECRET_KEY_2024_SECURE_RANDOM_STRING_HERE');
define('SESSION_TIMEOUT', 3600); // 1 ساعت
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 دقیقه

// تنظیمات فایل آپلود
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);
define('UPLOAD_PATH', 'uploads/');

// تنظیمات ایمیل
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', '');
define('MAIL_PASSWORD', '');
define('MAIL_FROM_EMAIL', '');
define('MAIL_FROM_NAME', 'سیستم CRM');

// تنظیمات متفرقه
define('DEFAULT_TIMEZONE', 'Asia/Tehran');
define('RECORDS_PER_PAGE', 20);
define('CURRENCY', 'تومان');

// تنظیم منطقه زمانی
date_default_timezone_set(DEFAULT_TIMEZONE);

// تنظیمات خطا
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// شروع بافر خروجی
ob_start();
?>

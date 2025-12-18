<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/auth.php';

// بررسی دسترسی
if (!hasRole('admin')) {
    setMessage('شما دسترسی لازم برای دانلود پشتیبان را ندارید', 'error');
    header('Location: settings.php');
    exit();
}

$file = $_GET['file'] ?? '';

// بررسی امنیت فایل
if (empty($file) || !preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $file)) {
    setMessage('فایل پشتیبان نامعتبر است', 'error');
    header('Location: settings.php');
    exit();
}

$file_path = __DIR__ . '/../backups/' . $file;

// بررسی وجود فایل
if (!file_exists($file_path)) {
    setMessage('فایل پشتیبان یافت نشد', 'error');
    header('Location: settings.php');
    exit();
}

// تنظیم هدرهای دانلود
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: must-revalidate');
header('Pragma: public');

// خواندن و ارسال فایل
readfile($file_path);

// ثبت فعالیت
logActivity($_SESSION['user_id'], 'download_backup', null, null, ['file' => $file]);

exit();
?>

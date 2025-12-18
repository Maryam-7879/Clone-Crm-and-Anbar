<?php
require_once __DIR__ . '/private/config.php';
require_once __DIR__ . '/private/database.php';

echo "تست اتصال به دیتابیس...\n\n";

try {
    $database = new Database();
    $conn = $database->getConnection();

    echo "✓ اتصال به دیتابیس موفقیت‌آمیز بود!\n\n";

    // تست کوئری
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch(PDO::FETCH_ASSOC);

    echo "تعداد کاربران در دیتابیس: " . $row['count'] . "\n";

    // نمایش اطلاعات اتصال
    echo "\nاطلاعات اتصال:\n";
    echo "Host: " . DB_HOST . "\n";
    echo "Port: " . DB_PORT . "\n";
    echo "Database: " . DB_NAME . "\n";
    echo "User: " . DB_USER . "\n";
    echo "Connection: " . DB_CONNECTION . "\n";

} catch (Exception $e) {
    echo "✗ خطا در اتصال: " . $e->getMessage() . "\n";
}
?>

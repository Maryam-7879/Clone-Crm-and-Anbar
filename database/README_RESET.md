# راهنمای بازسازی دیتابیس CRM

## مراحل اجرا:

### روش 1: از طریق phpMyAdmin

1. باز کردن phpMyAdmin در مرورگر: `http://localhost/phpmyadmin`
2. انتخاب دیتابیس `crm_system` (یا حذف آن اگر وجود دارد)
3. رفتن به تب Import
4. انتخاب فایل `schema.sql` از پوشه `database`
5. کلیک روی Go برای اجرا

### روش 2: از طریق Command Line

```bash
# وارد کردن به پوشه پروژه
cd /Applications/XAMPP/xamppfiles/htdocs/project-comp/new-crm

# اجرای دستورات MySQL
/Applications/XAMPP/xamppfiles/bin/mysql -u root -p -e "DROP DATABASE IF EXISTS crm_system; CREATE DATABASE crm_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import کردن schema
/Applications/XAMPP/xamppfiles/bin/mysql -u root -p crm_system < database/schema.sql
```

### روش 3: استفاده از فایل reset_database.sql

فایل `reset_database.sql` شامل دستورات اولیه برای حذف و ایجاد دیتابیس است.

## داده‌های تستی که اضافه می‌شوند:

### جداول جدید انبار:
- **5 دسته‌بندی** (الکترونیک، پوشاک، مواد غذایی، لوازم خانگی، کتاب و آموزش)
- **5 تامین‌کننده** (با اطلاعات تماس کامل)
- **10 محصول** (با تمام فیلدهای جدید شامل purchase_price, selling_price, is_active)
- **10 رکورد موجودی** (برای هر محصول)
- **13 تراکنش** (ورود، خروج، تعدیل)
- **5 هشدار** (موجودی کم، سفارشی)

### جداول CRM:
- **4 کاربر** (admin, manager, sales1, user1)
- **5 مشتری** (فردی و شرکتی)
- **5 لید**
- **5 وظیفه**
- **3 فروش**
- **3 پرداخت**

## اطلاعات ورود پیش‌فرض:

- **نام کاربری:** admin
- **رمز عبور:** password
- **نام کاربری:** manager
- **رمز عبور:** password

## نکات مهم:

1. قبل از اجرا، از دیتابیس فعلی backup بگیرید
2. تمام داده‌های قبلی حذف می‌شوند
3. فایل schema.sql شامل تمام جداول و داده‌های تستی است
4. بعد از اجرا، می‌توانید تمام بخش‌های انبار را تست کنید

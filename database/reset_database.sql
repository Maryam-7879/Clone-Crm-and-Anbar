-- دستورات برای بازسازی دیتابیس CRM
-- این فایل را در phpMyAdmin یا از طریق MySQL command line اجرا کنید

-- حذف و ایجاد دیتابیس
DROP DATABASE IF EXISTS crm_system;
CREATE DATABASE crm_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- استفاده از دیتابیس
USE crm_system;

-- سپس فایل schema.sql را import کنید
-- از phpMyAdmin: Import > Choose File > schema.sql > Go
-- یا از command line: mysql -u root -p crm_system < schema.sql

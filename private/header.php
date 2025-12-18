<?php
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$current_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Cache buster for fonts -->

    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #ff6b35;
            --primary-dark: #e55a2b;
            --primary-light: #ff8660;
            --primary-ultralight: #fff2ef;
            --secondary-color: #ffffff;
            --text-dark: #1a1a1a;
            --text-medium: #4a4a4a;
            --text-light: #6b7280;
            --text-muted: #9ca3af;
            --border-color: #e5e7eb;
            --bg-light: #fafafa;
            --bg-card: #ffffff;
            --bg-sidebar: linear-gradient(145deg, #ff6b35, #e55a2b);
            --success-color: #10b981;
            --success-light: #ecfdf5;
            --warning-color: #f59e0b;
            --warning-light: #fffbeb;
            --danger-color: #ef4444;
            --danger-light: #fef2f2;
            --info-color: #3b82f6;
            --info-light: #eff6ff;
            --dark-color: #374151;
            --dark-light: #f3f4f6;
            --shadow: 0 4px 20px rgba(255, 107, 53, 0.08);
            --shadow-hover: 0 8px 30px rgba(255, 107, 53, 0.12);
            --shadow-card: 0 1px 3px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
            --border-radius-sm: 8px;
            --transition: background-color 0.2s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            right: 0;
            height: 100vh;
            width: 280px;
            background: var(--bg-sidebar);
            color: white;
            z-index: 1000;
            transition: var(--transition);
            overflow: hidden;
            border-left: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            max-height: 100vh;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            flex-shrink: 0;
        }

        .sidebar-header h4 {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .sidebar-header small {
            opacity: 0.8;
        }

        .sidebar-menu {
            padding: 20px 0 40px 0;
            flex: 1;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
        }

        .sidebar-menu .menu-item {
            display: block;
            padding: 15px 25px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: var(--transition);
            border-right: 3px solid transparent;
            margin-bottom: 2px;
            white-space: nowrap;
            min-height: 50px;
            display: flex;
            align-items: center;
        }

        .sidebar-menu .menu-item:hover,
        .sidebar-menu .menu-item.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-right-color: white;
            cursor: pointer;
            position: relative;
            z-index: 5;
        }

        .sidebar-menu .menu-item i {
            width: 20px;
            margin-left: 10px;
        }

        .sidebar-user {
            position: relative;
            width: 100%;
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: var(--bg-sidebar);
            flex-shrink: 0;
            margin-top: auto;
            z-index: 1;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px 0;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 10px;
        }

        .logout-btn {
            width: 100%;
            padding: 10px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 8px;
            transition: var(--transition);
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Main Content */
        .main-content {
            margin-right: 280px;
            min-height: 100vh;
            transition: var(--transition);
        }

        /* Top Navigation */
        .top-nav {
            background: white;
            padding: 15px 30px;
            box-shadow: var(--shadow);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0;
        }

        .breadcrumb {
            background: transparent;
            padding: 0;
            margin: 5px 0 0 0;
        }

        .breadcrumb-item a {
            color: var(--primary-color);
            text-decoration: none;
        }

        /* Content Area */
        .content {
            padding: 30px;
        }

        /* Cards */
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: var(--transition);
            background: var(--bg-card);
        }

        .card:hover {
            box-shadow: var(--shadow-hover);
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--border-color);
            padding: 20px;
            font-weight: 600;
        }

        .card-body {
            padding: 20px;
        }

        /* Override Bootstrap Colors */
        .btn-primary, .btn-primary:focus, .btn-primary:active {
            background: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
            color: white !important;
            font-weight: 500;
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-card);
            transition: var(--transition);
        }

        .btn-primary:hover {
            background: var(--primary-dark) !important;
            border-color: var(--primary-dark) !important;
        }

        .btn-outline-primary, .btn-outline-primary:focus, .btn-outline-primary:active {
            border: 2px solid var(--primary-color) !important;
            color: var(--primary-color) !important;
            background: transparent !important;
            font-weight: 500;
            border-radius: var(--border-radius-sm);
        }

        .btn-outline-primary:hover {
            background: var(--primary-color) !important;
            color: white !important;
        }

        .btn-success, .btn-success:focus, .btn-success:active {
            background: var(--success-color) !important;
            border-color: var(--success-color) !important;
            color: white !important;
            border-radius: var(--border-radius-sm);
        }

        .btn-success:hover {
            background: #059669 !important;
            border-color: #059669 !important;
        }

        .btn-warning, .btn-warning:focus, .btn-warning:active {
            background: var(--warning-color) !important;
            border-color: var(--warning-color) !important;
            color: white !important;
            border-radius: var(--border-radius-sm);
        }

        .btn-warning:hover {
            background: #d97706 !important;
            border-color: #d97706 !important;
        }

        .btn-danger, .btn-danger:focus, .btn-danger:active {
            background: var(--danger-color) !important;
            border-color: var(--danger-color) !important;
            color: white !important;
            border-radius: var(--border-radius-sm);
        }

        .btn-danger:hover {
            background: #dc2626 !important;
            border-color: #dc2626 !important;
        }

        .btn-info, .btn-info:focus, .btn-info:active {
            background: var(--info-color) !important;
            border-color: var(--info-color) !important;
            color: white !important;
            border-radius: var(--border-radius-sm);
        }

        .btn-info:hover {
            background: #2563eb !important;
            border-color: #2563eb !important;
        }

        .btn-secondary, .btn-secondary:focus, .btn-secondary:active {
            background: var(--text-light) !important;
            border-color: var(--text-light) !important;
            color: white !important;
            border-radius: var(--border-radius-sm);
        }

        .btn-secondary:hover {
            background: var(--text-medium) !important;
            border-color: var(--text-medium) !important;
        }

        .btn-outline-secondary, .btn-outline-secondary:focus, .btn-outline-secondary:active {
            border: 2px solid var(--border-color) !important;
            color: var(--text-medium) !important;
            background: transparent !important;
            border-radius: var(--border-radius-sm);
        }

        .btn-outline-secondary:hover {
            background: var(--bg-light) !important;
            border-color: var(--text-light) !important;
            color: var(--text-dark) !important;
        }

        /* Forms */
        .form-control, .form-select {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 10px 15px;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(255, 107, 53, 0.25);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        /* Tables */
        .table {
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .table thead th {
            background: var(--bg-light);
            border: none;
            font-weight: 600;
            color: var(--text-dark);
            padding: 15px;
        }

        .table tbody td {
            padding: 15px;
            border-top: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: var(--primary-ultralight) !important;
        }

        /* بهینه‌سازی badges در جداول */
        .table .badge {
            padding: 6px 12px !important;
            font-size: 0.75rem !important;
            min-width: 60px !important;
            text-align: center !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
        }

        /* ستون‌های جدول بهتر */
        .table td:has(.badge) {
            min-width: 120px;
            text-align: center;
        }

        .table .badge-sm {
            padding: 4px 8px !important;
            font-size: 0.7rem !important;
            min-width: 50px !important;
        }

        /* Override Bootstrap Tables */
        .table-success {
            background: var(--success-light) !important;
            color: var(--success-color) !important;
        }

        .table-warning {
            background: var(--warning-light) !important;
            color: var(--warning-color) !important;
        }

        .table-danger {
            background: var(--danger-light) !important;
            color: var(--danger-color) !important;
        }

        .table-info {
            background: var(--info-light) !important;
            color: var(--info-color) !important;
        }

        .table-secondary {
            background: var(--dark-light) !important;
            color: var(--dark-color) !important;
        }

        /* Override Bootstrap Alerts */
        .alert {
            border: none !important;
            border-radius: var(--border-radius) !important;
            padding: 16px 20px !important;
            border-left: 4px solid !important;
            font-weight: 500;
        }

        .alert-success {
            background: var(--success-light) !important;
            color: var(--success-color) !important;
            border-left-color: var(--success-color) !important;
        }

        .alert-danger {
            background: var(--danger-light) !important;
            color: var(--danger-color) !important;
            border-left-color: var(--danger-color) !important;
            border: 1px solid var(--danger-color) !important;
        }

        .alert-warning {
            background: var(--warning-light) !important;
            color: var(--warning-color) !important;
            border-left-color: var(--warning-color) !important;
        }

        .alert-info {
            background: var(--info-light) !important;
            color: var(--info-color) !important;
            border-left-color: var(--info-color) !important;
        }

        /* Override Bootstrap Badges */
        .badge {
            padding: 8px 14px !important;
            border-radius: 20px !important;
            font-weight: 600 !important;
            font-size: 0.8rem !important;
            line-height: 1 !important;
            display: inline-flex !important;
            align-items: center !important;
            min-width: fit-content !important;
            white-space: nowrap !important;
        }

        .bg-primary, .badge.bg-primary {
            background: var(--primary-color) !important;
            color: white !important;
        }

        .bg-success, .badge.bg-success {
            background: var(--success-color) !important;
            color: white !important;
        }

        .bg-warning, .badge.bg-warning {
            background: var(--warning-color) !important;
            color: white !important;
        }

        .bg-danger, .badge.bg-danger {
            background: var(--danger-color) !important;
            color: white !important;
        }

        .bg-info, .badge.bg-info {
            background: var(--info-color) !important;
            color: white !important;
        }

        .bg-secondary, .badge.bg-secondary {
            background: var(--text-light) !important;
            color: white !important;
        }

        .bg-dark, .badge.bg-dark {
            background: var(--dark-color) !important;
            color: white !important;
        }

        /* Text Colors */
        .text-primary {
            color: var(--primary-color) !important;
        }

        .text-success {
            color: var(--success-color) !important;
        }

        .text-warning {
            color: var(--warning-color) !important;
        }

        .text-danger {
            color: var(--danger-color) !important;
        }

        .text-info {
            color: var(--info-color) !important;
        }

        .text-muted {
            color: var(--text-muted) !important;
        }

        /* استایل‌های اضافی برای وضعیت‌ها */
        .status-active {
            background: var(--success-color) !important;
            color: white !important;
        }

        .status-inactive {
            background: var(--text-muted) !important;
            color: white !important;
        }

        .status-pending {
            background: var(--warning-color) !important;
            color: white !important;
        }

        .status-completed {
            background: var(--success-color) !important;
            color: white !important;
        }

        .status-cancelled {
            background: var(--danger-color) !important;
            color: white !important;
        }

        /* badge sizes */
        .badge-xs {
            padding: 3px 6px !important;
            font-size: 0.65rem !important;
            min-width: 40px !important;
        }

        .badge-lg {
            padding: 10px 16px !important;
            font-size: 0.9rem !important;
            min-width: 80px !important;
        }

        /* Enhanced badges for status */
        .badge-status {
            padding: 8px 16px !important;
            font-size: 0.8rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            border-radius: 25px !important;
        }

        /* Override Bootstrap Progress Bars */
        .progress {
            background: var(--border-color) !important;
            border-radius: 10px !important;
            height: 8px !important;
        }

        .progress-bar {
            background: var(--primary-color) !important;
            border-radius: 10px !important;
        }

        .progress-bar.bg-success {
            background: var(--success-color) !important;
        }

        .progress-bar.bg-warning {
            background: var(--warning-color) !important;
        }

        .progress-bar.bg-danger {
            background: var(--danger-color) !important;
        }

        .progress-bar.bg-info {
            background: var(--info-color) !important;
        }

        /* Override Bootstrap Pagination */
        .pagination .page-link {
            background: white !important;
            border: 1px solid var(--border-color) !important;
            color: var(--text-medium) !important;
            border-radius: var(--border-radius-sm) !important;
            margin: 0 2px;
            font-weight: 500;
        }

        .pagination .page-link:hover {
            background: var(--primary-ultralight) !important;
            border-color: var(--primary-color) !important;
            color: var(--primary-color) !important;
        }

        .pagination .page-item.active .page-link {
            background: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
            color: white !important;
        }

        /* Override Bootstrap Forms */
        .form-check-input:checked {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }

        .form-switch .form-check-input:checked {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }

        /* بهبود select options */
        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 6 7 7 7-7'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: left 0.75rem center !important;
            background-size: 16px 12px !important;
            padding-left: 2.5rem !important;
            position: relative !important;
            z-index: 1000 !important;
        }

        .form-select:focus {
            z-index: 1050 !important;
            position: relative !important;
        }

        .form-select option {
            padding: 8px 12px !important;
            color: var(--text-dark) !important;
            background: white !important;
            position: relative !important;
            z-index: 1051 !important;
        }

        .form-select option:checked {
            background: var(--primary-color) !important;
            color: white !important;
        }

        /* ریسپانسیو امن جداول */
        .table-responsive {
            overflow-x: auto !important;
            overflow-y: visible !important;
            position: relative !important;
            -webkit-overflow-scrolling: touch !important;
        }

        .table-responsive .form-select {
            position: relative !important;
            z-index: 1 !important;
            min-width: 120px !important;
        }

        .table td:has(select) {
            position: relative !important;
            overflow: visible !important;
        }

        .dropdown-container {
            position: relative !important;
            z-index: 1070 !important;
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1100;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 12px;
            box-shadow: var(--shadow);
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1040;
        }

        /* Mobile Responsive */
        @media (max-width: 1200px) {
            .sidebar {
                width: 260px;
            }
            
            .main-content {
                margin-right: 260px;
            }
        }

        @media (max-width: 992px) {
            .mobile-menu-toggle {
                display: block;
            }

            .sidebar {
                width: 280px;
                transform: translateX(100%);
                z-index: 1050;
                transition: transform 0.3s ease;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .sidebar-overlay.show {
                display: block;
            }

            .main-content {
                margin-right: 0;
            }

            .content {
                padding: 20px 15px;
                margin-top: 60px;
            }

            .top-nav {
                padding: 15px 20px 15px 60px;
                position: fixed;
                width: 100%;
                top: 0;
                z-index: 1030;
            }
            
            .nav-title {
                font-size: 1.2rem;
            }
        }

        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }

            .sidebar {
                width: 280px;
                transform: translateX(100%);
                z-index: 1050;
                transition: transform 0.3s ease;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .sidebar-overlay.show {
                display: block;
            }

            .main-content {
                margin-right: 0;
            }

            .content {
                padding: 20px 15px;
                margin-top: 60px;
            }

            .top-nav {
                padding: 15px 20px 15px 60px;
                position: fixed;
                width: 100%;
                top: 0;
                z-index: 1030;
            }
            
            .nav-title {
                font-size: 1.2rem;
            }
            
            .table-responsive {
                font-size: 0.85rem;
                border-radius: var(--border-radius-sm);
                margin-bottom: 1rem;
                overflow-x: auto !important;
            }
            
            .table {
                margin-bottom: 0;
                min-width: 900px;
            }
            
            .table td, .table th {
                padding: 10px 8px;
                white-space: nowrap;
            }
            
            .badge {
                padding: 4px 8px !important;
                font-size: 0.7rem !important;
                min-width: 50px !important;
            }
            
            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 15px;
                align-items: stretch !important;
            }
            
            .btn-group {
                width: 100%;
                flex-wrap: wrap;
            }
            
            .btn-group .btn {
                flex: 1;
                min-width: 100px;
                margin-bottom: 5px;
            }
            
            .card-body {
                padding: 15px;
            }
            
            .card-header {
                padding: 15px;
            }
            
            .form-row {
                margin-bottom: 15px;
            }
            
            .col-form-label {
                margin-bottom: 5px;
                font-weight: 600;
            }
            
            .pagination {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .breadcrumb {
                font-size: 0.9rem;
                flex-wrap: wrap;
            }
            
            .stats-card {
                margin-bottom: 15px;
                padding: 20px;
            }
            
            .stats-card .value {
                font-size: 1.5rem;
            }
            
            .form-control, .form-select {
                padding: 12px 15px;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .container-fluid {
                padding: 5px;
            }
            
            .content {
                padding: 15px 10px;
                margin-top: 55px;
            }
            
            .top-nav {
                padding: 12px 15px 12px 55px;
            }
            
            .nav-title {
                font-size: 1.1rem;
            }
            
            .mobile-menu-toggle {
                top: 12px;
                left: 12px;
                padding: 8px 10px;
            }
            
            .table-responsive {
                font-size: 0.8rem;
                overflow-x: auto !important;
            }
            
            .table td, .table th {
                padding: 6px 4px;
                font-size: 0.75rem;
                min-width: 80px;
            }

            .table { min-width: 720px; }
            
            .badge {
                font-size: 0.65rem !important;
                padding: 3px 6px !important;
                min-width: 40px !important;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .stats-card {
                padding: 15px;
                text-align: center;
            }
            
            .stats-card .icon {
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
                margin: 0 auto 10px;
            }
            
            .stats-card .value {
                font-size: 1.3rem;
            }
            
            .card-body {
                padding: 12px;
            }
            
            .card-header {
                padding: 12px;
                font-size: 0.9rem;
            }
            
            .form-control, .form-select {
                padding: 10px 12px;
                font-size: 0.9rem;
            }
            
            .btn {
                padding: 8px 12px;
                font-size: 0.9rem;
            }
            
            .btn-group .btn {
                min-width: 80px;
                padding: 6px 8px;
                font-size: 0.8rem;
            }
            
            .d-flex.justify-content-between {
                gap: 10px;
            }
            
            .pagination .page-link {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
            
            .breadcrumb-item {
                font-size: 0.8rem;
            }
            
            .alert {
                padding: 12px 15px !important;
                font-size: 0.9rem;
            }
            
            .form-label {
                font-size: 0.9rem;
                margin-bottom: 5px;
            }
        }

        @media (max-width: 400px) {
            .sidebar {
                width: 100%;
            }
            
            .content {
                padding: 10px 8px;
            }
            
            .nav-title {
                font-size: 1rem;
            }
            
            .table td, .table th {
                padding: 4px 2px;
                font-size: 0.7rem;
                min-width: 60px;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn-group .btn {
                width: 100%;
                margin-bottom: 3px;
            }
            
            .stats-card .value {
                font-size: 1.1rem;
            }
            
            .form-control, .form-select {
                font-size: 0.85rem;
            }
        }

        /* Loading Spinner */
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }

        /* Custom Scrollbar */
        .sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 2px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        /* Stats Cards */
        .stats-card {
            padding: 25px;
            border-radius: var(--border-radius);
            background: white;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .stats-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .stats-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .stats-card .label {
            color: var(--text-light);
            font-weight: 500;
        }
    </style>
    <?php if (isset($additional_css)): ?>
        <?php echo $additional_css; ?>
    <?php endif; ?>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-users-cog me-2"></i>سیستم CRM</h4>
            <small>مدیریت ارتباط با مشتری</small>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                داشبورد
            </a>
            
            <?php if (hasPermission('view_customers')): ?>
            <a href="customers.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                مشتریان
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('view_leads')): ?>
            <a href="leads.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'leads.php' ? 'active' : ''; ?>">
                <i class="fas fa-bullseye"></i>
                لیدها
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('view_tasks')): ?>
            <a href="tasks.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'tasks.php' ? 'active' : ''; ?>">
                <i class="fas fa-tasks"></i>
                وظایف
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('view_sales')): ?>
            <a href="sales.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i>
                فروش
            </a>
            <?php endif; ?>
            
            <a href="products.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
                <i class="fas fa-box"></i>
                محصولات
            </a>
            
            <a href="categories.php" class="menu-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['categories.php', 'category_form.php', 'category_view.php']) ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i>
                دسته‌بندی‌ها
            </a>
            
            <a href="suppliers.php" class="menu-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['suppliers.php', 'supplier_form.php', 'supplier_view.php']) ? 'active' : ''; ?>">
                <i class="fas fa-truck"></i>
                تامین‌کنندگان
            </a>
            
            <a href="inventory.php" class="menu-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['inventory.php', 'inventory_view.php', 'inventory_edit.php', 'inventory_adjust.php']) ? 'active' : ''; ?>">
                <i class="fas fa-warehouse"></i>
                موجودی
            </a>
            
            <a href="transactions.php" class="menu-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['transactions.php', 'transaction_form.php', 'transaction_view.php', 'transaction_edit.php']) ? 'active' : ''; ?>">
                <i class="fas fa-exchange-alt"></i>
                تراکنش‌ها
            </a>
            
            <a href="alerts.php" class="menu-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['alerts.php', 'alert_form.php', 'alert_view.php']) ? 'active' : ''; ?>">
                <i class="fas fa-bell"></i>
                هشدارها
            </a>
            
            <?php if (hasPermission('view_reports')): ?>
            <a href="reports.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                گزارش‌ها
            </a>
            <?php endif; ?>
            
            <?php if (hasRole('admin')): ?>
            <a href="settings.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                تنظیمات
            </a>
            
            <a href="users.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-cog"></i>
                کاربران
            </a>
            <?php endif; ?>
        </div>
        
        <div class="sidebar-user">
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <div class="fw-bold"><?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?></div>
                    <small class="opacity-75"><?php echo getRoleTitle($current_user['role']); ?></small>
                </div>
            </div>
            <form method="POST" action="logout.php" style="display: inline;">
                <button type="submit" class="logout-btn">
                    <i class="fas fa-sign-out-alt me-2"></i>خروج
                </button>
            </form>
        </div>
    </div>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <div class="top-nav d-flex justify-content-between align-items-center">
            <div>
                <h1 class="nav-title"><?php echo isset($page_title) ? $page_title : 'داشبورد'; ?></h1>
                <?php if (isset($breadcrumb)): ?>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <?php foreach ($breadcrumb as $item): ?>
                            <?php if (isset($item['url'])): ?>
                                <li class="breadcrumb-item"><a href="<?php echo $item['url']; ?>"><?php echo $item['title']; ?></a></li>
                            <?php else: ?>
                                <li class="breadcrumb-item active"><?php echo $item['title']; ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </nav>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center">
                <span class="text-muted me-3 d-none d-md-inline">
                    <i class="fas fa-clock me-1"></i>
                    <?php echo formatPersianDate(date('Y-m-d H:i:s')); ?>
                </span>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <?php echo displayMessage(); ?>

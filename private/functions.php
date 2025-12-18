<?php
require_once __DIR__ . '/config.php';

// تابع برای نمایش پیام‌ها
function displayMessage($type = '') {
    $messages = ['success_message', 'error_message', 'warning_message', 'info_message'];
    $output = '';
    
    foreach ($messages as $message_type) {
        if (isset($_SESSION[$message_type])) {
            $class = str_replace('_message', '', $message_type);
            $icon = getMessageIcon($class);
            
            $alert_class = $class === 'error' ? 'danger' : $class;
            $output .= '<div class="alert alert-' . $alert_class . ' alert-dismissible fade show" role="alert">';
            $output .= '<i class="' . $icon . ' me-2"></i>';
            $output .= $_SESSION[$message_type];
            $output .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            $output .= '</div>';
            
            unset($_SESSION[$message_type]);
        }
    }
    
    return $output;
}

// دریافت آیکون پیام
function getMessageIcon($type) {
    $icons = [
        'success' => 'fas fa-check-circle',
        'error' => 'fas fa-times-circle',
        'warning' => 'fas fa-exclamation-triangle',
        'info' => 'fas fa-info-circle'
    ];
    
    return $icons[$type] ?? 'fas fa-info-circle';
}

// تنظیم پیام
function setMessage($message, $type = 'success') {
    $_SESSION[$type . '_message'] = $message;
}

// فرمت کردن تاریخ فارسی
function formatPersianDate($date, $format = 'Y/m/d H:i') {
    if (!$date) return '-';
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return jdate($format, $timestamp);
}

// تبدیل تاریخ میلادی به شمسی
function jdate($format, $timestamp = '') {
    $timestamp = $timestamp ? $timestamp : time();
    
    $jy = $jm = $jd = 0;
    gregorian_to_jalali(date('Y', $timestamp), date('m', $timestamp), date('d', $timestamp), $jy, $jm, $jd);
    
    $persian_months = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
        4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
        10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
    ];
    
    $month_name = $persian_months[$jm];
    
    $format = str_replace(['Y', 'M', 'm', 'd', 'H', 'i', 's'], 
                         [$jy, $month_name, sprintf('%02d', $jm), sprintf('%02d', $jd), 
                          date('H', $timestamp), date('i', $timestamp), date('s', $timestamp)], $format);
    
    return $format;
}

// تابع برای تبدیل تاریخ میلادی به شمسی برای چارت
function convertToJalaliForChart($gregorian_date) {
    $timestamp = strtotime($gregorian_date);
    $jy = $jm = $jd = 0;
    gregorian_to_jalali(date('Y', $timestamp), date('m', $timestamp), date('d', $timestamp), $jy, $jm, $jd);
    
    $persian_months = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
        4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
        10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
    ];
    
    return $persian_months[$jm] . ' ' . $jy;
}

// تبدیل تاریخ میلادی به شمسی
function gregorian_to_jalali($gy, $gm, $gd, &$jy, &$jm, &$jd) {
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    
    if ($gy <= 1600) {
        $jy = 0;
        $gy -= 621;
    } else {
        $jy = 979;
        $gy -= 1600;
    }
    
    if ($gm > 2) {
        $gy2 = $gy + 1;
    } else {
        $gy2 = $gy;
    }
    
    $days = (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + 
            ((int)(($gy2 + 399) / 400)) - 80 + $gd + $g_d_m[$gm - 1];
    
    $jy += 33 * ((int)($days / 12053));
    $days %= 12053;
    
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    
    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
}

// فرمت کردن مبلغ
function formatMoney($amount, $currency = CURRENCY) {
    return number_format($amount, 0, '.', ',') . ' ' . $currency;
}

// فرمت کردن شماره تلفن
function formatPhone($phone) {
    if (!$phone) return '-';
    
    $phone = preg_replace('/\D/', '', $phone);
    
    if (strlen($phone) == 11 && substr($phone, 0, 2) == '09') {
        return substr($phone, 0, 4) . '-' . substr($phone, 4, 3) . '-' . substr($phone, 7);
    } elseif (strlen($phone) == 11 && substr($phone, 0, 3) == '021') {
        return substr($phone, 0, 3) . '-' . substr($phone, 3, 4) . '-' . substr($phone, 7);
    }
    
    return $phone;
}

// کوتاه کردن متن
function truncateText($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }
    
    return mb_substr($text, 0, $length, 'UTF-8') . $suffix;
}

// تولید کد یکتا
function generateUniqueCode($prefix = '', $length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = $prefix;
    
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $code;
}

// بررسی فرمت فایل
function isValidFileType($filename, $allowed_types = UPLOAD_ALLOWED_TYPES) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, $allowed_types);
}

// بررسی اندازه فایل
function isValidFileSize($file_size, $max_size = UPLOAD_MAX_SIZE) {
    return $file_size <= $max_size;
}

// آپلود فایل
function uploadFile($file, $upload_path = UPLOAD_PATH, $allowed_types = UPLOAD_ALLOWED_TYPES) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'message' => 'فایلی انتخاب نشده است'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'خطا در آپلود فایل'];
    }
    
    if (!isValidFileType($file['name'], $allowed_types)) {
        return ['success' => false, 'message' => 'فرمت فایل مجاز نیست'];
    }
    
    if (!isValidFileSize($file['size'])) {
        return ['success' => false, 'message' => 'اندازه فایل بیش از حد مجاز است'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid() . '.' . $extension;
    $full_path = $upload_path . $filename;
    
    if (!is_dir($upload_path)) {
        mkdir($upload_path, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $full_path)) {
        return [
            'success' => true, 
            'filename' => $filename, 
            'path' => $full_path,
            'original_name' => $file['name']
        ];
    }
    
    return ['success' => false, 'message' => 'خطا در ذخیره فایل'];
}

// حذف فایل
function deleteFile($file_path) {
    if (file_exists($file_path)) {
        return unlink($file_path);
    }
    return false;
}

// دریافت اندازه فایل قابل خواندن
function formatFileSize($bytes) {
    $units = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

// ایجاد صفحه‌بندی
function createPagination($current_page, $total_records, $records_per_page, $base_url) {
    $total_pages = ceil($total_records / $records_per_page);
    
    if ($total_pages <= 1) return '';
    
    $output = '<nav aria-label="صفحه‌بندی">';
    $output .= '<ul class="pagination justify-content-center">';
    
    // دکمه قبلی
    if ($current_page > 1) {
        $output .= '<li class="page-item">';
        $output .= '<a class="page-link" href="' . $base_url . '&page=' . ($current_page - 1) . '">قبلی</a>';
        $output .= '</li>';
    }
    
    // شماره صفحات
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $current_page) ? ' active' : '';
        $output .= '<li class="page-item' . $active . '">';
        $output .= '<a class="page-link" href="' . $base_url . '&page=' . $i . '">' . $i . '</a>';
        $output .= '</li>';
    }
    
    // دکمه بعدی
    if ($current_page < $total_pages) {
        $output .= '<li class="page-item">';
        $output .= '<a class="page-link" href="' . $base_url . '&page=' . ($current_page + 1) . '">بعدی</a>';
        $output .= '</li>';
    }
    
    $output .= '</ul>';
    $output .= '</nav>';
    
    return $output;
}

// تولید رنگ تصادفی
function generateRandomColor() {
    return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
}

// تبدیل نام نقش به فارسی
function getRoleTitle($role) {
    $roles = [
        'admin' => 'مدیر کل',
        'manager' => 'مدیر',
        'sales' => 'فروشنده',
        'user' => 'کاربر'
    ];
    
    return $roles[$role] ?? $role;
}

// تبدیل وضعیت به فارسی
function getStatusTitle($status, $type = 'general') {
    $statuses = [
        'general' => [
            'active' => 'فعال',
            'inactive' => 'غیرفعال',
            'pending' => 'در انتظار',
            'suspended' => 'تعلیق شده',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
            'confirmed' => 'تایید شده',
            'processing' => 'در حال پردازش',
            'shipped' => 'ارسال شده',
            'delivered' => 'تحویل داده شده',
            'draft' => 'پیش‌نویس',
            'discontinued' => 'متوقف شده'
        ],
        'lead' => [
            'new' => 'جدید',
            'contacted' => 'تماس گرفته شده',
            'qualified' => 'واجد شرایط',
            'proposal' => 'پیشنهاد ارسال شده',
            'negotiation' => 'در حال مذاکره',
            'won' => 'موفق',
            'lost' => 'از دست رفته'
        ],
        'task' => [
            'pending' => 'در انتظار',
            'in_progress' => 'در حال انجام',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده'
        ],
        'user' => [
            'active' => 'فعال',
            'inactive' => 'غیرفعال',
            'suspended' => 'معلق'
        ],
        'product' => [
            'active' => 'فعال',
            'inactive' => 'غیرفعال',
            'discontinued' => 'متوقف شده'
        ]
    ];
    
    return $statuses[$type][$status] ?? $statuses['general'][$status] ?? $status;
}

// تبدیل اولویت به فارسی
function getPriorityTitle($priority) {
    $priorities = [
        'low' => 'کم',
        'medium' => 'متوسط',
        'high' => 'بالا',
        'urgent' => 'فوری'
    ];
    
    return $priorities[$priority] ?? $priority;
}

// دریافت کلاس CSS بر اساس وضعیت
function getStatusClass($status, $type = 'general') {
    $classes = [
        'general' => [
            'active' => 'success',
            'inactive' => 'secondary',
            'pending' => 'warning',
            'suspended' => 'danger'
        ],
        'lead' => [
            'new' => 'primary',
            'contacted' => 'info',
            'qualified' => 'warning',
            'proposal' => 'secondary',
            'negotiation' => 'dark',
            'won' => 'success',
            'lost' => 'danger'
        ]
    ];
    
    return $classes[$type][$status] ?? 'secondary';
}

// دریافت کلاس CSS بر اساس اولویت
function getPriorityClass($priority) {
    $classes = [
        'low' => 'success',
        'medium' => 'warning',
        'high' => 'danger',
        'urgent' => 'dark'
    ];
    
    return $classes[$priority] ?? 'secondary';
}

// تولید URL با پارامترهای GET موجود
function buildUrl($base_url, $new_params = []) {
    $current_params = $_GET;
    $params = array_merge($current_params, $new_params);
    
    $query_string = http_build_query($params);
    return $base_url . ($query_string ? '?' . $query_string : '');
}

// پاکسازی پارامترهای URL
function cleanUrl($url) {
    return filter_var($url, FILTER_SANITIZE_URL);
}





// تعیین کلاس عملیات
function getActionClass($action) {
    return match(true) {
        str_contains($action, 'create') => 'success',
        str_contains($action, 'update') => 'warning',
        str_contains($action, 'delete') => 'danger',
        str_contains($action, 'login') => 'info',
        default => 'secondary'
    };
}

// تعیین عنوان عملیات
function getActionTitle($action) {
    $actions = [
        'login' => 'ورود',
        'logout' => 'خروج',
        'create_customer' => 'ایجاد مشتری',
        'update_customer' => 'بروزرسانی مشتری',
        'delete_customer' => 'حذف مشتری',
        'create_lead' => 'ایجاد لید',
        'update_lead' => 'بروزرسانی لید',
        'delete_lead' => 'حذف لید',
        'create_task' => 'ایجاد وظیفه',
        'update_task' => 'بروزرسانی وظیفه',
        'delete_task' => 'حذف وظیفه',
        'create_sale' => 'ایجاد فروش',
        'update_sale' => 'بروزرسانی فروش',
        'delete_sale' => 'حذف فروش',
        'create_product' => 'ایجاد محصول',
        'update_product' => 'بروزرسانی محصول',
        'delete_product' => 'حذف محصول',
        'create_user' => 'ایجاد کاربر',
        'update_user' => 'بروزرسانی کاربر',
        'delete_user' => 'حذف کاربر'
    ];
    return $actions[$action] ?? $action;
}

// تعیین عنوان جدول
function getTableTitle($table) {
    $tables = [
        'customers' => 'مشتریان',
        'leads' => 'لیدها',
        'tasks' => 'وظایف',
        'sales' => 'فروش‌ها',
        'products' => 'محصولات',
        'users' => 'کاربران'
    ];
    return $tables[$table] ?? $table;
}

// تعیین کلاس نقش
function getRoleClass($role) {
    return match($role) {
        'admin' => 'danger',
        'manager' => 'warning',
        'sales' => 'success',
        'user' => 'info',
        default => 'secondary'
    };
}

// بررسی IP
function getRealIpAddr() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

// رمزگذاری URL Safe
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// رمزگشایی URL Safe
function base64UrlDecode($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}
?>

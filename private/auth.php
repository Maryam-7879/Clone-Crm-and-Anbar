<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// بررسی وضعیت لاگین
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
}

// دریافت اطلاعات کاربر فعلی
function getCurrentUser() {
    global $pdo;
    
    if (!isLoggedIn()) {
        return false;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// بررسی دسترسی نقش
function hasRole($required_roles) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    if (is_string($required_roles)) {
        $required_roles = [$required_roles];
    }
    
    return in_array($user['role'], $required_roles);
}

// بررسی دسترسی مجوز
function hasPermission($permission) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    // مدیر کل دسترسی دارد (به جز برخی محدودیت‌های خاص)
    if ($user['role'] === 'admin') return true;
    
    // تعریف مجوزها بر اساس نقش
    $permissions = [
        'manager' => [
            'view_dashboard', 'view_customers', 'add_customer', 'edit_customer', 'delete_customer',
            'view_leads', 'add_lead', 'edit_lead', 'delete_lead',
            'view_tasks', 'add_task', 'edit_task', 'delete_task',
            'view_sales', 'add_sale', 'edit_sale', 'delete_sale',
            'view_reports', 'manage_team'
        ],
        'sales' => [
            'view_dashboard', 'view_customers', 'add_customer', 'edit_customer',
            'view_leads', 'add_lead', 'edit_lead',
            'view_tasks', 'add_task', 'edit_task',
            'view_sales', 'add_sale', 'edit_sale', 'view_reports'
        ],
        'user' => [
            'view_dashboard', 'view_customers', 'view_leads', 'view_tasks', 'view_sales', 'view_reports'
        ]
    ];
    
    if (!isset($permissions[$user['role']])) return false;
    
    return in_array($permission, $permissions[$user['role']]);
}

// لاگین کاربر
function loginUser($username, $password) {
    global $pdo;
    
    // بررسی تعداد تلاش‌های ناموفق
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'message' => 'نام کاربری یا رمز عبور اشتباه است'];
    }
    
    // بررسی قفل بودن حساب
    if ($user['locked_until'] && new DateTime() < new DateTime($user['locked_until'])) {
        return ['success' => false, 'message' => 'حساب شما موقتاً قفل شده است'];
    }
    
    // بررسی وضعیت کاربر
    if ($user['status'] !== 'active') {
        return ['success' => false, 'message' => 'حساب کاربری غیرفعال است'];
    }
    
    // بررسی رمز عبور
    if (!password_verify($password, $user['password'])) {
        // افزایش تعداد تلاش‌های ناموفق
        $failed_attempts = $user['failed_login_attempts'] + 1;
        $locked_until = null;
        
        if ($failed_attempts >= MAX_LOGIN_ATTEMPTS) {
            $locked_until = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME);
        }
        
        $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?");
        $stmt->execute([$failed_attempts, $locked_until, $user['id']]);
        
        return ['success' => false, 'message' => 'نام کاربری یا رمز عبور اشتباه است'];
    }
    
    // لاگین موفق
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_logged_in'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    
    // بروزرسانی اطلاعات لاگین
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW(), failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // ثبت لاگ
    logActivity($user['id'], 'login', 'users', $user['id']);
    
    return ['success' => true, 'message' => 'ورود موفقیت‌آمیز'];
}

// خروج کاربر
function logoutUser() {
    if (isLoggedIn()) {
        logActivity($_SESSION['user_id'], 'logout', 'users', $_SESSION['user_id']);
    }
    
    session_unset();
    session_destroy();
    
    // شروع جلسه جدید برای پیام‌ها
    session_start();
    $_SESSION['success_message'] = 'شما با موفقیت خارج شدید';
}

// بررسی انقضای جلسه
function checkSessionTimeout() {
    if (isLoggedIn() && isset($_SESSION['login_time'])) {
        if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
            logoutUser();
            header('Location: login.php?expired=1');
            exit();
        }
        // بروزرسانی زمان فعالیت
        $_SESSION['login_time'] = time();
    }
}

// ثبت فعالیت
function logActivity($user_id, $action, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
    global $pdo;
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $old_json = $old_values ? json_encode($old_values, JSON_UNESCAPED_UNICODE) : null;
    $new_json = $new_values ? json_encode($new_values, JSON_UNESCAPED_UNICODE) : null;
    
    $stmt->execute([
        $user_id, $action, $table_name, $record_id, 
        $old_json, $new_json, $ip_address, $user_agent
    ]);
}

// هش کردن رمز عبور
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// تولید رمز عبور تصادفی
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, $length);
}

// رمزنگاری داده
function encryptData($data) {
    $key = ENCRYPTION_KEY;
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

// رمزگشایی داده
function decryptData($data) {
    $key = ENCRYPTION_KEY;
    $data = base64_decode($data);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
}

// اعتبارسنجی کلمه عبور
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'رمز عبور باید حداقل 8 کاراکتر باشد';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'رمز عبور باید شامل حداقل یک حرف بزرگ باشد';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'رمز عبور باید شامل حداقل یک حرف کوچک باشد';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'رمز عبور باید شامل حداقل یک عدد باشد';
    }
    
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = 'رمز عبور باید شامل حداقل یک کاراکتر خاص باشد';
    }
    
    return $errors;
}

// محافظت در برابر CSRF
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// پاکسازی ورودی
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// اعتبارسنجی ایمیل
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// اعتبارسنجی شماره تلفن ایرانی
function validateIranianPhone($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    return preg_match('/^(?:98|0)?9\d{9}$/', $phone);
}

// بررسی جلسه در هر درخواست
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// بررسی انقضای جلسه
checkSessionTimeout();
?>

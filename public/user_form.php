<?php
$user_id = (int)($_GET['id'] ?? 0);
$is_edit = $user_id > 0;
$page_title = $is_edit ? 'ویرایش کاربر' : 'کاربر جدید';

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
if (!hasPermission($is_edit ? 'edit_user' : 'add_user')) {
    setMessage('شما دسترسی لازم برای این عملیات را ندارید', 'error');
    header('Location: users.php');
    exit();
}

// دریافت کاربر برای ویرایش
$user = null;
if ($is_edit) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            setMessage('کاربر یافت نشد', 'error');
            header('Location: users.php');
            exit();
        }
        
        // کاربران معمولی نمی‌توانند ادمین‌ها را ویرایش کنند
        if ($_SESSION['user_role'] !== 'admin' && $user['role'] === 'admin') {
            setMessage('شما مجاز به ویرایش این کاربر نیستید', 'error');
            header('Location: users.php');
            exit();
        }
        
    } catch (PDOException $e) {
        error_log("خطا در دریافت اطلاعات کاربر: " . $e->getMessage());
        setMessage('خطا در بارگذاری اطلاعات کاربر', 'error');
        header('Location: users.php');
        exit();
    }
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        setMessage('درخواست نامعتبر. لطفاً مجدداً تلاش کنید.', 'error');
    } else {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? 'user';
        $status = $_POST['status'] ?? 'active';
        $department = trim($_POST['department'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $hire_date = $_POST['hire_date'] ?? '';
        $salary = (float)(str_replace(',', '', $_POST['salary'] ?? 0));
        $address = trim($_POST['address'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        $errors = [];
        
        // اعتبارسنجی
        if (empty($first_name)) {
            $errors[] = 'نام الزامی است';
        }
        
        if (empty($last_name)) {
            $errors[] = 'نام خانوادگی الزامی است';
        }
        
        if (empty($email)) {
            $errors[] = 'ایمیل الزامی است';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'فرمت ایمیل معتبر نیست';
        } else {
            // تولید username از ایمیل برای بررسی تکراری بودن
            $username = substr($email, 0, strpos($email, '@'));
            
            // بررسی تکراری نبودن ایمیل
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $errors[] = 'این ایمیل قبلاً ثبت شده است';
            }
            
            // بررسی تکراری نبودن username  
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetch()) {
                $errors[] = 'این نام کاربری قبلاً ثبت شده است';
            }
        }
        
        if ($mobile && !preg_match('/^09\d{9}$/', $mobile)) {
            $errors[] = 'شماره موبایل معتبر نیست (باید با 09 شروع شود و 11 رقم باشد)';
        }
        
        // بررسی رمز عبور
        if (!$is_edit || (!empty($password) || !empty($confirm_password))) {
            if (empty($password)) {
                $errors[] = 'رمز عبور الزامی است';
            } elseif (strlen($password) < 6) {
                $errors[] = 'رمز عبور باید حداقل 6 کاراکتر باشد';
            } elseif ($password !== $confirm_password) {
                $errors[] = 'رمز عبور و تکرار آن یکسان نیستند';
            }
        }
        
        // بررسی دسترسی تغییر نقش
        if ($_SESSION['user_role'] !== 'admin') {
            if ($role === 'admin' || ($is_edit && $user['role'] === 'admin')) {
                $errors[] = 'شما مجاز به تنظیم نقش ادمین نیستید';
            }
        }
        
        if (empty($errors)) {
            try {
                if ($is_edit) {
                    // بروزرسانی کاربر
                    if (!empty($password)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            UPDATE users SET 
                                first_name = ?, last_name = ?, email = ?, mobile = ?, phone = ?,
                                password = ?, role = ?, status = ?, department = ?, position = ?,
                                hire_date = ?, salary = ?, address = ?, notes = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $first_name, $last_name, $email, $mobile, $phone,
                            $hashed_password, $role, $status, $department, $position,
                            $hire_date ?: null, $salary, $address, $notes, $user_id
                        ]);
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE users SET 
                                first_name = ?, last_name = ?, email = ?, mobile = ?, phone = ?,
                                role = ?, status = ?, department = ?, position = ?, hire_date = ?,
                                salary = ?, address = ?, notes = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $first_name, $last_name, $email, $mobile, $phone,
                            $role, $status, $department, $position, $hire_date ?: null,
                            $salary, $address, $notes, $user_id
                        ]);
                    }
                    
                    $action = 'update';
                    $message = 'کاربر با موفقیت بروزرسانی شد';
                    
                } else {
                    // ایجاد کاربر جدید
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // تولید username از ایمیل
                    $username = substr($email, 0, strpos($email, '@'));
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO users (
                            username, first_name, last_name, email, mobile, phone, password, role, status,
                            department, position, hire_date, salary, address, notes
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $username, $first_name, $last_name, $email, $mobile, $phone, $hashed_password,
                        $role, $status, $department, $position, $hire_date ?: null, $salary, $address, $notes
                    ]);
                    
                    $user_id = $pdo->lastInsertId();
                    $action = 'create';
                    $message = 'کاربر با موفقیت ثبت شد';
                }
                
                // ثبت فعالیت
                logActivity($_SESSION['user_id'], $action . '_user', 'users', $user_id, [
                    'name' => $first_name . ' ' . $last_name,
                    'email' => $email,
                    'role' => $role
                ]);
                
                setMessage($message, 'success');
                header('Location: user_view.php?id=' . $user_id);
                exit();
                
            } catch (PDOException $e) {
                error_log("خطا در ذخیره کاربر: " . $e->getMessage());
                $errors[] = 'خطا در ذخیره اطلاعات';
            }
        }
        
        if (!empty($errors)) {
            setMessage(implode('<br>', $errors), 'error');
        }
    }
}

$csrf_token = generateCSRFToken();

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><?php echo $page_title; ?></h4>
        <p class="text-muted mb-0">
            <?php if ($is_edit): ?>
                ویرایش کاربر <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
            <?php else: ?>
                افزودن کاربر جدید به سیستم
            <?php endif; ?>
        </p>
    </div>
    
    <div>
        <a href="users.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-2"></i>
            بازگشت
        </a>
    </div>
</div>

<?php displayMessage(); ?>

<form method="POST" id="userForm">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <div class="row">
        <!-- اطلاعات شخصی -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user me-2 text-primary"></i>
                        اطلاعات شخصی
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label required">نام</label>
                            <input type="text" class="form-control" name="first_name" 
                                   value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" 
                                   required>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label required">نام خانوادگی</label>
                            <input type="text" class="form-control" name="last_name" 
                                   value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" 
                                   required>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label required">ایمیل</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                   required>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label">موبایل</label>
                            <input type="text" class="form-control" name="mobile" 
                                   value="<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>" 
                                   placeholder="09xxxxxxxxx">
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label">تلفن ثابت</label>
                            <input type="text" class="form-control" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                   placeholder="تلفن ثابت (اختیاری)">
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label">وضعیت</label>
                            <select class="form-select" name="status" required>
                                <option value="active" <?php echo ($user['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>فعال</option>
                                <option value="inactive" <?php echo ($user['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>غیرفعال</option>
                                <option value="suspended" <?php echo ($user['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>معلق</option>
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">آدرس</label>
                            <textarea class="form-control" name="address" rows="3" 
                                      placeholder="آدرس کامل..."><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- اطلاعات شغلی -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-briefcase me-2 text-primary"></i>
                        اطلاعات شغلی
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label">بخش/دپارتمان</label>
                            <input type="text" class="form-control" name="department" 
                                   value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>" 
                                   placeholder="مثال: فروش، بازاریابی، IT">
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label">سمت</label>
                            <input type="text" class="form-control" name="position" 
                                   value="<?php echo htmlspecialchars($user['position'] ?? ''); ?>" 
                                   placeholder="مثال: کارشناس فروش، مدیر">
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label">تاریخ استخدام</label>
                            <input type="date" class="form-control" name="hire_date" 
                                   value="<?php echo $user['hire_date'] ?? ''; ?>">
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label">حقوق (تومان)</label>
                            <input type="number" class="form-control" name="salary" 
                                   value="<?php echo $user['salary'] ?? ''; ?>" 
                                   min="0" step="1000"
                                   placeholder="حقوق ماهانه (اختیاری)">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- یادداشت‌ها -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-sticky-note me-2 text-primary"></i>
                        یادداشت‌های داخلی
                    </h5>
                </div>
                <div class="card-body">
                    <textarea class="form-control" name="notes" rows="4" 
                              placeholder="یادداشت‌های داخلی در مورد این کاربر..."><?php echo htmlspecialchars($user['notes'] ?? ''); ?></textarea>
                    <small class="text-muted">این یادداشت‌ها فقط توسط مدیران قابل مشاهده هستند</small>
                </div>
            </div>
        </div>
        
        <!-- تنظیمات دسترسی و امنیت -->
        <div class="col-lg-4">
            <!-- رمز عبور -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-lock me-2 text-primary"></i>
                        تنظیمات امنیتی
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label <?php echo !$is_edit ? 'required' : ''; ?>">
                            <?php echo $is_edit ? 'رمز عبور جدید (اختیاری)' : 'رمز عبور'; ?>
                        </label>
                        <input type="password" class="form-control" name="password" 
                               placeholder="<?php echo $is_edit ? 'برای تغییر رمز، رمز جدید وارد کنید' : 'رمز عبور'; ?>"
                               <?php echo !$is_edit ? 'required' : ''; ?>>
                        <small class="text-muted">حداقل 6 کاراکتر</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label <?php echo !$is_edit ? 'required' : ''; ?>">تکرار رمز عبور</label>
                        <input type="password" class="form-control" name="confirm_password" 
                               placeholder="تکرار رمز عبور"
                               <?php echo !$is_edit ? 'required' : ''; ?>>
                    </div>
                    
                    <?php if ($is_edit): ?>
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            برای حفظ رمز عبور فعلی، این فیلدها را خالی بگذارید
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- سطح دسترسی -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-shield-alt me-2 text-primary"></i>
                        سطح دسترسی
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required">نقش کاربر</label>
                        <select class="form-select" name="role" required id="roleSelect">
                            <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                <option value="admin" <?php echo ($user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>مدیر کل (Admin)</option>
                            <?php endif; ?>
                            <option value="manager" <?php echo ($user['role'] ?? 'user') === 'manager' ? 'selected' : ''; ?>>مدیر (Manager)</option>
                            <option value="sales" <?php echo ($user['role'] ?? '') === 'sales' ? 'selected' : ''; ?>>کارشناس فروش (Sales)</option>
                            <option value="user" <?php echo ($user['role'] ?? '') === 'user' ? 'selected' : ''; ?>>کاربر عادی (User)</option>
                        </select>
                    </div>
                    
                    <div class="permissions-info">
                        <div class="alert alert-light" id="permissionsAlert">
                            <small>
                                <strong>دسترسی‌های این نقش:</strong>
                                <div id="permissionsList"></div>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- آمار کاربر (فقط در حالت ویرایش) -->
            <?php if ($is_edit): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2 text-primary"></i>
                        آمار کاربر
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        // محاسبه آمار
                        $created_customers = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE created_by = ?");
                        $created_customers->execute([$user_id]);
                        $created_customers = $created_customers->fetchColumn();
                        
                        $created_leads = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE created_by = ?");
                        $created_leads->execute([$user_id]);
                        $created_leads = $created_leads->fetchColumn();
                        
                        $assigned_tasks = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ?");
                        $assigned_tasks->execute([$user_id]);
                        $assigned_tasks = $assigned_tasks->fetchColumn();
                        
                        $created_sales = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE created_by = ?");
                        $created_sales->execute([$user_id]);
                        $created_sales = $created_sales->fetchColumn();
                    } catch (PDOException $e) {
                        $created_customers = $created_leads = $assigned_tasks = $created_sales = 0;
                    }
                    ?>
                    
                    <div class="row text-center">
                        <div class="col-6 mb-2">
                            <div class="h6 text-primary"><?php echo number_format($created_customers); ?></div>
                            <small class="text-muted">مشتری ثبت شده</small>
                        </div>
                        <div class="col-6 mb-2">
                            <div class="h6 text-info"><?php echo number_format($created_leads); ?></div>
                            <small class="text-muted">لید ثبت شده</small>
                        </div>
                        <div class="col-6">
                            <div class="h6 text-warning"><?php echo number_format($assigned_tasks); ?></div>
                            <small class="text-muted">وظیفه محول شده</small>
                        </div>
                        <div class="col-6">
                            <div class="h6 text-success"><?php echo number_format($created_sales); ?></div>
                            <small class="text-muted">فروش ثبت شده</small>
                        </div>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <small class="text-muted">
                            عضو از: <?php echo formatPersianDate($user['created_at'], 'Y/m/d'); ?>
                        </small>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- دکمه‌های عملیات -->
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>
                            <?php echo $is_edit ? 'بروزرسانی کاربر' : 'ثبت کاربر'; ?>
                        </button>
                        
                        <?php if ($is_edit): ?>
                            <a href="user_view.php?id=<?php echo $user_id; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-eye me-2"></i>
                                مشاهده کاربر
                            </a>
                        <?php endif; ?>
                        
                        <a href="users.php" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-2"></i>
                            لیست کاربران
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
const rolePermissions = {
    'admin': [
        'مدیریت کامل سیستم',
        'مدیریت کاربران و دسترسی‌ها',
        'مشاهده تمام گزارش‌ها',
        'تنظیمات سیستم',
        'دسترسی به تمام بخش‌ها'
    ],
    'manager': [
        'مدیریت مشتریان و لیدها',
        'مشاهده گزارش‌های مدیریتی',
        'مدیریت محصولات',
        'مدیریت فروش',
        'مدیریت وظایف تیم'
    ],
    'sales': [
        'مدیریت مشتریان',
        'مدیریت لیدها',
        'ثبت فروش',
        'مدیریت وظایف شخصی',
        'مشاهده گزارش‌های فروش'
    ],
    'user': [
        'مشاهده مشتریان',
        'مشاهده لیدها',
        'مشاهده فروش‌ها',
        'مدیریت وظایف شخصی',
        'مشاهده گزارش‌های محدود'
    ]
};

function updatePermissions() {
    const role = document.getElementById('roleSelect').value;
    const permissionsList = document.getElementById('permissionsList');
    const permissions = rolePermissions[role] || [];
    
    permissionsList.innerHTML = permissions.map(permission => 
        `<div class="text-success small"><i class="fas fa-check me-1"></i>${permission}</div>`
    ).join('');
}

// بروزرسانی اولیه
document.addEventListener('DOMContentLoaded', function() {
    updatePermissions();
    
    // تغییر نقش
    document.getElementById('roleSelect').addEventListener('change', updatePermissions);
    
    // اعتبارسنجی رمز عبور
    const passwordField = document.querySelector('input[name="password"]');
    const confirmPasswordField = document.querySelector('input[name="confirm_password"]');
    
    function validatePasswords() {
        const password = passwordField.value;
        const confirmPassword = confirmPasswordField.value;
        
        if (password || confirmPassword) {
            if (password !== confirmPassword) {
                confirmPasswordField.setCustomValidity('رمز عبور و تکرار آن یکسان نیستند');
            } else if (password.length < 6) {
                passwordField.setCustomValidity('رمز عبور باید حداقل 6 کاراکتر باشد');
            } else {
                passwordField.setCustomValidity('');
                confirmPasswordField.setCustomValidity('');
            }
        } else {
            passwordField.setCustomValidity('');
            confirmPasswordField.setCustomValidity('');
        }
    }
    
    passwordField.addEventListener('input', validatePasswords);
    confirmPasswordField.addEventListener('input', validatePasswords);
});

// اعتبارسنجی فرم
document.getElementById('userForm').addEventListener('submit', function(e) {
    const email = document.querySelector('input[name="email"]').value;
    const mobile = document.querySelector('input[name="mobile"]').value;
    const isEdit = <?php echo $is_edit ? 'true' : 'false'; ?>;
    const password = document.querySelector('input[name="password"]').value;
    
    // بررسی ایمیل
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        e.preventDefault();
        alert('فرمت ایمیل معتبر نیست');
        return false;
    }
    
    // بررسی موبایل
    if (mobile && !/^09\d{9}$/.test(mobile)) {
        e.preventDefault();
        alert('شماره موبایل معتبر نیست');
        return false;
    }
    
    // بررسی رمز عبور برای کاربر جدید
    if (!isEdit && (!password || password.length < 6)) {
        e.preventDefault();
        alert('رمز عبور باید حداقل 6 کاراکتر باشد');
        return false;
    }
});
</script>

<style>
.required::after {
    content: ' *';
    color: var(--danger-color);
}

.card-header h5 {
    font-weight: 600;
}

.permissions-info .alert {
    background: var(--bg-light) !important;
    border: 1px solid var(--border-color) !important;
    color: var(--text-medium) !important;
}

.text-success.small {
    line-height: 1.4;
}

.h6 {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

#permissionsList .fas {
    color: var(--success-color);
}
</style>

<?php include __DIR__ . '/../private/footer.php'; ?>

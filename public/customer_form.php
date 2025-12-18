<?php
$customer_id = (int)($_GET['id'] ?? 0);
$page_title = $customer_id ? 'ویرایش مشتری' : 'افزودن مشتری جدید';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'مشتریان', 'url' => 'customers.php'],
    ['title' => $customer_id ? 'ویرایش مشتری' : 'افزودن مشتری']
];

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
$required_permission = $customer_id ? 'edit_customer' : 'add_customer';
if (!hasPermission($required_permission)) {
    setMessage('شما دسترسی لازم برای این عملیات را ندارید', 'error');
    header('Location: customers.php');
    exit();
}

// متغیرهای فرم
$customer = [
    'customer_code' => '',
    'company_name' => '',
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'mobile' => '',
    'address' => '',
    'city' => '',
    'state' => '',
    'postal_code' => '',
    'website' => '',
    'industry' => '',
    'customer_type' => 'individual',
    'status' => 'prospect',
    'source' => '',
    'assigned_to' => '',
    'tags' => '',
    'notes' => ''
];

$errors = [];

// بارگذاری اطلاعات مشتری برای ویرایش
if ($customer_id) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $existing_customer = $stmt->fetch();
    
    if (!$existing_customer) {
        setMessage('مشتری یافت نشد', 'error');
        header('Location: customers.php');
        exit();
    }
    
    $customer = array_merge($customer, $existing_customer);
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $errors[] = 'درخواست نامعتبر. لطفاً مجدداً تلاش کنید.';
    } else {
        // دریافت و اعتبارسنجی داده‌ها
        $customer['company_name'] = sanitizeInput($_POST['company_name']);
        $customer['first_name'] = sanitizeInput($_POST['first_name']);
        $customer['last_name'] = sanitizeInput($_POST['last_name']);
        $customer['email'] = sanitizeInput($_POST['email']);
        $customer['phone'] = sanitizeInput($_POST['phone']);
        $customer['mobile'] = sanitizeInput($_POST['mobile']);
        $customer['address'] = sanitizeInput($_POST['address']);
        $customer['city'] = sanitizeInput($_POST['city']);
        $customer['state'] = sanitizeInput($_POST['state']);
        $customer['postal_code'] = sanitizeInput($_POST['postal_code']);
        $customer['website'] = sanitizeInput($_POST['website']);
        $customer['industry'] = sanitizeInput($_POST['industry']);
        $customer['customer_type'] = $_POST['customer_type'];
        $customer['status'] = $_POST['status'];
        $customer['source'] = sanitizeInput($_POST['source']);
        $customer['assigned_to'] = (int)$_POST['assigned_to'];
        $customer['tags'] = sanitizeInput($_POST['tags']);
        $customer['notes'] = sanitizeInput($_POST['notes']);
        
        // اعتبارسنجی
        if (empty($customer['first_name'])) {
            $errors[] = 'نام الزامی است';
        }
        
        if (empty($customer['last_name'])) {
            $errors[] = 'نام خانوادگی الزامی است';
        }
        
        if ($customer['email'] && !validateEmail($customer['email'])) {
            $errors[] = 'فرمت ایمیل صحیح نیست';
        }
        
        if ($customer['mobile'] && !validateIranianPhone($customer['mobile'])) {
            $errors[] = 'فرمت شماره موبایل صحیح نیست';
        }
        
        // بررسی تکراری بودن ایمیل
        if ($customer['email']) {
            $email_check = $pdo->prepare("SELECT id FROM customers WHERE email = ? AND id != ?");
            $email_check->execute([$customer['email'], $customer_id]);
            if ($email_check->rowCount() > 0) {
                $errors[] = 'ایمیل وارد شده قبلاً ثبت شده است';
            }
        }
        
        // بررسی تکراری بودن موبایل
        if ($customer['mobile']) {
            $mobile_check = $pdo->prepare("SELECT id FROM customers WHERE mobile = ? AND id != ?");
            $mobile_check->execute([$customer['mobile'], $customer_id]);
            if ($mobile_check->rowCount() > 0) {
                $errors[] = 'شماره موبایل وارد شده قبلاً ثبت شده است';
            }
        }
        
        // ذخیره در صورت عدم وجود خطا
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                if ($customer_id) {
                    // ویرایش مشتری
                    $sql = "
                        UPDATE customers SET
                            company_name = ?, first_name = ?, last_name = ?, email = ?, 
                            phone = ?, mobile = ?, address = ?, city = ?, state = ?, 
                            postal_code = ?, website = ?, industry = ?, customer_type = ?, 
                            status = ?, source = ?, assigned_to = ?, tags = ?, notes = ?
                        WHERE id = ?
                    ";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $customer['company_name'], $customer['first_name'], $customer['last_name'], 
                        $customer['email'], $customer['phone'], $customer['mobile'], $customer['address'], 
                        $customer['city'], $customer['state'], $customer['postal_code'], $customer['website'], 
                        $customer['industry'], $customer['customer_type'], $customer['status'], 
                        $customer['source'], $customer['assigned_to'] ?: null, $customer['tags'], 
                        $customer['notes'], $customer_id
                    ]);
                    
                    logActivity($_SESSION['user_id'], 'update_customer', 'customers', $customer_id, $existing_customer, $customer);
                    setMessage('اطلاعات مشتری با موفقیت بروزرسانی شد', 'success');
                    
                } else {
                    // تولید کد مشتری یکتا
                    do {
                        $customer_code = 'C' . date('y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        $code_check = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE customer_code = ?");
                        $code_check->execute([$customer_code]);
                    } while ($code_check->fetchColumn() > 0);
                    
                    // افزودن مشتری جدید
                    $sql = "
                        INSERT INTO customers (
                            customer_code, company_name, first_name, last_name, email, 
                            phone, mobile, address, city, state, postal_code, website, 
                            industry, customer_type, status, source, assigned_to, tags, 
                            notes, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $customer_code, $customer['company_name'], $customer['first_name'], 
                        $customer['last_name'], $customer['email'], $customer['phone'], $customer['mobile'], 
                        $customer['address'], $customer['city'], $customer['state'], $customer['postal_code'], 
                        $customer['website'], $customer['industry'], $customer['customer_type'], 
                        $customer['status'], $customer['source'], $customer['assigned_to'] ?: null, 
                        $customer['tags'], $customer['notes'], $_SESSION['user_id']
                    ]);
                    
                    $new_customer_id = $pdo->lastInsertId();
                    logActivity($_SESSION['user_id'], 'create_customer', 'customers', $new_customer_id, null, $customer);
                    setMessage('مشتری جدید با موفقیت اضافه شد', 'success');
                }
                
                $pdo->commit();
                header('Location: customers.php');
                exit();
                
            } catch (PDOException $e) {
                $pdo->rollback();
                error_log("خطا در ذخیره مشتری: " . $e->getMessage());
                $errors[] = 'خطا در ذخیره اطلاعات';
            }
        }
    }
}

// دریافت کاربران برای انتساب
$users = $pdo->query("SELECT id, first_name, last_name FROM users WHERE status = 'active' ORDER BY first_name")->fetchAll();

// دریافت منابع متداول
$sources = ['وب‌سایت', 'تماس تلفنی', 'ایمیل', 'شبکه‌های اجتماعی', 'معرفی', 'نمایشگاه', 'تبلیغات', 'سایر'];

// دریافت صنایع متداول
$industries = ['فناوری اطلاعات', 'خدمات مالی', 'بهداشت و درمان', 'آموزش', 'ساخت و ساز', 'تولیدی', 'خرده‌فروشی', 'غذا و نوشیدنی', 'حمل و نقل', 'سایر'];

$csrf_token = generateCSRFToken();

include __DIR__ . '/../private/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-<?php echo $customer_id ? 'edit' : 'plus'; ?> me-2"></i>
                    <?php echo $page_title; ?>
                </h5>
            </div>
            
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="customerForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <!-- اطلاعات اصلی -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-user me-2 text-primary"></i>
                                اطلاعات اصلی
                            </h6>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="customer_type" class="form-label">نوع مشتری <span class="text-danger">*</span></label>
                            <select class="form-select" id="customer_type" name="customer_type" onchange="toggleCompanyFields()">
                                <option value="individual" <?php echo $customer['customer_type'] === 'individual' ? 'selected' : ''; ?>>حقیقی</option>
                                <option value="company" <?php echo $customer['customer_type'] === 'company' ? 'selected' : ''; ?>>حقوقی</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3" id="company_name_field">
                            <label for="company_name" class="form-label">نام شرکت</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" 
                                   value="<?php echo htmlspecialchars($customer['company_name']); ?>">
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="first_name" class="form-label">نام <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($customer['first_name']); ?>" required>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="last_name" class="form-label">نام خانوادگی <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($customer['last_name']); ?>" required>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="email" class="form-label">ایمیل</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($customer['email']); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3" id="industry_field">
                            <label for="industry" class="form-label">صنعت</label>
                            <select class="form-select" id="industry" name="industry">
                                <option value="">انتخاب صنعت</option>
                                <?php foreach ($industries as $industry): ?>
                                    <option value="<?php echo $industry; ?>" <?php echo $customer['industry'] === $industry ? 'selected' : ''; ?>>
                                        <?php echo $industry; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- اطلاعات تماس -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-phone me-2 text-primary"></i>
                                اطلاعات تماس
                            </h6>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="phone" class="form-label">تلفن ثابت</label>
                            <input type="text" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($customer['phone']); ?>"
                                   onchange="formatPhoneNumber(this)">
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="mobile" class="form-label">موبایل</label>
                            <input type="text" class="form-control" id="mobile" name="mobile" 
                                   value="<?php echo htmlspecialchars($customer['mobile']); ?>"
                                   onchange="formatPhoneNumber(this)">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="address" class="form-label">آدرس</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($customer['address']); ?></textarea>
                        </div>
                        
                        <div class="col-lg-4 col-md-6 col-12 mb-3">
                            <label for="city" class="form-label">شهر</label>
                            <input type="text" class="form-control" id="city" name="city" 
                                   value="<?php echo htmlspecialchars($customer['city']); ?>">
                        </div>
                        
                        <div class="col-lg-4 col-md-6 col-12 mb-3">
                            <label for="state" class="form-label">استان</label>
                            <input type="text" class="form-control" id="state" name="state" 
                                   value="<?php echo htmlspecialchars($customer['state']); ?>">
                        </div>
                        
                        <div class="col-lg-4 col-md-6 col-12 mb-3">
                            <label for="postal_code" class="form-label">کد پستی</label>
                            <input type="text" class="form-control" id="postal_code" name="postal_code" 
                                   value="<?php echo htmlspecialchars($customer['postal_code']); ?>">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="website" class="form-label">وب‌سایت</label>
                            <input type="url" class="form-control" id="website" name="website" 
                                   value="<?php echo htmlspecialchars($customer['website']); ?>"
                                   placeholder="https://example.com">
                        </div>
                    </div>
                    
                    <!-- اطلاعات مدیریتی -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-cogs me-2 text-primary"></i>
                                اطلاعات مدیریتی
                            </h6>
                        </div>
                        
                        <div class="col-lg-4 col-md-6 col-12 mb-3">
                            <label for="status" class="form-label">وضعیت</label>
                            <select class="form-select" id="status" name="status">
                                <option value="prospect" <?php echo $customer['status'] === 'prospect' ? 'selected' : ''; ?>>مشتری بالقوه</option>
                                <option value="active" <?php echo $customer['status'] === 'active' ? 'selected' : ''; ?>>فعال</option>
                                <option value="inactive" <?php echo $customer['status'] === 'inactive' ? 'selected' : ''; ?>>غیرفعال</option>
                            </select>
                        </div>
                        
                        <div class="col-lg-4 col-md-6 col-12 mb-3">
                            <label for="source" class="form-label">منبع</label>
                            <select class="form-select" id="source" name="source">
                                <option value="">انتخاب منبع</option>
                                <?php foreach ($sources as $source): ?>
                                    <option value="<?php echo $source; ?>" <?php echo $customer['source'] === $source ? 'selected' : ''; ?>>
                                        <?php echo $source; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-lg-4 col-md-6 col-12 mb-3">
                            <label for="assigned_to" class="form-label">مسئول</label>
                            <select class="form-select" id="assigned_to" name="assigned_to">
                                <option value="">بدون مسئول</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $customer['assigned_to'] == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="tags" class="form-label">برچسب‌ها</label>
                            <input type="text" class="form-control" id="tags" name="tags" 
                                   value="<?php echo htmlspecialchars($customer['tags']); ?>"
                                   placeholder="برچسب‌ها را با کاما جدا کنید">
                            <small class="form-text text-muted">مثال: VIP، مشتری وفادار، بازگشتی</small>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="notes" class="form-label">یادداشت‌ها</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo htmlspecialchars($customer['notes']); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- دکمه‌های عملیات -->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <a href="customers.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-right me-2"></i>
                                    بازگشت
                                </a>
                                
                                <div>
                                    <button type="button" class="btn btn-outline-primary me-2" onclick="clearForm()">
                                        <i class="fas fa-undo me-2"></i>
                                        پاک کردن فرم
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        <?php echo $customer_id ? 'بروزرسانی' : 'ذخیره'; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleCompanyFields() {
    const customerType = document.getElementById('customer_type').value;
    const companyField = document.getElementById('company_name_field');
    const industryField = document.getElementById('industry_field');
    
    if (customerType === 'company') {
        companyField.style.display = 'block';
        industryField.style.display = 'block';
    } else {
        companyField.style.display = 'none';
        industryField.style.display = 'none';
    }
}

function clearForm() {
    if (confirm('آیا از پاک کردن فرم مطمئن هستید؟')) {
        document.getElementById('customerForm').reset();
        toggleCompanyFields();
    }
}

// Initialize form
document.addEventListener('DOMContentLoaded', function() {
    toggleCompanyFields();
    enableAutoSave('customerForm');
});
</script>

<?php include __DIR__ . '/../private/footer.php'; ?>

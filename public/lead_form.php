<?php
$lead_id = (int)($_GET['id'] ?? 0);
$page_title = $lead_id ? 'ویرایش لید' : 'افزودن لید جدید';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'لیدها', 'url' => 'leads.php'],
    ['title' => $lead_id ? 'ویرایش لید' : 'افزودن لید']
];

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
$required_permission = $lead_id ? 'edit_lead' : 'add_lead';
if (!hasPermission($required_permission)) {
    setMessage('شما دسترسی لازم برای این عملیات را ندارید', 'error');
    header('Location: leads.php');
    exit();
}

// متغیرهای فرم
$lead = [
    'title' => '',
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'company' => '',
    'position' => '',
    'source' => '',
    'status' => 'new',
    'priority' => 'medium',
    'value' => 0,
    'probability' => 0,
    'expected_close_date' => '',
    'assigned_to' => '',
    'description' => '',
    'notes' => '',
    'tags' => ''
];

$errors = [];

// بارگذاری اطلاعات لید برای ویرایش
if ($lead_id) {
    $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$lead_id]);
    $existing_lead = $stmt->fetch();
    
    if (!$existing_lead) {
        setMessage('لید یافت نشد', 'error');
        header('Location: leads.php');
        exit();
    }
    
    $lead = array_merge($lead, $existing_lead);
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $errors[] = 'درخواست نامعتبر. لطفاً مجدداً تلاش کنید.';
    } else {
        // دریافت و اعتبارسنجی داده‌ها
        $lead['title'] = sanitizeInput($_POST['title']);
        $lead['first_name'] = sanitizeInput($_POST['first_name']);
        $lead['last_name'] = sanitizeInput($_POST['last_name']);
        $lead['email'] = sanitizeInput($_POST['email']);
        $lead['phone'] = sanitizeInput($_POST['phone']);
        $lead['company'] = sanitizeInput($_POST['company']);
        $lead['position'] = sanitizeInput($_POST['position']);
        $lead['source'] = sanitizeInput($_POST['source']);
        $lead['status'] = $_POST['status'];
        $lead['priority'] = $_POST['priority'];
        $lead['value'] = (float)str_replace(',', '', $_POST['value']);
        $lead['probability'] = (int)$_POST['probability'];
        $lead['expected_close_date'] = $_POST['expected_close_date'] ?: null;
        $lead['assigned_to'] = (int)$_POST['assigned_to'];
        $lead['description'] = sanitizeInput($_POST['description']);
        $lead['notes'] = sanitizeInput($_POST['notes']);
        $lead['tags'] = sanitizeInput($_POST['tags']);
        
        // اعتبارسنجی
        if (empty($lead['title'])) {
            $errors[] = 'عنوان لید الزامی است';
        }
        
        if (empty($lead['first_name'])) {
            $errors[] = 'نام الزامی است';
        }
        
        if (empty($lead['last_name'])) {
            $errors[] = 'نام خانوادگی الزامی است';
        }
        
        if ($lead['email'] && !validateEmail($lead['email'])) {
            $errors[] = 'فرمت ایمیل صحیح نیست';
        }
        
        if ($lead['phone'] && !validateIranianPhone($lead['phone'])) {
            $errors[] = 'فرمت شماره تلفن صحیح نیست';
        }
        
        if ($lead['probability'] < 0 || $lead['probability'] > 100) {
            $errors[] = 'احتمال باید بین 0 تا 100 باشد';
        }
        
        // ذخیره در صورت عدم وجود خطا
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                if ($lead_id) {
                    // ویرایش لید
                    $sql = "
                        UPDATE leads SET
                            title = ?, first_name = ?, last_name = ?, email = ?, phone = ?,
                            company = ?, position = ?, source = ?, status = ?, priority = ?,
                            value = ?, probability = ?, expected_close_date = ?, assigned_to = ?,
                            description = ?, notes = ?, tags = ?
                        WHERE id = ?
                    ";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $lead['title'], $lead['first_name'], $lead['last_name'], $lead['email'],
                        $lead['phone'], $lead['company'], $lead['position'], $lead['source'],
                        $lead['status'], $lead['priority'], $lead['value'], $lead['probability'],
                        $lead['expected_close_date'], $lead['assigned_to'] ?: null, $lead['description'],
                        $lead['notes'], $lead['tags'], $lead_id
                    ]);
                    
                    logActivity($_SESSION['user_id'], 'update_lead', 'leads', $lead_id, $existing_lead, $lead);
                    setMessage('اطلاعات لید با موفقیت بروزرسانی شد', 'success');
                    
                } else {
                    // افزودن لید جدید
                    $sql = "
                        INSERT INTO leads (
                            title, first_name, last_name, email, phone, company, position,
                            source, status, priority, value, probability, expected_close_date,
                            assigned_to, description, notes, tags, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $lead['title'], $lead['first_name'], $lead['last_name'], $lead['email'],
                        $lead['phone'], $lead['company'], $lead['position'], $lead['source'],
                        $lead['status'], $lead['priority'], $lead['value'], $lead['probability'],
                        $lead['expected_close_date'], $lead['assigned_to'] ?: null, $lead['description'],
                        $lead['notes'], $lead['tags'], $_SESSION['user_id']
                    ]);
                    
                    $new_lead_id = $pdo->lastInsertId();
                    logActivity($_SESSION['user_id'], 'create_lead', 'leads', $new_lead_id, null, $lead);
                    setMessage('لید جدید با موفقیت اضافه شد', 'success');
                }
                
                $pdo->commit();
                header('Location: leads.php');
                exit();
                
            } catch (PDOException $e) {
                $pdo->rollback();
                error_log("خطا در ذخیره لید: " . $e->getMessage());
                $errors[] = 'خطا در ذخیره اطلاعات';
            }
        }
    }
}

// دریافت کاربران برای انتساب
$users = $pdo->query("SELECT id, first_name, last_name FROM users WHERE status = 'active' ORDER BY first_name")->fetchAll();

// دریافت منابع متداول
$sources = ['وب‌سایت', 'تماس تلفنی', 'ایمیل', 'شبکه‌های اجتماعی', 'معرفی', 'نمایشگاه', 'تبلیغات', 'LinkedIn', 'سایر'];

$csrf_token = generateCSRFToken();

include __DIR__ . '/../private/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-<?php echo $lead_id ? 'edit' : 'plus'; ?> me-2"></i>
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
                
                <form method="POST" id="leadForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <!-- اطلاعات اصلی -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-info-circle me-2 text-primary"></i>
                                اطلاعات اصلی لید
                            </h6>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="title" class="form-label">عنوان لید <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?php echo htmlspecialchars($lead['title']); ?>" required
                                   placeholder="مثال: فروش نرم‌افزار CRM به شرکت ABC">
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="first_name" class="form-label">نام <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($lead['first_name']); ?>" required>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="last_name" class="form-label">نام خانوادگی <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($lead['last_name']); ?>" required>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="email" class="form-label">ایمیل</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($lead['email']); ?>">
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="phone" class="form-label">تلفن</label>
                            <input type="text" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($lead['phone']); ?>"
                                   onchange="formatPhoneNumber(this)">
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="company" class="form-label">شرکت</label>
                            <input type="text" class="form-control" id="company" name="company" 
                                   value="<?php echo htmlspecialchars($lead['company']); ?>">
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="position" class="form-label">سمت</label>
                            <input type="text" class="form-control" id="position" name="position" 
                                   value="<?php echo htmlspecialchars($lead['position']); ?>"
                                   placeholder="مثال: مدیر فروش، مدیرعامل">
                        </div>
                    </div>
                    
                    <!-- وضعیت و اولویت -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-cogs me-2 text-primary"></i>
                                وضعیت و اولویت
                            </h6>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="status" class="form-label">وضعیت</label>
                            <select class="form-select" id="status" name="status">
                                <option value="new" <?php echo $lead['status'] === 'new' ? 'selected' : ''; ?>>جدید</option>
                                <option value="contacted" <?php echo $lead['status'] === 'contacted' ? 'selected' : ''; ?>>تماس گرفته شده</option>
                                <option value="qualified" <?php echo $lead['status'] === 'qualified' ? 'selected' : ''; ?>>واجد شرایط</option>
                                <option value="proposal" <?php echo $lead['status'] === 'proposal' ? 'selected' : ''; ?>>پیشنهاد ارسال شده</option>
                                <option value="negotiation" <?php echo $lead['status'] === 'negotiation' ? 'selected' : ''; ?>>در حال مذاکره</option>
                                <option value="won" <?php echo $lead['status'] === 'won' ? 'selected' : ''; ?>>موفق</option>
                                <option value="lost" <?php echo $lead['status'] === 'lost' ? 'selected' : ''; ?>>از دست رفته</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="priority" class="form-label">اولویت</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="low" <?php echo $lead['priority'] === 'low' ? 'selected' : ''; ?>>کم</option>
                                <option value="medium" <?php echo $lead['priority'] === 'medium' ? 'selected' : ''; ?>>متوسط</option>
                                <option value="high" <?php echo $lead['priority'] === 'high' ? 'selected' : ''; ?>>بالا</option>
                                <option value="urgent" <?php echo $lead['priority'] === 'urgent' ? 'selected' : ''; ?>>فوری</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="source" class="form-label">منبع</label>
                            <select class="form-select" id="source" name="source">
                                <option value="">انتخاب منبع</option>
                                <?php foreach ($sources as $source): ?>
                                    <option value="<?php echo $source; ?>" <?php echo $lead['source'] === $source ? 'selected' : ''; ?>>
                                        <?php echo $source; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="assigned_to" class="form-label">مسئول</label>
                            <select class="form-select" id="assigned_to" name="assigned_to">
                                <option value="">بدون مسئول</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $lead['assigned_to'] == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- اطلاعات مالی -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-money-bill-wave me-2 text-primary"></i>
                                اطلاعات مالی
                            </h6>
                        </div>
                        
                        <div class="col-lg-4 col-md-6 col-12 mb-3">
                            <label for="value" class="form-label">ارزش لید (تومان)</label>
                            <input type="text" class="form-control" id="value" name="value" 
                                   value="<?php echo number_format($lead['value']); ?>"
                                   onchange="formatCurrency(this)"
                                   placeholder="0">
                        </div>
                        
                        <div class="col-lg-4 col-md-6 col-12 mb-3">
                            <label for="probability" class="form-label">احتمال موفقیت (%)</label>
                            <input type="number" class="form-control" id="probability" name="probability" 
                                   value="<?php echo $lead['probability']; ?>"
                                   min="0" max="100" placeholder="50">
                        </div>
                        
                        <div class="col-lg-4 col-md-6 col-12 mb-3">
                            <label for="expected_close_date" class="form-label">تاریخ بسته شدن مورد انتظار</label>
                            <input type="date" class="form-control" id="expected_close_date" name="expected_close_date" 
                                   value="<?php echo $lead['expected_close_date']; ?>">
                        </div>
                    </div>
                    
                    <!-- توضیحات -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-file-alt me-2 text-primary"></i>
                                توضیحات و یادداشت‌ها
                            </h6>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="description" class="form-label">توضیحات لید</label>
                            <textarea class="form-control" id="description" name="description" rows="4"
                                      placeholder="توضیحات مفصل در مورد این فرصت فروش..."><?php echo htmlspecialchars($lead['description']); ?></textarea>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="notes" class="form-label">یادداشت‌ها</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"
                                      placeholder="یادداشت‌های داخلی..."><?php echo htmlspecialchars($lead['notes']); ?></textarea>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="tags" class="form-label">برچسب‌ها</label>
                            <input type="text" class="form-control" id="tags" name="tags" 
                                   value="<?php echo htmlspecialchars($lead['tags']); ?>"
                                   placeholder="برچسب‌ها را با کاما جدا کنید">
                            <small class="form-text text-muted">مثال: داغ، آماده خرید، نیاز به پیگیری</small>
                        </div>
                    </div>
                    
                    <!-- دکمه‌های عملیات -->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <a href="leads.php" class="btn btn-secondary">
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
                                        <?php echo $lead_id ? 'بروزرسانی' : 'ذخیره'; ?>
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
function clearForm() {
    if (confirm('آیا از پاک کردن فرم مطمئن هستید؟')) {
        document.getElementById('leadForm').reset();
    }
}

// Initialize form
document.addEventListener('DOMContentLoaded', function() {
    enableAutoSave('leadForm');
    
    // Update probability based on status
    const statusSelect = document.getElementById('status');
    const probabilityInput = document.getElementById('probability');
    
    statusSelect.addEventListener('change', function() {
        const statusProbabilities = {
            'new': 10,
            'contacted': 25,
            'qualified': 50,
            'proposal': 75,
            'negotiation': 85,
            'won': 100,
            'lost': 0
        };
        
        const suggestedProbability = statusProbabilities[this.value];
        if (suggestedProbability !== undefined && probabilityInput.value == 0) {
            probabilityInput.value = suggestedProbability;
        }
    });
});
</script>

<?php include __DIR__ . '/../private/footer.php'; ?>

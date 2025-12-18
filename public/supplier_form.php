<?php
$supplier_id = (int)($_GET['id'] ?? 0);
$is_edit = $supplier_id > 0;
$page_title = $is_edit ? 'ویرایش تامین‌کننده' : 'تامین‌کننده جدید';

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

if (!hasPermission('view_dashboard')) {
    setMessage('شما دسترسی لازم برای این عملیات را ندارید', 'error');
    header('Location: suppliers.php');
    exit();
}

$supplier = null;
if ($is_edit) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$supplier_id]);
        $supplier = $stmt->fetch();
        
        if (!$supplier) {
            setMessage('تامین‌کننده یافت نشد', 'error');
            header('Location: suppliers.php');
            exit();
        }
        
    } catch (PDOException $e) {
        error_log("خطا در دریافت اطلاعات تامین‌کننده: " . $e->getMessage());
        setMessage('خطا در بارگذاری اطلاعات تامین‌کننده', 'error');
        header('Location: suppliers.php');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        setMessage('درخواست نامعتبر. لطفاً مجدداً تلاش کنید.', 'error');
    } else {
        $name = trim($_POST['name'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $tax_number = trim($_POST['tax_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'نام تامین‌کننده الزامی است';
        }
        
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'ایمیل معتبر نیست';
        }
        
        if (empty($errors)) {
            try {
                if ($is_edit) {
                    $stmt = $pdo->prepare("
                        UPDATE suppliers SET 
                            name = ?, contact_person = ?, email = ?, phone = ?, 
                            address = ?, tax_number = ?, notes = ?, is_active = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $contact_person, $email, $phone, $address, $tax_number, $notes, $is_active, $supplier_id]);
                    
                    $action = 'update';
                    $message = 'تامین‌کننده با موفقیت بروزرسانی شد';
                    
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO suppliers (name, contact_person, email, phone, address, tax_number, notes, is_active, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$name, $contact_person, $email, $phone, $address, $tax_number, $notes, $is_active]);
                    
                    $supplier_id = $pdo->lastInsertId();
                    $action = 'create';
                    $message = 'تامین‌کننده با موفقیت ثبت شد';
                }
                
                logActivity($_SESSION['user_id'], $action . '_supplier', 'suppliers', $supplier_id);
                
                setMessage($message, 'success');
                header('Location: supplier_view.php?id=' . $supplier_id);
                exit();
                
            } catch (PDOException $e) {
                error_log("خطا در ذخیره تامین‌کننده: " . $e->getMessage());
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
                ویرایش تامین‌کننده <?php echo htmlspecialchars($supplier['name']); ?>
            <?php else: ?>
                افزودن تامین‌کننده جدید
            <?php endif; ?>
        </p>
    </div>
    
    <div>
        <a href="suppliers.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-2"></i>
            بازگشت
        </a>
    </div>
</div>

<?php displayMessage(); ?>

<form method="POST" id="supplierForm">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2 text-primary"></i>
                        اطلاعات تامین‌کننده
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label required">نام تامین‌کننده</label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo htmlspecialchars($supplier['name'] ?? ''); ?>" 
                                   placeholder="نام کامل تامین‌کننده..."
                                   required>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label">شخص تماس</label>
                            <input type="text" class="form-control" name="contact_person" 
                                   value="<?php echo htmlspecialchars($supplier['contact_person'] ?? ''); ?>" 
                                   placeholder="نام مسئول تماس...">
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label">کد اقتصادی</label>
                            <input type="text" class="form-control" name="tax_number" 
                                   value="<?php echo htmlspecialchars($supplier['tax_number'] ?? ''); ?>" 
                                   placeholder="کد اقتصادی...">
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label">ایمیل</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($supplier['email'] ?? ''); ?>" 
                                   placeholder="example@email.com">
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label">تلفن</label>
                            <input type="text" class="form-control" name="phone" 
                                   value="<?php echo htmlspecialchars($supplier['phone'] ?? ''); ?>" 
                                   placeholder="021-12345678"
                                   onchange="formatPhoneNumber(this)">
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">آدرس</label>
                            <textarea class="form-control" name="address" rows="3" 
                                      placeholder="آدرس کامل تامین‌کننده..."><?php echo htmlspecialchars($supplier['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">یادداشت‌ها</label>
                            <textarea class="form-control" name="notes" rows="4" 
                                      placeholder="یادداشت‌های داخلی..."><?php echo htmlspecialchars($supplier['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-cog me-2 text-primary"></i>
                        تنظیمات
                    </h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                               <?php echo (!isset($supplier) || $supplier['is_active']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">
                            تامین‌کننده فعال است
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>
                            <?php echo $is_edit ? 'بروزرسانی' : 'ثبت'; ?>
                        </button>
                        
                        <?php if ($is_edit): ?>
                            <a href="supplier_view.php?id=<?php echo $supplier_id; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-eye me-2"></i>
                                مشاهده
                            </a>
                        <?php endif; ?>
                        
                        <a href="suppliers.php" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-2"></i>
                            لیست
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<style>
.required::after {
    content: ' *';
    color: var(--danger-color);
}
</style>

<?php include __DIR__ . '/../private/footer.php'; ?>

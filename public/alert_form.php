<?php
$alert_id = (int)($_GET['id'] ?? 0);
$is_edit = $alert_id > 0;
$page_title = $is_edit ? 'ویرایش هشدار' : 'هشدار جدید';

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

if (!hasPermission('view_dashboard')) {
    setMessage('شما دسترسی لازم برای این عملیات را ندارید', 'error');
    header('Location: alerts.php');
    exit();
}

$alert = null;
if ($is_edit) {
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, p.name as product_name, p.sku
            FROM alerts a
            INNER JOIN products p ON a.product_id = p.id
            WHERE a.id = ? AND a.deleted_at IS NULL
        ");
        $stmt->execute([$alert_id]);
        $alert = $stmt->fetch();
        
        if (!$alert) {
            setMessage('هشدار یافت نشد', 'error');
            header('Location: alerts.php');
            exit();
        }
        
    } catch (PDOException $e) {
        error_log("خطا در دریافت اطلاعات هشدار: " . $e->getMessage());
        setMessage('خطا در بارگذاری اطلاعات هشدار', 'error');
        header('Location: alerts.php');
        exit();
    }
}

// دریافت لیست محصولات
try {
    $products = $pdo->query("
        SELECT id, name, sku 
        FROM products 
        WHERE deleted_at IS NULL AND status = 'active' 
        ORDER BY name
    ")->fetchAll();
} catch (PDOException $e) {
    $products = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        setMessage('درخواست نامعتبر. لطفاً مجدداً تلاش کنید.', 'error');
    } else {
        $product_id = (int)$_POST['product_id'];
        $type = $_POST['type'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $is_read = isset($_POST['is_read']) ? 1 : 0;
        
        $errors = [];
        
        if (!$product_id) {
            $errors[] = 'محصول الزامی است';
        }
        
        if (!in_array($type, ['low_stock', 'out_of_stock', 'expiry', 'custom'])) {
            $errors[] = 'نوع هشدار نامعتبر است';
        }
        
        if (empty($title)) {
            $errors[] = 'عنوان الزامی است';
        }
        
        if (empty($message)) {
            $errors[] = 'پیام الزامی است';
        }
        
        if (empty($errors)) {
            try {
                if ($is_edit) {
                    $read_at = $is_read && !$alert['is_read'] ? 'NOW()' : ($alert['read_at'] ? "'{$alert['read_at']}'" : 'NULL');
                    
                    $stmt = $pdo->prepare("
                        UPDATE alerts SET 
                            product_id = ?, type = ?, title = ?, message = ?, 
                            is_read = ?, read_at = {$read_at}, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$product_id, $type, $title, $message, $is_read, $alert_id]);
                    
                    $action = 'update';
                    $message_text = 'هشدار با موفقیت به‌روزرسانی شد';
                    
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO alerts (product_id, type, title, message, is_read, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$product_id, $type, $title, $message, $is_read]);
                    
                    $alert_id = $pdo->lastInsertId();
                    $action = 'create';
                    $message_text = 'هشدار با موفقیت ثبت شد';
                }
                
                logActivity($_SESSION['user_id'], $action . '_alert', 'alerts', $alert_id);
                
                setMessage($message_text, 'success');
                header('Location: alert_view.php?id=' . $alert_id);
                exit();
                
            } catch (PDOException $e) {
                error_log("خطا در ذخیره هشدار: " . $e->getMessage());
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
                ویرایش هشدار
            <?php else: ?>
                افزودن هشدار جدید
            <?php endif; ?>
        </p>
    </div>
    
    <div>
        <a href="alerts.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-2"></i>
            بازگشت
        </a>
    </div>
</div>

<?php displayMessage(); ?>

<form method="POST" id="alertForm">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2 text-primary"></i>
                        اطلاعات هشدار
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">محصول</label>
                            <select class="form-select" name="product_id" required>
                                <option value="">انتخاب محصول...</option>
                                <?php foreach ($products as $prod): ?>
                                    <option value="<?php echo $prod['id']; ?>" 
                                            <?php echo (isset($alert) && $alert['product_id'] == $prod['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prod['name']); ?> (<?php echo htmlspecialchars($prod['sku']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">نوع هشدار</label>
                            <select class="form-select" name="type" required>
                                <option value="">انتخاب کنید...</option>
                                <option value="low_stock" <?php echo (isset($alert) && $alert['type'] === 'low_stock') ? 'selected' : ''; ?>>موجودی کم</option>
                                <option value="out_of_stock" <?php echo (isset($alert) && $alert['type'] === 'out_of_stock') ? 'selected' : ''; ?>>تمام شده</option>
                                <option value="expiry" <?php echo (isset($alert) && $alert['type'] === 'expiry') ? 'selected' : ''; ?>>انقضا</option>
                                <option value="custom" <?php echo (isset($alert) && $alert['type'] === 'custom') ? 'selected' : ''; ?>>سفارشی</option>
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label required">عنوان</label>
                            <input type="text" class="form-control" name="title" 
                                   value="<?php echo htmlspecialchars($alert['title'] ?? ''); ?>" 
                                   placeholder="عنوان هشدار..."
                                   required>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label required">پیام</label>
                            <textarea class="form-control" name="message" rows="5" 
                                      placeholder="متن کامل هشدار..." required><?php echo htmlspecialchars($alert['message'] ?? ''); ?></textarea>
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
                        <input class="form-check-input" type="checkbox" name="is_read" id="is_read" value="1"
                               <?php echo (isset($alert) && $alert['is_read']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_read">
                            هشدار خوانده شده است
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
                            <a href="alert_view.php?id=<?php echo $alert_id; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-eye me-2"></i>
                                مشاهده
                            </a>
                        <?php endif; ?>
                        
                        <a href="alerts.php" class="btn btn-outline-secondary">
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

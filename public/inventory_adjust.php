<?php
$product_id = (int)($_GET['product_id'] ?? 0);

if (!$product_id) {
    setMessage('شناسه محصول نامعتبر است', 'error');
    header('Location: inventory.php');
    exit();
}

$page_title = 'تعدیل موجودی';

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

if (!hasPermission('view_dashboard')) {
    setMessage('شما دسترسی لازم برای این عملیات را ندارید', 'error');
    header('Location: inventory.php');
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               i.id as inventory_id, i.current_stock, i.reserved_stock, i.available_stock, i.average_cost
        FROM products p
        LEFT JOIN inventories i ON p.id = i.product_id AND i.deleted_at IS NULL
        WHERE p.id = ?
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        setMessage('محصول یافت نشد', 'error');
        header('Location: inventory.php');
        exit();
    }
    
    // اگر موجودی وجود نداشت، ایجاد کن
    if (!$product['inventory_id']) {
        $stmt = $pdo->prepare("
            INSERT INTO inventories (product_id, current_stock, reserved_stock, available_stock, average_cost, last_updated_at)
            VALUES (?, 0, 0, 0, ?, NOW())
        ");
        $stmt->execute([$product_id, $product['cost_price']]);
        $product['inventory_id'] = $pdo->lastInsertId();
        $product['current_stock'] = 0;
        $product['reserved_stock'] = 0;
        $product['available_stock'] = 0;
        $product['average_cost'] = $product['cost_price'];
    }
    
} catch (PDOException $e) {
    error_log("خطا در دریافت اطلاعات محصول: " . $e->getMessage());
    setMessage('خطا در بارگذاری اطلاعات', 'error');
    header('Location: inventory.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        setMessage('درخواست نامعتبر. لطفاً مجدداً تلاش کنید.', 'error');
    } else {
        $type = $_POST['type'] ?? '';
        $quantity = (int)$_POST['quantity'];
        $unit_price = (float)$_POST['unit_price'];
        $notes = trim($_POST['notes'] ?? '');
        
        $errors = [];
        
        if (!in_array($type, ['in', 'out', 'adjustment'])) {
            $errors[] = 'نوع عملیات نامعتبر است';
        }
        
        if ($quantity <= 0) {
            $errors[] = 'تعداد باید بیشتر از صفر باشد';
        }
        
        if ($unit_price < 0) {
            $errors[] = 'قیمت واحد نمی‌تواند منفی باشد';
        }
        
        if ($type === 'out' && $quantity > $product['current_stock']) {
            $errors[] = 'موجودی کافی نیست. موجودی فعلی: ' . number_format($product['current_stock']);
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                $old_stock = $product['current_stock'];
                $new_stock = $old_stock;
                
                switch ($type) {
                    case 'in':
                        $new_stock += $quantity;
                        break;
                    case 'out':
                        $new_stock -= $quantity;
                        break;
                    case 'adjustment':
                        $new_stock = $quantity;
                        break;
                }
                
                $available_stock = $new_stock - $product['reserved_stock'];
                
                // به‌روزرسانی موجودی
                $stmt = $pdo->prepare("
                    UPDATE inventories SET 
                        current_stock = ?,
                        available_stock = ?,
                        average_cost = ?,
                        last_updated_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$new_stock, $available_stock, $unit_price, $product['inventory_id']]);
                
                // به‌روزرسانی stock_quantity در products
                $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                $stmt->execute([$new_stock, $product_id]);
                
                // ثبت تراکنش
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (product_id, user_id, type, quantity, unit_price, total_price, notes, reference_number, transaction_date, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $reference_number = 'ADJ-' . time();
                $total_price = $quantity * $unit_price;
                $stmt->execute([
                    $product_id,
                    $_SESSION['user_id'],
                    $type,
                    $quantity,
                    $unit_price,
                    $total_price,
                    $notes,
                    $reference_number
                ]);
                
                $pdo->commit();
                
                logActivity($_SESSION['user_id'], 'adjust_inventory', 'inventories', $product['inventory_id']);
                
                setMessage('موجودی با موفقیت تعدیل شد', 'success');
                header('Location: inventory_view.php?id=' . $product['inventory_id']);
                exit();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("خطا در تعدیل موجودی: " . $e->getMessage());
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
        <p class="text-muted mb-0">تعدیل موجودی <?php echo htmlspecialchars($product['name']); ?></p>
    </div>
    
    <div>
        <a href="inventory_view.php?id=<?php echo $product['inventory_id']; ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-2"></i>
            بازگشت
        </a>
    </div>
</div>

<?php displayMessage(); ?>

<form method="POST" id="adjustForm">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-adjust me-2 text-primary"></i>
                        اطلاعات تعدیل
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>محصول:</strong> <?php echo htmlspecialchars($product['name']); ?>
                        <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($product['sku']); ?></span>
                        <br>
                        <strong>موجودی فعلی:</strong> <?php echo number_format($product['current_stock']); ?> <?php echo htmlspecialchars($product['unit']); ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label required">نوع عملیات</label>
                            <select class="form-select" name="type" id="type" required>
                                <option value="">انتخاب کنید...</option>
                                <option value="in">افزودن موجودی</option>
                                <option value="out">کاهش موجودی</option>
                                <option value="adjustment">تنظیم دستی</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">تعداد</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="quantity" 
                                       min="1" step="1" required>
                                <span class="input-group-text"><?php echo htmlspecialchars($product['unit']); ?></span>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">قیمت واحد</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="unit_price" 
                                       value="<?php echo $product['average_cost']; ?>" 
                                       min="0" step="0.01" required>
                                <span class="input-group-text">تومان</span>
                            </div>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">یادداشت</label>
                            <textarea class="form-control" name="notes" rows="3" 
                                      placeholder="توضیحات در مورد این تعدیل..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2 text-primary"></i>
                        اطلاعات فعلی
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="fw-bold text-muted">موجودی فعلی</div>
                        <div class="fs-5"><?php echo number_format($product['current_stock']); ?> <?php echo htmlspecialchars($product['unit']); ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="fw-bold text-muted">رزرو شده</div>
                        <div class="fs-5 text-warning"><?php echo number_format($product['reserved_stock']); ?> <?php echo htmlspecialchars($product['unit']); ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="fw-bold text-muted">موجود برای فروش</div>
                        <div class="fs-5 text-success"><?php echo number_format($product['available_stock']); ?> <?php echo htmlspecialchars($product['unit']); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>
                            اعمال تعدیل
                        </button>
                        
                        <a href="inventory_view.php?id=<?php echo $product['inventory_id']; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-eye me-2"></i>
                            مشاهده موجودی
                        </a>
                        
                        <a href="inventory.php" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-2"></i>
                            لیست موجودی
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

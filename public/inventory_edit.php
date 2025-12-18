<?php
$inventory_id = (int)($_GET['id'] ?? 0);

if (!$inventory_id) {
    setMessage('شناسه موجودی نامعتبر است', 'error');
    header('Location: inventory.php');
    exit();
}

$page_title = 'ویرایش موجودی';

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
        SELECT i.*, p.name as product_name, p.sku, p.unit
        FROM inventories i
        INNER JOIN products p ON i.product_id = p.id
        WHERE i.id = ? AND i.deleted_at IS NULL
    ");
    $stmt->execute([$inventory_id]);
    $inventory = $stmt->fetch();
    
    if (!$inventory) {
        setMessage('موجودی یافت نشد', 'error');
        header('Location: inventory.php');
        exit();
    }
    
} catch (PDOException $e) {
    error_log("خطا در دریافت اطلاعات موجودی: " . $e->getMessage());
    setMessage('خطا در بارگذاری اطلاعات', 'error');
    header('Location: inventory.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        setMessage('درخواست نامعتبر. لطفاً مجدداً تلاش کنید.', 'error');
    } else {
        $current_stock = (int)$_POST['current_stock'];
        $reserved_stock = (int)$_POST['reserved_stock'];
        $average_cost = (float)$_POST['average_cost'];
        
        $errors = [];
        
        if ($current_stock < 0) {
            $errors[] = 'موجودی فعلی نمی‌تواند منفی باشد';
        }
        
        if ($reserved_stock < 0) {
            $errors[] = 'موجودی رزرو شده نمی‌تواند منفی باشد';
        }
        
        if ($reserved_stock > $current_stock) {
            $errors[] = 'موجودی رزرو شده نمی‌تواند بیشتر از موجودی فعلی باشد';
        }
        
        if ($average_cost < 0) {
            $errors[] = 'میانگین هزینه نمی‌تواند منفی باشد';
        }
        
        if (empty($errors)) {
            try {
                $available_stock = $current_stock - $reserved_stock;
                
                $stmt = $pdo->prepare("
                    UPDATE inventories SET 
                        current_stock = ?,
                        reserved_stock = ?,
                        available_stock = ?,
                        average_cost = ?,
                        last_updated_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$current_stock, $reserved_stock, $available_stock, $average_cost, $inventory_id]);
                
                // به‌روزرسانی stock_quantity در products
                $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                $stmt->execute([$current_stock, $inventory['product_id']]);
                
                logActivity($_SESSION['user_id'], 'update_inventory', 'inventories', $inventory_id);
                
                setMessage('موجودی با موفقیت به‌روزرسانی شد', 'success');
                header('Location: inventory_view.php?id=' . $inventory_id);
                exit();
                
            } catch (PDOException $e) {
                error_log("خطا در به‌روزرسانی موجودی: " . $e->getMessage());
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
        <p class="text-muted mb-0">ویرایش موجودی <?php echo htmlspecialchars($inventory['product_name']); ?></p>
    </div>
    
    <div>
        <a href="inventory_view.php?id=<?php echo $inventory_id; ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-2"></i>
            بازگشت
        </a>
    </div>
</div>

<?php displayMessage(); ?>

<form method="POST" id="inventoryForm">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-warehouse me-2 text-primary"></i>
                        اطلاعات موجودی
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>محصول:</strong> <?php echo htmlspecialchars($inventory['product_name']); ?>
                        <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($inventory['sku']); ?></span>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">موجودی فعلی</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="current_stock" 
                                       value="<?php echo $inventory['current_stock']; ?>" 
                                       min="0" step="1" required
                                       onchange="calculateAvailable()">
                                <span class="input-group-text"><?php echo htmlspecialchars($inventory['unit']); ?></span>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">موجودی رزرو شده</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="reserved_stock" 
                                       value="<?php echo $inventory['reserved_stock']; ?>" 
                                       min="0" step="1" required
                                       onchange="calculateAvailable()">
                                <span class="input-group-text"><?php echo htmlspecialchars($inventory['unit']); ?></span>
                            </div>
                            <small class="text-muted">موجودی که رزرو شده و قابل فروش نیست</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">موجود برای فروش</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="available_stock" 
                                       value="<?php echo $inventory['available_stock']; ?>" 
                                       readonly>
                                <span class="input-group-text"><?php echo htmlspecialchars($inventory['unit']); ?></span>
                            </div>
                            <small class="text-muted">محاسبه خودکار: موجودی فعلی - رزرو شده</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">میانگین هزینه</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="average_cost" 
                                       value="<?php echo $inventory['average_cost']; ?>" 
                                       min="0" step="0.01" required>
                                <span class="input-group-text">تومان</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>
                            ذخیره تغییرات
                        </button>
                        
                        <a href="inventory_view.php?id=<?php echo $inventory_id; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-eye me-2"></i>
                            مشاهده
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

<script>
function calculateAvailable() {
    const current = parseInt(document.querySelector('input[name="current_stock"]').value) || 0;
    const reserved = parseInt(document.querySelector('input[name="reserved_stock"]').value) || 0;
    const available = Math.max(0, current - reserved);
    document.getElementById('available_stock').value = available;
}
</script>

<style>
.required::after {
    content: ' *';
    color: var(--danger-color);
}
</style>

<?php include __DIR__ . '/../private/footer.php'; ?>

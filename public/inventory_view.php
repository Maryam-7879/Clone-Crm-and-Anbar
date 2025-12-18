<?php
$inventory_id = (int)($_GET['id'] ?? 0);

if (!$inventory_id) {
    setMessage('شناسه موجودی نامعتبر است', 'error');
    header('Location: inventory.php');
    exit();
}

$page_title = 'مشاهده موجودی';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'موجودی', 'url' => 'inventory.php'],
    ['title' => 'مشاهده']
];

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

if (!hasPermission('view_dashboard')) {
    setMessage('شما دسترسی لازم برای این بخش را ندارید', 'error');
    header('Location: dashboard.php');
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            i.*,
            p.name as product_name,
            p.sku,
            p.min_stock_level,
            p.max_stock_level,
            p.unit,
            p.cost_price,
            p.price as selling_price,
            c.name as category_name,
            c.color as category_color
        FROM inventories i
        INNER JOIN products p ON i.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id AND c.deleted_at IS NULL
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

$stock_status_class = 'success';
$stock_status_text = 'کافی';
if ($inventory['current_stock'] == 0) {
    $stock_status_class = 'danger';
    $stock_status_text = 'تمام شده';
} elseif ($inventory['current_stock'] <= $inventory['min_stock_level']) {
    $stock_status_class = 'warning';
    $stock_status_text = 'کم';
}

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><?php echo $page_title; ?></h4>
        <p class="text-muted mb-0">جزئیات موجودی <?php echo htmlspecialchars($inventory['product_name']); ?></p>
    </div>
    
    <div>
        <a href="inventory_edit.php?id=<?php echo $inventory_id; ?>" class="btn btn-warning me-2">
            <i class="fas fa-edit me-2"></i>
            ویرایش
        </a>
        <a href="inventory_adjust.php?product_id=<?php echo $inventory['product_id']; ?>" class="btn btn-info me-2">
            <i class="fas fa-adjust me-2"></i>
            تعدیل موجودی
        </a>
        <a href="inventory.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-2"></i>
            بازگشت
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2 text-primary"></i>
                    اطلاعات موجودی
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">محصول:</label>
                        <p class="form-control-plaintext">
                            <?php echo htmlspecialchars($inventory['product_name']); ?>
                            <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($inventory['sku']); ?></span>
                        </p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">وضعیت:</label>
                        <p class="form-control-plaintext">
                            <span class="badge bg-<?php echo $stock_status_class; ?> fs-6">
                                <?php echo $stock_status_text; ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">موجودی فعلی:</label>
                        <p class="form-control-plaintext fs-4 fw-bold">
                            <?php echo number_format($inventory['current_stock']); ?> 
                            <small class="text-muted"><?php echo htmlspecialchars($inventory['unit']); ?></small>
                        </p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">رزرو شده:</label>
                        <p class="form-control-plaintext fs-5 text-warning">
                            <?php echo number_format($inventory['reserved_stock']); ?> 
                            <small class="text-muted"><?php echo htmlspecialchars($inventory['unit']); ?></small>
                        </p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">موجود برای فروش:</label>
                        <p class="form-control-plaintext fs-5 text-success">
                            <?php echo number_format($inventory['available_stock']); ?> 
                            <small class="text-muted"><?php echo htmlspecialchars($inventory['unit']); ?></small>
                        </p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">میانگین هزینه:</label>
                        <p class="form-control-plaintext fs-5">
                            <?php echo formatMoney($inventory['average_cost']); ?>
                        </p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">حداقل موجودی:</label>
                        <p class="form-control-plaintext">
                            <?php echo number_format($inventory['min_stock_level']); ?> 
                            <small class="text-muted"><?php echo htmlspecialchars($inventory['unit']); ?></small>
                        </p>
                    </div>
                    
                    <?php if ($inventory['max_stock_level']): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">حداکثر موجودی:</label>
                        <p class="form-control-plaintext">
                            <?php echo number_format($inventory['max_stock_level']); ?> 
                            <small class="text-muted"><?php echo htmlspecialchars($inventory['unit']); ?></small>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">آخرین به‌روزرسانی:</label>
                        <p class="form-control-plaintext">
                            <?php echo $inventory['last_updated_at'] ? formatPersianDate($inventory['last_updated_at']) : '-'; ?>
                        </p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">تاریخ ایجاد:</label>
                        <p class="form-control-plaintext">
                            <?php echo formatPersianDate($inventory['created_at']); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2 text-primary"></i>
                    محاسبات
                </h5>
            </div>
            <div class="card-body">
                <?php
                $total_value = $inventory['current_stock'] * $inventory['average_cost'];
                $available_value = $inventory['available_stock'] * $inventory['average_cost'];
                ?>
                <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                    <div>
                        <div class="fw-bold text-primary"><?php echo formatMoney($total_value); ?></div>
                        <small class="text-muted">ارزش کل موجودی</small>
                    </div>
                    <i class="fas fa-dollar-sign fa-2x text-primary"></i>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                    <div>
                        <div class="fw-bold text-success"><?php echo formatMoney($available_value); ?></div>
                        <small class="text-muted">ارزش موجود</small>
                    </div>
                    <i class="fas fa-check-circle fa-2x text-success"></i>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="inventory_adjust.php?product_id=<?php echo $inventory['product_id']; ?>" class="btn btn-info">
                        <i class="fas fa-adjust me-2"></i>
                        تعدیل موجودی
                    </a>
                    <a href="product_view.php?id=<?php echo $inventory['product_id']; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-box me-2"></i>
                        مشاهده محصول
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../private/footer.php'; ?>

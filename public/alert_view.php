<?php
$alert_id = (int)($_GET['id'] ?? 0);

if (!$alert_id) {
    setMessage('شناسه هشدار نامعتبر است', 'error');
    header('Location: alerts.php');
    exit();
}

$page_title = 'مشاهده هشدار';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'هشدارها', 'url' => 'alerts.php'],
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
            a.*,
            p.name as product_name,
            p.sku,
            c.name as category_name,
            c.color as category_color
        FROM alerts a
        INNER JOIN products p ON a.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id AND c.deleted_at IS NULL
        WHERE a.id = ? AND a.deleted_at IS NULL
    ");
    $stmt->execute([$alert_id]);
    $alert = $stmt->fetch();
    
    if (!$alert) {
        setMessage('هشدار یافت نشد', 'error');
        header('Location: alerts.php');
        exit();
    }
    
    // علامت‌گذاری به عنوان خوانده شده
    if (!$alert['is_read']) {
        $stmt = $pdo->prepare("UPDATE alerts SET is_read = 1, read_at = NOW() WHERE id = ?");
        $stmt->execute([$alert_id]);
        $alert['is_read'] = 1;
        $alert['read_at'] = date('Y-m-d H:i:s');
    }
    
} catch (PDOException $e) {
    error_log("خطا در دریافت اطلاعات هشدار: " . $e->getMessage());
    setMessage('خطا در بارگذاری اطلاعات', 'error');
    header('Location: alerts.php');
    exit();
}

$type_labels = [
    'low_stock' => 'موجودی کم',
    'out_of_stock' => 'تمام شده',
    'expiry' => 'انقضا',
    'custom' => 'سفارشی'
];

$type_classes = [
    'low_stock' => 'warning',
    'out_of_stock' => 'danger',
    'expiry' => 'info',
    'custom' => 'secondary'
];

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><?php echo $page_title; ?></h4>
        <p class="text-muted mb-0">جزئیات هشدار</p>
    </div>
    
    <div>
        <a href="alert_form.php?id=<?php echo $alert_id; ?>" class="btn btn-warning me-2">
            <i class="fas fa-edit me-2"></i>
            ویرایش
        </a>
        <a href="alerts.php" class="btn btn-outline-secondary">
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
                    <i class="fas fa-bell me-2 text-primary"></i>
                    اطلاعات هشدار
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-<?php echo $type_classes[$alert['type']]; ?>">
                    <h5 class="alert-heading">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($alert['title']); ?>
                    </h5>
                    <hr>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($alert['message'])); ?></p>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">نوع هشدار:</label>
                        <p class="form-control-plaintext">
                            <span class="badge bg-<?php echo $type_classes[$alert['type']]; ?> fs-6">
                                <?php echo $type_labels[$alert['type']]; ?>
                            </span>
                        </p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">وضعیت:</label>
                        <p class="form-control-plaintext">
                            <?php if ($alert['is_read']): ?>
                                <span class="badge bg-secondary">خوانده شده</span>
                            <?php else: ?>
                                <span class="badge bg-warning">خوانده نشده</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">محصول:</label>
                        <p class="form-control-plaintext">
                            <?php echo htmlspecialchars($alert['product_name']); ?>
                            <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($alert['sku']); ?></span>
                        </p>
                    </div>
                    
                    <?php if ($alert['category_name']): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">دسته‌بندی:</label>
                        <p class="form-control-plaintext">
                            <span class="badge" style="background-color: <?php echo htmlspecialchars($alert['category_color']); ?>; color: white;">
                                <?php echo htmlspecialchars($alert['category_name']); ?>
                            </span>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">تاریخ ایجاد:</label>
                        <p class="form-control-plaintext">
                            <?php echo formatPersianDate($alert['created_at']); ?>
                        </p>
                    </div>
                    
                    <?php if ($alert['read_at']): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">تاریخ خوانده شدن:</label>
                        <p class="form-control-plaintext">
                            <?php echo formatPersianDate($alert['read_at']); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="alert_form.php?id=<?php echo $alert_id; ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-2"></i>
                        ویرایش
                    </a>
                    <a href="product_view.php?id=<?php echo $alert['product_id']; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-box me-2"></i>
                        مشاهده محصول
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../private/footer.php'; ?>

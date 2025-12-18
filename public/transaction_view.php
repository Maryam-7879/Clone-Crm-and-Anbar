<?php
$transaction_id = (int)($_GET['id'] ?? 0);

if (!$transaction_id) {
    setMessage('شناسه تراکنش نامعتبر است', 'error');
    header('Location: transactions.php');
    exit();
}

$page_title = 'مشاهده تراکنش';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'تراکنش‌ها', 'url' => 'transactions.php'],
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
            t.*,
            p.name as product_name,
            p.sku,
            p.unit,
            CONCAT(u.first_name, ' ', u.last_name) as user_name
        FROM transactions t
        INNER JOIN products p ON t.product_id = p.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.id = ? AND t.deleted_at IS NULL
    ");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        setMessage('تراکنش یافت نشد', 'error');
        header('Location: transactions.php');
        exit();
    }
    
} catch (PDOException $e) {
    error_log("خطا در دریافت اطلاعات تراکنش: " . $e->getMessage());
    setMessage('خطا در بارگذاری اطلاعات', 'error');
    header('Location: transactions.php');
    exit();
}

$type_labels = [
    'in' => 'ورود به انبار',
    'out' => 'خروج از انبار',
    'adjustment' => 'تعدیل موجودی',
    'transfer' => 'انتقال'
];

$type_classes = [
    'in' => 'success',
    'out' => 'danger',
    'adjustment' => 'warning',
    'transfer' => 'info'
];

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><?php echo $page_title; ?></h4>
        <p class="text-muted mb-0">جزئیات تراکنش</p>
    </div>
    
    <div>
        <a href="transaction_edit.php?id=<?php echo $transaction_id; ?>" class="btn btn-warning me-2">
            <i class="fas fa-edit me-2"></i>
            ویرایش
        </a>
        <a href="transactions.php" class="btn btn-outline-secondary">
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
                    اطلاعات تراکنش
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">محصول:</label>
                        <p class="form-control-plaintext">
                            <?php echo htmlspecialchars($transaction['product_name']); ?>
                            <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($transaction['sku']); ?></span>
                        </p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">نوع تراکنش:</label>
                        <p class="form-control-plaintext">
                            <span class="badge bg-<?php echo $type_classes[$transaction['type']]; ?> fs-6">
                                <?php echo $type_labels[$transaction['type']]; ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">تعداد:</label>
                        <p class="form-control-plaintext fs-5">
                            <?php echo number_format($transaction['quantity']); ?> 
                            <small class="text-muted"><?php echo htmlspecialchars($transaction['unit']); ?></small>
                        </p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">قیمت واحد:</label>
                        <p class="form-control-plaintext fs-5">
                            <?php echo formatMoney($transaction['unit_price']); ?>
                        </p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">جمع کل:</label>
                        <p class="form-control-plaintext fs-4 fw-bold text-primary">
                            <?php echo formatMoney($transaction['total_price']); ?>
                        </p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">کاربر:</label>
                        <p class="form-control-plaintext">
                            <?php echo htmlspecialchars($transaction['user_name'] ?: '-'); ?>
                        </p>
                    </div>
                </div>
                
                <?php if ($transaction['reference_number']): ?>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">شماره مرجع:</label>
                        <p class="form-control-plaintext">
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($transaction['reference_number']); ?></span>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">تاریخ تراکنش:</label>
                        <p class="form-control-plaintext">
                            <?php echo formatPersianDate($transaction['transaction_date']); ?>
                        </p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">تاریخ ثبت:</label>
                        <p class="form-control-plaintext">
                            <?php echo formatPersianDate($transaction['created_at']); ?>
                        </p>
                    </div>
                </div>
                
                <?php if ($transaction['notes']): ?>
                <div class="mb-3">
                    <label class="form-label fw-bold">یادداشت:</label>
                    <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($transaction['notes'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="transaction_edit.php?id=<?php echo $transaction_id; ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-2"></i>
                        ویرایش
                    </a>
                    <a href="product_view.php?id=<?php echo $transaction['product_id']; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-box me-2"></i>
                        مشاهده محصول
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../private/footer.php'; ?>

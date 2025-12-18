<?php
$sale_id = (int)($_GET['id'] ?? 0);
$page_title = 'جزئیات فروش';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'فروش‌ها', 'url' => 'sales.php'],
    ['title' => 'جزئیات فروش']
];

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
if (!hasPermission('view_sales')) {
    setMessage('شما دسترسی لازم برای مشاهده این صفحه را ندارید', 'error');
    header('Location: sales.php');
    exit();
}

if (!$sale_id) {
    setMessage('شناسه فروش معتبر نیست', 'error');
    header('Location: sales.php');
    exit();
}

// دریافت اطلاعات فروش
try {
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            c.customer_code, c.email as customer_email, c.mobile as customer_mobile,
            c.address as customer_address, c.city, c.state, c.postal_code,
            l.title as lead_title,
            CONCAT(u.first_name, ' ', u.last_name) as created_user
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN leads l ON s.lead_id = l.id
        LEFT JOIN users u ON s.created_by = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch();
    
    if (!$sale) {
        setMessage('فروش یافت نشد', 'error');
        header('Location: sales.php');
        exit();
    }
    
    // دریافت اقلام فروش
    $items_stmt = $pdo->prepare("
        SELECT 
            si.*,
            p.name as product_name, p.sku, p.description as product_description
        FROM sale_items si
        LEFT JOIN products p ON si.product_id = p.id
        WHERE si.sale_id = ?
        ORDER BY si.id
    ");
    $items_stmt->execute([$sale_id]);
    $sale_items = $items_stmt->fetchAll();
    
    // دریافت پرداخت‌ها (اگر جدول پرداخت وجود داشته باشد)
    $payments = [];
    try {
        $payments_stmt = $pdo->prepare("
            SELECT 
                sp.*,
                CONCAT(u.first_name, ' ', u.last_name) as created_user
            FROM sale_payments sp
            LEFT JOIN users u ON sp.created_by = u.id
            WHERE sp.sale_id = ?
            ORDER BY sp.payment_date DESC
        ");
        $payments_stmt->execute([$sale_id]);
        $payments = $payments_stmt->fetchAll();
    } catch (PDOException $e) {
        // جدول پرداخت وجود ندارد
    }
    
} catch (PDOException $e) {
    error_log("خطا در دریافت اطلاعات فروش: " . $e->getMessage());
    setMessage('خطا در بارگذاری اطلاعات فروش', 'error');
    header('Location: sales.php');
    exit();
}

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">جزئیات فروش</h4>
        <p class="text-muted mb-0">
            فروش شماره <?php echo htmlspecialchars($sale['sale_number']); ?>
            <span class="badge bg-<?php echo getStatusClass($sale['status']); ?> ms-2">
                <?php echo getStatusTitle($sale['status']); ?>
            </span>
        </p>
    </div>
    
    <div>
        <a href="sales.php" class="btn btn-outline-secondary me-2">
            <i class="fas fa-arrow-right me-2"></i>
            بازگشت
        </a>
        
        <div class="btn-group" role="group">
            <?php if (hasPermission('edit_sale')): ?>
                <a href="sale_form.php?id=<?php echo $sale_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-2"></i>
                    ویرایش
                </a>
            <?php endif; ?>
            
            <a href="invoice.php?sale_id=<?php echo $sale_id; ?>" class="btn btn-success" target="_blank">
                <i class="fas fa-file-invoice me-2"></i>
                فاکتور
            </a>
            
            <button type="button" class="btn btn-info" onclick="window.print()">
                <i class="fas fa-print me-2"></i>
                چاپ
            </button>
        </div>
    </div>
</div>

<div class="row">
    <!-- اطلاعات فروش -->
    <div class="col-lg-8 mb-4">
        <!-- اطلاعات اصلی -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2 text-primary"></i>
                    اطلاعات فروش
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="fw-bold text-muted">شماره فروش</div>
                            <div class="h6 mb-0">
                                <span class="badge bg-secondary fs-6"><?php echo htmlspecialchars($sale['sale_number']); ?></span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="fw-bold text-muted">تاریخ فروش</div>
                            <div><?php echo formatPersianDate($sale['sale_date']); ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="fw-bold text-muted">وضعیت</div>
                            <div>
                                <span class="badge bg-<?php echo getStatusClass($sale['status']); ?> fs-6">
                                    <?php echo getStatusTitle($sale['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if ($sale['payment_method']): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-muted">روش پرداخت</div>
                            <div>
                                <?php 
                                $methods = [
                                    'cash' => 'نقدی',
                                    'card' => 'کارت',
                                    'transfer' => 'انتقال بانکی',
                                    'check' => 'چک',
                                    'installment' => 'اقساطی'
                                ];
                                echo $methods[$sale['payment_method']] ?? $sale['payment_method'];
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="fw-bold text-muted">وضعیت پرداخت</div>
                            <div>
                                <span class="badge bg-<?php echo getPaymentStatusClass($sale['payment_status']); ?> fs-6">
                                    <?php echo getPaymentStatusTitle($sale['payment_status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="fw-bold text-muted">تاریخ ایجاد</div>
                            <div><?php echo formatPersianDate($sale['created_at']); ?></div>
                        </div>
                        
                        <?php if ($sale['created_user']): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-muted">ایجادکننده</div>
                            <div>
                                <i class="fas fa-user me-1"></i>
                                <?php echo htmlspecialchars($sale['created_user']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($sale['lead_title']): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-muted">لید مرتبط</div>
                            <div>
                                <a href="lead_view.php?id=<?php echo $sale['lead_id']; ?>" class="text-decoration-none">
                                    <i class="fas fa-bullseye me-1"></i>
                                    <?php echo htmlspecialchars($sale['lead_title']); ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($sale['notes']): ?>
                <div class="mt-3">
                    <div class="fw-bold text-muted mb-2">یادداشت‌ها</div>
                    <div class="bg-light p-3 rounded">
                        <?php echo nl2br(htmlspecialchars($sale['notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- اقلام فروش -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-shopping-cart me-2 text-primary"></i>
                    اقلام فروش (<?php echo count($sale_items); ?> قلم)
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>محصول</th>
                                <th>کد محصول</th>
                                <th class="text-center">تعداد</th>
                                <th class="text-end">قیمت واحد</th>
                                <th class="text-end">قیمت کل</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sale_items as $item): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                        <?php if ($item['product_description']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($item['product_description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($item['sku']); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?php echo number_format($item['quantity']); ?></span>
                                    </td>
                                    <td class="text-end fw-bold"><?php echo formatMoney($item['unit_price']); ?></td>
                                    <td class="text-end fw-bold text-success"><?php echo formatMoney($item['total_price']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="4" class="text-end">جمع کل:</th>
                                <th class="text-end text-success"><?php echo formatMoney($sale['subtotal']); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- پرداخت‌ها -->
        <?php if (!empty($payments)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-credit-card me-2 text-primary"></i>
                    تاریخچه پرداخت‌ها
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>تاریخ پرداخت</th>
                                <th>مبلغ</th>
                                <th>روش پرداخت</th>
                                <th>وضعیت</th>
                                <th>ایجادکننده</th>
                                <th>یادداشت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_paid = 0;
                            foreach ($payments as $payment): 
                                $total_paid += $payment['amount'];
                            ?>
                                <tr>
                                    <td><?php echo formatPersianDate($payment['payment_date']); ?></td>
                                    <td class="fw-bold text-success"><?php echo formatMoney($payment['amount']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo getPaymentStatusClass($payment['status']); ?>">
                                            <?php echo getPaymentStatusTitle($payment['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['created_user'] ?? 'نامشخص'); ?></td>
                                    <td><?php echo htmlspecialchars($payment['notes'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th>مجموع پرداخت‌ها:</th>
                                <th class="text-success"><?php echo formatMoney($total_paid); ?></th>
                                <th colspan="4"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- اطلاعات جانبی -->
    <div class="col-lg-4">
        <!-- خلاصه مالی -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calculator me-2 text-primary"></i>
                    خلاصه مالی
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">جمع کل:</span>
                    <span class="fw-bold"><?php echo formatMoney($sale['subtotal']); ?></span>
                </div>
                
                <?php if ($sale['tax_amount'] > 0): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">مالیات:</span>
                    <span class="fw-bold text-warning">+<?php echo formatMoney($sale['tax_amount']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($sale['discount_amount'] > 0): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">تخفیف:</span>
                    <span class="fw-bold text-danger">-<?php echo formatMoney($sale['discount_amount']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($sale['shipping_amount'] > 0): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">هزینه ارسال:</span>
                    <span class="fw-bold text-info">+<?php echo formatMoney($sale['shipping_amount']); ?></span>
                </div>
                <?php endif; ?>
                
                <hr>
                
                <div class="d-flex justify-content-between mb-3">
                    <span class="h6 mb-0">مبلغ نهایی:</span>
                    <span class="h6 mb-0 text-success"><?php echo formatMoney($sale['final_amount']); ?></span>
                </div>
                
                <?php if (!empty($payments)): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">پرداخت شده:</span>
                    <span class="fw-bold text-success"><?php echo formatMoney($total_paid); ?></span>
                </div>
                
                <div class="d-flex justify-content-between">
                    <span class="text-muted">باقی‌مانده:</span>
                    <span class="fw-bold text-<?php echo ($sale['final_amount'] - $total_paid) > 0 ? 'danger' : 'success'; ?>">
                        <?php echo formatMoney($sale['final_amount'] - $total_paid); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- اطلاعات مشتری -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2 text-primary"></i>
                    اطلاعات مشتری
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="user-avatar me-3" style="width: 50px; height: 50px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($sale['customer_name']); ?></div>
                        <small class="text-muted">کد: <?php echo htmlspecialchars($sale['customer_code']); ?></small>
                    </div>
                </div>
                
                <?php if ($sale['customer_email']): ?>
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-envelope text-primary me-2" style="width: 20px;"></i>
                    <a href="mailto:<?php echo $sale['customer_email']; ?>" class="text-decoration-none">
                        <?php echo htmlspecialchars($sale['customer_email']); ?>
                    </a>
                </div>
                <?php endif; ?>
                
                <?php if ($sale['customer_mobile']): ?>
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-mobile-alt text-success me-2" style="width: 20px;"></i>
                    <a href="tel:<?php echo $sale['customer_mobile']; ?>" class="text-decoration-none">
                        <?php echo formatPhone($sale['customer_mobile']); ?>
                    </a>
                </div>
                <?php endif; ?>
                
                <?php if ($sale['customer_address']): ?>
                <div class="d-flex align-items-start">
                    <i class="fas fa-map-marker-alt text-danger me-2 mt-1" style="width: 20px;"></i>
                    <div>
                        <div><?php echo htmlspecialchars($sale['customer_address']); ?></div>
                        <?php if ($sale['city'] || $sale['state']): ?>
                            <small class="text-muted">
                                <?php echo $sale['city'] ? htmlspecialchars($sale['city']) : ''; ?>
                                <?php echo ($sale['city'] && $sale['state']) ? '، ' : ''; ?>
                                <?php echo $sale['state'] ? htmlspecialchars($sale['state']) : ''; ?>
                                <?php if ($sale['postal_code']): ?>
                                    <br>کد پستی: <?php echo htmlspecialchars($sale['postal_code']); ?>
                                <?php endif; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <a href="customer_view.php?id=<?php echo $sale['customer_id']; ?>" class="btn btn-outline-primary btn-sm w-100">
                        <i class="fas fa-eye me-2"></i>
                        مشاهده مشتری
                    </a>
                </div>
            </div>
        </div>
        
        <!-- اقدامات سریع -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2 text-primary"></i>
                    اقدامات سریع
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="invoice.php?sale_id=<?php echo $sale_id; ?>" class="btn btn-success btn-sm" target="_blank">
                        <i class="fas fa-file-invoice me-2"></i>
                        دریافت فاکتور
                    </a>
                    
                    <?php if (hasPermission('edit_sale')): ?>
                        <a href="sale_form.php?id=<?php echo $sale_id; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit me-2"></i>
                            ویرایش فروش
                        </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('add_sale')): ?>
                        <a href="sale_form.php?customer_id=<?php echo $sale['customer_id']; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-plus me-2"></i>
                            فروش جدید به این مشتری
                        </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('add_task')): ?>
                        <a href="task_form.php?related_type=customer&related_id=<?php echo $sale['customer_id']; ?>" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-tasks me-2"></i>
                            ثبت وظیفه پیگیری
                        </a>
                    <?php endif; ?>
                    
                    <button type="button" class="btn btn-outline-info btn-sm" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>
                        چاپ این صفحه
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .btn, .card-header, .breadcrumb, .d-flex.justify-content-between {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .card-body {
        padding: 0 !important;
    }
    
    .col-lg-4 {
        display: none !important;
    }
    
    .col-lg-8 {
        width: 100% !important;
    }
}

.user-avatar {
    flex-shrink: 0;
}

.table th {
    border-top: none;
    border-bottom: 2px solid var(--border-color);
    color: var(--text-medium);
    font-weight: 600;
}

.badge.fs-6 {
    font-size: 0.875rem !important;
}
</style>

<?php include __DIR__ . '/../private/footer.php'; ?>

<?php
// توابع کمکی برای وضعیت پرداخت
function getPaymentStatusClass($status) {
    return match($status) {
        'pending' => 'warning',
        'partial' => 'info',
        'paid' => 'success',
        'refunded' => 'danger',
        default => 'secondary'
    };
}

function getPaymentStatusTitle($status) {
    return match($status) {
        'pending' => 'در انتظار پرداخت',
        'partial' => 'پرداخت جزئی',
        'paid' => 'پرداخت شده',
        'refunded' => 'بازگردانده شده',
        default => 'نامشخص'
    };
}
?>

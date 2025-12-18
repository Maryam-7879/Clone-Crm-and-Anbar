<?php
$supplier_id = (int)($_GET['id'] ?? 0);

if (!$supplier_id) {
    setMessage('شناسه تامین‌کننده نامعتبر است', 'error');
    header('Location: suppliers.php');
    exit();
}

$page_title = 'مشاهده تامین‌کننده';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'تامین‌کنندگان', 'url' => 'suppliers.php'],
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
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$supplier_id]);
    $supplier = $stmt->fetch();
    
    if (!$supplier) {
        setMessage('تامین‌کننده یافت نشد', 'error');
        header('Location: suppliers.php');
        exit();
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = ? AND deleted_at IS NULL");
    $stmt->execute([$supplier_id]);
    $products_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT id, name, sku, price, stock_quantity, status 
        FROM products 
        WHERE supplier_id = ? AND deleted_at IS NULL 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$supplier_id]);
    $products = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("خطا در دریافت اطلاعات تامین‌کننده: " . $e->getMessage());
    setMessage('خطا در بارگذاری اطلاعات', 'error');
    header('Location: suppliers.php');
    exit();
}

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><?php echo $page_title; ?></h4>
        <p class="text-muted mb-0">جزئیات تامین‌کننده <?php echo htmlspecialchars($supplier['name']); ?></p>
    </div>
    
    <div>
        <a href="supplier_form.php?id=<?php echo $supplier_id; ?>" class="btn btn-warning me-2">
            <i class="fas fa-edit me-2"></i>
            ویرایش
        </a>
        <a href="suppliers.php" class="btn btn-outline-secondary">
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
                    اطلاعات تامین‌کننده
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">نام:</label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($supplier['name']); ?></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">وضعیت:</label>
                        <p class="form-control-plaintext">
                            <span class="badge bg-<?php echo $supplier['is_active'] ? 'success' : 'danger'; ?>">
                                <?php echo $supplier['is_active'] ? 'فعال' : 'غیرفعال'; ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <?php if ($supplier['contact_person']): ?>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">شخص تماس:</label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($supplier['contact_person']); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <?php if ($supplier['email']): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">ایمیل:</label>
                        <p class="form-control-plaintext">
                            <a href="mailto:<?php echo htmlspecialchars($supplier['email']); ?>">
                                <?php echo htmlspecialchars($supplier['email']); ?>
                            </a>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($supplier['phone']): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">تلفن:</label>
                        <p class="form-control-plaintext"><?php echo formatPhone($supplier['phone']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($supplier['tax_number']): ?>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">کد اقتصادی:</label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($supplier['tax_number']); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($supplier['address']): ?>
                <div class="mb-3">
                    <label class="form-label fw-bold">آدرس:</label>
                    <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($supplier['address'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($supplier['notes']): ?>
                <div class="mb-3">
                    <label class="form-label fw-bold">یادداشت‌ها:</label>
                    <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($supplier['notes'])); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">تاریخ ایجاد:</label>
                        <p class="form-control-plaintext"><?php echo formatPersianDate($supplier['created_at']); ?></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">آخرین به‌روزرسانی:</label>
                        <p class="form-control-plaintext"><?php echo formatPersianDate($supplier['updated_at']); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-box me-2 text-primary"></i>
                    محصولات این تامین‌کننده
                    <span class="badge bg-primary ms-2"><?php echo number_format($products_count); ?></span>
                </h5>
                <?php if ($products_count > 0): ?>
                    <a href="products.php?supplier_id=<?php echo $supplier_id; ?>" class="btn btn-outline-primary btn-sm">
                        مشاهده همه
                        <i class="fas fa-arrow-left ms-1"></i>
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($products)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-box fa-3x mb-3"></i>
                        <p>محصولی از این تامین‌کننده وجود ندارد</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>نام محصول</th>
                                    <th>قیمت</th>
                                    <th>موجودی</th>
                                    <th>وضعیت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($product['sku']); ?></span></td>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo formatMoney($product['price']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $product['stock_quantity'] > 10 ? 'success' : ($product['stock_quantity'] > 0 ? 'warning' : 'danger'); ?>">
                                                <?php echo number_format($product['stock_quantity']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $product['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo $product['status'] === 'active' ? 'فعال' : 'غیرفعال'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="product_view.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($products_count > 20): ?>
                        <div class="text-center mt-3">
                            <a href="products.php?supplier_id=<?php echo $supplier_id; ?>" class="btn btn-outline-primary">
                                مشاهده همه <?php echo number_format($products_count); ?> محصول
                                <i class="fas fa-arrow-left ms-1"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2 text-primary"></i>
                    آمار
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                    <div>
                        <div class="fw-bold text-primary"><?php echo number_format($products_count); ?></div>
                        <small class="text-muted">تعداد محصولات</small>
                    </div>
                    <i class="fas fa-box fa-2x text-primary"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../private/footer.php'; ?>

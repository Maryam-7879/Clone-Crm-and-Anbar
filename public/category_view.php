<?php
$category_id = (int)($_GET['id'] ?? 0);

if (!$category_id) {
    setMessage('شناسه دسته‌بندی نامعتبر است', 'error');
    header('Location: categories.php');
    exit();
}

$page_title = 'مشاهده دسته‌بندی';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'دسته‌بندی‌ها', 'url' => 'categories.php'],
    ['title' => 'مشاهده']
];

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
if (!hasPermission('view_dashboard')) {
    setMessage('شما دسترسی لازم برای این بخش را ندارید', 'error');
    header('Location: dashboard.php');
    exit();
}

// دریافت اطلاعات دسته‌بندی
try {
    // دریافت اطلاعات دسته‌بندی
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch();
    
    if (!$category) {
        setMessage('دسته‌بندی یافت نشد', 'error');
        header('Location: categories.php');
        exit();
    }
    
    // تعداد محصولات این دسته‌بندی
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ? AND deleted_at IS NULL");
    $stmt->execute([$category_id]);
    $products_count = $stmt->fetchColumn();
    
    // لیست محصولات این دسته‌بندی
    $stmt = $pdo->prepare("
        SELECT id, name, sku, price, stock_quantity, status 
        FROM products 
        WHERE category_id = ? AND deleted_at IS NULL 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$category_id]);
    $products = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("خطا در دریافت اطلاعات دسته‌بندی: " . $e->getMessage());
    setMessage('خطا در بارگذاری اطلاعات', 'error');
    header('Location: categories.php');
    exit();
}

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><?php echo $page_title; ?></h4>
        <p class="text-muted mb-0">جزئیات دسته‌بندی <?php echo htmlspecialchars($category['name']); ?></p>
    </div>
    
    <div>
        <a href="category_form.php?id=<?php echo $category_id; ?>" class="btn btn-warning me-2">
            <i class="fas fa-edit me-2"></i>
            ویرایش
        </a>
        <a href="categories.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-2"></i>
            بازگشت
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- اطلاعات اصلی -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2 text-primary"></i>
                    اطلاعات دسته‌بندی
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">نام دسته‌بندی:</label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($category['name']); ?></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">وضعیت:</label>
                        <p class="form-control-plaintext">
                            <span class="badge bg-<?php echo $category['is_active'] ? 'success' : 'danger'; ?>">
                                <?php echo $category['is_active'] ? 'فعال' : 'غیرفعال'; ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <?php if ($category['description']): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">توضیحات:</label>
                        <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($category['description'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">رنگ:</label>
                        <p class="form-control-plaintext">
                            <span class="badge" style="background-color: <?php echo htmlspecialchars($category['color']); ?>; color: white;">
                                <div class="d-inline-block me-2" style="width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($category['color']); ?>; border: 1px solid #fff; border-radius: 3px;"></div>
                                <?php echo htmlspecialchars($category['color']); ?>
                            </span>
                        </p>
                    </div>
                    
                    <?php if ($category['icon']): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">آیکون:</label>
                        <p class="form-control-plaintext">
                            <i class="<?php echo htmlspecialchars($category['icon']); ?> fa-2x" style="color: <?php echo htmlspecialchars($category['color']); ?>;"></i>
                            <small class="text-muted ms-2"><?php echo htmlspecialchars($category['icon']); ?></small>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">تاریخ ایجاد:</label>
                        <p class="form-control-plaintext"><?php echo formatPersianDate($category['created_at']); ?></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">آخرین به‌روزرسانی:</label>
                        <p class="form-control-plaintext"><?php echo formatPersianDate($category['updated_at']); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- محصولات این دسته‌بندی -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-box me-2 text-primary"></i>
                    محصولات این دسته‌بندی
                    <span class="badge bg-primary ms-2"><?php echo number_format($products_count); ?></span>
                </h5>
                <?php if ($products_count > 0): ?>
                    <a href="products.php?category_id=<?php echo $category_id; ?>" class="btn btn-outline-primary btn-sm">
                        مشاهده همه
                        <i class="fas fa-arrow-left ms-1"></i>
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($products)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-box fa-3x mb-3"></i>
                        <p>محصولی در این دسته‌بندی وجود ندارد</p>
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
                                        <td>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($product['sku']); ?></span>
                                        </td>
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
                            <a href="products.php?category_id=<?php echo $category_id; ?>" class="btn btn-outline-primary">
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
        <!-- آمار -->
        <div class="card mb-4">
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
                
                <?php
                // آمار محصولات فعال
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ? AND status = 'active' AND deleted_at IS NULL");
                $stmt->execute([$category_id]);
                $active_products = $stmt->fetchColumn();
                ?>
                <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                    <div>
                        <div class="fw-bold text-success"><?php echo number_format($active_products); ?></div>
                        <small class="text-muted">محصولات فعال</small>
                    </div>
                    <i class="fas fa-check-circle fa-2x text-success"></i>
                </div>
            </div>
        </div>
        
        <!-- پیش‌نمایش -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-eye me-2 text-primary"></i>
                    پیش‌نمایش
                </h5>
            </div>
            <div class="card-body text-center">
                <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3"
                     style="width: 100px; height: 100px; background-color: <?php echo htmlspecialchars($category['color']); ?>20; color: <?php echo htmlspecialchars($category['color']); ?>;">
                    <i class="<?php echo htmlspecialchars($category['icon'] ?: 'fas fa-tag'); ?> fa-3x"></i>
                </div>
                <h5><?php echo htmlspecialchars($category['name']); ?></h5>
                <?php if ($category['description']): ?>
                    <p class="text-muted"><?php echo truncateText(htmlspecialchars($category['description']), 100); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../private/footer.php'; ?>

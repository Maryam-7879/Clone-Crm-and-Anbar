<?php
$product_id = (int)($_GET['id'] ?? 0);
$page_title = 'جزئیات محصول';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'محصولات', 'url' => 'products.php'],
    ['title' => 'جزئیات محصول']
];

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
if (!hasPermission('view_products')) {
    setMessage('شما دسترسی لازم برای مشاهده این صفحه را ندارید', 'error');
    header('Location: products.php');
    exit();
}

if (!$product_id) {
    setMessage('شناسه محصول معتبر نیست', 'error');
    header('Location: products.php');
    exit();
}

    // دریافت اطلاعات محصول
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.*,
                c.name as category_name, 
                c.color as category_color, 
                c.icon as category_icon,
                s.name as supplier_name,
                CONCAT(u.first_name, ' ', u.last_name) as created_user
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id AND c.deleted_at IS NULL
            LEFT JOIN suppliers s ON p.supplier_id = s.id AND s.deleted_at IS NULL
            LEFT JOIN users u ON p.created_by = u.id
            WHERE p.id = ?
        ");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
    
    if (!$product) {
        setMessage('محصول یافت نشد', 'error');
        header('Location: products.php');
        exit();
    }
    
    // آمار فروش محصول
    $sales_stats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_sales,
            COALESCE(SUM(si.quantity), 0) as total_quantity_sold,
            COALESCE(SUM(si.total_price), 0) as total_revenue,
            MAX(s.sale_date) as last_sale_date
        FROM sale_items si
        LEFT JOIN sales s ON si.sale_id = s.id
        WHERE si.product_id = ? AND s.status != 'cancelled'
    ");
    $sales_stats->execute([$product_id]);
    $sales_stats = $sales_stats->fetch();
    
    // فروش‌های اخیر
    $recent_sales = $pdo->prepare("
        SELECT 
            s.sale_number, s.sale_date, s.status,
            si.quantity, si.unit_price, si.total_price,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            c.customer_code
        FROM sale_items si
        LEFT JOIN sales s ON si.sale_id = s.id
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE si.product_id = ?
        ORDER BY s.sale_date DESC
        LIMIT 10
    ");
    $recent_sales->execute([$product_id]);
    $recent_sales = $recent_sales->fetchAll();
    
    // محصولات مرتبط (در همان دسته‌بندی)
    $related_products = [];
    if ($product['category_id']) {
        $related_stmt = $pdo->prepare("
            SELECT id, name, sku, price, stock_quantity, status
            FROM products 
            WHERE category_id = ? AND id != ? AND status = 'active' AND deleted_at IS NULL
            ORDER BY name
            LIMIT 5
        ");
        $related_stmt->execute([$product['category_id'], $product_id]);
        $related_products = $related_stmt->fetchAll();
    } elseif ($product['category']) {
        // Fallback برای داده‌های قدیمی
        $related_stmt = $pdo->prepare("
            SELECT id, name, sku, price, stock_quantity, status
            FROM products 
            WHERE category = ? AND id != ? AND status = 'active'
            ORDER BY name
            LIMIT 5
        ");
        $related_stmt->execute([$product['category'], $product_id]);
        $related_products = $related_stmt->fetchAll();
    }
    
    // بررسی موجودی کم
    $low_stock = $product['stock_quantity'] <= $product['min_stock_level'] && $product['min_stock_level'] > 0;
    
} catch (PDOException $e) {
    error_log("خطا در دریافت اطلاعات محصول: " . $e->getMessage());
    setMessage('خطا در بارگذاری اطلاعات محصول', 'error');
    header('Location: products.php');
    exit();
}

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">جزئیات محصول</h4>
        <p class="text-muted mb-0">
            <?php echo htmlspecialchars($product['name']); ?>
            <span class="badge bg-<?php echo getStatusClass($product['status'], 'product'); ?> ms-2">
                <?php echo getStatusTitle($product['status'], 'product'); ?>
            </span>
            <?php if ($low_stock): ?>
                <span class="badge bg-warning ms-1">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    موجودی کم
                </span>
            <?php endif; ?>
        </p>
    </div>
    
    <div>
        <a href="products.php" class="btn btn-outline-secondary me-2">
            <i class="fas fa-arrow-right me-2"></i>
            بازگشت
        </a>
        
        <div class="btn-group" role="group">
            <?php if (hasPermission('edit_product')): ?>
                <a href="product_form.php?id=<?php echo $product_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-2"></i>
                    ویرایش
                </a>
            <?php endif; ?>
            
            <button type="button" class="btn btn-info" onclick="window.print()">
                <i class="fas fa-print me-2"></i>
                چاپ
            </button>
        </div>
    </div>
</div>

<div class="row">
    <!-- اطلاعات اصلی محصول -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-box me-2 text-primary"></i>
                    اطلاعات محصول
                </h5>
            </div>
            <div class="card-body text-center">
                <div class="product-icon mx-auto mb-3" style="width: 80px; height: 80px; background: var(--primary-color); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                    <i class="fas fa-box"></i>
                </div>
                
                <h5 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h5>
                <p class="text-muted mb-3">
                    <span class="badge bg-secondary"><?php echo htmlspecialchars($product['sku']); ?></span>
                    <?php if (!empty($product['category_name'])): ?>
                        <span class="badge ms-1" style="background-color: <?php echo htmlspecialchars($product['category_color'] ?? '#17a2b8'); ?>; color: white;">
                            <?php if ($product['category_icon']): ?>
                                <i class="<?php echo htmlspecialchars($product['category_icon']); ?> me-1"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($product['category_name']); ?>
                        </span>
                    <?php elseif ($product['category']): ?>
                        <span class="badge bg-info ms-1"><?php echo htmlspecialchars($product['category']); ?></span>
                    <?php endif; ?>
                </p>
                
                <div class="row text-center">
                    <div class="col-6">
                        <div class="h4 text-success"><?php echo formatMoney($product['price']); ?></div>
                        <small class="text-muted">قیمت فروش</small>
                    </div>
                    <div class="col-6">
                        <div class="h4 text-<?php echo $low_stock ? 'danger' : ($product['stock_quantity'] > 0 ? 'info' : 'warning'); ?>">
                            <?php echo number_format($product['stock_quantity']); ?>
                        </div>
                        <small class="text-muted">موجودی <?php echo $product['unit'] ? '(' . $product['unit'] . ')' : ''; ?></small>
                    </div>
                </div>
                
                <?php if ($product['cost_price'] > 0): ?>
                <div class="mt-3">
                    <div class="small text-muted">سود هر واحد: 
                        <span class="fw-bold text-success">
                            <?php echo formatMoney($product['price'] - $product['cost_price']); ?>
                        </span>
                    </div>
                    <div class="small text-muted">درصد سود: 
                        <span class="fw-bold">
                            <?php echo round((($product['price'] - $product['cost_price']) / $product['cost_price']) * 100, 1); ?>%
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- اطلاعات انبار -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-warehouse me-2 text-primary"></i>
                    اطلاعات انبار
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted">موجودی فعلی:</span>
                    <span class="fw-bold text-<?php echo $low_stock ? 'danger' : 'success'; ?>">
                        <?php echo number_format($product['stock_quantity']); ?>
                        <?php echo $product['unit'] ? ' ' . $product['unit'] : ''; ?>
                    </span>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted">حداقل موجودی:</span>
                    <span class="fw-bold"><?php echo number_format($product['min_stock_level']); ?></span>
                </div>
                
                <?php if ($product['cost_price'] > 0): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted">ارزش موجودی:</span>
                    <span class="fw-bold text-info">
                        <?php echo formatMoney($product['stock_quantity'] * $product['cost_price']); ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if ($low_stock): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <small>موجودی کمتر از حداقل مجاز است!</small>
                </div>
                <?php endif; ?>
                
                <?php if ($product['barcode']): ?>
                <div class="mt-3 text-center">
                    <div class="text-muted small">بارکد</div>
                    <div class="fw-bold font-monospace"><?php echo htmlspecialchars($product['barcode']); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- آمار فروش -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2 text-primary"></i>
                    آمار فروش
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="h5 text-primary"><?php echo number_format($sales_stats['total_sales']); ?></div>
                        <small class="text-muted">تعداد فروش</small>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="h5 text-info"><?php echo number_format($sales_stats['total_quantity_sold']); ?></div>
                        <small class="text-muted">تعداد فروخته شده</small>
                    </div>
                    <div class="col-12">
                        <div class="h5 text-success"><?php echo formatMoney($sales_stats['total_revenue']); ?></div>
                        <small class="text-muted">درآمد کل</small>
                    </div>
                </div>
                
                <?php if ($sales_stats['last_sale_date']): ?>
                <div class="mt-3 text-center">
                    <small class="text-muted">
                        آخرین فروش: <?php echo formatPersianDate($sales_stats['last_sale_date'], 'Y/m/d'); ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- جزئیات و تب‌ها -->
    <div class="col-lg-8">
        <!-- اطلاعات تفصیلی -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2 text-primary"></i>
                    اطلاعات تفصیلی
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="fw-bold text-muted">کد محصول</div>
                            <div><span class="badge bg-secondary fs-6"><?php echo htmlspecialchars($product['sku']); ?></span></div>
                        </div>
                        
                        <?php if (!empty($product['category_name'])): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-muted">دسته‌بندی</div>
                            <div>
                                <span class="badge" style="background-color: <?php echo htmlspecialchars($product['category_color'] ?? '#17a2b8'); ?>; color: white;">
                                    <?php if ($product['category_icon']): ?>
                                        <i class="<?php echo htmlspecialchars($product['category_icon']); ?> me-1"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($product['category_name']); ?>
                                </span>
                            </div>
                        </div>
                        <?php elseif ($product['category']): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-muted">دسته‌بندی</div>
                            <div><?php echo htmlspecialchars($product['category']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($product['supplier_name'])): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-muted">تامین‌کننده</div>
                            <div>
                                <a href="supplier_view.php?id=<?php echo $product['supplier_id']; ?>" class="text-decoration-none">
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($product['supplier_name']); ?></span>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($product['unit']): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-muted">واحد شمارش</div>
                            <div><?php echo htmlspecialchars($product['unit']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <div class="fw-bold text-muted">وضعیت</div>
                            <div>
                                <span class="badge bg-<?php echo getStatusClass($product['status'], 'product'); ?> fs-6">
                                    <?php echo getStatusTitle($product['status'], 'product'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="fw-bold text-muted">قیمت فروش</div>
                            <div class="h6 text-success"><?php echo formatMoney($product['price']); ?></div>
                        </div>
                        
                        <?php if ($product['cost_price'] > 0): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-muted">قیمت خرید/تولید</div>
                            <div class="h6 text-info"><?php echo formatMoney($product['cost_price']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($product['weight'] > 0): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-muted">وزن</div>
                            <div><?php echo number_format($product['weight']); ?> گرم</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($product['dimensions']): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-muted">ابعاد</div>
                            <div><?php echo htmlspecialchars($product['dimensions']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($product['description']): ?>
                <div class="mb-3">
                    <div class="fw-bold text-muted mb-2">توضیحات محصول</div>
                    <div class="bg-light p-3 rounded"><?php echo nl2br(htmlspecialchars($product['description'])); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($product['tags']): ?>
                <div class="mb-3">
                    <div class="fw-bold text-muted mb-2">برچسب‌ها</div>
                    <div>
                        <?php
                        $tags = explode(',', $product['tags']);
                        foreach ($tags as $tag): 
                            $tag = trim($tag);
                            if ($tag):
                        ?>
                            <span class="badge bg-info me-1"><?php echo htmlspecialchars($tag); ?></span>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="fw-bold text-muted">تاریخ ایجاد</div>
                            <div><?php echo formatPersianDate($product['created_at']); ?></div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <?php if ($product['created_user']): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-muted">ایجادکننده</div>
                            <div>
                                <i class="fas fa-user me-1"></i>
                                <?php echo htmlspecialchars($product['created_user']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($product['notes']): ?>
                <div>
                    <div class="fw-bold text-muted mb-2">یادداشت‌های داخلی</div>
                    <div class="alert alert-info">
                        <small><?php echo nl2br(htmlspecialchars($product['notes'])); ?></small>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- تب‌ها -->
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="productTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales" type="button">
                            <i class="fas fa-chart-line me-1"></i>
                            فروش‌ها (<?php echo count($recent_sales); ?>)
                        </button>
                    </li>
                    <?php if (!empty($related_products)): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="related-tab" data-bs-toggle="tab" data-bs-target="#related" type="button">
                            <i class="fas fa-boxes me-1"></i>
                            محصولات مرتبط (<?php echo count($related_products); ?>)
                        </button>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="actions-tab" data-bs-toggle="tab" data-bs-target="#actions" type="button">
                            <i class="fas fa-bolt me-1"></i>
                            اقدامات سریع
                        </button>
                    </li>
                </ul>
            </div>
            
            <div class="card-body">
                <div class="tab-content" id="productTabContent">
                    <!-- فروش‌ها -->
                    <div class="tab-pane fade show active" id="sales" role="tabpanel">
                        <?php if (empty($recent_sales)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                <p class="text-muted">هیچ فروشی برای این محصول ثبت نشده است</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>شماره فروش</th>
                                            <th>مشتری</th>
                                            <th>تعداد</th>
                                            <th>قیمت واحد</th>
                                            <th>مجموع</th>
                                            <th>تاریخ</th>
                                            <th>وضعیت</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_sales as $sale): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($sale['sale_number']); ?></span>
                                                </td>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($sale['customer_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($sale['customer_code']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo number_format($sale['quantity']); ?></span>
                                                </td>
                                                <td class="fw-bold"><?php echo formatMoney($sale['unit_price']); ?></td>
                                                <td class="fw-bold text-success"><?php echo formatMoney($sale['total_price']); ?></td>
                                                <td><?php echo formatPersianDate($sale['sale_date'], 'Y/m/d'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusClass($sale['status']); ?>">
                                                        <?php echo getStatusTitle($sale['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- محصولات مرتبط -->
                    <?php if (!empty($related_products)): ?>
                    <div class="tab-pane fade" id="related" role="tabpanel">
                        <div class="row">
                            <?php foreach ($related_products as $related): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card border">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <div class="product-icon me-3" style="width: 40px; height: 40px; background: var(--info-color); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white;">
                                                    <i class="fas fa-box"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold"><?php echo htmlspecialchars($related['name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($related['sku']); ?></small>
                                                    <div class="d-flex justify-content-between align-items-center mt-1">
                                                        <span class="text-success fw-bold"><?php echo formatMoney($related['price']); ?></span>
                                                        <span class="badge bg-<?php echo $related['stock_quantity'] > 0 ? 'success' : 'warning'; ?>">
                                                            <?php echo number_format($related['stock_quantity']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <a href="product_view.php?id=<?php echo $related['id']; ?>" class="btn btn-outline-primary btn-sm w-100">
                                                    <i class="fas fa-eye me-1"></i>
                                                    مشاهده
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- اقدامات سریع -->
                    <div class="tab-pane fade" id="actions" role="tabpanel">
                        <div class="row">
                            <?php if (hasPermission('edit_product')): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-primary">
                                    <div class="card-body text-center">
                                        <i class="fas fa-edit fa-2x text-primary mb-3"></i>
                                        <h6>ویرایش محصول</h6>
                                        <p class="text-muted small">ویرایش اطلاعات این محصول</p>
                                        <a href="product_form.php?id=<?php echo $product_id; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit me-1"></i>
                                            ویرایش
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (hasPermission('add_sale')): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-success">
                                    <div class="card-body text-center">
                                        <i class="fas fa-shopping-cart fa-2x text-success mb-3"></i>
                                        <h6>فروش سریع</h6>
                                        <p class="text-muted small">ثبت فروش برای این محصول</p>
                                        <a href="sale_form.php?product_id=<?php echo $product_id; ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-plus me-1"></i>
                                            فروش جدید
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (hasPermission('add_product')): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-info">
                                    <div class="card-body text-center">
                                        <i class="fas fa-copy fa-2x text-info mb-3"></i>
                                        <h6>کپی محصول</h6>
                                        <p class="text-muted small">ایجاد محصول جدید بر اساس این محصول</p>
                                        <a href="product_form.php?copy_from=<?php echo $product_id; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-copy me-1"></i>
                                            کپی محصول
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-md-6 mb-3">
                                <div class="card border-warning">
                                    <div class="card-body text-center">
                                        <i class="fas fa-print fa-2x text-warning mb-3"></i>
                                        <h6>چاپ برچسب</h6>
                                        <p class="text-muted small">چاپ برچسب قیمت و بارکد</p>
                                        <button type="button" class="btn btn-warning btn-sm" onclick="printLabel()">
                                            <i class="fas fa-print me-1"></i>
                                            چاپ برچسب
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function printLabel() {
    const labelContent = `
        <div style="text-align: center; padding: 20px; font-family: 'Vazirmatn', sans-serif;">
            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
            <p>کد: <?php echo htmlspecialchars($product['sku']); ?></p>
            <?php if ($product['barcode']): ?>
                <p>بارکد: <?php echo htmlspecialchars($product['barcode']); ?></p>
            <?php endif; ?>
            <h2 style="color: #28a745;"><?php echo formatMoney($product['price']); ?></h2>
            <?php if ($product['unit']): ?>
                <p>واحد: <?php echo htmlspecialchars($product['unit']); ?></p>
            <?php endif; ?>
        </div>
    `;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>برچسب محصول</title>
                <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
                <style>
                    body { font-family: 'Vazirmatn', sans-serif; direction: rtl; }
                </style>
            </head>
            <body>${labelContent}</body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>

<style>
@media print {
    .btn, .card-header, .breadcrumb, .d-flex.justify-content-between, .nav-tabs {
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

.nav-tabs .nav-link {
    border: none;
    color: var(--text-medium);
    background: transparent;
    border-radius: var(--border-radius-sm) var(--border-radius-sm) 0 0;
}

.nav-tabs .nav-link.active {
    background: var(--primary-ultralight);
    color: var(--primary-color);
    border-bottom: 2px solid var(--primary-color);
}

.nav-tabs .nav-link:hover {
    background: var(--bg-light);
    color: var(--primary-color);
}

.badge.fs-6 {
    font-size: 0.875rem !important;
}

.product-icon {
    flex-shrink: 0;
}

.card.border {
    border-color: var(--border-color) !important;
}

.card.border-primary {
    border-color: var(--primary-color) !important;
}

.card.border-success {
    border-color: var(--success-color) !important;
}

.card.border-info {
    border-color: var(--info-color) !important;
}

.card.border-warning {
    border-color: var(--warning-color) !important;
}

.font-monospace {
    font-family: 'Courier New', monospace;
}
</style>

<?php include __DIR__ . '/../private/footer.php'; ?>

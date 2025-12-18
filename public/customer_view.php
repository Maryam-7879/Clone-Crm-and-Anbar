<?php
$customer_id = (int)($_GET['id'] ?? 0);
$page_title = 'جزئیات مشتری';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'مشتریان', 'url' => 'customers.php'],
    ['title' => 'جزئیات مشتری']
];

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
if (!hasPermission('view_customers')) {
    setMessage('شما دسترسی لازم برای مشاهده این صفحه را ندارید', 'error');
    header('Location: customers.php');
    exit();
}

if (!$customer_id) {
    setMessage('شناسه مشتری معتبر نیست', 'error');
    header('Location: customers.php');
    exit();
}

// دریافت اطلاعات مشتری
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            CONCAT(u.first_name, ' ', u.last_name) as assigned_user,
            CONCAT(cb.first_name, ' ', cb.last_name) as created_user
        FROM customers c
        LEFT JOIN users u ON c.assigned_to = u.id
        LEFT JOIN users cb ON c.created_by = cb.id
        WHERE c.id = ?
    ");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        setMessage('مشتری یافت نشد', 'error');
        header('Location: customers.php');
        exit();
    }
    
    // فعالیت‌های مشتری
    $activities = $pdo->prepare("
        SELECT 
            ca.*,
            CONCAT(u.first_name, ' ', u.last_name) as created_user
        FROM customer_activities ca
        LEFT JOIN users u ON ca.created_by = u.id
        WHERE ca.customer_id = ?
        ORDER BY ca.activity_date DESC
        LIMIT 10
    ");
    $activities->execute([$customer_id]);
    $activities = $activities->fetchAll();
    
    // فروش‌های مشتری
    $sales = $pdo->prepare("
        SELECT 
            s.*,
            (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as items_count
        FROM sales s
        WHERE s.customer_id = ?
        ORDER BY s.created_at DESC
        LIMIT 5
    ");
    $sales->execute([$customer_id]);
    $sales = $sales->fetchAll();
    
    // لیدهای مرتبط (اگر ایمیل یکسان باشد)
    $leads = [];
    if ($customer['email']) {
        $leads_stmt = $pdo->prepare("
            SELECT * FROM leads 
            WHERE email = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $leads_stmt->execute([$customer['email']]);
        $leads = $leads_stmt->fetchAll();
    }
    
    // آمار مشتری
    $stats = [
        'total_sales' => $pdo->prepare("SELECT COALESCE(SUM(final_amount), 0) FROM sales WHERE customer_id = ? AND status != 'cancelled'"),
        'total_orders' => $pdo->prepare("SELECT COUNT(*) FROM sales WHERE customer_id = ?"),
        'total_activities' => $pdo->prepare("SELECT COUNT(*) FROM customer_activities WHERE customer_id = ?"),
        'last_order' => $pdo->prepare("SELECT MAX(created_at) FROM sales WHERE customer_id = ?")
    ];
    
    foreach ($stats as $key => $stmt) {
        $stmt->execute([$customer_id]);
        $stats[$key] = $stmt->fetchColumn();
    }
    
} catch (PDOException $e) {
    error_log("خطا در دریافت اطلاعات مشتری: " . $e->getMessage());
    setMessage('خطا در بارگذاری اطلاعات مشتری', 'error');
    header('Location: customers.php');
    exit();
}

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">جزئیات مشتری</h4>
        <p class="text-muted mb-0"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></p>
    </div>
    
    <div>
        <a href="customers.php" class="btn btn-outline-secondary me-2">
            <i class="fas fa-arrow-right me-2"></i>
            بازگشت
        </a>
        
        <?php if (hasPermission('edit_customer')): ?>
            <a href="customer_form.php?id=<?php echo $customer_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i>
                ویرایش مشتری
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- اطلاعات اصلی مشتری -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2 text-primary"></i>
                    اطلاعات شخصی
                </h5>
            </div>
            <div class="card-body text-center">
                <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                    <i class="fas fa-user"></i>
                </div>
                
                <h5 class="mb-1"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></h5>
                <p class="text-muted mb-3">
                    <span class="badge bg-<?php echo getStatusClass($customer['status']); ?>">
                        <?php echo getStatusTitle($customer['status']); ?>
                    </span>
                    <span class="badge bg-secondary ms-1">
                        <?php echo $customer['customer_type'] === 'company' ? 'حقوقی' : 'حقیقی'; ?>
                    </span>
                </p>
                
                <div class="row text-center">
                    <div class="col-4">
                        <div class="h4 text-primary"><?php echo formatMoney($stats['total_sales']); ?></div>
                        <small class="text-muted">کل خرید</small>
                    </div>
                    <div class="col-4">
                        <div class="h4 text-success"><?php echo number_format($stats['total_orders']); ?></div>
                        <small class="text-muted">تعداد سفارش</small>
                    </div>
                    <div class="col-4">
                        <div class="h4 text-info"><?php echo number_format($stats['total_activities']); ?></div>
                        <small class="text-muted">فعالیت‌ها</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- اطلاعات تماس -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-address-book me-2 text-primary"></i>
                    اطلاعات تماس
                </h5>
            </div>
            <div class="card-body">
                <?php if ($customer['email']): ?>
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-envelope text-primary me-3"></i>
                    <div>
                        <div class="fw-bold">ایمیل</div>
                        <a href="mailto:<?php echo $customer['email']; ?>" class="text-muted">
                            <?php echo htmlspecialchars($customer['email']); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($customer['mobile']): ?>
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-mobile-alt text-success me-3"></i>
                    <div>
                        <div class="fw-bold">موبایل</div>
                        <a href="tel:<?php echo $customer['mobile']; ?>" class="text-muted">
                            <?php echo formatPhone($customer['mobile']); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($customer['phone']): ?>
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-phone text-info me-3"></i>
                    <div>
                        <div class="fw-bold">تلفن ثابت</div>
                        <a href="tel:<?php echo $customer['phone']; ?>" class="text-muted">
                            <?php echo formatPhone($customer['phone']); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($customer['website']): ?>
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-globe text-warning me-3"></i>
                    <div>
                        <div class="fw-bold">وب‌سایت</div>
                        <a href="<?php echo $customer['website']; ?>" target="_blank" class="text-muted">
                            <?php echo htmlspecialchars($customer['website']); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($customer['address']): ?>
                <div class="d-flex align-items-start">
                    <i class="fas fa-map-marker-alt text-danger me-3 mt-1"></i>
                    <div>
                        <div class="fw-bold">آدرس</div>
                        <div class="text-muted"><?php echo nl2br(htmlspecialchars($customer['address'])); ?></div>
                        <?php if ($customer['city'] || $customer['state']): ?>
                            <small class="text-muted">
                                <?php echo $customer['city'] ? htmlspecialchars($customer['city']) : ''; ?>
                                <?php echo ($customer['city'] && $customer['state']) ? '، ' : ''; ?>
                                <?php echo $customer['state'] ? htmlspecialchars($customer['state']) : ''; ?>
                                <?php if ($customer['postal_code']): ?>
                                    <br>کد پستی: <?php echo htmlspecialchars($customer['postal_code']); ?>
                                <?php endif; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- جزئیات و فعالیت‌ها -->
    <div class="col-lg-8">
        <!-- اطلاعات شرکت -->
        <?php if ($customer['customer_type'] === 'company' && $customer['company_name']): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-building me-2 text-primary"></i>
                    اطلاعات شرکت
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="fw-bold text-muted">نام شرکت</div>
                            <div><?php echo htmlspecialchars($customer['company_name']); ?></div>
                        </div>
                    </div>
                    <?php if ($customer['industry']): ?>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="fw-bold text-muted">صنعت</div>
                            <div><?php echo htmlspecialchars($customer['industry']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- اطلاعات مدیریتی -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-cogs me-2 text-primary"></i>
                    اطلاعات مدیریتی
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="fw-bold text-muted">کد مشتری</div>
                            <div><span class="badge bg-secondary"><?php echo htmlspecialchars($customer['customer_code']); ?></span></div>
                        </div>
                        
                        <?php if ($customer['source']): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-muted">منبع</div>
                            <div><?php echo htmlspecialchars($customer['source']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($customer['assigned_user']): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-muted">مسئول</div>
                            <div>
                                <i class="fas fa-user-tie me-1"></i>
                                <?php echo htmlspecialchars($customer['assigned_user']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="fw-bold text-muted">تاریخ ثبت</div>
                            <div><?php echo formatPersianDate($customer['created_at']); ?></div>
                        </div>
                        
                        <?php if ($customer['created_user']): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-muted">ثبت‌کننده</div>
                            <div><?php echo htmlspecialchars($customer['created_user']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($stats['last_order']): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-muted">آخرین سفارش</div>
                            <div><?php echo formatPersianDate($stats['last_order']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($customer['tags']): ?>
                <div class="mb-3">
                    <div class="fw-bold text-muted mb-2">برچسب‌ها</div>
                    <div>
                        <?php
                        $tags = explode(',', $customer['tags']);
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
                
                <?php if ($customer['notes']): ?>
                <div>
                    <div class="fw-bold text-muted mb-2">یادداشت‌ها</div>
                    <div class="bg-light p-3 rounded"><?php echo nl2br(htmlspecialchars($customer['notes'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- تب‌ها -->
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="customerTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="activities-tab" data-bs-toggle="tab" data-bs-target="#activities" type="button">
                            <i class="fas fa-history me-1"></i>
                            فعالیت‌ها (<?php echo count($activities); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales" type="button">
                            <i class="fas fa-shopping-cart me-1"></i>
                            فروش‌ها (<?php echo count($sales); ?>)
                        </button>
                    </li>
                    <?php if (!empty($leads)): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="leads-tab" data-bs-toggle="tab" data-bs-target="#leads" type="button">
                            <i class="fas fa-bullseye me-1"></i>
                            لیدهای مرتبط (<?php echo count($leads); ?>)
                        </button>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="card-body">
                <div class="tab-content" id="customerTabContent">
                    <!-- فعالیت‌ها -->
                    <div class="tab-pane fade show active" id="activities" role="tabpanel">
                        <?php if (empty($activities)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <p class="text-muted">هیچ فعالیتی ثبت نشده است</p>
                                <?php if (hasPermission('add_task')): ?>
                                    <a href="task_form.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>
                                        ثبت فعالیت جدید
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($activities as $activity): ?>
                                    <div class="timeline-item mb-3">
                                        <div class="d-flex">
                                            <div class="timeline-icon me-3">
                                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="fas fa-<?php 
                                                        echo match($activity['activity_type']) {
                                                            'call' => 'phone',
                                                            'email' => 'envelope',
                                                            'meeting' => 'calendar',
                                                            'note' => 'sticky-note',
                                                            'purchase' => 'shopping-cart',
                                                            'support' => 'headset',
                                                            default => 'info'
                                                        };
                                                    ?> text-white"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($activity['subject']); ?></h6>
                                                        <p class="mb-1 text-muted"><?php echo htmlspecialchars($activity['description']); ?></p>
                                                        <?php if ($activity['outcome']): ?>
                                                            <small class="text-success">نتیجه: <?php echo htmlspecialchars($activity['outcome']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small class="text-muted"><?php echo formatPersianDate($activity['activity_date']); ?></small>
                                                </div>
                                                <?php if ($activity['created_user']): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($activity['created_user']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- فروش‌ها -->
                    <div class="tab-pane fade" id="sales" role="tabpanel">
                        <?php if (empty($sales)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                <p class="text-muted">هیچ سفارشی ثبت نشده است</p>
                                <?php if (hasPermission('add_sale')): ?>
                                    <a href="sale_form.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>
                                        ثبت سفارش جدید
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>شماره فروش</th>
                                            <th>مبلغ</th>
                                            <th>تعداد اقلام</th>
                                            <th>وضعیت</th>
                                            <th>تاریخ</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sales as $sale): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($sale['sale_number']); ?></span>
                                                </td>
                                                <td class="fw-bold text-success"><?php echo formatMoney($sale['final_amount']); ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo number_format($sale['items_count']); ?> قلم</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusClass($sale['status']); ?>">
                                                        <?php echo getStatusTitle($sale['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatPersianDate($sale['sale_date'], 'Y/m/d'); ?></td>
                                                <td>
                                                    <a href="sale_view.php?id=<?php echo $sale['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- لیدهای مرتبط -->
                    <?php if (!empty($leads)): ?>
                    <div class="tab-pane fade" id="leads" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>عنوان</th>
                                        <th>ارزش</th>
                                        <th>احتمال</th>
                                        <th>وضعیت</th>
                                        <th>تاریخ</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leads as $lead): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($lead['title']); ?></td>
                                            <td class="fw-bold text-success"><?php echo formatMoney($lead['value']); ?></td>
                                            <td>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar" style="width: <?php echo $lead['probability']; ?>%"></div>
                                                </div>
                                                <small><?php echo $lead['probability']; ?>%</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getStatusClass($lead['status'], 'lead'); ?>">
                                                    <?php echo getStatusTitle($lead['status'], 'lead'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatPersianDate($lead['created_at'], 'Y/m/d'); ?></td>
                                            <td>
                                                <a href="lead_view.php?id=<?php echo $lead['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline-item {
    position: relative;
}

.timeline-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: 19px;
    top: 50px;
    width: 2px;
    height: calc(100% - 10px);
    background: var(--border-color);
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
</style>

<?php include __DIR__ . '/../private/footer.php'; ?>

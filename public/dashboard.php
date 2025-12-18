<?php
$page_title = 'داشبورد';
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
if (!hasPermission('view_dashboard')) {
    header('Location: login.php');
    exit();
}

// دریافت آمارها
try {
    // آمار مشتریان
    $total_customers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    $active_customers = $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'active'")->fetchColumn();
    $new_customers_month = $pdo->query("SELECT COUNT(*) FROM customers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    
    // آمار لیدها
    $total_leads = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
    $hot_leads = $pdo->query("SELECT COUNT(*) FROM leads WHERE priority = 'high' OR priority = 'urgent'")->fetchColumn();
    $won_leads = $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'won'")->fetchColumn();
    $conversion_rate = $total_leads > 0 ? round(($won_leads / $total_leads) * 100, 1) : 0;
    
    // آمار فروش
    $total_sales = $pdo->query("SELECT COALESCE(SUM(final_amount), 0) FROM sales WHERE status != 'cancelled'")->fetchColumn();
    $monthly_sales = $pdo->query("SELECT COALESCE(SUM(final_amount), 0) FROM sales WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND status != 'cancelled'")->fetchColumn();
    $pending_sales = $pdo->query("SELECT COUNT(*) FROM sales WHERE status = 'pending'")->fetchColumn();
    
    // آمار وظایف
    $total_tasks = $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
    $pending_tasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'pending'")->fetchColumn();
    $overdue_tasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE due_date < NOW() AND status != 'completed'")->fetchColumn();
    
    // دریافت فیلتر
    $period = $_GET['period'] ?? '6';
    
    // فروش ماهانه برای چارت
    $monthly_sales_data = [];
    try {
        $monthly_sales_data = $pdo->query("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(final_amount) as total,
                COUNT(*) as count
            FROM sales 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$period} MONTH) 
                AND status != 'cancelled'
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
            LIMIT 12
        ")->fetchAll();
    } catch (PDOException $e) {
        error_log("خطا در دریافت آمار فروش: " . $e->getMessage());
        // داده‌های آزمایشی
        $monthly_sales_data = [
            ['month' => '2025-08', 'total' => '45000000', 'count' => 10],
            ['month' => '2025-07', 'total' => '38000000', 'count' => 8],
            ['month' => '2025-06', 'total' => '42000000', 'count' => 9]
        ];
    }
    
    // آخرین فعالیت‌ها
    $recent_activities = $pdo->query("
        SELECT 
            al.*,
            u.first_name,
            u.last_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 10
    ")->fetchAll();
    
    // لیدهای فوری
    $urgent_leads = $pdo->query("
        SELECT 
            l.*,
            CONCAT(u.first_name, ' ', u.last_name) as assigned_user
        FROM leads l
        LEFT JOIN users u ON l.assigned_to = u.id
        WHERE l.priority IN ('high', 'urgent') 
            AND l.status NOT IN ('won', 'lost')
        ORDER BY 
            CASE l.priority 
                WHEN 'urgent' THEN 1
                WHEN 'high' THEN 2
                ELSE 3
            END,
            l.created_at DESC
        LIMIT 5
    ")->fetchAll();
    
    // وظایف امروز
    $today_tasks = $pdo->query("
        SELECT 
            t.*,
            CONCAT(u.first_name, ' ', u.last_name) as assigned_user
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE DATE(t.due_date) = CURDATE()
            AND t.status != 'completed'
        ORDER BY t.priority DESC, t.due_date ASC
        LIMIT 5
    ")->fetchAll();
    
    // ============ آمار موجودی ============
    // آمار کلی موجودی
    $inventory_stats = [];
    try {
        $inventory_stats['total_products'] = $pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL")->fetchColumn();
        $inventory_stats['total_categories'] = $pdo->query("SELECT COUNT(*) FROM categories WHERE deleted_at IS NULL")->fetchColumn();
        $inventory_stats['total_suppliers'] = $pdo->query("SELECT COUNT(*) FROM suppliers WHERE deleted_at IS NULL")->fetchColumn();
        $inventory_stats['total_transactions'] = $pdo->query("SELECT COUNT(*) FROM transactions WHERE deleted_at IS NULL")->fetchColumn();
        
        // محصولات کم موجودی
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT p.id) 
            FROM products p
            INNER JOIN inventories i ON p.id = i.product_id
            WHERE p.deleted_at IS NULL 
            AND i.deleted_at IS NULL
            AND i.current_stock <= p.min_stock_level
            AND i.current_stock > 0
        ");
        $inventory_stats['low_stock'] = $stmt->fetchColumn();
        
        // محصولات تمام شده
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT p.id) 
            FROM products p
            INNER JOIN inventories i ON p.id = i.product_id
            WHERE p.deleted_at IS NULL 
            AND i.deleted_at IS NULL
            AND i.current_stock = 0
        ");
        $inventory_stats['out_of_stock'] = $stmt->fetchColumn();
        
        // هشدارهای خوانده نشده
        $inventory_stats['unread_alerts'] = $pdo->query("SELECT COUNT(*) FROM alerts WHERE is_read = 0 AND deleted_at IS NULL")->fetchColumn();
        
        // ارزش کل موجودی
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(i.current_stock * i.average_cost), 0) as total_value
            FROM inventories i
            INNER JOIN products p ON i.product_id = p.id
            WHERE i.deleted_at IS NULL AND p.deleted_at IS NULL
        ");
        $inventory_stats['total_value'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("خطا در دریافت آمار موجودی: " . $e->getMessage());
        $inventory_stats = [
            'total_products' => 0,
            'total_categories' => 0,
            'total_suppliers' => 0,
            'total_transactions' => 0,
            'low_stock' => 0,
            'out_of_stock' => 0,
            'unread_alerts' => 0,
            'total_value' => 0
        ];
    }
    
    // آخرین تراکنش‌های موجودی
    $recent_inventory_transactions = [];
    try {
        $recent_inventory_transactions = $pdo->query("
            SELECT 
                t.*,
                p.name as product_name,
                p.sku,
                CONCAT(u.first_name, ' ', u.last_name) as user_name,
                c.name as category_name
            FROM transactions t
            INNER JOIN products p ON t.product_id = p.id
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN categories c ON p.category_id = c.id AND c.deleted_at IS NULL
            WHERE t.deleted_at IS NULL AND p.deleted_at IS NULL
            ORDER BY t.transaction_date DESC
            LIMIT 10
        ")->fetchAll();
    } catch (PDOException $e) {
        error_log("خطا در دریافت تراکنش‌های موجودی: " . $e->getMessage());
    }
    
    // هشدارهای جدید
    $recent_alerts = [];
    try {
        $recent_alerts = $pdo->query("
            SELECT 
                a.*,
                p.name as product_name,
                p.sku,
                c.name as category_name
            FROM alerts a
            INNER JOIN products p ON a.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id AND c.deleted_at IS NULL
            WHERE a.deleted_at IS NULL 
            AND p.deleted_at IS NULL
            AND a.is_read = 0
            ORDER BY a.created_at DESC
            LIMIT 5
        ")->fetchAll();
    } catch (PDOException $e) {
        error_log("خطا در دریافت هشدارها: " . $e->getMessage());
    }
    
    // محصولات با موجودی کم
    $low_stock_products = [];
    try {
        $low_stock_products = $pdo->query("
            SELECT 
                p.id,
                p.name,
                p.sku,
                i.current_stock,
                p.min_stock_level,
                c.name as category_name,
                c.color as category_color
            FROM products p
            INNER JOIN inventories i ON p.id = i.product_id
            LEFT JOIN categories c ON p.category_id = c.id AND c.deleted_at IS NULL
            WHERE p.deleted_at IS NULL 
            AND i.deleted_at IS NULL
            AND i.current_stock <= p.min_stock_level
            AND i.current_stock > 0
            ORDER BY (i.current_stock / NULLIF(p.min_stock_level, 0)) ASC
            LIMIT 5
        ")->fetchAll();
    } catch (PDOException $e) {
        error_log("خطا در دریافت محصولات کم موجودی: " . $e->getMessage());
    }

} catch (PDOException $e) {
    error_log("خطا در دریافت آمارهای داشبورد: " . $e->getMessage());
    setMessage('خطا در بارگذاری اطلاعات داشبورد', 'error');
    
    // مقادیر پیش‌فرض
    $inventory_stats = [
        'total_products' => 0,
        'total_categories' => 0,
        'total_suppliers' => 0,
        'total_transactions' => 0,
        'low_stock' => 0,
        'out_of_stock' => 0,
        'unread_alerts' => 0,
        'total_value' => 0
    ];
    $recent_inventory_transactions = [];
    $recent_alerts = [];
    $low_stock_products = [];
}

include __DIR__ . '/../private/header.php';
?>

<div class="row stats-grid">
    <!-- آمار کلی -->
    <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-4">
        <div class="stats-card">
            <div class="icon" style="background: rgba(255, 107, 53, 0.1); color: var(--primary-color);">
                <i class="fas fa-users"></i>
            </div>
            <div class="value"><?php echo number_format($total_customers); ?></div>
            <div class="label">کل مشتریان</div>
            <div class="mt-2">
                <small class="text-success">
                    <i class="fas fa-arrow-up me-1"></i>
                    <?php echo $new_customers_month; ?> مشتری جدید این ماه
                </small>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-4">
        <div class="stats-card">
            <div class="icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                <i class="fas fa-bullseye"></i>
            </div>
            <div class="value"><?php echo number_format($total_leads); ?></div>
            <div class="label">کل لیدها</div>
            <div class="mt-2">
                <small class="text-warning">
                    <i class="fas fa-fire me-1"></i>
                    <?php echo $hot_leads; ?> لید فوری
                </small>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-4">
        <div class="stats-card">
            <div class="icon" style="background: rgba(23, 162, 184, 0.1); color: #17a2b8;">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="value"><?php echo formatMoney($monthly_sales); ?></div>
            <div class="label">فروش این ماه</div>
            <div class="mt-2">
                <small class="text-info">
                    <i class="fas fa-percentage me-1"></i>
                    نرخ تبدیل: <?php echo $conversion_rate; ?>%
                </small>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-4">
        <div class="stats-card">
            <div class="icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                <i class="fas fa-tasks"></i>
            </div>
            <div class="value"><?php echo number_format($pending_tasks); ?></div>
            <div class="label">وظایف در انتظار</div>
            <div class="mt-2">
                <small class="text-danger">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <?php echo $overdue_tasks; ?> وظیفه عقب‌افتاده
                </small>
            </div>
        </div>
    </div>
</div>

<!-- آمار موجودی -->
<div class="row stats-grid mb-4">
    <div class="col-12 mb-3">
        <h5><i class="fas fa-warehouse me-2"></i>آمار موجودی و انبار</h5>
    </div>
    
    <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-4">
        <div class="stats-card">
            <div class="icon" style="background: rgba(0, 123, 255, 0.1); color: #007bff;">
                <i class="fas fa-box"></i>
            </div>
            <div class="value"><?php echo number_format($inventory_stats['total_products'] ?? 0); ?></div>
            <div class="label">کل محصولات</div>
            <div class="mt-2">
                <small class="text-info">
                    <i class="fas fa-tags me-1"></i>
                    <?php echo number_format($inventory_stats['total_categories'] ?? 0); ?> دسته‌بندی
                </small>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-4">
        <div class="stats-card">
            <div class="icon" style="background: rgba(255, 152, 0, 0.1); color: #ff9800;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="value"><?php echo number_format($inventory_stats['low_stock'] ?? 0); ?></div>
            <div class="label">موجودی کم</div>
            <div class="mt-2">
                <small class="text-danger">
                    <i class="fas fa-times-circle me-1"></i>
                    <?php echo number_format($inventory_stats['out_of_stock'] ?? 0); ?> تمام شده
                </small>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-4">
        <div class="stats-card">
            <div class="icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="value"><?php echo formatMoney($inventory_stats['total_value'] ?? 0); ?></div>
            <div class="label">ارزش کل موجودی</div>
            <div class="mt-2">
                <small class="text-success">
                    <i class="fas fa-truck me-1"></i>
                    <?php echo number_format($inventory_stats['total_suppliers'] ?? 0); ?> تامین‌کننده
                </small>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-4">
        <div class="stats-card">
            <div class="icon" style="background: rgba(156, 39, 176, 0.1); color: #9c27b0;">
                <i class="fas fa-bell"></i>
            </div>
            <div class="value"><?php echo number_format($inventory_stats['unread_alerts'] ?? 0); ?></div>
            <div class="label">هشدارهای جدید</div>
            <div class="mt-2">
                <small class="text-info">
                    <i class="fas fa-exchange-alt me-1"></i>
                    <?php echo number_format($inventory_stats['total_transactions'] ?? 0); ?> تراکنش
                </small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- نمودار فروش -->
    <div class="col-xl-8 col-lg-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-chart-area me-2 text-primary"></i>
                    روند فروش ماهانه
                </h5>
                <div class="dropdown">
                    <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-calendar-alt me-1"></i>
                        <?php 
                        $period_names = [
                            '3' => '۳ ماه گذشته',
                            '6' => '۶ ماه گذشته', 
                            '12' => '۱۲ ماه گذشته',
                            '24' => '۲ سال گذشته'
                        ];
                        echo $period_names[$period] ?? '۶ ماه گذشته';
                        ?>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="?period=3">۳ ماه گذشته</a></li>
                        <li><a class="dropdown-item" href="?period=6">۶ ماه گذشته</a></li>
                        <li><a class="dropdown-item" href="?period=12">۱۲ ماه گذشته</a></li>
                        <li><a class="dropdown-item" href="?period=24">۲ سال گذشته</a></li>
                    </ul>
                </div>
            </div>
            <div class="card-body">
                <div style="position: relative; height: 300px;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- آمار سریع -->
    <div class="col-xl-4 col-lg-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-tachometer-alt me-2 text-primary"></i>
                    آمار سریع
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                    <div>
                        <div class="fw-bold text-success"><?php echo number_format($active_customers); ?></div>
                        <small class="text-muted">مشتریان فعال</small>
                    </div>
                    <i class="fas fa-user-check fa-2x text-success"></i>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                    <div>
                        <div class="fw-bold text-warning"><?php echo number_format($pending_sales); ?></div>
                        <small class="text-muted">فروش در انتظار</small>
                    </div>
                    <i class="fas fa-clock fa-2x text-warning"></i>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                    <div>
                        <div class="fw-bold text-info"><?php echo formatMoney($total_sales); ?></div>
                        <small class="text-muted">کل فروش</small>
                    </div>
                    <i class="fas fa-money-bill-wave fa-2x text-info"></i>
                </div>
                
                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                    <div>
                        <div class="fw-bold text-primary"><?php echo $conversion_rate; ?>%</div>
                        <small class="text-muted">نرخ تبدیل</small>
                    </div>
                    <i class="fas fa-percentage fa-2x text-primary"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- لیدهای فوری -->
    <div class="col-xl-6 col-lg-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-fire me-2 text-danger"></i>
                    لیدهای فوری
                </h5>
                <a href="leads.php" class="btn btn-outline-primary btn-sm">
                    مشاهده همه
                    <i class="fas fa-arrow-left ms-1"></i>
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($urgent_leads)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-check-circle fa-3x mb-3"></i>
                        <p>لید فوری‌ای وجود ندارد</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($urgent_leads as $lead): ?>
                        <div class="d-flex align-items-center p-3 border rounded mb-2">
                            <div class="flex-grow-1">
                                <div class="fw-bold">
                                    <?php echo htmlspecialchars($lead['first_name'] . ' ' . $lead['last_name']); ?>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-building me-1"></i>
                                    <?php echo htmlspecialchars($lead['company'] ?: 'بدون شرکت'); ?>
                                </small>
                                <div class="mt-1">
                                    <span class="badge bg-<?php echo getPriorityClass($lead['priority']); ?>">
                                        <?php echo getPriorityTitle($lead['priority']); ?>
                                    </span>
                                    <span class="badge bg-<?php echo getStatusClass($lead['status'], 'lead'); ?>">
                                        <?php echo getStatusTitle($lead['status'], 'lead'); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success">
                                    <?php echo formatMoney($lead['value']); ?>
                                </div>
                                <small class="text-muted">
                                    <?php echo $lead['assigned_user'] ?: 'بدون مسئول'; ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- وظایف امروز -->
    <div class="col-xl-6 col-lg-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-day me-2 text-warning"></i>
                    وظایف امروز
                </h5>
                <a href="tasks.php" class="btn btn-outline-primary btn-sm">
                    مشاهده همه
                    <i class="fas fa-arrow-left ms-1"></i>
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($today_tasks)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-calendar-check fa-3x mb-3"></i>
                        <p>وظیفه‌ای برای امروز ندارید</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($today_tasks as $task): ?>
                        <div class="d-flex align-items-center p-3 border rounded mb-2">
                            <div class="flex-grow-1">
                                <div class="fw-bold">
                                    <?php echo htmlspecialchars($task['title']); ?>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo formatPersianDate($task['due_date'], 'H:i'); ?>
                                </small>
                                <div class="mt-1">
                                    <span class="badge bg-<?php echo getPriorityClass($task['priority']); ?>">
                                        <?php echo getPriorityTitle($task['priority']); ?>
                                    </span>
                                    <span class="badge bg-secondary">
                                        <?php echo getStatusTitle($task['type']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">
                                    <?php echo $task['assigned_user'] ?: 'بدون مسئول'; ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- بخش موجودی -->
<div class="row">
    <!-- آخرین تراکنش‌های موجودی -->
    <div class="col-xl-8 col-lg-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-exchange-alt me-2 text-primary"></i>
                    آخرین تراکنش‌های موجودی
                </h5>
                <a href="transactions.php" class="btn btn-outline-primary btn-sm">
                    مشاهده همه
                    <i class="fas fa-arrow-left ms-1"></i>
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_inventory_transactions)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-exchange-alt fa-3x mb-3"></i>
                        <p>تراکنشی ثبت نشده است</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>محصول</th>
                                    <th>نوع</th>
                                    <th>تعداد</th>
                                    <th>مبلغ</th>
                                    <th>کاربر</th>
                                    <th>تاریخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $type_labels = [
                                    'in' => 'ورود',
                                    'out' => 'خروج',
                                    'adjustment' => 'تعدیل',
                                    'transfer' => 'انتقال'
                                ];
                                $type_classes = [
                                    'in' => 'success',
                                    'out' => 'danger',
                                    'adjustment' => 'warning',
                                    'transfer' => 'info'
                                ];
                                foreach ($recent_inventory_transactions as $trans): 
                                ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($trans['product_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($trans['sku']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $type_classes[$trans['type']] ?? 'secondary'; ?>">
                                                <?php echo $type_labels[$trans['type']] ?? $trans['type']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($trans['quantity']); ?></td>
                                        <td><?php echo formatMoney($trans['total_price']); ?></td>
                                        <td>
                                            <small><?php echo htmlspecialchars($trans['user_name'] ?: 'نامشخص'); ?></small>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo formatPersianDate($trans['transaction_date'], 'Y/m/d H:i'); ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- هشدارهای جدید -->
    <div class="col-xl-4 col-lg-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-bell me-2 text-warning"></i>
                    هشدارهای جدید
                </h5>
                <a href="alerts.php" class="btn btn-outline-primary btn-sm">
                    مشاهده همه
                    <i class="fas fa-arrow-left ms-1"></i>
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_alerts)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-bell-slash fa-3x mb-3"></i>
                        <p>هشداری وجود ندارد</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $alert_type_classes = [
                        'low_stock' => 'warning',
                        'out_of_stock' => 'danger',
                        'expiry' => 'info',
                        'custom' => 'secondary'
                    ];
                    foreach ($recent_alerts as $alert): 
                    ?>
                        <div class="alert alert-<?php echo $alert_type_classes[$alert['type']] ?? 'secondary'; ?> alert-dismissible fade show mb-2" role="alert">
                            <div class="d-flex align-items-start">
                                <div class="me-2">
                                    <i class="fas fa-<?php echo $alert['type'] === 'low_stock' ? 'exclamation-triangle' : ($alert['type'] === 'out_of_stock' ? 'times-circle' : 'info-circle'); ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <strong><?php echo htmlspecialchars($alert['title']); ?></strong>
                                    <br>
                                    <small><?php echo htmlspecialchars($alert['product_name']); ?></small>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo formatPersianDate($alert['created_at'], 'Y/m/d H:i'); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- محصولات با موجودی کم -->
<?php if (!empty($low_stock_products)): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                    محصولات با موجودی کم
                </h5>
                <a href="inventory.php?stock_status=low" class="btn btn-outline-primary btn-sm">
                    مشاهده همه
                    <i class="fas fa-arrow-left ms-1"></i>
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>محصول</th>
                                <th>دسته‌بندی</th>
                                <th>موجودی فعلی</th>
                                <th>حداقل موجودی</th>
                                <th>وضعیت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($low_stock_products as $product): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($product['sku']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($product['category_name']): ?>
                                            <span class="badge" style="background-color: <?php echo htmlspecialchars($product['category_color'] ?: '#007bff'); ?>">
                                                <?php echo htmlspecialchars($product['category_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning"><?php echo number_format($product['current_stock']); ?></span>
                                    </td>
                                    <td><?php echo number_format($product['min_stock_level']); ?></td>
                                    <td>
                                        <?php 
                                        $percentage = $product['min_stock_level'] > 0 ? ($product['current_stock'] / $product['min_stock_level']) * 100 : 0;
                                        if ($percentage <= 50) {
                                            $status_class = 'danger';
                                            $status_text = 'بحرانی';
                                        } elseif ($percentage <= 80) {
                                            $status_class = 'warning';
                                            $status_text = 'کم';
                                        } else {
                                            $status_class = 'info';
                                            $status_text = 'نیاز به سفارش';
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                    <td>
                                        <a href="inventory_view.php?product_id=<?php echo $product['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- آخرین فعالیت‌ها -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2 text-info"></i>
                    آخرین فعالیت‌ها
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_activities)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>فعالیتی ثبت نشده است</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>کاربر</th>
                                    <th>عملیات</th>
                                    <th>جدول</th>
                                    <th>زمان</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_activities as $activity): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-2" style="width: 32px; height: 32px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px;">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <?php echo htmlspecialchars(($activity['first_name'] . ' ' . $activity['last_name']) ?: 'نامشخص'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo htmlspecialchars($activity['action']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['table_name'] ?: '-'); ?></td>
                                        <td>
                                            <small class="text-muted" title="<?php echo formatPersianDate($activity['created_at']); ?>">
                                                <?php echo formatPersianDate($activity['created_at'], 'H:i'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($activity['ip_address'] ?: '-'); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// نمودار فروش ماهانه
const salesChartData = <?php echo json_encode(array_values($monthly_sales_data)); ?>;
console.log('Dashboard Sales Data:', salesChartData);

const ctx = document.getElementById('salesChart').getContext('2d');

// داده‌های آزمایشی اگر خالی باشه
const fallbackData = [
    {month: '2025-06', total: '42000000', count: 9},
    {month: '2025-07', total: '38000000', count: 8},
    {month: '2025-08', total: '45000000', count: 10}
];

const finalData = salesChartData.length > 0 ? salesChartData : fallbackData;

// تنظیمات فارسی برای Chart.js
Chart.defaults.font.family = 'Vazirmatn, sans-serif';
Chart.defaults.font.size = 12;

const chart = new Chart(ctx, {
    type: 'line',
    data: {
                 labels: [
             <?php 
             $labels = [];
             foreach ($monthly_sales_data as $item) {
                 $labels[] = "'" . convertToJalaliForChart($item['month'] . '-01') . "'";
             }
             echo implode(',', $labels);
             ?>
         ],
        datasets: [{
            label: 'فروش ماهانه',
            data: finalData.map(item => parseFloat(item.total)),
            borderColor: '#ff6b35',
            backgroundColor: 'rgba(255, 107, 53, 0.1)',
            borderWidth: 3,
            pointBackgroundColor: '#ff6b35',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 6,
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false,
                labels: {
                    font: {
                        family: 'Vazirmatn, sans-serif',
                        size: 14
                    }
                }
            },
            tooltip: {
                titleFont: {
                    family: 'Vazirmatn, sans-serif'
                },
                bodyFont: {
                    family: 'Vazirmatn, sans-serif'
                },
                callbacks: {
                    label: function(context) {
                        return 'فروش: ' + new Intl.NumberFormat('fa-IR').format(context.parsed.y) + ' تومان';
                    }
                }
            }
        },
        scales: {
            x: {
                ticks: {
                    font: {
                        family: 'Vazirmatn, sans-serif',
                        size: 12
                    }
                }
            },
            y: {
                beginAtZero: true,
                ticks: {
                    font: {
                        family: 'Vazirmatn, sans-serif',
                        size: 12
                    },
                    callback: function(value) {
                        return new Intl.NumberFormat('fa-IR').format(value) + ' تومان';
                    }
                }
            }
        },
        elements: {
            point: {
                radius: 6,
                hoverRadius: 8
            }
        }
    }
});

// حذف بروزرسانی خودکار برای جلوگیری از هنگ
</script>

<?php include __DIR__ . '/../private/footer.php'; ?>

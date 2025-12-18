<?php
$user_id = (int)($_GET['id'] ?? 0);
$page_title = 'جزئیات کاربر';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'کاربران', 'url' => 'users.php'],
    ['title' => 'جزئیات کاربر']
];

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
if (!hasPermission('view_users')) {
    setMessage('شما دسترسی لازم برای مشاهده این صفحه را ندارید', 'error');
    header('Location: users.php');
    exit();
}

if (!$user_id) {
    setMessage('شناسه کاربر معتبر نیست', 'error');
    header('Location: users.php');
    exit();
}

// دریافت اطلاعات کاربر
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        setMessage('کاربر یافت نشد', 'error');
        header('Location: users.php');
        exit();
    }
    
    // کاربران معمولی نمی‌توانند ادمین‌ها را مشاهده کنند
    if ($_SESSION['user_role'] !== 'admin' && $user['role'] === 'admin') {
        setMessage('شما مجاز به مشاهده این کاربر نیستید', 'error');
        header('Location: users.php');
        exit();
    }
    
    // آمار فعالیت کاربر
    $stats = [
        'created_customers' => $pdo->prepare("SELECT COUNT(*) FROM customers WHERE created_by = ?"),
        'created_leads' => $pdo->prepare("SELECT COUNT(*) FROM leads WHERE created_by = ?"),
        'assigned_tasks' => $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ?"),
        'completed_tasks' => $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'completed'"),
        'created_sales' => $pdo->prepare("SELECT COUNT(*) FROM sales WHERE created_by = ?"),
        'total_sales_amount' => $pdo->prepare("SELECT COALESCE(SUM(final_amount), 0) FROM sales WHERE created_by = ? AND status != 'cancelled'")
    ];
    
    foreach ($stats as $key => $stmt) {
        $stmt->execute([$user_id]);
        $stats[$key] = $stmt->fetchColumn();
    }
    
    // آخرین فعالیت‌ها
    $recent_activities = $pdo->prepare("
        SELECT 
            al.*
        FROM activity_logs al
        WHERE al.user_id = ?
        ORDER BY al.created_at DESC
        LIMIT 10
    ");
    $recent_activities->execute([$user_id]);
    $recent_activities = $recent_activities->fetchAll();
    
    // وظایف اختصاص یافته
    $tasks = $pdo->prepare("
        SELECT 
            t.*,
            CASE 
                WHEN t.related_type = 'customer' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM customers WHERE id = t.related_id)
                WHEN t.related_type = 'lead' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM leads WHERE id = t.related_id)
                ELSE NULL
            END as related_name
        FROM tasks t
        WHERE t.assigned_to = ?
        ORDER BY 
            CASE t.status
                WHEN 'pending' THEN 1
                WHEN 'in_progress' THEN 2
                WHEN 'completed' THEN 3
                WHEN 'cancelled' THEN 4
            END,
            t.due_date ASC
        LIMIT 10
    ");
    $tasks->execute([$user_id]);
    $tasks = $tasks->fetchAll();
    
    // مشتریان ایجاد شده
    $customers = $pdo->prepare("
        SELECT 
            c.id, c.first_name, c.last_name, c.customer_code, c.email, c.created_at, c.status
        FROM customers c
        WHERE c.created_by = ?
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
    $customers->execute([$user_id]);
    $customers = $customers->fetchAll();
    
} catch (PDOException $e) {
    error_log("خطا در دریافت اطلاعات کاربر: " . $e->getMessage());
    setMessage('خطا در بارگذاری اطلاعات کاربر', 'error');
    header('Location: users.php');
    exit();
}

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">جزئیات کاربر</h4>
        <p class="text-muted mb-0">
            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
            <span class="badge bg-<?php echo getRoleClass($user['role']); ?> ms-2">
                <?php echo getRoleTitle($user['role']); ?>
            </span>
            <span class="badge bg-<?php echo getStatusClass($user['status'], 'user'); ?> ms-1">
                <?php echo getStatusTitle($user['status'], 'user'); ?>
            </span>
        </p>
    </div>
    
    <div>
        <a href="users.php" class="btn btn-outline-secondary me-2">
            <i class="fas fa-arrow-right me-2"></i>
            بازگشت
        </a>
        
        <div class="btn-group" role="group">
            <?php if (hasPermission('edit_user')): ?>
                <a href="user_form.php?id=<?php echo $user_id; ?>" class="btn btn-primary">
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
    <!-- اطلاعات اصلی کاربر -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2 text-primary"></i>
                    اطلاعات شخصی
                </h5>
            </div>
            <div class="card-body text-center">
                <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; background: var(--<?php echo getRoleClass($user['role']); ?>-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                    <i class="fas fa-user"></i>
                </div>
                
                <h5 class="mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                <p class="text-muted mb-3">
                    <span class="badge bg-<?php echo getRoleClass($user['role']); ?> fs-6">
                        <?php echo getRoleTitle($user['role']); ?>
                    </span>
                </p>
                
                <div class="row text-center">
                    <div class="col-4">
                        <div class="h5 text-primary"><?php echo number_format($stats['created_customers']); ?></div>
                        <small class="text-muted">مشتری</small>
                    </div>
                    <div class="col-4">
                        <div class="h5 text-info"><?php echo number_format($stats['created_leads']); ?></div>
                        <small class="text-muted">لید</small>
                    </div>
                    <div class="col-4">
                        <div class="h5 text-success"><?php echo number_format($stats['created_sales']); ?></div>
                        <small class="text-muted">فروش</small>
                    </div>
                </div>
                
                <?php if ($stats['total_sales_amount'] > 0): ?>
                <div class="mt-3">
                    <div class="h6 text-success"><?php echo formatMoney($stats['total_sales_amount']); ?></div>
                    <small class="text-muted">کل فروش</small>
                </div>
                <?php endif; ?>
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
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-envelope text-primary me-3"></i>
                    <div>
                        <div class="fw-bold">ایمیل</div>
                        <a href="mailto:<?php echo $user['email']; ?>" class="text-decoration-none">
                            <?php echo htmlspecialchars($user['email']); ?>
                        </a>
                    </div>
                </div>
                
                <?php if ($user['mobile']): ?>
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-mobile-alt text-success me-3"></i>
                    <div>
                        <div class="fw-bold">موبایل</div>
                        <a href="tel:<?php echo $user['mobile']; ?>" class="text-decoration-none">
                            <?php echo formatPhone($user['mobile']); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($user['phone']): ?>
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-phone text-info me-3"></i>
                    <div>
                        <div class="fw-bold">تلفن ثابت</div>
                        <a href="tel:<?php echo $user['phone']; ?>" class="text-decoration-none">
                            <?php echo formatPhone($user['phone']); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($user['address']): ?>
                <div class="d-flex align-items-start">
                    <i class="fas fa-map-marker-alt text-danger me-3 mt-1"></i>
                    <div>
                        <div class="fw-bold">آدرس</div>
                        <div class="text-muted"><?php echo nl2br(htmlspecialchars($user['address'])); ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- اطلاعات شغلی -->
        <?php if ($user['department'] || $user['position'] || $user['hire_date'] || $user['salary']): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-briefcase me-2 text-primary"></i>
                    اطلاعات شغلی
                </h5>
            </div>
            <div class="card-body">
                <?php if ($user['department']): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">بخش:</span>
                    <span class="fw-bold"><?php echo htmlspecialchars($user['department']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($user['position']): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">سمت:</span>
                    <span class="fw-bold"><?php echo htmlspecialchars($user['position']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($user['hire_date']): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">تاریخ استخدام:</span>
                    <span class="fw-bold"><?php echo formatPersianDate($user['hire_date'], 'Y/m/d'); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($user['salary'] > 0): ?>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted">حقوق:</span>
                    <span class="fw-bold text-success"><?php echo formatMoney($user['salary']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- جزئیات و فعالیت‌ها -->
    <div class="col-lg-8">
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
                            <div class="fw-bold text-muted">شناسه کاربر</div>
                            <div><span class="badge bg-secondary">#<?php echo $user['id']; ?></span></div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="fw-bold text-muted">نقش سیستمی</div>
                            <div>
                                <span class="badge bg-<?php echo getRoleClass($user['role']); ?> fs-6">
                                    <?php echo getRoleTitle($user['role']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="fw-bold text-muted">وضعیت حساب</div>
                            <div>
                                <span class="badge bg-<?php echo getStatusClass($user['status'], 'user'); ?> fs-6">
                                    <?php echo getStatusTitle($user['status'], 'user'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="fw-bold text-muted">تاریخ عضویت</div>
                            <div><?php echo formatPersianDate($user['created_at']); ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="fw-bold text-muted">آخرین بروزرسانی</div>
                            <div><?php echo formatPersianDate($user['updated_at'] ?? $user['created_at']); ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="fw-bold text-muted">مدت عضویت</div>
                            <div>
                                <?php 
                                $created = new DateTime($user['created_at']);
                                $now = new DateTime();
                                $diff = $created->diff($now);
                                
                                if ($diff->days > 365) {
                                    echo $diff->y . ' سال و ' . floor($diff->days % 365 / 30) . ' ماه';
                                } elseif ($diff->days > 30) {
                                    echo floor($diff->days / 30) . ' ماه و ' . ($diff->days % 30) . ' روز';
                                } else {
                                    echo $diff->days . ' روز';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($user['notes']): ?>
                <div>
                    <div class="fw-bold text-muted mb-2">یادداشت‌های مدیریتی</div>
                    <div class="alert alert-info">
                        <small><?php echo nl2br(htmlspecialchars($user['notes'])); ?></small>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- آمار عملکرد -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2 text-primary"></i>
                    آمار عملکرد
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 col-6 mb-3">
                        <div class="text-center">
                            <div class="h4 text-primary"><?php echo number_format($stats['created_customers']); ?></div>
                            <small class="text-muted">مشتری ثبت شده</small>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-6 mb-3">
                        <div class="text-center">
                            <div class="h4 text-info"><?php echo number_format($stats['created_leads']); ?></div>
                            <small class="text-muted">لید ثبت شده</small>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-6 mb-3">
                        <div class="text-center">
                            <div class="h4 text-warning"><?php echo number_format($stats['assigned_tasks']); ?></div>
                            <small class="text-muted">وظیفه محول شده</small>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-6 mb-3">
                        <div class="text-center">
                            <div class="h4 text-success"><?php echo number_format($stats['completed_tasks']); ?></div>
                            <small class="text-muted">وظیفه تکمیل شده</small>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="text-center">
                            <div class="h4 text-success"><?php echo number_format($stats['created_sales']); ?></div>
                            <small class="text-muted">تعداد فروش</small>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="text-center">
                            <div class="h4 text-success"><?php echo formatMoney($stats['total_sales_amount']); ?></div>
                            <small class="text-muted">مجموع فروش</small>
                        </div>
                    </div>
                </div>
                
                <?php if ($stats['assigned_tasks'] > 0): ?>
                <div class="mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>نرخ تکمیل وظایف:</span>
                        <span class="fw-bold">
                            <?php echo round(($stats['completed_tasks'] / $stats['assigned_tasks']) * 100, 1); ?>%
                        </span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" 
                             style="width: <?php echo ($stats['completed_tasks'] / $stats['assigned_tasks']) * 100; ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- تب‌ها -->
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="userTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tasks-tab" data-bs-toggle="tab" data-bs-target="#tasks" type="button">
                            <i class="fas fa-tasks me-1"></i>
                            وظایف (<?php echo count($tasks); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="customers-tab" data-bs-toggle="tab" data-bs-target="#customers" type="button">
                            <i class="fas fa-users me-1"></i>
                            مشتریان (<?php echo count($customers); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="activities-tab" data-bs-toggle="tab" data-bs-target="#activities" type="button">
                            <i class="fas fa-history me-1"></i>
                            فعالیت‌ها (<?php echo count($recent_activities); ?>)
                        </button>
                    </li>
                </ul>
            </div>
            
            <div class="card-body">
                <div class="tab-content" id="userTabContent">
                    <!-- وظایف -->
                    <div class="tab-pane fade show active" id="tasks" role="tabpanel">
                        <?php if (empty($tasks)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                                <p class="text-muted">هیچ وظیفه‌ای به این کاربر اختصاص داده نشده است</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>عنوان</th>
                                            <th>نوع</th>
                                            <th>مرتبط با</th>
                                            <th>وضعیت</th>
                                            <th>سررسید</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tasks as $task): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($task['title']); ?></div>
                                                    <?php if ($task['description']): ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars(substr($task['description'], 0, 50)) . (strlen($task['description']) > 50 ? '...' : ''); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php 
                                                        $types = ['call' => 'تماس', 'email' => 'ایمیل', 'meeting' => 'جلسه', 'follow_up' => 'پیگیری', 'other' => 'سایر'];
                                                        echo $types[$task['type']] ?? $task['type']; 
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($task['related_name']): ?>
                                                        <small><?php echo htmlspecialchars($task['related_name']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusClass($task['status'], 'task'); ?>">
                                                        <?php echo getStatusTitle($task['status'], 'task'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($task['due_date']): ?>
                                                        <?php 
                                                        $is_overdue = strtotime($task['due_date']) < time() && !in_array($task['status'], ['completed', 'cancelled']);
                                                        ?>
                                                        <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                                            <?php echo formatPersianDate($task['due_date'], 'Y/m/d'); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="task_view.php?id=<?php echo $task['id']; ?>" class="btn btn-outline-primary btn-sm">
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
                    
                    <!-- مشتریان -->
                    <div class="tab-pane fade" id="customers" role="tabpanel">
                        <?php if (empty($customers)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">این کاربر هیچ مشتری ثبت نکرده است</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>نام مشتری</th>
                                            <th>کد مشتری</th>
                                            <th>ایمیل</th>
                                            <th>وضعیت</th>
                                            <th>تاریخ ثبت</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($customers as $customer): ?>
                                            <tr>
                                                <td class="fw-bold"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($customer['customer_code']); ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($customer['email']): ?>
                                                        <a href="mailto:<?php echo $customer['email']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($customer['email']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusClass($customer['status']); ?>">
                                                        <?php echo getStatusTitle($customer['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatPersianDate($customer['created_at'], 'Y/m/d'); ?></td>
                                                <td>
                                                    <a href="customer_view.php?id=<?php echo $customer['id']; ?>" class="btn btn-outline-primary btn-sm">
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
                    
                    <!-- فعالیت‌ها -->
                    <div class="tab-pane fade" id="activities" role="tabpanel">
                        <?php if (empty($recent_activities)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <p class="text-muted">هیچ فعالیتی ثبت نشده است</p>
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($recent_activities as $activity): ?>
                                    <div class="timeline-item mb-3">
                                        <div class="d-flex">
                                            <div class="timeline-icon me-3">
                                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="fas fa-<?php 
                                                        echo match($activity['action']) {
                                                            'create_customer' => 'user-plus',
                                                            'create_lead' => 'bullseye',
                                                            'create_sale' => 'shopping-cart',
                                                            'create_task' => 'tasks',
                                                            'login' => 'sign-in-alt',
                                                            default => 'info'
                                                        };
                                                    ?> text-white"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <div class="fw-bold">
                                                            <?php 
                                                            $actions = [
                                                                'create_customer' => 'ثبت مشتری جدید',
                                                                'create_lead' => 'ثبت لید جدید',
                                                                'create_sale' => 'ثبت فروش جدید',
                                                                'create_task' => 'ثبت وظیفه جدید',
                                                                'update_customer' => 'بروزرسانی مشتری',
                                                                'update_lead' => 'بروزرسانی لید',
                                                                'login' => 'ورود به سیستم'
                                                            ];
                                                            echo $actions[$activity['action']] ?? $activity['action'];
                                                            ?>
                                                        </div>
                                                        <?php if ($activity['details']): ?>
                                                            <small class="text-muted"><?php echo htmlspecialchars($activity['details']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small class="text-muted"><?php echo formatPersianDate($activity['created_at']); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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

.badge.fs-6 {
    font-size: 0.875rem !important;
}

.user-avatar {
    flex-shrink: 0;
}

.h4, .h5, .h6 {
    font-weight: 600;
}
</style>

<?php include __DIR__ . '/../private/footer.php'; ?>

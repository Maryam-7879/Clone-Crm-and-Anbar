<?php
$lead_id = (int)($_GET['id'] ?? 0);
$page_title = 'جزئیات لید';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'لیدها', 'url' => 'leads.php'],
    ['title' => 'جزئیات لید']
];

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
if (!hasPermission('view_leads')) {
    setMessage('شما دسترسی لازم برای مشاهده این صفحه را ندارید', 'error');
    header('Location: leads.php');
    exit();
}

if (!$lead_id) {
    setMessage('شناسه لید معتبر نیست', 'error');
    header('Location: leads.php');
    exit();
}

// دریافت اطلاعات لید
try {
    $stmt = $pdo->prepare("
        SELECT 
            l.*,
            CONCAT(u.first_name, ' ', u.last_name) as assigned_user,
            CONCAT(cb.first_name, ' ', cb.last_name) as created_user
        FROM leads l
        LEFT JOIN users u ON l.assigned_to = u.id
        LEFT JOIN users cb ON l.created_by = cb.id
        WHERE l.id = ?
    ");
    $stmt->execute([$lead_id]);
    $lead = $stmt->fetch();
    
    if (!$lead) {
        setMessage('لید یافت نشد', 'error');
        header('Location: leads.php');
        exit();
    }
    
    // وظایف مرتبط با لید
    $tasks = $pdo->prepare("
        SELECT 
            t.*,
            CONCAT(u.first_name, ' ', u.last_name) as assigned_user
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.related_type = 'lead' AND t.related_id = ?
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $tasks->execute([$lead_id]);
    $tasks = $tasks->fetchAll();
    
    // فروش‌های مرتبط (اگر لید تبدیل شده باشد)
    $sales = [];
    if ($lead['status'] === 'won') {
        $sales_stmt = $pdo->prepare("
            SELECT 
                s.*,
                (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as items_count
            FROM sales s
            WHERE s.lead_id = ?
            ORDER BY s.created_at DESC
        ");
        $sales_stmt->execute([$lead_id]);
        $sales = $sales_stmt->fetchAll();
    }
    
    // مشتری مرتبط (اگر ایمیل یکسان باشد)
    $customer = null;
    if ($lead['email']) {
        $customer_stmt = $pdo->prepare("
            SELECT * FROM customers 
            WHERE email = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $customer_stmt->execute([$lead['email']]);
        $customer = $customer_stmt->fetch();
    }
    
} catch (PDOException $e) {
    error_log("خطا در دریافت اطلاعات لید: " . $e->getMessage());
    setMessage('خطا در بارگذاری اطلاعات لید', 'error');
    header('Location: leads.php');
    exit();
}

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">جزئیات لید</h4>
        <p class="text-muted mb-0"><?php echo htmlspecialchars($lead['title']); ?></p>
    </div>
    
    <div>
        <a href="leads.php" class="btn btn-outline-secondary me-2">
            <i class="fas fa-arrow-right me-2"></i>
            بازگشت
        </a>
        
        <?php if (hasPermission('edit_lead')): ?>
            <a href="lead_form.php?id=<?php echo $lead_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i>
                ویرایش لید
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- اطلاعات اصلی لید -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bullseye me-2 text-primary"></i>
                    اطلاعات لید
                </h5>
            </div>
            <div class="card-body text-center">
                <div class="lead-icon mx-auto mb-3" style="width: 80px; height: 80px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                    <i class="fas fa-bullseye"></i>
                </div>
                
                <h5 class="mb-1"><?php echo htmlspecialchars($lead['title']); ?></h5>
                <p class="text-muted mb-3">
                    <span class="badge bg-<?php echo getStatusClass($lead['status'], 'lead'); ?>">
                        <?php echo getStatusTitle($lead['status'], 'lead'); ?>
                    </span>
                    <span class="badge bg-<?php echo getPriorityClass($lead['priority']); ?> ms-1">
                        <?php echo getPriorityTitle($lead['priority']); ?>
                    </span>
                </p>
                
                <div class="row text-center">
                    <div class="col-6">
                        <div class="h4 text-success"><?php echo formatMoney($lead['value']); ?></div>
                        <small class="text-muted">ارزش لید</small>
                    </div>
                    <div class="col-6">
                        <div class="h4 text-info"><?php echo $lead['probability']; ?>%</div>
                        <small class="text-muted">احتمال موفقیت</small>
                    </div>
                </div>
                
                <div class="mt-3">
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-<?php echo getStatusClass($lead['status'], 'lead'); ?>" 
                             style="width: <?php echo $lead['probability']; ?>%"></div>
                    </div>
                    <small class="text-muted">پیشرفت لید</small>
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
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-user text-primary me-3"></i>
                    <div>
                        <div class="fw-bold">نام کامل</div>
                        <div class="text-muted"><?php echo htmlspecialchars($lead['first_name'] . ' ' . $lead['last_name']); ?></div>
                    </div>
                </div>
                
                <?php if ($lead['email']): ?>
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-envelope text-success me-3"></i>
                    <div>
                        <div class="fw-bold">ایمیل</div>
                        <a href="mailto:<?php echo $lead['email']; ?>" class="text-muted">
                            <?php echo htmlspecialchars($lead['email']); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($lead['phone']): ?>
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-phone text-info me-3"></i>
                    <div>
                        <div class="fw-bold">تلفن</div>
                        <a href="tel:<?php echo $lead['phone']; ?>" class="text-muted">
                            <?php echo formatPhone($lead['phone']); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($lead['company']): ?>
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-building text-warning me-3"></i>
                    <div>
                        <div class="fw-bold">شرکت</div>
                        <div class="text-muted"><?php echo htmlspecialchars($lead['company']); ?></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($lead['position']): ?>
                <div class="d-flex align-items-center">
                    <i class="fas fa-briefcase text-danger me-3"></i>
                    <div>
                        <div class="fw-bold">سمت</div>
                        <div class="text-muted"><?php echo htmlspecialchars($lead['position']); ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- مشتری مرتبط -->
        <?php if ($customer): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-check me-2 text-primary"></i>
                    مشتری مرتبط
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-3" style="width: 40px; height: 40px; background: var(--success-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></div>
                        <small class="text-muted">کد: <?php echo htmlspecialchars($customer['customer_code']); ?></small>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="customer_view.php?id=<?php echo $customer['id']; ?>" class="btn btn-outline-success btn-sm w-100">
                        <i class="fas fa-eye me-2"></i>
                        مشاهده مشتری
                    </a>
                </div>
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
                        <?php if ($lead['source']): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-muted">منبع</div>
                            <div><?php echo htmlspecialchars($lead['source']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($lead['assigned_user']): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-muted">مسئول</div>
                            <div>
                                <i class="fas fa-user-tie me-1"></i>
                                <?php echo htmlspecialchars($lead['assigned_user']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <div class="fw-bold text-muted">تاریخ ایجاد</div>
                            <div><?php echo formatPersianDate($lead['created_at']); ?></div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <?php if ($lead['expected_close_date']): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-muted">تاریخ بسته شدن مورد انتظار</div>
                            <div><?php echo formatPersianDate($lead['expected_close_date'], 'Y/m/d'); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($lead['created_user']): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-muted">ایجادکننده</div>
                            <div><?php echo htmlspecialchars($lead['created_user']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <div class="fw-bold text-muted">آخرین بروزرسانی</div>
                            <div><?php echo formatPersianDate($lead['updated_at']); ?></div>
                        </div>
                    </div>
                </div>
                
                <?php if ($lead['tags']): ?>
                <div class="mb-3">
                    <div class="fw-bold text-muted mb-2">برچسب‌ها</div>
                    <div>
                        <?php
                        $tags = explode(',', $lead['tags']);
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
                
                <?php if ($lead['description']): ?>
                <div class="mb-3">
                    <div class="fw-bold text-muted mb-2">توضیحات</div>
                    <div class="bg-light p-3 rounded"><?php echo nl2br(htmlspecialchars($lead['description'])); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($lead['notes']): ?>
                <div>
                    <div class="fw-bold text-muted mb-2">یادداشت‌ها</div>
                    <div class="bg-light p-3 rounded"><?php echo nl2br(htmlspecialchars($lead['notes'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- تب‌ها -->
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="leadTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tasks-tab" data-bs-toggle="tab" data-bs-target="#tasks" type="button">
                            <i class="fas fa-tasks me-1"></i>
                            وظایف (<?php echo count($tasks); ?>)
                        </button>
                    </li>
                    <?php if (!empty($sales)): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales" type="button">
                            <i class="fas fa-shopping-cart me-1"></i>
                            فروش‌ها (<?php echo count($sales); ?>)
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
                <div class="tab-content" id="leadTabContent">
                    <!-- وظایف -->
                    <div class="tab-pane fade show active" id="tasks" role="tabpanel">
                        <?php if (empty($tasks)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                                <p class="text-muted">هیچ وظیفه‌ای برای این لید ثبت نشده است</p>
                                <?php if (hasPermission('add_task')): ?>
                                    <a href="task_form.php?related_type=lead&related_id=<?php echo $lead_id; ?>" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>
                                        ثبت وظیفه جدید
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>عنوان</th>
                                            <th>نوع</th>
                                            <th>اولویت</th>
                                            <th>وضعیت</th>
                                            <th>سررسید</th>
                                            <th>مسئول</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tasks as $task): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($task['title']); ?></td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php 
                                                        $types = ['call' => 'تماس', 'email' => 'ایمیل', 'meeting' => 'جلسه', 'follow_up' => 'پیگیری', 'other' => 'سایر'];
                                                        echo $types[$task['type']] ?? $task['type']; 
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getPriorityClass($task['priority']); ?>">
                                                        <?php echo getPriorityTitle($task['priority']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusClass($task['status'], 'task'); ?>">
                                                        <?php echo getStatusTitle($task['status'], 'task'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($task['due_date']): ?>
                                                        <?php echo formatPersianDate($task['due_date'], 'Y/m/d H:i'); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($task['assigned_user']): ?>
                                                        <small><?php echo htmlspecialchars($task['assigned_user']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">بدون مسئول</span>
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
                    
                    <!-- فروش‌ها -->
                    <?php if (!empty($sales)): ?>
                    <div class="tab-pane fade" id="sales" role="tabpanel">
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
                    </div>
                    <?php endif; ?>
                    
                    <!-- اقدامات سریع -->
                    <div class="tab-pane fade" id="actions" role="tabpanel">
                        <div class="row">
                            <?php if (hasPermission('add_task')): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-primary">
                                    <div class="card-body text-center">
                                        <i class="fas fa-phone fa-2x text-primary mb-3"></i>
                                        <h6>ثبت تماس</h6>
                                        <p class="text-muted small">ثبت تماس تلفنی با این لید</p>
                                        <a href="task_form.php?related_type=lead&related_id=<?php echo $lead_id; ?>&type=call" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus me-1"></i>
                                            افزودن تماس
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="card border-success">
                                    <div class="card-body text-center">
                                        <i class="fas fa-calendar fa-2x text-success mb-3"></i>
                                        <h6>تنظیم جلسه</h6>
                                        <p class="text-muted small">تنظیم جلسه با این لید</p>
                                        <a href="task_form.php?related_type=lead&related_id=<?php echo $lead_id; ?>&type=meeting" class="btn btn-success btn-sm">
                                            <i class="fas fa-plus me-1"></i>
                                            تنظیم جلسه
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($lead['status'] === 'won' && hasPermission('add_customer')): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-warning">
                                    <div class="card-body text-center">
                                        <i class="fas fa-user-plus fa-2x text-warning mb-3"></i>
                                        <h6>تبدیل به مشتری</h6>
                                        <p class="text-muted small">تبدیل این لید به مشتری</p>
                                        <a href="customer_form.php?from_lead=<?php echo $lead_id; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-exchange-alt me-1"></i>
                                            تبدیل به مشتری
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($lead['status'] === 'won' && hasPermission('add_sale')): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-info">
                                    <div class="card-body text-center">
                                        <i class="fas fa-shopping-cart fa-2x text-info mb-3"></i>
                                        <h6>ثبت فروش</h6>
                                        <p class="text-muted small">ثبت فروش برای این لید</p>
                                        <a href="sale_form.php?lead_id=<?php echo $lead_id; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-plus me-1"></i>
                                            ثبت فروش
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
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

.card.border-primary {
    border-color: var(--primary-color) !important;
}

.card.border-success {
    border-color: var(--success-color) !important;
}

.card.border-warning {
    border-color: var(--warning-color) !important;
}

.card.border-info {
    border-color: var(--info-color) !important;
}
</style>

<?php include __DIR__ . '/../private/footer.php'; ?>

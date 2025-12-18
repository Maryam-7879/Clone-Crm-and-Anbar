<?php
$task_id = (int)($_GET['id'] ?? 0);
$page_title = 'جزئیات وظیفه';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'وظایف', 'url' => 'tasks.php'],
    ['title' => 'جزئیات وظیفه']
];

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
if (!hasPermission('view_tasks')) {
    setMessage('شما دسترسی لازم برای مشاهده این صفحه را ندارید', 'error');
    header('Location: tasks.php');
    exit();
}

if (!$task_id) {
    setMessage('شناسه وظیفه معتبر نیست', 'error');
    header('Location: tasks.php');
    exit();
}

// پردازش درخواست‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        setMessage('درخواست نامعتبر. لطفاً مجدداً تلاش کنید.', 'error');
    } else {
        if ($action === 'update_status' && hasPermission('edit_task')) {
            $new_status = $_POST['new_status'];
            
            try {
                $completed_at = $new_status === 'completed' ? 'NOW()' : 'NULL';
                
                $stmt = $pdo->prepare("UPDATE tasks SET status = ?, completed_at = $completed_at WHERE id = ?");
                $stmt->execute([$new_status, $task_id]);
                
                if ($stmt->rowCount() > 0) {
                    logActivity($_SESSION['user_id'], 'update_task_status', 'tasks', $task_id, ['status' => $new_status]);
                    setMessage('وضعیت وظیفه بروزرسانی شد', 'success');
                }
            } catch (PDOException $e) {
                error_log("خطا در بروزرسانی وضعیت وظیفه: " . $e->getMessage());
                setMessage('خطا در بروزرسانی وضعیت', 'error');
            }
        }
    }
}

// دریافت اطلاعات وظیفه
try {
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            CONCAT(u.first_name, ' ', u.last_name) as assigned_user,
            CONCAT(cb.first_name, ' ', cb.last_name) as created_user,
            CASE 
                WHEN t.related_type = 'customer' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM customers WHERE id = t.related_id)
                WHEN t.related_type = 'lead' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM leads WHERE id = t.related_id)
                ELSE NULL
            END as related_name,
            CASE 
                WHEN t.related_type = 'customer' THEN (SELECT customer_code FROM customers WHERE id = t.related_id)
                WHEN t.related_type = 'lead' THEN (SELECT title FROM leads WHERE id = t.related_id)
                ELSE NULL
            END as related_info
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        LEFT JOIN users cb ON t.created_by = cb.id
        WHERE t.id = ?
    ");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        setMessage('وظیفه یافت نشد', 'error');
        header('Location: tasks.php');
        exit();
    }
    
    // بررسی عقب‌افتادگی
    $is_overdue = $task['due_date'] && strtotime($task['due_date']) < time() && !in_array($task['status'], ['completed', 'cancelled']);
    
} catch (PDOException $e) {
    error_log("خطا در دریافت اطلاعات وظیفه: " . $e->getMessage());
    setMessage('خطا در بارگذاری اطلاعات وظیفه', 'error');
    header('Location: tasks.php');
    exit();
}

$csrf_token = generateCSRFToken();

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">جزئیات وظیفه</h4>
        <p class="text-muted mb-0"><?php echo htmlspecialchars($task['title']); ?></p>
    </div>
    
    <div>
        <a href="tasks.php" class="btn btn-outline-secondary me-2">
            <i class="fas fa-arrow-right me-2"></i>
            بازگشت
        </a>
        
        <?php if (hasPermission('edit_task')): ?>
            <a href="task_form.php?id=<?php echo $task_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i>
                ویرایش وظیفه
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- اطلاعات اصلی وظیفه -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-tasks me-2 text-primary"></i>
                    اطلاعات وظیفه
                </h5>
            </div>
            <div class="card-body text-center">
                <div class="task-icon mx-auto mb-3" style="width: 80px; height: 80px; background: var(--<?php echo getPriorityClass($task['priority']); ?>-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                    <i class="fas fa-<?php 
                        echo match($task['type']) {
                            'call' => 'phone',
                            'email' => 'envelope',
                            'meeting' => 'calendar',
                            'follow_up' => 'redo',
                            default => 'tasks'
                        };
                    ?>"></i>
                </div>
                
                <h5 class="mb-1"><?php echo htmlspecialchars($task['title']); ?></h5>
                <p class="text-muted mb-3">
                    <span class="badge bg-<?php echo getStatusClass($task['status'], 'task'); ?>">
                        <?php echo getStatusTitle($task['status'], 'task'); ?>
                    </span>
                    <span class="badge bg-<?php echo getPriorityClass($task['priority']); ?> ms-1">
                        <?php echo getPriorityTitle($task['priority']); ?>
                    </span>
                </p>
                
                <?php if ($task['due_date']): ?>
                <div class="mb-3">
                    <div class="fw-bold">سررسید</div>
                    <div class="<?php echo $is_overdue ? 'text-danger fw-bold' : 'text-muted'; ?>">
                        <i class="fas fa-clock me-1"></i>
                        <?php echo formatPersianDate($task['due_date']); ?>
                        <?php if ($is_overdue): ?>
                            <div class="text-danger small mt-1">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                عقب‌افتاده
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($task['reminder_datetime']): ?>
                <div class="mb-3">
                    <div class="fw-bold">یادآوری</div>
                    <div class="text-muted">
                        <i class="fas fa-bell me-1"></i>
                        <?php echo formatPersianDate($task['reminder_datetime']); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- تغییر وضعیت سریع -->
                <?php if (hasPermission('edit_task') && $task['status'] !== 'completed'): ?>
                <div class="mt-4">
                    <div class="d-grid gap-2">
                        <?php if ($task['status'] === 'pending'): ?>
                            <button type="button" class="btn btn-warning btn-sm" onclick="updateStatus('in_progress')">
                                <i class="fas fa-play me-1"></i>
                                شروع وظیفه
                            </button>
                        <?php endif; ?>
                        
                        <?php if (in_array($task['status'], ['pending', 'in_progress'])): ?>
                            <button type="button" class="btn btn-success btn-sm" onclick="updateStatus('completed')">
                                <i class="fas fa-check me-1"></i>
                                تکمیل وظیفه
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($task['status'] !== 'cancelled'): ?>
                            <button type="button" class="btn btn-danger btn-sm" onclick="updateStatus('cancelled')">
                                <i class="fas fa-times me-1"></i>
                                لغو وظیفه
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- اطلاعات مسئول -->
        <?php if ($task['assigned_user']): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-tie me-2 text-primary"></i>
                    مسئول انجام
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-3" style="width: 50px; height: 50px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($task['assigned_user']); ?></div>
                        <small class="text-muted">مسئول انجام وظیفه</small>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- آیتم مرتبط -->
        <?php if ($task['related_name']): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-link me-2 text-primary"></i>
                    مرتبط با
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="related-icon me-3" style="width: 40px; height: 40px; background: var(--info-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fas fa-<?php echo $task['related_type'] === 'customer' ? 'user' : 'bullseye'; ?>"></i>
                    </div>
                    <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($task['related_name']); ?></div>
                        <small class="text-muted">
                            <?php echo $task['related_type'] === 'customer' ? 'مشتری' : 'لید'; ?>
                            <?php if ($task['related_info']): ?>
                                - <?php echo htmlspecialchars($task['related_info']); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="<?php echo $task['related_type']; ?>_view.php?id=<?php echo $task['related_id']; ?>" class="btn btn-outline-info btn-sm w-100">
                        <i class="fas fa-eye me-2"></i>
                        مشاهده <?php echo $task['related_type'] === 'customer' ? 'مشتری' : 'لید'; ?>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- جزئیات وظیفه -->
    <div class="col-lg-8">
        <!-- توضیحات وظیفه -->
        <?php if ($task['description']): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-file-alt me-2 text-primary"></i>
                    توضیحات وظیفه
                </h5>
            </div>
            <div class="card-body">
                <div class="bg-light p-3 rounded">
                    <?php echo nl2br(htmlspecialchars($task['description'])); ?>
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
                            <div class="fw-bold text-muted">نوع وظیفه</div>
                            <div>
                                <span class="badge bg-secondary">
                                    <?php 
                                    $types = [
                                        'call' => 'تماس تلفنی',
                                        'email' => 'ارسال ایمیل',
                                        'meeting' => 'جلسه',
                                        'follow_up' => 'پیگیری',
                                        'other' => 'سایر'
                                    ];
                                    echo $types[$task['type']] ?? $task['type']; 
                                    ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="fw-bold text-muted">ایجادکننده</div>
                            <div>
                                <?php if ($task['created_user']): ?>
                                    <i class="fas fa-user me-1"></i>
                                    <?php echo htmlspecialchars($task['created_user']); ?>
                                <?php else: ?>
                                    <span class="text-muted">نامشخص</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="fw-bold text-muted">تاریخ ایجاد</div>
                            <div><?php echo formatPersianDate($task['created_at']); ?></div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="fw-bold text-muted">وضعیت فعلی</div>
                            <div>
                                <span class="badge bg-<?php echo getStatusClass($task['status'], 'task'); ?> fs-6">
                                    <?php echo getStatusTitle($task['status'], 'task'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if ($task['completed_at']): ?>
                        <div class="mb-3">
                            <div class="fw-bold text-muted">تاریخ تکمیل</div>
                            <div class="text-success">
                                <i class="fas fa-check-circle me-1"></i>
                                <?php echo formatPersianDate($task['completed_at']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <div class="fw-bold text-muted">آخرین بروزرسانی</div>
                            <div><?php echo formatPersianDate($task['updated_at']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- آمار و پیشرفت -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2 text-primary"></i>
                    پیشرفت و آمار
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="h4 text-<?php echo $task['status'] === 'completed' ? 'success' : ($is_overdue ? 'danger' : 'warning'); ?>">
                            <?php 
                            if ($task['status'] === 'completed') {
                                echo '100%';
                            } elseif ($task['status'] === 'in_progress') {
                                echo '50%';
                            } elseif ($task['status'] === 'cancelled') {
                                echo '0%';
                            } else {
                                echo '10%';
                            }
                            ?>
                        </div>
                        <small class="text-muted">پیشرفت</small>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="h4 text-info">
                            <?php 
                            if ($task['due_date']) {
                                $days_remaining = ceil((strtotime($task['due_date']) - time()) / (60 * 60 * 24));
                                if ($days_remaining < 0) {
                                    echo abs($days_remaining) . ' روز';
                                    echo '<br><small class="text-danger">عقب‌افتاده</small>';
                                } else {
                                    echo $days_remaining . ' روز';
                                    echo '<br><small class="text-muted">باقی‌مانده</small>';
                                }
                            } else {
                                echo '-';
                                echo '<br><small class="text-muted">بدون سررسید</small>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="h4 text-primary">
                            <?php 
                            $priority_scores = ['low' => 1, 'medium' => 2, 'high' => 3, 'urgent' => 4];
                            echo $priority_scores[$task['priority']] . '/4';
                            ?>
                        </div>
                        <small class="text-muted">اولویت</small>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="h4 text-success">
                            <?php 
                            $created = new DateTime($task['created_at']);
                            $now = new DateTime();
                            $diff = $created->diff($now);
                            echo $diff->days . ' روز';
                            ?>
                        </div>
                        <small class="text-muted">سن وظیفه</small>
                    </div>
                </div>
                
                <div class="mt-4">
                    <div class="progress" style="height: 15px;">
                        <?php
                        $progress = 0;
                        $color = 'secondary';
                        
                        switch ($task['status']) {
                            case 'pending':
                                $progress = 10;
                                $color = 'warning';
                                break;
                            case 'in_progress':
                                $progress = 50;
                                $color = 'info';
                                break;
                            case 'completed':
                                $progress = 100;
                                $color = 'success';
                                break;
                            case 'cancelled':
                                $progress = 0;
                                $color = 'danger';
                                break;
                        }
                        ?>
                        <div class="progress-bar bg-<?php echo $color; ?>" 
                             role="progressbar" 
                             style="width: <?php echo $progress; ?>%">
                            <?php echo $progress; ?>%
                        </div>
                    </div>
                    <small class="text-muted">پیشرفت کلی وظیفه</small>
                </div>
            </div>
        </div>
        
        <!-- اقدامات سریع -->
        <?php if (hasPermission('edit_task')): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2 text-primary"></i>
                    اقدامات سریع
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if ($task['status'] !== 'completed'): ?>
                    <div class="col-md-4 mb-3">
                        <div class="d-grid">
                            <a href="task_form.php?id=<?php echo $task_id; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-edit me-2"></i>
                                ویرایش جزئیات
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($task['related_type'] && $task['related_id']): ?>
                    <div class="col-md-4 mb-3">
                        <div class="d-grid">
                            <a href="task_form.php?related_type=<?php echo $task['related_type']; ?>&related_id=<?php echo $task['related_id']; ?>" class="btn btn-outline-success">
                                <i class="fas fa-plus me-2"></i>
                                وظیفه مرتبط جدید
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-4 mb-3">
                        <div class="d-grid">
                            <a href="tasks.php?assigned_to=<?php echo $task['assigned_to']; ?>" class="btn btn-outline-info">
                                <i class="fas fa-list me-2"></i>
                                سایر وظایف این کاربر
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function updateStatus(newStatus) {
    const statusTitles = {
        'pending': 'در انتظار',
        'in_progress': 'در حال انجام',
        'completed': 'تکمیل شده',
        'cancelled': 'لغو شده'
    };
    
    if (confirm(`آیا از تغییر وضعیت به "${statusTitles[newStatus]}" مطمئن هستید؟`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="new_status" value="${newStatus}">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include __DIR__ . '/../private/footer.php'; ?>

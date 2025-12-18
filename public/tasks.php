<?php
$page_title = 'مدیریت وظایف';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'وظایف']
];

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
if (!hasPermission('view_tasks')) {
    setMessage('شما دسترسی لازم برای مشاهده این صفحه را ندارید', 'error');
    header('Location: dashboard.php');
    exit();
}

// پردازش درخواست‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete' && hasPermission('delete_task')) {
        $task_id = (int)$_POST['task_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            
            if ($stmt->rowCount() > 0) {
                logActivity($_SESSION['user_id'], 'delete_task', 'tasks', $task_id);
                setMessage('وظیفه با موفقیت حذف شد', 'success');
            } else {
                setMessage('وظیفه یافت نشد', 'error');
            }
        } catch (PDOException $e) {
            error_log("خطا در حذف وظیفه: " . $e->getMessage());
            setMessage('خطا در حذف وظیفه', 'error');
        }
    }
    
    if ($action === 'update_status' && hasPermission('edit_task')) {
        $task_id = (int)$_POST['task_id'];
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
    
    if ($action === 'quick_add' && hasPermission('add_task')) {
        $title = sanitizeInput($_POST['quick_title']);
        $due_date = $_POST['quick_due_date'];
        $assigned_to = (int)$_POST['quick_assigned_to'];
        
        if ($title) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO tasks (title, due_date, assigned_to, created_by) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$title, $due_date ?: null, $assigned_to ?: null, $_SESSION['user_id']]);
                
                logActivity($_SESSION['user_id'], 'create_task', 'tasks', $pdo->lastInsertId());
                setMessage('وظیفه جدید اضافه شد', 'success');
            } catch (PDOException $e) {
                error_log("خطا در افزودن وظیفه: " . $e->getMessage());
                setMessage('خطا در افزودن وظیفه', 'error');
            }
        }
    }
}

// دریافت فیلترها
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$type = $_GET['type'] ?? '';
$assigned_to = $_GET['assigned_to'] ?? '';
$due_filter = $_GET['due_filter'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = RECORDS_PER_PAGE;
$offset = ($page - 1) * $per_page;

// ساخت کوئری
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(t.title LIKE ? OR t.description LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term]);
}

if ($status) {
    $where_conditions[] = "t.status = ?";
    $params[] = $status;
}

if ($priority) {
    $where_conditions[] = "t.priority = ?";
    $params[] = $priority;
}

if ($type) {
    $where_conditions[] = "t.type = ?";
    $params[] = $type;
}

if ($assigned_to) {
    $where_conditions[] = "t.assigned_to = ?";
    $params[] = $assigned_to;
}

if ($due_filter) {
    switch ($due_filter) {
        case 'today':
            $where_conditions[] = "DATE(t.due_date) = CURDATE()";
            break;
        case 'tomorrow':
            $where_conditions[] = "DATE(t.due_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'this_week':
            $where_conditions[] = "WEEK(t.due_date) = WEEK(CURDATE()) AND YEAR(t.due_date) = YEAR(CURDATE())";
            break;
        case 'overdue':
            $where_conditions[] = "t.due_date < NOW() AND t.status != 'completed'";
            break;
    }
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// دریافت تعداد کل رکوردها
$count_sql = "SELECT COUNT(*) FROM tasks t $where_clause";
$total_records = $pdo->prepare($count_sql);
$total_records->execute($params);
$total_records = $total_records->fetchColumn();

// دریافت وظایف
$sql = "
    SELECT 
        t.*,
        CONCAT(u.first_name, ' ', u.last_name) as assigned_user,
        CONCAT(cb.first_name, ' ', cb.last_name) as created_user,
        CASE 
            WHEN t.related_type = 'customer' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM customers WHERE id = t.related_id)
            WHEN t.related_type = 'lead' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM leads WHERE id = t.related_id)
            ELSE NULL
        END as related_name
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to = u.id
    LEFT JOIN users cb ON t.created_by = cb.id
    $where_clause
    ORDER BY 
        CASE t.status WHEN 'completed' THEN 3 WHEN 'cancelled' THEN 4 ELSE 1 END,
        CASE t.priority 
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
        END,
        t.due_date ASC,
        t.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$tasks = $pdo->prepare($sql);
$tasks->execute($params);
$tasks = $tasks->fetchAll();

// دریافت کاربران برای فیلتر
$users = $pdo->query("SELECT id, first_name, last_name FROM users WHERE status = 'active' ORDER BY first_name")->fetchAll();

// آمار وظایف
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'pending'")->fetchColumn(),
    'in_progress' => $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'in_progress'")->fetchColumn(),
    'completed' => $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'completed'")->fetchColumn(),
    'overdue' => $pdo->query("SELECT COUNT(*) FROM tasks WHERE due_date < NOW() AND status NOT IN ('completed', 'cancelled')")->fetchColumn(),
];

include __DIR__ . '/../private/header.php';
?>

<!-- آمار کوتاه -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-primary"><?php echo number_format($stats['total']); ?></h5>
                <small class="text-muted">کل وظایف</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-warning"><?php echo number_format($stats['pending']); ?></h5>
                <small class="text-muted">در انتظار</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-info"><?php echo number_format($stats['in_progress']); ?></h5>
                <small class="text-muted">در حال انجام</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-success"><?php echo number_format($stats['completed']); ?></h5>
                <small class="text-muted">تکمیل شده</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-danger"><?php echo number_format($stats['overdue']); ?></h5>
                <small class="text-muted">عقب‌افتاده</small>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center align-items-start mb-4 gap-3">
    <div>
        <h4 class="mb-1">مدیریت وظایف</h4>
        <p class="text-muted mb-0">مشاهده و مدیریت وظایف و پیگیری‌ها</p>
    </div>
    
    <div>
        <?php if (hasPermission('add_task')): ?>
            <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#quickAddModal">
                <i class="fas fa-plus-circle me-2"></i>
                افزودن سریع
            </button>
            <a href="task_form.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>
                افزودن وظیفه جدید
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- فیلترها -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label">جستجو</label>
                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="عنوان، توضیحات...">
            </div>
            
            <div class="col-lg-2 col-md-6 col-12">
                <label class="form-label">وضعیت</label>
                <select class="form-select" name="status">
                    <option value="">همه</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>در انتظار</option>
                    <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>در حال انجام</option>
                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>تکمیل شده</option>
                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>لغو شده</option>
                </select>
            </div>
            
            <div class="col-lg-2 col-md-6 col-12">
                <label class="form-label">اولویت</label>
                <select class="form-select" name="priority">
                    <option value="">همه</option>
                    <option value="urgent" <?php echo $priority === 'urgent' ? 'selected' : ''; ?>>فوری</option>
                    <option value="high" <?php echo $priority === 'high' ? 'selected' : ''; ?>>بالا</option>
                    <option value="medium" <?php echo $priority === 'medium' ? 'selected' : ''; ?>>متوسط</option>
                    <option value="low" <?php echo $priority === 'low' ? 'selected' : ''; ?>>کم</option>
                </select>
            </div>
            
            <div class="col-lg-2 col-md-6 col-12">
                <label class="form-label">نوع</label>
                <select class="form-select" name="type">
                    <option value="">همه</option>
                    <option value="call" <?php echo $type === 'call' ? 'selected' : ''; ?>>تماس</option>
                    <option value="email" <?php echo $type === 'email' ? 'selected' : ''; ?>>ایمیل</option>
                    <option value="meeting" <?php echo $type === 'meeting' ? 'selected' : ''; ?>>جلسه</option>
                    <option value="follow_up" <?php echo $type === 'follow_up' ? 'selected' : ''; ?>>پیگیری</option>
                    <option value="other" <?php echo $type === 'other' ? 'selected' : ''; ?>>سایر</option>
                </select>
            </div>
            
            <div class="col-lg-2 col-md-6 col-12">
                <label class="form-label">سررسید</label>
                <select class="form-select" name="due_filter">
                    <option value="">همه</option>
                    <option value="today" <?php echo $due_filter === 'today' ? 'selected' : ''; ?>>امروز</option>
                    <option value="tomorrow" <?php echo $due_filter === 'tomorrow' ? 'selected' : ''; ?>>فردا</option>
                    <option value="this_week" <?php echo $due_filter === 'this_week' ? 'selected' : ''; ?>>این هفته</option>
                    <option value="overdue" <?php echo $due_filter === 'overdue' ? 'selected' : ''; ?>>عقب‌افتاده</option>
                </select>
            </div>
            
            <div class="col-lg-1 col-md-12 col-12">
                <label class="form-label d-none d-lg-block">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-search me-1"></i>
                        <span class="d-lg-none">جستجو</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- جدول وظایف -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-tasks me-2"></i>
            لیست وظایف
            <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?></span>
        </h5>
        
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('tasksTable', 'tasks.csv')">
                <i class="fas fa-download me-1"></i>
                خروجی CSV
            </button>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (empty($tasks)): ?>
            <div class="text-center py-5">
                <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">وظیفه‌ای یافت نشد</h5>
                <p class="text-muted">برای شروع، وظیفه جدیدی اضافه کنید</p>
                <?php if (hasPermission('add_task')): ?>
                    <a href="task_form.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>
                        افزودن وظیفه اول
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="tasksTable">
                    <thead>
                        <tr>
                            <th>عنوان</th>
                            <th>نوع</th>
                            <th>اولویت</th>
                            <th>وضعیت</th>
                            <th>مسئول</th>
                            <th>مرتبط با</th>
                            <th>سررسید</th>
                            <th>تاریخ ایجاد</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <?php
                            $is_overdue = $task['due_date'] && strtotime($task['due_date']) < time() && !in_array($task['status'], ['completed', 'cancelled']);
                            $row_class = $is_overdue ? 'table-warning' : '';
                            if ($task['status'] === 'completed') $row_class = 'table-success';
                            if ($task['status'] === 'cancelled') $row_class = 'table-secondary';
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($task['title']); ?></div>
                                    <?php if ($task['description']): ?>
                                        <small class="text-muted">
                                            <?php echo truncateText(htmlspecialchars($task['description']), 60); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php
                                        $types = [
                                            'call' => 'تماس',
                                            'email' => 'ایمیل',
                                            'meeting' => 'جلسه',
                                            'follow_up' => 'پیگیری',
                                            'other' => 'سایر'
                                        ];
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
                                    <div class="dropdown">
                                        <button class="btn btn-sm badge bg-<?php echo getStatusClass($task['status'], 'task'); ?> dropdown-toggle" 
                                                type="button" data-bs-toggle="dropdown">
                                            <?php echo getStatusTitle($task['status'], 'task'); ?>
                                        </button>
                                        <?php if (hasPermission('edit_task')): ?>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'pending')">در انتظار</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'in_progress')">در حال انجام</a></li>
                                                <li><a class="dropdown-item text-success" href="#" onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'completed')">تکمیل شده</a></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'cancelled')">لغو شده</a></li>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($task['assigned_user']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-user-tie me-1"></i>
                                            <?php echo htmlspecialchars($task['assigned_user']); ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">بدون مسئول</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($task['related_name']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-<?php echo $task['related_type'] === 'customer' ? 'user' : 'bullseye'; ?> me-1"></i>
                                            <?php echo htmlspecialchars($task['related_name']); ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($task['due_date']): ?>
                                        <small class="<?php echo $is_overdue ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo formatPersianDate($task['due_date']); ?>
                                            <?php if ($is_overdue): ?>
                                                <i class="fas fa-exclamation-triangle ms-1"></i>
                                            <?php endif; ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo formatPersianDate($task['created_at'], 'Y/m/d'); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="task_view.php?id=<?php echo $task['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm" 
                                           data-bs-toggle="tooltip" title="مشاهده جزئیات">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if (hasPermission('edit_task')): ?>
                                            <a href="task_form.php?id=<?php echo $task['id']; ?>" 
                                               class="btn btn-outline-warning btn-sm"
                                               data-bs-toggle="tooltip" title="ویرایش">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (hasPermission('delete_task')): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-danger btn-sm"
                                                    onclick="deleteTask(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['title']); ?>')"
                                                    data-bs-toggle="tooltip" title="حذف">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- صفحه‌بندی -->
            <?php if ($total_records > $per_page): ?>
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div>
                        نمایش <?php echo number_format($offset + 1); ?> تا <?php echo number_format(min($offset + $per_page, $total_records)); ?> 
                        از <?php echo number_format($total_records); ?> رکورد
                    </div>
                    
                    <?php
                    $base_url = 'tasks.php?' . http_build_query(array_filter([
                        'search' => $search,
                        'status' => $status,
                        'priority' => $priority,
                        'type' => $type,
                        'assigned_to' => $assigned_to,
                        'due_filter' => $due_filter
                    ]));
                    echo createPagination($page, $total_records, $per_page, $base_url);
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal افزودن سریع -->
<?php if (hasPermission('add_task')): ?>
<div class="modal fade" id="quickAddModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">افزودن سریع وظیفه</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="quick_add">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="quick_title" class="form-label">عنوان وظیفه <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="quick_title" name="quick_title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quick_due_date" class="form-label">سررسید</label>
                        <input type="datetime-local" class="form-control" id="quick_due_date" name="quick_due_date">
                    </div>
                    
                    <div class="mb-3">
                        <label for="quick_assigned_to" class="form-label">مسئول</label>
                        <select class="form-select" id="quick_assigned_to" name="quick_assigned_to">
                            <option value="">بدون مسئول</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary">افزودن</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function deleteTask(taskId, taskTitle) {
    confirmDelete(`آیا از حذف وظیفه "${taskTitle}" مطمئن هستید؟`).then((confirmed) => {
        if (confirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="task_id" value="${taskId}">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function updateTaskStatus(taskId, newStatus) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="task_id" value="${taskId}">
        <input type="hidden" name="new_status" value="${newStatus}">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Initialize table sorting
document.addEventListener('DOMContentLoaded', function() {
    initTableSort('tasksTable');
    
    // Set default due date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(9, 0, 0, 0);
    document.getElementById('quick_due_date').value = tomorrow.toISOString().slice(0, 16);
});
</script>

<?php include __DIR__ . '/../private/footer.php'; ?>

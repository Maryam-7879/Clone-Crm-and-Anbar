<?php
$task_id = (int)($_GET['id'] ?? 0);
$page_title = $task_id ? 'ویرایش وظیفه' : 'افزودن وظیفه جدید';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'وظایف', 'url' => 'tasks.php'],
    ['title' => $task_id ? 'ویرایش وظیفه' : 'افزودن وظیفه']
];

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
$required_permission = $task_id ? 'edit_task' : 'add_task';
if (!hasPermission($required_permission)) {
    setMessage('شما دسترسی لازم برای این عملیات را ندارید', 'error');
    header('Location: tasks.php');
    exit();
}

// متغیرهای فرم
$task = [
    'title' => '',
    'description' => '',
    'type' => 'other',
    'status' => 'pending',
    'priority' => 'medium',
    'due_date' => '',
    'assigned_to' => '',
    'related_type' => '',
    'related_id' => '',
    'reminder_datetime' => ''
];

$errors = [];

// بارگذاری اطلاعات وظیفه برای ویرایش
if ($task_id) {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $existing_task = $stmt->fetch();
    
    if (!$existing_task) {
        setMessage('وظیفه یافت نشد', 'error');
        header('Location: tasks.php');
        exit();
    }
    
    $task = array_merge($task, $existing_task);
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $errors[] = 'درخواست نامعتبر. لطفاً مجدداً تلاش کنید.';
    } else {
        // دریافت و اعتبارسنجی داده‌ها
        $task['title'] = sanitizeInput($_POST['title']);
        $task['description'] = sanitizeInput($_POST['description']);
        $task['type'] = $_POST['type'];
        $task['status'] = $_POST['status'];
        $task['priority'] = $_POST['priority'];
        $task['due_date'] = $_POST['due_date'] ?: null;
        $task['assigned_to'] = (int)$_POST['assigned_to'];
        $task['related_type'] = $_POST['related_type'] ?: null;
        $task['related_id'] = (int)$_POST['related_id'] ?: null;
        $task['reminder_datetime'] = $_POST['reminder_datetime'] ?: null;
        
        // اعتبارسنجی
        if (empty($task['title'])) {
            $errors[] = 'عنوان وظیفه الزامی است';
        }
        
        if ($task['due_date'] && strtotime($task['due_date']) < strtotime('-1 day')) {
            $errors[] = 'تاریخ سررسید نمی‌تواند در گذشته باشد';
        }
        
        if ($task['reminder_datetime'] && $task['due_date'] && strtotime($task['reminder_datetime']) > strtotime($task['due_date'])) {
            $errors[] = 'زمان یادآوری نمی‌تواند بعد از سررسید باشد';
        }
        
        // ذخیره در صورت عدم وجود خطا
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                if ($task_id) {
                    // ویرایش وظیفه
                    $sql = "
                        UPDATE tasks SET
                            title = ?, description = ?, type = ?, status = ?, priority = ?,
                            due_date = ?, assigned_to = ?, related_type = ?, related_id = ?,
                            reminder_datetime = ?
                        WHERE id = ?
                    ";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $task['title'], $task['description'], $task['type'], $task['status'],
                        $task['priority'], $task['due_date'], $task['assigned_to'] ?: null,
                        $task['related_type'], $task['related_id'], $task['reminder_datetime'],
                        $task_id
                    ]);
                    
                    logActivity($_SESSION['user_id'], 'update_task', 'tasks', $task_id, $existing_task, $task);
                    setMessage('اطلاعات وظیفه با موفقیت بروزرسانی شد', 'success');
                    
                } else {
                    // افزودن وظیفه جدید
                    $sql = "
                        INSERT INTO tasks (
                            title, description, type, status, priority, due_date,
                            assigned_to, related_type, related_id, reminder_datetime, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $task['title'], $task['description'], $task['type'], $task['status'],
                        $task['priority'], $task['due_date'], $task['assigned_to'] ?: null,
                        $task['related_type'], $task['related_id'], $task['reminder_datetime'],
                        $_SESSION['user_id']
                    ]);
                    
                    $new_task_id = $pdo->lastInsertId();
                    logActivity($_SESSION['user_id'], 'create_task', 'tasks', $new_task_id, null, $task);
                    setMessage('وظیفه جدید با موفقیت اضافه شد', 'success');
                }
                
                $pdo->commit();
                header('Location: tasks.php');
                exit();
                
            } catch (PDOException $e) {
                $pdo->rollback();
                error_log("خطا در ذخیره وظیفه: " . $e->getMessage());
                $errors[] = 'خطا در ذخیره اطلاعات';
            }
        }
    }
}

// دریافت کاربران برای انتساب
$users = $pdo->query("SELECT id, first_name, last_name FROM users WHERE status = 'active' ORDER BY first_name")->fetchAll();

// دریافت مشتریان برای ارتباط
$customers = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM customers ORDER BY first_name LIMIT 50")->fetchAll();

// دریافت لیدها برای ارتباط
$leads = $pdo->query("SELECT id, title, CONCAT(first_name, ' ', last_name) as name FROM leads ORDER BY created_at DESC LIMIT 50")->fetchAll();

$csrf_token = generateCSRFToken();

include __DIR__ . '/../private/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-<?php echo $task_id ? 'edit' : 'plus'; ?> me-2"></i>
                    <?php echo $page_title; ?>
                </h5>
            </div>
            
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="taskForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <!-- اطلاعات اصلی -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-info-circle me-2 text-primary"></i>
                                اطلاعات اصلی وظیفه
                            </h6>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="title" class="form-label">عنوان وظیفه <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?php echo htmlspecialchars($task['title']); ?>" required
                                   placeholder="مثال: تماس با مشتری ABC برای پیگیری پیشنهاد">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="description" class="form-label">توضیحات</label>
                            <textarea class="form-control" id="description" name="description" rows="4"
                                      placeholder="توضیحات تکمیلی در مورد این وظیفه..."><?php echo htmlspecialchars($task['description']); ?></textarea>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="type" class="form-label">نوع وظیفه</label>
                            <select class="form-select" id="type" name="type">
                                <option value="call" <?php echo $task['type'] === 'call' ? 'selected' : ''; ?>>
                                    <i class="fas fa-phone"></i> تماس تلفنی
                                </option>
                                <option value="email" <?php echo $task['type'] === 'email' ? 'selected' : ''; ?>>
                                    <i class="fas fa-envelope"></i> ارسال ایمیل
                                </option>
                                <option value="meeting" <?php echo $task['type'] === 'meeting' ? 'selected' : ''; ?>>
                                    <i class="fas fa-calendar"></i> جلسه
                                </option>
                                <option value="follow_up" <?php echo $task['type'] === 'follow_up' ? 'selected' : ''; ?>>
                                    <i class="fas fa-redo"></i> پیگیری
                                </option>
                                <option value="other" <?php echo $task['type'] === 'other' ? 'selected' : ''; ?>>
                                    <i class="fas fa-tasks"></i> سایر
                                </option>
                            </select>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="assigned_to" class="form-label">مسئول انجام</label>
                            <select class="form-select" id="assigned_to" name="assigned_to">
                                <option value="">بدون مسئول</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $task['assigned_to'] == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- وضعیت و اولویت -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-cogs me-2 text-primary"></i>
                                وضعیت و اولویت
                            </h6>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="status" class="form-label">وضعیت</label>
                            <select class="form-select" id="status" name="status">
                                <option value="pending" <?php echo $task['status'] === 'pending' ? 'selected' : ''; ?>>در انتظار</option>
                                <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>در حال انجام</option>
                                <option value="completed" <?php echo $task['status'] === 'completed' ? 'selected' : ''; ?>>تکمیل شده</option>
                                <option value="cancelled" <?php echo $task['status'] === 'cancelled' ? 'selected' : ''; ?>>لغو شده</option>
                            </select>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="priority" class="form-label">اولویت</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="low" <?php echo $task['priority'] === 'low' ? 'selected' : ''; ?>>کم</option>
                                <option value="medium" <?php echo $task['priority'] === 'medium' ? 'selected' : ''; ?>>متوسط</option>
                                <option value="high" <?php echo $task['priority'] === 'high' ? 'selected' : ''; ?>>بالا</option>
                                <option value="urgent" <?php echo $task['priority'] === 'urgent' ? 'selected' : ''; ?>>فوری</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- زمان‌بندی -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-clock me-2 text-primary"></i>
                                زمان‌بندی
                            </h6>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="due_date" class="form-label">سررسید</label>
                            <input type="datetime-local" class="form-control" id="due_date" name="due_date" 
                                   value="<?php echo $task['due_date'] ? date('Y-m-d\TH:i', strtotime($task['due_date'])) : ''; ?>">
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="reminder_datetime" class="form-label">یادآوری</label>
                            <input type="datetime-local" class="form-control" id="reminder_datetime" name="reminder_datetime" 
                                   value="<?php echo $task['reminder_datetime'] ? date('Y-m-d\TH:i', strtotime($task['reminder_datetime'])) : ''; ?>">
                            <small class="form-text text-muted">یادآوری قبل از سررسید</small>
                        </div>
                    </div>
                    
                    <!-- ارتباط با رکوردهای دیگر -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-link me-2 text-primary"></i>
                                ارتباط با رکوردهای دیگر
                            </h6>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="related_type" class="form-label">نوع ارتباط</label>
                            <select class="form-select" id="related_type" name="related_type" onchange="toggleRelatedOptions()">
                                <option value="">بدون ارتباط</option>
                                <option value="customer" <?php echo $task['related_type'] === 'customer' ? 'selected' : ''; ?>>مشتری</option>
                                <option value="lead" <?php echo $task['related_type'] === 'lead' ? 'selected' : ''; ?>>لید</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3" id="customer_select" style="display: none;">
                            <label for="related_customer" class="form-label">انتخاب مشتری</label>
                            <select class="form-select" id="related_customer" name="related_id">
                                <option value="">انتخاب مشتری</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>" 
                                            <?php echo $task['related_type'] === 'customer' && $task['related_id'] == $customer['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3" id="lead_select" style="display: none;">
                            <label for="related_lead" class="form-label">انتخاب لید</label>
                            <select class="form-select" id="related_lead" name="related_id">
                                <option value="">انتخاب لید</option>
                                <?php foreach ($leads as $lead): ?>
                                    <option value="<?php echo $lead['id']; ?>" 
                                            <?php echo $task['related_type'] === 'lead' && $task['related_id'] == $lead['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lead['title'] . ' - ' . $lead['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- دکمه‌های عملیات -->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <a href="tasks.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-right me-2"></i>
                                    بازگشت
                                </a>
                                
                                <div>
                                    <button type="button" class="btn btn-outline-primary me-2" onclick="clearForm()">
                                        <i class="fas fa-undo me-2"></i>
                                        پاک کردن فرم
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        <?php echo $task_id ? 'بروزرسانی' : 'ذخیره'; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleRelatedOptions() {
    const relatedType = document.getElementById('related_type').value;
    const customerSelect = document.getElementById('customer_select');
    const leadSelect = document.getElementById('lead_select');
    
    customerSelect.style.display = 'none';
    leadSelect.style.display = 'none';
    
    if (relatedType === 'customer') {
        customerSelect.style.display = 'block';
        document.getElementById('related_lead').name = '';
        document.getElementById('related_customer').name = 'related_id';
    } else if (relatedType === 'lead') {
        leadSelect.style.display = 'block';
        document.getElementById('related_customer').name = '';
        document.getElementById('related_lead').name = 'related_id';
    } else {
        document.getElementById('related_customer').name = '';
        document.getElementById('related_lead').name = '';
    }
}

function clearForm() {
    if (confirm('آیا از پاک کردن فرم مطمئن هستید؟')) {
        document.getElementById('taskForm').reset();
        toggleRelatedOptions();
    }
}

function setQuickDateTime(hours) {
    const now = new Date();
    now.setHours(now.getHours() + hours);
    document.getElementById('due_date').value = now.toISOString().slice(0, 16);
    
    // Set reminder 1 hour before due date
    const reminder = new Date(now);
    reminder.setHours(reminder.getHours() - 1);
    document.getElementById('reminder_datetime').value = reminder.toISOString().slice(0, 16);
}

// Initialize form
document.addEventListener('DOMContentLoaded', function() {
    toggleRelatedOptions();
    enableAutoSave('taskForm');
    
    // Quick date buttons
    const dueDateContainer = document.getElementById('due_date').parentElement;
    const quickButtons = document.createElement('div');
    quickButtons.className = 'mt-2';
    quickButtons.innerHTML = `
        <small class="text-muted d-block mb-1">تنظیم سریع:</small>
        <button type="button" class="btn btn-outline-secondary btn-sm me-1" onclick="setQuickDateTime(1)">1 ساعت</button>
        <button type="button" class="btn btn-outline-secondary btn-sm me-1" onclick="setQuickDateTime(24)">فردا</button>
        <button type="button" class="btn btn-outline-secondary btn-sm me-1" onclick="setQuickDateTime(168)">1 هفته</button>
    `;
    dueDateContainer.appendChild(quickButtons);
});
</script>

<?php include __DIR__ . '/../private/footer.php'; ?>

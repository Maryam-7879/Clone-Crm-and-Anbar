<?php
$page_title = 'مدیریت کاربران';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'کاربران']
];

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
if (!hasRole('admin')) {
    setMessage('شما دسترسی لازم برای مشاهده این صفحه را ندارید', 'error');
    header('Location: dashboard.php');
    exit();
}

$errors = [];

// پردازش درخواست‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        $user_id = (int)$_POST['user_id'];
        
        // جلوگیری از حذف خودی
        if ($user_id == $_SESSION['user_id']) {
            setMessage('نمی‌توانید خودتان را حذف کنید', 'error');
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                
                if ($stmt->rowCount() > 0) {
                    logActivity($_SESSION['user_id'], 'delete_user', 'users', $user_id);
                    setMessage('کاربر با موفقیت حذف شد', 'success');
                } else {
                    setMessage('کاربر یافت نشد', 'error');
                }
            } catch (PDOException $e) {
                error_log("خطا در حذف کاربر: " . $e->getMessage());
                setMessage('خطا در حذف کاربر', 'error');
            }
        }
    }
    
    if ($action === 'toggle_status') {
        $user_id = (int)$_POST['user_id'];
        $new_status = $_POST['new_status'];
        
        // جلوگیری از غیرفعال کردن خودی
        if ($user_id == $_SESSION['user_id']) {
            setMessage('نمی‌توانید وضعیت خودتان را تغییر دهید', 'error');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    logActivity($_SESSION['user_id'], 'toggle_user_status', 'users', $user_id, ['status' => $new_status]);
                    setMessage('وضعیت کاربر بروزرسانی شد', 'success');
                }
            } catch (PDOException $e) {
                error_log("خطا در تغییر وضعیت کاربر: " . $e->getMessage());
                setMessage('خطا در تغییر وضعیت کاربر', 'error');
            }
        }
    }
    
    if ($action === 'quick_add') {
        $username = sanitizeInput($_POST['quick_username']);
        $email = sanitizeInput($_POST['quick_email']);
        $first_name = sanitizeInput($_POST['quick_first_name']);
        $last_name = sanitizeInput($_POST['quick_last_name']);
        $role = $_POST['quick_role'];
        $password = generateRandomPassword();
        
        // اعتبارسنجی
        if (empty($username) || empty($email) || empty($first_name) || empty($last_name)) {
            setMessage('لطفاً تمام فیلدهای الزامی را پر کنید', 'error');
        } elseif (!validateEmail($email)) {
            setMessage('فرمت ایمیل صحیح نیست', 'error');
        } else {
            try {
                // بررسی تکراری بودن
                $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
                $check->execute([$username, $email]);
                
                if ($check->fetchColumn() > 0) {
                    setMessage('نام کاربری یا ایمیل قبلاً ثبت شده است', 'error');
                } else {
                    $hashed_password = hashPassword($password);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password, first_name, last_name, role) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$username, $email, $hashed_password, $first_name, $last_name, $role]);
                    
                    logActivity($_SESSION['user_id'], 'create_user', 'users', $pdo->lastInsertId());
                    setMessage("کاربر جدید اضافه شد. رمز عبور موقت: $password", 'success');
                }
            } catch (PDOException $e) {
                error_log("خطا در افزودن کاربر: " . $e->getMessage());
                setMessage('خطا در افزودن کاربر', 'error');
            }
        }
    }
}

// دریافت فیلترها
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = RECORDS_PER_PAGE;
$offset = ($page - 1) * $per_page;

// ساخت کوئری
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if ($role) {
    $where_conditions[] = "role = ?";
    $params[] = $role;
}

if ($status) {
    $where_conditions[] = "status = ?";
    $params[] = $status;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// دریافت تعداد کل رکوردها
$count_sql = "SELECT COUNT(*) FROM users $where_clause";
$total_records = $pdo->prepare($count_sql);
$total_records->execute($params);
$total_records = $total_records->fetchColumn();

// دریافت کاربران
$sql = "
    SELECT *
    FROM users
    $where_clause
    ORDER BY created_at DESC
    LIMIT $per_page OFFSET $offset
";

$users = $pdo->prepare($sql);
$users->execute($params);
$users = $users->fetchAll();

// آمار کاربران
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
    'admins' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
    'online' => $pdo->query("SELECT COUNT(*) FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)")->fetchColumn(),
];

$csrf_token = generateCSRFToken();

include __DIR__ . '/../private/header.php';
?>

<!-- آمار کوتاه -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-primary"><?php echo number_format($stats['total']); ?></h5>
                <small class="text-muted">کل کاربران</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-success"><?php echo number_format($stats['active']); ?></h5>
                <small class="text-muted">فعال</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-warning"><?php echo number_format($stats['admins']); ?></h5>
                <small class="text-muted">مدیران</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-info"><?php echo number_format($stats['online']); ?></h5>
                <small class="text-muted">آنلاین</small>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center align-items-start mb-4 gap-3">
    <div>
        <h4 class="mb-1">مدیریت کاربران</h4>
        <p class="text-muted mb-0">مشاهده و مدیریت کاربران سیستم</p>
    </div>
    
    <div>
        <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#quickAddModal">
            <i class="fas fa-plus-circle me-2"></i>
            افزودن سریع
        </button>
        <a href="user_form.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>
            افزودن کاربر جدید
        </a>
    </div>
</div>

<!-- فیلترها -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-4 col-md-6 col-12">
                <label class="form-label">جستجو</label>
                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="نام کاربری، ایمیل، نام...">
            </div>
            
            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label">نقش</label>
                <select class="form-select" name="role">
                    <option value="">همه نقش‌ها</option>
                    <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>مدیر کل</option>
                    <option value="manager" <?php echo $role === 'manager' ? 'selected' : ''; ?>>مدیر</option>
                    <option value="sales" <?php echo $role === 'sales' ? 'selected' : ''; ?>>فروشنده</option>
                    <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>کاربر</option>
                </select>
            </div>
            
            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label">وضعیت</label>
                <select class="form-select" name="status">
                    <option value="">همه</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>فعال</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>غیرفعال</option>
                    <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>تعلیق شده</option>
                </select>
            </div>
            
            <div class="col-lg-2 col-md-12 col-12">
                <label class="form-label d-none d-lg-block">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-search me-1"></i>
                        <span class="d-none d-md-inline">جستجو</span>
                        <span class="d-md-none">جستجو</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- جدول کاربران -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-users me-2"></i>
            لیست کاربران
            <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?></span>
        </h5>
        
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('usersTable', 'users.csv')">
                <i class="fas fa-download me-1"></i>
                خروجی CSV
            </button>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (empty($users)): ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">کاربری یافت نشد</h5>
                <p class="text-muted">برای شروع، کاربر جدیدی اضافه کنید</p>
                <a href="user_form.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>
                    افزودن کاربر اول
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="usersTable">
                    <thead>
                        <tr>
                            <th>کاربر</th>
                            <th>ایمیل</th>
                            <th>نقش</th>
                            <th>وضعیت</th>
                            <th>آخرین ورود</th>
                            <th>تاریخ ثبت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php
                            $is_online = $user['last_login'] && strtotime($user['last_login']) > strtotime('-30 minutes');
                            $is_current_user = $user['id'] == $_SESSION['user_id'];
                            ?>
                            <tr class="<?php echo $is_current_user ? 'table-info' : ''; ?>">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-3" style="width: 40px; height: 40px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; position: relative;">
                                            <i class="fas fa-user"></i>
                                            <?php if ($is_online): ?>
                                                <span class="position-absolute top-0 start-100 translate-middle p-1 bg-success border border-light rounded-circle">
                                                    <span class="visually-hidden">آنلاین</span>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold">
                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                <?php if ($is_current_user): ?>
                                                    <span class="badge bg-info ms-1">شما</span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($user['email']); ?></div>
                                    <?php if ($user['phone']): ?>
                                        <small class="text-muted"><?php echo formatPhone($user['phone']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'manager' ? 'warning' : 'primary'); ?>">
                                        <?php echo getRoleTitle($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm badge bg-<?php echo getStatusClass($user['status']); ?> dropdown-toggle" 
                                                type="button" data-bs-toggle="dropdown"
                                                <?php echo $is_current_user ? 'disabled' : ''; ?>>
                                            <?php echo getStatusTitle($user['status']); ?>
                                        </button>
                                        <?php if (!$is_current_user): ?>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="toggleUserStatus(<?php echo $user['id']; ?>, 'active')">فعال</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="toggleUserStatus(<?php echo $user['id']; ?>, 'inactive')">غیرفعال</a></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="toggleUserStatus(<?php echo $user['id']; ?>, 'suspended')">تعلیق</a></li>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($user['last_login']): ?>
                                        <small class="text-muted" title="<?php echo formatPersianDate($user['last_login']); ?>">
                                            <?php echo formatPersianDate($user['last_login'], 'Y/m/d H:i'); ?>
                                            <?php if ($is_online): ?>
                                                <span class="text-success ms-1">آنلاین</span>
                                            <?php endif; ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">هرگز</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo formatPersianDate($user['created_at'], 'Y/m/d'); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="user_view.php?id=<?php echo $user['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm" 
                                           data-bs-toggle="tooltip" title="مشاهده جزئیات">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <a href="user_form.php?id=<?php echo $user['id']; ?>" 
                                           class="btn btn-outline-warning btn-sm"
                                           data-bs-toggle="tooltip" title="ویرایش">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if (!$is_current_user): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-danger btn-sm"
                                                    onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
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
                    $base_url = 'users.php?' . http_build_query(array_filter([
                        'search' => $search,
                        'role' => $role,
                        'status' => $status
                    ]));
                    echo createPagination($page, $total_records, $per_page, $base_url);
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal افزودن سریع -->
<div class="modal fade" id="quickAddModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">افزودن سریع کاربر</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="quick_add">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="quick_first_name" class="form-label">نام <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="quick_first_name" name="quick_first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="quick_last_name" class="form-label">نام خانوادگی <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="quick_last_name" name="quick_last_name" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quick_username" class="form-label">نام کاربری <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="quick_username" name="quick_username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quick_email" class="form-label">ایمیل <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="quick_email" name="quick_email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quick_role" class="form-label">نقش <span class="text-danger">*</span></label>
                        <select class="form-select" id="quick_role" name="quick_role" required>
                            <option value="user">کاربر</option>
                            <option value="sales">فروشنده</option>
                            <option value="manager">مدیر</option>
                            <option value="admin">مدیر کل</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        رمز عبور موقت به صورت خودکار تولید و نمایش داده می‌شود.
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

<script>
function deleteUser(userId, username) {
    confirmDelete(`آیا از حذف کاربر "${username}" مطمئن هستید؟`).then((confirmed) => {
        if (confirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="${userId}">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function toggleUserStatus(userId, newStatus) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="user_id" value="${userId}">
        <input type="hidden" name="new_status" value="${newStatus}">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Initialize table sorting
document.addEventListener('DOMContentLoaded', function() {
    initTableSort('usersTable');
});
</script>

<?php include __DIR__ . '/../private/footer.php'; ?>

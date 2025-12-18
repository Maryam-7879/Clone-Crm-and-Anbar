<?php
$page_title = 'گزارش فعالیت‌ها';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'گزارش فعالیت‌ها']
];

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
if (!hasPermission('view_activity_logs')) {
    setMessage('شما دسترسی لازم برای مشاهده این صفحه را ندارید', 'error');
    header('Location: dashboard.php');
    exit();
}

// پارامترهای فیلتر
$user_filter = $_GET['user_id'] ?? '';
$action_filter = $_GET['action'] ?? '';
$table_filter = $_GET['table_name'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = trim($_GET['search'] ?? '');
$per_page = (int)($_GET['per_page'] ?? 50);
$page = (int)($_GET['page'] ?? 1);

// ساخت کوئری
$where_conditions = [];
$params = [];

if ($user_filter) {
    $where_conditions[] = "al.user_id = ?";
    $params[] = $user_filter;
}

if ($action_filter) {
    $where_conditions[] = "al.action = ?";
    $params[] = $action_filter;
}

if ($table_filter) {
    $where_conditions[] = "al.table_name = ?";
    $params[] = $table_filter;
}

if ($date_from) {
    $where_conditions[] = "DATE(al.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(al.created_at) <= ?";
    $params[] = $date_to;
}

if ($search) {
    $where_conditions[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR al.details LIKE ? OR al.action LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

try {
    // شمارش کل رکوردها
    $count_query = "
        SELECT COUNT(*) 
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        $where_clause
    ";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    
    // محاسبه صفحه‌بندی
    $total_pages = ceil($total_records / $per_page);
    $offset = ($page - 1) * $per_page;
    
    // دریافت داده‌ها
    $query = "
        SELECT 
            al.*,
            CONCAT(u.first_name, ' ', u.last_name) as user_name,
            u.role as user_role
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        $where_clause
        ORDER BY al.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $activities = $stmt->fetchAll();
    
    // دریافت فیلترها
    $users = $pdo->query("
        SELECT id, first_name, last_name 
        FROM users 
        ORDER BY first_name, last_name
    ")->fetchAll();
    
    $actions = $pdo->query("
        SELECT DISTINCT action 
        FROM activity_logs 
        ORDER BY action
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    $tables = $pdo->query("
        SELECT DISTINCT table_name 
        FROM activity_logs 
        WHERE table_name IS NOT NULL
        ORDER BY table_name
    ")->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    error_log("خطا در دریافت لاگ فعالیت‌ها: " . $e->getMessage());
    $activities = [];
    $total_records = 0;
    $total_pages = 0;
}

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">گزارش فعالیت‌ها</h4>
        <p class="text-muted mb-0">مشاهده و پیگیری تمام فعالیت‌های انجام شده در سیستم</p>
    </div>
    
    <div>
        <button type="button" class="btn btn-outline-secondary" onclick="exportLogs()">
            <i class="fas fa-download me-2"></i>
            دریافت گزارش
        </button>
    </div>
</div>

<!-- فیلترهای جستجو -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            فیلترهای جستجو
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" id="filterForm">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">کاربر</label>
                    <select class="form-select" name="user_id">
                        <option value="">همه کاربران</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label class="form-label">نوع عملیات</label>
                    <select class="form-select" name="action">
                        <option value="">همه عملیات</option>
                        <?php foreach ($actions as $action): ?>
                            <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $action_filter === $action ? 'selected' : ''; ?>>
                                <?php echo getActionTitle($action); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label class="form-label">بخش</label>
                    <select class="form-select" name="table_name">
                        <option value="">همه بخش‌ها</option>
                        <?php foreach ($tables as $table): ?>
                            <option value="<?php echo htmlspecialchars($table); ?>" <?php echo $table_filter === $table ? 'selected' : ''; ?>>
                                <?php echo getTableTitle($table); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label class="form-label">جستجو</label>
                    <input type="text" class="form-control" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="جستجو در کاربران و جزئیات...">
                </div>
                
                <div class="col-md-3 mb-3">
                    <label class="form-label">از تاریخ</label>
                    <input type="date" class="form-control" name="date_from" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="col-md-3 mb-3">
                    <label class="form-label">تا تاریخ</label>
                    <input type="date" class="form-control" name="date_to" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <div class="col-md-3 mb-3">
                    <label class="form-label">تعداد در صفحه</label>
                    <select class="form-select" name="per_page">
                        <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>
                
                <div class="col-md-3 mb-3 d-flex align-items-end">
                    <div class="d-flex gap-2 w-100">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="fas fa-search me-1"></i>
                            جستجو
                        </button>
                        <a href="activity_logs.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- نتایج -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>
                فعالیت‌ها 
                <span class="badge bg-secondary"><?php echo number_format($total_records); ?> مورد</span>
            </h5>
            
            <?php if ($total_pages > 1): ?>
                <small class="text-muted">
                    صفحه <?php echo number_format($page); ?> از <?php echo number_format($total_pages); ?>
                </small>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card-body p-0">
        <?php if (empty($activities)): ?>
            <div class="text-center py-5">
                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">هیچ فعالیتی یافت نشد</h5>
                <p class="text-muted">با تغییر فیلترها مجدداً جستجو کنید</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="15%">تاریخ و زمان</th>
                            <th width="15%">کاربر</th>
                            <th width="15%">عملیات</th>
                            <th width="10%">بخش</th>
                            <th width="30%">جزئیات</th>
                            <th width="10%">IP</th>
                            <th width="5%">جزئیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $activity): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo formatPersianDate($activity['created_at'], 'Y/m/d'); ?></div>
                                    <small class="text-muted"><?php echo formatPersianDate($activity['created_at'], 'H:i:s'); ?></small>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-2" style="width: 32px; height: 32px; background: var(--<?php echo getRoleClass($activity['user_role']); ?>-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8rem;">
                                            <?php echo strtoupper(substr($activity['user_name'] ?? 'U', 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($activity['user_name'] ?? 'کاربر حذف شده'); ?></div>
                                            <?php if ($activity['user_role']): ?>
                                                <small class="text-muted"><?php echo getRoleTitle($activity['user_role']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo getActionClass($activity['action']); ?>">
                                        <?php echo getActionTitle($activity['action']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($activity['table_name']): ?>
                                        <span class="badge bg-info">
                                            <?php echo getTableTitle($activity['table_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($activity['details']): ?>
                                        <div class="activity-details">
                                            <?php 
                                            $details = $activity['details'];
                                            if (strlen($details) > 100) {
                                                echo '<span class="details-short">' . htmlspecialchars(substr($details, 0, 100)) . '...</span>';
                                                echo '<span class="details-full d-none">' . htmlspecialchars($details) . '</span>';
                                                echo '<a href="#" class="toggle-details text-primary ms-1" onclick="toggleDetails(this); return false;">نمایش بیشتر</a>';
                                            } else {
                                                echo htmlspecialchars($details);
                                            }
                                            ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">بدون جزئیات</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($activity['ip_address']): ?>
                                        <code class="small"><?php echo htmlspecialchars($activity['ip_address']); ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($activity['table_name'] && $activity['record_id']): ?>
                                        <a href="#" class="btn btn-outline-primary btn-sm" 
                                           onclick="showActivityModal(<?php echo htmlspecialchars(json_encode($activity)); ?>)">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($total_pages > 1): ?>
    <div class="card-footer">
        <nav aria-label="صفحه‌بندی">
            <ul class="pagination justify-content-center mb-0">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                            <?php echo number_format($i); ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Modal جزئیات فعالیت -->
<div class="modal fade" id="activityModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">جزئیات فعالیت</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="activityModalBody">
                <!-- محتوای modal اینجا لود می‌شود -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
            </div>
        </div>
    </div>
</div>

<script>
function toggleDetails(link) {
    const container = link.parentElement;
    const shortText = container.querySelector('.details-short');
    const fullText = container.querySelector('.details-full');
    
    if (fullText.classList.contains('d-none')) {
        shortText.classList.add('d-none');
        fullText.classList.remove('d-none');
        link.textContent = 'نمایش کمتر';
    } else {
        shortText.classList.remove('d-none');
        fullText.classList.add('d-none');
        link.textContent = 'نمایش بیشتر';
    }
}

function showActivityModal(activity) {
    const modal = new bootstrap.Modal(document.getElementById('activityModal'));
    const modalBody = document.getElementById('activityModalBody');
    
    let content = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted">اطلاعات فعالیت</h6>
                <table class="table table-sm">
                    <tr>
                        <td class="fw-bold">تاریخ و زمان:</td>
                        <td>${formatPersianDate(activity.created_at)}</td>
                    </tr>
                    <tr>
                        <td class="fw-bold">کاربر:</td>
                        <td>${activity.user_name || 'کاربر حذف شده'}</td>
                    </tr>
                    <tr>
                        <td class="fw-bold">عملیات:</td>
                        <td><span class="badge bg-primary">${getActionTitle(activity.action)}</span></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">بخش:</td>
                        <td>${activity.table_name ? getTableTitle(activity.table_name) : '-'}</td>
                    </tr>
                    <tr>
                        <td class="fw-bold">شناسه رکورد:</td>
                        <td>${activity.record_id || '-'}</td>
                    </tr>
                    <tr>
                        <td class="fw-bold">آدرس IP:</td>
                        <td><code>${activity.ip_address || '-'}</code></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">جزئیات</h6>
                <div class="bg-light p-3 rounded">
                    <pre class="mb-0 small">${activity.details || 'بدون جزئیات'}</pre>
                </div>
            </div>
        </div>
    `;
    
    modalBody.innerHTML = content;
    modal.show();
}

function exportLogs() {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('export', 'csv');
    window.open(currentUrl.toString(), '_blank');
}

function formatPersianDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fa-IR') + ' ' + date.toLocaleTimeString('fa-IR');
}

function getActionTitle(action) {
    const actions = {
        'login': 'ورود',
        'logout': 'خروج',
        'create_customer': 'ایجاد مشتری',
        'update_customer': 'بروزرسانی مشتری',
        'delete_customer': 'حذف مشتری',
        'create_lead': 'ایجاد لید',
        'update_lead': 'بروزرسانی لید',
        'delete_lead': 'حذف لید',
        'create_task': 'ایجاد وظیفه',
        'update_task': 'بروزرسانی وظیفه',
        'delete_task': 'حذف وظیفه',
        'create_sale': 'ایجاد فروش',
        'update_sale': 'بروزرسانی فروش',
        'delete_sale': 'حذف فروش',
        'create_product': 'ایجاد محصول',
        'update_product': 'بروزرسانی محصول',
        'delete_product': 'حذف محصول',
        'create_user': 'ایجاد کاربر',
        'update_user': 'بروزرسانی کاربر',
        'delete_user': 'حذف کاربر'
    };
    return actions[action] || action;
}

function getTableTitle(table) {
    const tables = {
        'customers': 'مشتریان',
        'leads': 'لیدها',
        'tasks': 'وظایف',
        'sales': 'فروش‌ها',
        'products': 'محصولات',
        'users': 'کاربران'
    };
    return tables[table] || table;
}
</script>

<style>
.user-avatar {
    flex-shrink: 0;
}

.activity-details {
    max-width: 300px;
}

.toggle-details {
    font-size: 0.85rem;
    text-decoration: none;
}

.toggle-details:hover {
    text-decoration: underline;
}

code {
    font-size: 0.8rem;
    padding: 2px 4px;
    background: var(--bg-light);
    border-radius: 3px;
}

.badge {
    font-size: 0.75rem;
}

.pagination .page-link {
    color: var(--primary-color);
    border-color: var(--border-color);
}

.pagination .page-link:hover {
    background: var(--primary-ultralight);
    border-color: var(--primary-color);
}

.pagination .page-item.active .page-link {
    background: var(--primary-color);
    border-color: var(--primary-color);
}

pre {
    white-space: pre-wrap;
    word-wrap: break-word;
    max-height: 200px;
    overflow-y: auto;
}
</style>

<?php include __DIR__ . '/../private/footer.php'; ?>

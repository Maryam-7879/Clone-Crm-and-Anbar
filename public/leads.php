<?php
$page_title = 'مدیریت لیدها';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'لیدها']
];

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
if (!hasPermission('view_leads')) {
    setMessage('شما دسترسی لازم برای مشاهده این صفحه را ندارید', 'error');
    header('Location: dashboard.php');
    exit();
}

// پردازش درخواست‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete' && hasPermission('delete_lead')) {
        $lead_id = (int)$_POST['lead_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ?");
            $stmt->execute([$lead_id]);
            
            if ($stmt->rowCount() > 0) {
                logActivity($_SESSION['user_id'], 'delete_lead', 'leads', $lead_id);
                setMessage('لید با موفقیت حذف شد', 'success');
            } else {
                setMessage('لید یافت نشد', 'error');
            }
        } catch (PDOException $e) {
            error_log("خطا در حذف لید: " . $e->getMessage());
            setMessage('خطا در حذف لید', 'error');
        }
    }
    
    if ($action === 'update_status' && hasPermission('edit_lead')) {
        $lead_id = (int)$_POST['lead_id'];
        $new_status = $_POST['new_status'];
        
        try {
            $stmt = $pdo->prepare("UPDATE leads SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $lead_id]);
            
            if ($stmt->rowCount() > 0) {
                logActivity($_SESSION['user_id'], 'update_lead_status', 'leads', $lead_id, ['status' => $new_status]);
                setMessage('وضعیت لید بروزرسانی شد', 'success');
            }
        } catch (PDOException $e) {
            error_log("خطا در بروزرسانی وضعیت لید: " . $e->getMessage());
            setMessage('خطا در بروزرسانی وضعیت', 'error');
        }
    }
}

// دریافت فیلترها
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$assigned_to = $_GET['assigned_to'] ?? '';
$source = $_GET['source'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = RECORDS_PER_PAGE;
$offset = ($page - 1) * $per_page;

// ساخت کوئری
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(l.first_name LIKE ? OR l.last_name LIKE ? OR l.email LIKE ? OR l.phone LIKE ? OR l.company LIKE ? OR l.title LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
}

if ($status) {
    $where_conditions[] = "l.status = ?";
    $params[] = $status;
}

if ($priority) {
    $where_conditions[] = "l.priority = ?";
    $params[] = $priority;
}

if ($assigned_to) {
    $where_conditions[] = "l.assigned_to = ?";
    $params[] = $assigned_to;
}

if ($source) {
    $where_conditions[] = "l.source = ?";
    $params[] = $source;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// دریافت تعداد کل رکوردها
$count_sql = "SELECT COUNT(*) FROM leads l $where_clause";
$total_records = $pdo->prepare($count_sql);
$total_records->execute($params);
$total_records = $total_records->fetchColumn();

// دریافت لیدها
$sql = "
    SELECT 
        l.*,
        CONCAT(u.first_name, ' ', u.last_name) as assigned_user,
        CONCAT(cb.first_name, ' ', cb.last_name) as created_user
    FROM leads l
    LEFT JOIN users u ON l.assigned_to = u.id
    LEFT JOIN users cb ON l.created_by = cb.id
    $where_clause
    ORDER BY 
        CASE l.priority 
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
        END,
        l.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$leads = $pdo->prepare($sql);
$leads->execute($params);
$leads = $leads->fetchAll();

// دریافت کاربران برای فیلتر
$users = $pdo->query("SELECT id, first_name, last_name FROM users WHERE status = 'active' ORDER BY first_name")->fetchAll();

// دریافت منابع
$sources_result = $pdo->query("SELECT DISTINCT source FROM leads WHERE source IS NOT NULL AND source != '' ORDER BY source")->fetchAll();
$sources = array_column($sources_result, 'source');

// آمار لیدها
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn(),
    'new' => $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'new'")->fetchColumn(),
    'contacted' => $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'contacted'")->fetchColumn(),
    'qualified' => $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'qualified'")->fetchColumn(),
    'won' => $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'won'")->fetchColumn(),
    'lost' => $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'lost'")->fetchColumn(),
];

include __DIR__ . '/../private/header.php';
?>

<!-- آمار کوتاه -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-primary"><?php echo number_format($stats['total']); ?></h5>
                <small class="text-muted">کل لیدها</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-info"><?php echo number_format($stats['new']); ?></h5>
                <small class="text-muted">جدید</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-warning"><?php echo number_format($stats['contacted']); ?></h5>
                <small class="text-muted">تماس گرفته شده</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-secondary"><?php echo number_format($stats['qualified']); ?></h5>
                <small class="text-muted">واجد شرایط</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-success"><?php echo number_format($stats['won']); ?></h5>
                <small class="text-muted">موفق</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-danger"><?php echo number_format($stats['lost']); ?></h5>
                <small class="text-muted">از دست رفته</small>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center align-items-start mb-4 gap-3">
    <div>
        <h4 class="mb-1">مدیریت لیدها</h4>
        <p class="text-muted mb-0">مشاهده و مدیریت فرصت‌های فروش</p>
    </div>
    
    <?php if (hasPermission('add_lead')): ?>
        <a href="lead_form.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>
            افزودن لید جدید
        </a>
    <?php endif; ?>
</div>

<!-- فیلترها -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label">جستجو</label>
                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="نام، ایمیل، شرکت...">
            </div>
            
            <div class="col-lg-2 col-md-6 col-12">
                <label class="form-label">وضعیت</label>
                <select class="form-select" name="status">
                    <option value="">همه</option>
                    <option value="new" <?php echo $status === 'new' ? 'selected' : ''; ?>>جدید</option>
                    <option value="contacted" <?php echo $status === 'contacted' ? 'selected' : ''; ?>>تماس گرفته شده</option>
                    <option value="qualified" <?php echo $status === 'qualified' ? 'selected' : ''; ?>>واجد شرایط</option>
                    <option value="proposal" <?php echo $status === 'proposal' ? 'selected' : ''; ?>>پیشنهاد ارسال شده</option>
                    <option value="negotiation" <?php echo $status === 'negotiation' ? 'selected' : ''; ?>>در حال مذاکره</option>
                    <option value="won" <?php echo $status === 'won' ? 'selected' : ''; ?>>موفق</option>
                    <option value="lost" <?php echo $status === 'lost' ? 'selected' : ''; ?>>از دست رفته</option>
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
                <label class="form-label">منبع</label>
                <select class="form-select" name="source">
                    <option value="">همه</option>
                    <?php foreach ($sources as $src): ?>
                        <option value="<?php echo htmlspecialchars($src); ?>" <?php echo $source === $src ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($src); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-lg-2 col-md-6 col-12">
                <label class="form-label">مسئول</label>
                <select class="form-select" name="assigned_to">
                    <option value="">همه</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $assigned_to == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
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

<!-- جدول لیدها -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-bullseye me-2"></i>
            لیست لیدها
            <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?></span>
        </h5>
        
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('leadsTable', 'leads.csv')">
                <i class="fas fa-download me-1"></i>
                خروجی CSV
            </button>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (empty($leads)): ?>
            <div class="text-center py-5">
                <i class="fas fa-bullseye fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">لیدی یافت نشد</h5>
                <p class="text-muted">برای شروع، لید جدیدی اضافه کنید</p>
                <?php if (hasPermission('add_lead')): ?>
                    <a href="lead_form.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>
                        افزودن لید اول
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="leadsTable">
                    <thead>
                        <tr>
                            <th>عنوان</th>
                            <th>نام و نام خانوادگی</th>
                            <th>شرکت</th>
                            <th>تماس</th>
                            <th>ارزش</th>
                            <th>احتمال</th>
                            <th>اولویت</th>
                            <th>وضعیت</th>
                            <th>مسئول</th>
                            <th>تاریخ بسته شدن</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($lead['title']); ?></div>
                                    <?php if ($lead['source']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-tag me-1"></i>
                                            <?php echo htmlspecialchars($lead['source']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-2" style="width: 32px; height: 32px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold">
                                                <?php echo htmlspecialchars($lead['first_name'] . ' ' . $lead['last_name']); ?>
                                            </div>
                                            <?php if ($lead['position']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($lead['position']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($lead['company']): ?>
                                        <div class="fw-bold"><?php echo htmlspecialchars($lead['company']); ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($lead['email'] || $lead['phone']): ?>
                                        <?php if ($lead['email']): ?>
                                            <div>
                                                <i class="fas fa-envelope me-1 text-primary"></i>
                                                <small><?php echo htmlspecialchars($lead['email']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($lead['phone']): ?>
                                            <div>
                                                <i class="fas fa-phone me-1 text-success"></i>
                                                <small><?php echo formatPhone($lead['phone']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($lead['value'] > 0): ?>
                                        <div class="fw-bold text-success">
                                            <?php echo formatMoney($lead['value']); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                 style="width: <?php echo $lead['probability']; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo $lead['probability']; ?>%</small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo getPriorityClass($lead['priority']); ?>">
                                        <?php echo getPriorityTitle($lead['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm badge bg-<?php echo getStatusClass($lead['status'], 'lead'); ?> dropdown-toggle" 
                                                type="button" data-bs-toggle="dropdown">
                                            <?php echo getStatusTitle($lead['status'], 'lead'); ?>
                                        </button>
                                        <?php if (hasPermission('edit_lead')): ?>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $lead['id']; ?>, 'new')">جدید</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $lead['id']; ?>, 'contacted')">تماس گرفته شده</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $lead['id']; ?>, 'qualified')">واجد شرایط</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $lead['id']; ?>, 'proposal')">پیشنهاد ارسال شده</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $lead['id']; ?>, 'negotiation')">در حال مذاکره</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-success" href="#" onclick="updateStatus(<?php echo $lead['id']; ?>, 'won')">موفق</a></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="updateStatus(<?php echo $lead['id']; ?>, 'lost')">از دست رفته</a></li>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($lead['assigned_user']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-user-tie me-1"></i>
                                            <?php echo htmlspecialchars($lead['assigned_user']); ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">بدون مسئول</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($lead['expected_close_date']): ?>
                                        <small class="text-muted">
                                            <?php echo formatPersianDate($lead['expected_close_date'], 'Y/m/d'); ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="lead_view.php?id=<?php echo $lead['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm" 
                                           data-bs-toggle="tooltip" title="مشاهده جزئیات">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if (hasPermission('edit_lead')): ?>
                                            <a href="lead_form.php?id=<?php echo $lead['id']; ?>" 
                                               class="btn btn-outline-warning btn-sm"
                                               data-bs-toggle="tooltip" title="ویرایش">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (hasPermission('delete_lead')): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-danger btn-sm"
                                                    onclick="deleteLead(<?php echo $lead['id']; ?>, '<?php echo htmlspecialchars($lead['title']); ?>')"
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
                    $base_url = 'leads.php?' . http_build_query(array_filter([
                        'search' => $search,
                        'status' => $status,
                        'priority' => $priority,
                        'assigned_to' => $assigned_to,
                        'source' => $source
                    ]));
                    echo createPagination($page, $total_records, $per_page, $base_url);
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function deleteLead(leadId, leadTitle) {
    confirmDelete(`آیا از حذف لید "${leadTitle}" مطمئن هستید؟`).then((confirmed) => {
        if (confirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="lead_id" value="${leadId}">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function updateStatus(leadId, newStatus) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="lead_id" value="${leadId}">
        <input type="hidden" name="new_status" value="${newStatus}">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Initialize table sorting
document.addEventListener('DOMContentLoaded', function() {
    initTableSort('leadsTable');
});
</script>

<?php include __DIR__ . '/../private/footer.php'; ?>

<?php
$page_title = 'مدیریت هشدارها';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'هشدارها']
];

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

if (!hasPermission('view_dashboard')) {
    setMessage('شما دسترسی لازم برای این بخش را ندارید', 'error');
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $alert_id = (int)$_POST['alert_id'];
        
        try {
            $stmt = $pdo->prepare("UPDATE alerts SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$alert_id]);
            
            if ($stmt->rowCount() > 0) {
                logActivity($_SESSION['user_id'], 'delete_alert', 'alerts', $alert_id);
                setMessage('هشدار با موفقیت حذف شد', 'success');
            }
        } catch (PDOException $e) {
            error_log("خطا در حذف هشدار: " . $e->getMessage());
            setMessage('خطا در حذف هشدار', 'error');
        }
        
        header('Location: alerts.php');
        exit();
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'mark_read') {
        $alert_id = (int)$_POST['alert_id'];
        
        try {
            $stmt = $pdo->prepare("UPDATE alerts SET is_read = 1, read_at = NOW() WHERE id = ?");
            $stmt->execute([$alert_id]);
            logActivity($_SESSION['user_id'], 'mark_alert_read', 'alerts', $alert_id);
            setMessage('هشدار به عنوان خوانده شده علامت‌گذاری شد', 'success');
        } catch (PDOException $e) {
            error_log("خطا در علامت‌گذاری هشدار: " . $e->getMessage());
            setMessage('خطا در علامت‌گذاری هشدار', 'error');
        }
        
        header('Location: alerts.php');
        exit();
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
        try {
            $stmt = $pdo->prepare("UPDATE alerts SET is_read = 1, read_at = NOW() WHERE is_read = 0 AND deleted_at IS NULL");
            $stmt->execute();
            logActivity($_SESSION['user_id'], 'mark_all_alerts_read', 'alerts', null);
            setMessage('همه هشدارها به عنوان خوانده شده علامت‌گذاری شدند', 'success');
        } catch (PDOException $e) {
            error_log("خطا در علامت‌گذاری هشدارها: " . $e->getMessage());
            setMessage('خطا در علامت‌گذاری هشدارها', 'error');
        }
        
        header('Location: alerts.php');
        exit();
    }
}

$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = RECORDS_PER_PAGE;
$offset = ($page - 1) * $per_page;

$where_conditions = ['a.deleted_at IS NULL', 'p.deleted_at IS NULL'];
$params = [];

if ($type) {
    $where_conditions[] = "a.type = ?";
    $params[] = $type;
}

if ($status) {
    if ($status === 'read') {
        $where_conditions[] = "a.is_read = 1";
    } elseif ($status === 'unread') {
        $where_conditions[] = "a.is_read = 0";
    }
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$count_sql = "
    SELECT COUNT(*) 
    FROM alerts a
    INNER JOIN products p ON a.product_id = p.id
    $where_clause
";
$total_records = $pdo->prepare($count_sql);
$total_records->execute($params);
$total_records = $total_records->fetchColumn();

$sql = "
    SELECT 
        a.*,
        p.name as product_name,
        p.sku,
        c.name as category_name,
        c.color as category_color
    FROM alerts a
    INNER JOIN products p ON a.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id AND c.deleted_at IS NULL
    $where_clause
    ORDER BY a.is_read ASC, a.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$alerts = $pdo->prepare($sql);
$alerts->execute($params);
$alerts = $alerts->fetchAll();

$type_labels = [
    'low_stock' => 'موجودی کم',
    'out_of_stock' => 'تمام شده',
    'expiry' => 'انقضا',
    'custom' => 'سفارشی'
];

$type_classes = [
    'low_stock' => 'warning',
    'out_of_stock' => 'danger',
    'expiry' => 'info',
    'custom' => 'secondary'
];

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center align-items-start mb-4 gap-3">
    <div>
        <h4 class="mb-1">مدیریت هشدارها</h4>
        <p class="text-muted mb-0">مشاهده و مدیریت هشدارهای سیستم</p>
    </div>
    
    <div>
        <form method="POST" style="display: inline;" onsubmit="return confirm('آیا می‌خواهید همه هشدارها را خوانده شده علامت‌گذاری کنید؟');">
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="btn btn-outline-success me-2">
                <i class="fas fa-check-double me-2"></i>
                همه را خوانده شده
            </button>
        </form>
        <a href="alert_form.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>
            افزودن هشدار جدید
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-4 col-md-6 col-12">
                <label class="form-label">نوع هشدار</label>
                <select class="form-select" name="type">
                    <option value="">همه</option>
                    <option value="low_stock" <?php echo $type === 'low_stock' ? 'selected' : ''; ?>>موجودی کم</option>
                    <option value="out_of_stock" <?php echo $type === 'out_of_stock' ? 'selected' : ''; ?>>تمام شده</option>
                    <option value="expiry" <?php echo $type === 'expiry' ? 'selected' : ''; ?>>انقضا</option>
                    <option value="custom" <?php echo $type === 'custom' ? 'selected' : ''; ?>>سفارشی</option>
                </select>
            </div>
            
            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label">وضعیت</label>
                <select class="form-select" name="status">
                    <option value="">همه</option>
                    <option value="unread" <?php echo $status === 'unread' ? 'selected' : ''; ?>>خوانده نشده</option>
                    <option value="read" <?php echo $status === 'read' ? 'selected' : ''; ?>>خوانده شده</option>
                </select>
            </div>
            
            <div class="col-lg-5 col-md-12 col-12">
                <label class="form-label d-none d-lg-block">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-search me-1"></i>
                        جستجو
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-bell me-2"></i>
            لیست هشدارها
            <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?></span>
        </h5>
    </div>
    
    <div class="card-body">
        <?php if (empty($alerts)): ?>
            <div class="text-center py-5">
                <i class="fas fa-bell fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">هشداری یافت نشد</h5>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>نوع</th>
                            <th>عنوان</th>
                            <th>محصول</th>
                            <th>پیام</th>
                            <th>وضعیت</th>
                            <th>تاریخ</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alerts as $alert): ?>
                            <tr class="<?php echo !$alert['is_read'] ? 'table-warning' : ''; ?>">
                                <td>
                                    <span class="badge bg-<?php echo $type_classes[$alert['type']]; ?>">
                                        <?php echo $type_labels[$alert['type']]; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold">
                                        <?php if (!$alert['is_read']): ?>
                                            <i class="fas fa-circle text-warning me-1" style="font-size: 8px;"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($alert['title']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($alert['product_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($alert['sku']); ?></small>
                                </td>
                                <td>
                                    <small><?php echo truncateText(htmlspecialchars($alert['message']), 50); ?></small>
                                </td>
                                <td>
                                    <?php if ($alert['is_read']): ?>
                                        <span class="badge bg-secondary">خوانده شده</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">خوانده نشده</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo formatPersianDate($alert['created_at'], 'Y/m/d H:i'); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="alert_view.php?id=<?php echo $alert['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (!$alert['is_read']): ?>
                                            <button type="button" class="btn btn-outline-success btn-sm" 
                                                    onclick="markAsRead(<?php echo $alert['id']; ?>)">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-outline-danger btn-sm"
                                                onclick="deleteAlert(<?php echo $alert['id']; ?>, '<?php echo htmlspecialchars($alert['title']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_records > $per_page): ?>
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div>
                        نمایش <?php echo number_format($offset + 1); ?> تا <?php echo number_format(min($offset + $per_page, $total_records)); ?> 
                        از <?php echo number_format($total_records); ?> رکورد
                    </div>
                    
                    <?php
                    $base_url = 'alerts.php?' . http_build_query(array_filter([
                        'type' => $type,
                        'status' => $status
                    ]));
                    echo createPagination($page, $total_records, $per_page, $base_url);
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function markAsRead(alertId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'alerts.php';
    form.innerHTML = `
        <input type="hidden" name="action" value="mark_read">
        <input type="hidden" name="alert_id" value="${alertId}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function deleteAlert(alertId, alertTitle) {
    if (confirm(`آیا از حذف هشدار "${alertTitle}" مطمئن هستید؟`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'alerts.php';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="alert_id" value="${alertId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include __DIR__ . '/../private/footer.php'; ?>

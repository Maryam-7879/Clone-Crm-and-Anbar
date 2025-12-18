<?php
$page_title = 'مدیریت تراکنش‌ها';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'تراکنش‌ها']
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

$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = RECORDS_PER_PAGE;
$offset = ($page - 1) * $per_page;

$where_conditions = ['t.deleted_at IS NULL', 'p.deleted_at IS NULL'];
$params = [];

if ($search) {
    $where_conditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR t.reference_number LIKE ? OR t.notes LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if ($type) {
    $where_conditions[] = "t.type = ?";
    $params[] = $type;
}

if ($date_from) {
    $where_conditions[] = "DATE(t.transaction_date) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(t.transaction_date) <= ?";
    $params[] = $date_to;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$count_sql = "
    SELECT COUNT(*) 
    FROM transactions t
    INNER JOIN products p ON t.product_id = p.id
    $where_clause
";
$total_records = $pdo->prepare($count_sql);
$total_records->execute($params);
$total_records = $total_records->fetchColumn();

$sql = "
    SELECT 
        t.*,
        p.name as product_name,
        p.sku,
        p.unit,
        CONCAT(u.first_name, ' ', u.last_name) as user_name
    FROM transactions t
    INNER JOIN products p ON t.product_id = p.id
    LEFT JOIN users u ON t.user_id = u.id
    $where_clause
    ORDER BY t.transaction_date DESC, t.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$transactions = $pdo->prepare($sql);
$transactions->execute($params);
$transactions = $transactions->fetchAll();

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

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center align-items-start mb-4 gap-3">
    <div>
        <h4 class="mb-1">مدیریت تراکنش‌ها</h4>
        <p class="text-muted mb-0">مشاهده و مدیریت تراکنش‌های انبار</p>
    </div>
    
    <div>
        <a href="transaction_form.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>
            افزودن تراکنش جدید
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label">جستجو</label>
                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="محصول، SKU، شماره مرجع...">
            </div>
            
            <div class="col-lg-2 col-md-6 col-12">
                <label class="form-label">نوع</label>
                <select class="form-select" name="type">
                    <option value="">همه</option>
                    <option value="in" <?php echo $type === 'in' ? 'selected' : ''; ?>>ورود</option>
                    <option value="out" <?php echo $type === 'out' ? 'selected' : ''; ?>>خروج</option>
                    <option value="adjustment" <?php echo $type === 'adjustment' ? 'selected' : ''; ?>>تعدیل</option>
                    <option value="transfer" <?php echo $type === 'transfer' ? 'selected' : ''; ?>>انتقال</option>
                </select>
            </div>
            
            <div class="col-lg-2 col-md-6 col-12">
                <label class="form-label">از تاریخ</label>
                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            
            <div class="col-lg-2 col-md-6 col-12">
                <label class="form-label">تا تاریخ</label>
                <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            
            <div class="col-lg-3 col-md-12 col-12">
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
            <i class="fas fa-exchange-alt me-2"></i>
            لیست تراکنش‌ها
            <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?></span>
        </h5>
    </div>
    
    <div class="card-body">
        <?php if (empty($transactions)): ?>
            <div class="text-center py-5">
                <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">تراکنشی یافت نشد</h5>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>تاریخ</th>
                            <th>محصول</th>
                            <th>نوع</th>
                            <th>تعداد</th>
                            <th>قیمت واحد</th>
                            <th>جمع</th>
                            <th>کاربر</th>
                            <th>شماره مرجع</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $trans): ?>
                            <tr>
                                <td>
                                    <small><?php echo formatPersianDate($trans['transaction_date'], 'Y/m/d H:i'); ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($trans['product_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($trans['sku']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $type_classes[$trans['type']]; ?>">
                                        <?php echo $type_labels[$trans['type']]; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo number_format($trans['quantity']); ?> 
                                    <small class="text-muted"><?php echo htmlspecialchars($trans['unit']); ?></small>
                                </td>
                                <td><?php echo formatMoney($trans['unit_price']); ?></td>
                                <td>
                                    <span class="fw-bold"><?php echo formatMoney($trans['total_price']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($trans['user_name'] ?: '-'); ?></td>
                                <td>
                                    <?php if ($trans['reference_number']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($trans['reference_number']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="transaction_view.php?id=<?php echo $trans['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="transaction_edit.php?id=<?php echo $trans['id']; ?>" class="btn btn-outline-warning btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
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
                    $base_url = 'transactions.php?' . http_build_query(array_filter([
                        'search' => $search,
                        'type' => $type,
                        'date_from' => $date_from,
                        'date_to' => $date_to
                    ]));
                    echo createPagination($page, $total_records, $per_page, $base_url);
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../private/footer.php'; ?>

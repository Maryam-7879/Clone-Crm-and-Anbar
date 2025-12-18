<?php
$page_title = 'مدیریت موجودی';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'موجودی']
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
$stock_status = $_GET['stock_status'] ?? '';
$category_id = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$page = (int)($_GET['page'] ?? 1);
$per_page = RECORDS_PER_PAGE;
$offset = ($page - 1) * $per_page;

$where_conditions = ['i.deleted_at IS NULL', 'p.deleted_at IS NULL'];
$params = [];

if ($search) {
    $where_conditions[] = "(p.name LIKE ? OR p.sku LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term]);
}

if ($stock_status) {
    if ($stock_status === 'low') {
        $where_conditions[] = "i.current_stock <= p.min_stock_level AND i.current_stock > 0";
    } elseif ($stock_status === 'out') {
        $where_conditions[] = "i.current_stock = 0";
    } elseif ($stock_status === 'in_stock') {
        $where_conditions[] = "i.current_stock > 0";
    }
}

if ($category_id) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_id;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$count_sql = "
    SELECT COUNT(*) 
    FROM inventories i
    INNER JOIN products p ON i.product_id = p.id
    $where_clause
";
$total_records = $pdo->prepare($count_sql);
$total_records->execute($params);
$total_records = $total_records->fetchColumn();

$sql = "
    SELECT 
        i.*,
        p.name as product_name,
        p.sku,
        p.min_stock_level,
        p.max_stock_level,
        p.unit,
        p.cost_price,
        p.price as selling_price,
        c.name as category_name,
        c.color as category_color
    FROM inventories i
    INNER JOIN products p ON i.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id AND c.deleted_at IS NULL
    $where_clause
    ORDER BY i.last_updated_at DESC, i.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$inventories = $pdo->prepare($sql);
$inventories->execute($params);
$inventories = $inventories->fetchAll();

// دریافت دسته‌بندی‌ها برای فیلتر
try {
    $categories = $pdo->query("
        SELECT id, name 
        FROM categories 
        WHERE deleted_at IS NULL AND is_active = 1 
        ORDER BY name
    ")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center align-items-start mb-4 gap-3">
    <div>
        <h4 class="mb-1">مدیریت موجودی</h4>
        <p class="text-muted mb-0">مشاهده و مدیریت موجودی محصولات</p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-4 col-md-6 col-12">
                <label class="form-label">جستجو</label>
                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="نام یا SKU محصول...">
            </div>
            
            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label">وضعیت موجودی</label>
                <select class="form-select" name="stock_status">
                    <option value="">همه</option>
                    <option value="in_stock" <?php echo $stock_status === 'in_stock' ? 'selected' : ''; ?>>موجود</option>
                    <option value="low" <?php echo $stock_status === 'low' ? 'selected' : ''; ?>>کم</option>
                    <option value="out" <?php echo $stock_status === 'out' ? 'selected' : ''; ?>>تمام شده</option>
                </select>
            </div>
            
            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label">دسته‌بندی</label>
                <select class="form-select" name="category_id">
                    <option value="">همه دسته‌ها</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-lg-2 col-md-12 col-12">
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
            <i class="fas fa-warehouse me-2"></i>
            لیست موجودی
            <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?></span>
        </h5>
    </div>
    
    <div class="card-body">
        <?php if (empty($inventories)): ?>
            <div class="text-center py-5">
                <i class="fas fa-warehouse fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">موجودی‌ای یافت نشد</h5>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>محصول</th>
                            <th>موجودی فعلی</th>
                            <th>رزرو شده</th>
                            <th>موجود</th>
                            <th>حداقل</th>
                            <th>میانگین هزینه</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventories as $inv): 
                            $stock_status_class = 'success';
                            $stock_status_text = 'کافی';
                            if ($inv['current_stock'] == 0) {
                                $stock_status_class = 'danger';
                                $stock_status_text = 'تمام شده';
                            } elseif ($inv['current_stock'] <= $inv['min_stock_level']) {
                                $stock_status_class = 'warning';
                                $stock_status_text = 'کم';
                            }
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($inv['product_name']); ?></div>
                                    <small class="text-muted">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($inv['sku']); ?></span>
                                        <?php if ($inv['category_name']): ?>
                                            <span class="badge ms-1" style="background-color: <?php echo htmlspecialchars($inv['category_color'] ?? '#17a2b8'); ?>; color: white;">
                                                <?php echo htmlspecialchars($inv['category_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="fw-bold"><?php echo number_format($inv['current_stock']); ?></span>
                                    <small class="text-muted"><?php echo htmlspecialchars($inv['unit']); ?></small>
                                </td>
                                <td>
                                    <span class="text-warning"><?php echo number_format($inv['reserved_stock']); ?></span>
                                    <small class="text-muted"><?php echo htmlspecialchars($inv['unit']); ?></small>
                                </td>
                                <td>
                                    <span class="text-success"><?php echo number_format($inv['available_stock']); ?></span>
                                    <small class="text-muted"><?php echo htmlspecialchars($inv['unit']); ?></small>
                                </td>
                                <td>
                                    <small><?php echo number_format($inv['min_stock_level']); ?> <?php echo htmlspecialchars($inv['unit']); ?></small>
                                </td>
                                <td><?php echo formatMoney($inv['average_cost']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $stock_status_class; ?>">
                                        <?php echo $stock_status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="inventory_view.php?id=<?php echo $inv['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="inventory_edit.php?id=<?php echo $inv['id']; ?>" class="btn btn-outline-warning btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="inventory_adjust.php?product_id=<?php echo $inv['product_id']; ?>" class="btn btn-outline-info btn-sm" title="تعدیل موجودی">
                                            <i class="fas fa-adjust"></i>
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
                    $base_url = 'inventory.php?' . http_build_query(array_filter([
                        'search' => $search,
                        'stock_status' => $stock_status,
                        'category_id' => $category_id ?: null
                    ]));
                    echo createPagination($page, $total_records, $per_page, $base_url);
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../private/footer.php'; ?>

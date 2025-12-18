<?php
$page_title = 'مدیریت تامین‌کنندگان';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'تامین‌کنندگان']
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $supplier_id = (int)$_POST['supplier_id'];
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = ? AND deleted_at IS NULL");
        $stmt->execute([$supplier_id]);
        $product_count = $stmt->fetchColumn();
        
        // Soft delete supplier
        $stmt = $pdo->prepare("UPDATE suppliers SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$supplier_id]);
        
        if ($stmt->rowCount() > 0) {
            // CASCADE: حذف محصولات این تامین‌کننده
            $stmt = $pdo->prepare("UPDATE products SET deleted_at = NOW() WHERE supplier_id = ? AND deleted_at IS NULL");
            $stmt->execute([$supplier_id]);
            $products = $stmt->rowCount();
            
            if ($products > 0) {
                // CASCADE: حذف inventories, transactions, alerts محصولات
                $product_ids = $pdo->prepare("SELECT id FROM products WHERE supplier_id = ? AND deleted_at IS NOT NULL");
                $product_ids->execute([$supplier_id]);
                $product_ids = $product_ids->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($product_ids)) {
                    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
                    $pdo->prepare("UPDATE inventories SET deleted_at = NOW() WHERE product_id IN ($placeholders) AND deleted_at IS NULL")->execute($product_ids);
                    $pdo->prepare("UPDATE transactions SET deleted_at = NOW() WHERE product_id IN ($placeholders) AND deleted_at IS NULL")->execute($product_ids);
                    $pdo->prepare("UPDATE alerts SET deleted_at = NOW() WHERE product_id IN ($placeholders) AND deleted_at IS NULL")->execute($product_ids);
                }
            }
            
            logActivity($_SESSION['user_id'], 'delete_supplier', 'suppliers', $supplier_id);
            setMessage('تامین‌کننده و ' . $products . ' محصول مرتبط با موفقیت حذف شدند', 'success');
        }
    } catch (PDOException $e) {
        error_log("خطا در حذف تامین‌کننده: " . $e->getMessage());
        setMessage('خطا در حذف تامین‌کننده', 'error');
    }
    
    header('Location: suppliers.php');
    exit();
}

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = RECORDS_PER_PAGE;
$offset = ($page - 1) * $per_page;

$where_conditions = ['s.deleted_at IS NULL'];
$params = [];

if ($search) {
    $where_conditions[] = "(s.name LIKE ? OR s.contact_person LIKE ? OR s.email LIKE ? OR s.phone LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if ($status) {
    if ($status === 'active') {
        $where_conditions[] = "s.is_active = 1";
    } elseif ($status === 'inactive') {
        $where_conditions[] = "s.is_active = 0";
    }
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$count_sql = "SELECT COUNT(*) FROM suppliers s $where_clause";
$total_records = $pdo->prepare($count_sql);
$total_records->execute($params);
$total_records = $total_records->fetchColumn();

$sql = "
    SELECT 
        s.*,
        COUNT(p.id) as products_count
    FROM suppliers s
    LEFT JOIN products p ON s.id = p.supplier_id AND p.deleted_at IS NULL
    $where_clause
    GROUP BY s.id
    ORDER BY s.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$suppliers = $pdo->prepare($sql);
$suppliers->execute($params);
$suppliers = $suppliers->fetchAll();

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center align-items-start mb-4 gap-3">
    <div>
        <h4 class="mb-1">مدیریت تامین‌کنندگان</h4>
        <p class="text-muted mb-0">مشاهده و مدیریت تامین‌کنندگان</p>
    </div>
    
    <div>
        <a href="supplier_form.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>
            افزودن تامین‌کننده جدید
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-6 col-md-6 col-12">
                <label class="form-label">جستجو</label>
                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="نام، تماس، ایمیل، تلفن...">
            </div>
            
            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label">وضعیت</label>
                <select class="form-select" name="status">
                    <option value="">همه</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>فعال</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>غیرفعال</option>
                </select>
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
            <i class="fas fa-truck me-2"></i>
            لیست تامین‌کنندگان
            <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?></span>
        </h5>
    </div>
    
    <div class="card-body">
        <?php if (empty($suppliers)): ?>
            <div class="text-center py-5">
                <i class="fas fa-truck fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">تامین‌کننده‌ای یافت نشد</h5>
                <a href="supplier_form.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>
                    افزودن تامین‌کننده اول
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>نام</th>
                            <th>تماس</th>
                            <th>ایمیل</th>
                            <th>تلفن</th>
                            <th>تعداد محصولات</th>
                            <th>وضعیت</th>
                            <th>تاریخ ایجاد</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $supplier): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($supplier['name']); ?></div>
                                    <?php if ($supplier['tax_number']): ?>
                                        <small class="text-muted">کد اقتصادی: <?php echo htmlspecialchars($supplier['tax_number']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($supplier['contact_person'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($supplier['email'] ?: '-'); ?></td>
                                <td><?php echo formatPhone($supplier['phone'] ?: '-'); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo number_format($supplier['products_count']); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $supplier['is_active'] ? 'success' : 'danger'; ?>">
                                        <?php echo $supplier['is_active'] ? 'فعال' : 'غیرفعال'; ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo formatPersianDate($supplier['created_at'], 'Y/m/d'); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="supplier_view.php?id=<?php echo $supplier['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="supplier_form.php?id=<?php echo $supplier['id']; ?>" 
                                           class="btn btn-outline-warning btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-outline-danger btn-sm"
                                                onclick="deleteSupplier(<?php echo $supplier['id']; ?>, '<?php echo htmlspecialchars($supplier['name']); ?>', <?php echo $supplier['products_count']; ?>)">
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
                    $base_url = 'suppliers.php?' . http_build_query(array_filter([
                        'search' => $search,
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
function deleteSupplier(supplierId, supplierName, productsCount) {
    if (productsCount > 0) {
        if (!confirm(`این تامین‌کننده دارای ${productsCount} محصول است. آیا می‌خواهید تامین‌کننده و تمام محصولات مرتبط را حذف کنید؟`)) {
            return;
        }
    } else {
        if (!confirm(`آیا از حذف تامین‌کننده "${supplierName}" مطمئن هستید؟`)) {
            return;
        }
    }
    
    {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="supplier_id" value="${supplierId}">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include __DIR__ . '/../private/footer.php'; ?>

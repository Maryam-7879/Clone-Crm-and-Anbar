<?php
$page_title = 'مدیریت محصولات';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'محصولات']
];

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// پردازش درخواست‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        $product_id = (int)$_POST['product_id'];
        
        try {
            // Soft delete product
            $stmt = $pdo->prepare("UPDATE products SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$product_id]);
            
            if ($stmt->rowCount() > 0) {
                // CASCADE: حذف inventories, transactions, alerts
                $pdo->prepare("UPDATE inventories SET deleted_at = NOW() WHERE product_id = ? AND deleted_at IS NULL")->execute([$product_id]);
                $pdo->prepare("UPDATE transactions SET deleted_at = NOW() WHERE product_id = ? AND deleted_at IS NULL")->execute([$product_id]);
                $pdo->prepare("UPDATE alerts SET deleted_at = NOW() WHERE product_id = ? AND deleted_at IS NULL")->execute([$product_id]);
                
                logActivity($_SESSION['user_id'], 'delete_product', 'products', $product_id);
                setMessage('محصول و زیرمجموعه‌های آن با موفقیت حذف شد', 'success');
            } else {
                setMessage('محصول یافت نشد', 'error');
            }
        } catch (PDOException $e) {
            error_log("خطا در حذف محصول: " . $e->getMessage());
            setMessage('خطا در حذف محصول', 'error');
        }
    }
    
    if ($action === 'quick_add') {
        $name = sanitizeInput($_POST['quick_name']);
        $price = (float)str_replace(',', '', $_POST['quick_price']);
        $category_id = !empty($_POST['quick_category_id']) ? (int)$_POST['quick_category_id'] : null;
        
        if ($name && $price >= 0) {
            try {
                // تولید SKU خودکار
                $sku = 'PRD' . date('y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO products (name, price, category_id, sku) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$name, $price, $category_id, $sku]);
                
                logActivity($_SESSION['user_id'], 'create_product', 'products', $pdo->lastInsertId());
                setMessage('محصول جدید اضافه شد', 'success');
            } catch (PDOException $e) {
                error_log("خطا در افزودن محصول: " . $e->getMessage());
                setMessage('خطا در افزودن محصول', 'error');
            }
        }
    }
}

// دریافت فیلترها
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? ''; // برای سازگاری با لینک‌های قدیمی
$category_id = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$supplier_id = !empty($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;
$status = $_GET['status'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = RECORDS_PER_PAGE;
$offset = ($page - 1) * $per_page;

// ساخت کوئری
$where_conditions = ['p.deleted_at IS NULL'];
$params = [];

if ($search) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

if ($category_id) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_id;
} elseif ($category) {
    // برای سازگاری با لینک‌های قدیمی
    $where_conditions[] = "p.category = ?";
    $params[] = $category;
}

if ($supplier_id) {
    $where_conditions[] = "p.supplier_id = ?";
    $params[] = $supplier_id;
}

if ($status) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// دریافت تعداد کل رکوردها
$count_sql = "SELECT COUNT(*) FROM products p $where_clause";
$total_records = $pdo->prepare($count_sql);
$total_records->execute($params);
$total_records = $total_records->fetchColumn();

// دریافت محصولات با اطلاعات دسته‌بندی و تامین‌کننده
$sql = "
    SELECT p.*, c.name as category_name, c.color as category_color, c.icon as category_icon,
           s.name as supplier_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id AND c.deleted_at IS NULL
    LEFT JOIN suppliers s ON p.supplier_id = s.id AND s.deleted_at IS NULL
    $where_clause
    ORDER BY p.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$products = $pdo->prepare($sql);
$products->execute($params);
$products = $products->fetchAll();

// دریافت دسته‌بندی‌ها از جدول categories
try {
    $categories = $pdo->query("
        SELECT id, name, color, icon 
        FROM categories 
        WHERE deleted_at IS NULL AND is_active = 1 
        ORDER BY name
    ")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// دریافت تامین‌کنندگان
try {
    $suppliers = $pdo->query("
        SELECT id, name 
        FROM suppliers 
        WHERE deleted_at IS NULL AND is_active = 1 
        ORDER BY name
    ")->fetchAll();
} catch (PDOException $e) {
    $suppliers = [];
}

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center align-items-start mb-4 gap-3">
    <div>
        <h4 class="mb-1">مدیریت محصولات</h4>
        <p class="text-muted mb-0">مشاهده و مدیریت محصولات و خدمات</p>
    </div>
    
    <div>
        <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#quickAddModal">
            <i class="fas fa-plus-circle me-2"></i>
            افزودن سریع
        </button>
        <a href="product_form.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>
            افزودن محصول جدید
        </a>
    </div>
</div>

<!-- فیلترها -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-4 col-md-6 col-12">
                <label class="form-label">جستجو</label>
                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="نام، توضیحات، SKU...">
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
            
            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label">تامین‌کننده</label>
                <select class="form-select" name="supplier_id">
                    <option value="">همه تامین‌کنندگان</option>
                    <?php foreach ($suppliers as $supp): ?>
                        <option value="<?php echo $supp['id']; ?>" <?php echo $supplier_id == $supp['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($supp['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label">وضعیت</label>
                <select class="form-select" name="status">
                    <option value="">همه</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>فعال</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>غیرفعال</option>
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

<!-- جدول محصولات -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-box me-2"></i>
            لیست محصولات
            <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?></span>
        </h5>
        
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('productsTable', 'products.csv')">
                <i class="fas fa-download me-1"></i>
                خروجی CSV
            </button>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (empty($products)): ?>
            <div class="text-center py-5">
                <i class="fas fa-box fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">محصولی یافت نشد</h5>
                <p class="text-muted">برای شروع، محصول جدیدی اضافه کنید</p>
                <a href="product_form.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>
                    افزودن محصول اول
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="productsTable">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>نام محصول</th>
                            <th>دسته‌بندی</th>
                            <th>تامین‌کننده</th>
                            <th>قیمت</th>
                            <th>موجودی</th>
                            <th>وضعیت</th>
                            <th>تاریخ ایجاد</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($product['sku']); ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></div>
                                    <?php if ($product['description']): ?>
                                        <small class="text-muted">
                                            <?php echo truncateText(htmlspecialchars($product['description']), 60); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($product['category_name'])): ?>
                                        <span class="badge" style="background-color: <?php echo htmlspecialchars($product['category_color'] ?? '#17a2b8'); ?>; color: white;">
                                            <?php if ($product['category_icon']): ?>
                                                <i class="<?php echo htmlspecialchars($product['category_icon']); ?> me-1"></i>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($product['category_name']); ?>
                                        </span>
                                    <?php elseif ($product['category']): ?>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($product['category']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">بدون دسته</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($product['supplier_name'])): ?>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($product['supplier_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-success">
                                        <?php echo formatMoney($product['price']); ?>
                                    </div>
                                    <?php if (isset($product['cost_price']) && $product['cost_price'] > 0): ?>
                                        <small class="text-muted">
                                            هزینه: <?php echo formatMoney($product['cost_price']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($product['stock_quantity'] >= 0): ?>
                                        <span class="badge bg-<?php echo $product['stock_quantity'] > 10 ? 'success' : ($product['stock_quantity'] > 0 ? 'warning' : 'danger'); ?>">
                                            <?php echo number_format($product['stock_quantity']); ?> <?php echo htmlspecialchars($product['unit']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">نامحدود</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $product['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo $product['status'] === 'active' ? 'فعال' : 'غیرفعال'; ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo formatPersianDate($product['created_at'], 'Y/m/d'); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="product_view.php?id=<?php echo $product['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm" 
                                           data-bs-toggle="tooltip" title="مشاهده جزئیات">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <a href="product_form.php?id=<?php echo $product['id']; ?>" 
                                           class="btn btn-outline-warning btn-sm"
                                           data-bs-toggle="tooltip" title="ویرایش">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <button type="button" 
                                                class="btn btn-outline-danger btn-sm"
                                                onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')"
                                                data-bs-toggle="tooltip" title="حذف">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
                    $base_url = 'products.php?' . http_build_query(array_filter([
                        'search' => $search,
                        'category_id' => $category_id ?: null,
                        'supplier_id' => $supplier_id ?: null,
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
                <h5 class="modal-title">افزودن سریع محصول</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="quick_add">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="quick_name" class="form-label">نام محصول <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="quick_name" name="quick_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quick_price" class="form-label">قیمت (تومان) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="quick_price" name="quick_price" required
                               onchange="formatCurrency(this)">
                    </div>
                    
                    <div class="mb-3">
                        <label for="quick_category_id" class="form-label">دسته‌بندی</label>
                        <select class="form-select" id="quick_category_id" name="quick_category_id">
                            <option value="">بدون دسته‌بندی</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>">
                                    <?php echo htmlspecialchars($cat['name']); ?>
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

<script>
function deleteProduct(productId, productName) {
    confirmDelete(`آیا از حذف محصول "${productName}" مطمئن هستید؟`).then((confirmed) => {
        if (confirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="product_id" value="${productId}">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Initialize table sorting
document.addEventListener('DOMContentLoaded', function() {
    initTableSort('productsTable');
});
</script>

<?php include __DIR__ . '/../private/footer.php'; ?>

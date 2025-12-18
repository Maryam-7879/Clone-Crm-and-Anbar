<?php
$page_title = 'مدیریت دسته‌بندی‌ها';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'دسته‌بندی‌ها']
];

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
if (!hasPermission('view_dashboard')) {
    setMessage('شما دسترسی لازم برای این بخش را ندارید', 'error');
    header('Location: dashboard.php');
    exit();
}

// پردازش درخواست حذف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $category_id = (int)$_POST['category_id'];
    
    try {
        // بررسی اینکه آیا دسته‌بندی محصول دارد یا نه
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ? AND deleted_at IS NULL");
        $stmt->execute([$category_id]);
        $product_count = $stmt->fetchColumn();
        
        // Soft delete category
        $stmt = $pdo->prepare("UPDATE categories SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$category_id]);
        
        if ($stmt->rowCount() > 0) {
            // CASCADE: حذف محصولات این دسته‌بندی
            $stmt = $pdo->prepare("UPDATE products SET deleted_at = NOW() WHERE category_id = ? AND deleted_at IS NULL");
            $stmt->execute([$category_id]);
            $products = $stmt->rowCount();
            
            if ($products > 0) {
                // CASCADE: حذف inventories, transactions, alerts محصولات
                $product_ids = $pdo->prepare("SELECT id FROM products WHERE category_id = ? AND deleted_at IS NOT NULL");
                $product_ids->execute([$category_id]);
                $product_ids = $product_ids->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($product_ids)) {
                    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
                    $pdo->prepare("UPDATE inventories SET deleted_at = NOW() WHERE product_id IN ($placeholders) AND deleted_at IS NULL")->execute($product_ids);
                    $pdo->prepare("UPDATE transactions SET deleted_at = NOW() WHERE product_id IN ($placeholders) AND deleted_at IS NULL")->execute($product_ids);
                    $pdo->prepare("UPDATE alerts SET deleted_at = NOW() WHERE product_id IN ($placeholders) AND deleted_at IS NULL")->execute($product_ids);
                }
            }
            
            logActivity($_SESSION['user_id'], 'delete_category', 'categories', $category_id);
            setMessage('دسته‌بندی و ' . $products . ' محصول مرتبط با موفقیت حذف شدند', 'success');
        }
    } catch (PDOException $e) {
        error_log("خطا در حذف دسته‌بندی: " . $e->getMessage());
        setMessage('خطا در حذف دسته‌بندی', 'error');
    }
    
    header('Location: categories.php');
    exit();
}

// دریافت فیلترها
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = RECORDS_PER_PAGE;
$offset = ($page - 1) * $per_page;

try {
    // ساخت کوئری
    $where_conditions = ['c.deleted_at IS NULL'];
    $params = [];

    if ($search) {
        $where_conditions[] = "(c.name LIKE ? OR c.description LIKE ?)";
        $search_term = "%$search%";
        $params = array_merge($params, [$search_term, $search_term]);
    }

    if ($status) {
        if ($status === 'active') {
            $where_conditions[] = "c.is_active = 1";
        } elseif ($status === 'inactive') {
            $where_conditions[] = "c.is_active = 0";
        }
    }

    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

    // دریافت تعداد کل رکوردها
    $count_sql = "SELECT COUNT(*) FROM categories c $where_clause";
    $total_records = $pdo->prepare($count_sql);
    $total_records->execute($params);
    $total_records = $total_records->fetchColumn();

    // دریافت دسته‌بندی‌ها با تعداد محصولات
    $sql = "
        SELECT 
            c.*,
            COUNT(p.id) as products_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.deleted_at IS NULL
        $where_clause
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";

    $categories = $pdo->prepare($sql);
    $categories->execute($params);
    $categories = $categories->fetchAll();
} catch (PDOException $e) {
    error_log("خطا در دریافت دسته‌بندی‌ها: " . $e->getMessage());
    $error_message = "خطا در اتصال به دیتابیس: " . $e->getMessage();
    $categories = [];
    $total_records = 0;
}

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center align-items-start mb-4 gap-3">
    <div>
        <h4 class="mb-1">مدیریت دسته‌بندی‌ها</h4>
        <p class="text-muted mb-0">مشاهده و مدیریت دسته‌بندی‌های محصولات</p>
    </div>
    
    <div>
        <a href="category_form.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>
            افزودن دسته‌بندی جدید
        </a>
    </div>
</div>

<!-- فیلترها -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-6 col-md-6 col-12">
                <label class="form-label">جستجو</label>
                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="نام، توضیحات...">
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

<!-- جدول دسته‌بندی‌ها -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-tags me-2"></i>
            لیست دسته‌بندی‌ها
            <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?></span>
        </h5>
    </div>
    
    <div class="card-body">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <br><br>
                <strong>راه حل:</strong> لطفاً فایل <code>database/schema.sql</code> را در دیتابیس <code>crm_system_new</code> اجرا کنید.
            </div>
        <?php elseif (empty($categories)): ?>
            <div class="text-center py-5">
                <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">دسته‌بندی‌ای یافت نشد</h5>
                <p class="text-muted">برای شروع، دسته‌بندی جدیدی اضافه کنید</p>
                <a href="category_form.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>
                    افزودن دسته‌بندی اول
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>آیکون</th>
                            <th>نام</th>
                            <th>توضیحات</th>
                            <th>رنگ</th>
                            <th>تعداد محصولات</th>
                            <th>وضعیت</th>
                            <th>تاریخ ایجاد</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td>
                                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                                         style="width: 40px; height: 40px; background-color: <?php echo htmlspecialchars($category['color']); ?>20; color: <?php echo htmlspecialchars($category['color']); ?>;">
                                        <i class="<?php echo htmlspecialchars($category['icon'] ?: 'fas fa-tag'); ?>"></i>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($category['name']); ?></div>
                                </td>
                                <td>
                                    <?php if ($category['description']): ?>
                                        <small class="text-muted">
                                            <?php echo truncateText(htmlspecialchars($category['description']), 50); ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded me-2" style="width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($category['color']); ?>;"></div>
                                        <small><?php echo htmlspecialchars($category['color']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo number_format($category['products_count']); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $category['is_active'] ? 'success' : 'danger'; ?>">
                                        <?php echo $category['is_active'] ? 'فعال' : 'غیرفعال'; ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo formatPersianDate($category['created_at'], 'Y/m/d'); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="category_view.php?id=<?php echo $category['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm" 
                                           data-bs-toggle="tooltip" title="مشاهده جزئیات">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <a href="category_form.php?id=<?php echo $category['id']; ?>" 
                                           class="btn btn-outline-warning btn-sm"
                                           data-bs-toggle="tooltip" title="ویرایش">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <button type="button" 
                                                class="btn btn-outline-danger btn-sm"
                                                onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', <?php echo $category['products_count']; ?>)"
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
                    $base_url = 'categories.php?' . http_build_query(array_filter([
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
function deleteCategory(categoryId, categoryName, productsCount) {
    if (productsCount > 0) {
        if (!confirm(`این دسته‌بندی دارای ${productsCount} محصول است. آیا می‌خواهید دسته‌بندی و تمام محصولات مرتبط را حذف کنید؟`)) {
            return;
        }
    } else {
        if (!confirm(`آیا از حذف دسته‌بندی "${categoryName}" مطمئن هستید؟`)) {
            return;
        }
    }
    
    {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="category_id" value="${categoryId}">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include __DIR__ . '/../private/footer.php'; ?>

<?php
$page_title = 'مدیریت مشتریان';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'مشتریان']
];

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
if (!hasPermission('view_customers')) {
    setMessage('شما دسترسی لازم برای مشاهده این صفحه را ندارید', 'error');
    header('Location: dashboard.php');
    exit();
}

// پردازش درخواست‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete' && hasPermission('delete_customer')) {
        $customer_id = (int)$_POST['customer_id'];
        
        try {
            $pdo->beginTransaction();
            
            // CASCADE: دریافت sale_id های مربوط به مشتری
            $stmt = $pdo->prepare("SELECT id FROM sales WHERE customer_id = ?");
            $stmt->execute([$customer_id]);
            $sale_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($sale_ids)) {
                $placeholders = str_repeat('?,', count($sale_ids) - 1) . '?';
                // CASCADE: حذف sale_items
                $pdo->prepare("DELETE FROM sale_items WHERE sale_id IN ($placeholders)")->execute($sale_ids);
                // CASCADE: حذف payments
                $pdo->prepare("DELETE FROM payments WHERE sale_id IN ($placeholders)")->execute($sale_ids);
                // CASCADE: حذف sales
                $pdo->prepare("DELETE FROM sales WHERE customer_id = ?")->execute([$customer_id]);
            }
            
            // CASCADE: حذف فعالیت‌های مشتری
            $pdo->prepare("DELETE FROM customer_activities WHERE customer_id = ?")->execute([$customer_id]);
            
            // حذف مشتری
            $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->execute([$customer_id]);
            
            if ($stmt->rowCount() > 0) {
                logActivity($_SESSION['user_id'], 'delete_customer', 'customers', $customer_id);
                $message = 'مشتری با موفقیت حذف شد';
                if (count($sale_ids) > 0) {
                    $message .= ' و ' . count($sale_ids) . ' فروش مرتبط نیز حذف شدند';
                }
                setMessage($message, 'success');
            } else {
                setMessage('مشتری یافت نشد', 'error');
            }
            
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollback();
            error_log("خطا در حذف مشتری: " . $e->getMessage());
            setMessage('خطا در حذف مشتری: ' . $e->getMessage(), 'error');
        }
    }
}

// دریافت فیلترها
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';
$assigned_to = $_GET['assigned_to'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = RECORDS_PER_PAGE;
$offset = ($page - 1) * $per_page;

// ساخت کوئری
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.company_name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
}

if ($status) {
    $where_conditions[] = "c.status = ?";
    $params[] = $status;
}

if ($type) {
    $where_conditions[] = "c.customer_type = ?";
    $params[] = $type;
}

if ($assigned_to) {
    $where_conditions[] = "c.assigned_to = ?";
    $params[] = $assigned_to;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// دریافت تعداد کل رکوردها
$count_sql = "SELECT COUNT(*) FROM customers c $where_clause";
$total_records = $pdo->prepare($count_sql);
$total_records->execute($params);
$total_records = $total_records->fetchColumn();

// دریافت مشتریان
$sql = "
    SELECT 
        c.*,
        CONCAT(u.first_name, ' ', u.last_name) as assigned_user,
        (SELECT COUNT(*) FROM customer_activities WHERE customer_id = c.id) as activities_count
    FROM customers c
    LEFT JOIN users u ON c.assigned_to = u.id
    $where_clause
    ORDER BY c.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$customers = $pdo->prepare($sql);
$customers->execute($params);
$customers = $customers->fetchAll();

// دریافت کاربران برای فیلتر
$users = $pdo->query("SELECT id, first_name, last_name FROM users WHERE status = 'active' ORDER BY first_name")->fetchAll();

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center align-items-start mb-4 gap-3">
    <div>
        <h4 class="mb-1">مدیریت مشتریان</h4>
        <p class="text-muted mb-0">مشاهده و مدیریت اطلاعات مشتریان</p>
    </div>
    
    <?php if (hasPermission('add_customer')): ?>
        <a href="customer_form.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>
            افزودن مشتری جدید
        </a>
    <?php endif; ?>
</div>

<!-- فیلترها -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label">جستجو</label>
                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="نام، ایمیل، تلفن...">
            </div>
            
            <div class="col-lg-2 col-md-6 col-12">
                <label class="form-label">وضعیت</label>
                <select class="form-select" name="status">
                    <option value="">همه</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>فعال</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>غیرفعال</option>
                    <option value="prospect" <?php echo $status === 'prospect' ? 'selected' : ''; ?>>مشتری بالقوه</option>
                </select>
            </div>
            
            <div class="col-lg-2 col-md-6 col-12">
                <label class="form-label">نوع</label>
                <select class="form-select" name="type">
                    <option value="">همه</option>
                    <option value="individual" <?php echo $type === 'individual' ? 'selected' : ''; ?>>حقیقی</option>
                    <option value="company" <?php echo $type === 'company' ? 'selected' : ''; ?>>حقوقی</option>
                </select>
            </div>
            
            <div class="col-lg-3 col-md-6 col-12">
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

<!-- جدول مشتریان -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-users me-2"></i>
            لیست مشتریان
            <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?></span>
        </h5>
        
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('customersTable', 'customers.csv')">
                <i class="fas fa-download me-1"></i>
                خروجی CSV
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                <i class="fas fa-print me-1"></i>
                چاپ
            </button>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (empty($customers)): ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">مشتری‌ای یافت نشد</h5>
                <p class="text-muted">برای شروع، مشتری جدیدی اضافه کنید</p>
                <?php if (hasPermission('add_customer')): ?>
                    <a href="customer_form.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>
                        افزودن مشتری اول
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="customersTable">
                    <thead>
                        <tr>
                            <th>کد مشتری</th>
                            <th>نام و نام خانوادگی</th>
                            <th>شرکت</th>
                            <th>تماس</th>
                            <th>نوع</th>
                            <th>وضعیت</th>
                            <th>مسئول</th>
                            <th>فعالیت‌ها</th>
                            <th>تاریخ ایجاد</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($customer['customer_code']); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-2" style="width: 40px; height: 40px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold">
                                                <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                            </div>
                                            <?php if ($customer['email']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-envelope me-1"></i>
                                                    <?php echo htmlspecialchars($customer['email']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($customer['company_name']): ?>
                                        <div class="fw-bold"><?php echo htmlspecialchars($customer['company_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($customer['industry'] ?: 'صنعت نامشخص'); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($customer['phone'] || $customer['mobile']): ?>
                                        <?php if ($customer['mobile']): ?>
                                            <div>
                                                <i class="fas fa-mobile-alt me-1 text-primary"></i>
                                                <?php echo formatPhone($customer['mobile']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($customer['phone']): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-phone me-1"></i>
                                                <?php echo formatPhone($customer['phone']); ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $customer['customer_type'] === 'company' ? 'primary' : 'secondary'; ?>">
                                        <?php echo $customer['customer_type'] === 'company' ? 'حقوقی' : 'حقیقی'; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo getStatusClass($customer['status']); ?>">
                                        <?php echo getStatusTitle($customer['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($customer['assigned_user']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-user-tie me-1"></i>
                                            <?php echo htmlspecialchars($customer['assigned_user']); ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">بدون مسئول</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo number_format($customer['activities_count']); ?> فعالیت
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo formatPersianDate($customer['created_at']); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="customer_view.php?id=<?php echo $customer['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm" 
                                           data-bs-toggle="tooltip" title="مشاهده جزئیات">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if (hasPermission('edit_customer')): ?>
                                            <a href="customer_form.php?id=<?php echo $customer['id']; ?>" 
                                               class="btn btn-outline-warning btn-sm"
                                               data-bs-toggle="tooltip" title="ویرایش">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (hasPermission('delete_customer')): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-danger btn-sm"
                                                    onclick="deleteCustomer(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>')"
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
                    $base_url = 'customers.php?' . http_build_query(array_filter([
                        'search' => $search,
                        'status' => $status,
                        'type' => $type,
                        'assigned_to' => $assigned_to
                    ]));
                    echo createPagination($page, $total_records, $per_page, $base_url);
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function deleteCustomer(customerId, customerName) {
    confirmDelete(`آیا از حذف مشتری "${customerName}" مطمئن هستید؟`).then((confirmed) => {
        if (confirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="customer_id" value="${customerId}">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Initialize table sorting
document.addEventListener('DOMContentLoaded', function() {
    initTableSort('customersTable');
});
</script>

<?php include __DIR__ . '/../private/footer.php'; ?>

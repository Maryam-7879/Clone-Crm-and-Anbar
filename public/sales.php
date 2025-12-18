<?php
$page_title = 'مدیریت فروش';
$breadcrumb = [
    ['title' => 'داشبورد', 'url' => 'dashboard.php'],
    ['title' => 'فروش']
];

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
if (!hasPermission('view_sales')) {
    setMessage('شما دسترسی لازم برای مشاهده این صفحه را ندارید', 'error');
    header('Location: dashboard.php');
    exit();
}

// پردازش درخواست‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete' && hasPermission('delete_sale')) {
        $sale_id = (int)$_POST['sale_id'];
        
        try {
            $pdo->beginTransaction();
            
            // CASCADE: حذف آیتم‌های فروش
            $pdo->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$sale_id]);
            
            // CASCADE: حذف پرداخت‌ها
            $pdo->prepare("DELETE FROM payments WHERE sale_id = ?")->execute([$sale_id]);
            
            // حذف فروش
            $stmt = $pdo->prepare("DELETE FROM sales WHERE id = ?");
            $stmt->execute([$sale_id]);
            
            if ($stmt->rowCount() > 0) {
                logActivity($_SESSION['user_id'], 'delete_sale', 'sales', $sale_id);
                setMessage('فروش با موفقیت حذف شد', 'success');
            } else {
                setMessage('فروش یافت نشد', 'error');
            }
            
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollback();
            error_log("خطا در حذف فروش: " . $e->getMessage());
            setMessage('خطا در حذف فروش', 'error');
        }
    }
    
    if ($action === 'update_status' && hasPermission('edit_sale')) {
        $sale_id = (int)$_POST['sale_id'];
        $new_status = $_POST['new_status'];
        
        try {
            $stmt = $pdo->prepare("UPDATE sales SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $sale_id]);
            
            if ($stmt->rowCount() > 0) {
                logActivity($_SESSION['user_id'], 'update_sale_status', 'sales', $sale_id, ['status' => $new_status]);
                setMessage('وضعیت فروش بروزرسانی شد', 'success');
            }
        } catch (PDOException $e) {
            error_log("خطا در بروزرسانی وضعیت فروش: " . $e->getMessage());
            setMessage('خطا در بروزرسانی وضعیت', 'error');
        }
    }
}

// دریافت فیلترها
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$payment_status = $_GET['payment_status'] ?? '';
$customer_id = $_GET['customer_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = RECORDS_PER_PAGE;
$offset = ($page - 1) * $per_page;

// ساخت کوئری
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(s.sale_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

if ($status) {
    $where_conditions[] = "s.status = ?";
    $params[] = $status;
}

if ($payment_status) {
    $where_conditions[] = "s.payment_status = ?";
    $params[] = $payment_status;
}

if ($customer_id) {
    $where_conditions[] = "s.customer_id = ?";
    $params[] = $customer_id;
}

if ($date_from) {
    $where_conditions[] = "s.sale_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "s.sale_date <= ?";
    $params[] = $date_to;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// دریافت تعداد کل رکوردها
$count_sql = "SELECT COUNT(*) FROM sales s LEFT JOIN customers c ON s.customer_id = c.id $where_clause";
$total_records = $pdo->prepare($count_sql);
$total_records->execute($params);
$total_records = $total_records->fetchColumn();

// دریافت فروش‌ها
$sql = "
    SELECT 
        s.*,
        CONCAT(c.first_name, ' ', c.last_name) as customer_name,
        c.company_name,
        CONCAT(u.first_name, ' ', u.last_name) as created_user,
        (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as items_count
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN users u ON s.created_by = u.id
    $where_clause
    ORDER BY s.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$sales = $pdo->prepare($sql);
$sales->execute($params);
$sales = $sales->fetchAll();

// دریافت مشتریان برای فیلتر
$customers = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM customers ORDER BY first_name LIMIT 100")->fetchAll();

// آمار فروش
$stats = [
    'total_sales' => $pdo->query("SELECT COALESCE(SUM(final_amount), 0) FROM sales WHERE status != 'cancelled'")->fetchColumn(),
    'monthly_sales' => $pdo->query("SELECT COALESCE(SUM(final_amount), 0) FROM sales WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND status != 'cancelled'")->fetchColumn(),
    'pending_sales' => $pdo->query("SELECT COUNT(*) FROM sales WHERE status = 'pending'")->fetchColumn(),
    'confirmed_sales' => $pdo->query("SELECT COUNT(*) FROM sales WHERE status = 'confirmed'")->fetchColumn(),
];

include __DIR__ . '/../private/header.php';
?>

<!-- آمار کوتاه -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 col-12 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-success"><?php echo formatMoney($stats['total_sales']); ?></h5>
                <small class="text-muted">کل فروش</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-12 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-primary"><?php echo formatMoney($stats['monthly_sales']); ?></h5>
                <small class="text-muted">فروش این ماه</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-12 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-warning"><?php echo number_format($stats['pending_sales']); ?></h5>
                <small class="text-muted">در انتظار</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-12 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-info"><?php echo number_format($stats['confirmed_sales']); ?></h5>
                <small class="text-muted">تایید شده</small>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center align-items-start mb-4 gap-3">
    <div>
        <h4 class="mb-1">مدیریت فروش</h4>
        <p class="text-muted mb-0">مشاهده و مدیریت سفارشات و فروش</p>
    </div>
    
    <?php if (hasPermission('add_sale')): ?>
        <a href="sale_form.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>
            ثبت فروش جدید
        </a>
    <?php endif; ?>
</div>

<!-- فیلترها -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-2 col-md-6 col-12">
                <label class="form-label">جستجو</label>
                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="شماره فروش، مشتری...">
            </div>
            
            <div class="col-lg-2 col-md-6 col-12">
                <label class="form-label">وضعیت</label>
                <select class="form-select" name="status">
                    <option value="">همه</option>
                    <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>پیش‌نویس</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>در انتظار</option>
                    <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>تایید شده</option>
                    <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>تحویل شده</option>
                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>لغو شده</option>
                </select>
            </div>
            
            <div class="col-lg-2 col-md-6 col-12">
                <label class="form-label">وضعیت پرداخت</label>
                <select class="form-select" name="payment_status">
                    <option value="">همه</option>
                    <option value="pending" <?php echo $payment_status === 'pending' ? 'selected' : ''; ?>>در انتظار</option>
                    <option value="partial" <?php echo $payment_status === 'partial' ? 'selected' : ''; ?>>جزئی</option>
                    <option value="paid" <?php echo $payment_status === 'paid' ? 'selected' : ''; ?>>پرداخت شده</option>
                    <option value="refunded" <?php echo $payment_status === 'refunded' ? 'selected' : ''; ?>>برگشت داده شده</option>
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
            
            <div class="col-lg-2 col-md-6 col-12">
                <label class="form-label">&nbsp;</label>
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

<!-- جدول فروش‌ها -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-shopping-cart me-2"></i>
            لیست فروش‌ها
            <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?></span>
        </h5>
        
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('salesTable', 'sales.csv')">
                <i class="fas fa-download me-1"></i>
                خروجی CSV
            </button>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (empty($sales)): ?>
            <div class="text-center py-5">
                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">فروشی یافت نشد</h5>
                <p class="text-muted">برای شروع، فروش جدیدی ثبت کنید</p>
                <?php if (hasPermission('add_sale')): ?>
                    <a href="sale_form.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>
                        ثبت فروش اول
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="salesTable">
                    <thead>
                        <tr>
                            <th>شماره فروش</th>
                            <th>مشتری</th>
                            <th>تعداد اقلام</th>
                            <th>مبلغ نهایی</th>
                            <th>وضعیت</th>
                            <th>وضعیت پرداخت</th>
                            <th>تاریخ فروش</th>
                            <th>ثبت‌کننده</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($sale['sale_number']); ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($sale['customer_name']); ?></div>
                                    <?php if ($sale['company_name']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($sale['company_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo number_format($sale['items_count']); ?> قلم</span>
                                </td>
                                <td>
                                    <div class="fw-bold text-success"><?php echo formatMoney($sale['final_amount']); ?></div>
                                    <?php if ($sale['discount_amount'] > 0): ?>
                                        <small class="text-muted">تخفیف: <?php echo formatMoney($sale['discount_amount']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm badge bg-<?php echo getStatusClass($sale['status']); ?> dropdown-toggle" 
                                                type="button" data-bs-toggle="dropdown">
                                            <?php 
                                            $statuses = [
                                                'draft' => 'پیش‌نویس',
                                                'pending' => 'در انتظار',
                                                'confirmed' => 'تایید شده',
                                                'delivered' => 'تحویل شده',
                                                'cancelled' => 'لغو شده'
                                            ];
                                            echo $statuses[$sale['status']] ?? $sale['status'];
                                            ?>
                                        </button>
                                        <?php if (hasPermission('edit_sale')): ?>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="updateSaleStatus(<?php echo $sale['id']; ?>, 'pending')">در انتظار</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="updateSaleStatus(<?php echo $sale['id']; ?>, 'confirmed')">تایید شده</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="updateSaleStatus(<?php echo $sale['id']; ?>, 'delivered')">تحویل شده</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="updateSaleStatus(<?php echo $sale['id']; ?>, 'cancelled')">لغو شده</a></li>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $sale['payment_status'] === 'paid' ? 'success' : ($sale['payment_status'] === 'partial' ? 'warning' : 'danger'); ?>">
                                        <?php 
                                        $payment_statuses = [
                                            'pending' => 'در انتظار',
                                            'partial' => 'جزئی',
                                            'paid' => 'پرداخت شده',
                                            'refunded' => 'برگشت داده شده'
                                        ];
                                        echo $payment_statuses[$sale['payment_status']] ?? $sale['payment_status'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo formatPersianDate($sale['sale_date'], 'Y/m/d'); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($sale['created_user']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($sale['created_user']); ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="sale_view.php?id=<?php echo $sale['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm" 
                                           data-bs-toggle="tooltip" title="مشاهده جزئیات">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if (hasPermission('edit_sale')): ?>
                                            <a href="sale_form.php?id=<?php echo $sale['id']; ?>" 
                                               class="btn btn-outline-warning btn-sm"
                                               data-bs-toggle="tooltip" title="ویرایش">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="invoice.php?id=<?php echo $sale['id']; ?>" 
                                           class="btn btn-outline-info btn-sm"
                                           data-bs-toggle="tooltip" title="فاکتور">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                        
                                        <?php if (hasPermission('delete_sale')): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-danger btn-sm"
                                                    onclick="deleteSale(<?php echo $sale['id']; ?>, '<?php echo htmlspecialchars($sale['sale_number']); ?>')"
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
                    $base_url = 'sales.php?' . http_build_query(array_filter([
                        'search' => $search,
                        'status' => $status,
                        'payment_status' => $payment_status,
                        'customer_id' => $customer_id,
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

<script>
function deleteSale(saleId, saleNumber) {
    confirmDelete(`آیا از حذف فروش "${saleNumber}" مطمئن هستید؟`).then((confirmed) => {
        if (confirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="sale_id" value="${saleId}">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function updateSaleStatus(saleId, newStatus) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="sale_id" value="${saleId}">
        <input type="hidden" name="new_status" value="${newStatus}">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Initialize table sorting
document.addEventListener('DOMContentLoaded', function() {
    initTableSort('salesTable');
});
</script>

<?php include __DIR__ . '/../private/footer.php'; ?>

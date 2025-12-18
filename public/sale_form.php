<?php
$sale_id = (int)($_GET['id'] ?? 0);
$customer_id = (int)($_GET['customer_id'] ?? 0);
$lead_id = (int)($_GET['lead_id'] ?? 0);
$is_edit = $sale_id > 0;
$page_title = $is_edit ? 'ویرایش فروش' : 'فروش جدید';

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
if (!hasPermission($is_edit ? 'edit_sale' : 'add_sale')) {
    setMessage('شما دسترسی لازم برای این عملیات را ندارید', 'error');
    header('Location: sales.php');
    exit();
}

// دریافت فروش برای ویرایش
$sale = null;
if ($is_edit) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
        $stmt->execute([$sale_id]);
        $sale = $stmt->fetch();
        
        if (!$sale) {
            setMessage('فروش یافت نشد', 'error');
            header('Location: sales.php');
            exit();
        }
        
        $customer_id = $sale['customer_id'];
        $lead_id = $sale['lead_id'];
        
    } catch (PDOException $e) {
        error_log("خطا در دریافت اطلاعات فروش: " . $e->getMessage());
        setMessage('خطا در بارگذاری اطلاعات فروش', 'error');
        header('Location: sales.php');
        exit();
    }
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        setMessage('درخواست نامعتبر. لطفاً مجدداً تلاش کنید.', 'error');
    } else {
        $sale_number = trim($_POST['sale_number'] ?? '');
        $customer_id = (int)($_POST['customer_id'] ?? 0);
        $lead_id = $_POST['lead_id'] ? (int)$_POST['lead_id'] : null;
        $sale_date = $_POST['sale_date'] ?? '';
        $subtotal = (float)(str_replace(',', '', $_POST['subtotal'] ?? 0));
        $tax_amount = (float)(str_replace(',', '', $_POST['tax_amount'] ?? 0));
        $discount_amount = (float)(str_replace(',', '', $_POST['discount_amount'] ?? 0));
        $shipping_amount = (float)(str_replace(',', '', $_POST['shipping_amount'] ?? 0));
        $final_amount = (float)(str_replace(',', '', $_POST['final_amount'] ?? 0));
        $status = $_POST['status'] ?? 'pending';
        $payment_method = $_POST['payment_method'] ?? '';
        $payment_status = $_POST['payment_status'] ?? 'pending';
        
        // اگر payment_method خالی است، آن را null کنیم
        if (empty($payment_method)) {
            $payment_method = null;
        }
        
        $notes = trim($_POST['notes'] ?? '');
        $items = $_POST['items'] ?? [];
        
        $errors = [];
        
        // اعتبارسنجی
        if (empty($sale_number)) {
            $errors[] = 'شماره فروش الزامی است';
        } elseif (!$is_edit) {
            // بررسی تکراری نبودن شماره فروش
            $stmt = $pdo->prepare("SELECT id FROM sales WHERE sale_number = ?");
            $stmt->execute([$sale_number]);
            if ($stmt->fetch()) {
                $errors[] = 'شماره فروش تکراری است';
            }
        }
        
        if (!$customer_id) {
            $errors[] = 'انتخاب مشتری الزامی است';
        }
        
        if (empty($sale_date)) {
            $errors[] = 'تاریخ فروش الزامی است';
        }
        
        if ($final_amount <= 0) {
            $errors[] = 'مبلغ نهایی باید بیشتر از صفر باشد';
        }
        
        if (empty($items) || count($items) === 0) {
            $errors[] = 'حداقل یک محصول باید انتخاب شود';
        }
        
        // بررسی محصولات
        foreach ($items as $index => $item) {
            $quantity = (float)(str_replace(',', '', $item['quantity'] ?? 0));
            $unit_price = (float)(str_replace(',', '', $item['unit_price'] ?? 0));
            
            if (empty($item['product_id']) || $quantity <= 0 || $unit_price <= 0) {
                $errors[] = "اطلاعات محصول ردیف " . ($index + 1) . " کامل نیست";
            }
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                if ($is_edit) {
                    // بروزرسانی فروش
                    $stmt = $pdo->prepare("
                        UPDATE sales SET 
                            sale_number = ?, customer_id = ?, lead_id = ?, sale_date = ?, 
                            subtotal = ?, tax_amount = ?, discount_amount = ?, shipping_amount = ?, final_amount = ?,
                            status = ?, payment_method = ?, payment_status = ?, notes = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $sale_number, $customer_id, $lead_id, $sale_date,
                        $subtotal, $tax_amount, $discount_amount, $shipping_amount, $final_amount,
                        $status, $payment_method, $payment_status, $notes, $sale_id
                    ]);
                    
                    // حذف اقلام قدیمی
                    $stmt = $pdo->prepare("DELETE FROM sale_items WHERE sale_id = ?");
                    $stmt->execute([$sale_id]);
                    
                    $action = 'update';
                    $message = 'فروش با موفقیت بروزرسانی شد';
                    
                } else {
                    // ایجاد فروش جدید
                    $stmt = $pdo->prepare("
                        INSERT INTO sales (
                            sale_number, customer_id, lead_id, sale_date, 
                            subtotal, tax_amount, discount_amount, shipping_amount, final_amount,
                            status, payment_method, payment_status, notes, created_by, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $sale_number, $customer_id, $lead_id, $sale_date,
                        $subtotal, $tax_amount, $discount_amount, $shipping_amount, $final_amount,
                        $status, $payment_method, $payment_status, $notes, $_SESSION['user_id']
                    ]);
                    
                    $sale_id = $pdo->lastInsertId();
                    $action = 'create';
                    $message = 'فروش با موفقیت ثبت شد';
                }
                
                // ثبت اقلام فروش
                $stmt = $pdo->prepare("
                    INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total_price)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $calculated_subtotal = 0;
                foreach ($items as $item) {
                    $quantity = (float)(str_replace(',', '', $item['quantity'] ?? 0));
                    $unit_price = (float)(str_replace(',', '', $item['unit_price'] ?? 0));
                    
                    if (!empty($item['product_id']) && $quantity > 0) {
                        $total_price = $quantity * $unit_price;
                        $calculated_subtotal += $total_price;
                        $stmt->execute([
                            $sale_id, $item['product_id'], $quantity, 
                            $unit_price, $total_price
                        ]);
                    }
                }
                
                // بروزرسانی subtotal و final_amount واقعی
                $final_amount_real = $calculated_subtotal + $tax_amount - $discount_amount + $shipping_amount;
                $stmt = $pdo->prepare("UPDATE sales SET subtotal = ?, final_amount = ?, total_amount = ? WHERE id = ?");
                $stmt->execute([$calculated_subtotal, $final_amount_real, $final_amount_real, $sale_id]);
                
                // بروزرسانی موجودی محصولات
                foreach ($items as $item) {
                    $quantity = (float)(str_replace(',', '', $item['quantity'] ?? 0));
                    
                    if (!empty($item['product_id']) && $quantity > 0) {
                        $stmt = $pdo->prepare("
                            UPDATE products SET stock_quantity = stock_quantity - ? 
                            WHERE id = ? AND stock_quantity >= ?
                        ");
                        $stmt->execute([$quantity, $item['product_id'], $quantity]);
                    }
                }
                
                $pdo->commit();
                
                // ثبت فعالیت
                logActivity($_SESSION['user_id'], $action . '_sale', 'sales', $sale_id, [
                    'sale_number' => $sale_number,
                    'customer_id' => $customer_id,
                    'final_amount' => $final_amount
                ]);
                
                setMessage($message, 'success');
                header('Location: sale_view.php?id=' . $sale_id);
                exit();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("خطا در ذخیره فروش: " . $e->getMessage());
                $errors[] = 'خطا در ذخیره اطلاعات';
            }
        }
        
        if (!empty($errors)) {
            setMessage(implode('<br>', $errors), 'error');
        }
    }
}

// دریافت مشتریان
try {
    $customers = $pdo->query("
        SELECT id, first_name, last_name, customer_code, email 
        FROM customers 
        WHERE status = 'active' 
        ORDER BY first_name, last_name
    ")->fetchAll();
} catch (PDOException $e) {
    $customers = [];
}

// دریافت لیدها
try {
    $leads = $pdo->query("
        SELECT id, title, first_name, last_name 
        FROM leads 
        WHERE status IN ('qualified', 'won') 
        ORDER BY first_name, last_name
    ")->fetchAll();
} catch (PDOException $e) {
    $leads = [];
}

// دریافت محصولات
try {
    $products = $pdo->query("
        SELECT id, name, sku, price, stock_quantity 
        FROM products 
        WHERE status = 'active' 
        ORDER BY name
    ")->fetchAll();
} catch (PDOException $e) {
    $products = [];
}

// دریافت اقلام فروش برای ویرایش
$sale_items = [];
if ($is_edit) {
    try {
        $stmt = $pdo->prepare("
            SELECT si.*, p.name as product_name, p.sku, p.stock_quantity 
            FROM sale_items si
            LEFT JOIN products p ON si.product_id = p.id
            WHERE si.sale_id = ?
            ORDER BY si.id
        ");
        $stmt->execute([$sale_id]);
        $sale_items = $stmt->fetchAll();
    } catch (PDOException $e) {
        $sale_items = [];
    }
}

$csrf_token = generateCSRFToken();

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><?php echo $page_title; ?></h4>
        <p class="text-muted mb-0">
            <?php if ($is_edit): ?>
                ویرایش فروش شماره <?php echo htmlspecialchars($sale['sale_number']); ?>
            <?php else: ?>
                ثبت فروش جدید در سیستم
            <?php endif; ?>
        </p>
    </div>
    
    <div>
        <a href="sales.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-2"></i>
            بازگشت
        </a>
    </div>
</div>

<?php displayMessage(); ?>

<form method="POST" id="saleForm">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <div class="row">
        <!-- اطلاعات اصلی فروش -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2 text-primary"></i>
                        اطلاعات فروش
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label required">شماره فروش</label>
                            <input type="text" class="form-control" name="sale_number" 
                                   value="<?php echo htmlspecialchars($sale['sale_number'] ?? 'S-' . date('Ymd') . '-' . sprintf('%04d', rand(1, 9999))); ?>" 
                                   required>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label required">تاریخ فروش</label>
                            <input type="datetime-local" class="form-control" name="sale_date" 
                                   value="<?php echo $sale['sale_date'] ?? date('Y-m-d\TH:i'); ?>" 
                                   required>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label required">مشتری</label>
                            <select class="form-select" name="customer_id" required id="customerSelect">
                                <option value="">انتخاب مشتری...</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>" 
                                            <?php echo ($customer_id == $customer['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name'] . ' (' . $customer['customer_code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label">لید مرتبط</label>
                            <select class="form-select" name="lead_id" id="leadSelect">
                                <option value="">انتخاب لید (اختیاری)...</option>
                                <?php foreach ($leads as $lead): ?>
                                    <option value="<?php echo $lead['id']; ?>" 
                                            <?php echo ($lead_id == $lead['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lead['title'] . ' - ' . $lead['first_name'] . ' ' . $lead['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label required">وضعیت</label>
                            <select class="form-select" name="status" required>
                                <option value="pending" <?php echo ($sale['status'] ?? 'pending') === 'pending' ? 'selected' : ''; ?>>در انتظار</option>
                                <option value="confirmed" <?php echo ($sale['status'] ?? '') === 'confirmed' ? 'selected' : ''; ?>>تایید شده</option>
                                <option value="processing" <?php echo ($sale['status'] ?? '') === 'processing' ? 'selected' : ''; ?>>در حال پردازش</option>
                                <option value="shipped" <?php echo ($sale['status'] ?? '') === 'shipped' ? 'selected' : ''; ?>>ارسال شده</option>
                                <option value="delivered" <?php echo ($sale['status'] ?? '') === 'delivered' ? 'selected' : ''; ?>>تحویل داده شده</option>
                                <option value="completed" <?php echo ($sale['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>تکمیل شده</option>
                                <option value="cancelled" <?php echo ($sale['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>لغو شده</option>
                            </select>
                        </div>
                        
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label">روش پرداخت</label>
                            <select class="form-select" name="payment_method">
                                <option value="">انتخاب روش پرداخت...</option>
                                <option value="cash" <?php echo ($sale['payment_method'] ?? '') === 'cash' ? 'selected' : ''; ?>>نقدی</option>
                                <option value="card" <?php echo ($sale['payment_method'] ?? '') === 'card' ? 'selected' : ''; ?>>کارت</option>
                                <option value="transfer" <?php echo ($sale['payment_method'] ?? '') === 'transfer' ? 'selected' : ''; ?>>انتقال بانکی</option>
                                <option value="cheque" <?php echo ($sale['payment_method'] ?? '') === 'cheque' ? 'selected' : ''; ?>>چک</option>
                                <option value="installment" <?php echo ($sale['payment_method'] ?? '') === 'installment' ? 'selected' : ''; ?>>اقساطی</option>
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">یادداشت‌ها</label>
                            <textarea class="form-control" name="notes" rows="3" 
                                      placeholder="توضیحات اضافی در مورد این فروش..."><?php echo htmlspecialchars($sale['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- اقلام فروش -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-shopping-cart me-2 text-primary"></i>
                            اقلام فروش
                        </h5>
                        <button type="button" class="btn btn-primary btn-sm" onclick="addItem()">
                            <i class="fas fa-plus me-1"></i>
                            افزودن محصول
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless" id="itemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="35%">محصول</th>
                                    <th width="15%">تعداد</th>
                                    <th width="20%">قیمت واحد</th>
                                    <th width="20%">قیمت کل</th>
                                    <th width="10%">حذف</th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <?php if (!empty($sale_items)): ?>
                                    <?php foreach ($sale_items as $index => $item): ?>
                                        <tr>
                                            <td>
                                                <select class="form-select" name="items[<?php echo $index; ?>][product_id]" required onchange="updatePrice(this)">
                                                    <option value="">انتخاب محصول...</option>
                                                    <?php foreach ($products as $product): ?>
                                                        <option value="<?php echo $product['id']; ?>" 
                                                                data-price="<?php echo $product['price']; ?>"
                                                                data-stock="<?php echo $product['stock_quantity']; ?>"
                                                                <?php echo ($item['product_id'] == $product['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($product['name'] . ' (' . $product['sku'] . ')'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" name="items[<?php echo $index; ?>][quantity]" 
                                                       value="<?php echo $item['quantity']; ?>" min="1" step="1" required 
                                                       onchange="updateTotal(this)">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" name="items[<?php echo $index; ?>][unit_price]" 
                                                       value="<?php echo $item['unit_price']; ?>" min="0" step="0.01" required 
                                                       onchange="updateTotal(this)">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control total-price" 
                                                       value="<?php echo formatMoney($item['total_price']); ?>" readonly>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeItem(this)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (empty($sale_items)): ?>
                    <div class="text-center py-4" id="emptyState">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <p class="text-muted">هیچ محصولی اضافه نشده است</p>
                        <button type="button" class="btn btn-primary" onclick="addItem()">
                            <i class="fas fa-plus me-2"></i>
                            افزودن اولین محصول
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- خلاصه مالی -->
        <div class="col-lg-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-calculator me-2 text-primary"></i>
                        خلاصه مالی
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">جمع کل (بدون مالیات)</label>
                        <input type="number" class="form-control" name="subtotal" id="subtotal" 
                               value="<?php echo $sale['subtotal'] ?? 0; ?>" step="0.01" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">مالیات</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="tax_amount" id="taxAmount" 
                                   value="<?php echo $sale['tax_amount'] ?? 0; ?>" step="0.01" min="0" 
                                   onchange="calculateTotal()">
                            <span class="input-group-text">تومان</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">تخفیف</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="discount_amount" id="discountAmount" 
                                   value="<?php echo $sale['discount_amount'] ?? 0; ?>" step="0.01" min="0" 
                                   onchange="calculateTotal()">
                            <span class="input-group-text">تومان</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">هزینه ارسال</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="shipping_amount" id="shippingAmount" 
                                   value="<?php echo $sale['shipping_amount'] ?? 0; ?>" step="0.01" min="0" 
                                   onchange="calculateTotal()">
                            <span class="input-group-text">تومان</span>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <label class="form-label">مبلغ نهایی</label>
                        <input type="number" class="form-control fw-bold text-success" name="final_amount" 
                               id="finalAmount" value="<?php echo $sale['final_amount'] ?? 0; ?>" step="0.01" readonly>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">وضعیت پرداخت</label>
                        <select class="form-select" name="payment_status">
                            <option value="pending" <?php echo ($sale['payment_status'] ?? 'pending') === 'pending' ? 'selected' : ''; ?>>در انتظار پرداخت</option>
                            <option value="partial" <?php echo ($sale['payment_status'] ?? '') === 'partial' ? 'selected' : ''; ?>>پرداخت جزئی</option>
                            <option value="paid" <?php echo ($sale['payment_status'] ?? '') === 'paid' ? 'selected' : ''; ?>>پرداخت شده</option>
                            <option value="refunded" <?php echo ($sale['payment_status'] ?? '') === 'refunded' ? 'selected' : ''; ?>>بازگردانده شده</option>
                        </select>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>
                            <?php echo $is_edit ? 'بروزرسانی فروش' : 'ثبت فروش'; ?>
                        </button>
                        
                        <?php if ($is_edit): ?>
                            <a href="sale_view.php?id=<?php echo $sale_id; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-eye me-2"></i>
                                مشاهده فروش
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Template برای اقلام جدید -->
<template id="itemTemplate">
    <tr>
        <td>
            <select class="form-select" name="items[INDEX][product_id]" required onchange="updatePrice(this)">
                <option value="">انتخاب محصول...</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['id']; ?>" 
                            data-price="<?php echo $product['price']; ?>"
                            data-stock="<?php echo $product['stock_quantity']; ?>">
                        <?php echo htmlspecialchars($product['name'] . ' (' . $product['sku'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="number" class="form-control" name="items[INDEX][quantity]" 
                   value="1" min="1" step="1" required onchange="updateTotal(this)">
        </td>
        <td>
            <input type="number" class="form-control" name="items[INDEX][unit_price]" 
                   value="0" min="0" step="0.01" required onchange="updateTotal(this)">
        </td>
        <td>
            <input type="text" class="form-control total-price" value="0 تومان" readonly>
        </td>
        <td>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeItem(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
</template>

<script>
let itemIndex = <?php echo count($sale_items); ?>;

function addItem() {
    const template = document.getElementById('itemTemplate');
    const tbody = document.getElementById('itemsBody');
    const emptyState = document.getElementById('emptyState');
    
    if (emptyState) {
        emptyState.style.display = 'none';
    }
    
    const newRow = template.content.cloneNode(true);
    const html = newRow.querySelector('tr').outerHTML.replace(/INDEX/g, itemIndex);
    tbody.insertAdjacentHTML('beforeend', html);
    
    itemIndex++;
}

function removeItem(button) {
    const row = button.closest('tr');
    row.remove();
    
    const tbody = document.getElementById('itemsBody');
    const emptyState = document.getElementById('emptyState');
    
    if (tbody.children.length === 0 && emptyState) {
        emptyState.style.display = 'block';
    }
    
    calculateSubtotal();
}

function updatePrice(select) {
    const option = select.selectedOptions[0];
    const price = option.dataset.price || 0;
    const row = select.closest('tr');
    const priceInput = row.querySelector('input[name$="[unit_price]"]');
    
    priceInput.value = price;
    updateTotal(priceInput);
}

function updateTotal(input) {
    const row = input.closest('tr');
    const quantity = parsePersianNumber(row.querySelector('input[name$="[quantity]"]').value);
    const unitPrice = parsePersianNumber(row.querySelector('input[name$="[unit_price]"]').value);
    const total = quantity * unitPrice;
    
    row.querySelector('.total-price').value = formatMoney(total);
    
    calculateSubtotal();
}

function calculateSubtotal() {
    const rows = document.querySelectorAll('#itemsBody tr');
    let subtotal = 0;
    
    rows.forEach(row => {
        const quantity = parsePersianNumber(row.querySelector('input[name$="[quantity]"]').value);
        const unitPrice = parsePersianNumber(row.querySelector('input[name$="[unit_price]"]').value);
        const total = quantity * unitPrice;
        subtotal += total;
    });
    
    document.getElementById('subtotal').value = subtotal.toFixed(2);
    calculateTotal();
}

function calculateTotal() {
    const subtotal = parsePersianNumber(document.getElementById('subtotal').value);
    const tax = parsePersianNumber(document.getElementById('taxAmount').value);
    const discount = parsePersianNumber(document.getElementById('discountAmount').value);
    const shipping = parsePersianNumber(document.getElementById('shippingAmount').value);
    
    const finalAmount = subtotal + tax - discount + shipping;
    document.getElementById('finalAmount').value = Math.max(0, finalAmount).toFixed(2);
}

function formatMoney(amount) {
    return new Intl.NumberFormat('fa-IR').format(amount) + ' تومان';
}

function parsePersianNumber(str) {
    if (typeof str === 'number') return str;
    return parseFloat(str.replace(/[^\d.]/g, '')) || 0;
}

// محاسبه اولیه
document.addEventListener('DOMContentLoaded', function() {
    calculateSubtotal();
});
</script>

<style>
.required::after {
    content: ' *';
    color: var(--danger-color);
}

.sticky-top {
    z-index: 1020;
}

#itemsTable th {
    border-bottom: 2px solid var(--border-color);
    color: var(--text-medium);
    font-weight: 600;
}

.table-borderless td {
    border: none;
    padding: 0.75rem 0.5rem;
}

.table-borderless tr {
    border-bottom: 1px solid var(--border-color);
}

.total-price {
    font-weight: 600;
    color: var(--success-color);
}
</style>

<?php include __DIR__ . '/../private/footer.php'; ?>

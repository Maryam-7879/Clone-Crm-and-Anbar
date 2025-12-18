<?php
$transaction_id = (int)($_GET['id'] ?? 0);

if (!$transaction_id) {
    setMessage('شناسه تراکنش نامعتبر است', 'error');
    header('Location: transactions.php');
    exit();
}

$page_title = 'ویرایش تراکنش';

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

if (!hasPermission('view_dashboard')) {
    setMessage('شما دسترسی لازم برای این عملیات را ندارید', 'error');
    header('Location: transactions.php');
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT t.*, p.name as product_name, p.sku, p.unit
        FROM transactions t
        INNER JOIN products p ON t.product_id = p.id
        WHERE t.id = ? AND t.deleted_at IS NULL
    ");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        setMessage('تراکنش یافت نشد', 'error');
        header('Location: transactions.php');
        exit();
    }
    
} catch (PDOException $e) {
    error_log("خطا در دریافت اطلاعات تراکنش: " . $e->getMessage());
    setMessage('خطا در بارگذاری اطلاعات تراکنش', 'error');
    header('Location: transactions.php');
    exit();
}

// دریافت لیست محصولات
try {
    $products = $pdo->query("
        SELECT id, name, sku 
        FROM products 
        WHERE deleted_at IS NULL AND status = 'active' 
        ORDER BY name
    ")->fetchAll();
} catch (PDOException $e) {
    $products = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        setMessage('درخواست نامعتبر. لطفاً مجدداً تلاش کنید.', 'error');
    } else {
        $product_id = (int)$_POST['product_id'];
        $type = $_POST['type'] ?? '';
        $quantity = (int)$_POST['quantity'];
        $unit_price = (float)$_POST['unit_price'];
        $notes = trim($_POST['notes'] ?? '');
        $reference_number = trim($_POST['reference_number'] ?? '');
        $transaction_date = $_POST['transaction_date'] ?? date('Y-m-d H:i:s');
        
        $errors = [];
        
        if (!$product_id) {
            $errors[] = 'محصول الزامی است';
        }
        
        if (!in_array($type, ['in', 'out', 'adjustment', 'transfer'])) {
            $errors[] = 'نوع تراکنش نامعتبر است';
        }
        
        if ($quantity <= 0) {
            $errors[] = 'تعداد باید بیشتر از صفر باشد';
        }
        
        if ($unit_price < 0) {
            $errors[] = 'قیمت واحد نمی‌تواند منفی باشد';
        }
        
        if (empty($errors)) {
            try {
                $total_price = $quantity * $unit_price;
                
                $stmt = $pdo->prepare("
                    UPDATE transactions SET 
                        product_id = ?, type = ?, quantity = ?, unit_price = ?, 
                        total_price = ?, notes = ?, reference_number = ?, 
                        transaction_date = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $product_id, $type, $quantity, $unit_price, $total_price,
                    $notes, $reference_number, $transaction_date, $transaction_id
                ]);
                
                logActivity($_SESSION['user_id'], 'update_transaction', 'transactions', $transaction_id);
                
                setMessage('تراکنش با موفقیت به‌روزرسانی شد', 'success');
                header('Location: transaction_view.php?id=' . $transaction_id);
                exit();
                
            } catch (PDOException $e) {
                error_log("خطا در به‌روزرسانی تراکنش: " . $e->getMessage());
                $errors[] = 'خطا در ذخیره اطلاعات';
            }
        }
        
        if (!empty($errors)) {
            setMessage(implode('<br>', $errors), 'error');
        }
    }
}

$csrf_token = generateCSRFToken();

include __DIR__ . '/../private/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><?php echo $page_title; ?></h4>
        <p class="text-muted mb-0">ویرایش تراکنش</p>
    </div>
    
    <div>
        <a href="transaction_view.php?id=<?php echo $transaction_id; ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-2"></i>
            بازگشت
        </a>
    </div>
</div>

<?php displayMessage(); ?>

<form method="POST" id="transactionForm">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2 text-primary"></i>
                        اطلاعات تراکنش
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">محصول</label>
                            <select class="form-select" name="product_id" id="product_id" required>
                                <option value="">انتخاب محصول...</option>
                                <?php foreach ($products as $prod): ?>
                                    <option value="<?php echo $prod['id']; ?>" 
                                            <?php echo ($transaction['product_id'] == $prod['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prod['name']); ?> (<?php echo htmlspecialchars($prod['sku']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">نوع تراکنش</label>
                            <select class="form-select" name="type" id="type" required>
                                <option value="">انتخاب کنید...</option>
                                <option value="in" <?php echo $transaction['type'] === 'in' ? 'selected' : ''; ?>>ورود به انبار</option>
                                <option value="out" <?php echo $transaction['type'] === 'out' ? 'selected' : ''; ?>>خروج از انبار</option>
                                <option value="adjustment" <?php echo $transaction['type'] === 'adjustment' ? 'selected' : ''; ?>>تعدیل موجودی</option>
                                <option value="transfer" <?php echo $transaction['type'] === 'transfer' ? 'selected' : ''; ?>>انتقال</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">تعداد</label>
                            <input type="number" class="form-control" name="quantity" 
                                   value="<?php echo $transaction['quantity']; ?>" 
                                   min="1" step="1" required
                                   onchange="calculateTotal()">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">قیمت واحد</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="unit_price" 
                                       value="<?php echo $transaction['unit_price']; ?>" 
                                       min="0" step="0.01" required
                                       onchange="calculateTotal()">
                                <span class="input-group-text">تومان</span>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">جمع کل</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="total_price" 
                                       value="<?php echo number_format($transaction['total_price']); ?>" readonly>
                                <span class="input-group-text">تومان</span>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">تاریخ تراکنش</label>
                            <input type="datetime-local" class="form-control" name="transaction_date" 
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($transaction['transaction_date'])); ?>" 
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">شماره مرجع</label>
                            <input type="text" class="form-control" name="reference_number" 
                                   value="<?php echo htmlspecialchars($transaction['reference_number']); ?>" 
                                   placeholder="مثال: INV-001">
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">یادداشت</label>
                            <textarea class="form-control" name="notes" rows="3" 
                                      placeholder="توضیحات در مورد این تراکنش..."><?php echo htmlspecialchars($transaction['notes']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>
                            ذخیره تغییرات
                        </button>
                        
                        <a href="transaction_view.php?id=<?php echo $transaction_id; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-eye me-2"></i>
                            مشاهده
                        </a>
                        
                        <a href="transactions.php" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-2"></i>
                            لیست
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function calculateTotal() {
    const quantity = parseFloat(document.querySelector('input[name="quantity"]').value) || 0;
    const unitPrice = parseFloat(document.querySelector('input[name="unit_price"]').value) || 0;
    const total = quantity * unitPrice;
    document.getElementById('total_price').value = new Intl.NumberFormat('fa-IR').format(Math.round(total));
}
</script>

<style>
.required::after {
    content: ' *';
    color: var(--danger-color);
}
</style>

<?php include __DIR__ . '/../private/footer.php'; ?>

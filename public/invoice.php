<?php
$sale_id = (int)($_GET['id'] ?? $_GET['sale_id'] ?? 0);

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
if (!hasPermission('view_sales')) {
    setMessage('شما دسترسی لازم برای مشاهده این صفحه را ندارید', 'error');
    header('Location: sales.php');
    exit();
}

if (!$sale_id) {
    setMessage('شناسه فروش معتبر نیست', 'error');
    header('Location: sales.php');
    exit();
}

// دریافت اطلاعات فروش
try {
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            c.customer_code, c.email as customer_email, c.mobile as customer_mobile,
            c.phone as customer_phone, c.company_name, c.customer_type,
            c.address as customer_address, c.city, c.state, c.postal_code,
            CONCAT(u.first_name, ' ', u.last_name) as created_user
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.created_by = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch();
    
    if (!$sale) {
        setMessage('فروش یافت نشد', 'error');
        header('Location: sales.php');
        exit();
    }
    
    // دریافت اقلام فروش
    $items_stmt = $pdo->prepare("
        SELECT 
            si.*,
            p.name as product_name, p.sku, p.description as product_description
        FROM sale_items si
        LEFT JOIN products p ON si.product_id = p.id
        WHERE si.sale_id = ?
        ORDER BY si.id
    ");
    $items_stmt->execute([$sale_id]);
    $sale_items = $items_stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("خطا در دریافت اطلاعات فاکتور: " . $e->getMessage());
    setMessage('خطا در بارگذاری اطلاعات فاکتور', 'error');
    header('Location: sales.php');
    exit();
}

// تنظیم عنوان صفحه
$page_title = 'فاکتور فروش - ' . $sale['sale_number'];
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #ff6b35;
            --primary-dark: #e55a2b;
            --text-dark: #1a1a1a;
            --text-medium: #4a4a4a;
            --text-light: #6b7280;
            --text-muted: #9ca3af;
            --border-color: #e5e7eb;
            --bg-light: #fafafa;
            --bg-card: #ffffff;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Vazirmatn', sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: var(--text-dark);
            background: white;
        }
        
        .invoice-container {
            max-width: 21cm;
            margin: 0 auto;
            padding: 1cm;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .invoice-header {
            border-bottom: 3px solid var(--primary-color);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .company-info {
            text-align: center;
        }
        
        .company-name {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .company-subtitle {
            font-size: 1.1rem;
            color: var(--text-medium);
            margin-bottom: 15px;
        }
        
        .invoice-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--text-dark);
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: 8px;
        }
        
        .invoice-meta {
            background: var(--bg-light);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .meta-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .meta-label {
            font-weight: 600;
            color: var(--text-medium);
            min-width: 120px;
        }
        
        .meta-value {
            color: var(--text-dark);
            font-weight: 500;
        }
        
        .customer-info {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .customer-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 15px;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 8px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .items-table th {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 15px 10px;
            text-align: center;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .items-table td {
            padding: 12px 10px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        
        .items-table tbody tr:hover {
            background: rgba(255, 107, 53, 0.05);
        }
        
        .items-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .product-name {
            font-weight: 600;
            color: var(--text-dark);
            text-align: right;
        }
        
        .product-sku {
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        
        .amount {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .total-amount {
            color: var(--primary-color);
        }
        
        .summary-table {
            width: 100%;
            max-width: 400px;
            margin: 0 0 0 auto;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .summary-row.total {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
            margin-top: 10px;
            padding-top: 15px;
        }
        
        .summary-label {
            color: var(--text-medium);
        }
        
        .summary-value {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .footer-info {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid var(--border-color);
            text-align: center;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-box {
            text-align: center;
            width: 200px;
        }
        
        .signature-line {
            border-top: 1px solid var(--text-dark);
            margin-top: 60px;
            padding-top: 8px;
            font-size: 0.9rem;
            color: var(--text-medium);
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-processing { background: #e2e3e5; color: #383d41; }
        .status-shipped { background: #cce7ff; color: #004085; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .payment-pending { background: #fff3cd; color: #856404; }
        .payment-partial { background: #cce7ff; color: #004085; }
        .payment-paid { background: #d4edda; color: #155724; }
        .payment-refunded { background: #f8d7da; color: #721c24; }
        
        /* Print Styles */
        @media print {
            body {
                margin: 0;
                padding: 0;
                background: white;
            }
            
            .invoice-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
                max-width: none;
            }
            
            .no-print {
                display: none !important;
            }
            
            .items-table {
                box-shadow: none;
            }
            
            .invoice-header {
                border-bottom: 2px solid #000;
            }
            
            .invoice-title {
                background: #f5f5f5 !important;
                color: #000 !important;
                border: 2px solid #000;
            }
            
            .items-table th {
                background: #f5f5f5 !important;
                color: #000 !important;
                border: 1px solid #000;
            }
            
            .items-table td {
                border: 1px solid #000;
            }
        }
        
        .print-controls {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 0 5px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-secondary {
            background: var(--text-light);
            color: white;
        }
        
        .btn-secondary:hover {
            background: var(--text-medium);
        }
        
        .row {
            display: flex;
            margin: 0 -10px;
        }
        
        .col-6 {
            flex: 0 0 50%;
            padding: 0 10px;
        }
        
        .text-end {
            text-align: left;
        }
        
        .fw-bold {
            font-weight: 600;
        }
        
        .text-muted {
            color: var(--text-muted);
        }
        
        .mb-2 {
            margin-bottom: 8px;
        }
        
        .mb-3 {
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <!-- کنترل‌های چاپ -->
    <div class="print-controls no-print">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print me-2"></i>
            چاپ فاکتور
        </button>
        <a href="sale_view.php?id=<?php echo $sale_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-right me-2"></i>
            بازگشت
        </a>
    </div>

    <div class="invoice-container">
        <!-- هدر فاکتور -->
        <div class="invoice-header">
            <div class="company-info">
                <div class="company-name"><?php echo APP_NAME; ?></div>
                <div class="company-subtitle">سیستم مدیریت ارتباط با مشتری</div>
                <div class="company-contact">
                    <small class="text-muted">
                        وب‌سایت: <?php echo BASE_URL; ?> | ایمیل: info@crm-system.com | تلفن: 021-12345678
                    </small>
                </div>
            </div>
        </div>

        <!-- عنوان فاکتور -->
        <div class="invoice-title">
            فاکتور فروش
        </div>

        <!-- اطلاعات فاکتور -->
        <div class="invoice-meta">
            <div class="row">
                <div class="col-6">
                    <div class="meta-row">
                        <span class="meta-label">شماره فاکتور:</span>
                        <span class="meta-value"><?php echo htmlspecialchars($sale['sale_number']); ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">تاریخ فاکتور:</span>
                        <span class="meta-value"><?php echo formatPersianDate($sale['sale_date'], 'Y/m/d'); ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">وضعیت سفارش:</span>
                        <span class="meta-value">
                            <span class="status-badge status-<?php echo $sale['status']; ?>">
                                <?php echo getStatusTitle($sale['status']); ?>
                            </span>
                        </span>
                    </div>
                </div>
                <div class="col-6">
                    <div class="meta-row">
                        <span class="meta-label">تاریخ صدور:</span>
                        <span class="meta-value"><?php echo formatPersianDate('now', 'Y/m/d H:i'); ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">وضعیت پرداخت:</span>
                        <span class="meta-value">
                            <span class="status-badge payment-<?php echo $sale['payment_status']; ?>">
                                <?php echo getPaymentStatusTitle($sale['payment_status']); ?>
                            </span>
                        </span>
                    </div>
                    <?php if ($sale['created_user']): ?>
                    <div class="meta-row">
                        <span class="meta-label">صادرکننده:</span>
                        <span class="meta-value"><?php echo htmlspecialchars($sale['created_user']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- اطلاعات مشتری -->
        <div class="customer-info">
            <div class="customer-title">
                <i class="fas fa-user me-2"></i>
                مشخصات خریدار
            </div>
            
            <div class="row">
                <div class="col-6">
                    <div class="mb-2">
                        <span class="fw-bold">نام:</span>
                        <?php echo htmlspecialchars($sale['customer_name']); ?>
                        <?php if ($sale['customer_type'] === 'company' && $sale['company_name']): ?>
                            <br><small class="text-muted">شرکت: <?php echo htmlspecialchars($sale['company_name']); ?></small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-2">
                        <span class="fw-bold">کد مشتری:</span>
                        <?php echo htmlspecialchars($sale['customer_code']); ?>
                    </div>
                    
                    <?php if ($sale['customer_email']): ?>
                    <div class="mb-2">
                        <span class="fw-bold">ایمیل:</span>
                        <?php echo htmlspecialchars($sale['customer_email']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-6">
                    <?php if ($sale['customer_mobile']): ?>
                    <div class="mb-2">
                        <span class="fw-bold">موبایل:</span>
                        <?php echo formatPhone($sale['customer_mobile']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($sale['customer_phone']): ?>
                    <div class="mb-2">
                        <span class="fw-bold">تلفن:</span>
                        <?php echo formatPhone($sale['customer_phone']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($sale['customer_address']): ?>
                    <div class="mb-2">
                        <span class="fw-bold">آدرس:</span>
                        <?php echo htmlspecialchars($sale['customer_address']); ?>
                        <?php if ($sale['city'] || $sale['state']): ?>
                            <br><small class="text-muted">
                                <?php echo $sale['city'] ? htmlspecialchars($sale['city']) : ''; ?>
                                <?php echo ($sale['city'] && $sale['state']) ? '، ' : ''; ?>
                                <?php echo $sale['state'] ? htmlspecialchars($sale['state']) : ''; ?>
                                <?php if ($sale['postal_code']): ?>
                                    - کد پستی: <?php echo htmlspecialchars($sale['postal_code']); ?>
                                <?php endif; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- جدول اقلام -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%">ردیف</th>
                    <th width="35%">شرح کالا/خدمات</th>
                    <th width="15%">کد کالا</th>
                    <th width="10%">تعداد</th>
                    <th width="15%">مبلغ واحد</th>
                    <th width="20%">مبلغ کل</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sale_items as $index => $item): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td class="product-name">
                            <?php echo htmlspecialchars($item['product_name']); ?>
                            <?php if ($item['product_description']): ?>
                                <br><small class="product-sku"><?php echo htmlspecialchars($item['product_description']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="product-sku"><?php echo htmlspecialchars($item['sku']); ?></td>
                        <td class="amount"><?php echo number_format($item['quantity']); ?></td>
                        <td class="amount"><?php echo formatMoney($item['unit_price']); ?></td>
                        <td class="amount total-amount"><?php echo formatMoney($item['total_price']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- خلاصه مالی -->
        <div class="summary-table">
            <div class="summary-row">
                <span class="summary-label">جمع کل:</span>
                <span class="summary-value"><?php echo formatMoney($sale['subtotal']); ?></span>
            </div>
            
            <?php if ($sale['tax_amount'] > 0): ?>
            <div class="summary-row">
                <span class="summary-label">مالیات:</span>
                <span class="summary-value"><?php echo formatMoney($sale['tax_amount']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($sale['discount_amount'] > 0): ?>
            <div class="summary-row">
                <span class="summary-label">تخفیف:</span>
                <span class="summary-value">-<?php echo formatMoney($sale['discount_amount']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($sale['shipping_amount'] > 0): ?>
            <div class="summary-row">
                <span class="summary-label">هزینه ارسال:</span>
                <span class="summary-value"><?php echo formatMoney($sale['shipping_amount']); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="summary-row total">
                <span>مبلغ قابل پرداخت:</span>
                <span><?php echo formatMoney($sale['final_amount']); ?></span>
            </div>
        </div>

        <!-- یادداشت‌ها -->
        <?php if ($sale['notes']): ?>
        <div class="customer-info">
            <div class="customer-title">
                <i class="fas fa-sticky-note me-2"></i>
                توضیحات
            </div>
            <p class="mb-0"><?php echo nl2br(htmlspecialchars($sale['notes'])); ?></p>
        </div>
        <?php endif; ?>

        <!-- امضاها -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line">امضا و مهر فروشنده</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">امضا خریدار</div>
            </div>
        </div>

        <!-- اطلاعات پایانی -->
        <div class="footer-info">
            <p class="mb-2">
                <strong>شرایط و ضوابط:</strong>
                لطفاً مبلغ فاکتور را حداکثر تا <?php echo formatPersianDate(date('Y-m-d', strtotime('+30 days')), 'Y/m/d'); ?> پرداخت نمایید.
            </p>
            <p class="mb-2">
                این فاکتور در تاریخ <?php echo formatPersianDate('now', 'Y/m/d H:i'); ?> توسط سیستم <?php echo APP_NAME; ?> صادر شده است.
            </p>
            <p class="mb-0">
                <small>شماره فاکتور: <?php echo htmlspecialchars($sale['sale_number']); ?> | شناسه فروش: #<?php echo $sale_id; ?></small>
            </p>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Auto Print (اختیاری) -->
    <script>
        // اگر از URL پارامتر auto_print=true استفاده شود، خودکار چاپ می‌شود
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('auto_print') === 'true') {
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 1000);
            };
        }
        
        // میانبر کیبورد برای چاپ
        document.addEventListener('keydown', function(event) {
            if (event.ctrlKey && event.key === 'p') {
                event.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>

<?php
// توابع کمکی برای وضعیت پرداخت
function getPaymentStatusTitle($status) {
    return match($status) {
        'pending' => 'در انتظار پرداخت',
        'partial' => 'پرداخت جزئی',
        'paid' => 'پرداخت شده',
        'refunded' => 'بازگردانده شده',
        default => 'نامشخص'
    };
}
?>

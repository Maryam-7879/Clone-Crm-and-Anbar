<?php
$category_id = (int)($_GET['id'] ?? 0);
$is_edit = $category_id > 0;
$page_title = $is_edit ? 'ویرایش دسته‌بندی' : 'دسته‌بندی جدید';

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// بررسی دسترسی
if (!hasPermission($is_edit ? 'edit_category' : 'add_category')) {
    // Fallback to view_dashboard permission if specific permissions don't exist
    if (!hasPermission('view_dashboard')) {
        setMessage('شما دسترسی لازم برای این عملیات را ندارید', 'error');
        header('Location: categories.php');
        exit();
    }
}

// دریافت دسته‌بندی برای ویرایش
$category = null;
if ($is_edit) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$category_id]);
        $category = $stmt->fetch();
        
        if (!$category) {
            setMessage('دسته‌بندی یافت نشد', 'error');
            header('Location: categories.php');
            exit();
        }
        
    } catch (PDOException $e) {
        error_log("خطا در دریافت اطلاعات دسته‌بندی: " . $e->getMessage());
        setMessage('خطا در بارگذاری اطلاعات دسته‌بندی', 'error');
        header('Location: categories.php');
        exit();
    }
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        setMessage('درخواست نامعتبر. لطفاً مجدداً تلاش کنید.', 'error');
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $color = trim($_POST['color'] ?? '#007bff');
        $icon = trim($_POST['icon'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $errors = [];
        
        // اعتبارسنجی
        if (empty($name)) {
            $errors[] = 'نام دسته‌بندی الزامی است';
        }
        
        // بررسی تکراری نبودن نام
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ? AND deleted_at IS NULL");
                $stmt->execute([$name, $category_id]);
                if ($stmt->fetch()) {
                    $errors[] = 'این نام دسته‌بندی قبلاً استفاده شده است';
                }
            } catch (PDOException $e) {
                error_log("خطا در بررسی تکراری بودن نام: " . $e->getMessage());
            }
        }
        
        if (empty($errors)) {
            try {
                if ($is_edit) {
                    // بروزرسانی دسته‌بندی
                    $stmt = $pdo->prepare("
                        UPDATE categories SET 
                            name = ?, description = ?, color = ?, icon = ?, is_active = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $description, $color, $icon, $is_active, $category_id]);
                    
                    $action = 'update';
                    $message = 'دسته‌بندی با موفقیت بروزرسانی شد';
                    
                } else {
                    // ایجاد دسته‌بندی جدید
                    $stmt = $pdo->prepare("
                        INSERT INTO categories (name, description, color, icon, is_active, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$name, $description, $color, $icon, $is_active]);
                    
                    $category_id = $pdo->lastInsertId();
                    $action = 'create';
                    $message = 'دسته‌بندی با موفقیت ثبت شد';
                }
                
                // ثبت فعالیت
                logActivity($_SESSION['user_id'], $action . '_category', 'categories', $category_id);
                
                setMessage($message, 'success');
                header('Location: category_view.php?id=' . $category_id);
                exit();
                
            } catch (PDOException $e) {
                error_log("خطا در ذخیره دسته‌بندی: " . $e->getMessage());
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
        <p class="text-muted mb-0">
            <?php if ($is_edit): ?>
                ویرایش دسته‌بندی <?php echo htmlspecialchars($category['name']); ?>
            <?php else: ?>
                افزودن دسته‌بندی جدید
            <?php endif; ?>
        </p>
    </div>
    
    <div>
        <a href="categories.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-2"></i>
            بازگشت
        </a>
    </div>
</div>

<?php displayMessage(); ?>

<form method="POST" id="categoryForm">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2 text-primary"></i>
                        اطلاعات دسته‌بندی
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label required">نام دسته‌بندی</label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo htmlspecialchars($category['name'] ?? ''); ?>" 
                                   placeholder="نام دسته‌بندی..."
                                   required>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">توضیحات</label>
                            <textarea class="form-control" name="description" rows="4" 
                                      placeholder="توضیحات در مورد دسته‌بندی..."><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- ظاهر و تنظیمات -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-palette me-2 text-primary"></i>
                        ظاهر دسته‌بندی
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required">رنگ</label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" id="color" name="color" 
                                   value="<?php echo htmlspecialchars($category['color'] ?? '#007bff'); ?>" required>
                            <input type="text" class="form-control" id="color_text" 
                                   value="<?php echo htmlspecialchars($category['color'] ?? '#007bff'); ?>" readonly>
                        </div>
                        <small class="text-muted">رنگ نمایش دسته‌بندی در سیستم</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">آیکون</label>
                        <div class="row">
                            <div class="col-8">
                                <input type="text" class="form-control" id="icon" name="icon" 
                                       value="<?php echo htmlspecialchars($category['icon'] ?? 'fas fa-tag'); ?>" 
                                       placeholder="مثال: fas fa-tag">
                            </div>
                            <div class="col-4">
                                <div class="d-flex align-items-center justify-content-center h-100">
                                    <i id="icon_preview" class="<?php echo htmlspecialchars($category['icon'] ?? 'fas fa-tag'); ?> fa-2x" 
                                       style="color: <?php echo htmlspecialchars($category['color'] ?? '#007bff'); ?>;"></i>
                                </div>
                            </div>
                        </div>
                        <small class="text-muted">از آیکون‌های Font Awesome استفاده کنید</small>
                    </div>
                    
                    <!-- آیکون‌های محبوب -->
                    <div class="mb-3">
                        <label class="form-label">آیکون‌های محبوب</label>
                        <div class="row g-2">
                            <?php
                            $popularIcons = [
                                'fas fa-tag', 'fas fa-box', 'fas fa-laptop', 'fas fa-mobile-alt',
                                'fas fa-book', 'fas fa-car', 'fas fa-utensils', 'fas fa-home',
                                'fas fa-dumbbell', 'fas fa-palette', 'fas fa-baby', 'fas fa-briefcase',
                                'fas fa-tshirt', 'fas fa-shoe-prints', 'fas fa-gem', 'fas fa-gamepad'
                            ];
                            foreach ($popularIcons as $icon): ?>
                                <div class="col-3">
                                    <button type="button" class="btn btn-outline-secondary btn-sm icon-btn w-100"
                                            data-icon="<?php echo htmlspecialchars($icon); ?>"
                                            title="<?php echo htmlspecialchars($icon); ?>">
                                        <i class="<?php echo htmlspecialchars($icon); ?>"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- رنگ‌های محبوب -->
                    <div class="mb-3">
                        <label class="form-label">رنگ‌های محبوب</label>
                        <div class="row g-2">
                            <?php
                            $popularColors = [
                                '#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8',
                                '#6f42c1', '#fd7e14', '#20c997', '#e83e8c', '#6c757d'
                            ];
                            foreach ($popularColors as $color): ?>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-sm color-btn"
                                            style="background-color: <?php echo htmlspecialchars($color); ?>; width: 35px; height: 35px; border: 2px solid transparent;"
                                            data-color="<?php echo htmlspecialchars($color); ?>">
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- تنظیمات -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-cog me-2 text-primary"></i>
                        تنظیمات
                    </h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                               <?php echo (!isset($category) || $category['is_active']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">
                            دسته‌بندی فعال است
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- دکمه‌های عملیات -->
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>
                            <?php echo $is_edit ? 'بروزرسانی دسته‌بندی' : 'ثبت دسته‌بندی'; ?>
                        </button>
                        
                        <?php if ($is_edit): ?>
                            <a href="category_view.php?id=<?php echo $category_id; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-eye me-2"></i>
                                مشاهده دسته‌بندی
                            </a>
                        <?php endif; ?>
                        
                        <a href="categories.php" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-2"></i>
                            لیست دسته‌بندی‌ها
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Color picker sync
document.getElementById('color').addEventListener('input', function() {
    document.getElementById('color_text').value = this.value;
    document.getElementById('icon_preview').style.color = this.value;
});

// Icon input sync
document.getElementById('icon').addEventListener('input', function() {
    const iconPreview = document.getElementById('icon_preview');
    iconPreview.className = this.value || 'fas fa-tag';
});

// Popular icons click handler
document.querySelectorAll('.icon-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const icon = this.dataset.icon;
        document.getElementById('icon').value = icon;
        document.getElementById('icon_preview').className = icon;
    });
});

// Popular colors click handler
document.querySelectorAll('.color-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const color = this.dataset.color;
        document.getElementById('color').value = color;
        document.getElementById('color_text').value = color;
        document.getElementById('icon_preview').style.color = color;
        
        // Update all color buttons border
        document.querySelectorAll('.color-btn').forEach(b => {
            b.style.border = '2px solid transparent';
        });
        this.style.border = '2px solid #000';
    });
});
</script>

<style>
.required::after {
    content: ' *';
    color: var(--danger-color);
}

.form-control-color {
    width: 70px;
    height: 38px;
    cursor: pointer;
}

.icon-btn {
    transition: all 0.2s;
}

.icon-btn:hover {
    transform: scale(1.1);
}

.color-btn {
    transition: all 0.2s;
    cursor: pointer;
}

.color-btn:hover {
    transform: scale(1.1);
}
</style>

<?php include __DIR__ . '/../private/footer.php'; ?>

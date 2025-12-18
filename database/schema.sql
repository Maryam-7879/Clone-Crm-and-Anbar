

-- جدول کاربران
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    mobile VARCHAR(15),
    phone VARCHAR(20),
    avatar VARCHAR(255),
    role ENUM('admin', 'manager', 'sales', 'user') DEFAULT 'user',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    department VARCHAR(100),
    position VARCHAR(100),
    hire_date DATE,
    salary DECIMAL(10,2),
    address TEXT,
    notes TEXT,
    last_login DATETIME,
    failed_login_attempts INT DEFAULT 0,
    locked_until DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- جدول مشتریان
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_code VARCHAR(20) UNIQUE NOT NULL,
    company_name VARCHAR(100),
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    mobile VARCHAR(20),
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    postal_code VARCHAR(20),
    website VARCHAR(100),
    industry VARCHAR(50),
    customer_type ENUM('individual', 'company') DEFAULT 'individual',
    status ENUM('active', 'inactive', 'prospect') DEFAULT 'prospect',
    source VARCHAR(50),
    assigned_to INT,
    tags TEXT,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- جدول لیدها
CREATE TABLE leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    company VARCHAR(100),
    position VARCHAR(50),
    source VARCHAR(50),
    status ENUM('new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost') DEFAULT 'new',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    value DECIMAL(15,2) DEFAULT 0,
    probability INT DEFAULT 0,
    expected_close_date DATE,
    assigned_to INT,
    description TEXT,
    notes TEXT,
    tags TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- جدول فعالیت‌ها و وظایف
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    type ENUM('call', 'email', 'meeting', 'follow_up', 'other') DEFAULT 'other',
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    due_date DATETIME,
    completed_at DATETIME NULL,
    assigned_to INT,
    related_type ENUM('customer', 'lead', 'user') NULL,
    related_id INT NULL,
    reminder_datetime DATETIME NULL,
    is_reminder_sent BOOLEAN DEFAULT FALSE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- جدول فعالیت‌های مشتریان
CREATE TABLE customer_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    activity_type ENUM('call', 'email', 'meeting', 'note', 'purchase', 'support') NOT NULL,
    subject VARCHAR(200) NOT NULL,
    description TEXT,
    activity_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    duration INT DEFAULT 0, -- مدت زمان به دقیقه
    outcome VARCHAR(100),
    next_action VARCHAR(200),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- جدول دسته‌بندی‌ها
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    color VARCHAR(7) DEFAULT '#007bff',
    icon VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول تامین‌کنندگان
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    tax_number VARCHAR(50) NULL,
    notes TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول محصولات/خدمات
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    category_id INT NULL,
    supplier_id INT NULL,
    price DECIMAL(15,2) DEFAULT 0,
    cost_price DECIMAL(15,2) DEFAULT 0,
    purchase_price DECIMAL(15,2) DEFAULT 0,
    selling_price DECIMAL(15,2) DEFAULT 0,
    sku VARCHAR(100) UNIQUE NOT NULL,
    status ENUM('active', 'inactive', 'discontinued') DEFAULT 'active',
    stock_quantity INT DEFAULT 0,
    min_stock_level INT DEFAULT 5,
    max_stock_level INT NULL,
    unit VARCHAR(50) DEFAULT 'عدد',
    barcode VARCHAR(100),
    image VARCHAR(255) NULL,
    weight DECIMAL(10,3) DEFAULT 0,
    dimensions VARCHAR(100),
    tags TEXT,
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول موجودی
CREATE TABLE inventories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    current_stock INT DEFAULT 0,
    reserved_stock INT DEFAULT 0,
    available_stock INT DEFAULT 0,
    average_cost DECIMAL(15,2) DEFAULT 0,
    last_updated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- جدول فروش/سفارشات
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_number VARCHAR(20) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    lead_id INT NULL,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(15,2) DEFAULT 0,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    shipping_amount DECIMAL(15,2) DEFAULT 0,
    final_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    status ENUM('draft', 'pending', 'confirmed', 'processing', 'shipped', 'delivered', 'completed', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'partial', 'paid', 'refunded') DEFAULT 'pending',
    payment_method ENUM('cash', 'card', 'transfer', 'cheque', 'installment'),
    sale_date DATETIME NOT NULL,
    delivery_date DATE NULL,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- جدول آیتم‌های فروش
CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity DECIMAL(10,3) NOT NULL DEFAULT 1,
    unit_price DECIMAL(15,2) NOT NULL,
    total_price DECIMAL(15,2) NOT NULL,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- جدول پرداخت‌ها
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_number VARCHAR(20) UNIQUE NOT NULL,
    sale_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'transfer', 'cheque', 'other') NOT NULL,
    payment_date DATE NOT NULL,
    reference_number VARCHAR(50),
    notes TEXT,
    status ENUM('pending', 'confirmed', 'failed', 'cancelled') DEFAULT 'pending',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- جدول تنظیمات سیستم
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description VARCHAR(200),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- جدول لاگ‌ها
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(50) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON NULL,
    new_values JSON NULL,
    details TEXT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- جدول فایل‌ها
CREATE TABLE files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    related_type ENUM('customer', 'lead', 'task', 'sale', 'user') NOT NULL,
    related_id INT NOT NULL,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ایجاد ایندکس‌ها برای بهینه‌سازی
CREATE INDEX idx_customers_email ON customers(email);
CREATE INDEX idx_customers_phone ON customers(phone);
CREATE INDEX idx_customers_assigned_to ON customers(assigned_to);
CREATE INDEX idx_customers_status ON customers(status);

CREATE INDEX idx_leads_email ON leads(email);
CREATE INDEX idx_leads_phone ON leads(phone);
CREATE INDEX idx_leads_status ON leads(status);
CREATE INDEX idx_leads_assigned_to ON leads(assigned_to);

CREATE INDEX idx_tasks_assigned_to ON tasks(assigned_to);
CREATE INDEX idx_tasks_status ON tasks(status);
CREATE INDEX idx_tasks_due_date ON tasks(due_date);

CREATE INDEX idx_sales_customer_id ON sales(customer_id);
CREATE INDEX idx_sales_status ON sales(status);
CREATE INDEX idx_sales_sale_date ON sales(sale_date);

CREATE INDEX idx_activity_logs_user_id ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_created_at ON activity_logs(created_at);

CREATE INDEX idx_categories_is_active ON categories(is_active);
CREATE INDEX idx_categories_deleted_at ON categories(deleted_at);
CREATE INDEX idx_products_category_id ON products(category_id);
CREATE INDEX idx_products_supplier_id ON products(supplier_id);
CREATE INDEX idx_products_is_active ON products(is_active);
CREATE INDEX idx_products_deleted_at ON products(deleted_at);

CREATE INDEX idx_suppliers_is_active ON suppliers(is_active);
CREATE INDEX idx_suppliers_deleted_at ON suppliers(deleted_at);
CREATE INDEX idx_inventories_product_id ON inventories(product_id);
CREATE INDEX idx_inventories_deleted_at ON inventories(deleted_at);

-- جدول تراکنش‌ها
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    type ENUM('in', 'out', 'adjustment', 'transfer') NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    total_price DECIMAL(15,2) NOT NULL,
    notes TEXT NULL,
    reference_number VARCHAR(255) NULL,
    transaction_date TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_transactions_product_id ON transactions(product_id);
CREATE INDEX idx_transactions_user_id ON transactions(user_id);
CREATE INDEX idx_transactions_type ON transactions(type);
CREATE INDEX idx_transactions_transaction_date ON transactions(transaction_date);
CREATE INDEX idx_transactions_deleted_at ON transactions(deleted_at);

-- جدول هشدارها
CREATE TABLE alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    type ENUM('low_stock', 'out_of_stock', 'expiry', 'custom') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_alerts_product_id ON alerts(product_id);
CREATE INDEX idx_alerts_type ON alerts(type);
CREATE INDEX idx_alerts_is_read ON alerts(is_read);
CREATE INDEX idx_alerts_deleted_at ON alerts(deleted_at);

-- درج داده‌های اولیه
INSERT INTO users (username, email, password, first_name, last_name, role, mobile, phone, department, position, hire_date, salary, address, notes, status, created_at) VALUES
('admin', 'admin@crm.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'علی', 'احمدی', 'admin', '09121234567', '02188776655', 'مدیریت', 'مدیر کل', '2020-01-15', 25000000.00, 'تهران، ولیعصر، پلاک 123', 'مدیر کل سیستم', 'active', NOW()),
('manager', 'manager@crm.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'سارا', 'محمدی', 'manager', '09129876543', '02188776656', 'فروش', 'مدیر فروش', '2021-03-10', 18000000.00, 'تهران، انقلاب، پلاک 456', 'مدیر بخش فروش', 'active', NOW()),
('sales1', 'sales@crm.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'رضا', 'کریمی', 'sales', '09112345678', '02188776657', 'فروش', 'کارشناس فروش', '2022-06-20', 12000000.00, 'تهران، کریمخان، پلاک 789', 'کارشناس فروش ارشد', 'active', NOW()),
('user1', 'user@crm.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مریم', 'حسینی', 'user', '09198765432', '02188776658', 'پشتیبانی', 'کارشناس پشتیبانی', '2023-01-05', 10000000.00, 'تهران، شریعتی، پلاک 321', 'کارشناس پشتیبانی مشتریان', 'active', NOW());

-- مشتریان تستی
INSERT INTO customers (customer_code, first_name, last_name, company_name, customer_type, email, phone, mobile, address, city, postal_code, status, created_by, created_at) VALUES
('CUS001', 'احمد', 'رضایی', NULL, 'individual', 'ahmad@email.com', '02112345678', '09123456789', 'خیابان ولیعصر، پلاک 123', 'تهران', '1234567890', 'active', 1, DATE_SUB(NOW(), INTERVAL 45 DAY)),
('CUS002', 'فاطمه', 'علوی', NULL, 'individual', 'fateme@email.com', '02187654321', '09198765432', 'خیابان انقلاب، پلاک 456', 'تهران', '0987654321', 'active', 1, DATE_SUB(NOW(), INTERVAL 30 DAY)),
('CUS003', 'شرکت', 'فناوری پارس', 'شرکت فناوری پارس', 'company', 'info@parstech.ir', '02155667788', '09155667788', 'خیابان شریعتی، برج میلاد', 'تهران', '1122334455', 'active', 1, DATE_SUB(NOW(), INTERVAL 25 DAY)),
('CUS004', 'حسن', 'کریمی', NULL, 'individual', 'hassan@email.com', '02133445566', '09133445566', 'خیابان کریمخان، پلاک 789', 'تهران', '5566778899', 'active', 1, DATE_SUB(NOW(), INTERVAL 20 DAY)),
('CUS005', 'شرکت', 'بازرگانی آریا', 'شرکت بازرگانی آریا', 'company', 'contact@arya.com', '02144556677', '09144556677', 'میدان آزادی، ساختمان تجاری', 'تهران', '6677889900', 'active', 1, DATE_SUB(NOW(), INTERVAL 15 DAY));

-- لیدهای تستی  
INSERT INTO leads (title, first_name, last_name, company, email, phone, source, status, priority, description, assigned_to, created_by, created_at) VALUES
('مدیر فروش', 'مهدی', 'نوری', 'شرکت تکنولوژی نوین', 'mehdi@novin.com', '09121112233', 'website', 'new', 'high', 'علاقه‌مند به خرید سیستم CRM', 3, 1, DATE_SUB(NOW(), INTERVAL 15 DAY)),
('کارشناس IT', 'زهرا', 'صادقی', 'شرکت داده پردازی', 'zahra@dataproc.ir', '09134445566', 'phone', 'contacted', 'medium', 'نیاز به راهکار مدیریت مشتری', 3, 1, DATE_SUB(NOW(), INTERVAL 10 DAY)),
('مدیر عامل', 'کامران', 'احمدی', 'گروه صنعتی البرز', 'kamran@alborz.com', '09167778899', 'email', 'qualified', 'high', 'درخواست دمو محصول', 3, 1, DATE_SUB(NOW(), INTERVAL 20 DAY)),
('مدیر بازاریابی', 'لیلا', 'محمدی', 'شرکت بازرگانی پارس', 'leila@pars.com', '09155443322', 'social', 'proposal', 'medium', 'جلسه برای ارائه قیمت', 3, 1, DATE_SUB(NOW(), INTERVAL 8 DAY)),
('مدیر فنی', 'امین', 'کریمی', 'شرکت نرم‌افزاری رایان', 'amin@rayan.ir', '09188776655', 'referral', 'won', 'low', 'قرارداد منعقد شده', 3, 1, DATE_SUB(NOW(), INTERVAL 5 DAY));

-- وظایف تستی
INSERT INTO tasks (title, description, assigned_to, priority, status, due_date, related_type, related_id, completed_at, created_by, created_at) VALUES
('تماس با مشتری جدید', 'تماس اولیه با مشتری برای شناخت نیازها', 3, 'high', 'pending', DATE_ADD(NOW(), INTERVAL 2 DAY), 'customer', 1, NULL, 1, NOW()),
('ارسال پیشنهاد قیمت', 'تهیه و ارسال پیشنهاد قیمت برای پروژه CRM', 3, 'medium', 'in_progress', DATE_ADD(NOW(), INTERVAL 5 DAY), 'lead', 2, NULL, 1, NOW()),
('دمو محصول', 'برگزاری جلسه دمو برای نمایش امکانات', 2, 'high', 'pending', DATE_ADD(NOW(), INTERVAL 3 DAY), 'lead', 3, NULL, 1, NOW()),
('پیگیری قرارداد', 'پیگیری وضعیت امضای قرارداد', 3, 'medium', 'completed', DATE_SUB(NOW(), INTERVAL 1 DAY), 'customer', 3, DATE_SUB(NOW(), INTERVAL 1 DAY), 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
('بررسی نیازمندی‌ها', 'تحلیل دقیق نیازمندی‌های فنی مشتری', 4, 'low', 'in_progress', DATE_ADD(NOW(), INTERVAL 7 DAY), 'lead', 4, NULL, 1, DATE_SUB(NOW(), INTERVAL 3 DAY));

-- لاگ‌های فعالیت تستی
INSERT INTO activity_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at) VALUES
(1, 'create_customer', 'customers', 1, NULL, '{"name":"احمد رضایی","email":"ahmad@email.com"}', '127.0.0.1', 'Mozilla/5.0', NOW()),
(3, 'create_sale', 'sales', 1, NULL, '{"sale_number":"S240001","amount":6050000}', '127.0.0.1', 'Mozilla/5.0', NOW()),
(1, 'update_lead', 'leads', 3, '{"status":"qualified"}', '{"status":"won"}', '127.0.0.1', 'Mozilla/5.0', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 'create_task', 'tasks', 3, NULL, '{"title":"دمو محصول","priority":"high"}', '127.0.0.1', 'Mozilla/5.0', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(3, 'update_task', 'tasks', 4, '{"status":"in_progress"}', '{"status":"completed"}', '127.0.0.1', 'Mozilla/5.0', DATE_SUB(NOW(), INTERVAL 3 HOUR));

-- دسته‌بندی‌های تستی
INSERT INTO categories (name, description, color, icon, is_active, created_at) VALUES
('الکترونیک', 'دسته‌بندی محصولات الکترونیکی و دیجیتالی', '#007bff', 'fas fa-mobile-alt', TRUE, NOW()),
('پوشاک', 'دسته‌بندی انواع پوشاک و البسه', '#28a745', 'fas fa-tshirt', TRUE, NOW()),
('مواد غذایی', 'دسته‌بندی مواد خوراکی و آشامیدنی', '#ffc107', 'fas fa-utensils', TRUE, NOW()),
('لوازم خانگی', 'دسته‌بندی لوازم و تجهیزات منزل', '#dc3545', 'fas fa-home', TRUE, NOW()),
('کتاب و آموزش', 'دسته‌بندی کتب، لوازم تحریر و محصولات آموزشی', '#17a2b8', 'fas fa-book', TRUE, NOW());

-- تامین‌کنندگان تستی
INSERT INTO suppliers (name, contact_person, email, phone, address, tax_number, notes, is_active, created_at) VALUES
('شرکت الکترونیک پارس', 'علی احمدی', 'info@parselectronic.com', '02188776655', 'تهران، خیابان ولیعصر، پلاک 123', '1234567890', 'تامین‌کننده اصلی محصولات الکترونیک', TRUE, NOW()),
('کارخانه پوشاک ایرانی', 'فاطمه محمدی', 'sales@iraniclothing.com', '02177665544', 'تهران، خیابان انقلاب، پلاک 456', '2345678901', 'تولید کننده پوشاک با کیفیت', TRUE, NOW()),
('شرکت مواد غذایی سلام', 'رضا کریمی', 'contact@salamfood.com', '02166554433', 'تهران، میدان آزادی، برج تجاری', '3456789012', 'تامین‌کننده مواد غذایی تازه', TRUE, NOW()),
('لوازم خانگی مدرن', 'سارا حسینی', 'info@modernhome.com', '02155443322', 'تهران، خیابان شریعتی، پلاک 789', '4567890123', 'نماینده انحصاری برندهای معتبر', TRUE, NOW()),
('انتشارات علم و دانش', 'امیر نوری', 'contact@elm-danesh.com', '02144332211', 'تهران، خیابان کریمخان، پلاک 321', '5678901234', 'ناشر کتاب‌های علمی و آموزشی', TRUE, NOW());

-- محصولات تستی (با فیلدهای جدید)
INSERT INTO products (name, sku, description, category_id, supplier_id, price, cost_price, purchase_price, selling_price, min_stock_level, max_stock_level, unit, barcode, status, stock_quantity, is_active, created_by, created_at) VALUES
('گوشی موبایل سامسونگ', 'ELC-MOB-001', 'گوشی هوشمند سامسونگ مدل A54 با حافظه 128GB', 1, 1, 15000000.00, 12000000.00, 12000000.00, 15000000.00, 5, 50, 'عدد', '8806091234567', 'active', 25, TRUE, 1, NOW()),
('لپ‌تاپ لنوو', 'ELC-LAP-002', 'لپ‌تاپ لنوو ThinkPad با پردازنده Intel Core i7', 1, 1, 35000000.00, 28000000.00, 28000000.00, 35000000.00, 3, 30, 'عدد', '1944621234567', 'active', 15, TRUE, 1, NOW()),
('تی‌شرت مردانه', 'CLO-TSH-003', 'تی‌شرت پنبه‌ای مردانه سایز L', 2, 2, 150000.00, 80000.00, 80000.00, 150000.00, 10, 200, 'عدد', '8901234567890', 'active', 100, TRUE, 1, NOW()),
('شلوار جین', 'CLO-JNS-004', 'شلوار جین مردانه مدل اسلیم', 2, 2, 450000.00, 250000.00, 250000.00, 450000.00, 8, 150, 'عدد', '8902345678901', 'active', 75, TRUE, 1, NOW()),
('برنج ایرانی', 'FOO-RIC-005', 'برنج ایرانی درجه یک کیلویی', 3, 3, 80000.00, 50000.00, 50000.00, 80000.00, 50, 1000, 'کیلوگرم', '6260504012345', 'active', 500, TRUE, 1, NOW()),
('روغن مایع', 'FOO-OIL-006', 'روغن مایع آفتابگردان 1 لیتری', 3, 3, 45000.00, 30000.00, 30000.00, 45000.00, 30, 500, 'عدد', '6260403023456', 'active', 200, TRUE, 1, NOW()),
('یخچال ساید بای ساید', 'HOM-FRD-007', 'یخچال فریزر ساید بای ساید 25 فوت', 4, 4, 25000000.00, 20000000.00, 20000000.00, 25000000.00, 2, 20, 'عدد', '6223001234567', 'active', 8, TRUE, 1, NOW()),
('ماشین لباسشویی', 'HOM-WSH-008', 'ماشین لباسشویی 10 کیلویی اتوماتیک', 4, 4, 18000000.00, 14000000.00, 14000000.00, 18000000.00, 3, 25, 'عدد', '6223002345678', 'active', 12, TRUE, 1, NOW()),
('کتاب PHP پیشرفته', 'BOK-PHP-009', 'کتاب آموزش PHP پیشرفته و Laravel', 5, 5, 250000.00, 150000.00, 150000.00, 250000.00, 5, 100, 'عدد', '9789641234567', 'active', 50, TRUE, 1, NOW()),
('کتاب Python', 'BOK-PYT-010', 'کتاب آموزش برنامه‌نویسی Python از مبتدی تا پیشرفته', 5, 5, 300000.00, 180000.00, 180000.00, 300000.00, 5, 100, 'عدد', '9789642345678', 'active', 45, TRUE, 1, NOW());

-- فروش‌های تستی (بعد از محصولات)
INSERT INTO sales (sale_number, customer_id, lead_id, sale_date, subtotal, total_amount, tax_amount, discount_amount, shipping_amount, final_amount, status, payment_status, payment_method, notes, created_by, created_at) VALUES
('S240001', 1, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), 16500000.00, 16500000.00, 1485000.00, 500000.00, 0.00, 17485000.00, 'confirmed', 'paid', 'transfer', 'پرداخت کامل انجام شده', 3, DATE_SUB(NOW(), INTERVAL 2 DAY)),
('S240002', 3, 3, DATE_SUB(NOW(), INTERVAL 5 DAY), 50000000.00, 50000000.00, 4500000.00, 1000000.00, 0.00, 53500000.00, 'confirmed', 'paid', 'transfer', 'فروش بزرگ', 3, DATE_SUB(NOW(), INTERVAL 5 DAY)),
('S240003', 2, NULL, DATE_SUB(NOW(), INTERVAL 8 DAY), 1350000.00, 1350000.00, 121500.00, 0.00, 0.00, 1471500.00, 'delivered', 'paid', 'cash', 'تحویل کامل', 3, DATE_SUB(NOW(), INTERVAL 8 DAY));

-- آیتم‌های فروش تستی (بعد از محصولات - به product_id های جدید انبار اشاره می‌کنند)
INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total_price) VALUES
(1, 1, 1, 15000000.00, 15000000.00),
(1, 3, 10, 150000.00, 1500000.00),
(2, 1, 1, 15000000.00, 15000000.00),
(2, 2, 1, 35000000.00, 35000000.00),
(3, 4, 3, 450000.00, 1350000.00);

-- پرداخت‌های تستی (بعد از sales و sale_items)
INSERT INTO payments (payment_number, sale_id, amount, payment_method, payment_date, reference_number, notes, status, created_by, created_at) VALUES
('PAY001', 1, 17485000.00, 'transfer', CURDATE(), 'TXN123456789', 'پرداخت کامل فاکتور', 'confirmed', 3, NOW()),
('PAY002', 2, 53500000.00, 'transfer', DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'CARD987654321', 'پرداخت کامل', 'confirmed', 3, DATE_SUB(NOW(), INTERVAL 3 DAY)),
('PAY003', 3, 1471500.00, 'cash', DATE_SUB(CURDATE(), INTERVAL 1 DAY), NULL, 'پرداخت نقدی', 'confirmed', 3, DATE_SUB(NOW(), INTERVAL 1 DAY));

-- موجودی تستی (برای محصولات جدید انبار - شروع از ID 1)
INSERT INTO inventories (product_id, current_stock, reserved_stock, available_stock, average_cost, last_updated_at, created_at) VALUES
(1, 25, 3, 22, 12000000.00, NOW(), NOW()),
(2, 15, 1, 14, 28000000.00, NOW(), NOW()),
(3, 100, 15, 85, 80000.00, NOW(), NOW()),
(4, 75, 5, 70, 250000.00, NOW(), NOW()),
(5, 500, 50, 450, 50000.00, NOW(), NOW()),
(6, 200, 20, 180, 30000.00, NOW(), NOW()),
(7, 8, 1, 7, 20000000.00, NOW(), NOW()),
(8, 12, 2, 10, 14000000.00, NOW(), NOW()),
(9, 50, 3, 47, 150000.00, NOW(), NOW()),
(10, 45, 2, 43, 180000.00, NOW(), NOW());

-- تراکنش‌های تستی
INSERT INTO transactions (product_id, user_id, type, quantity, unit_price, total_price, notes, reference_number, transaction_date, created_at) VALUES
(1, 1, 'in', 30, 12000000.00, 360000000.00, 'خرید اولیه محصول', 'PO-2024-001', DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 30 DAY)),
(1, 1, 'out', 5, 15000000.00, 75000000.00, 'فروش به مشتری', 'SO-2024-001', DATE_SUB(NOW(), INTERVAL 25 DAY), DATE_SUB(NOW(), INTERVAL 25 DAY)),
(2, 1, 'in', 20, 28000000.00, 560000000.00, 'سفارش جدید', 'PO-2024-002', DATE_SUB(NOW(), INTERVAL 28 DAY), DATE_SUB(NOW(), INTERVAL 28 DAY)),
(2, 1, 'out', 5, 35000000.00, 175000000.00, 'فروش عمده', 'SO-2024-002', DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY)),
(3, 1, 'in', 150, 80000.00, 12000000.00, 'خرید فصلی', 'PO-2024-003', DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY)),
(3, 1, 'out', 50, 150000.00, 7500000.00, 'فروش عمده', 'SO-2024-003', DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY)),
(5, 1, 'in', 600, 50000.00, 30000000.00, 'خرید فصلی برنج', 'PO-2024-004', DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY)),
(5, 1, 'out', 100, 80000.00, 8000000.00, 'فروش به مشتریان', 'SO-2024-004', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(7, 1, 'in', 10, 20000000.00, 200000000.00, 'خرید یخچال', 'PO-2024-005', DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY)),
(7, 1, 'out', 2, 25000000.00, 50000000.00, 'فروش', 'SO-2024-005', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
(9, 1, 'in', 60, 150000.00, 9000000.00, 'خرید کتاب', 'PO-2024-006', DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY)),
(9, 1, 'out', 10, 250000.00, 2500000.00, 'فروش کتاب', 'SO-2024-006', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 1, 'adjustment', 2, 0, 0, 'تعدیل موجودی - شمارش فیزیکی', 'ADJ-2024-001', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY));

-- هشدارهای تستی
INSERT INTO alerts (product_id, type, title, message, is_read, read_at, created_at) VALUES
(1, 'low_stock', 'موجودی گوشی موبایل در حال اتمام', 'موجودی محصول "گوشی موبایل سامسونگ" به 25 عدد رسیده است. لطفاً نسبت به خرید مجدد اقدام کنید.', FALSE, NULL, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(8, 'low_stock', 'موجودی ماشین لباسشویی کم است', 'موجودی محصول "ماشین لباسشویی" به 12 عدد رسیده است و از حداقل موجودی کمتر است.', FALSE, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(9, 'low_stock', 'موجودی کتاب PHP کم است', 'موجودی کتاب "PHP پیشرفته" به 50 عدد رسیده است.', TRUE, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)),
(10, 'low_stock', 'موجودی کتاب Python', 'موجودی کتاب "Python" به 45 عدد رسیده است.', FALSE, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(5, 'custom', 'به‌روزرسانی قیمت برنج', 'قیمت برنج ایرانی نیاز به به‌روزرسانی دارد. لطفاً قیمت جدید را بررسی کنید.', FALSE, NULL, DATE_SUB(NOW(), INTERVAL 5 DAY));

-- تنظیمات سیستم
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('company_name', 'شرکت سیستم مدیریت ارتباط پارس', 'string', 'نام شرکت'),
('company_phone', '021-88776655', 'string', 'تلفن شرکت'),
('company_email', 'info@parscrm.ir', 'string', 'ایمیل شرکت'),
('company_address', 'تهران، میدان ولیعصر، برج میلاد، طبقه 15', 'string', 'آدرس شرکت'),
('tax_rate', '9', 'integer', 'نرخ مالیات (درصد)'),
('currency', 'تومان', 'string', 'واحد پول'),
('records_per_page', '20', 'integer', 'تعداد رکورد در هر صفحه');
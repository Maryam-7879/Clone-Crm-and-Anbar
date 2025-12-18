const API_URL = 'http://localhost:3001/api';
let currentUser = null;
let currentTab = 'customers';

document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    setupEventListeners();
});

function setupEventListeners() {
    document.getElementById('loginForm').addEventListener('submit', handleLogin);
}

async function handleLogin(e) {
    e.preventDefault();

    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const errorDiv = document.getElementById('loginError');

    try {
        const response = await fetch(`${API_URL}/auth/login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ username, password })
        });

        const data = await response.json();

        if (data.success) {
            currentUser = data.user;
            localStorage.setItem('user', JSON.stringify(data.user));
            showDashboard();
        } else {
            errorDiv.textContent = data.message || 'خطا در ورود';
            errorDiv.style.display = 'block';
        }
    } catch (error) {
        console.error('Login error:', error);
        errorDiv.textContent = 'خطا در اتصال به سرور';
        errorDiv.style.display = 'block';
    }
}

function checkAuth() {
    const savedUser = localStorage.getItem('user');
    if (savedUser) {
        currentUser = JSON.parse(savedUser);
        showDashboard();
    }
}

function showDashboard() {
    document.getElementById('loginPage').style.display = 'none';
    document.getElementById('dashboardPage').style.display = 'block';
    document.getElementById('userInfo').textContent = `👤 ${currentUser.full_name}`;

    loadDashboardData();
}

function logout() {
    localStorage.removeItem('user');
    currentUser = null;
    document.getElementById('loginPage').style.display = 'flex';
    document.getElementById('dashboardPage').style.display = 'none';
}

async function loadDashboardData() {
    await Promise.all([
        loadStats(),
        loadCustomers(),
        loadProducts(),
        loadSales(),
        loadTasks(),
        loadLeads()
    ]);
}

async function loadStats() {
    try {
        const [customers, products, sales, tasks] = await Promise.all([
            fetch(`${API_URL}/customers`).then(r => r.json()),
            fetch(`${API_URL}/products`).then(r => r.json()),
            fetch(`${API_URL}/sales`).then(r => r.json()),
            fetch(`${API_URL}/tasks`).then(r => r.json())
        ]);

        document.getElementById('customersCount').textContent = customers.length;
        document.getElementById('productsCount').textContent = products.length;

        const today = new Date().toISOString().split('T')[0];
        const todaySales = sales.filter(s => s.sale_date?.startsWith(today));
        document.getElementById('todaySales').textContent = todaySales.length;

        const activeTasks = tasks.filter(t => t.status !== 'completed');
        document.getElementById('activeTasks').textContent = activeTasks.length;
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

async function loadCustomers() {
    try {
        const response = await fetch(`${API_URL}/customers`);
        const customers = await response.json();

        const content = document.getElementById('customersContent');

        if (customers.length === 0) {
            content.innerHTML = '<div class="empty-state">هیچ مشتری ثبت نشده است</div>';
            return;
        }

        let html = '<table><thead><tr><th>کد مشتری</th><th>نام</th><th>شرکت</th><th>ایمیل</th><th>موبایل</th><th>شهر</th><th>عملیات</th></tr></thead><tbody>';

        customers.forEach(customer => {
            const fullName = `${customer.first_name || ''} ${customer.last_name || ''}`.trim();
            html += `
                <tr>
                    <td>${customer.customer_code || ''}</td>
                    <td>${fullName}</td>
                    <td>${customer.company_name || ''}</td>
                    <td>${customer.email || ''}</td>
                    <td>${customer.mobile || customer.phone || ''}</td>
                    <td>${customer.city || ''}</td>
                    <td>
                        <button class="action-btn btn-view" onclick="viewCustomer(${customer.id})">مشاهده</button>
                        <button class="action-btn btn-edit" onclick="editCustomer(${customer.id})">ویرایش</button>
                        <button class="action-btn btn-delete" onclick="deleteCustomer(${customer.id})">حذف</button>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        content.innerHTML = html;
    } catch (error) {
        console.error('Error loading customers:', error);
        document.getElementById('customersContent').innerHTML = '<div class="error-message" style="display:block">خطا در بارگذاری مشتریان</div>';
    }
}

async function loadProducts() {
    try {
        const response = await fetch(`${API_URL}/products`);
        const products = await response.json();

        const content = document.getElementById('productsContent');

        if (products.length === 0) {
            content.innerHTML = '<div class="empty-state">هیچ محصولی ثبت نشده است</div>';
            return;
        }

        let html = '<table><thead><tr><th>نام محصول</th><th>SKU</th><th>قیمت</th><th>موجودی</th><th>عملیات</th></tr></thead><tbody>';

        products.forEach(product => {
            html += `
                <tr>
                    <td>${product.name || ''}</td>
                    <td>${product.sku || ''}</td>
                    <td>${Number(product.price || 0).toLocaleString('fa-IR')} تومان</td>
                    <td>${product.stock_quantity || 0}</td>
                    <td>
                        <button class="action-btn btn-view" onclick="viewProduct(${product.id})">مشاهده</button>
                        <button class="action-btn btn-edit" onclick="editProduct(${product.id})">ویرایش</button>
                        <button class="action-btn btn-delete" onclick="deleteProduct(${product.id})">حذف</button>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        content.innerHTML = html;
    } catch (error) {
        console.error('Error loading products:', error);
        document.getElementById('productsContent').innerHTML = '<div class="error-message" style="display:block">خطا در بارگذاری محصولات</div>';
    }
}

async function loadSales() {
    try {
        const response = await fetch(`${API_URL}/sales`);
        const sales = await response.json();

        const content = document.getElementById('salesContent');

        if (sales.length === 0) {
            content.innerHTML = '<div class="empty-state">هیچ فروشی ثبت نشده است</div>';
            return;
        }

        let html = '<table><thead><tr><th>شماره فاکتور</th><th>مشتری</th><th>مبلغ</th><th>تاریخ</th><th>وضعیت پرداخت</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>';

        sales.forEach(sale => {
            const statusColors = {
                'pending': '🟡',
                'completed': '🟢',
                'cancelled': '🔴',
                'confirmed': '🔵',
                'processing': '🟠',
                'shipped': '📦',
                'delivered': '✅'
            };

            const paymentColors = {
                'pending': '🟡',
                'paid': '🟢',
                'partial': '🟠',
                'refunded': '🔴'
            };

            html += `
                <tr>
                    <td>${sale.sale_number || ''}</td>
                    <td>${sale.customer_name || ''}</td>
                    <td>${Number(sale.final_amount || sale.total_amount || 0).toLocaleString('fa-IR')} تومان</td>
                    <td>${sale.sale_date ? new Date(sale.sale_date).toLocaleDateString('fa-IR') : ''}</td>
                    <td>${paymentColors[sale.payment_status] || ''} ${sale.payment_status || ''}</td>
                    <td>${statusColors[sale.status] || ''} ${sale.status || ''}</td>
                    <td>
                        <button class="action-btn btn-view" onclick="viewSale(${sale.id})">مشاهده</button>
                        <button class="action-btn btn-edit" onclick="editSale(${sale.id})">ویرایش</button>
                        <button class="action-btn btn-delete" onclick="deleteSale(${sale.id})">حذف</button>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        content.innerHTML = html;
    } catch (error) {
        console.error('Error loading sales:', error);
        document.getElementById('salesContent').innerHTML = '<div class="error-message" style="display:block">خطا در بارگذاری فروش‌ها</div>';
    }
}

async function loadTasks() {
    try {
        const response = await fetch(`${API_URL}/tasks`);
        const tasks = await response.json();

        const content = document.getElementById('tasksContent');

        if (tasks.length === 0) {
            content.innerHTML = '<div class="empty-state">هیچ وظیفه‌ای ثبت نشده است</div>';
            return;
        }

        let html = '<table><thead><tr><th>عنوان</th><th>توضیحات</th><th>اولویت</th><th>وضعیت</th><th>تاریخ</th><th>عملیات</th></tr></thead><tbody>';

        tasks.forEach(task => {
            const priorityColors = {
                'low': '🟢',
                'medium': '🟡',
                'high': '🔴'
            };

            html += `
                <tr>
                    <td>${task.title || ''}</td>
                    <td>${(task.description || '').substring(0, 50)}${task.description?.length > 50 ? '...' : ''}</td>
                    <td>${priorityColors[task.priority] || ''} ${task.priority || ''}</td>
                    <td>${task.status || ''}</td>
                    <td>${task.due_date ? new Date(task.due_date).toLocaleDateString('fa-IR') : ''}</td>
                    <td>
                        <button class="action-btn btn-view" onclick="viewTask(${task.id})">مشاهده</button>
                        <button class="action-btn btn-edit" onclick="editTask(${task.id})">ویرایش</button>
                        <button class="action-btn btn-delete" onclick="deleteTask(${task.id})">حذف</button>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        content.innerHTML = html;
    } catch (error) {
        console.error('Error loading tasks:', error);
        document.getElementById('tasksContent').innerHTML = '<div class="error-message" style="display:block">خطا در بارگذاری وظایف</div>';
    }
}

async function loadLeads() {
    try {
        const response = await fetch(`${API_URL}/leads`);
        const leads = await response.json();

        const content = document.getElementById('leadsContent');

        if (leads.length === 0) {
            content.innerHTML = '<div class="empty-state">هیچ سرنخی ثبت نشده است</div>';
            return;
        }

        let html = '<table><thead><tr><th>عنوان</th><th>نام</th><th>شرکت</th><th>ایمیل</th><th>تلفن</th><th>وضعیت</th><th>احتمال</th><th>ارزش</th><th>عملیات</th></tr></thead><tbody>';

        leads.forEach(lead => {
            const fullName = `${lead.first_name || ''} ${lead.last_name || ''}`.trim();
            const statusColors = {
                'new': '🆕',
                'contacted': '📞',
                'qualified': '✅',
                'proposal': '📄',
                'negotiation': '🤝',
                'won': '🏆',
                'lost': '❌'
            };

            html += `
                <tr>
                    <td>${lead.title || ''}</td>
                    <td>${fullName}</td>
                    <td>${lead.company || ''}</td>
                    <td>${lead.email || ''}</td>
                    <td>${lead.phone || ''}</td>
                    <td>${statusColors[lead.status] || ''} ${lead.status || ''}</td>
                    <td>${lead.probability || 0}%</td>
                    <td>${Number(lead.value || 0).toLocaleString('fa-IR')} تومان</td>
                    <td>
                        <button class="action-btn btn-view" onclick="viewLead(${lead.id})">مشاهده</button>
                        <button class="action-btn btn-edit" onclick="editLead(${lead.id})">ویرایش</button>
                        <button class="action-btn btn-delete" onclick="deleteLead(${lead.id})">حذف</button>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        content.innerHTML = html;
    } catch (error) {
        console.error('Error loading leads:', error);
        document.getElementById('leadsContent').innerHTML = '<div class="error-message" style="display:block">خطا در بارگذاری سرنخ‌ها</div>';
    }
}

function switchTab(tabName) {
    const tabs = document.querySelectorAll('.tab');
    const contents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => tab.classList.remove('active'));
    contents.forEach(content => content.classList.remove('active'));

    event.target.classList.add('active');
    document.getElementById(`${tabName}Tab`).classList.add('active');

    currentTab = tabName;
}

function showAddCustomerModal() {
    document.getElementById('customerModal').classList.add('show');
    document.getElementById('customerForm').reset();

    document.getElementById('customerForm').onsubmit = async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);

        try {
            const response = await fetch(`${API_URL}/customers`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            if (response.ok) {
                closeModal('customerModal');
                await loadCustomers();
                await loadStats();
            }
        } catch (error) {
            console.error('Error adding customer:', error);
            alert('خطا در افزودن مشتری');
        }
    };
}

function showAddProductModal() {
    alert('فرم افزودن محصول در حال توسعه است');
}

function showAddSaleModal() {
    alert('فرم ثبت فروش در حال توسعه است');
}

function showAddTaskModal() {
    alert('فرم افزودن وظیفه در حال توسعه است');
}

function showAddLeadModal() {
    alert('فرم افزودن سرنخ در حال توسعه است');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

async function deleteCustomer(id) {
    if (!confirm('آیا از حذف این مشتری اطمینان دارید؟')) return;

    try {
        const response = await fetch(`${API_URL}/customers/${id}`, {
            method: 'DELETE'
        });

        if (response.ok) {
            await loadCustomers();
            await loadStats();
        }
    } catch (error) {
        console.error('Error deleting customer:', error);
        alert('خطا در حذف مشتری');
    }
}

async function deleteProduct(id) {
    if (!confirm('آیا از حذف این محصول اطمینان دارید؟')) return;

    try {
        const response = await fetch(`${API_URL}/products/${id}`, {
            method: 'DELETE'
        });

        if (response.ok) {
            await loadProducts();
            await loadStats();
        }
    } catch (error) {
        console.error('Error deleting product:', error);
        alert('خطا در حذف محصول');
    }
}

async function deleteSale(id) {
    if (!confirm('آیا از حذف این فروش اطمینان دارید؟')) return;

    try {
        const response = await fetch(`${API_URL}/sales/${id}`, {
            method: 'DELETE'
        });

        if (response.ok) {
            await loadSales();
            await loadStats();
        }
    } catch (error) {
        console.error('Error deleting sale:', error);
        alert('خطا در حذف فروش');
    }
}

async function deleteTask(id) {
    if (!confirm('آیا از حذف این وظیفه اطمینان دارید؟')) return;

    try {
        const response = await fetch(`${API_URL}/tasks/${id}`, {
            method: 'DELETE'
        });

        if (response.ok) {
            await loadTasks();
            await loadStats();
        }
    } catch (error) {
        console.error('Error deleting task:', error);
        alert('خطا در حذف وظیفه');
    }
}

async function deleteLead(id) {
    if (!confirm('آیا از حذف این سرنخ اطمینان دارید؟')) return;

    try {
        const response = await fetch(`${API_URL}/leads/${id}`, {
            method: 'DELETE'
        });

        if (response.ok) {
            await loadLeads();
            await loadStats();
        }
    } catch (error) {
        console.error('Error deleting lead:', error);
        alert('خطا در حذف سرنخ');
    }
}

function viewCustomer(id) { alert('نمایش جزئیات مشتری #' + id); }
function editCustomer(id) { alert('ویرایش مشتری #' + id); }
function viewProduct(id) { alert('نمایش جزئیات محصول #' + id); }
function editProduct(id) { alert('ویرایش محصول #' + id); }
function viewSale(id) { alert('نمایش جزئیات فروش #' + id); }
function editSale(id) { alert('ویرایش فروش #' + id); }
function viewTask(id) { alert('نمایش جزئیات وظیفه #' + id); }
function editTask(id) { alert('ویرایش وظیفه #' + id); }
function viewLead(id) { alert('نمایش جزئیات سرنخ #' + id); }
function editLead(id) { alert('ویرایش سرنخ #' + id); }

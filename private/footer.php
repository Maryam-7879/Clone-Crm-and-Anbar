        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Toggle Sidebar for Mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            const toggleBtn = event.target.closest('.mobile-menu-toggle');
            
            if (!sidebar.contains(event.target) && !toggleBtn && window.innerWidth <= 768) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });

        // Confirm delete actions
        function confirmDelete(message = 'آیا از حذف این آیتم مطمئن هستید؟') {
            return new Promise((resolve) => {
                Swal.fire({
                    title: 'تأیید حذف',
                    text: message,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'بله، حذف کن',
                    cancelButtonText: 'انصراف',
                    reverseButtons: true
                }).then((result) => {
                    resolve(result.isConfirmed);
                });
            });
        }

        // Success message
        function showSuccess(message) {
            Swal.fire({
                icon: 'success',
                title: 'موفقیت‌آمیز',
                text: message,
                timer: 3000,
                showConfirmButton: false
            });
        }

        // Error message
        function showError(message) {
            Swal.fire({
                icon: 'error',
                title: 'خطا',
                text: message
            });
        }

        // Form validation
        function validateForm(formId) {
            const form = document.getElementById(formId);
            const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;

            inputs.forEach(function(input) {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });

            return isValid;
        }

        // Phone number formatting
        function formatPhoneNumber(input) {
            let value = input.value.replace(/\D/g, '');
            
            if (value.startsWith('09') && value.length === 11) {
                value = value.replace(/(\d{4})(\d{3})(\d{4})/, '$1-$2-$3');
            } else if (value.startsWith('021') && value.length === 11) {
                value = value.replace(/(\d{3})(\d{4})(\d{4})/, '$1-$2-$3');
            }
            
            input.value = value;
        }

        // Currency formatting
        function formatCurrency(input) {
            let value = input.value.replace(/[^\d]/g, '');
            value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            input.value = value;
        }

        // File upload preview
        function previewFile(input, previewId) {
            const file = input.files[0];
            const preview = document.getElementById(previewId);
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (file.type.startsWith('image/')) {
                        preview.innerHTML = `<img src="${e.target.result}" class="img-thumbnail" style="max-width: 200px;">`;
                    } else {
                        preview.innerHTML = `<i class="fas fa-file fa-3x text-muted"></i><br><small>${file.name}</small>`;
                    }
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        }

        // Auto-save form data to localStorage
        function enableAutoSave(formId) {
            const form = document.getElementById(formId);
            const inputs = form.querySelectorAll('input, select, textarea');
            
            // Load saved data
            inputs.forEach(function(input) {
                const savedValue = localStorage.getItem(`${formId}_${input.name}`);
                if (savedValue && input.type !== 'password') {
                    input.value = savedValue;
                }
            });
            
            // Save data on change
            inputs.forEach(function(input) {
                input.addEventListener('change', function() {
                    if (input.type !== 'password') {
                        localStorage.setItem(`${formId}_${input.name}`, input.value);
                    }
                });
            });
            
            // Clear saved data on form submit
            form.addEventListener('submit', function() {
                inputs.forEach(function(input) {
                    localStorage.removeItem(`${formId}_${input.name}`);
                });
            });
        }

        // Search functionality
        function initSearch(inputId, targetClass) {
            const searchInput = document.getElementById(inputId);
            if (!searchInput) return;
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const items = document.querySelectorAll(targetClass);
                
                items.forEach(function(item) {
                    const text = item.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }

        // Table sorting
        function initTableSort(tableId) {
            const table = document.getElementById(tableId);
            if (!table) return;
            
            const headers = table.querySelectorAll('th[data-sort]');
            headers.forEach(function(header) {
                header.style.cursor = 'pointer';
                header.innerHTML += ' <i class="fas fa-sort ms-1"></i>';
                
                header.addEventListener('click', function() {
                    const column = this.dataset.sort;
                    const tbody = table.querySelector('tbody');
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    
                    const isAscending = !this.classList.contains('sort-asc');
                    
                    // Reset all headers
                    headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
                    
                    // Set current header
                    this.classList.add(isAscending ? 'sort-asc' : 'sort-desc');
                    
                    rows.sort(function(a, b) {
                        const aValue = a.querySelector(`td:nth-child(${parseInt(column) + 1})`).textContent.trim();
                        const bValue = b.querySelector(`td:nth-child(${parseInt(column) + 1})`).textContent.trim();
                        
                        if (isAscending) {
                            return aValue.localeCompare(bValue);
                        } else {
                            return bValue.localeCompare(aValue);
                        }
                    });
                    
                    rows.forEach(function(row) {
                        tbody.appendChild(row);
                    });
                });
            });
        }

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // AJAX form submission
        function submitAjaxForm(formId, successCallback) {
            const form = document.getElementById(formId);
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(form);
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>در حال ارسال...';
                
                fetch(form.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSuccess(data.message);
                        if (successCallback) successCallback(data);
                    } else {
                        showError(data.message);
                    }
                })
                .catch(error => {
                    showError('خطا در ارسال اطلاعات');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                });
            });
        }

        // Real-time notifications (if WebSocket is available)
        function initNotifications() {
            // This can be extended with WebSocket or Server-Sent Events
            // for real-time notifications
        }

        // Export table to CSV
        function exportTableToCSV(tableId, filename = 'export.csv') {
            const table = document.getElementById(tableId);
            if (!table) return;
            
            const rows = table.querySelectorAll('tr');
            const csv = [];
            
            rows.forEach(function(row) {
                const cols = row.querySelectorAll('td, th');
                const rowData = [];
                
                cols.forEach(function(col) {
                    rowData.push('"' + col.textContent.trim().replace(/"/g, '""') + '"');
                });
                
                csv.push(rowData.join(','));
            });
            
            const csvString = csv.join('\n');
            const blob = new Blob(['\ufeff' + csvString], { type: 'text/csv;charset=utf-8;' });
            
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            link.click();
        }
    </script>

    <?php if (isset($additional_js)): ?>
        <?php echo $additional_js; ?>
    <?php endif; ?>
</body>
</html>

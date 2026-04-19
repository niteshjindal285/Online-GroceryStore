    </main>

    <!-- Footer -->
    <footer class="app-footer py-3 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; <?= date('Y') ?> MJR Group of Companies. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-end">
                    <small>ERP System v1.0</small>
                </div>
            </div>
        </div>
    </footer>

    <style>
    html[data-app-theme="dark"] {
        --mjr-shell-bg: #0f172a;
        --mjr-shell-text: #f8fafc;
        --mjr-shell-text-muted: rgba(248,250,252,0.72);
        --mjr-shell-border: rgba(248,250,252,0.12);
        --mjr-panel-bg: #111827;
        --mjr-overlay-bg: rgba(255,255,255,0.06);
        --mjr-table-head-bg: rgba(255,255,255,0.08);
        --mjr-table-row-bg: rgba(255,255,255,0.03);
        --mjr-input-bg: #1f2937;
        --mjr-input-border: rgba(255,255,255,0.14);
        --mjr-footer-bg: #111827;
        --mjr-footer-text: #f8fafc;
    }

    html[data-app-theme="light"] {
        --mjr-shell-bg: #f8f9fa;
        --mjr-shell-text: #212529;
        --mjr-shell-text-muted: rgba(33,37,41,0.65);
        --mjr-shell-border: rgba(0,0,0,0.12);
        --mjr-panel-bg: #ffffff;
        --mjr-overlay-bg: rgba(248,249,250,0.95);
        --mjr-table-head-bg: rgba(248,249,250,1);
        --mjr-table-row-bg: #ffffff;
        --mjr-input-bg: #ffffff;
        --mjr-input-border: rgba(0,0,0,0.18);
        --mjr-footer-bg: #e9ecef;
        --mjr-footer-text: #212529;
    }

    html[data-app-theme] body {
        background: var(--mjr-shell-bg) !important;
        color: var(--mjr-shell-text) !important;
    }

    html[data-app-theme] .premium-card,
    html[data-app-theme] .modal-content,
    html[data-app-theme] .offcanvas,
    html[data-app-theme] .accordion-item,
    html[data-app-theme] .list-group-item,
    html[data-app-theme] .je-card,
    html[data-app-theme] .je-table-wrap,
    html[data-app-theme] .pm-card {
        background: var(--mjr-panel-bg) !important;
        color: var(--mjr-shell-text) !important;
        border-color: var(--mjr-shell-border) !important;
    }

    html[data-app-theme] .app-footer {
        background: var(--mjr-footer-bg) !important;
        color: var(--mjr-footer-text) !important;
        border-top: 1px solid var(--mjr-shell-border) !important;
    }

    html[data-app-theme] .app-footer a,
    html[data-app-theme] .app-footer small,
    html[data-app-theme] .app-footer p {
        color: var(--mjr-footer-text) !important;
    }

    html[data-app-theme] .bg-dark-light,
    html[data-app-theme] .notification-menu,
    html[data-app-theme] .notification-menu-header,
    html[data-app-theme] .notification-menu-footer {
        background: var(--mjr-overlay-bg) !important;
        color: var(--mjr-shell-text) !important;
        border-color: var(--mjr-shell-border) !important;
    }

    html[data-app-theme] .table-dark,
    html[data-app-theme] .table-dark thead th,
    html[data-app-theme] .table-dark tbody td,
    html[data-app-theme] .table-dark tfoot td,
    html[data-app-theme] .table-dark tr,
    html[data-app-theme] table.table-dark,
    html[data-app-theme] .table.table-dark {
        background: var(--mjr-panel-bg) !important;
        color: var(--mjr-shell-text) !important;
        border-color: var(--mjr-shell-border) !important;
    }

    html[data-app-theme] .table-dark thead th,
    html[data-app-theme] .table-dark th {
        background: var(--mjr-table-head-bg) !important;
        color: var(--mjr-shell-text-muted) !important;
    }

    html[data-app-theme] .table-dark tbody td,
    html[data-app-theme] .table-dark td {
        background: var(--mjr-table-row-bg) !important;
    }

    html[data-app-theme] .form-control,
    html[data-app-theme] .form-select,
    html[data-app-theme] textarea,
    html[data-app-theme] input,
    html[data-app-theme] select,
    html[data-app-theme] .input-group-text,
    html[data-app-theme] .je-input,
    html[data-app-theme] .je-select,
    html[data-app-theme] .pm-input,
    html[data-app-theme] .pm-select {
        background: var(--mjr-input-bg) !important;
        color: var(--mjr-shell-text) !important;
        border-color: var(--mjr-input-border) !important;
    }

    html[data-app-theme] .form-control::placeholder,
    html[data-app-theme] textarea::placeholder,
    html[data-app-theme] input::placeholder {
        color: var(--mjr-shell-text-muted) !important;
    }

    html[data-app-theme] .select2-container--default .select2-selection--single,
    html[data-app-theme] .select2-container--default .select2-selection--multiple,
    html[data-app-theme] .select2-dropdown {
        background: var(--mjr-input-bg) !important;
        color: var(--mjr-shell-text) !important;
        border-color: var(--mjr-input-border) !important;
    }

    html[data-app-theme] .select2-container--default .select2-selection--single .select2-selection__rendered,
    html[data-app-theme] .select2-container--default .select2-selection--multiple .select2-selection__choice,
    html[data-app-theme] .select2-results__option {
        color: var(--mjr-shell-text) !important;
    }

    html[data-app-theme] .text-white,
    html[data-app-theme] .text-light,
    html[data-app-theme] .btn-close + .text-white,
    html[data-app-theme] .pm-title,
    html[data-app-theme] .je-title,
    html[data-app-theme] .info-value {
        color: var(--mjr-shell-text) !important;
    }

    html[data-app-theme] .text-white-50,
    html[data-app-theme] .text-muted,
    html[data-app-theme] .text-secondary,
    html[data-app-theme] .small.text-muted,
    html[data-app-theme] .pm-sub,
    html[data-app-theme] .pm-label,
    html[data-app-theme] .je-label,
    html[data-app-theme] .info-label {
        color: var(--mjr-shell-text-muted) !important;
    }

    html[data-app-theme] [class*="border-secondary"],
    html[data-app-theme] [class*="border-light"],
    html[data-app-theme] [class*="border-dark"] {
        border-color: var(--mjr-shell-border) !important;
    }

    html[data-app-theme] .auto-gen-box,
    html[data-app-theme] .po-preview-box,
    html[data-app-theme] .section-badge {
        border-color: rgba(13, 202, 240, 0.28) !important;
    }

    html[data-app-theme] .notification-menu .dropdown-item,
    html[data-app-theme] .notification-menu .dropdown-header,
    html[data-app-theme] .notification-menu strong,
    html[data-app-theme] .notification-menu p,
    html[data-app-theme] .notification-menu h6 {
        color: var(--mjr-shell-text) !important;
    }

    html[data-app-theme] .dataTables_wrapper .dataTables_filter input,
    html[data-app-theme] .dataTables_wrapper .dataTables_length select {
        background: var(--mjr-input-bg) !important;
        color: var(--mjr-shell-text) !important;
        border-color: var(--mjr-input-border) !important;
    }

    html[data-app-theme="light"] [style*="background:#1a1a27"],
    html[data-app-theme="light"] [style*="background: #1a1a27"],
    html[data-app-theme="light"] [style*="background:#1a1a24"],
    html[data-app-theme="light"] [style*="background: #1a1a24"],
    html[data-app-theme="light"] [style*="background:#1e1e2d"],
    html[data-app-theme="light"] [style*="background: #1e1e2d"],
    html[data-app-theme="light"] [style*="background:#212133"],
    html[data-app-theme="light"] [style*="background: #212133"],
    html[data-app-theme="light"] [style*="background:#222230"],
    html[data-app-theme="light"] [style*="background: #222230"],
    html[data-app-theme="light"] [style*="background-color:#1a1a27"],
    html[data-app-theme="light"] [style*="background-color: #1a1a27"],
    html[data-app-theme="light"] [style*="background-color:#1a1a24"],
    html[data-app-theme="light"] [style*="background-color: #1a1a24"],
    html[data-app-theme="light"] [style*="background-color:#222230"],
    html[data-app-theme="light"] [style*="background-color: #222230"] {
        background: var(--mjr-panel-bg) !important;
    }

    html[data-app-theme="light"] [style*="color:#fff"],
    html[data-app-theme="light"] [style*="color: #fff"],
    html[data-app-theme="light"] [style*="color:#ffffff"],
    html[data-app-theme="light"] [style*="color: #ffffff"] {
        color: var(--mjr-shell-text) !important;
    }

    html[data-app-theme="light"] [style*="color:#8e8e9e"],
    html[data-app-theme="light"] [style*="color: #8e8e9e"],
    html[data-app-theme="light"] [style*="color:#a2a3b7"],
    html[data-app-theme="light"] [style*="color: #a2a3b7"],
    html[data-app-theme="light"] [style*="color:#646c9a"],
    html[data-app-theme="light"] [style*="color: #646c9a"] {
        color: var(--mjr-shell-text-muted) !important;
    }

    html[data-app-theme="light"] [style*="border: 1px solid rgba(255,255,255"],
    html[data-app-theme="light"] [style*="border-color: rgba(255,255,255"] {
        border-color: var(--mjr-shell-border) !important;
    }
    </style>

    <!-- Additional JavaScript for dropdown functionality and theme toggle -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize all Bootstrap dropdowns
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
        var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl);
        });
        
        // Theme Toggle Functionality
        const themeToggle = document.getElementById('themeToggle');
        const themeIconLight = document.getElementById('themeIconLight');
        const themeIconDark = document.getElementById('themeIconDark');
        const htmlElement = document.documentElement;
        const mainNavbar = document.getElementById('mainNavbar');
        
        // Get saved theme from localStorage or default to dark
        const savedTheme = localStorage.getItem('theme') || 'dark';
        applyTheme(savedTheme);
        
        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                const currentTheme = htmlElement.getAttribute('data-bs-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                applyTheme(newTheme);
            });
        }
        
        function applyTheme(theme) {
            htmlElement.setAttribute('data-bs-theme', theme);
            htmlElement.setAttribute('data-app-theme', theme);
            localStorage.setItem('theme', theme);
            if (themeIconLight && themeIconDark) {
                themeIconLight.style.display = theme === 'dark' ? 'inline-block' : 'none';
                themeIconDark.style.display = theme === 'dark' ? 'none' : 'inline-block';
            }

            if (mainNavbar) {
                mainNavbar.classList.toggle('navbar-dark', theme === 'dark');
                mainNavbar.classList.toggle('navbar-light', theme === 'light');
                mainNavbar.classList.toggle('bg-dark', theme === 'dark');
                mainNavbar.classList.toggle('bg-light', theme === 'light');
            }

            const flatpickrTheme = theme === 'dark' ? 'dark' : 'light';
            if (typeof flatpickr !== 'undefined') {
                document.querySelectorAll('input[type="text"][placeholder*="DD-MM-YYYY"], .datepicker').forEach(el => {
                    if (el._flatpickr) {
                        el._flatpickr.set('theme', flatpickrTheme);
                    }
                });
            }
        }
        
        // Auto-style currency selects to match UI theme
        const applyCurrencyStyle = (el) => {
            const option = el.options[el.selectedIndex];
            if (!option) return;
            
            // Try to get currency code: from data attribute, value, or option text
            let currency = el.getAttribute('data-currency-code') || option.getAttribute('data-currency-code') || el.value;
            
            // If numeric or long, try parsing from text (e.g. "USD - US Dollar")
            if (!currency || !isNaN(currency) || currency.length > 4) {
                const textMatch = option.text.match(/^[A-Z]{3}/);
                if (textMatch) currency = textMatch[0];
                else currency = 'USD';
            }
            
            currency = currency.toString().toUpperCase().trim();
            el.setAttribute('data-currency', currency);
            
            // Premium colors from the AI UI
            const colors = {
                'FJD': '#ff5722', // Orange/Deep Orange for FJD
                'USD': '#0dcaf0', // Cyan
                'EUR': '#198754', // Green
                'GBP': '#0d6efd', // Blue
                'INR': '#ffc107'  // Yellow
            };
            
            const color = colors[currency] || colors['FJD'];
            const currentTheme = htmlElement.getAttribute('data-bs-theme');
            el.style.borderLeft = "4px solid " + color;
            el.style.color = color;
            el.style.fontWeight = "600";
            el.style.backgroundColor = currentTheme === 'light' ? "rgba(23,35,61,0.04)" : "rgba(0,0,0,0.2)";
        };

        document.querySelectorAll('.currency-select').forEach(el => {
            applyCurrencyStyle(el);
            el.addEventListener('change', () => applyCurrencyStyle(el));
        });

        // Initialize Flatpickr for date fields
        if (typeof flatpickr !== 'undefined') {
            flatpickr('input[type="text"][placeholder*="DD-MM-YYYY"], .datepicker', {
                dateFormat: 'd-m-Y',
                allowInput: true,
                theme: 'dark'
            });
        }

        console.log('MJR Group ERP initialized successfully');
    });
    </script>
    
    <?php if (isset($additional_scripts)): ?>
        <?= $additional_scripts ?>
    <?php endif; ?>
    
    <?php if (function_exists('url')): ?>
    <!-- Simulated Web-Cron (Triggers background tasks silently) -->
    <script>
    setTimeout(() => {
        // Use a relative path to avoid CORS issues if BASE_URL protocol doesn't match browser
        const cronPath = '<?= str_replace(BASE_URL, "", url("cron_trigger.php")) ?>'.replace(/^\/+/, '');
        fetch('/MJR/public/' + cronPath).catch(() => {});
    }, 3000);
    </script>
    <?php endif; ?>
</body>
</html>

<?php
/**
 * Modern UI Component Library
 * Provides modern CSS components and utilities for the TaaBia platform
 */

// Modern CSS Variables and Design System
function get_modern_css_variables() {
    return "
    :root {
        /* Color Palette */
        --primary-color: #00796b;
        --primary-light: #48a999;
        --primary-dark: #004c40;
        --secondary-color: #f0f2f5;
        --accent-color: #ff6b35;
        --success-color: #4caf50;
        --warning-color: #ff9800;
        --danger-color: #f44336;
        --info-color: #2196f3;
        
        /* Neutral Colors */
        --white: #ffffff;
        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-400: #9ca3af;
        --gray-500: #6b7280;
        --gray-600: #4b5563;
        --gray-700: #374151;
        --gray-800: #1f2937;
        --gray-900: #111827;
        
        /* Typography */
        --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        --font-size-xs: 0.75rem;
        --font-size-sm: 0.875rem;
        --font-size-base: 1rem;
        --font-size-lg: 1.125rem;
        --font-size-xl: 1.25rem;
        --font-size-2xl: 1.5rem;
        --font-size-3xl: 1.875rem;
        --font-size-4xl: 2.25rem;
        
        /* Spacing */
        --spacing-1: 0.25rem;
        --spacing-2: 0.5rem;
        --spacing-3: 0.75rem;
        --spacing-4: 1rem;
        --spacing-5: 1.25rem;
        --spacing-6: 1.5rem;
        --spacing-8: 2rem;
        --spacing-10: 2.5rem;
        --spacing-12: 3rem;
        --spacing-16: 4rem;
        --spacing-20: 5rem;
        
        /* Border Radius */
        --radius-sm: 0.25rem;
        --radius-md: 0.375rem;
        --radius-lg: 0.5rem;
        --radius-xl: 0.75rem;
        --radius-2xl: 1rem;
        --radius-full: 9999px;
        
        /* Shadows */
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        
        /* Transitions */
        --transition-fast: 150ms ease-in-out;
        --transition-normal: 300ms ease-in-out;
        --transition-slow: 500ms ease-in-out;
        
        /* Z-Index */
        --z-dropdown: 1000;
        --z-sticky: 1020;
        --z-fixed: 1030;
        --z-modal-backdrop: 1040;
        --z-modal: 1050;
        --z-popover: 1060;
        --z-tooltip: 1070;
        
        /* Layout */
        --sidebar-width: 280px;
        --header-height: 70px;
        --container-max-width: 1200px;
    }
    
    /* Modern Reset and Base Styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    html {
        scroll-behavior: smooth;
    }
    
    body {
        font-family: var(--font-family);
        line-height: 1.6;
        color: var(--gray-800);
        background-color: var(--gray-50);
    }
    
    /* Modern Button Components */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--spacing-2);
        padding: var(--spacing-3) var(--spacing-6);
        font-size: var(--font-size-sm);
        font-weight: 600;
        border-radius: var(--radius-lg);
        border: none;
        cursor: pointer;
        transition: all var(--transition-fast);
        text-decoration: none;
        white-space: nowrap;
    }
    
    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .btn-primary {
        background: var(--primary-color);
        color: var(--white);
    }
    
    .btn-primary:hover:not(:disabled) {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: var(--shadow-lg);
    }
    
    .btn-secondary {
        background: var(--gray-200);
        color: var(--gray-700);
    }
    
    .btn-secondary:hover:not(:disabled) {
        background: var(--gray-300);
    }
    
    .btn-success {
        background: var(--success-color);
        color: var(--white);
    }
    
    .btn-danger {
        background: var(--danger-color);
        color: var(--white);
    }
    
    .btn-outline {
        background: transparent;
        border: 2px solid var(--primary-color);
        color: var(--primary-color);
    }
    
    .btn-outline:hover:not(:disabled) {
        background: var(--primary-color);
        color: var(--white);
    }
    
    /* Modern Card Components */
    .card {
        background: var(--white);
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-md);
        overflow: hidden;
        transition: all var(--transition-normal);
    }
    
    .card:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-2px);
    }
    
    .card-header {
        padding: var(--spacing-6);
        border-bottom: 1px solid var(--gray-200);
    }
    
    .card-body {
        padding: var(--spacing-6);
    }
    
    .card-footer {
        padding: var(--spacing-6);
        border-top: 1px solid var(--gray-200);
        background: var(--gray-50);
    }
    
    /* Modern Form Components */
    .form-group {
        margin-bottom: var(--spacing-6);
    }
    
    .form-label {
        display: block;
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--gray-700);
        margin-bottom: var(--spacing-2);
    }
    
    .form-input {
        width: 100%;
        padding: var(--spacing-3) var(--spacing-4);
        font-size: var(--font-size-base);
        border: 2px solid var(--gray-300);
        border-radius: var(--radius-lg);
        transition: all var(--transition-fast);
        background: var(--white);
    }
    
    .form-input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(0, 121, 107, 0.1);
    }
    
    .form-input:disabled {
        background: var(--gray-100);
        cursor: not-allowed;
    }
    
    /* Modern Alert Components */
    .alert {
        padding: var(--spacing-4) var(--spacing-6);
        border-radius: var(--radius-lg);
        border-left: 4px solid;
        margin-bottom: var(--spacing-6);
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
        border-left-color: var(--success-color);
    }
    
    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border-left-color: var(--danger-color);
    }
    
    .alert-warning {
        background: #fff3cd;
        color: #856404;
        border-left-color: var(--warning-color);
    }
    
    .alert-info {
        background: #d1ecf1;
        color: #0c5460;
        border-left-color: var(--info-color);
    }
    
    /* Modern Badge Components */
    .badge {
        display: inline-flex;
        align-items: center;
        padding: var(--spacing-1) var(--spacing-3);
        font-size: var(--font-size-xs);
        font-weight: 600;
        border-radius: var(--radius-full);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .badge-success {
        background: var(--success-color);
        color: var(--white);
    }
    
    .badge-warning {
        background: var(--warning-color);
        color: var(--white);
    }
    
    .badge-danger {
        background: var(--danger-color);
        color: var(--white);
    }
    
    .badge-info {
        background: var(--info-color);
        color: var(--white);
    }
    
    /* Modern Loading States */
    .loading {
        position: relative;
        overflow: hidden;
    }
    
    .loading::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        animation: loading 1.5s infinite;
    }
    
    @keyframes loading {
        0% { left: -100%; }
        100% { left: 100%; }
    }
    
    /* Modern Grid System */
    .grid {
        display: grid;
        gap: var(--spacing-6);
    }
    
    .grid-cols-1 { grid-template-columns: repeat(1, 1fr); }
    .grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
    .grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
    .grid-cols-4 { grid-template-columns: repeat(4, 1fr); }
    
    /* Modern Flexbox Utilities */
    .flex { display: flex; }
    .flex-col { flex-direction: column; }
    .items-center { align-items: center; }
    .justify-center { justify-content: center; }
    .justify-between { justify-content: space-between; }
    .gap-2 { gap: var(--spacing-2); }
    .gap-4 { gap: var(--spacing-4); }
    .gap-6 { gap: var(--spacing-6); }
    
    /* Modern Spacing Utilities */
    .p-4 { padding: var(--spacing-4); }
    .p-6 { padding: var(--spacing-6); }
    .px-4 { padding-left: var(--spacing-4); padding-right: var(--spacing-4); }
    .py-4 { padding-top: var(--spacing-4); padding-bottom: var(--spacing-4); }
    .m-4 { margin: var(--spacing-4); }
    .m-6 { margin: var(--spacing-6); }
    .mb-4 { margin-bottom: var(--spacing-4); }
    .mb-6 { margin-bottom: var(--spacing-6); }
    
    /* Modern Responsive Design */
    @media (max-width: 768px) {
        .grid-cols-2,
        .grid-cols-3,
        .grid-cols-4 {
            grid-template-columns: 1fr;
        }
        
        .btn {
            width: 100%;
        }
        
        .card {
            margin: var(--spacing-4);
        }
    }
    
    /* Modern Animations */
    .fade-in {
        animation: fadeIn 0.5s ease-in-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .slide-in {
        animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
        from { transform: translateX(-100%); }
        to { transform: translateX(0); }
    }
    
    /* Modern Scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
    }
    
    ::-webkit-scrollbar-track {
        background: var(--gray-100);
    }
    
    ::-webkit-scrollbar-thumb {
        background: var(--gray-400);
        border-radius: var(--radius-full);
    }
    
    ::-webkit-scrollbar-thumb:hover {
        background: var(--gray-500);
    }
    ";
}

// Modern JavaScript Utilities
function get_modern_js_utilities() {
    return "
    // Modern JavaScript Utilities
    const ModernUI = {
        // Toast notifications
        showToast: function(message, type = 'info', duration = 3000) {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div class='toast-content'>
                    <span class='toast-message'>${message}</span>
                    <button class='toast-close' onclick='this.parentElement.parentElement.remove()'>×</button>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        },
        
        // Loading states
        setLoading: function(element, loading = true) {
            if (loading) {
                element.classList.add('loading');
                element.disabled = true;
            } else {
                element.classList.remove('loading');
                element.disabled = false;
            }
        },
        
        // Form validation
        validateForm: function(form) {
            const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('error');
                    isValid = false;
                } else {
                    input.classList.remove('error');
                }
            });
            
            return isValid;
        },
        
        // Smooth scrolling
        scrollTo: function(element, offset = 0) {
            const target = typeof element === 'string' ? document.querySelector(element) : element;
            if (target) {
                const targetPosition = target.offsetTop - offset;
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        },
        
        // Debounce function
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },
        
        // Local storage utilities
        storage: {
            set: function(key, value) {
                localStorage.setItem(key, JSON.stringify(value));
            },
            get: function(key, defaultValue = null) {
                const item = localStorage.getItem(key);
                return item ? JSON.parse(item) : defaultValue;
            },
            remove: function(key) {
                localStorage.removeItem(key);
            }
        }
    };
    
    // Auto-hide alerts
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    });
    
    // Form auto-save
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form[data-auto-save]');
        forms.forEach(form => {
            const formId = form.getAttribute('data-auto-save');
            const inputs = form.querySelectorAll('input, select, textarea');
            
            // Load saved data
            const savedData = ModernUI.storage.get(`form_${formId}`, {});
            inputs.forEach(input => {
                if (savedData[input.name]) {
                    input.value = savedData[input.name];
                }
            });
            
            // Auto-save on input
            const saveForm = ModernUI.debounce(() => {
                const formData = {};
                inputs.forEach(input => {
                    if (input.name) {
                        formData[input.name] = input.value;
                    }
                });
                ModernUI.storage.set(`form_${formId}`, formData);
            }, 1000);
            
            inputs.forEach(input => {
                input.addEventListener('input', saveForm);
            });
            
            // Clear saved data on submit
            form.addEventListener('submit', () => {
                ModernUI.storage.remove(`form_${formId}`);
            });
        });
    });
    ";
}

// Modern Toast CSS
function get_toast_css() {
    return "
    .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        padding: var(--spacing-4);
        transform: translateX(100%);
        transition: transform var(--transition-normal);
        z-index: var(--z-tooltip);
        max-width: 400px;
    }
    
    .toast.show {
        transform: translateX(0);
    }
    
    .toast-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--spacing-3);
    }
    
    .toast-message {
        flex: 1;
        font-size: var(--font-size-sm);
    }
    
    .toast-close {
        background: none;
        border: none;
        font-size: var(--font-size-lg);
        cursor: pointer;
        color: var(--gray-500);
        padding: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .toast-close:hover {
        color: var(--gray-700);
    }
    
    .toast-success {
        border-left: 4px solid var(--success-color);
    }
    
    .toast-danger {
        border-left: 4px solid var(--danger-color);
    }
    
    .toast-warning {
        border-left: 4px solid var(--warning-color);
    }
    
    .toast-info {
        border-left: 4px solid var(--info-color);
    }
    ";
}

// Function to include modern UI styles
function include_modern_ui() {
    echo '<style>' . get_modern_css_variables() . get_toast_css() . '</style>';
    echo '<script>' . get_modern_js_utilities() . '</script>';
}
?>

























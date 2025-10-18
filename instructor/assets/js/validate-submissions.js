/**
 * TaaBia Validate Submissions JavaScript
 * 
 * Enhanced functionality for submissions management page
 */

class ValidateSubmissionsManager {
    constructor() {
        this.init();
        this.setupEventListeners();
        this.setupAutoRefresh();
    }

    init() {
        console.log('Validate Submissions Manager initialized');
        this.updateStats();
        this.setupBulkActions();
        this.setupFilters();
    }

    setupEventListeners() {
        // Auto-submit form when filters change
        const filterSelects = document.querySelectorAll('.filter-select');
        filterSelects.forEach(select => {
            select.addEventListener('change', () => {
                this.debounce(() => {
                    document.getElementById('filtersForm').submit();
                }, 300)();
            });
        });

        // Search with debounce
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                this.debounce(() => {
                    document.getElementById('filtersForm').submit();
                }, 500)();
            });
        }

        // Bulk selection
        const selectAllCheckbox = document.getElementById('selectAll');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                this.toggleAllSelections(e.target.checked);
            });
        }

        // Individual selection checkboxes
        const submissionCheckboxes = document.querySelectorAll('.submission-checkbox');
        submissionCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                this.updateBulkActionsState();
            });
        });

        // Bulk action buttons
        const bulkActionBtn = document.getElementById('bulkActionBtn');
        if (bulkActionBtn) {
            bulkActionBtn.addEventListener('click', () => {
                this.executeBulkAction();
            });
        }

        // Quick action buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('.quick-approve')) {
                e.preventDefault();
                this.quickApprove(e.target.dataset.id);
            } else if (e.target.matches('.quick-reject')) {
                e.preventDefault();
                this.quickReject(e.target.dataset.id);
            } else if (e.target.matches('.view-submission')) {
                e.preventDefault();
                this.viewSubmission(e.target.dataset.id);
            }
        });
    }

    setupBulkActions() {
        // Add bulk action controls if they don't exist
        const submissionsHeader = document.querySelector('.submissions-header');
        if (submissionsHeader && !document.getElementById('bulkActions')) {
            const bulkActionsHTML = `
                <div id="bulkActions" class="bulk-actions" style="display: none;">
                    <select id="bulkActionSelect" class="bulk-select">
                        <option value=""><?= __('select_action') ?></option>
                        <option value="approve"><?= __('approve_selected') ?></option>
                        <option value="reject"><?= __('reject_selected') ?></option>
                        <option value="export"><?= __('export_selected') ?></option>
                    </select>
                    <button id="bulkActionBtn" class="btn btn-primary btn-sm">
                        <i class="fas fa-check"></i> <?= __('execute') ?>
                    </button>
                    <span id="selectedCount" class="selected-count">0 <?= __('selected') ?></span>
                </div>
            `;
            submissionsHeader.insertAdjacentHTML('beforeend', bulkActionsHTML);
        }
    }

    setupFilters() {
        // Add date range picker functionality
        const dateFromInput = document.querySelector('input[name="date_from"]');
        const dateToInput = document.querySelector('input[name="date_to"]');
        
        if (dateFromInput && dateToInput) {
            dateFromInput.addEventListener('change', () => {
                if (dateFromInput.value && dateToInput.value && dateFromInput.value > dateToInput.value) {
                    dateToInput.value = dateFromInput.value;
                }
            });
            
            dateToInput.addEventListener('change', () => {
                if (dateFromInput.value && dateToInput.value && dateFromInput.value > dateToInput.value) {
                    dateFromInput.value = dateToInput.value;
                }
            });
        }
    }

    setupAutoRefresh() {
        // Auto-refresh every 5 minutes
        setInterval(() => {
            this.refreshData();
        }, 300000);
    }

    toggleAdvancedFilters() {
        const advancedFilters = document.getElementById('advancedFilters');
        if (advancedFilters) {
            const isVisible = advancedFilters.style.display !== 'none';
            advancedFilters.style.display = isVisible ? 'none' : 'block';
            
            // Update button text
            const button = document.querySelector('[onclick="toggleAdvancedFilters()"]');
            if (button) {
                const icon = button.querySelector('i');
                if (isVisible) {
                    icon.className = 'fas fa-sliders-h';
                    button.innerHTML = `<i class="fas fa-sliders-h"></i> <?= __('advanced_filters') ?>`;
                } else {
                    icon.className = 'fas fa-chevron-up';
                    button.innerHTML = `<i class="fas fa-chevron-up"></i> <?= __('hide_filters') ?>`;
                }
            }
        }
    }

    clearAllFilters() {
        const form = document.getElementById('filtersForm');
        if (form) {
            form.reset();
            form.submit();
        }
    }

    toggleAllSelections(checked) {
        const checkboxes = document.querySelectorAll('.submission-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = checked;
        });
        this.updateBulkActionsState();
    }

    updateBulkActionsState() {
        const selectedCheckboxes = document.querySelectorAll('.submission-checkbox:checked');
        const bulkActions = document.getElementById('bulkActions');
        const selectedCount = document.getElementById('selectedCount');
        
        if (bulkActions && selectedCount) {
            if (selectedCheckboxes.length > 0) {
                bulkActions.style.display = 'flex';
                selectedCount.textContent = `${selectedCheckboxes.length} <?= __('selected') ?>`;
            } else {
                bulkActions.style.display = 'none';
            }
        }
    }

    executeBulkAction() {
        const action = document.getElementById('bulkActionSelect').value;
        const selectedIds = Array.from(document.querySelectorAll('.submission-checkbox:checked'))
            .map(checkbox => checkbox.value);
        
        if (!action || selectedIds.length === 0) {
            this.showNotification('<?= __('please_select_action_and_submissions') ?>', 'warning');
            return;
        }

        if (confirm(`<?= __('confirm_bulk_action') ?> ${selectedIds.length} <?= __('submissions') ?>?`)) {
            this.performBulkAction(action, selectedIds);
        }
    }

    async performBulkAction(action, submissionIds) {
        try {
            const response = await fetch('api/bulk_submission_action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: action,
                    submission_ids: submissionIds
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification(result.message, 'success');
                this.refreshData();
            } else {
                this.showNotification(result.message, 'error');
            }
        } catch (error) {
            console.error('Bulk action error:', error);
            this.showNotification('<?= __('error_occurred') ?>', 'error');
        }
    }

    async quickApprove(submissionId) {
        if (confirm('<?= __('confirm_approve_submission') ?>?')) {
            try {
                const response = await fetch('api/quick_submission_action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'approve',
                        submission_id: submissionId
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    this.showNotification('<?= __('submission_approved') ?>', 'success');
                    this.refreshData();
                } else {
                    this.showNotification(result.message, 'error');
                }
            } catch (error) {
                console.error('Quick approve error:', error);
                this.showNotification('<?= __('error_occurred') ?>', 'error');
            }
        }
    }

    async quickReject(submissionId) {
        const feedback = prompt('<?= __('rejection_reason') ?>:');
        if (feedback !== null) {
            try {
                const response = await fetch('api/quick_submission_action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'reject',
                        submission_id: submissionId,
                        feedback: feedback
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    this.showNotification('<?= __('submission_rejected') ?>', 'success');
                    this.refreshData();
                } else {
                    this.showNotification(result.message, 'error');
                }
            } catch (error) {
                console.error('Quick reject error:', error);
                this.showNotification('<?= __('error_occurred') ?>', 'error');
            }
        }
    }

    viewSubmission(submissionId) {
        // Open submission in modal or new window
        window.open(`view_submission.php?id=${submissionId}`, '_blank');
    }

    async refreshData() {
        const page = document.querySelector('.main-content');
        if (page) {
            page.classList.add('loading');
        }

        try {
            // Reload the page to get fresh data
            window.location.reload();
        } catch (error) {
            console.error('Refresh error:', error);
            if (page) {
                page.classList.remove('loading');
            }
        }
    }

    async updateStats() {
        try {
            const response = await fetch('api/submission_analytics.php');
            const stats = await response.json();
            
            // Update stat cards with new data
            this.updateStatCard('total_submissions', stats.total_submissions);
            this.updateStatCard('pending_submissions', stats.pending_submissions);
            this.updateStatCard('overdue_submissions', stats.overdue_submissions);
            this.updateStatCard('approved_submissions', stats.approved_submissions);
            this.updateStatCard('avg_grade', stats.avg_grade);
            this.updateStatCard('unique_students', stats.unique_students);
        } catch (error) {
            console.error('Stats update error:', error);
        }
    }

    updateStatCard(statType, value) {
        const statElement = document.querySelector(`[data-stat="${statType}"]`);
        if (statElement) {
            const currentValue = parseInt(statElement.textContent);
            if (currentValue !== value) {
                this.animateValue(statElement, currentValue, value, 1000);
            }
        }
    }

    animateValue(element, start, end, duration) {
        const startTimestamp = performance.now();
        const step = (timestamp) => {
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            const current = Math.floor(progress * (end - start) + start);
            element.textContent = current;
            if (progress < 1) {
                requestAnimationFrame(step);
            }
        };
        requestAnimationFrame(step);
    }

    exportSubmissions() {
        const form = document.getElementById('filtersForm');
        if (form) {
            const exportForm = form.cloneNode(true);
            exportForm.action = 'export_submissions.php';
            exportForm.method = 'POST';
            document.body.appendChild(exportForm);
            exportForm.submit();
            document.body.removeChild(exportForm);
        }
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${this.getNotificationIcon(type)}"></i>
                <span>${message}</span>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        // Add to page
        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Global functions for onclick handlers
function toggleAdvancedFilters() {
    if (window.submissionsManager) {
        window.submissionsManager.toggleAdvancedFilters();
    }
}

function clearAllFilters() {
    if (window.submissionsManager) {
        window.submissionsManager.clearAllFilters();
    }
}

function exportSubmissions() {
    if (window.submissionsManager) {
        window.submissionsManager.exportSubmissions();
    }
}

function refreshData() {
    if (window.submissionsManager) {
        window.submissionsManager.refreshData();
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.submissionsManager = new ValidateSubmissionsManager();
});

// Add notification styles
const notificationStyles = `
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        max-width: 400px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        border-left: 4px solid #2563eb;
        animation: slideInRight 0.3s ease-out;
    }

    .notification-success { border-left-color: #059669; }
    .notification-error { border-left-color: #dc2626; }
    .notification-warning { border-left-color: #d97706; }

    .notification-content {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px;
    }

    .notification-close {
        background: none;
        border: none;
        cursor: pointer;
        padding: 4px;
        margin-left: auto;
        opacity: 0.6;
    }

    .notification-close:hover {
        opacity: 1;
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;

// Inject notification styles
const styleSheet = document.createElement('style');
styleSheet.textContent = notificationStyles;
document.head.appendChild(styleSheet);

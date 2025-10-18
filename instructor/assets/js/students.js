/**
 * Students Management Page JavaScript
 * 
 * Advanced functionality for student management including:
 * - Dynamic filtering and search
 * - View switching (grid/list)
 * - Bulk operations
 * - Real-time updates
 * - Export functionality
 */

class StudentsManager {
    constructor() {
        this.config = window.studentsConfig || {};
        this.currentView = 'grid';
        this.selectedStudents = new Set();
        this.filters = {
            search: '',
            course: '',
            status: '',
            sort: 'name',
            per_page: 20
        };
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.initializeFilters();
        this.setupAutoRefresh();
        this.initializeTooltips();
        this.setupKeyboardShortcuts();
    }

    setupEventListeners() {
        // View toggle buttons
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.switchView(e.target.dataset.view);
            });
        });

        // Filter form
        const filterForm = document.getElementById('filtersForm');
        if (filterForm) {
            filterForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.applyFilters();
            });

            // Auto-submit on filter changes
            filterForm.querySelectorAll('select').forEach(select => {
                select.addEventListener('change', () => {
                    this.applyFilters();
                });
            });

            // Debounced search
            const searchInput = filterForm.querySelector('input[name="search"]');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.applyFilters();
                    }, 500);
                });
            }
        }

        // Student card interactions
        document.querySelectorAll('.student-card').forEach(card => {
            this.setupStudentCardEvents(card);
        });

        // Bulk selection
        document.addEventListener('click', (e) => {
            if (e.target.matches('.select-all-btn')) {
                this.selectAllStudents();
            }
        });

        // Export functionality
        document.addEventListener('click', (e) => {
            if (e.target.matches('.export-btn')) {
                this.exportStudents();
            }
        });
    }

    setupStudentCardEvents(card) {
        const studentId = card.dataset.studentId;
        
        // Card selection
        card.addEventListener('click', (e) => {
            if (e.target.closest('.action-btn')) return;
            
            this.toggleStudentSelection(studentId, card);
        });

        // Action buttons
        card.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.handleStudentAction(btn, studentId);
            });
        });

        // Hover effects
        card.addEventListener('mouseenter', () => {
            this.highlightRelatedStudents(studentId);
        });

        card.addEventListener('mouseleave', () => {
            this.clearHighlights();
        });
    }

    switchView(view) {
        if (view === this.currentView) return;

        this.currentView = view;
        
        // Update view buttons
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === view);
        });

        // Update students container
        const studentsContent = document.getElementById('studentsContent');
        if (studentsContent) {
            studentsContent.className = `students-content ${view}-view`;
        }

        // Animate transition
        this.animateViewTransition();
    }

    animateViewTransition() {
        const cards = document.querySelectorAll('.student-card');
        cards.forEach((card, index) => {
            card.style.animation = 'none';
            card.offsetHeight; // Trigger reflow
            card.style.animation = `fadeInUp 0.3s ease-out ${index * 0.1}s`;
        });
    }

    applyFilters() {
        const form = document.getElementById('filtersForm');
        if (!form) return;

        const formData = new FormData(form);
        const params = new URLSearchParams();
        
        for (const [key, value] of formData.entries()) {
            if (value.trim()) {
                params.append(key, value);
            }
        }

        // Add current page if not specified
        if (!params.has('page')) {
            params.append('page', '1');
        }

        // Navigate to filtered results
        window.location.href = `students.php?${params.toString()}`;
    }

    clearFilters() {
        const form = document.getElementById('filtersForm');
        if (form) {
            form.reset();
            this.applyFilters();
        }
    }

    saveFilters() {
        const form = document.getElementById('filtersForm');
        if (!form) return;

        const formData = new FormData(form);
        const filters = {};
        
        for (const [key, value] of formData.entries()) {
            filters[key] = value;
        }

        // Save to localStorage
        localStorage.setItem('students_filters', JSON.stringify(filters));
        
        this.showNotification('<?= __('filters_saved') ?>', 'success');
    }

    loadSavedFilters() {
        const saved = localStorage.getItem('students_filters');
        if (!saved) return;

        try {
            const filters = JSON.parse(saved);
            const form = document.getElementById('filtersForm');
            if (!form) return;

            Object.entries(filters).forEach(([key, value]) => {
                const input = form.querySelector(`[name="${key}"]`);
                if (input) {
                    input.value = value;
                }
            });
        } catch (e) {
            console.error('Error loading saved filters:', e);
        }
    }

    toggleStudentSelection(studentId, card) {
        if (this.selectedStudents.has(studentId)) {
            this.selectedStudents.delete(studentId);
            card.classList.remove('selected');
        } else {
            this.selectedStudents.add(studentId);
            card.classList.add('selected');
        }

        this.updateSelectionUI();
    }

    selectAllStudents() {
        const cards = document.querySelectorAll('.student-card');
        cards.forEach(card => {
            const studentId = card.dataset.studentId;
            this.selectedStudents.add(studentId);
            card.classList.add('selected');
        });

        this.updateSelectionUI();
    }

    clearSelection() {
        this.selectedStudents.clear();
        document.querySelectorAll('.student-card').forEach(card => {
            card.classList.remove('selected');
        });
        this.updateSelectionUI();
    }

    updateSelectionUI() {
        const count = this.selectedStudents.size;
        const selectAllBtn = document.querySelector('.select-all-btn');
        
        if (selectAllBtn) {
            selectAllBtn.textContent = count > 0 ? 
                `<?= __('clear_selection') ?> (${count})` : 
                '<?= __('select_all') ?>';
        }

        // Show/hide bulk actions
        this.toggleBulkActions(count > 0);
    }

    toggleBulkActions(show) {
        let bulkActions = document.querySelector('.bulk-actions');
        
        if (show && !bulkActions) {
            bulkActions = this.createBulkActions();
            document.querySelector('.students-header').appendChild(bulkActions);
        } else if (!show && bulkActions) {
            bulkActions.remove();
        }
    }

    createBulkActions() {
        const container = document.createElement('div');
        container.className = 'bulk-actions';
        container.innerHTML = `
            <div class="bulk-actions-content">
                <span class="bulk-count">${this.selectedStudents.size} <?= __('students_selected') ?></span>
                <div class="bulk-buttons">
                    <button class="btn btn-sm btn-primary" onclick="studentsManager.bulkMessage()">
                        <i class="fas fa-envelope"></i> <?= __('send_message') ?>
                    </button>
                    <button class="btn btn-sm btn-success" onclick="studentsManager.bulkExport()">
                        <i class="fas fa-download"></i> <?= __('export_selected') ?>
                    </button>
                    <button class="btn btn-sm btn-warning" onclick="studentsManager.bulkUpdateProgress()">
                        <i class="fas fa-edit"></i> <?= __('update_progress') ?>
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="studentsManager.clearSelection()">
                        <i class="fas fa-times"></i> <?= __('clear') ?>
                    </button>
                </div>
            </div>
        `;
        return container;
    }

    handleStudentAction(button, studentId) {
        const action = button.classList.contains('primary') ? 'progress' :
                      button.classList.contains('info') ? 'message' :
                      button.classList.contains('success') ? 'update' :
                      button.classList.contains('danger') ? 'remove' : null;

        if (!action) return;

        switch (action) {
            case 'progress':
                this.viewStudentProgress(studentId);
                break;
            case 'message':
                this.messageStudent(studentId);
                break;
            case 'update':
                this.updateStudentProgress(studentId);
                break;
            case 'remove':
                this.removeStudent(studentId);
                break;
        }
    }

    viewStudentProgress(studentId) {
        // Find course ID from the student card
        const card = document.querySelector(`[data-student-id="${studentId}"]`);
        const courseId = card?.dataset.courseId;
        
        if (courseId) {
            window.open(`view_student_progress.php?student_id=${studentId}&course_id=${courseId}`, '_blank');
        }
    }

    messageStudent(studentId) {
        window.open(`message_student.php?id=${studentId}`, '_blank');
    }

    updateStudentProgress(studentId) {
        const card = document.querySelector(`[data-student-id="${studentId}"]`);
        const courseId = card?.dataset.courseId;
        
        if (courseId) {
            window.open(`update_progress.php?student_id=${studentId}&course_id=${courseId}`, '_blank');
        }
    }

    removeStudent(studentId) {
        if (!confirm('<?= __('confirm_remove_student') ?>')) return;

        const card = document.querySelector(`[data-student-id="${studentId}"]`);
        const courseId = card?.dataset.courseId;
        
        if (courseId) {
            this.performRemoveStudent(studentId, courseId);
        }
    }

    async performRemoveStudent(studentId, courseId) {
        try {
            const response = await fetch('api/remove_student.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    student_id: studentId,
                    course_id: courseId
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('<?= __('student_removed_successfully') ?>', 'success');
                // Remove card from DOM
                const card = document.querySelector(`[data-student-id="${studentId}"]`);
                if (card) {
                    card.style.animation = 'fadeOut 0.3s ease-out';
                    setTimeout(() => card.remove(), 300);
                }
            } else {
                this.showNotification(result.message || '<?= __('error_removing_student') ?>', 'error');
            }
        } catch (error) {
            console.error('Error removing student:', error);
            this.showNotification('<?= __('error_removing_student') ?>', 'error');
        }
    }

    bulkMessage() {
        if (this.selectedStudents.size === 0) return;
        
        const studentIds = Array.from(this.selectedStudents);
        window.open(`bulk_message.php?students=${studentIds.join(',')}`, '_blank');
    }

    bulkExport() {
        if (this.selectedStudents.size === 0) return;
        
        const studentIds = Array.from(this.selectedStudents);
        const params = new URLSearchParams({
            students: studentIds.join(','),
            format: 'csv'
        });
        
        window.open(`api/export_students.php?${params.toString()}`, '_blank');
    }

    bulkUpdateProgress() {
        if (this.selectedStudents.size === 0) return;
        
        const studentIds = Array.from(this.selectedStudents);
        window.open(`bulk_update_progress.php?students=${studentIds.join(',')}`, '_blank');
    }

    exportStudents() {
        const params = new URLSearchParams(window.location.search);
        params.set('format', 'csv');
        params.set('export', 'all');
        
        window.open(`api/export_students.php?${params.toString()}`, '_blank');
    }

    refreshData() {
        const refreshBtn = document.querySelector('.refresh-btn');
        if (refreshBtn) {
            refreshBtn.disabled = true;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?= __('refreshing') ?>...';
        }

        // Reload page after a short delay
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }

    setupAutoRefresh() {
        // Auto-refresh every 5 minutes
        setInterval(() => {
            this.refreshData();
        }, 300000);
    }

    initializeFilters() {
        this.loadSavedFilters();
    }

    initializeTooltips() {
        // Initialize tooltips for action buttons
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('mouseenter', (e) => {
                this.showTooltip(e.target, e.target.title);
            });
            
            btn.addEventListener('mouseleave', () => {
                this.hideTooltip();
            });
        });
    }

    showTooltip(element, text) {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = text;
        tooltip.style.cssText = `
            position: absolute;
            background: var(--gray-800);
            color: var(--white);
            padding: var(--spacing-2) var(--spacing-3);
            border-radius: var(--border-radius);
            font-size: var(--font-size-xs);
            z-index: 1000;
            pointer-events: none;
            white-space: nowrap;
        `;

        document.body.appendChild(tooltip);

        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';

        this.currentTooltip = tooltip;
    }

    hideTooltip() {
        if (this.currentTooltip) {
            this.currentTooltip.remove();
            this.currentTooltip = null;
        }
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + A to select all
            if ((e.ctrlKey || e.metaKey) && e.key === 'a' && e.target.tagName !== 'INPUT') {
                e.preventDefault();
                this.selectAllStudents();
            }
            
            // Escape to clear selection
            if (e.key === 'Escape') {
                this.clearSelection();
            }
            
            // Ctrl/Cmd + F to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) {
                    searchInput.focus();
                }
            }
        });
    }

    highlightRelatedStudents(studentId) {
        // Highlight students from the same course
        const card = document.querySelector(`[data-student-id="${studentId}"]`);
        if (!card) return;

        const courseId = card.dataset.courseId;
        document.querySelectorAll('.student-card').forEach(otherCard => {
            if (otherCard.dataset.courseId === courseId && otherCard.dataset.studentId !== studentId) {
                otherCard.classList.add('related');
            }
        });
    }

    clearHighlights() {
        document.querySelectorAll('.student-card.related').forEach(card => {
            card.classList.remove('related');
        });
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            min-width: 300px;
            animation: slideInRight 0.3s ease-out;
        `;

        document.body.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }
        }, 5000);
    }

    // Public methods for global access
    static getInstance() {
        if (!window.studentsManager) {
            window.studentsManager = new StudentsManager();
        }
        return window.studentsManager;
    }
}

// Global functions for onclick handlers
function removeStudent(studentId, courseId) {
    const manager = StudentsManager.getInstance();
    manager.performRemoveStudent(studentId, courseId);
}

function exportStudents() {
    const manager = StudentsManager.getInstance();
    manager.exportStudents();
}

function refreshData() {
    const manager = StudentsManager.getInstance();
    manager.refreshData();
}

function clearFilters() {
    const manager = StudentsManager.getInstance();
    manager.clearFilters();
}

function saveFilters() {
    const manager = StudentsManager.getInstance();
    manager.saveFilters();
}

function selectAll() {
    const manager = StudentsManager.getInstance();
    manager.selectAllStudents();
}

// Initialize when DOM is loaded
function initializeStudentsPage() {
    window.studentsManager = StudentsManager.getInstance();
    
    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; transform: scale(1); }
            to { opacity: 0; transform: scale(0.95); }
        }
        
        .student-card.selected {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }
        
        .student-card.related {
            border-color: var(--info-color);
            background: rgba(6, 182, 212, 0.05);
        }
        
        .bulk-actions {
            position: sticky;
            top: 0;
            background: var(--primary-color);
            color: var(--white);
            padding: var(--spacing-3) var(--spacing-4);
            border-radius: var(--border-radius);
            margin-bottom: var(--spacing-4);
            z-index: 100;
        }
        
        .bulk-actions-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .bulk-buttons {
            display: flex;
            gap: var(--spacing-2);
        }
        
        .bulk-buttons .btn {
            background: rgba(255, 255, 255, 0.2);
            color: var(--white);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .bulk-buttons .btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .notification-content {
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            padding: var(--spacing-3);
        }
        
        .notification-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            margin-left: auto;
        }
    `;
    document.head.appendChild(style);
    
    console.log('Students page initialized successfully');
}

// Export for global access
window.StudentsManager = StudentsManager;

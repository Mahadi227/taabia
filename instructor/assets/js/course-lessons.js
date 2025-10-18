/**
 * Course Lessons Page JavaScript - Optimized
 * 
 * This file contains all JavaScript functionality for the course lessons page.
 * Organized for maintainability, performance, and user experience.
 */

// ============================================================================
// CONFIGURATION & CONSTANTS
// ============================================================================
const CONFIG = {
    courseId: null, // Will be set from PHP
    language: 'fr', // Will be set from PHP
    apiEndpoint: 'api/course_analytics.php',
    refreshInterval: 30000, // 30 seconds
    chartColors: {
        primary: '#2563eb',
        success: '#10b981',
        warning: '#f59e0b',
        danger: '#ef4444',
        info: '#06b6d4'
    },
    breakpoints: {
        mobile: 768,
        tablet: 1024,
        desktop: 1200
    }
};

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================
const Utils = {
    /**
     * Format number with locale-specific separators
     */
    formatNumber: (num) => {
        return new Intl.NumberFormat(CONFIG.language).format(num);
    },

    /**
     * Format date with locale-specific format
     */
    formatDate: (dateString) => {
        return new Date(dateString).toLocaleDateString(CONFIG.language);
    },

    /**
     * Format time duration in minutes
     */
    formatDuration: (minutes) => {
        if (minutes < 60) {
            return `${minutes}m`;
        }
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
    },

    /**
     * Show loading state for element
     */
    showLoading: (elementId) => {
        const element = document.getElementById(elementId);
        if (element) {
            element.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            element.classList.add('loading');
        }
    },

    /**
     * Show error state for element
     */
    showError: (elementId, message = 'Error') => {
        const element = document.getElementById(elementId);
        if (element) {
            element.innerHTML = `<span class="error-text">${message}</span>`;
            element.classList.add('error');
        }
    },

    /**
     * Clear loading/error states
     */
    clearState: (elementId) => {
        const element = document.getElementById(elementId);
        if (element) {
            element.classList.remove('loading', 'error');
        }
    },

    /**
     * Debounce function calls
     */
    debounce: (func, wait) => {
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

    /**
     * Throttle function calls
     */
    throttle: (func, limit) => {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    /**
     * Get content type icon
     */
    getContentTypeIcon: (type) => {
        const icons = {
            'text': 'file-alt',
            'video': 'video',
            'pdf': 'file-pdf',
            'quiz': 'question-circle'
        };
        return icons[type] || 'file';
    },

    /**
     * Truncate text to specified length
     */
    truncateText: (text, length) => {
        if (!text) return '';
        return text.length > length ? text.substring(0, length) + '...' : text;
    },

    /**
     * Check if element is in viewport
     */
    isInViewport: (element) => {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    },

    /**
     * Smooth scroll to element
     */
    scrollToElement: (elementId, offset = 0) => {
        const element = document.getElementById(elementId);
        if (element) {
            const elementPosition = element.getBoundingClientRect().top;
            const offsetPosition = elementPosition + window.pageYOffset - offset;
            
            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth'
            });
        }
    }
};

// ============================================================================
// NOTIFICATION SYSTEM
// ============================================================================
class NotificationSystem {
    constructor() {
        this.container = this.createContainer();
    }

    createContainer() {
        let container = document.getElementById('notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notification-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                pointer-events: none;
            `;
            document.body.appendChild(container);
        }
        return container;
    }

    show(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.style.pointerEvents = 'auto';
        
        const icon = this.getIcon(type);
        notification.innerHTML = `
            <i class="fas fa-${icon}"></i>
            <span>${message}</span>
            <button class="notification-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;

        this.container.appendChild(notification);

        // Auto remove after duration
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, duration);

        return notification;
    }

    getIcon(type) {
        const icons = {
            'success': 'check-circle',
            'error': 'exclamation-triangle',
            'warning': 'exclamation-circle',
            'info': 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    success(message, duration) {
        return this.show(message, 'success', duration);
    }

    error(message, duration) {
        return this.show(message, 'error', duration);
    }

    warning(message, duration) {
        return this.show(message, 'warning', duration);
    }

    info(message, duration) {
        return this.show(message, 'info', duration);
    }
}

// ============================================================================
// ANALYTICS MANAGER
// ============================================================================
class AnalyticsManager {
    constructor() {
        this.data = null;
        this.charts = {};
        this.isLoading = false;
        this.notifications = new NotificationSystem();
        this.retryCount = 0;
        this.maxRetries = 3;
        this.init();
    }

    /**
     * Initialize analytics system
     */
    init() {
        this.setupEventListeners();
        this.loadAnalyticsData();
        this.setupAutoRefresh();
        this.setupIntersectionObserver();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Tab switching
        document.querySelectorAll('.analytics-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                this.switchTab(e.target.dataset.tab);
            });
        });

        // Window resize handler for charts
        window.addEventListener('resize', Utils.debounce(() => {
            this.resizeCharts();
        }, 250));

        // Visibility change handler
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseAutoRefresh();
            } else {
                this.resumeAutoRefresh();
            }
        });
    }

    /**
     * Setup intersection observer for lazy loading
     */
    setupIntersectionObserver() {
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const target = entry.target;
                        if (target.dataset.chartType) {
                            this.loadChart(target.dataset.chartType);
                        }
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('[data-chart-type]').forEach(element => {
                observer.observe(element);
            });
        }
    }

    /**
     * Load analytics data from API
     */
    async loadAnalyticsData() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showLoadingState();

        try {
            const response = await fetch(`${CONFIG.apiEndpoint}?course_id=${CONFIG.courseId}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                cache: 'no-cache'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();

            if (result.status === 'success') {
                this.data = result.data;
                this.updateAnalyticsDisplay();
                this.hideLoadingState();
                this.retryCount = 0; // Reset retry count on success
                this.notifications.success('Analytics data updated successfully', 3000);
            } else {
                throw new Error(result.message || 'Failed to load analytics');
            }
        } catch (error) {
            console.error('Analytics loading error:', error);
            this.handleLoadError(error);
        } finally {
            this.isLoading = false;
        }
    }

    /**
     * Handle loading errors with retry logic
     */
    handleLoadError(error) {
        this.showErrorState(error.message);
        
        if (this.retryCount < this.maxRetries) {
            this.retryCount++;
            const retryDelay = Math.pow(2, this.retryCount) * 1000; // Exponential backoff
            
            this.notifications.warning(
                `Failed to load analytics. Retrying in ${retryDelay/1000} seconds... (${this.retryCount}/${this.maxRetries})`,
                3000
            );
            
            setTimeout(() => {
                this.loadAnalyticsData();
            }, retryDelay);
        } else {
            this.notifications.error(
                'Failed to load analytics after multiple attempts. Please refresh the page.',
                10000
            );
        }
    }

    /**
     * Show loading state
     */
    showLoadingState() {
        const elements = ['totalViews', 'totalCompletions', 'averageRating', 'averageTime'];
        elements.forEach(id => Utils.showLoading(id));
    }

    /**
     * Hide loading state
     */
    hideLoadingState() {
        const elements = ['totalViews', 'totalCompletions', 'averageRating', 'averageTime'];
        elements.forEach(id => Utils.clearState(id));
    }

    /**
     * Show error state
     */
    showErrorState(message) {
        const elements = ['totalViews', 'totalCompletions', 'averageRating', 'averageTime'];
        elements.forEach(id => Utils.showError(id, 'Error'));
    }

    /**
     * Update analytics display with real data
     */
    updateAnalyticsDisplay() {
        if (!this.data) return;

        // Update overview cards
        this.updateOverviewCards();
        
        // Update charts
        this.updateCharts();
        
        // Update lessons table
        this.updateLessonsTable();
    }

    /**
     * Update overview cards
     */
    updateOverviewCards() {
        const overview = this.data.overview;
        
        document.getElementById('totalViews').textContent = Utils.formatNumber(overview.total_views);
        document.getElementById('totalCompletions').textContent = Utils.formatNumber(overview.total_completions);
        document.getElementById('averageRating').textContent = overview.average_rating.toFixed(1);
        document.getElementById('averageTime').textContent = Utils.formatDuration(overview.average_time);

        // Update key metrics
        document.getElementById('completionRate').textContent = overview.completion_rate + '%';
        document.getElementById('engagementRate').textContent = overview.engagement_rate + '%';
        document.getElementById('successRate').textContent = overview.success_rate + '%';
        document.getElementById('totalStudents').textContent = Utils.formatNumber(overview.total_students);
    }

    /**
     * Update charts with real data
     */
    updateCharts() {
        this.createEngagementChart();
        this.createTrendsCharts();
    }

    /**
     * Create engagement chart
     */
    createEngagementChart() {
        const ctx = document.getElementById('engagementChart');
        if (!ctx || !this.data.engagement) return;

        // Destroy existing chart if it exists
        if (this.charts.engagement) {
            this.charts.engagement.destroy();
        }

        const engagement = this.data.engagement;
        
        this.charts.engagement = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [
                    'Completed',
                    'In Progress',
                    'Not Started'
                ],
                datasets: [{
                    data: [
                        engagement.completed,
                        engagement.in_progress,
                        engagement.not_started
                    ],
                    backgroundColor: [
                        CONFIG.chartColors.success,
                        CONFIG.chartColors.warning,
                        CONFIG.chartColors.danger
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                return `${context.label}: ${context.parsed} (${percentage}%)`;
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 1000
                }
            }
        });
    }

    /**
     * Create trends charts
     */
    createTrendsCharts() {
        this.createViewsTrendChart();
        this.createCompletionsTrendChart();
    }

    /**
     * Create views trend chart
     */
    createViewsTrendChart() {
        const ctx = document.getElementById('viewsTrendChart');
        if (!ctx || !this.data.trends.views) return;

        // Destroy existing chart if it exists
        if (this.charts.viewsTrend) {
            this.charts.viewsTrend.destroy();
        }

        const viewsData = this.data.trends.views;
        const labels = viewsData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString(CONFIG.language, {
                month: 'short',
                year: 'numeric'
            });
        });
        const values = viewsData.map(item => parseInt(item.views) || 0);

        this.charts.viewsTrend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Views',
                    data: values,
                    borderColor: CONFIG.chartColors.primary,
                    backgroundColor: CONFIG.chartColors.primary + '20',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: CONFIG.chartColors.primary,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: CONFIG.chartColors.primary,
                        borderWidth: 1
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });
    }

    /**
     * Create completions trend chart
     */
    createCompletionsTrendChart() {
        const ctx = document.getElementById('completionsTrendChart');
        if (!ctx || !this.data.trends.completions) return;

        // Destroy existing chart if it exists
        if (this.charts.completionsTrend) {
            this.charts.completionsTrend.destroy();
        }

        const completionsData = this.data.trends.completions;
        const labels = completionsData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString(CONFIG.language, {
                month: 'short',
                year: 'numeric'
            });
        });
        const values = completionsData.map(item => parseInt(item.completions) || 0);

        this.charts.completionsTrend = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Completions',
                    data: values,
                    backgroundColor: CONFIG.chartColors.success,
                    borderRadius: 4,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: CONFIG.chartColors.success,
                        borderWidth: 1
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });
    }

    /**
     * Update lessons table
     */
    updateLessonsTable() {
        const tableBody = document.getElementById('lessonsAnalyticsTableBody');
        if (!tableBody || !this.data.lessons) return;

        tableBody.innerHTML = '';
        
        if (this.data.lessons.length === 0) {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td colspan="7" class="text-center text-muted">
                    <i class="fas fa-chart-line"></i>
                    No analytics data available
                </td>
            `;
            tableBody.appendChild(row);
            return;
        }

        this.data.lessons.forEach((lesson, index) => {
            const row = document.createElement('tr');
            row.style.animationDelay = `${index * 0.1}s`;
            row.classList.add('fade-in');
            
            row.innerHTML = `
                <td>
                    <div class="lesson-info">
                        <strong>${lesson.title}</strong>
                        <br><small class="text-muted">
                            <i class="fas fa-${Utils.getContentTypeIcon(lesson.content_type)}"></i>
                            ${lesson.content_type} • ${Utils.formatDuration(lesson.duration)}
                        </small>
                    </div>
                </td>
                <td>
                    <span class="metric-value">${Utils.formatNumber(lesson.views)}</span>
                </td>
                <td>
                    <span class="metric-value">${Utils.formatNumber(lesson.completions)}</span>
                </td>
                <td>
                    <span class="completion-rate ${this.getCompletionRateClass(lesson.completion_rate)}">
                        ${lesson.completion_rate}%
                    </span>
                </td>
                <td>
                    <div class="rating-display">
                        ${lesson.avg_rating} 
                        <i class="fas fa-star" style="color: #fbbf24;"></i>
                    </div>
                </td>
                <td>
                    <span class="time-display">${Utils.formatDuration(lesson.avg_time)}</span>
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" 
                            onclick="viewLessonDetails(${lesson.id}, '${lesson.title.replace(/'/g, "\\'")}')"
                            title="View detailed analytics for ${lesson.title}">
                        <i class="fas fa-eye"></i> View Details
                    </button>
                </td>
            `;
            tableBody.appendChild(row);
        });
    }

    /**
     * Get completion rate CSS class
     */
    getCompletionRateClass(rate) {
        if (rate >= 80) return 'high';
        if (rate >= 60) return 'medium';
        if (rate >= 40) return 'low';
        return 'very-low';
    }

    /**
     * Switch between analytics tabs
     */
    switchTab(tabName) {
        // Remove active class from all tabs and contents
        document.querySelectorAll('.analytics-tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.analytics-tab-content').forEach(content => content.classList.remove('active'));
        
        // Add active class to selected tab and content
        const activeTab = document.querySelector(`[data-tab="${tabName}"]`);
        const activeContent = document.getElementById(`${tabName}-tab`);
        
        if (activeTab) activeTab.classList.add('active');
        if (activeContent) activeContent.classList.add('active');

        // Load chart data for the active tab
        if (tabName === 'trends') {
            this.loadTrendsCharts();
        }
    }

    /**
     * Load trends charts when tab becomes active
     */
    loadTrendsCharts() {
        if (this.data && this.data.trends) {
            this.createTrendsCharts();
        }
    }

    /**
     * Resize charts on window resize
     */
    resizeCharts() {
        Object.values(this.charts).forEach(chart => {
            if (chart && typeof chart.resize === 'function') {
                chart.resize();
            }
        });
    }

    /**
     * Setup auto-refresh
     */
    setupAutoRefresh() {
        this.refreshInterval = setInterval(() => {
            this.loadAnalyticsData();
        }, CONFIG.refreshInterval);
    }

    /**
     * Pause auto-refresh
     */
    pauseAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }

    /**
     * Resume auto-refresh
     */
    resumeAutoRefresh() {
        if (!this.refreshInterval) {
            this.setupAutoRefresh();
        }
    }

    /**
     * Destroy analytics manager
     */
    destroy() {
        this.pauseAutoRefresh();
        Object.values(this.charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        this.charts = {};
    }
}

// ============================================================================
// LESSON MANAGER
// ============================================================================
class LessonManager {
    constructor() {
        this.notifications = new NotificationSystem();
        this.init();
    }

    init() {
        this.setupEventListeners();
    }

    setupEventListeners() {
        // Lesson card interactions
        document.querySelectorAll('.lesson-card').forEach(card => {
            card.addEventListener('mouseenter', this.handleCardHover.bind(this));
            card.addEventListener('mouseleave', this.handleCardLeave.bind(this));
        });

        // Lesson action buttons
        document.querySelectorAll('.lesson-action-btn').forEach(btn => {
            btn.addEventListener('click', this.handleActionClick.bind(this));
        });
    }

    handleCardHover(event) {
        const card = event.currentTarget;
        card.style.transform = 'translateY(-4px)';
        card.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.15)';
    }

    handleCardLeave(event) {
        const card = event.currentTarget;
        card.style.transform = 'translateY(0)';
        card.style.boxShadow = '0 4px 6px -1px rgb(0 0 0 / 0.1)';
    }

    handleActionClick(event) {
        const btn = event.currentTarget;
        const action = btn.classList.contains('delete-btn') ? 'delete' : 
                      btn.classList.contains('edit-btn') ? 'edit' : 'view';
        
        // Add loading state
        const originalContent = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;

        // Simulate action (in real implementation, this would be an API call)
        setTimeout(() => {
            btn.innerHTML = originalContent;
            btn.disabled = false;
            
            this.notifications.success(`Lesson ${action} action completed`, 2000);
        }, 1000);
    }
}

// ============================================================================
// RESPONSIVE MANAGER
// ============================================================================
class ResponsiveManager {
    constructor() {
        this.currentBreakpoint = this.getCurrentBreakpoint();
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.handleResize();
    }

    setupEventListeners() {
        window.addEventListener('resize', Utils.throttle(() => {
            this.handleResize();
        }, 250));
    }

    getCurrentBreakpoint() {
        const width = window.innerWidth;
        if (width < CONFIG.breakpoints.mobile) return 'mobile';
        if (width < CONFIG.breakpoints.tablet) return 'tablet';
        if (width < CONFIG.breakpoints.desktop) return 'desktop';
        return 'large';
    }

    handleResize() {
        const newBreakpoint = this.getCurrentBreakpoint();
        
        if (newBreakpoint !== this.currentBreakpoint) {
            this.currentBreakpoint = newBreakpoint;
            this.onBreakpointChange(newBreakpoint);
        }
    }

    onBreakpointChange(breakpoint) {
        // Update analytics charts
        if (window.analyticsManager) {
            window.analyticsManager.resizeCharts();
        }

        // Update layout-specific elements
        this.updateLayoutForBreakpoint(breakpoint);
    }

    updateLayoutForBreakpoint(breakpoint) {
        const analyticsGrid = document.querySelector('.analytics-grid');
        const trendsContainer = document.querySelector('.trends-container');
        const lessonsGrid = document.querySelector('.lessons-grid');

        if (breakpoint === 'mobile') {
            if (analyticsGrid) analyticsGrid.style.gridTemplateColumns = '1fr';
            if (trendsContainer) trendsContainer.style.gridTemplateColumns = '1fr';
            if (lessonsGrid) lessonsGrid.style.gridTemplateColumns = '1fr';
        } else if (breakpoint === 'tablet') {
            if (analyticsGrid) analyticsGrid.style.gridTemplateColumns = '1fr';
            if (trendsContainer) trendsContainer.style.gridTemplateColumns = '1fr';
            if (lessonsGrid) lessonsGrid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(300px, 1fr))';
        } else {
            if (analyticsGrid) analyticsGrid.style.gridTemplateColumns = '2fr 1fr';
            if (trendsContainer) trendsContainer.style.gridTemplateColumns = '1fr 1fr';
            if (lessonsGrid) lessonsGrid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(350px, 1fr))';
        }
    }
}

// ============================================================================
// GLOBAL FUNCTIONS
// ============================================================================

/**
 * View lesson details
 */
function viewLessonDetails(lessonId, lessonTitle) {
    // Show loading notification
    const notification = new NotificationSystem();
    notification.info(`Loading details for: ${lessonTitle}`, 2000);
    
    // Navigate to lesson edit page
    setTimeout(() => {
        window.location.href = `lesson_edit.php?id=${lessonId}`;
    }, 500);
}

/**
 * Refresh analytics data
 */
function refreshAnalytics() {
    if (window.analyticsManager) {
        window.analyticsManager.loadAnalyticsData();
    }
}

/**
 * Export analytics data
 */
function exportAnalytics() {
    if (!window.analyticsManager || !window.analyticsManager.data) {
        const notification = new NotificationSystem();
        notification.warning('No analytics data available to export');
        return;
    }

    const data = window.analyticsManager.data;
    const csvContent = generateCSV(data);
    
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `course-analytics-${CONFIG.courseId}-${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);

    const notification = new NotificationSystem();
    notification.success('Analytics data exported successfully');
}

/**
 * Generate CSV content from analytics data
 */
function generateCSV(data) {
    const headers = ['Metric', 'Value'];
    const rows = [
        ['Total Views', data.overview.total_views],
        ['Total Completions', data.overview.total_completions],
        ['Average Rating', data.overview.average_rating],
        ['Average Time', data.overview.average_time],
        ['Completion Rate', data.overview.completion_rate],
        ['Engagement Rate', data.overview.engagement_rate],
        ['Success Rate', data.overview.success_rate],
        ['Total Students', data.overview.total_students]
    ];

    const csvContent = [headers, ...rows]
        .map(row => row.map(cell => `"${cell}"`).join(','))
        .join('\n');

    return csvContent;
}

// ============================================================================
// INITIALIZATION
// ============================================================================

/**
 * Initialize the page when DOM is loaded
 */
document.addEventListener('DOMContentLoaded', () => {
    // Set configuration from PHP
    CONFIG.courseId = window.courseId || null;
    CONFIG.language = window.language || 'fr';

    // Initialize managers
    window.analyticsManager = new AnalyticsManager();
    window.lessonManager = new LessonManager();
    window.responsiveManager = new ResponsiveManager();

    // Add global event listeners
    document.addEventListener('keydown', (e) => {
        // Keyboard shortcuts
        if (e.ctrlKey || e.metaKey) {
            switch (e.key) {
                case 'r':
                    e.preventDefault();
                    refreshAnalytics();
                    break;
                case 'e':
                    e.preventDefault();
                    exportAnalytics();
                    break;
            }
        }
    });

    // Performance monitoring
    if ('performance' in window) {
        window.addEventListener('load', () => {
            const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
            console.log(`Page loaded in ${loadTime}ms`);
        });
    }

    console.log('Course lessons page initialized successfully');
});

// ============================================================================
// ERROR HANDLING
// ============================================================================

// Global error handler
window.addEventListener('error', (event) => {
    console.error('Global error:', event.error);
    
    const notification = new NotificationSystem();
    notification.error('An unexpected error occurred. Please refresh the page.');
});

// Unhandled promise rejection handler
window.addEventListener('unhandledrejection', (event) => {
    console.error('Unhandled promise rejection:', event.reason);
    
    const notification = new NotificationSystem();
    notification.error('A network error occurred. Please check your connection.');
});

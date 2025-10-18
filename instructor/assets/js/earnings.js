/**
 * Professional LMS Earnings Dashboard JavaScript
 * Interactive functionality for earnings management
 */

class EarningsDashboard {
    constructor() {
        this.charts = {};
        this.autoRefreshInterval = null;
        this.isAutoRefreshEnabled = false;
        this.init();
    }

    init() {
        this.initializeCharts();
        this.setupEventListeners();
        this.startAutoRefresh();
        this.setupAnimations();
    }

    initializeCharts() {
        // Get data from PHP
        const earningsData = window.earningsData || [];
        const stats = window.stats || {};

        // Monthly Earnings Chart
        this.initializeEarningsChart(earningsData);
        
        // Revenue Distribution Chart
        this.initializeRevenueChart(stats);
        
        // Performance Metrics Chart (if needed)
        this.initializePerformanceChart();
    }

    initializeEarningsChart(data) {
        const ctx = document.getElementById('earningsChart');
        if (!ctx) return;

        this.charts.earnings = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: data.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Monthly Earnings',
                    data: data.map(item => parseFloat(item.monthly_revenue) || 0),
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: 'white',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    pointHoverBackgroundColor: '#1d4ed8',
                    pointHoverBorderColor: 'white',
                    pointHoverBorderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.9)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: '#2563eb',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: false,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        },
                        callbacks: {
                            title: function(context) {
                                return context[0].label;
                            },
                            label: function(context) {
                                const value = context.parsed.y;
                                return `Earnings: ${value.toLocaleString()} ${window.currency || 'USD'}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#64748b',
                            font: {
                                size: 12
                            },
                            callback: function(value) {
                                return value.toLocaleString() + ' ' + (window.currency || 'USD');
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#64748b',
                            font: {
                                size: 12
                            }
                        }
                    }
                },
                elements: {
                    point: {
                        hoverRadius: 8
                    }
                }
            }
        });
    }

    initializeRevenueChart(stats) {
        const ctx = document.getElementById('revenueChart');
        if (!ctx) return;

        const instructorRevenue = parseFloat(stats.total_instructor_revenue) || 0;
        const platformCommission = parseFloat(stats.total_platform_commission) || 0;
        const total = instructorRevenue + platformCommission;

        this.charts.revenue = new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Instructor Revenue', 'Platform Commission'],
                datasets: [{
                    data: [instructorRevenue, platformCommission],
                    backgroundColor: [
                        '#10b981',
                        '#f59e0b'
                    ],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 13,
                                weight: '500'
                            },
                            color: '#374151'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.9)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: '#2563eb',
                        borderWidth: 1,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed;
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${context.label}: ${value.toLocaleString()} ${window.currency || 'USD'} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    initializePerformanceChart() {
        // Additional performance metrics chart can be added here
        // For example: conversion rates, student engagement, etc.
    }

    setupEventListeners() {
        // Chart period controls
        document.querySelectorAll('.chart-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.handlePeriodChange(e.target);
            });
        });

        // Auto-refresh toggle
        const autoRefreshBtn = document.querySelector('.auto-refresh-btn');
        if (autoRefreshBtn) {
            autoRefreshBtn.addEventListener('click', () => {
                this.toggleAutoRefresh();
            });
        }

        // Manual refresh
        const refreshBtn = document.querySelector('.refresh-btn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.refreshData();
            });
        }

        // Export functionality
        const exportBtn = document.querySelector('.export-btn');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                this.exportData();
            });
        }

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', (e) => {
                e.preventDefault();
                const target = document.querySelector(anchor.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Loading states for links and buttons
        document.querySelectorAll('a, button').forEach(element => {
            element.addEventListener('click', (e) => {
                if (element.href && !element.href.includes('#')) {
                    this.showLoadingState(element);
                }
            });
        });
    }

    handlePeriodChange(button) {
        // Remove active class from all buttons
        document.querySelectorAll('.chart-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        // Add active class to clicked button
        button.classList.add('active');

        // Show loading state
        const chartContainer = button.closest('.chart-card').querySelector('.chart-container');
        chartContainer.classList.add('loading');

        // Simulate data fetching (replace with actual API call)
        setTimeout(() => {
            chartContainer.classList.remove('loading');
            this.updateChartData(button.dataset.period);
        }, 1000);
    }

    updateChartData(period) {
        // This would typically fetch new data based on the selected period
        // For now, we'll just show a notification
        this.showNotification(`Chart updated for ${period} period`, 'success');
    }

    startAutoRefresh() {
        if (this.isAutoRefreshEnabled) {
            this.autoRefreshInterval = setInterval(() => {
                this.refreshData(true);
            }, 30000); // Refresh every 30 seconds
        }
    }

    stopAutoRefresh() {
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
            this.autoRefreshInterval = null;
        }
    }

    toggleAutoRefresh() {
        const btn = document.querySelector('.auto-refresh-btn');
        if (!btn) return;

        this.isAutoRefreshEnabled = !this.isAutoRefreshEnabled;
        
        if (this.isAutoRefreshEnabled) {
            btn.classList.add('active');
            this.startAutoRefresh();
            this.showNotification('Auto-refresh enabled', 'success');
        } else {
            btn.classList.remove('active');
            this.stopAutoRefresh();
            this.showNotification('Auto-refresh disabled', 'info');
        }
    }

    refreshData(silent = false) {
        if (!silent) {
            const refreshBtn = document.querySelector('.refresh-btn');
            if (refreshBtn) {
                refreshBtn.classList.add('spinning');
            }
        }

        // Simulate API call
        setTimeout(() => {
            if (!silent) {
                const refreshBtn = document.querySelector('.refresh-btn');
                if (refreshBtn) {
                    refreshBtn.classList.remove('spinning');
                }
                this.showNotification('Data refreshed successfully', 'success');
            }
            
            // In a real application, you would:
            // 1. Fetch new data from the server
            // 2. Update the charts with new data
            // 3. Update the statistics cards
            // 4. Update the recent sales table
            
        }, 1500);
    }

    exportData() {
        // Create CSV data
        const csvData = this.generateCSVData();
        
        // Create and download file
        const blob = new Blob([csvData], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `earnings-${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        this.showNotification('Data exported successfully', 'success');
    }

    generateCSVData() {
        // This would generate actual CSV data from the current data
        const headers = ['Date', 'Course/Product', 'Buyer', 'Amount', 'Status'];
        const rows = window.recentSales || [];
        
        let csv = headers.join(',') + '\n';
        rows.forEach(row => {
            csv += [
                row.created_at,
                row.course_title || row.product_name,
                row.buyer_name,
                row.instructor_revenue,
                row.status
            ].join(',') + '\n';
        });
        
        return csv;
    }

    showLoadingState(element) {
        element.style.opacity = '0.7';
        element.style.pointerEvents = 'none';
        
        // Reset after a delay
        setTimeout(() => {
            element.style.opacity = '1';
            element.style.pointerEvents = 'auto';
        }, 2000);
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${this.getNotificationIcon(type)}"></i>
                <span>${message}</span>
            </div>
        `;

        // Add styles
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${this.getNotificationColor(type)};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            animation: slideInRight 0.3s ease-out;
        `;

        // Add to page
        document.body.appendChild(notification);

        // Remove after 3 seconds
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease-in';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
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

    getNotificationColor(type) {
        const colors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#2563eb'
        };
        return colors[type] || '#2563eb';
    }

    setupAnimations() {
        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
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
            
            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
            
            .notification-content {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            
            .spinning i {
                animation: spin 1s linear infinite;
            }
        `;
        document.head.appendChild(style);

        // Intersection Observer for scroll animations
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                }
            });
        }, {
            threshold: 0.1
        });

        // Observe elements for animation
        document.querySelectorAll('.stat-card, .chart-card, .recent-sales-card').forEach(el => {
            observer.observe(el);
        });
    }

    // Public methods for external use
    updateChart(chartName, newData) {
        if (this.charts[chartName]) {
            this.charts[chartName].data = newData;
            this.charts[chartName].update();
        }
    }

    destroy() {
        this.stopAutoRefresh();
        Object.values(this.charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
    }
}

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.earningsDashboard = new EarningsDashboard();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.earningsDashboard) {
        window.earningsDashboard.destroy();
    }
});

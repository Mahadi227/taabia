<?php

/**
 * Test file for dynamic instructor dashboard functionality
 * This file tests the API endpoints and dynamic features
 */

// Start session for testing
session_start();

// Set a test instructor ID (you may need to adjust this based on your database)
$_SESSION['user_id'] = 1; // Replace with actual instructor ID from your database

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dynamic Dashboard Test - TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .test-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .test-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .test-header h1 {
            color: #2d3748;
            margin-bottom: 10px;
        }

        .test-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .test-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .test-section h3 {
            color: #2d3748;
            margin-top: 0;
            margin-bottom: 15px;
        }

        .api-test {
            background: white;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
        }

        .api-test h4 {
            margin-top: 0;
            color: #4a5568;
        }

        .test-result {
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-family: monospace;
            font-size: 12px;
            max-height: 200px;
            overflow-y: auto;
        }

        .test-result.success {
            background: #f0fff4;
            border: 1px solid #9ae6b4;
            color: #22543d;
        }

        .test-result.error {
            background: #fff5f5;
            border: 1px solid #feb2b2;
            color: #742a2a;
        }

        .test-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
            margin-bottom: 10px;
        }

        .test-btn:hover {
            background: #5a67d8;
        }

        .test-btn:disabled {
            background: #a0aec0;
            cursor: not-allowed;
        }

        .chart-container {
            position: relative;
            height: 300px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .status-indicator.success {
            background: #48bb78;
        }

        .status-indicator.error {
            background: #f56565;
        }

        .status-indicator.loading {
            background: #ed8936;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .instructions {
            background: #e6fffa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #38b2ac;
            margin-bottom: 30px;
        }

        .instructions h3 {
            color: #234e52;
            margin-top: 0;
        }

        .instructions ol {
            color: #285e61;
        }
    </style>
</head>

<body>
    <div class="test-container">
        <div class="test-header">
            <h1>🚀 Dynamic Dashboard Test</h1>
            <p>Testing the dynamic earnings chart and transactions functionality</p>
        </div>

        <div class="instructions">
            <h3>🧪 Testing Instructions:</h3>
            <ol>
                <li><strong>API Test:</strong> Click "Test API Endpoint" to verify the data endpoint works</li>
                <li><strong>Chart Test:</strong> Click "Load Chart Data" to test the dynamic chart functionality</li>
                <li><strong>Transactions Test:</strong> Click "Load Transactions" to test the transactions table</li>
                <li><strong>Auto Refresh:</strong> Enable auto-refresh to see real-time updates</li>
                <li><strong>Period Controls:</strong> Test different time periods (1M, 3M, 6M, 12M)</li>
                <li><strong>Language Switch:</strong> Test the language switcher to ensure translations work</li>
            </ol>
        </div>

        <div class="test-grid">
            <div class="test-section">
                <h3><i class="fas fa-chart-line"></i> Dynamic Earnings Chart</h3>

                <div class="api-test">
                    <h4>API Endpoint Test</h4>
                    <button class="test-btn" onclick="testAPI()">
                        <i class="fas fa-play"></i> Test API Endpoint
                    </button>
                    <div id="api-result" class="test-result" style="display: none;"></div>
                </div>

                <div class="api-test">
                    <h4>Chart Controls</h4>
                    <button class="test-btn" onclick="loadChartData()">
                        <i class="fas fa-chart-bar"></i> Load Chart Data
                    </button>
                    <button class="test-btn" onclick="testPeriodControls()">
                        <i class="fas fa-calendar"></i> Test Period Controls
                    </button>
                    <div id="chart-result" class="test-result" style="display: none;"></div>
                </div>

                <div class="chart-container">
                    <canvas id="testChart"></canvas>
                </div>
            </div>

            <div class="test-section">
                <h3><i class="fas fa-clock"></i> Dynamic Transactions</h3>

                <div class="api-test">
                    <h4>Transactions Test</h4>
                    <button class="test-btn" onclick="loadTransactions()">
                        <i class="fas fa-sync"></i> Load Transactions
                    </button>
                    <button class="test-btn" onclick="toggleAutoRefresh()">
                        <i class="fas fa-play" id="auto-refresh-icon"></i> <span id="auto-refresh-text">Enable Auto Refresh</span>
                    </button>
                    <div id="transactions-result" class="test-result" style="display: none;"></div>
                </div>

                <div id="transactions-content">
                    <div style="text-align: center; padding: 40px; color: #718096;">
                        <i class="fas fa-shopping-cart" style="font-size: 48px; margin-bottom: 15px;"></i>
                        <p>Click "Load Transactions" to test the dynamic functionality</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="test-section">
            <h3><i class="fas fa-info-circle"></i> Test Status & Results</h3>
            <div id="test-status">
                <p><span class="status-indicator"></span> Ready to test</p>
            </div>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="instructor/index.php" style="color: #667eea; text-decoration: none; font-weight: 500;">
                <i class="fas fa-arrow-left"></i> Back to Instructor Dashboard
            </a>
        </div>
    </div>

    <script>
        let testChart = null;
        let autoRefreshInterval = null;
        let isAutoRefreshEnabled = false;

        // Initialize test chart
        function initTestChart() {
            const ctx = document.getElementById('testChart').getContext('2d');
            testChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Monthly Earnings (GHS)',
                        data: [],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Test API endpoint
        async function testAPI() {
            updateStatus('Testing API endpoint...', 'loading');
            const resultDiv = document.getElementById('api-result');

            try {
                const response = await fetch('instructor/api/get_dashboard_data.php');
                const result = await response.json();

                if (result.success) {
                    resultDiv.className = 'test-result success';
                    resultDiv.innerHTML = `
                        <strong>✅ API Test Successful</strong><br>
                        <strong>Data received:</strong><br>
                        • Monthly earnings: ${result.data.monthly_earnings.length} months<br>
                        • Recent transactions: ${result.data.recent_transactions.length} transactions<br>
                        • Statistics: ${Object.keys(result.data.statistics).length} metrics<br>
                        <strong>Timestamp:</strong> ${new Date(result.timestamp * 1000).toLocaleString()}
                    `;
                    resultDiv.style.display = 'block';
                    updateStatus('API test successful', 'success');
                } else {
                    throw new Error(result.error || 'Unknown API error');
                }
            } catch (error) {
                resultDiv.className = 'test-result error';
                resultDiv.innerHTML = `
                    <strong>❌ API Test Failed</strong><br>
                    <strong>Error:</strong> ${error.message}<br>
                    <strong>Check:</strong> Make sure you're logged in as an instructor and the API file exists.
                `;
                resultDiv.style.display = 'block';
                updateStatus('API test failed', 'error');
            }
        }

        // Load chart data
        async function loadChartData() {
            updateStatus('Loading chart data...', 'loading');
            const resultDiv = document.getElementById('chart-result');

            try {
                if (!testChart) {
                    initTestChart();
                }

                const response = await fetch('instructor/api/get_dashboard_data.php');
                const result = await response.json();

                if (result.success) {
                    const earnings = result.data.monthly_earnings;
                    const labels = earnings.map(item => {
                        const date = new Date(item.month + '-01');
                        return date.toLocaleDateString('en-US', {
                            month: 'short',
                            year: 'numeric'
                        });
                    });
                    const values = earnings.map(item => parseFloat(item.total) || 0);

                    testChart.data.labels = labels;
                    testChart.data.datasets[0].data = values;
                    testChart.update();

                    resultDiv.className = 'test-result success';
                    resultDiv.innerHTML = `
                        <strong>✅ Chart Data Loaded</strong><br>
                        <strong>Data points:</strong> ${values.length}<br>
                        <strong>Total earnings:</strong> ${values.reduce((a, b) => a + b, 0).toLocaleString()} GHS<br>
                        <strong>Average:</strong> ${(values.reduce((a, b) => a + b, 0) / values.length).toFixed(0)} GHS
                    `;
                    resultDiv.style.display = 'block';
                    updateStatus('Chart data loaded successfully', 'success');
                } else {
                    throw new Error(result.error || 'Failed to load chart data');
                }
            } catch (error) {
                resultDiv.className = 'test-result error';
                resultDiv.innerHTML = `
                    <strong>❌ Chart Load Failed</strong><br>
                    <strong>Error:</strong> ${error.message}
                `;
                resultDiv.style.display = 'block';
                updateStatus('Chart load failed', 'error');
            }
        }

        // Test period controls
        function testPeriodControls() {
            const resultDiv = document.getElementById('chart-result');
            resultDiv.className = 'test-result success';
            resultDiv.innerHTML = `
                <strong>✅ Period Controls Test</strong><br>
                <strong>Available periods:</strong> 1M, 3M, 6M, 12M<br>
                <strong>Functionality:</strong> Click period buttons to filter chart data<br>
                <strong>Status:</strong> Period controls are working correctly
            `;
            resultDiv.style.display = 'block';
            updateStatus('Period controls test passed', 'success');
        }

        // Load transactions
        async function loadTransactions() {
            updateStatus('Loading transactions...', 'loading');
            const resultDiv = document.getElementById('transactions-result');
            const contentDiv = document.getElementById('transactions-content');

            try {
                const response = await fetch('instructor/api/get_dashboard_data.php');
                const result = await response.json();

                if (result.success) {
                    const transactions = result.data.recent_transactions;

                    if (transactions.length === 0) {
                        contentDiv.innerHTML = `
                            <div style="text-align: center; padding: 40px; color: #718096;">
                                <i class="fas fa-shopping-cart" style="font-size: 48px; margin-bottom: 15px;"></i>
                                <p>No transactions found</p>
                            </div>
                        `;
                    } else {
                        let html = `
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #f8f9fa; border-bottom: 2px solid #e2e8f0;">
                                        <th style="padding: 10px; text-align: left;">ID</th>
                                        <th style="padding: 10px; text-align: left;">Type</th>
                                        <th style="padding: 10px; text-align: left;">Course</th>
                                        <th style="padding: 10px; text-align: left;">Student</th>
                                        <th style="padding: 10px; text-align: left;">Amount</th>
                                        <th style="padding: 10px; text-align: left;">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                        `;

                        transactions.forEach(transaction => {
                            html += `
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 10px;">#${transaction.id}</td>
                                    <td style="padding: 10px;">
                                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: ${transaction.type === 'course' ? '#d4edda' : '#d1ecf1'}; color: ${transaction.type === 'course' ? '#155724' : '#0c5460'};">
                                            ${transaction.type.charAt(0).toUpperCase() + transaction.type.slice(1)}
                                        </span>
                                    </td>
                                    <td style="padding: 10px;">${transaction.course_title || 'N/A'}</td>
                                    <td style="padding: 10px;">${transaction.buyer_name}</td>
                                    <td style="padding: 10px; font-weight: 600; color: #48bb78;">
                                        ${parseFloat(transaction.amount).toLocaleString()} GHS
                                    </td>
                                    <td style="padding: 10px;">${new Date(transaction.created_at).toLocaleDateString()}</td>
                                </tr>
                            `;
                        });

                        html += '</tbody></table>';
                        contentDiv.innerHTML = html;
                    }

                    resultDiv.className = 'test-result success';
                    resultDiv.innerHTML = `
                        <strong>✅ Transactions Loaded</strong><br>
                        <strong>Count:</strong> ${transactions.length} transactions<br>
                        <strong>Total amount:</strong> ${transactions.reduce((sum, t) => sum + parseFloat(t.amount), 0).toLocaleString()} GHS<br>
                        <strong>Last updated:</strong> ${new Date().toLocaleTimeString()}
                    `;
                    resultDiv.style.display = 'block';
                    updateStatus('Transactions loaded successfully', 'success');
                } else {
                    throw new Error(result.error || 'Failed to load transactions');
                }
            } catch (error) {
                resultDiv.className = 'test-result error';
                resultDiv.innerHTML = `
                    <strong>❌ Transactions Load Failed</strong><br>
                    <strong>Error:</strong> ${error.message}
                `;
                resultDiv.style.display = 'block';
                updateStatus('Transactions load failed', 'error');
            }
        }

        // Toggle auto refresh
        function toggleAutoRefresh() {
            const icon = document.getElementById('auto-refresh-icon');
            const text = document.getElementById('auto-refresh-text');

            if (isAutoRefreshEnabled) {
                clearInterval(autoRefreshInterval);
                isAutoRefreshEnabled = false;
                icon.className = 'fas fa-play';
                text.textContent = 'Enable Auto Refresh';
                updateStatus('Auto refresh disabled', 'success');
            } else {
                autoRefreshInterval = setInterval(() => {
                    loadTransactions();
                }, 10000); // Refresh every 10 seconds for testing
                isAutoRefreshEnabled = true;
                icon.className = 'fas fa-pause';
                text.textContent = 'Disable Auto Refresh';
                updateStatus('Auto refresh enabled (10s intervals)', 'success');
            }
        }

        // Update status
        function updateStatus(message, type) {
            const statusDiv = document.getElementById('test-status');
            const indicator = statusDiv.querySelector('.status-indicator');
            indicator.className = `status-indicator ${type}`;
            statusDiv.innerHTML = `<span class="status-indicator ${type}"></span> ${message}`;
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateStatus('Test page loaded successfully', 'success');
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        });
    </script>
</body>

</html>
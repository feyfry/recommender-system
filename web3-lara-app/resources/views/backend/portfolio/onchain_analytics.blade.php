@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
            <div>
                <h1 class="text-3xl font-bold mb-2 flex items-center">
                    <div class="bg-success/20 p-2 clay-badge mr-3">
                        <i class="fas fa-chart-line text-success"></i>
                    </div>
                    Onchain Analytics
                </h1>
                <p class="text-gray-600">Analisis mendalam aktivitas onchain wallet Anda</p>
                <div class="text-sm text-gray-500 mt-1">
                    Wallet: <span class="font-mono">{{ Str::limit($walletAddress, 20) }}</span>
                    <button onclick="copyToClipboard('{{ $walletAddress }}')" class="ml-2 text-primary hover:text-primary-dark">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
            <div class="mt-4 md:mt-0 flex space-x-3">
                <a href="{{ route('panel.portfolio') }}" class="clay-button clay-button-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Portfolio
                </a>
                <button type="button" onclick="refreshAnalytics()" class="clay-button clay-button-success" id="refresh-analytics-btn">
                    <i class="fas fa-sync-alt mr-2"></i> Refresh Data
                </button>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="clay-alert clay-alert-success mb-8">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            {{ session('success') }}
        </div>
    </div>
    @endif

    <!-- ⚡ ENHANCED: Loading State with Progress Indicator - Show by default -->
    <div id="analytics-loading" class="mb-8">
        <div class="clay-card p-6">
            <div class="animate-pulse">
                <div class="flex items-center justify-center py-12">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-4 border-success mr-4"></div>
                    <div>
                        <div class="text-lg font-medium mb-2">Loading onchain analytics...</div>
                        <div class="text-sm text-gray-500">Menganalisis transaksi dan volume trading</div>
                        <div class="text-xs text-gray-400 mt-1">Proses: Fetching transactions → Calculating volumes → Building charts</div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-8">
                    <div class="clay-card bg-gray-100 p-6 h-28 rounded-lg"></div>
                    <div class="clay-card bg-gray-100 p-6 h-28 rounded-lg"></div>
                    <div class="clay-card bg-gray-100 p-6 h-28 rounded-lg"></div>
                    <div class="clay-card bg-gray-100 p-6 h-28 rounded-lg"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Overview - Hidden initially -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8" id="analytics-overview" style="display: none;">
        <div class="clay-card bg-primary/10 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-gray-600 text-sm">Total Transactions</div>
                    <div class="text-3xl font-bold" id="total-transactions">Loading...</div>
                    <div class="text-xs text-gray-500 mt-1">Onchain activity</div>
                </div>
                <div class="bg-primary/20 p-3 rounded-lg">
                    <i class="fas fa-exchange-alt text-primary text-xl"></i>
                </div>
            </div>
        </div>

        <div class="clay-card bg-secondary/10 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-gray-600 text-sm">Unique Tokens</div>
                    <div class="text-3xl font-bold" id="unique-tokens">Loading...</div>
                    <div class="text-xs text-gray-500 mt-1">Tokens traded</div>
                </div>
                <div class="bg-secondary/20 p-3 rounded-lg">
                    <i class="fas fa-coins text-secondary text-xl"></i>
                </div>
            </div>
        </div>

        <div class="clay-card bg-success/10 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-gray-600 text-sm">Total Volume (Est.)</div>
                    <div class="text-3xl font-bold" id="total-volume">Loading...</div>
                    <div class="text-xs text-gray-500 mt-1">USD equivalent</div>
                </div>
                <div class="bg-success/20 p-3 rounded-lg">
                    <i class="fas fa-chart-bar text-success text-xl"></i>
                </div>
            </div>
        </div>

        <div class="clay-card bg-info/10 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-gray-600 text-sm">Active Chains</div>
                    <div class="text-3xl font-bold" id="active-chains">Loading...</div>
                    <div class="text-xs text-gray-500 mt-1">Blockchains used</div>
                </div>
                <div class="bg-info/20 p-3 rounded-lg">
                    <i class="fas fa-link text-info text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8" id="analytics-content" style="display: none;">
        <!-- Most Traded Tokens -->
        <div class="clay-card p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-trophy mr-2 text-warning"></i>
                Most Traded Tokens
            </h2>

            <div id="most-traded-tokens">
                <!-- ⚡ Data akan di-populate via JavaScript -->
            </div>
        </div>

        <!-- Chain Activity -->
        <div class="clay-card p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-network-wired mr-2 text-info"></i>
                Chain Activity
            </h2>

            <div id="chain-activity">
                <!-- ⚡ Data akan di-populate via JavaScript -->
            </div>
        </div>
    </div>

    <!-- ⚡ ENHANCED: Transaction Frequency Chart dengan fallback - Hidden initially -->
    <div class="clay-card p-6 mb-8" id="chart-container-wrapper" style="display: none;">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-chart-area mr-2 text-primary"></i>
            Transaction Frequency (Last 30 Days)
        </h2>

        <div id="chart-container">
            <div class="relative">
                <div class="h-64" id="chart-wrapper">
                    <canvas id="transactionChart"></canvas>
                </div>
                <!-- ⚡ FALLBACK: Simple bar chart jika Chart.js gagal -->
                <div id="chart-fallback" style="display: none;" class="space-y-2">
                    <!-- Fallback content will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions - Hidden initially -->
    <div class="clay-card p-6" id="recent-transactions-wrapper" style="display: none;">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-history mr-2 text-secondary"></i>
            Recent Onchain Transactions
        </h2>

        <div id="recent-transactions">
            <!-- ⚡ Data akan di-populate via JavaScript -->
        </div>
    </div>

    <!-- Analytics Insights - Hidden initially -->
    <div class="clay-card p-6 mt-8" id="analytics-insights" style="display: none;">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-lightbulb mr-2 text-warning"></i>
            Analytics Insights
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="clay-card bg-success/10 p-4">
                <h3 class="font-bold mb-2">💡 Trading Activity</h3>
                <p class="text-sm mb-2" id="trading-activity-insight">
                    Analyzing your trading patterns...
                </p>
            </div>

            <div class="clay-card bg-info/10 p-4">
                <h3 class="font-bold mb-2">🌐 Diversification</h3>
                <p class="text-sm mb-2" id="diversification-insight">
                    Analyzing your portfolio diversification...
                </p>
            </div>

            <div class="clay-card bg-warning/10 p-4">
                <h3 class="font-bold mb-2">⛓️ Multi-Chain</h3>
                <p class="text-sm mb-2" id="multichain-insight">
                    Analyzing your multi-chain activity...
                </p>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<script>
    let transactionChart = null;
    let chartInitialized = false;
    let analyticsData = null;

    // ⚡ LAZY LOADING: Load analytics data saat halaman ready
    document.addEventListener('DOMContentLoaded', function() {
        loadAnalyticsData();
    });

    // ⚡ NEW: Function untuk lazy load analytics data
    async function loadAnalyticsData() {
        const loadingDiv = document.getElementById('analytics-loading');
        const overviewDiv = document.getElementById('analytics-overview');
        const contentDiv = document.getElementById('analytics-content');

        try {
            const response = await fetch('{{ route('panel.portfolio.load-analytics') }}', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            const data = await response.json();

            if (data.success) {
                analyticsData = data;

                // Update analytics overview
                updateAnalyticsOverview(data.analytics);

                // Update content sections
                updateMostTradedTokens(data.analytics.most_traded_tokens || []);
                updateChainActivity(data.analytics.chains_activity || {});
                updateTransactionChart(data.analytics.transaction_frequency || {});

                // Populate transactions table
                populateTransactionsTable(data.transactions || []);

                // Populate insights
                populateAnalyticsInsights(data.analytics);

                // Show content
                loadingDiv.style.display = 'none';
                overviewDiv.style.display = 'grid';
                contentDiv.style.display = 'block';
                document.getElementById('chart-container-wrapper').style.display = 'block';
                document.getElementById('recent-transactions-wrapper').style.display = 'block';
                document.getElementById('analytics-insights').style.display = 'block';

                // Show cache indicator if data came from cache
                if (data.cached) {
                    showNotification('Data loaded from cache (30 min)', 'info');
                }

            } else {
                throw new Error(data.message || 'Failed to load analytics data');
            }

        } catch (error) {
            console.error('Error loading analytics data:', error);

            // Show error state
            loadingDiv.innerHTML = `
                <div class="clay-card p-6">
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-triangle text-5xl text-yellow-500 mb-4"></i>
                        <h3 class="text-xl font-bold mb-3">Gagal Memuat Analytics Data</h3>
                        <p class="text-gray-600 mb-4">${error.message}</p>
                        <button onclick="loadAnalyticsData()" class="clay-button clay-button-primary">
                            <i class="fas fa-retry mr-2"></i> Coba Lagi
                        </button>
                    </div>
                </div>
            `;
        }
    }

    // Copy wallet address to clipboard
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            showNotification('Wallet address copied to clipboard!', 'success');
        });
    }

    // ⚡ NEW: Populate analytics insights
    function populateAnalyticsInsights(analytics) {
        const totalTx = analytics.total_transactions || 0;
        const uniqueTokens = analytics.unique_tokens_traded || 0;
        const chainsCount = Object.keys(analytics.chains_activity || {}).length;

        // Trading Activity Insight
        let tradingInsight = '';
        if (totalTx > 100) {
            tradingInsight = `Anda adalah trader yang sangat aktif dengan ${numberFormat(totalTx, 0)} transaksi.`;
        } else if (totalTx > 20) {
            tradingInsight = `Aktivitas trading Anda cukup baik dengan ${numberFormat(totalTx, 0)} transaksi.`;
        } else {
            tradingInsight = `Anda baru memulai atau jarang trading dengan ${numberFormat(totalTx, 0)} transaksi.`;
        }
        document.getElementById('trading-activity-insight').textContent = tradingInsight;

        // Diversification Insight
        let diversificationInsight = '';
        if (uniqueTokens > 20) {
            diversificationInsight = `Portfolio Anda sangat terdiversifikasi dengan ${uniqueTokens} token berbeda.`;
        } else if (uniqueTokens > 5) {
            diversificationInsight = `Diversifikasi yang baik dengan ${uniqueTokens} token berbeda.`;
        } else {
            diversificationInsight = `Pertimbangkan untuk diversifikasi lebih banyak token.`;
        }
        document.getElementById('diversification-insight').textContent = diversificationInsight;

        // Multi-Chain Insight
        let multichainInsight = '';
        if (chainsCount > 2) {
            multichainInsight = `Excellent! Anda aktif di ${chainsCount} blockchain berbeda.`;
        } else if (chainsCount > 1) {
            multichainInsight = `Good! Anda menggunakan ${chainsCount} blockchain.`;
        } else {
            multichainInsight = `Pertimbangkan untuk explore blockchain lain untuk biaya yang lebih rendah.`;
        }
        document.getElementById('multichain-insight').textContent = multichainInsight;
    }

    // ⚡ NEW: Populate transactions table
    function populateTransactionsTable(transactions) {
        const container = document.getElementById('recent-transactions');

        if (transactions.length > 0) {
            let html = `
                <div class="overflow-x-auto">
                    <table class="clay-table min-w-full">
                        <thead>
                            <tr>
                                <th class="py-2 px-4 text-left">Date</th>
                                <th class="py-2 px-4 text-left">Hash</th>
                                <th class="py-2 px-4 text-left">Type</th>
                                <th class="py-2 px-4 text-left">Value</th>
                                <th class="py-2 px-4 text-left">Gas</th>
                                <th class="py-2 px-4 text-left">Chain</th>
                                <th class="py-2 px-4 text-left">Status</th>
                                <th class="py-2 px-4 text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            transactions.slice(0, 20).forEach(tx => {
                const date = new Date(tx.timestamp).toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                const txHash = tx.tx_hash ? tx.tx_hash.substring(0, 16) + '...' : 'N/A';
                const typeClass = tx.transaction_type === 'native' ? 'primary' :
                                tx.transaction_type === 'token' ? 'secondary' : 'info';
                const statusClass = tx.status === 'success' ? 'success' : 'danger';

                html += `
                    <tr>
                        <td class="py-3 px-4 text-sm">${date}</td>
                        <td class="py-3 px-4">
                            <div class="font-mono text-sm">${txHash}</div>
                        </td>
                        <td class="py-3 px-4">
                            <span class="clay-badge clay-badge-${typeClass}">${tx.transaction_type || 'Unknown'}</span>
                        </td>
                        <td class="py-3 px-4">
                            <div class="font-medium">${numberFormat(tx.value || 0, 8)}</div>
                            ${tx.token_symbol ? `<div class="text-xs text-gray-500">${tx.token_symbol}</div>` : ''}
                        </td>
                        <td class="py-3 px-4 text-sm">${numberFormat(tx.gas_used || 0, 0)}</td>
                        <td class="py-3 px-4">
                            <span class="clay-badge clay-badge-info">${(tx.chain || '').toUpperCase()}</span>
                        </td>
                        <td class="py-3 px-4">
                            <span class="clay-badge clay-badge-${statusClass}">${tx.status || 'Unknown'}</span>
                        </td>
                        <td class="py-3 px-4">
                            <button onclick="viewTransaction('${tx.chain || ''}', '${tx.tx_hash || ''}')"
                                    class="clay-badge clay-badge-primary py-1 px-2 text-xs">
                                <i class="fas fa-external-link-alt"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                </div>
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-500">Showing last 20 transactions</p>
                </div>
            `;

            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div class="text-center py-12">
                    <i class="fas fa-history text-4xl text-gray-400 mb-3"></i>
                    <p class="text-gray-500">Tidak ada transaksi onchain ditemukan</p>
                    <p class="text-sm text-gray-400 mt-2">Pastikan wallet address sudah benar dan sudah melakukan transaksi</p>
                </div>
            `;
        }
    }
    async function refreshAnalytics() {
        const btn = document.getElementById('refresh-analytics-btn');
        const originalText = btn.innerHTML;
        const loadingDiv = document.getElementById('analytics-loading');
        const overviewDiv = document.getElementById('analytics-overview');

        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Refreshing...';
        btn.disabled = true;

        // Show loading state
        loadingDiv.style.display = 'block';
        overviewDiv.style.display = 'none';

        try {
            const response = await fetch('{{ route('panel.portfolio.refresh-onchain') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            const data = await response.json();

            if (data.success && data.analytics) {
                // Update analytics overview cards
                updateAnalyticsOverview(data.analytics);

                // Update most traded tokens
                updateMostTradedTokens(data.analytics.most_traded_tokens || []);

                // Update chain activity
                updateChainActivity(data.analytics.chains_activity || {});

                // Update transaction frequency chart
                updateTransactionChart(data.analytics.transaction_frequency || {});

                showNotification('Analytics data berhasil diperbarui!', 'success');
            } else {
                showNotification(data.message || 'Gagal memperbarui data', 'error');
            }

        } catch (error) {
            console.error('Error refreshing analytics:', error);
            showNotification('Error memperbarui analytics: ' + error.message, 'error');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;

            // Hide loading state
            loadingDiv.style.display = 'none';
            overviewDiv.style.display = 'grid';
        }
    }

    // ⚡ Update analytics overview cards
    function updateAnalyticsOverview(analytics) {
        document.getElementById('total-transactions').textContent = numberFormat(analytics.total_transactions || 0, 0);
        document.getElementById('unique-tokens').textContent = numberFormat(analytics.unique_tokens_traded || 0, 0);
        document.getElementById('total-volume').textContent = '$' + numberFormat(analytics.total_volume_usd || 0, 2);
        document.getElementById('active-chains').textContent = Object.keys(analytics.chains_activity || {}).length;
    }

    // ⚡ Update most traded tokens section
    function updateMostTradedTokens(tokens) {
        const container = document.getElementById('most-traded-tokens');

        if (tokens.length > 0) {
            let html = '<div class="space-y-4">';

            tokens.slice(0, 10).forEach((token, index) => {
                html += `
                    <div class="flex items-center justify-between p-3 clay-card bg-gray-50">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-warning/20 rounded-full flex items-center justify-center mr-3">
                                <span class="text-warning font-bold text-sm">${index + 1}</span>
                            </div>
                            <div>
                                <div class="font-medium">${token.symbol || 'Unknown'}</div>
                                <div class="text-xs text-gray-500">${token.trade_count || 0} transactions</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-medium">$${numberFormat(token.volume_usd || 0, 2)}</div>
                            <div class="text-xs text-gray-500">${numberFormat(token.volume || 0, 8)} ${token.symbol || ''}</div>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-coins text-4xl text-gray-400 mb-3"></i>
                    <p class="text-gray-500">Tidak ada data token trading</p>
                </div>
            `;
        }
    }

    // ⚡ Update chain activity section
    function updateChainActivity(chainsActivity) {
        const container = document.getElementById('chain-activity');
        const chains = Object.entries(chainsActivity);

        if (chains.length > 0) {
            const totalChainTxs = Object.values(chainsActivity).reduce((a, b) => a + b, 0);
            let html = '<div class="space-y-4">';

            chains.forEach(([chain, txCount]) => {
                const percentage = totalChainTxs > 0 ? (txCount / totalChainTxs) * 100 : 0;

                html += `
                    <div class="clay-card bg-info/5 p-3">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-medium">${chain.charAt(0).toUpperCase() + chain.slice(1)}</span>
                            <span class="text-sm">${numberFormat(txCount, 0)} txs</span>
                        </div>
                        <div class="clay-progress h-3">
                            <div class="clay-progress-bar clay-progress-info" style="width: ${Math.max(percentage, 1)}%"></div>
                        </div>
                        <div class="text-xs text-right mt-1">${percentage.toFixed(1)}%</div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-link text-4xl text-gray-400 mb-3"></i>
                    <p class="text-gray-500">Tidak ada data aktivitas chain</p>
                </div>
            `;
        }
    }

    // ⚡ ENHANCED: Update transaction chart dengan better fallback
    function updateTransactionChart(transactionFrequency) {
        const entries = Object.entries(transactionFrequency);
        if (entries.length === 0) {
            showChartFallback(transactionFrequency);
            return;
        }

        // Try to initialize Chart.js
        try {
            if (transactionChart) {
                transactionChart.destroy();
            }

            // Sort by date
            entries.sort((a, b) => new Date(a[0]) - new Date(b[0]));

            const labels = entries.map(([date]) => {
                const d = new Date(date);
                return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            });
            const data = entries.map(([, count]) => count);

            const ctx = document.getElementById('transactionChart');
            if (!ctx) {
                showChartFallback(transactionFrequency);
                return;
            }

            // Check if Chart.js is available
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js not loaded, showing fallback');
                showChartFallback(transactionFrequency);
                return;
            }

            transactionChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Transactions',
                        data: data,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.2,
                        pointBackgroundColor: 'rgb(59, 130, 246)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    return Math.floor(value) === value ? value : '';
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
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
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: 'rgb(59, 130, 246)',
                            borderWidth: 1
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });

            // Hide fallback and show chart
            const chartFallback = document.getElementById('chart-fallback');
            const chartWrapper = document.getElementById('chart-wrapper');
            if (chartFallback) chartFallback.style.display = 'none';
            if (chartWrapper) chartWrapper.style.display = 'block';
            chartInitialized = true;

        } catch (error) {
            console.error('Error initializing chart:', error);
            showChartFallback(transactionFrequency);
        }
    }

    // ⚡ Enhanced fallback chart dengan data
    function showChartFallback(transactionFrequency = {}) {
        const chartWrapper = document.getElementById('chart-wrapper');
        const chartFallback = document.getElementById('chart-fallback');

        if (chartWrapper) chartWrapper.style.display = 'none';
        if (chartFallback) {
            const entries = Object.entries(transactionFrequency);

            if (entries.length > 0) {
                // Sort by date
                entries.sort((a, b) => new Date(a[0]) - new Date(b[0]));

                const maxCount = Math.max(...entries.map(([, count]) => count));
                let html = '';

                entries.forEach(([date, count]) => {
                    const formattedDate = new Date(date).toLocaleDateString('en-US', {
                        month: 'short',
                        day: 'numeric'
                    });
                    const percentage = maxCount > 0 ? (count / maxCount) * 100 : 0;

                    html += `
                        <div class="flex items-center">
                            <div class="w-20 text-xs text-gray-600">${formattedDate}</div>
                            <div class="flex-1 mx-2">
                                <div class="clay-progress h-4">
                                    <div class="clay-progress-bar clay-progress-primary" style="width: ${percentage}%"></div>
                                </div>
                            </div>
                            <div class="w-8 text-xs text-gray-600 text-right">${count}</div>
                        </div>
                    `;
                });

                chartFallback.innerHTML = html;
            } else {
                chartFallback.innerHTML = `
                    <div class="text-center py-16">
                        <i class="fas fa-chart-line text-4xl text-gray-400 mb-3"></i>
                        <p class="text-gray-500">Tidak ada data frekuensi transaksi</p>
                        <p class="text-sm text-gray-400 mt-2">Grafik akan muncul setelah ada aktivitas trading</p>
                    </div>
                `;
            }

            chartFallback.style.display = 'block';
        }
    }

    // View transaction on blockchain explorer
    function viewTransaction(chain, txHash) {
        const explorers = {
            'eth': 'https://etherscan.io',
            'ethereum': 'https://etherscan.io',
            'bsc': 'https://bscscan.com',
            'binance_smart_chain': 'https://bscscan.com',
            'polygon': 'https://polygonscan.com',
            'avalanche': 'https://snowtrace.io'
        };

        const explorerUrl = explorers[chain.toLowerCase()];
        if (explorerUrl && txHash) {
            window.open(`${explorerUrl}/tx/${txHash}`, '_blank');
        } else {
            showNotification('Explorer not available for this chain', 'warning');
        }
    }

    // ⚡ ENHANCED: Number formatting dengan support untuk decimal yang berbeda
    function numberFormat(number, decimals = 2) {
        if (number === null || number === undefined || isNaN(number)) {
            return '0';
        }

        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    }

    // Simple notification system
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 clay-alert clay-alert-${type} max-w-sm`;
        notification.innerHTML = `
            <div class="flex items-center justify-between">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-3">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    // ⚡ ENHANCED: Initialize when page loads - no static data needed
    document.addEventListener('DOMContentLoaded', function() {
        loadAnalyticsData();
    });
</script>
@endpush
@endsection

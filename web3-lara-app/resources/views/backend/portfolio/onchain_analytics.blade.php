@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
            <div class="flex-1">
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
            <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 w-full md:w-auto mt-4 md:mt-0">
                <a href="{{ route('panel.portfolio') }}" class="clay-button clay-button-secondary w-full sm:w-auto text-sm">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Portfolio
                </a>
                <button type="button" onclick="refreshAnalytics()" class="clay-button clay-button-success w-full sm:w-auto text-sm" id="refresh-analytics-btn">
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
                        <div class="text-lg font-medium mb-2">⚡ Loading onchain analytics...</div>
                        <div class="text-sm text-gray-500">Menganalisis transaksi dan volume trading (5-10 detik)</div>
                        <div class="text-xs text-gray-400 mt-1">Proses: Fetching transactions → Filtering spam → Building charts</div>
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

    <!-- ⚡ FIXED: 2 Columns Layout - Most Traded Tokens + Chain Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8" id="analytics-content" style="display: none;">
        <!-- Most Traded Tokens (Left Column) -->
        <div class="clay-card p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-trophy mr-2 text-warning"></i>
                    Most Traded Tokens
                </div>
                <span class="text-sm text-gray-500" id="most-traded-count">Loading...</span>
            </h2>

            <div id="most-traded-tokens">
                <!-- ⚡ Data akan di-populate via JavaScript -->
            </div>

            <!-- ⚡ Pagination for Most Traded Tokens -->
            <div class="mt-4" id="most-traded-pagination" style="display: none;">
                <div class="flex justify-center items-center space-x-2">
                    <button onclick="changeMostTradedPage(-1)" class="clay-button clay-button-secondary py-1 px-2 text-xs" id="most-traded-prev">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <span class="text-sm text-gray-600" id="most-traded-page-info">Page 1</span>
                    <button onclick="changeMostTradedPage(1)" class="clay-button clay-button-secondary py-1 px-2 text-xs" id="most-traded-next">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Chain Activity (Right Column) -->
        <div class="clay-card p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-network-wired mr-2 text-info"></i>
                    Chain Activity
                </div>
                <span class="text-sm text-gray-500" id="chain-activity-count">Loading...</span>
            </h2>

            <div id="chain-activity">
                <!-- ⚡ Data akan di-populate via JavaScript -->
            </div>

            <!-- ⚡ Pagination for Chain Activity (if needed) -->
            <div class="mt-4" id="chain-activity-pagination" style="display: none;">
                <div class="flex justify-center items-center space-x-2">
                    <button onclick="changeChainActivityPage(-1)" class="clay-button clay-button-secondary py-1 px-2 text-xs" id="chain-activity-prev">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <span class="text-sm text-gray-600" id="chain-activity-page-info">Page 1</span>
                    <button onclick="changeChainActivityPage(1)" class="clay-button clay-button-secondary py-1 px-2 text-xs" id="chain-activity-next">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
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

    <!-- Recent Transactions with Pagination - Hidden initially -->
    <div class="clay-card p-6" id="recent-transactions-wrapper" style="display: none;">
        <h2 class="text-xl font-bold mb-4 flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-history mr-2 text-secondary"></i>
                Recent Onchain Transactions
            </div>
            <span class="text-sm text-gray-500" id="transactions-count">Loading...</span>
        </h2>

        <div id="recent-transactions">
            <!-- ⚡ Data akan di-populate via JavaScript -->
        </div>

        <!-- ⚡ Pagination for Recent Transactions -->
        <div class="mt-6" id="transactions-pagination" style="display: none;">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <!-- Info pages -->
                <div class="mb-4 md:mb-0">
                    <span class="text-sm text-gray-600" id="transactions-page-info">
                        Showing transactions...
                    </span>
                </div>

                <!-- Pagination buttons -->
                <div class="flex justify-center space-x-2">
                    <!-- Previous -->
                    <button onclick="changeTransactionsPage(-1)"
                           class="clay-button clay-button-secondary py-1.5 px-3 text-sm ml-4"
                           id="transactions-prev">
                        <i class="fas fa-chevron-left"></i>
                    </button>

                    <!-- Page Numbers -->
                    <div id="transactions-page-numbers" class="flex space-x-1">
                        <!-- Will be populated by JavaScript -->
                    </div>

                    <!-- Next -->
                    <button onclick="changeTransactionsPage(1)"
                           class="clay-button clay-button-secondary py-1.5 px-3 text-sm"
                           id="transactions-next">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
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
    let analyticsData = null;

    // ⚡ Pagination variables
    let mostTradedTokens = [];
    let chainActivityData = [];
    let transactionsData = [];

    let mostTradedPage = 1;
    let chainActivityPage = 1;
    let transactionsPage = 1;

    const ITEMS_PER_PAGE = {
        mostTraded: 5,
        chainActivity: 5,
        transactions: 10
    };

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

                // Store data for pagination
                mostTradedTokens = data.analytics.most_traded_tokens || [];
                chainActivityData = Object.entries(data.analytics.chains_activity || {});
                transactionsData = data.transactions || [];

                // Update content sections
                updateMostTradedTokensPaginated();
                updateChainActivityPaginated();
                updateTransactionChart(data.analytics.transaction_frequency || {});

                // Populate transactions table with pagination
                populateTransactionsTablePaginated();

                // Populate insights
                populateAnalyticsInsights(data.analytics);

                // Show content
                loadingDiv.style.display = 'none';
                overviewDiv.style.display = 'grid';
                contentDiv.style.display = 'grid';
                document.getElementById('chart-container-wrapper').style.display = 'block';
                document.getElementById('recent-transactions-wrapper').style.display = 'block';
                document.getElementById('analytics-insights').style.display = 'block';

                // Show cache indicator if data came from cache
                if (data.cached) {
                    showNotification('⚡ Data loaded from cache (fast!)', 'info');
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

    // ⚡ NEW: Paginated Most Traded Tokens
    function updateMostTradedTokensPaginated() {
        const container = document.getElementById('most-traded-tokens');
        const totalItems = mostTradedTokens.length;
        const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE.mostTraded);

        // Update count
        document.getElementById('most-traded-count').textContent = `${totalItems} tokens`;

        if (totalItems > 0) {
            const startIndex = (mostTradedPage - 1) * ITEMS_PER_PAGE.mostTraded;
            const endIndex = Math.min(startIndex + ITEMS_PER_PAGE.mostTraded, totalItems);
            const pageItems = mostTradedTokens.slice(startIndex, endIndex);

            let html = '<div class="space-y-3">';

            pageItems.forEach((token, index) => {
                const globalIndex = startIndex + index + 1;
                html += `
                    <div class="flex items-center justify-between p-3 clay-card bg-gray-50">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-warning/20 rounded-full flex items-center justify-center mr-3">
                                <span class="text-warning font-bold text-sm">${globalIndex}</span>
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

            // Show pagination if needed
            if (totalPages > 1) {
                const paginationDiv = document.getElementById('most-traded-pagination');
                paginationDiv.style.display = 'block';

                document.getElementById('most-traded-prev').disabled = mostTradedPage <= 1;
                document.getElementById('most-traded-next').disabled = mostTradedPage >= totalPages;
                document.getElementById('most-traded-prev').style.opacity = mostTradedPage <= 1 ? '0.5' : '1';
                document.getElementById('most-traded-next').style.opacity = mostTradedPage >= totalPages ? '0.5' : '1';
            }
        } else {
            container.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-coins text-4xl text-gray-400 mb-3"></i>
                    <p class="text-gray-500">Tidak ada data token trading</p>
                </div>
            `;
        }
    }

    // ⚡ FIXED: Update Chain Activity dengan progress bar yang robust
    function updateChainActivityPaginated() {
        const container = document.getElementById('chain-activity');
        const totalItems = chainActivityData.length;
        const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE.chainActivity);

        // Update count
        document.getElementById('chain-activity-count').textContent = `${totalItems} chains`;

        if (totalItems > 0) {
            const totalChainTxs = chainActivityData.reduce((sum, [, count]) => sum + count, 0);
            const startIndex = (chainActivityPage - 1) * ITEMS_PER_PAGE.chainActivity;
            const endIndex = Math.min(startIndex + ITEMS_PER_PAGE.chainActivity, totalItems);
            const pageItems = chainActivityData.slice(startIndex, endIndex);

            let html = '<div class="space-y-3">';

            pageItems.forEach(([chain, txCount]) => {
                const percentage = totalChainTxs > 0 ? (txCount / totalChainTxs) * 100 : 0;
                const chainName = chain.charAt(0).toUpperCase() + chain.slice(1);

                // ⚡ DEBUG: Console log untuk troubleshooting
                console.log(`Chain Activity - ${chainName}: ${txCount} txs (${percentage.toFixed(1)}%)`);

                html += `
                    <div class="clay-card bg-info/5 p-3">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-medium">${chainName}</span>
                            <span class="text-sm">${numberFormat(txCount, 0)} txs</span>
                        </div>
                        <!-- ⚡ GUARANTEED VISIBLE PROGRESS BAR - Same style as fixed portfolio -->
                        <div style="width: 100%; height: 14px; background-color: #e0f2fe; border: 1px solid #81d4fa; border-radius: 7px; overflow: hidden; margin-bottom: 8px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
                            <div style="height: 100%; background: linear-gradient(135deg, #0ea5e9, #0284c7); border-radius: 6px; width: ${percentage}%; transition: width 0.5s ease-in-out; min-width: ${percentage > 0 ? Math.max(percentage, 1.5) : 0}%; box-shadow: 0 1px 3px rgba(0,0,0,0.2);"></div>
                        </div>
                        <div class="text-xs text-right">${percentage.toFixed(1)}%</div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;

            // ⚡ SUCCESS LOG
            console.log(`✅ Chain Activity rendered: ${pageItems.length} chains with progress bars`);

            // Show pagination if needed
            if (totalPages > 1) {
                const paginationDiv = document.getElementById('chain-activity-pagination');
                paginationDiv.style.display = 'block';

                document.getElementById('chain-activity-prev').disabled = chainActivityPage <= 1;
                document.getElementById('chain-activity-next').disabled = chainActivityPage >= totalPages;
                document.getElementById('chain-activity-prev').style.opacity = chainActivityPage <= 1 ? '0.5' : '1';
                document.getElementById('chain-activity-next').style.opacity = chainActivityPage >= totalPages ? '0.5' : '1';
            }
        } else {
            container.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-link text-4xl text-gray-400 mb-3"></i>
                    <p class="text-gray-500">Tidak ada data aktivitas chain</p>
                </div>
            `;
            console.log('❌ No chain activity data found');
        }
    }

    // ⚡ NEW: Paginated Transactions Table
    function populateTransactionsTablePaginated() {
        const container = document.getElementById('recent-transactions');
        const totalItems = transactionsData.length;
        const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE.transactions);

        // Update count
        document.getElementById('transactions-count').textContent = `${totalItems} transactions`;

        if (totalItems > 0) {
            const startIndex = (transactionsPage - 1) * ITEMS_PER_PAGE.transactions;
            const endIndex = Math.min(startIndex + ITEMS_PER_PAGE.transactions, totalItems);
            const pageItems = transactionsData.slice(startIndex, endIndex);

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

            pageItems.forEach(tx => {
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
            `;

            container.innerHTML = html;

            // Update pagination info and show pagination
            if (totalPages > 1) {
                updateTransactionsPagination(totalPages, startIndex + 1, endIndex);
                document.getElementById('transactions-pagination').style.display = 'flex';
            } else {
                document.getElementById('transactions-page-info').textContent = `Showing all ${totalItems} transactions`;
            }
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

    // ⚡ NEW: Update transactions pagination
    function updateTransactionsPagination(totalPages, startItem, endItem) {
        // Update info
        document.getElementById('transactions-page-info').textContent =
            `Showing ${startItem} to ${endItem} of ${transactionsData.length} transactions`;

        // Update buttons
        document.getElementById('transactions-prev').disabled = transactionsPage <= 1;
        document.getElementById('transactions-next').disabled = transactionsPage >= totalPages;
        document.getElementById('transactions-prev').style.opacity = transactionsPage <= 1 ? '0.5' : '1';
        document.getElementById('transactions-next').style.opacity = transactionsPage >= totalPages ? '0.5' : '1';

        // Update page numbers
        const pageNumbersDiv = document.getElementById('transactions-page-numbers');
        let html = '';

        const startPage = Math.max(1, transactionsPage - 2);
        const endPage = Math.min(totalPages, transactionsPage + 2);

        for (let i = startPage; i <= endPage; i++) {
            const isActive = i === transactionsPage;
            html += `
                <button onclick="goToTransactionsPage(${i})"
                       class="clay-button ${isActive ? 'clay-button-primary' : 'clay-button-secondary'} py-1.5 px-3 text-sm">
                    ${i}
                </button>
            `;
        }

        pageNumbersDiv.innerHTML = html;
    }

    // ⚡ NEW: Pagination functions
    function changeMostTradedPage(direction) {
        const totalPages = Math.ceil(mostTradedTokens.length / ITEMS_PER_PAGE.mostTraded);
        const newPage = mostTradedPage + direction;

        if (newPage >= 1 && newPage <= totalPages) {
            mostTradedPage = newPage;
            updateMostTradedTokensPaginated();
        }
    }

    function changeChainActivityPage(direction) {
        const totalPages = Math.ceil(chainActivityData.length / ITEMS_PER_PAGE.chainActivity);
        const newPage = chainActivityPage + direction;

        if (newPage >= 1 && newPage <= totalPages) {
            chainActivityPage = newPage;
            updateChainActivityPaginated();
        }
    }

    function changeTransactionsPage(direction) {
        const totalPages = Math.ceil(transactionsData.length / ITEMS_PER_PAGE.transactions);
        const newPage = transactionsPage + direction;

        if (newPage >= 1 && newPage <= totalPages) {
            transactionsPage = newPage;
            populateTransactionsTablePaginated();
        }
    }

    function goToTransactionsPage(page) {
        const totalPages = Math.ceil(transactionsData.length / ITEMS_PER_PAGE.transactions);

        if (page >= 1 && page <= totalPages) {
            transactionsPage = page;
            populateTransactionsTablePaginated();
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

                // Refresh paginated data
                mostTradedTokens = data.analytics.most_traded_tokens || [];
                chainActivityData = Object.entries(data.analytics.chains_activity || {});

                // Reset pagination
                mostTradedPage = 1;
                chainActivityPage = 1;
                transactionsPage = 1;

                // Update paginated sections
                updateMostTradedTokensPaginated();
                updateChainActivityPaginated();
                updateTransactionChart(data.analytics.transaction_frequency || {});

                showNotification('⚡ Analytics data berhasil diperbarui dengan optimasi!', 'success');
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
        notification.className = `fixed top-20 right-4 z-50 clay-alert clay-alert-${type} max-w-sm`;
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
</script>
@endpush
@endsection

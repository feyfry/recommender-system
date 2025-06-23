@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header dengan Chain Selector -->
    <div class="clay-card p-6 mb-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
            <div class="flex-1">
                <h1 class="text-3xl font-bold mb-2 flex items-center">
                    <div class="bg-success/20 p-2 clay-badge mr-3">
                        <i class="fas fa-chart-line text-success"></i>
                    </div>
                    Multi-Chain Onchain Analytics
                </h1>
                <p class="text-gray-600">Analisis mendalam aktivitas onchain multi-blockchain wallet Anda</p>
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

    <!-- ⚡ ENHANCED: Chain Selector -->
    <div class="clay-card p-6 mb-8">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between">
            <div class="mb-4 md:mb-0">
                <h2 class="text-lg font-bold mb-2 flex items-center">
                    <i class="fas fa-filter mr-2 text-primary"></i>
                    Chain Analytics Filter
                </h2>
                <p class="text-sm text-gray-600">Pilih chain untuk analisis detail atau lihat semua chains sekaligus</p>
            </div>

            <div class="w-full md:w-auto">
                <select id="chain-selector" class="clay-select w-full md:w-64" onchange="changeAnalyticsChain()">
                    <!-- Options akan di-populate via JavaScript -->
                </select>
            </div>
        </div>

        <!-- Chain Info Display -->
        <div class="mt-4 p-3 clay-card bg-info/10" id="chain-info-display">
            <div class="flex items-center">
                <i class="fas fa-info-circle text-info mr-2"></i>
                <span id="chain-info-text">Loading chain information...</span>
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

    <!-- ⚡ ENHANCED: Loading State dengan Chain-aware Progress -->
    <div id="analytics-loading" class="mb-8">
        <div class="clay-card p-6">
            <div class="animate-pulse">
                <div class="flex items-center justify-center py-12">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-4 border-success mr-4"></div>
                    <div>
                        <div class="text-lg font-medium mb-2">⚡ Loading multi-chain analytics...</div>
                        <div class="text-sm text-gray-500">Menganalisis transaksi dari multiple blockchains (10-15 detik)</div>
                        <div class="text-xs text-gray-400 mt-1">Proses: Scanning ETH + BSC + Polygon + Avalanche → Aggregating data → Building analytics</div>
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

    <!-- Analytics Overview - Enhanced dengan Chain Info -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8" id="analytics-overview" style="display: none;">
        <div class="clay-card bg-primary/10 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-gray-600 text-sm">Total Transactions</div>
                    <div class="text-3xl font-bold" id="total-transactions">Loading...</div>
                    <div class="text-xs text-gray-500 mt-1" id="transactions-scope">Multi-chain activity</div>
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
                    <div class="text-xs text-gray-500 mt-1" id="tokens-scope">All chains</div>
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
                    <div class="text-xs text-gray-500 mt-1" id="volume-scope">USD equivalent</div>
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
                    <div class="text-xs text-gray-500 mt-1" id="chains-scope">Blockchains used</div>
                </div>
                <div class="bg-info/20 p-3 rounded-lg">
                    <i class="fas fa-network-wired text-info text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- ⚡ ADD: Native Token Summary Section -->
    <div class="clay-card p-6 mb-6" id="native-tokens-section" style="display: none;">
        <h2 class="text-xl font-bold mb-4 flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-star mr-2 text-warning"></i>
                Native Tokens Activity
                <span class="ml-2 clay-badge clay-badge-warning text-xs">NATIVE ONLY</span>
            </div>
            <span class="text-sm text-gray-500" id="native-tokens-count">Loading...</span>
        </h2>

        <div id="native-tokens-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- ⚡ Data akan di-populate via JavaScript -->
        </div>
    </div>

    <!-- ⚡ FIXED: 2 Columns Layout dengan Better Logic -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8" id="analytics-content" style="display: none;">
        <!-- Most Traded Tokens (Left Column) -->
        <div class="clay-card p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-trophy mr-2 text-warning"></i>
                    Most Traded Tokens
                    <span class="ml-2 clay-badge clay-badge-info text-xs" id="most-traded-badge">ALL CHAINS</span>
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
                    Chain Activity Distribution
                    <span class="ml-2 clay-badge clay-badge-info text-xs">ALL CHAINS</span>
                </div>
                <span class="text-sm text-gray-500" id="chain-activity-count">Loading...</span>
            </h2>

            <div id="chain-activity">
                <!-- ⚡ Data akan di-populate via JavaScript -->
            </div>
        </div>
    </div>

    <!-- ⚡ ENHANCED: Transaction Frequency Chart -->
    <div class="clay-card p-6 mb-8" id="chart-container-wrapper" style="display: none;">
        <h2 class="text-xl font-bold mb-4 flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-chart-area mr-2 text-primary"></i>
                Transaction Frequency (Last 30 Days)
            </div>
            <span class="text-sm text-gray-500" id="chart-scope-info">All chains</span>
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

    <!-- ⚡ SIMPLIFIED: Chain-Specific Analytics Section (hanya info, tanpa redundant transactions) -->
    <div class="clay-card p-6 mb-8" id="chain-specific-section" style="display: none;">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-focus mr-2 text-secondary"></i>
            <span id="chain-specific-title">Chain-Specific Analytics</span>
            <span class="ml-2 clay-badge clay-badge-secondary text-xs" id="chain-specific-badge">SELECTED</span>
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="clay-card bg-secondary/10 p-4">
                <div class="text-gray-600 text-sm">Chain Transactions</div>
                <div class="text-2xl font-bold" id="chain-specific-transactions">0</div>
                <div class="text-xs text-gray-500 mt-1" id="chain-specific-info">Selected chain only</div>
            </div>
            <div class="clay-card bg-warning/10 p-4">
                <div class="text-gray-600 text-sm">Native Token</div>
                <div class="text-2xl font-bold" id="chain-native-token">-</div>
                <div class="text-xs text-gray-500 mt-1">Primary asset</div>
            </div>
            <div class="clay-card bg-info/10 p-4">
                <div class="text-gray-600 text-sm">Latest Activity</div>
                <div class="text-2xl font-bold" id="chain-latest-activity">-</div>
                <div class="text-xs text-gray-500 mt-1">Last transaction</div>
            </div>
        </div>
    </div>

    <!-- ⚡ FIXED: Recent Transactions (Unified, dengan proper filtering) -->
    <div class="clay-card p-6" id="recent-transactions-wrapper" style="display: none;">
        <h2 class="text-xl font-bold mb-4 flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-history mr-2 text-secondary"></i>
                Recent Onchain Transactions
                <span class="ml-2 clay-badge clay-badge-info text-xs" id="transactions-scope-badge">ALL CHAINS</span>
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

    <!-- Analytics Insights - Enhanced dengan Multi-Chain -->
    <div class="clay-card p-6 mt-8" id="analytics-insights" style="display: none;">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-lightbulb mr-2 text-warning"></i>
            Multi-Chain Analytics Insights
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="clay-card bg-success/10 p-4">
                <h3 class="font-bold mb-2">💡 Trading Activity</h3>
                <p class="text-sm mb-2" id="trading-activity-insight">
                    Analyzing your multi-chain trading patterns...
                </p>
            </div>

            <div class="clay-card bg-info/10 p-4">
                <h3 class="font-bold mb-2">🌐 Diversification</h3>
                <p class="text-sm mb-2" id="diversification-insight">
                    Analyzing your cross-chain portfolio diversification...
                </p>
            </div>

            <div class="clay-card bg-warning/10 p-4">
                <h3 class="font-bold mb-2">⛓️ Multi-Chain Strategy</h3>
                <p class="text-sm mb-2" id="multichain-insight">
                    Analyzing your multi-blockchain optimization...
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
    let availableChains = [];
    let selectedChain = null;

    // ⚡ Pagination variables
    let mostTradedTokens = [];
    let chainActivityData = [];
    let transactionsData = [];

    let mostTradedPage = 1;
    let transactionsPage = 1;

    const ITEMS_PER_PAGE = {
        mostTraded: 5,
        transactions: 15
    };

    // ⚡ Load analytics data saat halaman ready
    document.addEventListener('DOMContentLoaded', function() {
        loadAnalyticsData();
    });

    // ⚡ FIXED: Change analytics chain dengan proper reload
    function changeAnalyticsChain() {
        const selector = document.getElementById('chain-selector');
        const newSelectedChain = selector.value || null;

        console.log('⚡ CHAIN CHANGE: From', selectedChain, 'to', newSelectedChain);

        selectedChain = newSelectedChain;

        // Update chain info display
        updateChainInfoDisplay();

        // Reset pagination
        mostTradedPage = 1;
        transactionsPage = 1;

        // Reload analytics with selected chain
        loadAnalyticsData();
    }

    // ⚡ Update chain info display
    function updateChainInfoDisplay() {
        const infoText = document.getElementById('chain-info-text');
        const selectedChainData = availableChains.find(chain => chain.value === selectedChain);

        if (selectedChain && selectedChainData) {
            infoText.innerHTML = `<i class="${selectedChainData.icon} mr-2"></i>Analyzing ${selectedChainData.label} specifically. Volume calculations include all detected transactions.`;
        } else {
            infoText.innerHTML = `<i class="fas fa-globe mr-2"></i>Analyzing all chains simultaneously. Comprehensive USD volume calculation enabled.`;
        }
    }

    // ⚡ FIXED: Enhanced analytics data loading dengan better error handling
    async function loadAnalyticsData() {
        const loadingDiv = document.getElementById('analytics-loading');
        const overviewDiv = document.getElementById('analytics-overview');
        const contentDiv = document.getElementById('analytics-content');

        // Show loading
        loadingDiv.style.display = 'block';
        overviewDiv.style.display = 'none';
        contentDiv.style.display = 'none';

        try {
            // ⚡ FIXED: Build request URL dengan chain parameter
            let requestUrl = '{{ route('panel.portfolio.load-analytics') }}';
            if (selectedChain) {
                requestUrl += `?chain=${selectedChain}`;
            }

            console.log('⚡ LOADING: Fetching analytics from:', requestUrl);

            const response = await fetch(requestUrl, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            const data = await response.json();
            console.log('⚡ RESPONSE:', data);

            if (data.success) {
                analyticsData = data;
                availableChains = data.available_chains || [];

                // ⚡ Enhanced debug info logging
                if (data.debug_info) {
                    console.log('⚡ DEBUG INFO:', data.debug_info);
                    console.log('⚡ TOTAL VOLUME USD:', data.debug_info.total_volume_usd);
                }

                // ⚡ Populate chain selector jika belum ada
                populateChainSelector();

                // Update analytics overview
                updateAnalyticsOverview(data.analytics);

                // Populate native tokens
                populateNativeTokensSection(data.analytics);

                // ⚡ FIXED: Prepare data dengan comprehensive logic
                prepareMostTradedData(data.analytics);
                chainActivityData = Object.entries(data.analytics.chains_activity || {});

                // ⚡ FIXED: Store transactions data
                transactionsData = data.transactions || [];
                console.log(`⚡ TRANSACTIONS: Received ${transactionsData.length} transactions`);

                // Update content sections
                updateChainActivityPaginated();
                updateTransactionChart(data.analytics.transaction_frequency || {});

                // Populate transactions table with pagination
                populateTransactionsTablePaginated();

                // Handle chain-specific data
                handleChainSpecificData(data.analytics);

                // Populate insights
                populateAnalyticsInsights(data.analytics);

                // Show content
                loadingDiv.style.display = 'none';
                overviewDiv.style.display = 'grid';
                contentDiv.style.display = 'grid';
                document.getElementById('chart-container-wrapper').style.display = 'block';
                document.getElementById('recent-transactions-wrapper').style.display = 'block';
                document.getElementById('analytics-insights').style.display = 'block';

                // Update scope indicators
                updateScopeIndicators();

                // Show appropriate notification
                if (data.cached) {
                    const cacheType = selectedChain ? `cached (${selectedChain})` : 'cached (multi-chain)';
                    showNotification(`⚡ Data loaded from ${cacheType}`, 'info');
                }

                if (data.analytics.total_transactions === 0) {
                    showNotification('⚠️ No analytics data found. Check API configuration.', 'warning');
                }

            } else {
                throw new Error(data.message || 'Failed to load analytics data');
            }

        } catch (error) {
            console.error('⚡ ERROR: Loading analytics data:', error);

            // Enhanced error state
            loadingDiv.innerHTML = `
                <div class="clay-card p-6">
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-triangle text-5xl text-yellow-500 mb-4"></i>
                        <h3 class="text-xl font-bold mb-3">Gagal Memuat Multi-Chain Analytics</h3>
                        <p class="text-gray-600 mb-4">${error.message}</p>
                        <div class="text-sm text-gray-500 mb-6">
                            <p>Possible issues:</p>
                            <ul class="list-disc list-inside text-left max-w-md mx-auto">
                                <li>API endpoint not responding</li>
                                <li>Analytics processing error</li>
                                <li>Chain data not available</li>
                                <li>USD volume calculation issues</li>
                                <li>Native token separation problems</li>
                            </ul>
                        </div>
                        <div class="space-x-3">
                            <button onclick="loadAnalyticsData()" class="clay-button clay-button-primary">
                                <i class="fas fa-retry mr-2"></i> Coba Lagi
                            </button>
                            <button onclick="checkApiStatus()" class="clay-button clay-button-info">
                                <i class="fas fa-heartbeat mr-2"></i> Check API
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }
    }

    // ⚡ FIXED: Enhanced populate chain selector
    function populateChainSelector() {
        const selector = document.getElementById('chain-selector');
        if (!selector || availableChains.length === 0) return;

        let html = '';
        availableChains.forEach(chain => {
            const selected = chain.value === selectedChain ? 'selected' : '';
            html += `<option value="${chain.value || ''}" ${selected}>${chain.label}</option>`;
        });

        selector.innerHTML = html;
        updateChainInfoDisplay();

        console.log('⚡ SELECTOR: Populated chain selector with', availableChains.length, 'options');
    }

    // ⚡ FIXED: Enhanced prepare most traded data dengan comprehensive logic
    function prepareMostTradedData(analytics) {
        mostTradedTokens = [];

        // ⚡ FIXED: Use comprehensive token list (native + alt) dengan smart filtering
        if (analytics.most_traded_tokens && analytics.most_traded_tokens.length > 0) {
            // ⚡ ENHANCED: Include both native dan alt tokens, tapi prioritize based on context
            mostTradedTokens = analytics.most_traded_tokens.filter(token => {
                // Skip tokens dengan 0 trade count
                return token.trade_count && token.trade_count > 0;
            });

            // ⚡ ENHANCED: If ETH chain selected dan no alt tokens, show native ETH
            if (selectedChain === 'eth' && mostTradedTokens.length === 0) {
                // Add native ETH dari native_token_summary jika ada
                if (analytics.native_token_summary) {
                    const ethNative = analytics.native_token_summary.find(token =>
                        token.symbol === 'ETH' || token.symbol === 'WETH'
                    );
                    if (ethNative) {
                        mostTradedTokens = [ethNative];
                    }
                }
            }
        }

        console.log('⚡ MOST TRADED: Prepared', mostTradedTokens.length, 'tokens for chain:', selectedChain || 'all');

        // Reset pagination
        mostTradedPage = 1;
        updateMostTradedTokensPaginated();
    }

    // ⚡ FIXED: Enhanced update most traded tokens dengan comprehensive logic
    function updateMostTradedTokensPaginated() {
        const container = document.getElementById('most-traded-tokens');
        const totalItems = mostTradedTokens.length;
        const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE.mostTraded);

        // ⚡ FIXED: Update badge berdasarkan context
        const selectedChainInfo = availableChains.find(c => c.value === selectedChain);
        const chainLabel = selectedChain && selectedChainInfo ? selectedChainInfo.label.toUpperCase() : 'ALL CHAINS';

        // ⚡ FIXED: Update badge pada section title
        const mostTradedBadge = document.getElementById('most-traded-badge');
        if (mostTradedBadge) {
            mostTradedBadge.textContent = chainLabel;
        }

        document.getElementById('most-traded-count').textContent = `${totalItems} tokens (${chainLabel.toLowerCase()})`;

        if (totalItems > 0) {
            const startIndex = (mostTradedPage - 1) * ITEMS_PER_PAGE.mostTraded;
            const endIndex = Math.min(startIndex + ITEMS_PER_PAGE.mostTraded, totalItems);
            const pageItems = mostTradedTokens.slice(startIndex, endIndex);

            let html = '<div class="space-y-3">';

            pageItems.forEach((token, index) => {
                const globalIndex = startIndex + index + 1;

                // ⚡ ENHANCED: Comprehensive token display dengan native detection
                const isNativeToken = ['ETH', 'BNB', 'MATIC', 'AVAX'].includes(token.symbol?.toUpperCase());
                const chainBadge = token.chain ?
                    `<span class="clay-badge clay-badge-${isNativeToken ? 'warning' : 'info'} text-xs ml-2">${token.chain.toUpperCase()}</span>` : '';

                // ⚡ ENHANCED: Volume display dengan USD prioritization
                const volumeDisplay = token.volume_usd && token.volume_usd > 0 ?
                    `$${numberFormat(token.volume_usd, 2)}` :
                    `${numberFormat(token.volume || 0, 8)} ${token.symbol || ''}`;

                html += `
                    <div class="flex items-center justify-between p-3 clay-card bg-gray-50">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-warning/20 rounded-full flex items-center justify-center mr-3">
                                <span class="text-warning font-bold text-sm">${globalIndex}</span>
                            </div>
                            <div>
                                <div class="font-medium flex items-center">
                                    ${token.symbol || 'Unknown'}
                                    ${chainBadge}
                                    ${isNativeToken ? '<span class="clay-badge clay-badge-success text-xs ml-1">NATIVE</span>' : ''}
                                </div>
                                <div class="text-xs text-gray-500">${token.trade_count || 0} transactions</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-medium">${volumeDisplay}</div>
                            ${token.volume_usd && token.volume_usd > 0 ?
                                `<div class="text-xs text-gray-500">${numberFormat(token.volume || 0, 6)} ${token.symbol || ''}</div>` :
                                '<div class="text-xs text-gray-500">Volume tracked</div>'
                            }
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

                document.getElementById('most-traded-page-info').textContent = `Page ${mostTradedPage} of ${totalPages}`;
            }
        } else {
            // ⚡ ENHANCED: Better empty state dengan context-aware messaging
            const chainContext = selectedChain ? ` untuk ${chainLabel}` : ' multi-chain';
            let emptyMessage = `Tidak ada token trading${chainContext}`;
            let subMessage = 'Transaksi akan muncul setelah ada aktivitas trading';

            if (selectedChain === 'eth') {
                emptyMessage = 'ETH trading activity belum terdeteksi';
                subMessage = 'Periksa apakah ada transaksi ETH di wallet ini atau coba refresh data';
            }

            container.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-coins text-4xl text-gray-400 mb-3"></i>
                    <p class="text-gray-500">${emptyMessage}</p>
                    <p class="text-sm text-gray-400 mt-2">${subMessage}</p>
                    ${selectedChain ? `
                        <button onclick="resetToAllChains()" class="clay-button clay-button-info mt-4">
                            <i class="fas fa-globe mr-2"></i> View All Chains
                        </button>
                    ` : ''}
                </div>
            `;
        }
    }

    // ⚡ FIXED: Update chain activity paginated
    function updateChainActivityPaginated() {
        const container = document.getElementById('chain-activity');
        const totalItems = chainActivityData.length;

        // Update count
        document.getElementById('chain-activity-count').textContent = `${totalItems} chains (distribution across all)`;

        if (totalItems > 0) {
            const totalChainTxs = chainActivityData.reduce((sum, [, count]) => sum + count, 0);

            let html = '<div class="space-y-3">';

            chainActivityData.forEach(([chain, txCount]) => {
                const percentage = totalChainTxs > 0 ? (txCount / totalChainTxs) * 100 : 0;

                // ⚡ ENHANCED: Chain-specific styling
                const chainInfo = getChainDisplayInfo(chain);

                // ⚡ ENHANCED: Highlight selected chain
                const isSelectedChain = selectedChain === chain;
                const highlightClass = isSelectedChain ? 'ring-2 ring-primary ring-opacity-50 bg-primary/5' : 'bg-info/5';

                html += `
                    <div class="clay-card ${highlightClass} p-3">
                        <div class="flex justify-between items-center mb-2">
                            <div class="flex items-center">
                                <i class="${chainInfo.icon} text-${chainInfo.color} mr-2"></i>
                                <span class="font-medium">${chainInfo.name}</span>
                                ${isSelectedChain ? '<span class="clay-badge clay-badge-primary text-xs ml-2">SELECTED</span>' : ''}
                            </div>
                            <span class="text-sm">${numberFormat(txCount, 0)} txs</span>
                        </div>
                        <div style="width: 100%; height: 14px; background-color: #e0f2fe; border: 1px solid #81d4fa; border-radius: 7px; overflow: hidden; margin-bottom: 8px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
                            <div style="height: 100%; background: ${chainInfo.gradient}; border-radius: 6px; width: ${percentage}%; transition: width 0.5s ease-in-out; min-width: ${percentage > 0 ? Math.max(percentage, 1.5) : 0}%; box-shadow: 0 1px 3px rgba(0,0,0,0.2);"></div>
                        </div>
                        <div class="text-xs text-right">${percentage.toFixed(1)}% of total activity</div>
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

    // ⚡ Get chain display info dengan icons dan colors
    function getChainDisplayInfo(chain) {
        const chainMap = {
            'ethereum': {
                name: 'Ethereum',
                icon: 'fab fa-ethereum',
                color: 'blue',
                gradient: 'linear-gradient(135deg, #627eea, #4c68d7)'
            },
            'eth': {
                name: 'Ethereum',
                icon: 'fab fa-ethereum',
                color: 'blue',
                gradient: 'linear-gradient(135deg, #627eea, #4c68d7)'
            },
            'bsc': {
                name: 'Binance Smart Chain',
                icon: 'fas fa-coins',
                color: 'yellow',
                gradient: 'linear-gradient(135deg, #f0b90b, #d49c06)'
            },
            'polygon': {
                name: 'Polygon',
                icon: 'fas fa-project-diagram',
                color: 'purple',
                gradient: 'linear-gradient(135deg, #8247e5, #6c38cc)'
            },
            'avalanche': {
                name: 'Avalanche',
                icon: 'fas fa-mountain',
                color: 'red',
                gradient: 'linear-gradient(135deg, #e84142, #d73334)'
            }
        };

        return chainMap[chain.toLowerCase()] || {
            name: chain.charAt(0).toUpperCase() + chain.slice(1),
            icon: 'fas fa-link',
            color: 'gray',
            gradient: 'linear-gradient(135deg, #6b7280, #4b5563)'
        };
    }

    // ⚡ FIXED: Enhanced populate transactions table dengan proper filtering
    function populateTransactionsTablePaginated() {
        const container = document.getElementById('recent-transactions');

        // ⚡ FIXED: Filter transactions berdasarkan selected chain
        let filteredTransactions = transactionsData;
        if (selectedChain && transactionsData.length > 0) {
            filteredTransactions = transactionsData.filter(tx => {
                const txChain = tx.chain ? tx.chain.toLowerCase() : '';
                const filterChain = selectedChain.toLowerCase();

                // Handle ethereum/eth variations
                const normalizedTxChain = txChain === 'eth' ? 'ethereum' : txChain;
                const normalizedFilterChain = filterChain === 'eth' ? 'ethereum' : filterChain;

                return normalizedTxChain === normalizedFilterChain;
            });
            console.log(`⚡ FILTERED TRANSACTIONS: ${filteredTransactions.length} dari ${transactionsData.length} untuk chain ${selectedChain}`);
        }

        const totalItems = filteredTransactions.length;
        const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE.transactions);

        // ⚡ FIXED: Update count dengan chain context
        const selectedChainInfo = availableChains.find(c => c.value === selectedChain);
        const chainLabel = selectedChain && selectedChainInfo ? selectedChainInfo.label.toUpperCase() : 'ALL CHAINS';
        document.getElementById('transactions-count').textContent = `${totalItems} transactions (${chainLabel.toLowerCase()})`;

        // ⚡ FIXED: Update badge pada section title
        const transactionsBadge = document.getElementById('transactions-scope-badge');
        if (transactionsBadge) {
            transactionsBadge.textContent = chainLabel;
        }

        if (totalItems > 0) {
            const startIndex = (transactionsPage - 1) * ITEMS_PER_PAGE.transactions;
            const endIndex = Math.min(startIndex + ITEMS_PER_PAGE.transactions, totalItems);
            const pageItems = filteredTransactions.slice(startIndex, endIndex);

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
                // ⚡ FIXED: Handle date formatting dengan fallback
                let date = 'Unknown';
                try {
                    if (tx.timestamp) {
                        date = new Date(tx.timestamp).toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    }
                } catch (e) {
                    console.warn('⚡ WARNING: Date parsing failed for transaction:', tx.tx_hash);
                }

                const txHash = tx.tx_hash ? tx.tx_hash.substring(0, 16) + '...' : 'N/A';
                const typeClass = tx.transaction_type === 'native' ? 'primary' :
                                tx.transaction_type === 'token' ? 'secondary' : 'info';
                const statusClass = tx.status === 'success' ? 'success' : 'danger';

                // ⚡ ENHANCED: Chain-specific styling
                const chainInfo = getChainDisplayInfo(tx.chain || '');

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
                            <div class="flex items-center">
                                <i class="${chainInfo.icon} text-${chainInfo.color} mr-1 text-xs"></i>
                                <span class="clay-badge clay-badge-info text-xs">${(tx.chain || '').toUpperCase()}</span>
                            </div>
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
                updateTransactionsPagination(totalPages, startIndex + 1, endIndex, totalItems);
                document.getElementById('transactions-pagination').style.display = 'flex';
            } else {
                document.getElementById('transactions-page-info').textContent = `Showing all ${totalItems} transactions`;
            }
        } else {
            // ⚡ FIXED: Enhanced empty state dengan chain context
            const chainContext = selectedChain ? ` untuk chain ${chainLabel}` : ' multi-chain';
            container.innerHTML = `
                <div class="text-center py-12">
                    <i class="fas fa-history text-4xl text-gray-400 mb-3"></i>
                    <p class="text-gray-500">Tidak ada transaksi onchain ditemukan${chainContext}</p>
                    <p class="text-sm text-gray-400 mt-2">
                        ${selectedChain ?
                            `Coba pilih chain lain atau "All Chains" untuk melihat semua data` :
                            'Pastikan wallet address sudah benar dan sudah melakukan transaksi'
                        }
                    </p>
                    ${selectedChain ? `
                        <button onclick="resetToAllChains()" class="clay-button clay-button-info mt-4">
                            <i class="fas fa-globe mr-2"></i> View All Chains
                        </button>
                    ` : ''}
                </div>
            `;

            // Hide pagination
            document.getElementById('transactions-pagination').style.display = 'none';
        }
    }

    // ⚡ Reset to all chains
    function resetToAllChains() {
        selectedChain = null;
        const selector = document.getElementById('chain-selector');
        if (selector) {
            selector.value = '';
        }
        changeAnalyticsChain();
    }

    // ⚡ Update transactions pagination
    function updateTransactionsPagination(totalPages, startItem, endItem, totalFilteredItems) {
        // Update info dengan filtered data count
        document.getElementById('transactions-page-info').textContent =
            `Showing ${startItem} to ${endItem} of ${totalFilteredItems} transactions`;

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

    // ⚡ Pagination functions
    function changeMostTradedPage(direction) {
        const totalPages = Math.ceil(mostTradedTokens.length / ITEMS_PER_PAGE.mostTraded);
        const newPage = mostTradedPage + direction;

        if (newPage >= 1 && newPage <= totalPages) {
            mostTradedPage = newPage;
            updateMostTradedTokensPaginated();
        }
    }

    function changeTransactionsPage(direction) {
        // ⚡ FIXED: Calculate total pages berdasarkan filtered data
        let filteredCount = transactionsData.length;
        if (selectedChain && transactionsData.length > 0) {
            const filtered = transactionsData.filter(tx => {
                const txChain = tx.chain ? tx.chain.toLowerCase() : '';
                const filterChain = selectedChain.toLowerCase();
                const normalizedTxChain = txChain === 'eth' ? 'ethereum' : txChain;
                const normalizedFilterChain = filterChain === 'eth' ? 'ethereum' : filterChain;
                return normalizedTxChain === normalizedFilterChain;
            });
            filteredCount = filtered.length;
        }

        const totalPages = Math.ceil(filteredCount / ITEMS_PER_PAGE.transactions);
        const newPage = transactionsPage + direction;

        if (newPage >= 1 && newPage <= totalPages) {
            transactionsPage = newPage;
            populateTransactionsTablePaginated();
        }
    }

    function goToTransactionsPage(page) {
        // ⚡ FIXED: Calculate total pages berdasarkan filtered data
        let filteredCount = transactionsData.length;
        if (selectedChain && transactionsData.length > 0) {
            const filtered = transactionsData.filter(tx => {
                const txChain = tx.chain ? tx.chain.toLowerCase() : '';
                const filterChain = selectedChain.toLowerCase();
                const normalizedTxChain = txChain === 'eth' ? 'ethereum' : txChain;
                const normalizedFilterChain = filterChain === 'eth' ? 'ethereum' : filterChain;
                return normalizedTxChain === normalizedFilterChain;
            });
            filteredCount = filtered.length;
        }

        const totalPages = Math.ceil(filteredCount / ITEMS_PER_PAGE.transactions);

        if (page >= 1 && page <= totalPages) {
            transactionsPage = page;
            populateTransactionsTablePaginated();
        }
    }

    // ⚡ Handle chain-specific data (simplified)
    function handleChainSpecificData(analytics) {
        const chainSpecificSection = document.getElementById('chain-specific-section');

        if (selectedChain && analytics.chain_specific_data) {
            const chainData = analytics.chain_specific_data;

            // Show chain-specific section
            chainSpecificSection.style.display = 'block';

            // Update title and badge
            const selectedChainInfo = availableChains.find(c => c.value === selectedChain);
            document.getElementById('chain-specific-title').textContent =
                `${selectedChainInfo?.label || selectedChain} Analytics`;
            document.getElementById('chain-specific-badge').textContent = selectedChain.toUpperCase();

            // Update chain-specific stats
            document.getElementById('chain-specific-transactions').textContent =
                numberFormat(chainData.total_transactions || 0, 0);
            document.getElementById('chain-native-token').textContent =
                chainData.native_token || 'N/A';

            // Calculate latest activity
            const latestTx = chainData.transactions && chainData.transactions.length > 0
                ? chainData.transactions[0] : null;
            const latestActivity = latestTx
                ? new Date(latestTx.timestamp).toLocaleDateString('id-ID')
                : 'No data';
            document.getElementById('chain-latest-activity').textContent = latestActivity;

        } else {
            // Hide chain-specific section
            chainSpecificSection.style.display = 'none';
        }
    }

    // ⚡ Update scope indicators
    function updateScopeIndicators() {
        const selectedChainInfo = availableChains.find(c => c.value === selectedChain);

        if (selectedChain && selectedChainInfo) {
            document.getElementById('transactions-scope').textContent = `${selectedChainInfo.label} activity`;
            document.getElementById('chart-scope-info').textContent = selectedChainInfo.label;
        } else {
            document.getElementById('transactions-scope').textContent = 'Multi-chain activity';
            document.getElementById('chart-scope-info').textContent = 'All chains';
        }

        // Always keep these as all chains for comprehensive view
        document.getElementById('tokens-scope').textContent = 'All chains';
        document.getElementById('volume-scope').textContent = 'All chains USD';
        document.getElementById('chains-scope').textContent = 'All blockchains';

        console.log('⚡ SCOPE: Updated indicators for chain:', selectedChain || 'all');
    }

    // ⚡ Enhanced refresh analytics
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
            let requestUrl = '{{ route('panel.portfolio.refresh-analytics') }}';
            const requestData = {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            };

            if (selectedChain) {
                requestData.body = JSON.stringify({ chain: selectedChain });
            }

            console.log('⚡ REFRESH: Refreshing analytics for chain:', selectedChain || 'all');

            const response = await fetch(requestUrl, requestData);
            const data = await response.json();

            console.log('⚡ REFRESH RESPONSE:', data);

            if (data.success && data.analytics) {
                // Update analytics overview cards
                updateAnalyticsOverview(data.analytics);

                // ⚡ FIXED: Refresh data dengan comprehensive logic
                prepareMostTradedData(data.analytics);
                chainActivityData = Object.entries(data.analytics.chains_activity || {});

                // ⚡ FIXED: Update transactions
                transactionsData = data.transactions || [];
                console.log(`⚡ REFRESH: Received ${transactionsData.length} transactions`);

                // Reset pagination
                mostTradedPage = 1;
                transactionsPage = 1;

                // Update sections
                updateChainActivityPaginated();
                populateTransactionsTablePaginated();
                updateTransactionChart(data.analytics.transaction_frequency || {});

                // Handle chain-specific data
                handleChainSpecificData(data.analytics);

                // Update scope indicators
                updateScopeIndicators();

                const chainInfo = selectedChain ? ` untuk ${selectedChain}` : ' multi-chain';
                showNotification(`⚡ Analytics data${chainInfo} berhasil diperbarui!`, 'success');

                if (data.debug_info) {
                    console.log('⚡ REFRESH DEBUG:', data.debug_info);
                }
            } else {
                showNotification(data.message || 'Gagal memperbarui data', 'error');
            }

        } catch (error) {
            console.error('⚡ ERROR: Refreshing analytics:', error);
            showNotification('Error memperbarui analytics: ' + error.message, 'error');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;

            // Hide loading state
            loadingDiv.style.display = 'none';
            overviewDiv.style.display = 'grid';
        }
    }

    // ⚡ Copy wallet address to clipboard
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            showNotification('Wallet address copied to clipboard!', 'success');
        });
    }

    // ⚡ Enhanced insights untuk multi-chain
    function populateAnalyticsInsights(analytics) {
        const totalTx = analytics.total_transactions || 0;
        const uniqueTokens = analytics.unique_tokens_traded || 0;
        const chainsCount = Object.keys(analytics.chains_activity || {}).length;
        const totalVolumeUsd = analytics.total_volume_usd || 0;

        // Trading Activity Insight
        let tradingInsight = '';
        if (totalTx > 200) {
            tradingInsight = `Sangat aktif dengan ${numberFormat(totalTx, 0)} transaksi dan $${numberFormat(totalVolumeUsd, 2)} volume USD.`;
        } else if (totalTx > 50) {
            tradingInsight = `Aktivitas solid dengan ${numberFormat(totalTx, 0)} transaksi di ${chainsCount} chains.`;
        } else {
            tradingInsight = `Portfolio developing dengan ${numberFormat(totalTx, 0)} transaksi tracked.`;
        }
        document.getElementById('trading-activity-insight').textContent = tradingInsight;

        // Diversification Insight
        let diversificationInsight = '';
        if (uniqueTokens > 10 && chainsCount > 2) {
            diversificationInsight = `Excellent diversification: ${uniqueTokens} tokens across ${chainsCount} chains.`;
        } else if (uniqueTokens > 5) {
            diversificationInsight = `Good diversification dengan ${uniqueTokens} tokens. Consider expand ke chains lain.`;
        } else {
            diversificationInsight = `Pertimbangkan diversifikasi lebih luas across multiple chains.`;
        }
        document.getElementById('diversification-insight').textContent = diversificationInsight;

        // Multi-Chain Strategy Insight
        let multichainInsight = '';
        if (chainsCount >= 3) {
            multichainInsight = `Optimal multi-chain strategy! Aktif di ${chainsCount} blockchains dengan total volume $${numberFormat(totalVolumeUsd, 2)}.`;
        } else if (chainsCount >= 2) {
            multichainInsight = `Good multi-chain approach. Consider expand untuk better yields dan opportunities.`;
        } else {
            multichainInsight = `Single-chain focused. Multi-chain strategy dapat memberikan better yields.`;
        }
        document.getElementById('multichain-insight').textContent = multichainInsight;
    }

    // ⚡ Update analytics overview cards
    function updateAnalyticsOverview(analytics) {
        document.getElementById('total-transactions').textContent = numberFormat(analytics.total_transactions || 0, 0);
        document.getElementById('unique-tokens').textContent = numberFormat(analytics.unique_tokens_traded || 0, 0);
        document.getElementById('total-volume').textContent = '$' + numberFormat(analytics.total_volume_usd || 0, 2);
        document.getElementById('active-chains').textContent = Object.keys(analytics.chains_activity || {}).length;
    }

    // ⚡ Function untuk populate native tokens section
    function populateNativeTokensSection(analytics) {
        const nativeTokensSection = document.getElementById('native-tokens-section');
        const container = document.getElementById('native-tokens-list');
        const countElement = document.getElementById('native-tokens-count');

        if (analytics.native_token_summary && analytics.native_token_summary.length > 0) {
            const nativeTokens = analytics.native_token_summary;

            // Show section
            nativeTokensSection.style.display = 'block';
            countElement.textContent = `${nativeTokens.length} native tokens`;

            let html = '';
            nativeTokens.forEach(token => {
                const chainInfo = getChainDisplayInfo(token.chain || '');

                html += `
                    <div class="clay-card bg-warning/10 p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center">
                                <i class="${chainInfo.icon} text-${chainInfo.color} mr-2"></i>
                                <span class="font-bold">${token.symbol}</span>
                            </div>
                            <span class="clay-badge clay-badge-${chainInfo.color} text-xs">${token.chain.toUpperCase()}</span>
                        </div>
                        <div class="text-sm text-gray-600 mb-1">${token.trade_count} transactions</div>
                        <div class="text-lg font-bold">$${numberFormat(token.volume_usd || 0, 2)}</div>
                        <div class="text-xs text-gray-500">${numberFormat(token.volume || 0, 8)} ${token.symbol}</div>
                    </div>
                `;
            });

            container.innerHTML = html;
        } else {
            // Hide section if no native tokens
            nativeTokensSection.style.display = 'none';
        }
    }

    // ⚡ Update transaction chart
    function updateTransactionChart(transactionFrequency) {
        const entries = Object.entries(transactionFrequency);
        if (entries.length === 0) {
            showChartFallback(transactionFrequency);
            return;
        }

        try {
            if (transactionChart) {
                transactionChart.destroy();
            }

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

            const chartFallback = document.getElementById('chart-fallback');
            const chartWrapper = document.getElementById('chart-wrapper');
            if (chartFallback) chartFallback.style.display = 'none';
            if (chartWrapper) chartWrapper.style.display = 'block';

        } catch (error) {
            console.error('Error initializing chart:', error);
            showChartFallback(transactionFrequency);
        }
    }

    // ⚡ Enhanced fallback chart
    function showChartFallback(transactionFrequency = {}) {
        const chartWrapper = document.getElementById('chart-wrapper');
        const chartFallback = document.getElementById('chart-fallback');

        if (chartWrapper) chartWrapper.style.display = 'none';
        if (chartFallback) {
            const entries = Object.entries(transactionFrequency);

            if (entries.length > 0) {
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
                const chainContext = selectedChain ? ` pada ${selectedChain.toUpperCase()}` : ' multi-chain';
                chartFallback.innerHTML = `
                    <div class="text-center py-16">
                        <i class="fas fa-chart-line text-4xl text-gray-400 mb-3"></i>
                        <p class="text-gray-500">Tidak ada data frekuensi transaksi${chainContext}</p>
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

    // ⚡ Number formatting
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

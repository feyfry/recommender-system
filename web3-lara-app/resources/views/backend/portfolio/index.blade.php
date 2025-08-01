@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
            <div class="flex-1">
                <h1 class="text-3xl font-bold mb-2 flex items-center">
                    <div class="bg-success/20 p-2 clay-badge mr-3">
                        <i class="fas fa-wallet text-success"></i>
                    </div>
                    Portfolio Overview
                </h1>
                <p class="text-gray-600">Portfolio real onchain dan data transaksi manual</p>
                <div class="text-sm text-gray-500 mt-1">
                    Wallet: <span class="font-mono">{{ Str::limit($walletAddress, 20) }}</span>
                    <button onclick="copyToClipboard('{{ $walletAddress }}')" class="ml-2 text-primary hover:text-primary-dark">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
            <div class="mt-4 md:mt-0 flex flex-col sm:flex-row gap-2 sm:gap-3 w-full md:w-auto">
                <button type="button" onclick="refreshOnchainData()" class="clay-button clay-button-info w-full sm:w-auto text-sm" id="refresh-btn">
                    <i class="fas fa-sync-alt mr-2"></i> Refresh Onchain
                </button>
                <a href="{{ route('panel.portfolio.transaction-management') }}" class="clay-button clay-button-primary w-full sm:w-auto text-sm">
                    <i class="fas fa-exchange-alt mr-2"></i> Transaction Management
                </a>
                <a href="{{ route('panel.portfolio.onchain-analytics') }}" class="clay-button clay-button-secondary w-full sm:w-auto text-sm">
                    <i class="fas fa-chart-line mr-2"></i> Onchain Analytics
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="clay-alert clay-alert-success mb-6">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            {{ session('success') }}
        </div>
    </div>
    @endif

    <!-- API Status Indicator -->
    <div class="clay-card p-4 mb-6" id="api-status-card">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div id="api-status-indicator" class="w-3 h-3 rounded-full mr-3"></div>
                <span class="text-sm font-medium" id="api-status-text">Checking API status...</span>
            </div>
            <span class="text-xs text-gray-500" id="api-optimization-info">Native token focus enabled</span>
        </div>
    </div>

    <!-- ⚡ ENHANCED: Loading State with Better Skeleton -->
    <div id="loading-state" class="mb-8">
        <div class="clay-card p-6">
            <div class="animate-pulse">
                <div class="flex items-center justify-center py-12">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-4 border-primary mr-4"></div>
                    <div>
                        <div class="text-lg font-medium mb-2">⚡ Loading native-focused portfolio data...</div>
                        <div class="text-sm text-gray-500">Prioritizing native tokens untuk faster loading (5-10 detik)</div>
                        <div class="text-xs text-gray-400 mt-1">Proses: Scanning ETH/BNB/MATIC/AVAX → Filtering spam → Calculating USD values</div>
                    </div>
                </div>
                <!-- Enhanced Skeleton content -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8">
                    <div class="lg:col-span-2">
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div class="clay-card bg-gray-100 p-6 h-28 rounded-lg"></div>
                            <div class="clay-card bg-gray-100 p-6 h-28 rounded-lg"></div>
                        </div>
                        <div class="clay-card bg-gray-100 p-6 h-80 rounded-lg"></div>
                    </div>
                    <div class="lg:col-span-1">
                        <div class="clay-card bg-gray-100 p-6 h-40 mb-6 rounded-lg"></div>
                        <div class="clay-card bg-gray-100 p-6 h-40 rounded-lg"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Real Portfolio (Onchain Data) - ⚡ LAZY LOADED -->
    <div id="onchain-portfolio-section" class="mb-8" style="display: none;">
        <div class="clay-card p-6">
            <h2 class="text-2xl font-bold mb-4 flex flex-wrap items-center">
                <div class="bg-primary/20 p-2 rounded-lg mr-3">
                    <i class="fas fa-link text-primary"></i>
                </div>
                Real Portfolio (Onchain Data)
                <span class="lg:ml-3 clay-badge clay-badge-success text-xs break-all">LIVE</span>
                <span id="native-focus-badge" class="ml-2 clay-badge clay-badge-info text-xs break-all">
                    NATIVE FOCUS
                </span>
                <span id="spam-filter-badge" class="ml-2 clay-badge clay-badge-warning text-xs break-all" style="display: none;">
                    SPAM FILTERED
                </span>
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Summary Cards -->
                <div class="lg:col-span-2">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                        <div class="clay-card bg-primary/10 p-4">
                            <div class="text-gray-600 text-sm">Total Value (Real)</div>
                            <div class="text-2xl font-bold" id="onchain-total-value">
                                $0.00000000
                            </div>
                            <div class="text-xs text-gray-500 mt-1" id="onchain-last-updated">
                                Loading...
                            </div>
                        </div>
                        <div class="clay-card bg-success/10 p-4">
                            <div class="text-gray-600 text-sm">Valid Assets</div>
                            <div class="text-2xl font-bold" id="onchain-asset-count">
                                0
                            </div>
                            <div class="text-xs text-gray-500 mt-1" id="onchain-chains">
                                Loading...
                            </div>
                        </div>
                    </div>

                    <!-- ⚡ NEW: Native Tokens Section -->
                    <div class="clay-card bg-blue/5 p-4 mb-4" id="native-tokens-section" style="display: none;">
                        <h3 class="font-bold mb-3 flex items-center justify-between">
                            <div class="flex items-center">
                                <i class="fas fa-star mr-2 text-blue-600"></i>
                                Native Tokens (Priority)
                                <span class="ml-2 clay-badge clay-badge-info text-xs">HIGH VALUE</span>
                            </div>
                            <span class="text-sm text-gray-500" id="native-tokens-count">Loading...</span>
                        </h3>
                        <div id="native-tokens-list" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <!-- Native tokens will be populated here -->
                        </div>
                    </div>

                    <!-- ⚡ ENHANCED: Portfolio Holdings dengan filtering options -->
                    <div class="mb-4">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-3">
                            <h3 class="font-bold flex items-center">
                                <i class="fas fa-coins mr-2 text-primary"></i>
                                Portfolio Assets
                            </h3>

                            <!-- Filter Controls -->
                            <div class="flex items-center space-x-3 mt-3 md:mt-0">
                                <div class="flex items-center space-x-2">
                                    <label class="text-sm text-gray-600">Show:</label>
                                    <select id="asset-filter" class="clay-select text-sm" onchange="filterAssets()">
                                        <option value="all">All Assets</option>
                                        <option value="native">Native Only</option>
                                        <option value="tokens">Tokens Only</option>
                                        <option value="valuable">With USD Value</option>
                                    </select>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <label class="text-sm text-gray-600">Sort:</label>
                                    <select id="asset-sort" class="clay-select text-sm" onchange="sortAssets()">
                                        <option value="value_desc">USD Value (High to Low)</option>
                                        <option value="value_asc">USD Value (Low to High)</option>
                                        <option value="balance_desc">Balance (High to Low)</option>
                                        <option value="name_asc">Name (A to Z)</option>
                                    </select>
                                </div>
                                <span class="text-sm text-gray-500" id="assets-count">Loading...</span>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="clay-table min-w-full">
                                <thead>
                                    <tr>
                                        <th class="py-2 px-4 text-left">Asset</th>
                                        <th class="py-2 px-4 text-left">Balance</th>
                                        <th class="py-2 px-4 text-left">Chain</th>
                                        <th class="py-2 px-4 text-left">USD Value</th>
                                        <th class="py-2 px-4 text-left">Status</th>
                                        <th class="py-2 px-4 text-left">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="onchain-holdings-table">
                                    <!-- ⚡ Data akan di-populate via JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <!-- ⚡ Enhanced: Pagination untuk Holdings -->
                        <div class="mt-6" id="holdings-pagination" style="display: none;">
                            <div class="flex flex-col md:flex-row justify-between items-center">
                                <!-- Info pages -->
                                <div class="mb-4 md:mb-0">
                                    <span class="text-sm text-gray-600" id="holdings-page-info">
                                        Showing assets...
                                    </span>
                                </div>

                                <!-- Pagination buttons -->
                                <div class="flex justify-center space-x-2">
                                    <!-- Previous -->
                                    <button onclick="changeHoldingsPage(-1)"
                                           class="clay-button clay-button-secondary py-1.5 px-3 text-sm ml-4"
                                           id="holdings-prev">
                                        <i class="fas fa-chevron-left"></i>
                                    </button>

                                    <!-- Page Numbers -->
                                    <div id="holdings-page-numbers" class="flex space-x-1">
                                        <!-- Will be populated by JavaScript -->
                                    </div>

                                    <!-- Next -->
                                    <button onclick="changeHoldingsPage(1)"
                                           class="clay-button clay-button-secondary py-1.5 px-3 text-sm"
                                           id="holdings-next">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Sidebar - Distributions -->
                <div class="lg:col-span-1">
                    <!-- Category Distribution -->
                    <div class="clay-card p-4 mb-6">
                        <h3 class="font-bold mb-3 flex items-center">
                            <i class="fas fa-chart-pie mr-2 text-secondary"></i>
                            Category Distribution
                        </h3>
                        <div id="onchain-category-distribution">
                            <!-- ⚡ Data akan di-populate via JavaScript -->
                        </div>
                    </div>

                    <!-- Chain Distribution -->
                    <div class="clay-card p-4 mb-6">
                        <h3 class="font-bold mb-3 flex items-center">
                            <i class="fas fa-link mr-2 text-info"></i>
                            Chain Distribution
                        </h3>
                        <div id="onchain-chain-distribution">
                            <!-- ⚡ Data akan di-populate via JavaScript -->
                        </div>
                    </div>

                    <!-- Portfolio Stats -->
                    <div class="clay-card p-4">
                        <h3 class="font-bold mb-3 flex items-center">
                            <i class="fas fa-chart-bar mr-2 text-warning"></i>
                            Portfolio Statistics
                        </h3>
                        <div id="portfolio-stats" class="space-y-3">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ⚡ ENHANCED: Error State dengan troubleshooting info -->
    <div id="error-state" class="mb-8" style="display: none;">
        <div class="clay-card p-6">
            <div class="text-center py-8">
                <i class="fas fa-exclamation-triangle text-5xl text-yellow-500 mb-4"></i>
                <h3 class="text-xl font-bold mb-3">Gagal Memuat Data Onchain</h3>
                <p class="text-gray-600 mb-4" id="error-message">Tidak dapat terhubung ke blockchain API</p>
                <div class="text-sm text-gray-500 mb-6" id="error-details"></div>

                <!-- Troubleshooting Steps -->
                <div class="clay-card bg-yellow-50 p-4 mb-4 text-left max-w-md mx-auto">
                    <h4 class="font-bold mb-2 text-yellow-800">🔧 Troubleshooting:</h4>
                    <ul class="text-sm text-yellow-700 space-y-1">
                        <li>• Sistem fokus pada native tokens untuk speed (ETH, BNB, MATIC, AVAX)</li>
                        <li>• Coba refresh setelah 10 detik</li>
                        <li>• Pastikan koneksi internet stabil</li>
                        <li>• Wallet dengan banyak spam tokens butuh waktu filter</li>
                        <li>• USD value calculation memerlukan CoinGecko API</li>
                    </ul>
                </div>

                <div class="flex justify-center space-x-3">
                    <button onclick="loadOnchainData()" class="clay-button clay-button-primary">
                        <i class="fas fa-retry mr-2"></i> Coba Lagi
                    </button>
                    <button onclick="checkApiStatus()" class="clay-button clay-button-info">
                        <i class="fas fa-heartbeat mr-2"></i> Cek Status API
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Manual Portfolio (Transaction Management) -->
    <div class="mb-8">
        <div class="clay-card p-6">
            <h2 class="text-2xl font-bold mb-4 flex flex-wrap items-center">
                <div class="bg-warning/20 p-2 rounded-lg mr-3">
                    <i class="fas fa-edit text-warning"></i>
                </div>
                Manual Portfolio (Transaction Management)
                <span class="lg:ml-3 clay-badge clay-badge-warning text-xs break-all">MANUAL</span>
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="clay-card bg-primary/10 p-4">
                    <div class="text-gray-600 text-sm">Total Value (Manual)</div>
                    <div class="text-2xl font-bold break-all">${{ number_format($manualTotalValue, 8) }}</div>
                </div>
                <div class="clay-card bg-secondary/10 p-4">
                    <div class="text-gray-600 text-sm">Total Cost</div>
                    <div class="text-2xl font-bold break-all">${{ number_format($manualTotalCost, 8) }}</div>
                </div>
                <div class="clay-card bg-{{ $manualProfitLoss >= 0 ? 'success' : 'danger' }}/10 p-4">
                    <div class="text-gray-600 text-sm">Profit/Loss</div>
                    <div class="text-2xl font-bold {{ $manualProfitLoss >= 0 ? 'text-success' : 'text-danger' }} break-all">
                        {{ $manualProfitLoss >= 0 ? '+' : '' }}${{ number_format($manualProfitLoss, 8) }}
                    </div>
                </div>
                <div class="clay-card bg-info/10 p-4">
                    <div class="text-gray-600 text-sm">ROI</div>
                    <div class="text-2xl font-bold {{ $manualProfitLoss >= 0 ? 'text-success' : 'text-danger' }} break-all">
                        {{ $manualProfitLoss >= 0 ? '+' : '' }}{{ number_format($manualProfitLossPercentage, 2) }}%
                    </div>
                </div>
            </div>

            @if(count($manualPortfolios) > 0)
            <div class="overflow-x-auto mb-4">
                <table class="clay-table min-w-full">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 text-left">Asset</th>
                            <th class="py-2 px-4 text-left">Amount</th>
                            <th class="py-2 px-4 text-left">Avg Price</th>
                            <th class="py-2 px-4 text-left">Current Price</th>
                            <th class="py-2 px-4 text-left">Total Value</th>
                            <th class="py-2 px-4 text-left">P/L</th>
                            <th class="py-2 px-4 text-left">Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($manualPortfolios as $portfolio)
                        <tr class="border-l-4 border-orange-500">
                            <td class="py-3 px-4">
                                <div class="flex items-center">
                                    @if($portfolio->project->image)
                                        <img src="{{ $portfolio->project->image }}" alt="{{ $portfolio->project->symbol }}" class="w-8 h-8 rounded-full mr-3">
                                    @endif
                                    <div>
                                        <div class="font-medium">{{ $portfolio->project->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $portfolio->project->symbol }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4 font-medium">{{ number_format($portfolio->amount, 8) }}</td>
                            <td class="py-3 px-4">${{ number_format($portfolio->average_buy_price, 8) }}</td>
                            <td class="py-3 px-4">${{ number_format($portfolio->project->current_price, 8) }}</td>
                            <td class="py-3 px-4 font-medium">${{ number_format($portfolio->current_value, 8) }}</td>
                            <td class="py-3 px-4 {{ $portfolio->profit_loss_value >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $portfolio->profit_loss_value >= 0 ? '+' : '' }}${{ number_format($portfolio->profit_loss_value, 8) }}
                                <div class="text-xs">{{ number_format($portfolio->profit_loss_percentage, 2) }}%</div>
                            </td>
                            <td class="py-3 px-4">
                                @if($portfolio->project->price_change_percentage_24h)
                                    <span class="clay-badge clay-badge-{{ $portfolio->project->price_change_percentage_24h >= 0 ? 'success' : 'danger' }} text-xs">
                                        {{ $portfolio->project->price_change_percentage_24h >= 0 ? '+' : '' }}{{ number_format($portfolio->project->price_change_percentage_24h, 2) }}%
                                    </span>
                                @else
                                    <span class="text-gray-400 text-xs">N/A</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-8">
                <i class="fas fa-chart-line text-4xl text-gray-400 mb-3"></i>
                <p class="text-gray-500 mb-3">Belum ada data transaksi manual</p>
                <a href="{{ route('panel.portfolio.transaction-management') }}" class="clay-button clay-button-warning">
                    <i class="fas fa-plus mr-2"></i> Mulai Catat Transaksi
                </a>
            </div>
            @endif

            <div class="mt-6 flex justify-center">
                <a href="{{ route('panel.portfolio.transaction-management') }}" class="clay-button clay-button-warning">
                    <i class="fas fa-exchange-alt mr-2"></i> Kelola Transaksi Manual
                </a>
            </div>
        </div>
    </div>

    <!-- Portfolio Insights -->
    <div class="clay-card p-6 mb-8" id="portfolio-insights" style="display: none;">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-lightbulb mr-2 text-warning"></i>
            Portfolio Insights
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4" id="insights-container">
            <!-- Insights will be populated by JavaScript -->
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="clay-card p-6">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-bolt mr-2 text-primary"></i>
            Quick Actions
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <a href="{{ route('panel.portfolio.transaction-management') }}" class="clay-card bg-warning/10 p-4 hover:bg-warning/20 transition-colors">
                <div class="text-center">
                    <i class="fas fa-plus-circle text-warning text-2xl mb-2"></i>
                    <div class="font-medium">Add Transaction</div>
                    <div class="text-xs text-gray-500">Manual entry</div>
                </div>
            </a>

            <a href="{{ route('panel.portfolio.onchain-analytics') }}" class="clay-card bg-success/10 p-4 hover:bg-success/20 transition-colors">
                <div class="text-center">
                    <i class="fas fa-chart-line text-success text-2xl mb-2"></i>
                    <div class="font-medium">Onchain Analytics</div>
                    <div class="text-xs text-gray-500">Deep analysis</div>
                </div>
            </a>

            <button onclick="refreshOnchainData()" class="clay-card bg-primary/10 p-4 hover:bg-primary/20 transition-colors">
                <div class="text-center">
                    <i class="fas fa-sync-alt text-primary text-2xl mb-2"></i>
                    <div class="font-medium">Refresh Data</div>
                    <div class="text-xs text-gray-500">Update onchain</div>
                </div>
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    let onchainData = null;
    let retryCount = 0;
    const maxRetries = 3;

    // ⚡ Enhanced: Pagination and filtering variables
    let allHoldingsData = [];
    let filteredHoldingsData = [];
    let holdingsPage = 1;
    const HOLDINGS_PER_PAGE = 10;
    let currentFilter = 'all';
    let currentSort = 'value_desc';

    // ⚡ Load data saat halaman ready
    document.addEventListener('DOMContentLoaded', function() {
        checkApiStatus();
        loadOnchainData();
    });

    // ⚡ Enhanced: Check API status dengan comprehensive checks
    async function checkApiStatus() {
        const indicator = document.getElementById('api-status-indicator');
        const statusText = document.getElementById('api-status-text');
        const optimizationInfo = document.getElementById('api-optimization-info');

        try {
            const apiUrl = '{{ $apiUrl ?? "http://localhost:8001" }}';
            const response = await fetch(apiUrl + '/health', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                },
                timeout: 5000
            });

            if (response.ok) {
                const data = await response.json();

                // Update status
                indicator.className = 'w-3 h-3 rounded-full mr-3 bg-green-500';
                statusText.textContent = 'Connected';

                // Show optimization info
                if (data.optimization_status) {
                    const optimizations = [];
                    if (data.optimization_status.native_token_focus === 'enabled') {
                        optimizations.push('Native Focus');
                    }
                    if (data.optimization_status.spam_detection === 'enhanced') {
                        optimizations.push('Spam Filter');
                    }
                    if (data.optimization_status.usd_volume_calculation === 'comprehensive') {
                        optimizations.push('USD Calc');
                    }

                    optimizationInfo.textContent = optimizations.join(' • ');
                }

                console.log('⚡ API STATUS: Connected with optimizations:', data.optimization_status);
            } else {
                throw new Error(`API returned status ${response.status}`);
            }
        } catch (error) {
            indicator.className = 'w-3 h-3 rounded-full mr-3 bg-red-500';
            statusText.textContent = 'API Unavailable';
            optimizationInfo.textContent = 'Offline mode';
            console.warn('⚡ API STATUS: Unavailable -', error.message);
        }
    }

    // ⚡ Enhanced: Function untuk load onchain data dengan comprehensive error handling
    async function loadOnchainData() {
        const loadingState = document.getElementById('loading-state');
        const onchainSection = document.getElementById('onchain-portfolio-section');
        const errorState = document.getElementById('error-state');

        // Show loading
        loadingState.style.display = 'block';
        onchainSection.style.display = 'none';
        errorState.style.display = 'none';

        try {
            console.log('⚡ PORTFOLIO: Loading onchain data with native focus...');

            const response = await fetch('{{ route('panel.portfolio.refresh-onchain') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            const data = await response.json();
            console.log('⚡ PORTFOLIO RESPONSE:', data);

            if (data.success && data.portfolio) {
                onchainData = data.portfolio;
                populateOnchainData(data.portfolio);

                // Show section
                loadingState.style.display = 'none';
                onchainSection.style.display = 'block';
                errorState.style.display = 'none';

                // Show insights
                generatePortfolioInsights(data.portfolio);

                retryCount = 0; // Reset retry count on success

                // Show optimization message
                let optimizationMsg = '⚡ Portfolio loaded';
                if (data.optimization && data.optimization.includes('native')) {
                    optimizationMsg += ' with native token focus (faster!)';
                }
                showNotification(optimizationMsg, 'success');

                console.log('⚡ PORTFOLIO: Successfully loaded with optimization:', data.optimization);
            } else {
                throw new Error(data.message || 'Failed to load portfolio data');
            }

        } catch (error) {
            console.error('⚡ ERROR: Loading onchain data:', error);

            // Show error state dengan detail yang lebih baik
            loadingState.style.display = 'none';
            onchainSection.style.display = 'none';
            errorState.style.display = 'block';

            // ⚡ Enhanced: Better error categorization
            let errorMessage = error.message;
            let troubleshootingText = 'Pastikan API blockchain sedang berjalan dan koneksi internet stabil.';

            if (error.message.includes('timeout') || error.message.includes('timed out')) {
                errorMessage = 'Request timeout - API blockchain sedang lambat';
                troubleshootingText = 'Sistem sedang memproses native tokens. Coba lagi dalam 10 detik.';
            } else if (error.message.includes('503') || error.message.includes('500')) {
                errorMessage = 'API blockchain sedang tidak tersedia';
                troubleshootingText = 'Service sedang maintenance atau overload. Coba lagi dalam beberapa menit.';
            } else if (error.message.includes('network')) {
                errorMessage = 'Masalah koneksi jaringan';
                troubleshootingText = 'Periksa koneksi internet dan coba lagi.';
            }

            document.getElementById('error-message').textContent = errorMessage;
            document.getElementById('error-details').textContent = troubleshootingText;
        }
    }

    // ⚡ Enhanced: Populate onchain data dengan native token prioritization
    function populateOnchainData(portfolio) {
        // Update summary cards dengan 8 decimal precision
        document.getElementById('onchain-total-value').textContent = `$${numberFormat(portfolio.total_usd_value || 0, 8)}`;

        // Count only non-spam assets
        const validNative = (portfolio.native_balances || []).length;
        const validTokens = (portfolio.token_balances || []).filter(token => !token.is_spam).length;
        const totalValidAssets = validNative + validTokens;

        document.getElementById('onchain-asset-count').textContent = totalValidAssets;

        const chains = portfolio.chains_scanned?.join(', ') || 'Unknown';
        document.getElementById('onchain-chains').textContent = `Chains: ${chains}`;

        const lastUpdated = portfolio.last_updated ? new Date(portfolio.last_updated).toLocaleString('id-ID') : 'Never';
        document.getElementById('onchain-last-updated').textContent = `Last updated: ${lastUpdated}`;

        // Show spam filter badge if any tokens were filtered
        const spamBadge = document.getElementById('spam-filter-badge');
        if (portfolio.filtered_tokens_count && portfolio.filtered_tokens_count > 0) {
            spamBadge.textContent = `${portfolio.filtered_tokens_count} SPAM FILTERED`;
            spamBadge.style.display = 'inline-block';
        }

        // ⚡ Enhanced: Populate native tokens section
        populateNativeTokensSection(portfolio);

        // ⚡ Enhanced: Prepare holdings data dengan filtering dan sorting
        prepareHoldingsData(portfolio);

        // Populate distributions
        populateCategoryDistribution(portfolio);
        populateChainDistribution(portfolio);

        // Populate portfolio stats
        populatePortfolioStats(portfolio);
    }

    // ⚡ Enhanced: Populate native tokens section
    function populateNativeTokensSection(portfolio) {
        const nativeSection = document.getElementById('native-tokens-section');
        const container = document.getElementById('native-tokens-list');
        const countElement = document.getElementById('native-tokens-count');

        if (portfolio.native_balances && portfolio.native_balances.length > 0) {
            const nativeTokens = portfolio.native_balances.filter(token =>
                token.usd_value && token.usd_value > 0
            );

            if (nativeTokens.length > 0) {
                nativeSection.style.display = 'block';
                countElement.textContent = `${nativeTokens.length} native tokens`;

                let html = '';
                nativeTokens.forEach(token => {
                    const chainInfo = getChainDisplayInfo(token.chain);

                    html += `
                        <div class="clay-card bg-blue/10 p-3">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center">
                                    <i class="${chainInfo.icon} text-${chainInfo.color} mr-2"></i>
                                    <span class="font-bold">${token.token_symbol}</span>
                                </div>
                                <span class="clay-badge clay-badge-${chainInfo.color} text-xs">${token.chain.toUpperCase()}</span>
                            </div>
                            <div class="text-lg font-bold">$${numberFormat(token.usd_value, 8)}</div>
                            <div class="text-xs text-gray-500">${numberFormat(token.balance, 8)} ${token.token_symbol}</div>
                        </div>
                    `;
                });

                container.innerHTML = html;
            } else {
                nativeSection.style.display = 'none';
            }
        } else {
            nativeSection.style.display = 'none';
        }
    }

    // ⚡ Enhanced: Prepare holdings data dengan filtering and sorting
    function prepareHoldingsData(portfolio) {
        allHoldingsData = [];

        // Add native balances
        if (portfolio.native_balances && portfolio.native_balances.length > 0) {
            portfolio.native_balances.forEach(balance => {
                allHoldingsData.push({
                    type: 'native',
                    token_address: balance.token_address,
                    token_name: balance.token_name,
                    token_symbol: balance.token_symbol,
                    balance: balance.balance,
                    chain: balance.chain,
                    usd_value: balance.usd_value,
                    is_spam: false,
                    category: balance.project_data?.primary_category || 'Layer-1'
                });
            });
        }

        // Add token balances
        if (portfolio.token_balances && portfolio.token_balances.length > 0) {
            portfolio.token_balances.forEach(token => {
                allHoldingsData.push({
                    type: 'token',
                    token_address: token.token_address,
                    token_name: token.token_name,
                    token_symbol: token.token_symbol,
                    balance: token.balance,
                    chain: token.chain,
                    usd_value: token.usd_value,
                    is_spam: token.is_spam || false,
                    category: token.project_data?.primary_category || 'Other'
                });
            });
        }

        // Reset pagination and apply current filter/sort
        holdingsPage = 1;
        applyFilterAndSort();
    }

    // ⚡ Enhanced: Apply filter and sort
    function applyFilterAndSort() {
        // Apply filter
        switch (currentFilter) {
            case 'native':
                filteredHoldingsData = allHoldingsData.filter(item => item.type === 'native');
                break;
            case 'tokens':
                filteredHoldingsData = allHoldingsData.filter(item => item.type === 'token' && !item.is_spam);
                break;
            case 'valuable':
                filteredHoldingsData = allHoldingsData.filter(item =>
                    item.usd_value && item.usd_value > 0 && !item.is_spam
                );
                break;
            default:
                filteredHoldingsData = allHoldingsData.filter(item => !item.is_spam);
        }

        // Apply sort
        switch (currentSort) {
            case 'value_desc':
                filteredHoldingsData.sort((a, b) => (b.usd_value || 0) - (a.usd_value || 0));
                break;
            case 'value_asc':
                filteredHoldingsData.sort((a, b) => (a.usd_value || 0) - (b.usd_value || 0));
                break;
            case 'balance_desc':
                filteredHoldingsData.sort((a, b) => b.balance - a.balance);
                break;
            case 'name_asc':
                filteredHoldingsData.sort((a, b) => (a.token_name || '').localeCompare(b.token_name || ''));
                break;
        }

        populateHoldingsTablePaginated();
    }

    // ⚡ Filter functions
    function filterAssets() {
        const filterSelect = document.getElementById('asset-filter');
        currentFilter = filterSelect.value;
        holdingsPage = 1;
        applyFilterAndSort();
    }

    function sortAssets() {
        const sortSelect = document.getElementById('asset-sort');
        currentSort = sortSelect.value;
        holdingsPage = 1;
        applyFilterAndSort();
    }

    // ⚡ Enhanced: Populate holdings table dengan pagination
    function populateHoldingsTablePaginated() {
        const container = document.getElementById('onchain-holdings-table');
        const totalItems = filteredHoldingsData.length;
        const totalPages = Math.ceil(totalItems / HOLDINGS_PER_PAGE);

        // Update count dengan filter info
        const filterInfo = currentFilter === 'all' ? 'all assets' :
                          currentFilter === 'native' ? 'native tokens' :
                          currentFilter === 'tokens' ? 'alt tokens' :
                          'valuable assets';
        document.getElementById('assets-count').textContent = `${totalItems} ${filterInfo}`;

        if (totalItems > 0) {
            const startIndex = (holdingsPage - 1) * HOLDINGS_PER_PAGE;
            const endIndex = Math.min(startIndex + HOLDINGS_PER_PAGE, totalItems);
            const pageItems = filteredHoldingsData.slice(startIndex, endIndex);

            let html = '';

            pageItems.forEach(item => {
                // Handle USD value display
                let usdValue = 'N/A';
                if (item.usd_value !== null && item.usd_value !== undefined) {
                    if (item.usd_value > 0) {
                        usdValue = `$${numberFormat(item.usd_value, 8)}`;
                    } else if (item.usd_value === 0) {
                        usdValue = '$0.00000000';
                    }
                }

                const isSpam = item.is_spam || false;
                const rowClass = isSpam ? 'opacity-50 border-l-4 border-red-500' :
                                item.type === 'native' ? 'border-l-4 border-blue-500' : '';

                const statusBadge = isSpam ? '<span class="clay-badge clay-badge-danger">Spam</span>' :
                                   item.type === 'native' ? '<span class="clay-badge clay-badge-success">Native</span>' :
                                   '<span class="clay-badge clay-badge-secondary">Token</span>';

                const chainInfo = getChainDisplayInfo(item.chain);

                html += `
                    <tr class="${rowClass}">
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                <div class="w-8 h-8 ${item.type === 'native' ? 'bg-blue-500' : 'bg-gray-300'} rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-coins ${item.type === 'native' ? 'text-white' : 'text-gray-600'} text-xs"></i>
                                </div>
                                <div>
                                    <div class="font-medium ${isSpam ? 'line-through' : ''}">${item.token_name || item.token_symbol}</div>
                                    <div class="text-xs text-gray-500">${item.token_symbol}</div>
                                    ${item.category ? `<div class="text-xs text-gray-400">${item.category}</div>` : ''}
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4 font-medium">${numberFormat(item.balance, 8)}</td>
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                <i class="${chainInfo.icon} text-${chainInfo.color} mr-1 text-xs"></i>
                                <span class="clay-badge clay-badge-${chainInfo.color} text-xs">${item.chain.toUpperCase()}</span>
                            </div>
                        </td>
                        <td class="py-3 px-4 font-medium">${usdValue}</td>
                        <td class="py-3 px-4">${statusBadge}</td>
                        <td class="py-3 px-4">
                            <button class="clay-badge clay-badge-primary py-1 px-2 text-xs" onclick="viewOnExplorer('${item.chain}', '${item.token_address}')">
                                <i class="fas fa-external-link-alt"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            container.innerHTML = html;

            // Update pagination
            if (totalPages > 1) {
                updateHoldingsPagination(totalPages, startIndex + 1, endIndex);
                document.getElementById('holdings-pagination').style.display = 'flex';
            } else {
                document.getElementById('holdings-page-info').textContent = `Showing all ${totalItems} ${filterInfo}`;
                document.getElementById('holdings-pagination').style.display = 'none';
            }
        } else {
            container.innerHTML = `
                <tr>
                    <td colspan="6" class="py-6 px-4 text-center">
                        <div class="text-gray-500">
                            <i class="fas fa-filter text-4xl mb-3"></i>
                            <p>Tidak ada ${filterInfo} yang sesuai filter</p>
                            <p class="text-sm">Coba ubah filter atau sort untuk melihat data lain</p>
                        </div>
                    </td>
                </tr>
            `;
            document.getElementById('holdings-pagination').style.display = 'none';
        }
    }

    // ⚡ Holdings pagination functions
    function updateHoldingsPagination(totalPages, startItem, endItem) {
        document.getElementById('holdings-page-info').textContent =
            `Showing ${startItem} to ${endItem} of ${filteredHoldingsData.length} assets`;

        document.getElementById('holdings-prev').disabled = holdingsPage <= 1;
        document.getElementById('holdings-next').disabled = holdingsPage >= totalPages;
        document.getElementById('holdings-prev').style.opacity = holdingsPage <= 1 ? '0.5' : '1';
        document.getElementById('holdings-next').style.opacity = holdingsPage >= totalPages ? '0.5' : '1';

        const pageNumbersDiv = document.getElementById('holdings-page-numbers');
        let html = '';

        const startPage = Math.max(1, holdingsPage - 2);
        const endPage = Math.min(totalPages, holdingsPage + 2);

        for (let i = startPage; i <= endPage; i++) {
            const isActive = i === holdingsPage;
            html += `
                <button onclick="goToHoldingsPage(${i})"
                       class="clay-button ${isActive ? 'clay-button-primary' : 'clay-button-secondary'} py-1.5 px-3 text-sm">
                    ${i}
                </button>
            `;
        }

        pageNumbersDiv.innerHTML = html;
    }

    function changeHoldingsPage(direction) {
        const totalPages = Math.ceil(filteredHoldingsData.length / HOLDINGS_PER_PAGE);
        const newPage = holdingsPage + direction;

        if (newPage >= 1 && newPage <= totalPages) {
            holdingsPage = newPage;
            populateHoldingsTablePaginated();
        }
    }

    function goToHoldingsPage(page) {
        const totalPages = Math.ceil(filteredHoldingsData.length / HOLDINGS_PER_PAGE);

        if (page >= 1 && page <= totalPages) {
            holdingsPage = page;
            populateHoldingsTablePaginated();
        }
    }

    // ⚡ Enhanced: Populate category distribution dengan spam filtering
    function populateCategoryDistribution(portfolio) {
        const container = document.getElementById('onchain-category-distribution');
        const totalValue = portfolio.total_usd_value || 0;

        let categories = {};

        // Process semua holdings data
        allHoldingsData.forEach(item => {
            if (!item.is_spam && item.usd_value && item.usd_value > 0) {
                const category = item.category || 'Other';

                if (!categories[category]) {
                    categories[category] = {
                        category: category,
                        value: 0,
                        count: 0
                    };
                }

                categories[category].value += item.usd_value;
                categories[category].count++;
            }
        });

        const categoryArray = Object.values(categories);

        if (categoryArray.length > 0) {
            let html = '<div class="space-y-3">';

            categoryArray.forEach(category => {
                const percentage = totalValue > 0 ? (category.value / totalValue) * 100 : 0;

                html += `
                    <div class="clay-card bg-secondary/5 p-3">
                        <div class="flex justify-between mb-1">
                            <span class="font-medium text-sm">${category.category}</span>
                            <span class="text-sm">$${numberFormat(category.value, 4)}</span>
                        </div>
                        <div class="clay-progress h-2">
                            <div class="clay-progress-bar clay-progress-secondary" style="width: ${percentage}%"></div>
                        </div>
                        <div class="text-xs text-right mt-1">${category.count} assets (${percentage.toFixed(1)}%)</div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div class="text-center py-4">
                    <p class="text-gray-500 text-sm">Menunggu data dengan USD value</p>
                </div>
            `;
        }
    }

    // ⚡ Enhanced: Populate chain distribution
    function populateChainDistribution(portfolio) {
        const container = document.getElementById('onchain-chain-distribution');
        const totalValue = portfolio.total_usd_value || 0;

        let chains = {};

        // Process semua holdings data
        allHoldingsData.forEach(item => {
            if (!item.is_spam && item.usd_value && item.usd_value > 0) {
                const chain = item.chain || 'Unknown';

                if (!chains[chain]) {
                    chains[chain] = {
                        chain: chain,
                        value: 0,
                        count: 0
                    };
                }

                chains[chain].value += item.usd_value;
                chains[chain].count++;
            }
        });

        const chainArray = Object.values(chains);

        if (chainArray.length > 0) {
            let html = '<div class="space-y-3">';

            chainArray.forEach(chain => {
                const percentage = totalValue > 0 ? (chain.value / totalValue) * 100 : 0;
                const chainInfo = getChainDisplayInfo(chain.chain);

                html += `
                    <div class="clay-card bg-info/5 p-3">
                        <div class="flex justify-between mb-2">
                            <div class="flex items-center">
                                <i class="${chainInfo.icon} text-${chainInfo.color} mr-2"></i>
                                <span class="font-medium text-sm">${chainInfo.name}</span>
                            </div>
                            <span class="text-sm">$${numberFormat(chain.value, 4)}</span>
                        </div>
                        <div style="width: 100%; height: 12px; background-color: #e5e7eb; border-radius: 6px; overflow: hidden; margin-bottom: 8px;">
                            <div style="height: 100%; background: ${chainInfo.gradient}; border-radius: 6px; width: ${percentage}%; transition: width 0.3s ease-in-out;"></div>
                        </div>
                        <div class="text-xs text-right">${chain.count} assets (${percentage.toFixed(1)}%)</div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div class="text-center py-4">
                    <p class="text-gray-500 text-sm">Menunggu data dengan USD value</p>
                </div>
            `;
        }
    }

    // ⚡ New: Populate portfolio stats
    function populatePortfolioStats(portfolio) {
        const container = document.getElementById('portfolio-stats');

        const totalAssets = allHoldingsData.filter(item => !item.is_spam).length;
        const nativeAssets = allHoldingsData.filter(item => item.type === 'native').length;
        const valuableAssets = allHoldingsData.filter(item =>
            !item.is_spam && item.usd_value && item.usd_value > 0
        ).length;
        const spamFiltered = portfolio.filtered_tokens_count || 0;

        let html = `
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600">Total Assets</span>
                    <span class="font-medium">${totalAssets}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600">Native Tokens</span>
                    <span class="font-medium text-blue-600">${nativeAssets}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600">With USD Value</span>
                    <span class="font-medium text-green-600">${valuableAssets}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600">Spam Filtered</span>
                    <span class="font-medium text-red-600">${spamFiltered}</span>
                </div>
            </div>
        `;

        container.innerHTML = html;
    }

    // ⚡ Enhanced: Generate portfolio insights
    function generatePortfolioInsights(portfolio) {
        const insightsSection = document.getElementById('portfolio-insights');
        const container = document.getElementById('insights-container');

        const totalValue = portfolio.total_usd_value || 0;
        const nativeCount = (portfolio.native_balances || []).length;
        const tokenCount = (portfolio.token_balances || []).filter(t => !t.is_spam).length;
        const chainsCount = (portfolio.chains_scanned || []).length;

        let insights = [];

        // Diversification insight
        if (chainsCount >= 3 && tokenCount >= 5) {
            insights.push({
                icon: 'fas fa-chart-pie',
                title: '🌟 Excellent Diversification',
                message: `Portfolio spread across ${chainsCount} chains with ${tokenCount + nativeCount} assets. Great risk management!`,
                type: 'success'
            });
        } else if (chainsCount >= 2) {
            insights.push({
                icon: 'fas fa-expand-arrows-alt',
                title: '📈 Good Multi-Chain Strategy',
                message: `Active on ${chainsCount} chains. Consider expanding to more networks for better opportunities.`,
                type: 'info'
            });
        } else {
            insights.push({
                icon: 'fas fa-link',
                title: '🔗 Single Chain Focus',
                message: `Currently focused on one chain. Multi-chain approach could provide better yields and opportunities.`,
                type: 'warning'
            });
        }

        // Value insight
        if (totalValue > 1000) {
            insights.push({
                icon: 'fas fa-dollar-sign',
                title: '💰 Significant Portfolio',
                message: `Portfolio value of $${numberFormat(totalValue, 2)} shows strong crypto allocation. Consider DeFi opportunities.`,
                type: 'success'
            });
        } else if (totalValue > 100) {
            insights.push({
                icon: 'fas fa-seedling',
                title: '🌱 Growing Portfolio',
                message: `Building momentum with $${numberFormat(totalValue, 2)}. Continue DCA strategy for long-term growth.`,
                type: 'info'
            });
        } else {
            insights.push({
                icon: 'fas fa-rocket',
                title: '🚀 Early Stage',
                message: `Starting journey with $${numberFormat(totalValue, 2)}. Focus on learning and gradual accumulation.`,
                type: 'warning'
            });
        }

        // Native token insight
        if (nativeCount >= 3) {
            insights.push({
                icon: 'fas fa-star',
                title: '⭐ Native Token Champion',
                message: `Holding ${nativeCount} native tokens shows smart ecosystem participation. Great for staking rewards!`,
                type: 'success'
            });
        }

        if (insights.length > 0) {
            let html = '';
            insights.forEach(insight => {
                html += `
                    <div class="clay-card bg-${insight.type}/10 p-4">
                        <h3 class="font-bold mb-2 flex items-center">
                            <i class="${insight.icon} mr-2 text-${insight.type}"></i>
                            ${insight.title}
                        </h3>
                        <p class="text-sm">${insight.message}</p>
                    </div>
                `;
            });

            container.innerHTML = html;
            insightsSection.style.display = 'block';
        }
    }

    // Helper functions
    function getChainDisplayInfo(chain) {
        const chainMap = {
            'ethereum': { name: 'Ethereum', icon: 'fab fa-ethereum', color: 'blue', gradient: 'linear-gradient(135deg, #627eea, #4c68d7)' },
            'eth': { name: 'Ethereum', icon: 'fab fa-ethereum', color: 'blue', gradient: 'linear-gradient(135deg, #627eea, #4c68d7)' },
            'bsc': { name: 'BSC', icon: 'fas fa-coins', color: 'yellow', gradient: 'linear-gradient(135deg, #f0b90b, #d49c06)' },
            'polygon': { name: 'Polygon', icon: 'fas fa-project-diagram', color: 'purple', gradient: 'linear-gradient(135deg, #8247e5, #6c38cc)' },
            'avalanche': { name: 'Avalanche', icon: 'fas fa-mountain', color: 'red', gradient: 'linear-gradient(135deg, #e84142, #d73334)' }
        };

        return chainMap[chain.toLowerCase()] || {
            name: chain.charAt(0).toUpperCase() + chain.slice(1),
            icon: 'fas fa-link',
            color: 'gray',
            gradient: 'linear-gradient(135deg, #6b7280, #4b5563)'
        };
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            showNotification('Wallet address copied to clipboard!', 'success');
        });
    }

    async function refreshOnchainData() {
        const btn = document.getElementById('refresh-btn');
        const originalText = btn.innerHTML;

        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Refreshing...';
        btn.disabled = true;

        try {
            await loadOnchainData();
            showNotification('⚡ Data onchain berhasil diperbarui dengan native focus!', 'success');
        } catch (error) {
            showNotification('Gagal memperbarui data: ' + error.message, 'error');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    function viewOnExplorer(chain, address) {
        const explorers = {
            'eth': 'https://etherscan.io',
            'ethereum': 'https://etherscan.io',
            'bsc': 'https://bscscan.com',
            'binance_smart_chain': 'https://bscscan.com',
            'polygon': 'https://polygonscan.com',
            'avalanche': 'https://snowtrace.io'
        };

        const explorerUrl = explorers[chain.toLowerCase()];
        if (explorerUrl) {
            window.open(`${explorerUrl}/address/${address}`, '_blank');
        } else {
            showNotification('Explorer not available for this chain', 'warning');
        }
    }

    function numberFormat(number, decimals = 8) {
        if (number === null || number === undefined || isNaN(number)) {
            return '0.' + '0'.repeat(decimals);
        }

        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    }

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

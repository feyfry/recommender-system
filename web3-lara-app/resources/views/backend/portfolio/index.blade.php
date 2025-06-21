@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
            <div>
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
            <div class="mt-4 md:mt-0 flex space-x-3">
                <button type="button" onclick="refreshOnchainData()" class="clay-button clay-button-info" id="refresh-btn">
                    <i class="fas fa-sync-alt mr-2"></i> Refresh Onchain
                </button>
                <a href="{{ route('panel.portfolio.transaction-management') }}" class="clay-button clay-button-primary">
                    <i class="fas fa-exchange-alt mr-2"></i> Transaction Management
                </a>
                <a href="{{ route('panel.portfolio.onchain-analytics') }}" class="clay-button clay-button-secondary">
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

    <!-- ⚡ ENHANCED: Loading State with Better Skeleton -->
    <div id="loading-state" class="mb-8">
        <div class="clay-card p-6">
            <div class="animate-pulse">
                <div class="flex items-center justify-center py-12">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-4 border-primary mr-4"></div>
                    <div>
                        <div class="text-lg font-medium mb-2">Loading onchain portfolio data...</div>
                        <div class="text-sm text-gray-500">Mengambil data dari blockchain API (mungkin butuh 1-2 menit)</div>
                        <div class="text-xs text-gray-400 mt-1">Proses: Scanning multi-chain → Filtering spam → Calculating USD values</div>
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
            <h2 class="text-2xl font-bold mb-4 flex items-center">
                <div class="bg-primary/20 p-2 rounded-lg mr-3">
                    <i class="fas fa-link text-primary"></i>
                </div>
                Real Portfolio (Onchain Data)
                <span class="ml-3 clay-badge clay-badge-success text-xs">LIVE</span>
                <span id="spam-filter-badge" class="ml-2 clay-badge clay-badge-warning text-xs" style="display: none;">
                    SPAM FILTERED
                </span>
            </h2>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Summary Cards -->
                <div class="lg:col-span-2">
                    <div class="grid grid-cols-2 gap-4 mb-6">
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

                    <!-- Onchain Holdings Table -->
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
                </div>

                <!-- Onchain Distribution -->
                <div class="lg:col-span-1">
                    <!-- Category Distribution -->
                    <div class="clay-card p-4 mb-6">
                        <h3 class="font-bold mb-3 flex items-center">
                            <i class="fas fa-chart-pie mr-2 text-secondary"></i>
                            Distribusi Kategori (Onchain)
                        </h3>
                        <div id="onchain-category-distribution">
                            <!-- ⚡ Data akan di-populate via JavaScript -->
                        </div>
                    </div>

                    <!-- Chain Distribution -->
                    <div class="clay-card p-4">
                        <h3 class="font-bold mb-3 flex items-center">
                            <i class="fas fa-link mr-2 text-info"></i>
                            Distribusi Chain (Onchain)
                        </h3>
                        <div id="onchain-chain-distribution">
                            <!-- ⚡ Data akan di-populate via JavaScript -->
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
                        <li>• API blockchain mungkin sedang lambat (1-2 menit normal)</li>
                        <li>• Coba refresh setelah 30 detik</li>
                        <li>• Pastikan koneksi internet stabil</li>
                        <li>• Wallet dengan banyak token butuh waktu lebih lama</li>
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
            <h2 class="text-2xl font-bold mb-4 flex items-center">
                <div class="bg-warning/20 p-2 rounded-lg mr-3">
                    <i class="fas fa-edit text-warning"></i>
                </div>
                Manual Portfolio (Transaction Management)
                <span class="ml-3 clay-badge clay-badge-warning text-xs">MANUAL</span>
            </h2>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="clay-card bg-primary/10 p-4">
                    <div class="text-gray-600 text-sm">Total Value (Manual)</div>
                    <div class="text-2xl font-bold">${{ number_format($manualTotalValue, 8) }}</div>
                </div>
                <div class="clay-card bg-{{ $manualProfitLoss >= 0 ? 'success' : 'danger' }}/10 p-4">
                    <div class="text-gray-600 text-sm">Profit/Loss (Manual)</div>
                    <div class="text-2xl font-bold {{ $manualProfitLoss >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $manualProfitLoss >= 0 ? '+' : '' }}${{ number_format($manualProfitLoss, 8) }}
                        <span class="text-sm">({{ number_format($manualProfitLossPercentage, 2) }}%)</span>
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

    <!-- Quick Actions -->
    <div class="clay-card p-6">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-bolt mr-2 text-primary"></i>
            Quick Actions
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <a href="{{ route('panel.portfolio.transaction-management') }}" class="clay-card bg-warning/10 p-4 hover:bg-warning/20 transition-colors">
                <div class="text-center">
                    <i class="fas fa-plus-circle text-warning text-2xl mb-2"></i>
                    <div class="font-medium">Add Transaction</div>
                    <div class="text-xs text-gray-500">Manual entry</div>
                </div>
            </a>

            <a href="{{ route('panel.portfolio.price-alerts') }}" class="clay-card bg-info/10 p-4 hover:bg-info/20 transition-colors">
                <div class="text-center">
                    <i class="fas fa-bell text-info text-2xl mb-2"></i>
                    <div class="font-medium">Price Alerts</div>
                    <div class="text-xs text-gray-500">Set notifications</div>
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

    // ⚡ Load onchain data saat halaman ready
    document.addEventListener('DOMContentLoaded', function() {
        loadOnchainData();
    });

    // ⚡ ENHANCED: Function untuk load onchain data dengan better error handling
    async function loadOnchainData() {
        const loadingState = document.getElementById('loading-state');
        const onchainSection = document.getElementById('onchain-portfolio-section');
        const errorState = document.getElementById('error-state');

        // Show loading
        loadingState.style.display = 'block';
        onchainSection.style.display = 'none';
        errorState.style.display = 'none';

        try {
            const response = await fetch('{{ route('panel.portfolio.refresh-onchain') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            const data = await response.json();

            if (data.success && data.portfolio) {
                onchainData = data.portfolio;
                populateOnchainData(data.portfolio);

                // Show section
                loadingState.style.display = 'none';
                onchainSection.style.display = 'block';
                errorState.style.display = 'none';

                retryCount = 0; // Reset retry count on success
            } else {
                throw new Error(data.message || 'Failed to load portfolio data');
            }

        } catch (error) {
            console.error('Error loading onchain data:', error);

            // Show error state dengan detail yang lebih baik
            loadingState.style.display = 'none';
            onchainSection.style.display = 'none';
            errorState.style.display = 'block';

            // ⚡ ENHANCED: Better error categorization
            let errorMessage = error.message;
            let troubleshootingText = 'Pastikan API blockchain sedang berjalan dan koneksi internet stabil.';

            if (error.message.includes('timeout') || error.message.includes('timed out')) {
                errorMessage = 'Request timeout - API blockchain sedang lambat';
                troubleshootingText = 'Wallet dengan banyak tokens membutuhkan waktu lebih lama. Coba lagi dalam 30 detik.';
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

    // ⚡ Check API status
    async function checkApiStatus() {
        try {
            showNotification('Mengecek status API...', 'info');

            const apiUrl = '{{ $apiUrl ?? "http://localhost:8001" }}'; // ⚡ FIXED: Fallback untuk apiUrl
            const response = await fetch(apiUrl + '/health', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                showNotification('API blockchain tersedia dan berjalan normal', 'success');
            } else {
                showNotification('API blockchain tidak merespons dengan benar', 'warning');
            }
        } catch (error) {
            showNotification('Tidak dapat menghubungi API blockchain', 'error');
        }
    }

    // ⚡ ENHANCED: Function untuk populate data dengan 8 decimal precision dan spam filtering
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

        // Populate holdings table
        populateHoldingsTable(portfolio);

        // Populate distributions
        populateCategoryDistribution(portfolio);
        populateChainDistribution(portfolio);
    }

    // ⚡ ENHANCED: Populate holdings table dengan spam detection dan 8 decimal precision
    function populateHoldingsTable(portfolio) {
        const tableBody = document.getElementById('onchain-holdings-table');
        let html = '';

        // Native balances
        if (portfolio.native_balances && portfolio.native_balances.length > 0) {
            portfolio.native_balances.forEach(balance => {
                const usdValue = balance.usd_value ? `$${numberFormat(balance.usd_value, 8)}` : 'Calculating...';

                html += `
                    <tr class="border-l-4 border-blue-500">
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-coins text-white text-xs"></i>
                                </div>
                                <div>
                                    <div class="font-medium">${balance.token_name}</div>
                                    <div class="text-xs text-gray-500">${balance.token_symbol}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4 font-medium">${numberFormat(balance.balance, 8)}</td>
                        <td class="py-3 px-4">
                            <span class="clay-badge clay-badge-info">${balance.chain.toUpperCase()}</span>
                        </td>
                        <td class="py-3 px-4 font-medium">${usdValue}</td>
                        <td class="py-3 px-4">
                            <span class="clay-badge clay-badge-success">Native</span>
                        </td>
                        <td class="py-3 px-4">
                            <button class="clay-badge clay-badge-primary py-1 px-2 text-xs" onclick="viewOnExplorer('${balance.chain}', '{{ $walletAddress }}')">
                                <i class="fas fa-external-link-alt"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        }

        // Token balances dengan spam filtering
        if (portfolio.token_balances && portfolio.token_balances.length > 0) {
            // Sort: Non-spam first, then by USD value
            const sortedTokens = portfolio.token_balances.sort((a, b) => {
                if (a.is_spam && !b.is_spam) return 1;
                if (!a.is_spam && b.is_spam) return -1;
                return (b.usd_value || 0) - (a.usd_value || 0);
            });

            sortedTokens.forEach(token => {
                const usdValue = token.usd_value ? `$${numberFormat(token.usd_value, 8)}` : 'N/A';
                const isSpam = token.is_spam || false;

                // Skip spam tokens in main display, or show them grayed out
                const rowClass = isSpam ? 'opacity-50 border-l-4 border-red-500' : '';
                const statusBadge = isSpam
                    ? '<span class="clay-badge clay-badge-danger">Spam</span>'
                    : '<span class="clay-badge clay-badge-secondary">Token</span>';

                // Only show non-spam tokens or first 5 spam tokens for reference
                if (!isSpam || html.split('border-red-500').length <= 5) {
                    html += `
                        <tr class="${rowClass}">
                            <td class="py-3 px-4">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                                        <i class="fas fa-coins text-gray-600 text-xs"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium ${isSpam ? 'line-through' : ''}">${token.token_name || token.token_symbol}</div>
                                        <div class="text-xs text-gray-500">${token.token_symbol}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4 font-medium">${numberFormat(token.balance, 8)}</td>
                            <td class="py-3 px-4">
                                <span class="clay-badge clay-badge-secondary">${token.chain.toUpperCase()}</span>
                            </td>
                            <td class="py-3 px-4 font-medium">${usdValue}</td>
                            <td class="py-3 px-4">${statusBadge}</td>
                            <td class="py-3 px-4">
                                <button class="clay-badge clay-badge-primary py-1 px-2 text-xs" onclick="viewOnExplorer('${token.chain}', '${token.token_address}')">
                                    <i class="fas fa-external-link-alt"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                }
            });
        }

        if (html === '') {
            html = `
                <tr>
                    <td colspan="6" class="py-6 px-4 text-center">
                        <div class="text-gray-500">
                            <i class="fas fa-wallet text-4xl mb-3"></i>
                            <p>Tidak ada token ditemukan di wallet ini</p>
                            <p class="text-sm">Pastikan wallet address sudah benar dan memiliki balance</p>
                        </div>
                    </td>
                </tr>
            `;
        }

        tableBody.innerHTML = html;
    }

    // ⚡ ENHANCED: Populate category distribution dengan filtering spam
    function populateCategoryDistribution(portfolio) {
        const container = document.getElementById('onchain-category-distribution');
        const totalValue = portfolio.total_usd_value || 0;

        // Calculate category distribution dari enriched project data (exclude spam)
        let categories = {};

        // Process native balances
        if (portfolio.native_balances) {
            portfolio.native_balances.forEach(balance => {
                if (balance.usd_value && balance.usd_value > 0) {
                    const category = balance.project_data?.primary_category || 'Layer-1';

                    if (!categories[category]) {
                        categories[category] = {
                            primary_category: category,
                            value: 0,
                            project_count: 0
                        };
                    }

                    categories[category].value += balance.usd_value;
                    categories[category].project_count++;
                }
            });
        }

        // Process token balances (exclude spam)
        if (portfolio.token_balances) {
            portfolio.token_balances.forEach(token => {
                if (!token.is_spam && token.usd_value && token.usd_value > 0) {
                    const category = token.project_data?.primary_category || 'Other';

                    if (!categories[category]) {
                        categories[category] = {
                            primary_category: category,
                            value: 0,
                            project_count: 0
                        };
                    }

                    categories[category].value += token.usd_value;
                    categories[category].project_count++;
                }
            });
        }

        const categoryArray = Object.values(categories);

        if (categoryArray.length > 0) {
            let html = '<div class="space-y-3">';

            categoryArray.forEach(category => {
                const percentage = totalValue > 0 ? (category.value / totalValue) * 100 : 0;

                html += `
                    <div class="clay-card bg-secondary/5 p-3">
                        <div class="flex justify-between mb-1">
                            <span class="font-medium text-sm">${category.primary_category}</span>
                            <span class="text-sm">$${numberFormat(category.value, 8)}</span>
                        </div>
                        <div class="clay-progress h-2">
                            <div class="clay-progress-bar clay-progress-secondary" style="width: ${percentage}%"></div>
                        </div>
                        <div class="text-xs text-right mt-1">${category.project_count} assets (${percentage.toFixed(1)}%)</div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div class="text-center py-4">
                    <p class="text-gray-500 text-sm">Menunggu data dengan USD value</p>
                    <p class="text-xs text-gray-400 mt-1">Token akan muncul setelah harga berhasil diambil</p>
                </div>
            `;
        }
    }

    // ⚡ ENHANCED: Populate chain distribution dengan format yang benar dan 8 decimal
    function populateChainDistribution(portfolio) {
        const container = document.getElementById('onchain-chain-distribution');
        const totalValue = portfolio.total_usd_value || 0;

        // Calculate chain distribution (exclude spam)
        let chains = {};

        // Native balances
        if (portfolio.native_balances) {
            portfolio.native_balances.forEach(balance => {
                const chain = balance.chain || 'Unknown';
                const value = balance.usd_value || 0;

                if (!chains[chain]) {
                    chains[chain] = {
                        chain: chain,
                        value: 0,
                        project_count: 0,
                        balance_display: 0
                    };
                }

                chains[chain].value += value;
                chains[chain].project_count++;
                chains[chain].balance_display += balance.balance; // For display purposes
            });
        }

        // Token balances (exclude spam)
        if (portfolio.token_balances) {
            portfolio.token_balances.forEach(token => {
                if (!token.is_spam) {
                    const chain = token.chain || 'Unknown';
                    const value = token.usd_value || 0;

                    if (!chains[chain]) {
                        chains[chain] = {
                            chain: chain,
                            value: 0,
                            project_count: 0,
                            balance_display: 0
                        };
                    }

                    chains[chain].value += value;
                    chains[chain].project_count++;
                    chains[chain].balance_display += token.balance;
                }
            });
        }

        const chainArray = Object.values(chains);

        if (chainArray.length > 0) {
            let html = '<div class="space-y-3">';

            chainArray.forEach(chain => {
                const percentage = totalValue > 0 ? (chain.value / totalValue) * 100 : 0;
                const chainName = chain.chain.charAt(0).toUpperCase() + chain.chain.slice(1);

                html += `
                    <div class="clay-card bg-info/5 p-3">
                        <div class="flex justify-between mb-1">
                            <span class="font-medium text-sm">${chainName}</span>
                            <span class="text-sm">$${numberFormat(chain.value, 8)}</span>
                        </div>
                        <div class="clay-progress h-2">
                            <div class="clay-progress-bar clay-progress-info" style="width: ${Math.max(percentage, 1)}%"></div>
                        </div>
                        <div class="text-xs text-right mt-1">${chain.project_count} assets (${percentage.toFixed(1)}%)</div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div class="text-center py-4">
                    <p class="text-gray-500 text-sm">Menunggu data dengan USD value</p>
                    <p class="text-xs text-gray-400 mt-1">Chain akan muncul setelah harga berhasil diambil</p>
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

    // ⚡ ENHANCED: Refresh onchain data dengan retry logic
    async function refreshOnchainData() {
        const btn = document.getElementById('refresh-btn');
        const originalText = btn.innerHTML;

        // Show loading state
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Refreshing...';
        btn.disabled = true;

        try {
            await loadOnchainData();
            showNotification('Data onchain berhasil diperbarui!', 'success');
        } catch (error) {
            showNotification('Gagal memperbarui data: ' + error.message, 'error');
        } finally {
            // Restore button
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    // View token/transaction on blockchain explorer
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

    // ⚡ ENHANCED: Number formatting dengan 8 decimal precision
    function numberFormat(number, decimals = 8) {
        if (number === null || number === undefined || isNaN(number)) {
            return '0.' + '0'.repeat(decimals);
        }

        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    }

    // ⚡ ENHANCED: Notification system
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

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
</script>
@endpush
@endsection

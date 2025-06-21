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

    <!-- ⚡ BARU: Loading State -->
    <div id="loading-state" class="mb-8">
        <div class="clay-card p-6">
            <div class="flex items-center justify-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mr-3"></div>
                <span class="text-gray-600">Loading onchain portfolio data...</span>
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
            </h2>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Summary Cards -->
                <div class="lg:col-span-2">
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="clay-card bg-primary/10 p-4">
                            <div class="text-gray-600 text-sm">Total Value (Real)</div>
                            <div class="text-2xl font-bold" id="onchain-total-value">
                                $0.00
                            </div>
                            <div class="text-xs text-gray-500 mt-1" id="onchain-last-updated">
                                Loading...
                            </div>
                        </div>
                        <div class="clay-card bg-success/10 p-4">
                            <div class="text-gray-600 text-sm">Total Assets</div>
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

    <!-- ⚡ Error State -->
    <div id="error-state" class="mb-8" style="display: none;">
        <div class="clay-card p-6">
            <div class="text-center py-8">
                <i class="fas fa-exclamation-triangle text-4xl text-yellow-500 mb-3"></i>
                <h3 class="text-lg font-bold mb-2">Gagal Memuat Data Onchain</h3>
                <p class="text-gray-600 mb-4" id="error-message">Tidak dapat terhubung ke blockchain API</p>
                <button onclick="loadOnchainData()" class="clay-button clay-button-primary">
                    <i class="fas fa-retry mr-2"></i> Coba Lagi
                </button>
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
                    <div class="text-2xl font-bold">${{ number_format($manualTotalValue, 2) }}</div>
                </div>
                <div class="clay-card bg-{{ $manualProfitLoss >= 0 ? 'success' : 'danger' }}/10 p-4">
                    <div class="text-gray-600 text-sm">Profit/Loss (Manual)</div>
                    <div class="text-2xl font-bold {{ $manualProfitLoss >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $manualProfitLoss >= 0 ? '+' : '' }}${{ number_format($manualProfitLoss, 2) }}
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
                            <td class="py-3 px-4 font-medium">{{ number_format($portfolio->amount, 6) }}</td>
                            <td class="py-3 px-4">${{ number_format($portfolio->average_buy_price, 4) }}</td>
                            <td class="py-3 px-4">${{ number_format($portfolio->project->current_price, 4) }}</td>
                            <td class="py-3 px-4 font-medium">${{ number_format($portfolio->current_value, 2) }}</td>
                            <td class="py-3 px-4 {{ $portfolio->profit_loss_value >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $portfolio->profit_loss_value >= 0 ? '+' : '' }}${{ number_format($portfolio->profit_loss_value, 2) }}
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

    // ⚡ Load onchain data saat halaman ready
    document.addEventListener('DOMContentLoaded', function() {
        loadOnchainData();
    });

    // ⚡ BARU: Function untuk load onchain data secara async
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
            } else {
                throw new Error(data.message || 'Failed to load portfolio data');
            }

        } catch (error) {
            console.error('Error loading onchain data:', error);

            // Show error state
            loadingState.style.display = 'none';
            onchainSection.style.display = 'none';
            errorState.style.display = 'block';

            document.getElementById('error-message').textContent = error.message;
        }
    }

    // ⚡ BARU: Function untuk populate data ke UI
    function populateOnchainData(portfolio) {
        // Update summary cards
        document.getElementById('onchain-total-value').textContent = `${numberFormat(portfolio.total_usd_value || 0, 2)}`;

        const totalAssets = (portfolio.native_balances?.length || 0) + (portfolio.token_balances?.length || 0);
        document.getElementById('onchain-asset-count').textContent = totalAssets;

        const chains = portfolio.chains_scanned?.join(', ') || 'Unknown';
        document.getElementById('onchain-chains').textContent = `Chains: ${chains}`;

        const lastUpdated = portfolio.last_updated ? new Date(portfolio.last_updated).toLocaleString('id-ID') : 'Never';
        document.getElementById('onchain-last-updated').textContent = `Last updated: ${lastUpdated}`;

        // Populate holdings table
        populateHoldingsTable(portfolio);

        // Populate distributions
        populateCategoryDistribution(portfolio);
        populateChainDistribution(portfolio);
    }

    // ⚡ BARU: Populate holdings table
    function populateHoldingsTable(portfolio) {
        const tableBody = document.getElementById('onchain-holdings-table');
        let html = '';

        // Native balances
        if (portfolio.native_balances && portfolio.native_balances.length > 0) {
            portfolio.native_balances.forEach(balance => {
                const usdValue = balance.usd_value ? `${numberFormat(balance.usd_value, 2)}` : 'N/A';

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
                        <td class="py-3 px-4 font-medium">${numberFormat(balance.balance, 6)}</td>
                        <td class="py-3 px-4">
                            <span class="clay-badge clay-badge-info">${balance.chain.toUpperCase()}</span>
                        </td>
                        <td class="py-3 px-4 font-medium">${usdValue}</td>
                        <td class="py-3 px-4">
                            <button class="clay-badge clay-badge-primary py-1 px-2 text-xs" onclick="viewOnExplorer('${balance.chain}', '{{ $walletAddress }}')">
                                <i class="fas fa-external-link-alt"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        }

        // Token balances
        if (portfolio.token_balances && portfolio.token_balances.length > 0) {
            portfolio.token_balances.forEach(token => {
                const usdValue = token.usd_value ? `${numberFormat(token.usd_value, 2)}` : 'N/A';

                html += `
                    <tr>
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-coins text-gray-600 text-xs"></i>
                                </div>
                                <div>
                                    <div class="font-medium">${token.token_name || token.token_symbol}</div>
                                    <div class="text-xs text-gray-500">${token.token_symbol}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4 font-medium">${numberFormat(token.balance, 6)}</td>
                        <td class="py-3 px-4">
                            <span class="clay-badge clay-badge-secondary">${token.chain.toUpperCase()}</span>
                        </td>
                        <td class="py-3 px-4 font-medium">${usdValue}</td>
                        <td class="py-3 px-4">
                            <button class="clay-badge clay-badge-primary py-1 px-2 text-xs" onclick="viewOnExplorer('${token.chain}', '${token.token_address}')">
                                <i class="fas fa-external-link-alt"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        }

        if (html === '') {
            html = `
                <tr>
                    <td colspan="5" class="py-6 px-4 text-center">
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

    // ⚡ BARU: Populate category distribution dengan fix calculation
    function populateCategoryDistribution(portfolio) {
        const container = document.getElementById('onchain-category-distribution');
        const totalValue = portfolio.total_usd_value || 0;

        // Calculate category distribution from token balances
        let categories = {};

        if (portfolio.token_balances) {
            portfolio.token_balances.forEach(token => {
                if (token.usd_value && token.usd_value > 0) {
                    // Use a simplified category mapping
                    const category = getCategoryFromSymbol(token.token_symbol) || 'Other';

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
                            <span class="text-sm">${numberFormat(category.value, 2)}</span>
                        </div>
                        <div class="clay-progress h-2">
                            <div class="clay-progress-bar clay-progress-secondary" style="width: ${percentage}%"></div>
                        </div>
                        <div class="text-xs text-right mt-1">${category.project_count} assets</div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div class="text-center py-4">
                    <p class="text-gray-500 text-sm">Tidak ada data kategori</p>
                </div>
            `;
        }
    }

    // ⚡ BARU: Populate chain distribution dengan fix calculation
    function populateChainDistribution(portfolio) {
        const container = document.getElementById('onchain-chain-distribution');
        const totalValue = portfolio.total_usd_value || 0;

        // Calculate chain distribution
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
                        project_count: 0
                    };
                }

                chains[chain].value += value;
                chains[chain].project_count++;
            });
        }

        // Token balances
        if (portfolio.token_balances) {
            portfolio.token_balances.forEach(token => {
                const chain = token.chain || 'Unknown';
                const value = token.usd_value || 0;

                if (!chains[chain]) {
                    chains[chain] = {
                        chain: chain,
                        value: 0,
                        project_count: 0
                    };
                }

                chains[chain].value += value;
                chains[chain].project_count++;
            });
        }

        const chainArray = Object.values(chains);

        if (chainArray.length > 0) {
            let html = '<div class="space-y-3">';

            chainArray.forEach(chain => {
                const percentage = totalValue > 0 ? (chain.value / totalValue) * 100 : 0;

                html += `
                    <div class="clay-card bg-info/5 p-3">
                        <div class="flex justify-between mb-1">
                            <span class="font-medium text-sm">${chain.chain.charAt(0).toUpperCase() + chain.chain.slice(1)}</span>
                            <span class="text-sm">${numberFormat(chain.value, 2)}</span>
                        </div>
                        <div class="clay-progress h-2">
                            <div class="clay-progress-bar clay-progress-info" style="width: ${percentage}%"></div>
                        </div>
                        <div class="text-xs text-right mt-1">${chain.project_count} assets</div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div class="text-center py-4">
                    <p class="text-gray-500 text-sm">Tidak ada data blockchain</p>
                </div>
            `;
        }
    }

    // ⚡ Helper function untuk mapping symbol ke category
    function getCategoryFromSymbol(symbol) {
        const categoryMap = {
            'ETH': 'Layer-1',
            'BNB': 'Layer-1',
            'MATIC': 'Layer-2',
            'WETH': 'DeFi',
            'USDT': 'Stablecoin',
            'USDC': 'Stablecoin',
            'DAI': 'Stablecoin',
            'UNI': 'DeFi',
            'AAVE': 'DeFi',
            'COMP': 'DeFi'
        };

        return categoryMap[symbol] || 'Other';
    }

    // Copy wallet address to clipboard
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            showNotification('Wallet address copied to clipboard!', 'success');
        });
    }

    // Refresh onchain data
    async function refreshOnchainData() {
        const btn = document.getElementById('refresh-btn');
        const originalText = btn.innerHTML;

        // Show loading state
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Refreshing...';
        btn.disabled = true;

        try {
            await loadOnchainData();
            showNotification('Onchain data refreshed successfully!', 'success');
        } catch (error) {
            showNotification('Error refreshing data', 'error');
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
            'polygon': 'https://polygonscan.com'
        };

        const explorerUrl = explorers[chain.toLowerCase()];
        if (explorerUrl) {
            window.open(`${explorerUrl}/address/${address}`, '_blank');
        } else {
            showNotification('Explorer not available for this chain', 'warning');
        }
    }

    // ⚡ Helper: Number formatting
    function numberFormat(number, decimals = 2) {
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

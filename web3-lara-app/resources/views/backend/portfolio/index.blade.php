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

    <!-- Real Portfolio (Onchain Data) -->
    <div class="mb-8">
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
                            <div class="text-2xl font-bold">
                                ${{ number_format($onchainPortfolio['total_usd_value'] ?? 0, 2) }}
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                Last updated: {{ isset($onchainPortfolio['last_updated']) ? \Carbon\Carbon::parse($onchainPortfolio['last_updated'])->diffForHumans() : 'Never' }}
                            </div>
                        </div>
                        <div class="clay-card bg-success/10 p-4">
                            <div class="text-gray-600 text-sm">Total Assets</div>
                            <div class="text-2xl font-bold">
                                {{ count($onchainPortfolio['token_balances'] ?? []) + count($onchainPortfolio['native_balances'] ?? []) }}
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                Chains: {{ implode(', ', $onchainPortfolio['chains_scanned'] ?? []) }}
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
                                    <th class="py-2 px-4 text-left">24h Change</th>
                                    <th class="py-2 px-4 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Native Balances --}}
                                @if(isset($onchainPortfolio['native_balances']))
                                    @foreach($onchainPortfolio['native_balances'] as $balance)
                                    <tr class="border-l-4 border-blue-500">
                                        <td class="py-3 px-4">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center mr-3">
                                                    <i class="fas fa-coins text-white text-xs"></i>
                                                </div>
                                                <div>
                                                    <div class="font-medium">{{ $balance['token_name'] }}</div>
                                                    <div class="text-xs text-gray-500">{{ $balance['token_symbol'] }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 font-medium">{{ number_format($balance['balance'], 6) }}</td>
                                        <td class="py-3 px-4">
                                            <span class="clay-badge clay-badge-info">{{ strtoupper($balance['chain']) }}</span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="text-gray-500">Estimating...</span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="text-gray-500">-</span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <button class="clay-badge clay-badge-primary py-1 px-2 text-xs" onclick="viewOnExplorer('{{ $balance['chain'] }}', '{{ $walletAddress }}')">
                                                <i class="fas fa-external-link-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                @endif

                                {{-- Token Balances --}}
                                @if(isset($onchainPortfolio['token_balances']))
                                    @foreach($onchainPortfolio['token_balances'] as $token)
                                    <tr>
                                        <td class="py-3 px-4">
                                            <div class="flex items-center">
                                                @if(isset($token['project_data']['image']))
                                                    <img src="{{ $token['project_data']['image'] }}" alt="{{ $token['token_symbol'] }}" class="w-8 h-8 rounded-full mr-3">
                                                @else
                                                    <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                                                        <i class="fas fa-coins text-gray-600 text-xs"></i>
                                                    </div>
                                                @endif
                                                <div>
                                                    <div class="font-medium">{{ $token['token_name'] }}</div>
                                                    <div class="text-xs text-gray-500">{{ $token['token_symbol'] }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 font-medium">{{ number_format($token['balance'], 6) }}</td>
                                        <td class="py-3 px-4">
                                            <span class="clay-badge clay-badge-secondary">{{ strtoupper($token['chain']) }}</span>
                                        </td>
                                        <td class="py-3 px-4 font-medium">
                                            @if(isset($token['usd_value']))
                                                ${{ number_format($token['usd_value'], 2) }}
                                            @else
                                                <span class="text-gray-500">N/A</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4">
                                            @if(isset($token['project_data']['price_change_24h']))
                                                <span class="{{ $token['project_data']['price_change_24h'] >= 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ $token['project_data']['price_change_24h'] >= 0 ? '+' : '' }}{{ number_format($token['project_data']['price_change_24h'], 2) }}%
                                                </span>
                                            @else
                                                <span class="text-gray-500">-</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex space-x-2">
                                                @if(isset($token['project_data']['id']))
                                                    <a href="{{ route('panel.recommendations.project', $token['project_data']['id']) }}" class="clay-badge clay-badge-info py-1 px-2 text-xs">
                                                        <i class="fas fa-info-circle"></i>
                                                    </a>
                                                @endif
                                                <button class="clay-badge clay-badge-primary py-1 px-2 text-xs" onclick="viewOnExplorer('{{ $token['chain'] }}', '{{ $token['token_address'] }}')">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="6" class="py-6 px-4 text-center">
                                            <div class="text-gray-500">
                                                <i class="fas fa-wallet text-4xl mb-3"></i>
                                                <p>Tidak ada token ditemukan di wallet ini</p>
                                                <p class="text-sm">Pastikan wallet address sudah benar dan memiliki balance</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
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

                        @if(count($onchainCategoryDistribution) > 0)
                        <div class="space-y-3">
                            @foreach($onchainCategoryDistribution as $category)
                            <div class="clay-card bg-secondary/5 p-3">
                                <div class="flex justify-between mb-1">
                                    <span class="font-medium text-sm">{{ $category['primary_category'] ?: 'Unknown' }}</span>
                                    <span class="text-sm">${{ number_format($category['value'], 2) }}</span>
                                </div>
                                <div class="clay-progress h-2">
                                    <div class="clay-progress-bar clay-progress-secondary" style="width: {{ ($onchainPortfolio['total_usd_value'] > 0) ? ($category['value'] / $onchainPortfolio['total_usd_value']) * 100 : 0 }}%"></div>
                                </div>
                                <div class="text-xs text-right mt-1">{{ $category['project_count'] }} assets</div>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <div class="text-center py-4">
                            <p class="text-gray-500 text-sm">Tidak ada data kategori</p>
                        </div>
                        @endif
                    </div>

                    <!-- Chain Distribution -->
                    <div class="clay-card p-4">
                        <h3 class="font-bold mb-3 flex items-center">
                            <i class="fas fa-link mr-2 text-info"></i>
                            Distribusi Chain (Onchain)
                        </h3>

                        @if(count($onchainChainDistribution) > 0)
                        <div class="space-y-3">
                            @foreach($onchainChainDistribution as $chain)
                            <div class="clay-card bg-info/5 p-3">
                                <div class="flex justify-between mb-1">
                                    <span class="font-medium text-sm">{{ ucfirst($chain['chain']) }}</span>
                                    <span class="text-sm">${{ number_format($chain['value'], 2) }}</span>
                                </div>
                                <div class="clay-progress h-2">
                                    <div class="clay-progress-bar clay-progress-info" style="width: {{ ($onchainPortfolio['total_usd_value'] > 0) ? ($chain['value'] / $onchainPortfolio['total_usd_value']) * 100 : 0 }}%"></div>
                                </div>
                                <div class="text-xs text-right mt-1">{{ $chain['project_count'] }} assets</div>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <div class="text-center py-4">
                            <p class="text-gray-500 text-sm">Tidak ada data blockchain</p>
                        </div>
                        @endif
                    </div>
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
    // Copy wallet address to clipboard
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            // Show success notification
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
            const response = await fetch('{{ route('panel.portfolio.refresh-onchain') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            const data = await response.json();

            if (data.success) {
                showNotification('Onchain data refreshed successfully!', 'success');
                // Reload page to show new data
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showNotification(data.message || 'Failed to refresh data', 'error');
            }

        } catch (error) {
            console.error('Error refreshing data:', error);
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

    // Auto refresh onchain data every 5 minutes (optional)
    // setInterval(refreshOnchainData, 5 * 60 * 1000);
</script>
@endpush
@endsection

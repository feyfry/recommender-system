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
    <div class="clay-alert clay-alert-success mb-6">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            {{ session('success') }}
        </div>
    </div>
    @endif

    <!-- Analytics Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="clay-card bg-primary/10 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-gray-600 text-sm">Total Transactions</div>
                    <div class="text-3xl font-bold">{{ number_format($analytics['total_transactions'] ?? 0) }}</div>
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
                    <div class="text-3xl font-bold">{{ number_format($analytics['unique_tokens_traded'] ?? 0) }}</div>
                </div>
                <div class="bg-secondary/20 p-3 rounded-lg">
                    <i class="fas fa-coins text-secondary text-xl"></i>
                </div>
            </div>
        </div>

        <div class="clay-card bg-success/10 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-gray-600 text-sm">Total Volume</div>
                    <div class="text-3xl font-bold">${{ number_format($analytics['total_volume_usd'] ?? 0, 0) }}</div>
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
                    <div class="text-3xl font-bold">{{ count($analytics['chains_activity'] ?? []) }}</div>
                </div>
                <div class="bg-info/20 p-3 rounded-lg">
                    <i class="fas fa-link text-info text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Most Traded Tokens -->
        <div class="clay-card p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-trophy mr-2 text-warning"></i>
                Most Traded Tokens
            </h2>

            @if(isset($analytics['most_traded_tokens']) && count($analytics['most_traded_tokens']) > 0)
            <div class="space-y-4">
                @foreach(array_slice($analytics['most_traded_tokens'], 0, 10) as $index => $token)
                <div class="flex items-center justify-between p-3 clay-card bg-gray-50">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-warning/20 rounded-full flex items-center justify-center mr-3">
                            <span class="text-warning font-bold text-sm">{{ $index + 1 }}</span>
                        </div>
                        <div>
                            <div class="font-medium">{{ $token['symbol'] ?? 'Unknown' }}</div>
                            <div class="text-xs text-gray-500">{{ $token['trade_count'] ?? 0 }} transactions</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-medium">${{ number_format($token['volume'] ?? 0, 2) }}</div>
                        <div class="text-xs text-gray-500">Volume</div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-8">
                <i class="fas fa-coins text-4xl text-gray-400 mb-3"></i>
                <p class="text-gray-500">Tidak ada data token trading</p>
            </div>
            @endif
        </div>

        <!-- Chain Activity -->
        <div class="clay-card p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-network-wired mr-2 text-info"></i>
                Chain Activity
            </h2>

            @if(isset($analytics['chains_activity']) && count($analytics['chains_activity']) > 0)
            <div class="space-y-4">
                @php
                    $totalChainTxs = array_sum($analytics['chains_activity']);
                @endphp
                @foreach($analytics['chains_activity'] as $chain => $txCount)
                <div class="clay-card bg-info/5 p-3">
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-medium">{{ ucfirst($chain) }}</span>
                        <span class="text-sm">{{ number_format($txCount) }} txs</span>
                    </div>
                    <div class="clay-progress h-3">
                        <div class="clay-progress-bar clay-progress-info"
                             style="width: {{ $totalChainTxs > 0 ? ($txCount / $totalChainTxs) * 100 : 0 }}%"></div>
                    </div>
                    <div class="text-xs text-right mt-1">
                        {{ $totalChainTxs > 0 ? number_format(($txCount / $totalChainTxs) * 100, 1) : 0 }}%
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-8">
                <i class="fas fa-link text-4xl text-gray-400 mb-3"></i>
                <p class="text-gray-500">Tidak ada data aktivitas chain</p>
            </div>
            @endif
        </div>
    </div>

    <!-- Transaction Frequency Chart -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-chart-area mr-2 text-primary"></i>
            Transaction Frequency (Last 30 Days)
        </h2>

        @if(isset($analytics['transaction_frequency']) && count($analytics['transaction_frequency']) > 0)
        <div class="h-64">
            <canvas id="transactionChart"></canvas>
        </div>
        @else
        <div class="text-center py-16">
            <i class="fas fa-chart-line text-4xl text-gray-400 mb-3"></i>
            <p class="text-gray-500">Tidak ada data frekuensi transaksi</p>
        </div>
        @endif
    </div>

    <!-- Recent Transactions -->
    <div class="clay-card p-6">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-history mr-2 text-secondary"></i>
            Recent Onchain Transactions
        </h2>

        @if(count($recentTransactions) > 0)
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
                    @foreach(array_slice($recentTransactions, 0, 20) as $tx)
                    <tr>
                        <td class="py-3 px-4 text-sm">
                            {{ \Carbon\Carbon::parse($tx['timestamp'])->format('M j, H:i') }}
                        </td>
                        <td class="py-3 px-4">
                            <div class="font-mono text-sm">
                                {{ Str::limit($tx['tx_hash'] ?? '', 16) }}
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            @if($tx['transaction_type'] === 'native')
                                <span class="clay-badge clay-badge-primary">Native</span>
                            @elseif($tx['transaction_type'] === 'token')
                                <span class="clay-badge clay-badge-secondary">Token</span>
                            @else
                                <span class="clay-badge clay-badge-info">Contract</span>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            <div class="font-medium">{{ number_format($tx['value'] ?? 0, 6) }}</div>
                            @if(isset($tx['token_symbol']))
                                <div class="text-xs text-gray-500">{{ $tx['token_symbol'] }}</div>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-sm">
                            {{ number_format($tx['gas_used'] ?? 0) }}
                        </td>
                        <td class="py-3 px-4">
                            <span class="clay-badge clay-badge-info">{{ strtoupper($tx['chain'] ?? '') }}</span>
                        </td>
                        <td class="py-3 px-4">
                            @if($tx['status'] === 'success')
                                <span class="clay-badge clay-badge-success">Success</span>
                            @else
                                <span class="clay-badge clay-badge-danger">Failed</span>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            <button onclick="viewTransaction('{{ $tx['chain'] ?? '' }}', '{{ $tx['tx_hash'] ?? '' }}')"
                                    class="clay-badge clay-badge-primary py-1 px-2 text-xs">
                                <i class="fas fa-external-link-alt"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-500">Showing last 20 transactions</p>
        </div>
        @else
        <div class="text-center py-12">
            <i class="fas fa-history text-4xl text-gray-400 mb-3"></i>
            <p class="text-gray-500">Tidak ada transaksi onchain ditemukan</p>
            <p class="text-sm text-gray-400 mt-2">Pastikan wallet address sudah benar</p>
        </div>
        @endif
    </div>

    <!-- Analytics Insights -->
    <div class="clay-card p-6 mt-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-lightbulb mr-2 text-warning"></i>
            Analytics Insights
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="clay-card bg-success/10 p-4">
                <h3 class="font-bold mb-2">💡 Trading Activity</h3>
                <p class="text-sm mb-2">
                    @if(($analytics['total_transactions'] ?? 0) > 100)
                        Anda adalah trader yang sangat aktif dengan {{ number_format($analytics['total_transactions']) }} transaksi.
                    @elseif(($analytics['total_transactions'] ?? 0) > 20)
                        Aktivitas trading Anda cukup baik dengan {{ number_format($analytics['total_transactions']) }} transaksi.
                    @else
                        Anda baru memulai atau jarang trading dengan {{ number_format($analytics['total_transactions']) }} transaksi.
                    @endif
                </p>
            </div>

            <div class="clay-card bg-info/10 p-4">
                <h3 class="font-bold mb-2">🌐 Diversification</h3>
                <p class="text-sm mb-2">
                    @if(($analytics['unique_tokens_traded'] ?? 0) > 20)
                        Portfolio Anda sangat terdiversifikasi dengan {{ $analytics['unique_tokens_traded'] }} token berbeda.
                    @elseif(($analytics['unique_tokens_traded'] ?? 0) > 5)
                        Diversifikasi yang baik dengan {{ $analytics['unique_tokens_traded'] }} token berbeda.
                    @else
                        Pertimbangkan untuk diversifikasi lebih banyak token.
                    @endif
                </p>
            </div>

            <div class="clay-card bg-warning/10 p-4">
                <h3 class="font-bold mb-2">⛓️ Multi-Chain</h3>
                <p class="text-sm mb-2">
                    @if(count($analytics['chains_activity'] ?? []) > 2)
                        Excellent! Anda aktif di {{ count($analytics['chains_activity']) }} blockchain berbeda.
                    @elseif(count($analytics['chains_activity'] ?? []) > 1)
                        Good! Anda menggunakan {{ count($analytics['chains_activity']) }} blockchain.
                    @else
                        Pertimbangkan untuk explore blockchain lain untuk biaya yang lebih rendah.
                    @endif
                </p>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Copy wallet address to clipboard
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            showNotification('Wallet address copied to clipboard!', 'success');
        });
    }

    // Refresh analytics data
    async function refreshAnalytics() {
        const btn = document.getElementById('refresh-analytics-btn');
        const originalText = btn.innerHTML;

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
                showNotification('Analytics data refreshed successfully!', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showNotification(data.message || 'Failed to refresh data', 'error');
            }

        } catch (error) {
            console.error('Error refreshing analytics:', error);
            showNotification('Error refreshing analytics data', 'error');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    // View transaction on blockchain explorer
    function viewTransaction(chain, txHash) {
        const explorers = {
            'eth': 'https://etherscan.io',
            'ethereum': 'https://etherscan.io',
            'bsc': 'https://bscscan.com',
            'binance_smart_chain': 'https://bscscan.com',
            'polygon': 'https://polygonscan.com'
        };

        const explorerUrl = explorers[chain.toLowerCase()];
        if (explorerUrl && txHash) {
            window.open(`${explorerUrl}/tx/${txHash}`, '_blank');
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

        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    // Initialize transaction frequency chart
    document.addEventListener('DOMContentLoaded', function() {
        @if(isset($analytics['transaction_frequency']) && count($analytics['transaction_frequency']) > 0)
        const transactionData = @json($analytics['transaction_frequency']);

        const labels = Object.keys(transactionData).sort();
        const data = labels.map(date => transactionData[date] || 0);

        const ctx = document.getElementById('transactionChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Transactions',
                    data: data,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        type: 'time',
                        time: {
                            parser: 'YYYY-MM-DD',
                            displayFormats: {
                                day: 'MMM DD'
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        @endif
    });
</script>
@endpush
@endsection

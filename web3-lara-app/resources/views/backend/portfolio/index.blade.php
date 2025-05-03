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
                    Portfolio
                </h1>
                <p class="text-gray-600">Kelola dan pantau aset cryptocurrency Anda</p>
            </div>
            <div class="mt-4 md:mt-0">
                <button type="button" class="clay-button clay-button-success" onclick="document.getElementById('add-transaction-modal').classList.remove('hidden')">
                    <i class="fas fa-plus mr-2"></i> Tambah Transaksi
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

    @if(session('error'))
    <div class="clay-alert clay-alert-danger mb-6">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            {{ session('error') }}
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Portfolio Summary -->
        <div class="lg:col-span-2">
            <div class="clay-card p-6">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-chart-pie mr-2 text-primary"></i>
                    Ringkasan Portfolio
                </h2>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="clay-card bg-primary/10 p-4">
                        <div class="text-gray-600 text-sm">Total Nilai</div>
                        <div class="text-2xl font-bold">${{ number_format($totalValue, 2) }}</div>
                    </div>
                    <div class="clay-card bg-{{ $profitLoss >= 0 ? 'success' : 'danger' }}/10 p-4">
                        <div class="text-gray-600 text-sm">Profit/Loss</div>
                        <div class="text-2xl font-bold {{ $profitLoss >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $profitLoss >= 0 ? '+' : '' }}${{ number_format($profitLoss, 2) }}
                            <span class="text-sm">({{ number_format($profitLossPercentage, 2) }}%)</span>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="clay-table min-w-full">
                        <thead>
                            <tr>
                                <th class="py-2 px-4 text-left">Aset</th>
                                <th class="py-2 px-4 text-left">Jumlah</th>
                                <th class="py-2 px-4 text-left">Harga Avg.</th>
                                <th class="py-2 px-4 text-left">Harga Saat Ini</th>
                                <th class="py-2 px-4 text-left">Nilai Total</th>
                                <th class="py-2 px-4 text-left">P/L</th>
                                <th class="py-2 px-4 text-left">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($portfolios as $portfolio)
                            <tr>
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
                                <td class="py-3 px-4">
                                    <div class="flex space-x-2">
                                        <a href="{{ route('panel.recommendations.project', $portfolio->project_id) }}" class="clay-badge clay-badge-info py-1 px-2 text-xs">
                                            <i class="fas fa-info-circle"></i>
                                        </a>
                                        <button type="button" onclick="openTransactionModal('{{ $portfolio->project_id }}', '{{ $portfolio->project->name }}', 'sell')" class="clay-badge clay-badge-danger py-1 px-2 text-xs">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="py-6 px-4 text-center">
                                    <p class="text-gray-500">Belum ada aset di portfolio Anda</p>
                                    <button type="button" onclick="document.getElementById('add-transaction-modal').classList.remove('hidden')" class="clay-button clay-button-success mt-3 py-1.5 px-3 text-sm">
                                        <i class="fas fa-plus mr-1"></i> Tambah Transaksi
                                    </button>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Portfolio Distribution -->
        <div class="lg:col-span-1">
            <div class="clay-card p-6 mb-6">
                <h2 class="text-lg font-bold mb-4 flex items-center">
                    <i class="fas fa-tags mr-2 text-secondary"></i>
                    Distribusi Kategori
                </h2>

                @if(count($categoryDistribution) > 0)
                <div class="space-y-4">
                    @foreach($categoryDistribution as $category)
                    <div class="clay-card bg-secondary/5 p-3">
                        <div class="flex justify-between mb-1">
                            <span class="font-medium">{{ $category->primary_category ?: 'Lainnya' }}</span>
                            <span>${{ number_format($category->value, 2) }}</span>
                        </div>
                        <div class="clay-progress">
                            <div class="clay-progress-bar clay-progress-secondary" style="width: {{ ($category->value / $totalValue) * 100 }}%"></div>
                        </div>
                        <div class="text-xs text-right mt-1">{{ number_format(($category->value / $totalValue) * 100, 1) }}% ({{ $category->project_count }} aset)</div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-6">
                    <p class="text-gray-500">Tidak ada data distribusi kategori</p>
                </div>
                @endif
            </div>

            <div class="clay-card p-6 mb-6">
                <h2 class="text-lg font-bold mb-4 flex items-center">
                    <i class="fas fa-link mr-2 text-info"></i>
                    Distribusi Blockchain
                </h2>

                @if(count($chainDistribution) > 0)
                <div class="space-y-4">
                    @foreach($chainDistribution as $chain)
                    <div class="clay-card bg-info/5 p-3">
                        <div class="flex justify-between mb-1">
                            <span class="font-medium">{{ $chain->chain ?: 'Lainnya' }}</span>
                            <span>${{ number_format($chain->value, 2) }}</span>
                        </div>
                        <div class="clay-progress">
                            <div class="clay-progress-bar clay-progress-info" style="width: {{ ($chain->value / $totalValue) * 100 }}%"></div>
                        </div>
                        <div class="text-xs text-right mt-1">{{ number_format(($chain->value / $totalValue) * 100, 1) }}% ({{ $chain->project_count }} aset)</div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-6">
                    <p class="text-gray-500">Tidak ada data distribusi blockchain</p>
                </div>
                @endif
            </div>

            <div class="clay-card p-6">
                <h2 class="text-lg font-bold mb-4 flex items-center">
                    <i class="fas fa-link mr-2 text-warning"></i>
                    Aksi Cepat
                </h2>

                <div class="space-y-3">
                    <a href="{{ route('panel.portfolio.transactions') }}" class="clay-button clay-button-info w-full flex justify-between items-center">
                        <span><i class="fas fa-exchange-alt mr-2"></i> Riwayat Transaksi</span>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <a href="{{ route('panel.portfolio.price-alerts') }}" class="clay-button clay-button-warning w-full flex justify-between items-center">
                        <span><i class="fas fa-bell mr-2"></i> Price Alerts</span>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <button type="button" onclick="document.getElementById('add-transaction-modal').classList.remove('hidden')" class="clay-button clay-button-success w-full flex justify-between items-center">
                        <span><i class="fas fa-plus mr-2"></i> Tambah Transaksi</span>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Portfolio Performance Chart -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-chart-line mr-2 text-primary"></i>
            Performa Portfolio (30 Hari)
        </h2>

        @if(count($performanceData) > 0)
        <div class="h-80">
            <!-- Canvas untuk chart -->
            <canvas id="portfolioChart"></canvas>
        </div>
        @else
        <div class="text-center py-6">
            <p class="text-gray-500">Tidak ada data performa portfolio</p>
        </div>
        @endif
    </div>

    <!-- Add Transaction Modal -->
    <div id="add-transaction-modal" class="fixed inset-0 z-50 hidden">
        <div class="clay-modal-backdrop"></div>
        <div class="clay-modal max-w-md">
            <div class="clay-modal-header">
                <h3 class="text-xl font-bold">Tambah Transaksi</h3>
            </div>
            <form action="{{ route('panel.portfolio.add-transaction') }}" method="POST">
                @csrf
                <div class="clay-modal-body">
                    <div class="space-y-4">
                        <div>
                            <label for="project_id" class="block font-medium mb-2">Proyek</label>
                            <select name="project_id" id="project_id" class="clay-select" required>
                                <option value="">-- Pilih Proyek --</option>
                                @foreach(\App\Models\Project::orderBy('name')->get() as $project)
                                    <option value="{{ $project->id }}">{{ $project->name }} ({{ $project->symbol }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="transaction_type" class="block font-medium mb-2">Tipe Transaksi</label>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="clay-card bg-success/10 p-3 cursor-pointer">
                                    <input type="radio" name="transaction_type" value="buy" class="sr-only" checked>
                                    <div class="flex items-center justify-center">
                                        <i class="fas fa-arrow-circle-down text-success mr-2"></i>
                                        <span class="font-medium">Buy</span>
                                    </div>
                                </label>
                                <label class="clay-card bg-danger/10 p-3 cursor-pointer">
                                    <input type="radio" name="transaction_type" value="sell" class="sr-only">
                                    <div class="flex items-center justify-center">
                                        <i class="fas fa-arrow-circle-up text-danger mr-2"></i>
                                        <span class="font-medium">Sell</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label for="amount" class="block font-medium mb-2">Jumlah</label>
                            <input type="number" name="amount" id="amount" class="clay-input" step="0.000001" min="0.000001" required>
                        </div>

                        <div>
                            <label for="price" class="block font-medium mb-2">Harga per Unit ($)</label>
                            <input type="number" name="price" id="price" class="clay-input" step="0.000001" min="0.000001" required>
                        </div>

                        <div>
                            <label for="transaction_hash" class="block font-medium mb-2">Transaction Hash (opsional)</label>
                            <input type="text" name="transaction_hash" id="transaction_hash" class="clay-input">
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="followed_recommendation" id="followed_recommendation" class="clay-checkbox">
                            <label for="followed_recommendation" class="ml-2">Transaksi ini mengikuti rekomendasi sistem</label>
                        </div>
                    </div>
                </div>
                <div class="clay-modal-footer">
                    <button type="button" class="clay-button" onclick="document.getElementById('add-transaction-modal').classList.add('hidden')">
                        Batal
                    </button>
                    <button type="submit" class="clay-button clay-button-success">
                        <i class="fas fa-save mr-1"></i> Simpan Transaksi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Setup data for chart
        @if(count($performanceData) > 0)
        const ctx = document.getElementById('portfolioChart').getContext('2d');

        const chartData = {
            labels: @json(array_column($performanceData, 'date')),
            datasets: [{
                label: 'Portfolio Value ($)',
                data: @json(array_column($performanceData, 'value')),
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.1
            }]
        };

        new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                return ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        @endif

        // Transaction modal script
        const radios = document.querySelectorAll('input[name="transaction_type"]');
        for (const radio of radios) {
            radio.addEventListener('change', function(event) {
                const cards = document.querySelectorAll('.clay-card.bg-success\\/10, .clay-card.bg-danger\\/10');
                cards.forEach(card => {
                    card.classList.remove('border-2', 'border-success', 'border-danger');
                });

                if (event.target.value === 'buy') {
                    event.target.closest('.clay-card').classList.add('border-2', 'border-success');
                } else {
                    event.target.closest('.clay-card').classList.add('border-2', 'border-danger');
                }
            });
        }

        // Trigger change for the default checked radio
        document.querySelector('input[name="transaction_type"]:checked').dispatchEvent(new Event('change'));
    });

    function openTransactionModal(projectId, projectName, type) {
        const modal = document.getElementById('add-transaction-modal');
        const projectSelect = document.getElementById('project_id');
        const transactionTypeRadios = document.querySelectorAll('input[name="transaction_type"]');

        // Set project
        for (const option of projectSelect.options) {
            if (option.value === projectId) {
                option.selected = true;
                break;
            }
        }

        // Set transaction type
        for (const radio of transactionTypeRadios) {
            if (radio.value === type) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change'));
                break;
            }
        }

        modal.classList.remove('hidden');
    }
</script>
@endpush
@endsection

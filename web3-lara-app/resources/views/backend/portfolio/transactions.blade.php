@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <h1 class="text-3xl font-bold mb-4 flex items-center">
            <div class="bg-info/20 p-2 clay-badge mr-3">
                <i class="fas fa-exchange-alt text-info"></i>
            </div>
            Riwayat Transaksi
        </h1>
        <p class="text-lg">
            Lihat dan kelola histori transaksi cryptocurrency Anda untuk melacak semua aktivitas buy dan sell.
        </p>
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
        <!-- Transaction Stats -->
        <div class="lg:col-span-2">
            <div class="clay-card p-6">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-chart-pie mr-2 text-primary"></i>
                    Statistik Transaksi
                </h2>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    @php
                        $buyTotal = 0;
                        $sellTotal = 0;
                        $netValue = 0;

                        if(isset($volumeStats)) {
                            foreach($volumeStats as $stat) {
                                if($stat->transaction_type == 'buy') {
                                    $buyTotal = $stat->total_value;
                                } else if($stat->transaction_type == 'sell') {
                                    $sellTotal = $stat->total_value;
                                }
                            }
                            $netValue = $sellTotal - $buyTotal;
                        }
                    @endphp

                    <div class="clay-card bg-primary/10 p-4">
                        <div class="text-gray-600 text-sm">Jumlah Transaksi</div>
                        <div class="text-2xl font-bold">{{ $transactions->total() ?? 0 }}</div>
                    </div>
                    <div class="clay-card bg-success/10 p-4">
                        <div class="text-gray-600 text-sm">Total Pembelian</div>
                        <div class="text-2xl font-bold">${{ number_format($buyTotal, 2) }}</div>
                    </div>
                    <div class="clay-card bg-danger/10 p-4">
                        <div class="text-gray-600 text-sm">Total Penjualan</div>
                        <div class="text-2xl font-bold">${{ number_format($sellTotal, 2) }}</div>
                    </div>
                    <div class="clay-card bg-{{ $netValue >= 0 ? 'success' : 'danger' }}/10 p-4">
                        <div class="text-gray-600 text-sm">Net Value</div>
                        <div class="text-2xl font-bold {{ $netValue >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $netValue >= 0 ? '+' : '' }}${{ number_format(abs($netValue), 2) }}
                        </div>
                    </div>
                </div>

                <!-- Most Traded Projects -->
                <h3 class="font-bold mb-3">Proyek Paling Sering Diperdagangkan</h3>
                <div class="overflow-x-auto mb-6">
                    <table class="clay-table min-w-full">
                        <thead>
                            <tr>
                                <th class="py-2 px-4 text-left">Proyek</th>
                                <th class="py-2 px-4 text-left">Jumlah Transaksi</th>
                                <th class="py-2 px-4 text-left">Total Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($mostTradedProjects ?? [] as $project)
                            <tr>
                                <td class="py-2 px-4">
                                    <div class="flex items-center">
                                        @if($project->image)
                                            <img src="{{ $project->image }}" alt="{{ $project->symbol }}" class="w-6 h-6 mr-2 rounded-full">
                                        @endif
                                        <span class="font-medium">{{ $project->name }} ({{ $project->symbol }})</span>
                                    </div>
                                </td>
                                <td class="py-2 px-4">{{ $project->transaction_count }}</td>
                                <td class="py-2 px-4 font-medium">${{ number_format($project->total_value, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="py-4 px-4 text-center text-gray-500">Tidak ada data transaksi proyek</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Recommendation Influence -->
                @if(isset($recommendationInfluence) && count($recommendationInfluence) > 0)
                <h3 class="font-bold mb-3">Pengaruh Rekomendasi Terhadap Transaksi</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($recommendationInfluence as $influence)
                    <div class="clay-card bg-{{ $influence->followed_recommendation ? 'secondary' : 'primary' }}/10 p-4">
                        <div class="font-medium mb-2">
                            {{ $influence->followed_recommendation ? 'Transaksi Mengikuti Rekomendasi' : 'Transaksi Independen' }}
                        </div>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span>Jumlah Transaksi:</span>
                                <span class="font-medium">{{ $influence->count }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total Nilai:</span>
                                <span class="font-medium">${{ number_format($influence->total_value, 2) }}</span>
                            </div>
                            @if(isset($influence->avg_buy_price) && $influence->avg_buy_price > 0)
                            <div class="flex justify-between">
                                <span>Harga Beli Rata-rata:</span>
                                <span class="font-medium">${{ number_format($influence->avg_buy_price, 2) }}</span>
                            </div>
                            @endif
                            @if(isset($influence->avg_sell_price) && $influence->avg_sell_price > 0)
                            <div class="flex justify-between">
                                <span>Harga Jual Rata-rata:</span>
                                <span class="font-medium">${{ number_format($influence->avg_sell_price, 2) }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>

        <!-- Add Transaction -->
        <div class="lg:col-span-1">
            <div class="clay-card p-6 sticky top-24">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-plus-circle mr-2 text-success"></i>
                    Tambah Transaksi Baru
                </h2>

                <form action="{{ route('panel.portfolio.add-transaction') }}" method="POST" class="space-y-4">
                    @csrf
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

                    <button type="submit" class="clay-button clay-button-success w-full py-2 mt-2">
                        <i class="fas fa-plus-circle mr-1"></i> Tambah Transaksi
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Transactions List -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-history mr-2 text-info"></i>
            Semua Transaksi
        </h2>

        <div class="overflow-x-auto">
            <table class="clay-table min-w-full">
                <thead>
                    <tr>
                        <th class="py-2 px-4 text-left">Tanggal</th>
                        <th class="py-2 px-4 text-left">Proyek</th>
                        <th class="py-2 px-4 text-left">Tipe</th>
                        <th class="py-2 px-4 text-left">Jumlah</th>
                        <th class="py-2 px-4 text-left">Harga</th>
                        <th class="py-2 px-4 text-left">Total Nilai</th>
                        <th class="py-2 px-4 text-left">Rekomendasi</th>
                        <th class="py-2 px-4 text-left">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                    <tr>
                        <td class="py-3 px-4 text-sm">{{ $transaction->created_at->format('j M Y H:i') }}</td>
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                @if($transaction->project->image)
                                    <img src="{{ $transaction->project->image }}" alt="{{ $transaction->project->symbol }}" class="w-6 h-6 mr-2 rounded-full">
                                @endif
                                <span class="font-medium">{{ $transaction->project->symbol }}</span>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            @if($transaction->transaction_type == 'buy')
                                <span class="clay-badge clay-badge-success">Buy</span>
                            @else
                                <span class="clay-badge clay-badge-danger">Sell</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 font-medium">{{ number_format($transaction->amount, 6) }}</td>
                        <td class="py-3 px-4">${{ number_format($transaction->price, 2) }}</td>
                        <td class="py-3 px-4 font-medium">${{ number_format($transaction->total_value, 2) }}</td>
                        <td class="py-3 px-4">
                            @if($transaction->followed_recommendation)
                                <span class="clay-badge clay-badge-info py-1 px-2 text-xs">
                                    <i class="fas fa-check-circle mr-1"></i> Ya
                                </span>
                            @else
                                <span class="text-gray-500">-</span>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            @if($transaction->transaction_hash)
                                <a href="#" class="clay-badge clay-badge-secondary py-1 px-2 text-xs" title="{{ $transaction->transaction_hash }}">
                                    <i class="fas fa-file-alt"></i>
                                </a>
                            @endif
                            <a href="{{ route('panel.recommendations.project', $transaction->project_id) }}" class="clay-badge clay-badge-primary py-1 px-2 text-xs">
                                <i class="fas fa-info-circle"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-6 px-4 text-center text-gray-500">
                            <p>Belum ada transaksi yang tercatat.</p>
                            <p class="text-sm mt-2">Tambahkan transaksi baru menggunakan form di samping.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if(isset($transactions) && $transactions->hasPages())
        <div class="mt-6">
            {{ $transactions->links() }}
        </div>
        @endif
    </div>

    <!-- Tips and Guidance -->
    <div class="clay-card p-6">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-lightbulb mr-2 text-warning"></i>
            Tips Mencatat Transaksi
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="clay-card bg-success/10 p-4">
                <h3 class="font-bold mb-2">Akurasi Data</h3>
                <ul class="text-sm space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Catat transaksi segera setelah pembelian/penjualan</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Gunakan harga sesuai dengan yang tercatat di platform exchange</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Simpan transaction hash untuk referensi di blockchain</span>
                    </li>
                </ul>
            </div>

            <div class="clay-card bg-info/10 p-4">
                <h3 class="font-bold mb-2">Analisis Performa</h3>
                <ul class="text-sm space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-info-circle text-info mt-1 mr-2"></i>
                        <span>Bandingkan hasil transaksi yang mengikuti rekomendasi vs yang tidak</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-info-circle text-info mt-1 mr-2"></i>
                        <span>Evaluasi performa per kategori dan blockchain</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-info-circle text-info mt-1 mr-2"></i>
                        <span>Perhatikan waktu terbaik untuk pembelian dan penjualan</span>
                    </li>
                </ul>
            </div>

            <div class="clay-card bg-warning/10 p-4">
                <h3 class="font-bold mb-2">Rekam Jejak Investasi</h3>
                <ul class="text-sm space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-exclamation-circle text-warning mt-1 mr-2"></i>
                        <span>Riwayat transaksi yang lengkap penting untuk pajak dan audit</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-exclamation-circle text-warning mt-1 mr-2"></i>
                        <span>Dokumentasikan alasan di balik keputusan transaksi</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-exclamation-circle text-warning mt-1 mr-2"></i>
                        <span>Manfaatkan data historikal untuk perbaikan strategi</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Transaction type radio buttons
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
</script>
@endpush
@endsection

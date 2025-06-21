@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
            <div>
                <h1 class="text-3xl font-bold mb-2 flex items-center">
                    <div class="bg-warning/20 p-2 clay-badge mr-3">
                        <i class="fas fa-cash-register text-warning"></i>
                    </div>
                    Transaction Management
                </h1>
                <p class="text-gray-600">Sistem kasir untuk mencatat transaksi manual cryptocurrency Anda</p>
                <div class="text-sm text-gray-500 mt-1">
                    <i class="fas fa-info-circle mr-1"></i>
                    Catat pembelian/penjualan secara manual berdasarkan aktivitas trading onchain Anda
                </div>
            </div>
            <div class="mt-4 md:mt-0 flex space-x-3">
                <a href="{{ route('panel.portfolio') }}" class="clay-button clay-button-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Portfolio
                </a>
                <a href="{{ route('panel.portfolio.onchain-analytics') }}" class="clay-button clay-button-info">
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

    @if(session('error'))
    <div class="clay-alert clay-alert-danger mb-6">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            {{ session('error') }}
        </div>
    </div>
    @endif

    {{-- Alert khusus untuk project yang baru ditambahkan --}}
    @if(request()->has('add_project'))
    <div class="clay-alert clay-alert-info mb-6" id="add-project-alert">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-info-circle mr-2"></i>
                <span>Proyek <strong id="selected-project-name">{{ request('add_project') }}</strong> sudah dipilih di form transaksi. Silakan lengkapi detail transaksi di bawah.</span>
            </div>
            <button onclick="document.getElementById('add-project-alert').style.display='none'" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- Transaction Management System (Like POS) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Transaction Stats & Analytics -->
        <div class="lg:col-span-2">
            <div class="clay-card p-6">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-chart-bar mr-2 text-primary"></i>
                    Statistik Transaksi Manual
                    <span class="ml-3 clay-badge clay-badge-warning text-xs">MANUAL RECORDS</span>
                </h2>

                {{-- Transaction summary cards --}}
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
                        <div class="text-gray-600 text-sm">Total Records</div>
                        <div class="text-2xl font-bold">{{ $transactions->total() ?? 0 }}</div>
                        <div class="text-xs text-gray-500 mt-1">Manual entries</div>
                    </div>
                    <div class="clay-card bg-success/10 p-4">
                        <div class="text-gray-600 text-sm">Buy Volume</div>
                        <div class="text-2xl font-bold">${{ number_format($buyTotal, 2) }}</div>
                        <div class="text-xs text-gray-500 mt-1">Total purchases</div>
                    </div>
                    <div class="clay-card bg-danger/10 p-4">
                        <div class="text-gray-600 text-sm">Sell Volume</div>
                        <div class="text-2xl font-bold">${{ number_format($sellTotal, 2) }}</div>
                        <div class="text-xs text-gray-500 mt-1">Total sales</div>
                    </div>
                    <div class="clay-card bg-{{ $netValue >= 0 ? 'success' : 'danger' }}/10 p-4">
                        <div class="text-gray-600 text-sm">Net Flow</div>
                        <div class="text-2xl font-bold {{ $netValue >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $netValue >= 0 ? '+' : '' }}${{ number_format(abs($netValue), 2) }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">Cash flow</div>
                    </div>
                </div>

                <!-- Most Traded Projects (Manual Records) -->
                <h3 class="font-bold mb-3 flex items-center">
                    <i class="fas fa-trophy mr-2 text-warning"></i>
                    Proyek Paling Sering Dicatat
                </h3>
                <div class="overflow-x-auto mb-6">
                    <table class="clay-table min-w-full">
                        <thead>
                            <tr>
                                <th class="py-2 px-4 text-left">Proyek</th>
                                <th class="py-2 px-4 text-left">Manual Records</th>
                                <th class="py-2 px-4 text-left">Total Volume</th>
                                <th class="py-2 px-4 text-left">Actions</th>
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
                                <td class="py-2 px-4">
                                    <span class="clay-badge clay-badge-primary">{{ $project->transaction_count }}</span>
                                </td>
                                <td class="py-2 px-4 font-medium">${{ number_format($project->total_value, 2) }}</td>
                                <td class="py-2 px-4">
                                    <button onclick="quickAddTransaction('{{ $project->id }}', '{{ $project->name }}', '{{ $project->symbol }}')"
                                            class="clay-badge clay-badge-success py-1 px-2 text-xs">
                                        <i class="fas fa-plus mr-1"></i> Quick Add
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="py-4 px-4 text-center text-gray-500">Belum ada data transaksi manual</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Recommendation Influence Analysis -->
                @if(isset($recommendationInfluence) && count($recommendationInfluence) > 0)
                <h3 class="font-bold mb-3 flex items-center">
                    <i class="fas fa-brain mr-2 text-info"></i>
                    Analisis Pengaruh Rekomendasi
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($recommendationInfluence as $influence)
                    <div class="clay-card bg-{{ $influence->followed_recommendation ? 'success' : 'secondary' }}/10 p-4">
                        <div class="font-medium mb-2">
                            {{ $influence->followed_recommendation ? '🤖 AI-Guided Transactions' : '👤 Independent Decisions' }}
                        </div>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span>Records Count:</span>
                                <span class="font-medium">{{ $influence->count }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total Volume:</span>
                                <span class="font-medium">${{ number_format($influence->total_value, 2) }}</span>
                            </div>
                            @if(isset($influence->avg_buy_price) && $influence->avg_buy_price > 0)
                            <div class="flex justify-between">
                                <span>Avg Buy Price:</span>
                                <span class="font-medium">${{ number_format($influence->avg_buy_price, 2) }}</span>
                            </div>
                            @endif
                            @if(isset($influence->avg_sell_price) && $influence->avg_sell_price > 0)
                            <div class="flex justify-between">
                                <span>Avg Sell Price:</span>
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

        <!-- POS-Style Transaction Entry -->
        <div class="lg:col-span-1">
            <div class="clay-card p-6 sticky top-24">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-cash-register mr-2 text-success"></i>
                    POS Transaction Entry
                </h2>
                <div class="text-sm text-gray-500 mb-4">
                    <i class="fas fa-lightbulb mr-1"></i>
                    Seperti sistem kasir - pilih proyek, masukkan jumlah dan harga
                </div>

                <form action="{{ route('panel.portfolio.add-transaction') }}" method="POST" class="space-y-4" id="transaction-form">
                    @csrf

                    <!-- Project Selection -->
                    <div>
                        <label for="project_id" class="block font-medium mb-2">
                            <i class="fas fa-coins mr-1"></i> Pilih Proyek
                        </label>
                        <select name="project_id" id="project_id" class="clay-select" required>
                            <option value="">-- Pilih Cryptocurrency --</option>
                            @foreach(\App\Models\Project::orderBy('name')->get() as $project)
                                <option value="{{ $project->id }}"
                                    data-symbol="{{ $project->symbol }}"
                                    data-name="{{ $project->name }}"
                                    data-price="{{ $project->current_price }}"
                                    {{ request('add_project') == $project->id ? 'selected' : '' }}>
                                    {{ $project->name }} ({{ $project->symbol }}) - ${{ number_format($project->current_price, 4) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Transaction Type -->
                    <div>
                        <label class="block font-medium mb-2">
                            <i class="fas fa-exchange-alt mr-1"></i> Tipe Transaksi
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="clay-card bg-success/10 p-3 cursor-pointer transaction-type-card border-2 border-transparent hover:border-success/30">
                                <input type="radio" name="transaction_type" value="buy" class="sr-only" checked>
                                <div class="flex items-center justify-center">
                                    <i class="fas fa-shopping-cart text-success mr-2"></i>
                                    <span class="font-medium">BUY</span>
                                </div>
                                <div class="text-xs text-center mt-1">Purchase</div>
                            </label>
                            <label class="clay-card bg-danger/10 p-3 cursor-pointer transaction-type-card border-2 border-transparent hover:border-danger/30">
                                <input type="radio" name="transaction_type" value="sell" class="sr-only">
                                <div class="flex items-center justify-center">
                                    <i class="fas fa-hand-holding-usd text-danger mr-2"></i>
                                    <span class="font-medium">SELL</span>
                                </div>
                                <div class="text-xs text-center mt-1">Sale</div>
                            </label>
                        </div>
                    </div>

                    <!-- Amount -->
                    <div>
                        <label for="amount" class="block font-medium mb-2">
                            <i class="fas fa-weight mr-1"></i> Jumlah/Amount
                        </label>
                        <input type="number" name="amount" id="amount" class="clay-input"
                               step="0.000001" min="0.000001" required
                               placeholder="0.000000">
                        <div class="text-xs text-gray-500 mt-1">Jumlah koin/token</div>
                    </div>

                    <!-- Price -->
                    <div>
                        <label for="price" class="block font-medium mb-2">
                            <i class="fas fa-dollar-sign mr-1"></i> Harga per Unit
                        </label>
                        <input type="number" name="price" id="price" class="clay-input"
                               step="0.000001" min="0.000001" required
                               placeholder="0.000000">
                        <div class="text-xs text-gray-500 mt-1">Harga dalam USD</div>
                        <button type="button" onclick="useCurrentPrice()" class="text-xs text-primary hover:underline mt-1">
                            <i class="fas fa-sync-alt mr-1"></i> Use current market price
                        </button>
                    </div>

                    <!-- Total Value Display -->
                    <div class="clay-card bg-info/10 p-3">
                        <div class="text-sm font-medium">Total Value:</div>
                        <div id="total-value" class="text-xl font-bold">$0.00</div>
                    </div>

                    <!-- Transaction Hash (Optional) -->
                    <div>
                        <label for="transaction_hash" class="block font-medium mb-2">
                            <i class="fas fa-hashtag mr-1"></i> Transaction Hash (opsional)
                        </label>
                        <input type="text" name="transaction_hash" id="transaction_hash" class="clay-input"
                               placeholder="0x1234567890abcdef...">
                        <div class="text-xs text-gray-500 mt-1">Hash dari blockchain explorer</div>
                    </div>

                    <!-- Recommendation Follow -->
                    <div class="flex items-center p-3 clay-card bg-secondary/10">
                        <input type="checkbox" name="followed_recommendation" id="followed_recommendation" class="clay-checkbox">
                        <label for="followed_recommendation" class="ml-2 text-sm">
                            <i class="fas fa-robot mr-1"></i>
                            Transaksi ini mengikuti rekomendasi AI sistem
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="clay-button clay-button-success w-full py-3 text-lg font-bold">
                        <i class="fas fa-save mr-2"></i> Record Transaction
                    </button>
                </form>

                <!-- Quick Actions -->
                <div class="mt-6 space-y-2">
                    <div class="text-sm font-medium text-gray-600 mb-2">Quick Actions:</div>
                    <button onclick="clearForm()" class="clay-button clay-button-secondary w-full py-2 text-sm">
                        <i class="fas fa-eraser mr-1"></i> Clear Form
                    </button>
                    <button onclick="copyLastTransaction()" class="clay-button clay-button-info w-full py-2 text-sm">
                        <i class="fas fa-copy mr-1"></i> Copy Last Transaction
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction Records Table -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-table mr-2 text-info"></i>
            Manual Transaction Records
            <span class="ml-3 clay-badge clay-badge-warning text-xs">{{ $transactions->total() ?? 0 }} RECORDS</span>
        </h2>

        <div class="overflow-x-auto">
            <table class="clay-table min-w-full">
                <thead>
                    <tr>
                        <th class="py-2 px-4 text-left">Date & Time</th>
                        <th class="py-2 px-4 text-left">Project</th>
                        <th class="py-2 px-4 text-left">Type</th>
                        <th class="py-2 px-4 text-left">Amount</th>
                        <th class="py-2 px-4 text-left">Price</th>
                        <th class="py-2 px-4 text-left">Total Value</th>
                        <th class="py-2 px-4 text-left">AI Rec.</th>
                        <th class="py-2 px-4 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4 text-sm">
                            <div class="font-medium">{{ $transaction->created_at->format('M j, Y') }}</div>
                            <div class="text-xs text-gray-500">{{ $transaction->created_at->format('H:i:s') }}</div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                @if($transaction->project->image)
                                    <img src="{{ $transaction->project->image }}" alt="{{ $transaction->project->symbol }}" class="w-6 h-6 mr-2 rounded-full">
                                @endif
                                <div>
                                    <div class="font-medium">{{ $transaction->project->symbol }}</div>
                                    <div class="text-xs text-gray-500">{{ $transaction->project->name }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            @if($transaction->transaction_type == 'buy')
                                <span class="clay-badge clay-badge-success">
                                    <i class="fas fa-shopping-cart mr-1"></i> BUY
                                </span>
                            @else
                                <span class="clay-badge clay-badge-danger">
                                    <i class="fas fa-hand-holding-usd mr-1"></i> SELL
                                </span>
                            @endif
                        </td>
                        <td class="py-3 px-4 font-medium">{{ number_format($transaction->amount, 6) }}</td>
                        <td class="py-3 px-4">${{ number_format($transaction->price, 4) }}</td>
                        <td class="py-3 px-4 font-medium">${{ number_format($transaction->total_value, 2) }}</td>
                        <td class="py-3 px-4">
                            @if($transaction->followed_recommendation)
                                <span class="clay-badge clay-badge-info py-1 px-2 text-xs">
                                    <i class="fas fa-robot mr-1"></i> Yes
                                </span>
                            @else
                                <span class="text-gray-500 text-xs">Manual</span>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex space-x-1">
                                @if($transaction->transaction_hash)
                                    <button onclick="viewTransactionHash('{{ $transaction->transaction_hash }}')"
                                            class="clay-badge clay-badge-secondary py-1 px-2 text-xs"
                                            title="View on blockchain explorer">
                                        <i class="fas fa-external-link-alt"></i>
                                    </button>
                                @endif
                                <a href="{{ route('panel.recommendations.project', $transaction->project_id) }}"
                                   class="clay-badge clay-badge-primary py-1 px-2 text-xs"
                                   title="View project details">
                                    <i class="fas fa-info-circle"></i>
                                </a>
                                <button onclick="duplicateTransaction({{ json_encode($transaction) }})"
                                        class="clay-badge clay-badge-success py-1 px-2 text-xs"
                                        title="Duplicate transaction">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-8 px-4 text-center text-gray-500">
                            <div class="text-center">
                                <i class="fas fa-cash-register text-4xl text-gray-400 mb-3"></i>
                                <p class="text-lg mb-2">Belum ada transaksi manual yang dicatat</p>
                                <p class="text-sm mb-4">Mulai mencatat aktivitas trading Anda secara manual</p>
                                <button onclick="document.getElementById('project_id').focus()"
                                        class="clay-button clay-button-success">
                                    <i class="fas fa-plus mr-2"></i> Catat Transaksi Pertama
                                </button>
                            </div>
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

    <!-- Tips dan Best Practices -->
    <div class="clay-card p-6">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-lightbulb mr-2 text-warning"></i>
            Tips Transaction Management
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="clay-card bg-success/10 p-4">
                <h3 class="font-bold mb-2 flex items-center">
                    <i class="fas fa-cash-register mr-2 text-success"></i>
                    Cara Seperti Kasir
                </h3>
                <ul class="text-sm space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2 flex-shrink-0"></i>
                        <span>Catat setiap transaksi segera setelah trading onchain</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2 flex-shrink-0"></i>
                        <span>Gunakan harga aktual dari exchange tempat Anda trading</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2 flex-shrink-0"></i>
                        <span>Simpan transaction hash untuk verifikasi onchain</span>
                    </li>
                </ul>
            </div>

            <div class="clay-card bg-info/10 p-4">
                <h3 class="font-bold mb-2 flex items-center">
                    <i class="fas fa-robot mr-2 text-info"></i>
                    AI vs Manual Decision
                </h3>
                <ul class="text-sm space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-info-circle text-info mt-1 mr-2 flex-shrink-0"></i>
                        <span>Tandai transaksi yang mengikuti rekomendasi AI sistem</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-info-circle text-info mt-1 mr-2 flex-shrink-0"></i>
                        <span>Bandingkan performa keputusan AI vs manual</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-info-circle text-info mt-1 mr-2 flex-shrink-0"></i>
                        <span>Evaluasi strategi mana yang lebih menguntungkan</span>
                    </li>
                </ul>
            </div>

            <div class="clay-card bg-warning/10 p-4">
                <h3 class="font-bold mb-2 flex items-center">
                    <i class="fas fa-link mr-2 text-warning"></i>
                    Integrasi Onchain
                </h3>
                <ul class="text-sm space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-exclamation-circle text-warning mt-1 mr-2 flex-shrink-0"></i>
                        <span>Data manual ini terpisah dari portfolio onchain real</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-exclamation-circle text-warning mt-1 mr-2 flex-shrink-0"></i>
                        <span>Gunakan untuk tracking dan analisis performance</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-exclamation-circle text-warning mt-1 mr-2 flex-shrink-0"></i>
                        <span>Portfolio onchain menunjukkan holding real dari wallet</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Quick Guide -->
        <div class="mt-6 clay-card bg-primary/10 p-4">
            <h3 class="font-bold mb-3 flex items-center">
                <i class="fas fa-graduation-cap mr-2 text-primary"></i>
                Quick Start Guide
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <div class="font-medium mb-2">1. Pilih Cryptocurrency</div>
                    <p class="text-gray-600 mb-3">Pilih dari daftar proyek yang tersedia di sistem</p>

                    <div class="font-medium mb-2">2. Tentukan Tipe Transaksi</div>
                    <p class="text-gray-600 mb-3">BUY untuk pembelian, SELL untuk penjualan</p>
                </div>
                <div>
                    <div class="font-medium mb-2">3. Input Jumlah & Harga</div>
                    <p class="text-gray-600 mb-3">Masukkan sesuai dengan transaksi real Anda</p>

                    <div class="font-medium mb-2">4. Catat Hash (Opsional)</div>
                    <p class="text-gray-600 mb-3">Untuk verifikasi dan tracking onchain</p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    let lastTransaction = null;

    document.addEventListener('DOMContentLoaded', function() {
        // Auto-populate project ketika ada parameter add_project
        const urlParams = new URLSearchParams(window.location.search);
        const addProjectId = urlParams.get('add_project');

        if (addProjectId) {
            const projectSelect = document.getElementById('project_id');
            const option = projectSelect.querySelector(`option[value="${addProjectId}"]`);

            if (option) {
                projectSelect.value = addProjectId;

                // Update alert message dengan nama proyek yang sebenarnya
                const projectName = option.dataset.name + ' (' + option.dataset.symbol + ')';
                const selectedProjectNameEl = document.getElementById('selected-project-name');
                if (selectedProjectNameEl) {
                    selectedProjectNameEl.textContent = projectName;
                }

                // Focus ke field berikutnya
                setTimeout(() => {
                    const amountField = document.getElementById('amount');
                    if (amountField) {
                        amountField.focus();
                    }
                }, 100);
            }
        }

        // Transaction type radio buttons
        const radios = document.querySelectorAll('input[name="transaction_type"]');
        for (const radio of radios) {
            radio.addEventListener('change', function(event) {
                const cards = document.querySelectorAll('.transaction-type-card');
                cards.forEach(card => {
                    card.classList.remove('border-success', 'border-danger');
                });

                if (event.target.value === 'buy') {
                    event.target.closest('.transaction-type-card').classList.add('border-success');
                } else {
                    event.target.closest('.transaction-type-card').classList.add('border-danger');
                }
            });
        }

        // Trigger change for the default checked radio
        const defaultRadio = document.querySelector('input[name="transaction_type"]:checked');
        if (defaultRadio) {
            defaultRadio.dispatchEvent(new Event('change'));
        }

        // Calculate total value on amount/price change
        const amountInput = document.getElementById('amount');
        const priceInput = document.getElementById('price');
        const totalValueDisplay = document.getElementById('total-value');

        function calculateTotal() {
            const amount = parseFloat(amountInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const total = amount * price;

            totalValueDisplay.textContent = ' + total.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            // Change color based on transaction type
            const transactionType = document.querySelector('input[name="transaction_type"]:checked').value;
            totalValueDisplay.className = transactionType === 'buy' ? 'text-xl font-bold text-success' : 'text-xl font-bold text-danger';
        }

        amountInput.addEventListener('input', calculateTotal);
        priceInput.addEventListener('input', calculateTotal);

        // Also trigger on transaction type change
        radios.forEach(radio => {
            radio.addEventListener('change', calculateTotal);
        });
    });

    // Use current market price
    function useCurrentPrice() {
        const projectSelect = document.getElementById('project_id');
        const priceInput = document.getElementById('price');
        const selectedOption = projectSelect.options[projectSelect.selectedIndex];

        if (selectedOption && selectedOption.dataset.price) {
            priceInput.value = parseFloat(selectedOption.dataset.price).toFixed(6);
            priceInput.dispatchEvent(new Event('input'));
            showNotification('Current market price applied', 'success');
        } else {
            showNotification('Please select a project first', 'warning');
        }
    }

    // Quick add transaction for frequently traded projects
    function quickAddTransaction(projectId, projectName, projectSymbol) {
        const projectSelect = document.getElementById('project_id');
        projectSelect.value = projectId;

        // Focus to amount field
        setTimeout(() => {
            document.getElementById('amount').focus();
        }, 100);

        showNotification(`${projectSymbol} selected for quick entry`, 'info');
    }

    // Clear form
    function clearForm() {
        document.getElementById('transaction-form').reset();
        document.getElementById('project_id').value = '';
        document.getElementById('total-value').textContent = '$0.00';

        // Reset transaction type styling
        const defaultRadio = document.querySelector('input[name="transaction_type"][value="buy"]');
        if (defaultRadio) {
            defaultRadio.checked = true;
            defaultRadio.dispatchEvent(new Event('change'));
        }

        showNotification('Form cleared', 'info');
    }

    // Copy last transaction data
    function copyLastTransaction() {
        if (lastTransaction) {
            document.getElementById('project_id').value = lastTransaction.project_id;
            document.getElementById('amount').value = lastTransaction.amount;
            document.getElementById('price').value = lastTransaction.price;

            const transactionTypeRadio = document.querySelector(`input[name="transaction_type"][value="${lastTransaction.transaction_type}"]`);
            if (transactionTypeRadio) {
                transactionTypeRadio.checked = true;
                transactionTypeRadio.dispatchEvent(new Event('change'));
            }

            showNotification('Last transaction data copied', 'success');
        } else {
            showNotification('No previous transaction to copy', 'warning');
        }
    }

    // Duplicate transaction (from table row)
    function duplicateTransaction(transaction) {
        document.getElementById('project_id').value = transaction.project_id;
        document.getElementById('amount').value = transaction.amount;
        document.getElementById('price').value = transaction.price;

        const transactionTypeRadio = document.querySelector(`input[name="transaction_type"][value="${transaction.transaction_type}"]`);
        if (transactionTypeRadio) {
            transactionTypeRadio.checked = true;
            transactionTypeRadio.dispatchEvent(new Event('change'));
        }

        // Scroll to form
        document.getElementById('transaction-form').scrollIntoView({ behavior: 'smooth' });

        showNotification('Transaction data duplicated to form', 'success');
    }

    // View transaction hash on explorer
    function viewTransactionHash(hash) {
        if (hash.startsWith('0x')) {
            // Ethereum-like hash
            window.open(`https://etherscan.io/tx/${hash}`, '_blank');
        } else {
            // Copy to clipboard as fallback
            navigator.clipboard.writeText(hash).then(function() {
                showNotification('Transaction hash copied to clipboard', 'info');
            });
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

        // Auto remove after 4 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 4000);
    }

    // Save form data as last transaction when form is submitted
    document.getElementById('transaction-form').addEventListener('submit', function() {
        lastTransaction = {
            project_id: document.getElementById('project_id').value,
            amount: document.getElementById('amount').value,
            price: document.getElementById('price').value,
            transaction_type: document.querySelector('input[name="transaction_type"]:checked').value
        };
    });
</script>
@endpush
@endsection

@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <h1 class="text-3xl font-bold mb-4 flex items-center">
            <div class="bg-warning/20 p-2 clay-badge mr-3">
                <i class="fas fa-bell text-warning"></i>
            </div>
            Price Alerts
        </h1>
        <p class="text-lg">
            Atur dan kelola notifikasi alert harga untuk proyek cryptocurrency yang Anda minati.
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
        <!-- Alert Stats -->
        <div class="lg:col-span-2">
            <div class="clay-card p-6">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-chart-bar mr-2 text-primary"></i>
                    Statistik Alert
                </h2>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    @php
                        $activeCount = 0;
                        $triggeredCount = 0;
                        $aboveCount = 0;
                        $belowCount = 0;

                        if(isset($alertStats)) {
                            foreach($alertStats as $stat) {
                                if($stat->is_triggered == 0) {
                                    $activeCount += $stat->count;

                                    if($stat->alert_type == 'above') {
                                        $aboveCount += $stat->count;
                                    } else if($stat->alert_type == 'below') {
                                        $belowCount += $stat->count;
                                    }
                                } else {
                                    $triggeredCount += $stat->count;
                                }
                            }
                        }
                    @endphp

                    <div class="clay-card bg-success/10 p-4">
                        <div class="text-gray-600 text-sm">Alert Aktif</div>
                        <div class="text-2xl font-bold">{{ $activeCount }}</div>
                    </div>
                    <div class="clay-card bg-info/10 p-4">
                        <div class="text-gray-600 text-sm">Alert Terpicu</div>
                        <div class="text-2xl font-bold">{{ $triggeredCount }}</div>
                    </div>
                    <div class="clay-card bg-secondary/10 p-4">
                        <div class="text-gray-600 text-sm">Alert Above (Naik)</div>
                        <div class="text-2xl font-bold">{{ $aboveCount }}</div>
                    </div>
                    <div class="clay-card bg-warning/10 p-4">
                        <div class="text-gray-600 text-sm">Alert Below (Turun)</div>
                        <div class="text-2xl font-bold">{{ $belowCount }}</div>
                    </div>
                </div>

                <!-- Triggered Alerts -->
                <h3 class="font-bold mb-3 flex items-center">
                    <i class="fas fa-bell-slash mr-2 text-info"></i>
                    Alert yang Terpicu Terbaru
                </h3>
                <div class="overflow-x-auto mb-6">
                    <table class="clay-table min-w-full">
                        <thead>
                            <tr>
                                <th class="py-2 px-4 text-left">Proyek</th>
                                <th class="py-2 px-4 text-left">Tipe Alert</th>
                                <th class="py-2 px-4 text-left">Target Harga</th>
                                <th class="py-2 px-4 text-left">Terpicu Pada</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($triggeredAlerts ?? [] as $alert)
                            <tr>
                                <td class="py-2 px-4">
                                    <div class="flex items-center">
                                        @if($alert->project->image)
                                            <img src="{{ $alert->project->image }}" alt="{{ $alert->project->symbol }}" class="w-6 h-6 mr-2 rounded-full">
                                        @endif
                                        <span class="font-medium">{{ $alert->project->name }} ({{ $alert->project->symbol }})</span>
                                    </div>
                                </td>
                                <td class="py-2 px-4">
                                    @if($alert->alert_type == 'above')
                                        <span class="clay-badge clay-badge-secondary py-1 px-2">Naik Di Atas Target</span>
                                    @else
                                        <span class="clay-badge clay-badge-warning py-1 px-2">Turun Di Bawah Target</span>
                                    @endif
                                </td>
                                <td class="py-2 px-4 font-medium">${{ number_format($alert->target_price, 4) }}</td>
                                <td class="py-2 px-4 text-sm text-gray-600">{{ $alert->triggered_at ? $alert->triggered_at->format('j M Y H:i') : '-' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="py-4 px-4 text-center text-gray-500">Belum ada alert yang terpicu</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Active Alerts Summary -->
                <h3 class="font-bold mb-3 flex items-center">
                    <i class="fas fa-chart-pie mr-2 text-secondary"></i>
                    Distribusi Alert Aktif
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="clay-card bg-secondary/10 p-4">
                        <h4 class="font-medium mb-2">Berdasarkan Tipe</h4>
                        <div class="clay-progress h-4 rounded-full overflow-hidden mb-3">
                            @if(($aboveCount + $belowCount) > 0)
                                <div class="clay-progress-bar bg-secondary" style="width: {{ ($aboveCount / ($aboveCount + $belowCount) * 100) }}%"></div>
                                <div class="clay-progress-bar bg-warning" style="width: {{ ($belowCount / ($aboveCount + $belowCount) * 100) }}%"></div>
                            @else
                                <div class="clay-progress-bar bg-gray-300" style="width: 100%"></div>
                            @endif
                        </div>
                        <div class="flex text-sm justify-between">
                            <div>
                                <i class="fas fa-arrow-up text-secondary mr-1"></i> Above: {{ $aboveCount }}
                            </div>
                            <div>
                                <i class="fas fa-arrow-down text-warning mr-1"></i> Below: {{ $belowCount }}
                            </div>
                        </div>
                    </div>

                    <div class="clay-card bg-primary/10 p-4">
                        <h4 class="font-medium mb-2">Top Projects dengan Alert</h4>
                        @php
                            $projectCounter = [];
                            foreach($activeAlerts ?? [] as $alert) {
                                $projectId = $alert->project->id;
                                if(!isset($projectCounter[$projectId])) {
                                    $projectCounter[$projectId] = [
                                        'name' => $alert->project->name,
                                        'symbol' => $alert->project->symbol,
                                        'count' => 0
                                    ];
                                }
                                $projectCounter[$projectId]['count']++;
                            }
                            arsort($projectCounter);
                            $projectCounter = array_slice($projectCounter, 0, 3, true);
                        @endphp

                        @if(count($projectCounter) > 0)
                            <ul class="space-y-2 text-sm">
                                @foreach($projectCounter as $project)
                                <li class="flex justify-between">
                                    <span>{{ $project['symbol'] }}</span>
                                    <span class="font-medium">{{ $project['count'] }} alert</span>
                                </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-gray-500">Tidak ada data yang tersedia</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Alert -->
        <div class="lg:col-span-1">
            <div class="clay-card p-6 sticky top-24">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-plus-circle mr-2 text-success"></i>
                    Tambah Price Alert
                </h2>

                <form action="{{ route('panel.portfolio.add-price-alert') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="project_id" class="block font-medium mb-2">Proyek</label>
                        <select name="project_id" id="project_id" class="clay-select" required>
                            <option value="">-- Pilih Proyek --</option>
                            @foreach(\App\Models\Project::orderBy('name')->get() as $project)
                                <option value="{{ $project->id }}" data-price="{{ $project->current_price }}">
                                    {{ $project->name }} ({{ $project->symbol }}) - ${{ number_format($project->current_price, 4) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="alert_type" class="block font-medium mb-2">Tipe Alert</label>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="clay-card bg-secondary/10 p-3 cursor-pointer">
                                <input type="radio" name="alert_type" value="above" class="sr-only" checked>
                                <div class="flex items-center justify-center">
                                    <i class="fas fa-arrow-circle-up text-secondary mr-2"></i>
                                    <span class="font-medium">Above</span>
                                </div>
                                <p class="text-xs text-center mt-1">Alert ketika harga naik di atas target</p>
                            </label>
                            <label class="clay-card bg-warning/10 p-3 cursor-pointer">
                                <input type="radio" name="alert_type" value="below" class="sr-only">
                                <div class="flex items-center justify-center">
                                    <i class="fas fa-arrow-circle-down text-warning mr-2"></i>
                                    <span class="font-medium">Below</span>
                                </div>
                                <p class="text-xs text-center mt-1">Alert ketika harga turun di bawah target</p>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label for="target_price" class="block font-medium mb-2">Target Harga ($)</label>
                        <input type="number" name="target_price" id="target_price" class="clay-input" step="0.000001" min="0.000001" required>
                        <div class="text-sm mt-1 text-gray-500">
                            <span id="percentageFromCurrent"></span>
                        </div>
                    </div>

                    <button type="submit" class="clay-button clay-button-success w-full py-2 mt-2">
                        <i class="fas fa-bell mr-1"></i> Tambah Alert
                    </button>
                </form>

                <div class="mt-6 clay-card bg-info/10 p-4 text-sm">
                    <div class="font-medium mb-2">
                        <i class="fas fa-info-circle mr-1 text-info"></i> Tentang Price Alerts
                    </div>
                    <p class="mb-2">
                        Price alerts akan memberi tahu Anda ketika harga cryptocurrency mencapai target yang Anda tentukan,
                        membantu Anda melakukan aksi pada saat yang tepat.
                    </p>
                    <ul class="space-y-1 ml-4 list-disc">
                        <li>Alerts "Above" berguna untuk mengambil keuntungan saat harga naik</li>
                        <li>Alerts "Below" berguna untuk membeli saat harga turun ke level yang diinginkan</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Alerts List -->
    <div class="clay-card p-6">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-bell mr-2 text-warning"></i>
            Alert Aktif
        </h2>

        <div class="overflow-x-auto">
            <table class="clay-table min-w-full">
                <thead>
                    <tr>
                        <th class="py-2 px-4 text-left">Proyek</th>
                        <th class="py-2 px-4 text-left">Tipe Alert</th>
                        <th class="py-2 px-4 text-left">Target Harga</th>
                        <th class="py-2 px-4 text-left">Harga Saat Ini</th>
                        <th class="py-2 px-4 text-left">Jarak ke Target</th>
                        <th class="py-2 px-4 text-left">Dibuat Pada</th>
                        <th class="py-2 px-4 text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeAlerts ?? [] as $alert)
                    <tr>
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                @if($alert->project->image)
                                    <img src="{{ $alert->project->image }}" alt="{{ $alert->project->symbol }}" class="w-6 h-6 mr-2 rounded-full">
                                @endif
                                <span class="font-medium">{{ $alert->project->name }} ({{ $alert->project->symbol }})</span>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            @if($alert->alert_type == 'above')
                                <span class="clay-badge clay-badge-secondary">Above</span>
                            @else
                                <span class="clay-badge clay-badge-warning">Below</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 font-medium">${{ number_format($alert->target_price, 4) }}</td>
                        <td class="py-3 px-4">${{ number_format($alert->project->current_price, 4) }}</td>
                        <td class="py-3 px-4">
                            @php
                                $percentToTarget = (($alert->target_price - $alert->project->current_price) / $alert->project->current_price) * 100;
                                $distance = abs($percentToTarget);
                                $direction = $percentToTarget > 0 ? 'naik' : 'turun';
                            @endphp
                            <span class="{{ $percentToTarget > 0 ? 'text-success' : 'text-danger' }}">
                                {{ $direction }} {{ number_format($distance, 2) }}%
                            </span>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-600">{{ $alert->created_at->format('j M Y H:i') }}</td>
                        <td class="py-3 px-4">
                            <div class="flex space-x-2">
                                <a href="{{ route('panel.recommendations.project', $alert->project_id) }}" class="clay-badge clay-badge-info py-1 px-2 text-xs">
                                    <i class="fas fa-chart-line"></i>
                                </a>
                                <form method="POST" action="{{ route('panel.portfolio.delete-price-alert', $alert->id) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="clay-badge clay-badge-danger py-1 px-2 text-xs" onclick="return confirm('Apakah Anda yakin ingin menghapus alert ini?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-6 px-4 text-center text-gray-500">
                            <p>Belum ada price alert yang aktif.</p>
                            <p class="text-sm mt-2">Tambahkan alert baru menggunakan form di samping.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Alert Best Practices -->
    <div class="clay-card p-6 mt-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-lightbulb mr-2 text-warning"></i>
            Tips Penggunaan Price Alert
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="clay-card bg-secondary/10 p-4">
                <h3 class="font-bold mb-2">Strategi "Above" Alert</h3>
                <ul class="text-sm space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-arrow-circle-up text-secondary mt-1 mr-2"></i>
                        <span>Set alert di atas resistance level untuk konfirmasi breakout</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-arrow-circle-up text-secondary mt-1 mr-2"></i>
                        <span>Gunakan untuk mengambil keuntungan pada target harga yang diinginkan</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-arrow-circle-up text-secondary mt-1 mr-2"></i>
                        <span>Alert pada ATH (All-Time High) untuk memantau momentum yang kuat</span>
                    </li>
                </ul>
            </div>

            <div class="clay-card bg-warning/10 p-4">
                <h3 class="font-bold mb-2">Strategi "Below" Alert</h3>
                <ul class="text-sm space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-arrow-circle-down text-warning mt-1 mr-2"></i>
                        <span>Set alert pada support level untuk peluang entry</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-arrow-circle-down text-warning mt-1 mr-2"></i>
                        <span>Atur alert pada level diskon saat koreksi pasar</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-arrow-circle-down text-warning mt-1 mr-2"></i>
                        <span>Alert ketika harga jatuh di bawah trendline untuk manajemen risiko</span>
                    </li>
                </ul>
            </div>

            <div class="clay-card bg-info/10 p-4">
                <h3 class="font-bold mb-2">Praktik Terbaik</h3>
                <ul class="text-sm space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-info mt-1 mr-2"></i>
                        <span>Jangan terlalu banyak alert untuk menghindari notifikasi berlebihan</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-info mt-1 mr-2"></i>
                        <span>Gunakan analisis teknikal untuk menentukan level signifikan</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-info mt-1 mr-2"></i>
                        <span>Set alert dengan jarak realistis dari harga saat ini</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-info mt-1 mr-2"></i>
                        <span>Evaluasi dan perbarui alert yang tidak terpicu dalam waktu lama</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Alert type radio buttons
        const radios = document.querySelectorAll('input[name="alert_type"]');
        for (const radio of radios) {
            radio.addEventListener('change', function(event) {
                const cards = document.querySelectorAll('.clay-card.bg-secondary\\/10, .clay-card.bg-warning\\/10');
                cards.forEach(card => {
                    card.classList.remove('border-2', 'border-secondary', 'border-warning');
                });

                if (event.target.value === 'above') {
                    event.target.closest('.clay-card').classList.add('border-2', 'border-secondary');
                } else {
                    event.target.closest('.clay-card').classList.add('border-2', 'border-warning');
                }
            });
        }

        // Trigger change for the default checked radio
        document.querySelector('input[name="alert_type"]:checked').dispatchEvent(new Event('change'));

        // Calculate percentage from current price
        const projectSelect = document.getElementById('project_id');
        const targetPrice = document.getElementById('target_price');
        const percentageDisplay = document.getElementById('percentageFromCurrent');

        function updatePercentage() {
            const selectedOption = projectSelect.options[projectSelect.selectedIndex];
            if (selectedOption && selectedOption.value && targetPrice.value) {
                const currentPrice = parseFloat(selectedOption.dataset.price);
                const targetValue = parseFloat(targetPrice.value);

                if (!isNaN(currentPrice) && !isNaN(targetValue) && currentPrice > 0) {
                    const percent = ((targetValue - currentPrice) / currentPrice) * 100;
                    const direction = percent > 0 ? 'naik' : 'turun';

                    percentageDisplay.innerHTML = `Target ${direction} <strong>${Math.abs(percent).toFixed(2)}%</strong> dari harga saat ini`;
                    percentageDisplay.className = percent > 0 ? 'text-success' : 'text-danger';
                }
            } else {
                percentageDisplay.innerHTML = '';
            }
        }

        projectSelect.addEventListener('change', updatePercentage);
        targetPrice.addEventListener('input', updatePercentage);
    });
</script>
@endpush
@endsection

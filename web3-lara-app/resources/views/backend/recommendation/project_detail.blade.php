@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    @if($isColdStart)
        <div class="clay-alert clay-alert-info mb-6">
            <p class="flex items-center"><i class="fas fa-info-circle mr-2"></i> Anda terdeteksi sebagai pengguna baru. Rekomendasi akan lebih akurat setelah Anda berinteraksi dengan lebih banyak proyek.</p>
        </div>
    @endif

    @if(isset($projectInDb) && !$projectInDb && $project)
        <div class="clay-alert clay-alert-warning mb-6">
            <p class="flex items-center"><i class="fas fa-exclamation-triangle mr-2"></i> Proyek ini belum tersimpan dalam sistem kami. Beberapa fitur seperti favorit dan portfolio mungkin belum tersedia.</p>
        </div>
    @endif

    @if(!$project)
        <div class="clay-card p-6 text-center">
            <div class="text-7xl text-danger mb-4"><i class="fas fa-project-diagram"></i></div>
            <h2 class="text-2xl font-bold mb-2">Proyek Tidak Ditemukan</h2>
            <p class="mb-4">Maaf, proyek dengan ID "{{ request()->route('id') }}" tidak ditemukan dalam database kami.</p>
            <p class="mb-6">Mungkin proyek ini belum tersedia atau telah dihapus. Silakan jelajahi proyek lain yang tersedia.</p>

            <div class="flex justify-center space-x-4">
                <a href="{{ route('panel.recommendations.trending') }}" class="clay-button clay-button-primary py-2 px-4">
                    <i class="fas fa-chart-line mr-2"></i> Lihat Trending
                </a>
                <a href="{{ route('panel.recommendations') }}" class="clay-button clay-button-secondary py-2 px-4">
                    <i class="fas fa-home mr-2"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>
    @else
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Project Info Column -->
        <div class="lg:col-span-2">
            <!-- Project Header -->
            <div class="clay-card p-6 mb-6">
                <div class="flex flex-col sm:flex-row items-start sm:items-center mb-4">
                    @if($project->image)
                        <img src="{{ $project->image }}" alt="{{ $project->symbol }}" class="w-16 h-16 rounded-full mr-4 mb-3 sm:mb-0">
                    @endif
                    <div>
                        <h1 class="text-3xl font-bold">{{ $project->name }}</h1>
                        <div class="flex items-center mt-1">
                            <span class="clay-badge clay-badge-warning font-bold mr-2">{{ $project->symbol }}</span>
                            @if($project->primary_category)
                                <span class="clay-badge clay-badge-info mr-2">{{ $project->primary_category }}</span>
                            @endif
                            @if($project->chain)
                                <span class="clay-badge clay-badge-secondary">{{ $project->chain }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-4 mt-4">
                    <a href="{{ route('panel.recommendations.project', $project->id) }}" class="clay-button clay-button-primary py-1.5 px-3 text-sm">
                        <i class="fas fa-sync-alt mr-1"></i> Refresh
                    </a>
                    @if(isset($projectInDb) && $projectInDb)
                    <form method="POST" action="{{ route('panel.recommendations.add-favorite') }}" class="inline">
                        @csrf
                        <input type="hidden" name="project_id" value="{{ $project->id }}">
                        <button type="submit" class="clay-button clay-button-secondary py-1.5 px-3 text-sm">
                            <i class="fas fa-heart mr-1"></i> Tambah ke Favorit
                        </button>
                    </form>
                    <a href="#" class="clay-button clay-button-success py-1.5 px-3 text-sm">
                        <i class="fas fa-wallet mr-1"></i> Tambah ke Portfolio
                    </a>
                    @else
                    <a href="#" class="clay-button clay-button-secondary py-1.5 px-3 text-sm disabled" onclick="alert('Proyek harus ada di database lokal untuk ditambahkan ke favorit.'); return false;">
                        <i class="fas fa-heart mr-1"></i> Tambah ke Favorit
                    </a>
                    <a href="#" class="clay-button clay-button-success py-1.5 px-3 text-sm disabled" onclick="alert('Proyek harus ada di database lokal untuk ditambahkan ke portfolio.'); return false;">
                        <i class="fas fa-wallet mr-1"></i> Tambah ke Portfolio
                    </a>
                    @endif
                </div>
            </div>

            <!-- Price Statistics -->
            <div class="clay-card p-6 mb-6">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-chart-line mr-2 text-primary"></i>
                    Statistik Harga
                </h2>

                <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                    <div class="clay-card bg-primary/10 p-3">
                        <div class="text-sm text-gray-600">Harga Saat Ini</div>
                        <div class="text-xl font-bold">{{ $project->formatted_price ?? '$'.number_format($project->current_price, 2) }}</div>
                    </div>
                    <div class="clay-card bg-{{ $project->price_change_percentage_24h >= 0 ? 'success' : 'danger' }}/10 p-3">
                        <div class="text-sm text-gray-600">Perubahan 24h</div>
                        <div class="text-xl font-bold {{ $project->price_change_percentage_24h >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $project->price_change_percentage_24h >= 0 ? '+' : '' }}{{ number_format($project->price_change_percentage_24h, 2) }}%
                        </div>
                    </div>
                    <div class="clay-card bg-secondary/10 p-3">
                        <div class="text-sm text-gray-600">Perubahan 7d</div>
                        <div class="text-xl font-bold {{ $project->price_change_percentage_7d_in_currency >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $project->price_change_percentage_7d_in_currency >= 0 ? '+' : '' }}{{ number_format($project->price_change_percentage_7d_in_currency, 2) }}%
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <div class="text-sm text-gray-600">Market Cap</div>
                        <div class="font-medium">{{ $project->formatted_market_cap ?? ('$'.number_format($project->market_cap, 0)) }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-600">Volume 24h</div>
                        <div class="font-medium">${{ number_format($project->total_volume, 0) }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-600">Popularitas</div>
                        <div class="font-medium flex items-center">
                            <span class="mr-1">{{ number_format($project->popularity_score, 1) }}</span>
                            <div class="clay-progress w-12 h-2">
                                <div class="clay-progress-bar clay-progress-primary" style="width: {{ min(100, $project->popularity_score) }}%"></div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-600">Trend Score</div>
                        <div class="font-medium flex items-center">
                            <span class="mr-1">{{ number_format($project->trend_score, 1) }}</span>
                            <div class="clay-progress w-12 h-2">
                                <div class="clay-progress-bar clay-progress-secondary" style="width: {{ min(100, $project->trend_score) }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Project Description -->
            <div class="clay-card p-6 mb-6">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-info-circle mr-2 text-info"></i>
                    Deskripsi Proyek
                </h2>
                <p>{{ $project->description ?? 'Tidak ada deskripsi tersedia untuk proyek ini.' }}</p>

                @if(isset($project->is_from_api) && $project->is_from_api)
                    <div class="clay-alert clay-alert-info mt-4">
                        <p class="text-sm"><i class="fas fa-info-circle mr-1"></i> Data proyek ini diambil dari API eksternal. Beberapa informasi mungkin tidak lengkap.</p>
                    </div>
                @endif
            </div>

            <!-- Bagian konten lainnya (Social metrics, dll) tetap sama seperti sebelumnya -->
            <!-- ... -->

            <!-- Similar Projects -->
            @if(!empty($similarProjects))
            <div class="clay-card p-6 mb-6">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-project-diagram mr-2 text-warning"></i>
                    Proyek Serupa
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @forelse($similarProjects as $similarProject)
                    <div class="clay-card p-3 hover:translate-y-[-5px] transition-transform">
                        <div class="flex items-center mb-2">
                            @if(isset($similarProject['image']) && $similarProject['image'])
                                <img src="{{ $similarProject['image'] }}" alt="{{ $similarProject['symbol'] }}" class="w-8 h-8 rounded-full mr-2">
                            @endif
                            <div class="font-bold truncate">{{ $similarProject['name'] ?? $similarProject->name }} ({{ $similarProject['symbol'] ?? $similarProject->symbol }})</div>
                        </div>
                        <div class="text-sm mb-2 flex justify-between">
                            <span>{{ isset($similarProject['current_price']) ? '$'.number_format($similarProject['current_price'], 2) : '' }}</span>
                            @if(isset($similarProject['price_change_percentage_24h']))
                            <span class="{{ $similarProject['price_change_percentage_24h'] >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $similarProject['price_change_percentage_24h'] >= 0 ? '+' : '' }}{{ number_format($similarProject['price_change_percentage_24h'], 2) }}%
                            </span>
                            @endif
                        </div>
                        <a href="{{ route('panel.recommendations.project', $similarProject['id'] ?? $similarProject->id) }}" class="clay-badge clay-badge-info py-1 px-2 text-xs">
                            <i class="fas fa-info-circle mr-1"></i> Detail
                        </a>
                    </div>
                    @empty
                    <div class="col-span-full text-center py-6">
                        <p>Tidak ada data proyek serupa</p>
                    </div>
                    @endforelse
                </div>
            </div>
            @endif
        </div>

        <!-- Trading Signals Column -->
        <div class="lg:col-span-1">
            <!-- Trading Signals -->
            <div class="clay-card p-6 mb-6 sticky top-24">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-signal mr-2 text-success"></i>
                    Sinyal Trading
                </h2>

                @if(isset($tradingSignals) && !empty($tradingSignals))
                <div class="clay-card {{ $tradingSignals['action'] == 'buy' ? 'bg-success/10' : ($tradingSignals['action'] == 'sell' ? 'bg-danger/10' : 'bg-warning/10') }} p-4 mb-4">
                    <div class="text-center">
                        <div class="mb-2">
                            @if($tradingSignals['action'] == 'buy')
                                <i class="fas fa-arrow-circle-up text-5xl text-success"></i>
                            @elseif($tradingSignals['action'] == 'sell')
                                <i class="fas fa-arrow-circle-down text-5xl text-danger"></i>
                            @else
                                <i class="fas fa-minus-circle text-5xl text-warning"></i>
                            @endif
                        </div>
                        <div class="font-bold text-2xl capitalize">{{ $tradingSignals['action'] }}</div>
                        <div class="text-sm mb-2">Kepercayaan: {{ number_format(($tradingSignals['confidence'] ?? 0.5) * 100, 0) }}%</div>

                        @if(isset($tradingSignals['target_price']) && $tradingSignals['target_price'] > 0)
                        <div class="clay-badge {{ $tradingSignals['action'] == 'buy' ? 'clay-badge-success' : 'clay-badge-warning' }} py-1 px-2">
                            Target: ${{ number_format($tradingSignals['target_price'], 2) }}
                        </div>
                        @endif
                    </div>
                </div>

                @if(isset($tradingSignals['evidence']) && !empty($tradingSignals['evidence']))
                <div class="clay-card bg-info/5 p-4 mb-4">
                    <div class="font-medium mb-2">Indikasi Sinyal:</div>
                    <ul class="space-y-2 text-sm">
                        @foreach($tradingSignals['evidence'] as $evidence)
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-info mt-1 mr-2"></i>
                                <span>{{ $evidence }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                @if(isset($tradingSignals['personalized_message']))
                <div class="clay-card bg-secondary/5 p-4 mb-4">
                    <div class="font-medium mb-2">Pesan Personal:</div>
                    <p class="text-sm">{{ $tradingSignals['personalized_message'] }}</p>
                </div>
                @endif

                @if(isset($tradingSignals['indicators']) && !empty($tradingSignals['indicators']))
                <div class="clay-card bg-primary/5 p-4">
                    <div class="font-medium mb-2">Indikator Teknikal:</div>
                    <div class="space-y-2 text-sm">
                        @foreach($tradingSignals['indicators'] as $indicator => $value)
                        <div class="flex justify-between">
                            <span class="uppercase">{{ $indicator }}</span>
                            <span class="font-medium">{{ is_numeric($value) ? number_format($value, 2) : $value }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @else
                <div class="clay-alert clay-alert-info">
                    <p>Tidak ada sinyal trading yang tersedia saat ini.</p>
                </div>
                @endif

                <!-- Set Price Alert -->
                <div class="mt-6">
                    <div class="font-bold mb-3">Set Price Alert</div>
                    <form action="{{ route('panel.portfolio.add-price-alert') }}" method="POST" class="space-y-3">
                        @csrf
                        <input type="hidden" name="project_id" value="{{ $project->id }}">

                        <div>
                            <label for="target_price" class="text-sm">Target Price ($)</label>
                            <input type="number" name="target_price" id="target_price" step="0.000001" min="0" class="clay-input mt-1" placeholder="{{ $project->current_price }}">
                        </div>

                        <div>
                            <label for="alert_type" class="text-sm">Alert Type</label>
                            <select name="alert_type" id="alert_type" class="clay-select mt-1">
                                <option value="above">Above Target</option>
                                <option value="below">Below Target</option>
                            </select>
                        </div>

                        <button type="submit" class="clay-button clay-button-warning w-full">
                            <i class="fas fa-bell mr-1"></i> Set Alert
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

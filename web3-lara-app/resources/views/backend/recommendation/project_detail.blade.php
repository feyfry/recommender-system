@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    @if($isColdStart ?? false)
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
                            <i class="fas fa-heart mr-1"></i> Sukai
                        </button>
                    </form>
                    <form method="POST" action="{{ route('panel.recommendations.add-portfolio') }}" class="inline">
                        @csrf
                        <input type="hidden" name="project_id" value="{{ $project->id }}">
                        <button type="submit" class="clay-button clay-button-success py-1.5 px-3 text-sm">
                            <i class="fas fa-wallet mr-1"></i> Tambah ke Portfolio
                        </button>
                    </form>
                    @else
                    <a href="#" class="clay-button clay-button-secondary py-1.5 px-3 text-sm disabled" onclick="alert('Proyek belum tersedia dalam sistem kami untuk Disukai.'); return false;">
                        <i class="fas fa-heart mr-1"></i> Sukai
                    </a>
                    <a href="#" class="clay-button clay-button-success py-1.5 px-3 text-sm disabled" onclick="alert('Proyek belum tersedia dalam sistem kami untuk Ditambahkan ke Portfolio.'); return false;">
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
                        <div class="font-medium">${{ number_format($project->market_cap, 0) }}</div>
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

            <!-- Similar Projects dengan Lazy Loading -->
            <div class="clay-card p-6 mb-6" x-data="{ loading: true, similarProjects: [] }">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-project-diagram mr-2 text-warning"></i>
                    Proyek Serupa
                </h2>

                <!-- Loading Indicator -->
                <div x-show="loading" class="py-4 text-center">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-warning"></div>
                    <p class="mt-2 text-gray-500">Memuat proyek serupa...</p>
                </div>

                <!-- Similar Projects Content -->
                <div x-show="!loading" x-init="
                    @if(!empty($similarProjects))
                        similarProjects = {{ json_encode($similarProjects) }};
                        loading = false;
                    @else
                        fetch('{{ route('panel.recommendations.project', $project->id) }}?format=json&part=similar')
                            .then(response => response.json())
                            .then(data => {
                                similarProjects = data.similar_projects || [];
                                loading = false;
                            })
                            .catch(error => {
                                console.error('Error loading similar projects:', error);
                                loading = false;
                            });
                    @endif
                ">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <template x-for="(similarProject, index) in similarProjects" :key="index">
                            <div class="clay-card p-3 hover:translate-y-[-5px] transition-transform">
                                <div class="flex items-center mb-2">
                                    <template x-if="similarProject.image">
                                        <img :src="similarProject.image" :alt="similarProject.symbol" class="w-8 h-8 rounded-full mr-2" loading="lazy">
                                    </template>
                                    <div class="font-bold truncate" x-text="similarProject.name + ' (' + similarProject.symbol + ')'"></div>
                                </div>
                                <div class="text-sm mb-2 flex justify-between">
                                    <span x-text="'$' + (similarProject.current_price ? similarProject.current_price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 8}) : '0.00')"></span>
                                    <span :class="(similarProject.price_change_24h || 0) > 0 ? 'text-success' : 'text-danger'"
                                        x-text="((similarProject.price_change_24h || 0) > 0 ? '+' : '') +
                                                    ((similarProject.price_change_24h || 0).toFixed(8)) + '$'">
                                    </span>
                                </div>
                                <a :href="'/panel/recommendations/project/' + similarProject.id" class="clay-badge clay-badge-info py-1 px-2 text-xs">
                                    <i class="fas fa-info-circle mr-1"></i> Detail
                                </a>
                            </div>
                        </template>

                        <template x-if="similarProjects.length === 0">
                            <div class="col-span-full text-center py-6">
                                <p>Tidak ada data proyek serupa</p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Trading Signals Column dengan Lazy Loading -->
        <div class="lg:col-span-1" x-data="{
            tradingSignalsLoaded: false,
            tradingSignals: null,
            priceAlertSettings: {
                target_price: {{ $project->current_price ?? 0 }},
                alert_type: 'above'
            }
        }">
            <!-- Trading Signals -->
            <div class="clay-card p-6 mb-6 sticky top-24">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-signal mr-2 text-success"></i>
                    Sinyal Trading
                </h2>

                <!-- Loading Indicator -->
                <div x-show="!tradingSignalsLoaded" class="py-6 text-center">
                    <div class="inline-block animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-success"></div>
                    <p class="mt-3 text-gray-500">Memuat sinyal trading...</p>
                </div>

                <!-- Trading Signals Content -->
                <div x-show="tradingSignalsLoaded" x-init="
                    @if(isset($tradingSignals) && !empty($tradingSignals))
                        tradingSignals = {{ json_encode($tradingSignals) }};
                        tradingSignalsLoaded = true;
                    @else
                        setTimeout(() => {
                            fetch('{{ route('panel.recommendations.project', $project->id) }}?format=json&part=trading')
                                .then(response => response.json())
                                .then(data => {
                                    tradingSignals = data.trading_signals || null;
                                    tradingSignalsLoaded = true;
                                })
                                .catch(error => {
                                    console.error('Error loading trading signals:', error);
                                    tradingSignals = {
                                        action: 'hold',
                                        confidence: 0.5,
                                        evidence: ['Data tidak tersedia saat ini'],
                                        personalized_message: 'Data analisis teknikal tidak tersedia saat ini.'
                                    };
                                    tradingSignalsLoaded = true;
                                });
                        }, 300);
                    @endif
                ">
                    <template x-if="tradingSignals">
                        <div>
                            <div :class="'clay-card ' + (
                                tradingSignals.action == 'buy' ? 'bg-success/10' :
                                (tradingSignals.action == 'sell' ? 'bg-danger/10' : 'bg-warning/10')
                            ) + ' p-4 mb-4'">
                                <div class="text-center">
                                    <div class="mb-2">
                                        <template x-if="tradingSignals.action == 'buy'">
                                            <i class="fas fa-arrow-circle-up text-5xl text-success"></i>
                                        </template>
                                        <template x-if="tradingSignals.action == 'sell'">
                                            <i class="fas fa-arrow-circle-down text-5xl text-danger"></i>
                                        </template>
                                        <template x-if="tradingSignals.action != 'buy' && tradingSignals.action != 'sell'">
                                            <i class="fas fa-minus-circle text-5xl text-warning"></i>
                                        </template>
                                    </div>
                                    <div class="font-bold text-2xl capitalize" x-text="tradingSignals.action"></div>
                                    <div class="text-sm mb-2" x-text="'Kepercayaan: ' + Math.round((tradingSignals.confidence || 0.5) * 100) + '%'"></div>

                                    <template x-if="tradingSignals.target_price && tradingSignals.target_price > 0">
                                        <div :class="'clay-badge ' + (tradingSignals.action == 'buy' ? 'clay-badge-success' : 'clay-badge-warning') + ' py-1 px-2'"
                                            x-text="'Target: $' + tradingSignals.target_price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></div>
                                    </template>
                                </div>
                            </div>

                            <template x-if="tradingSignals.evidence && tradingSignals.evidence.length > 0">
                                <div class="clay-card bg-info/5 p-4 mb-4">
                                    <div class="font-medium mb-2">Indikasi Sinyal:</div>
                                    <ul class="space-y-2 text-sm">
                                        <template x-for="(evidence, index) in tradingSignals.evidence" :key="index">
                                            <li class="flex items-start">
                                                <i class="fas fa-check-circle text-info mt-1 mr-2"></i>
                                                <span x-text="evidence"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </template>

                            <template x-if="tradingSignals.personalized_message">
                                <div class="clay-card bg-secondary/5 p-4 mb-4">
                                    <div class="font-medium mb-2">Pesan Personal:</div>
                                    <p class="text-sm" x-text="tradingSignals.personalized_message"></p>
                                </div>
                            </template>

                            <template x-if="tradingSignals.indicators && Object.keys(tradingSignals.indicators).length > 0">
                                <div class="clay-card bg-primary/5 p-4">
                                    <div class="font-medium mb-2">Indikator Teknikal:</div>
                                    <div class="space-y-2 text-sm">
                                        <template x-for="(value, indicator) in tradingSignals.indicators" :key="indicator">
                                            <div class="flex justify-between">
                                                <span class="uppercase" x-text="indicator"></span>
                                                <span class="font-medium" x-text="typeof value === 'number' ? value.toFixed(2) : value"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="!tradingSignals">
                        <div class="clay-alert clay-alert-info">
                            <p>Tidak ada sinyal trading yang tersedia saat ini.</p>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

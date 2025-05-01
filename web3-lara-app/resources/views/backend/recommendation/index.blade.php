@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <h1 class="text-3xl font-bold mb-4 flex items-center">
            <div class="bg-primary/20 p-2 clay-badge mr-3">
                <i class="fas fa-star text-primary"></i>
            </div>
            Rekomendasi
        </h1>
        <p class="text-lg">
            Dapatkan rekomendasi proyek cryptocurrency berdasarkan popularitas, tren, dan preferensi personal Anda.
            Sistem kami menggunakan model hybrid untuk memberikan rekomendasi yang paling relevan.
        </p>
    </div>

    <!-- Navigation Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <a href="{{ route('panel.recommendations.personal') }}" class="clay-card p-6 hover:translate-y-[-5px] transition-transform">
            <div class="flex items-center mb-4">
                <div class="clay-rounded-full bg-secondary/20 p-3 mr-3">
                    <i class="fas fa-user text-secondary text-xl"></i>
                </div>
                <h2 class="text-xl font-bold">Personal</h2>
            </div>
            <p class="text-sm text-gray-600">
                Rekomendasi khusus untuk Anda berdasarkan interaksi dan preferensi Anda.
            </p>
        </a>

        <a href="{{ route('panel.recommendations.trending') }}" class="clay-card p-6 hover:translate-y-[-5px] transition-transform">
            <div class="flex items-center mb-4">
                <div class="clay-rounded-full bg-warning/20 p-3 mr-3">
                    <i class="fas fa-fire text-warning text-xl"></i>
                </div>
                <h2 class="text-xl font-bold">Trending</h2>
            </div>
            <p class="text-sm text-gray-600">
                Proyek-proyek yang sedang populer dan memiliki momentum pasar.
            </p>
        </a>

        <a href="{{ route('panel.recommendations.popular') }}" class="clay-card p-6 hover:translate-y-[-5px] transition-transform">
            <div class="flex items-center mb-4">
                <div class="clay-rounded-full bg-success/20 p-3 mr-3">
                    <i class="fas fa-trophy text-success text-xl"></i>
                </div>
                <h2 class="text-xl font-bold">Popular</h2>
            </div>
            <p class="text-sm text-gray-600">
                Proyek dengan popularitas tinggi berdasarkan metrik sosial dan penggunaan.
            </p>
        </a>

        <a href="{{ route('panel.recommendations.categories') }}" class="clay-card p-6 hover:translate-y-[-5px] transition-transform">
            <div class="flex items-center mb-4">
                <div class="clay-rounded-full bg-info/20 p-3 mr-3">
                    <i class="fas fa-tags text-info text-xl"></i>
                </div>
                <h2 class="text-xl font-bold">Kategori</h2>
            </div>
            <p class="text-sm text-gray-600">
                Temukan proyek berdasarkan kategori seperti DeFi, NFT, GameFi, dll.
            </p>
        </a>
    </div>

    <!-- Personal Recommendations Preview -->
    <div class="clay-card p-6 mb-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold flex items-center">
                <i class="fas fa-user-check mr-2 text-secondary"></i>
                Rekomendasi Personal
            </h2>
            <a href="{{ route('panel.recommendations.personal') }}" class="clay-button clay-button-secondary py-1.5 px-3 text-sm">
                Lihat Semua <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @forelse($personalRecommendations ?? [] as $index => $recommendation)
                @if($index < 4)
                <div class="clay-card p-4 hover:translate-y-[-5px] transition-transform">
                    <div class="font-bold text-lg mb-2">{{ $recommendation->name ?? $recommendation['name'] }} ({{ $recommendation->symbol ?? $recommendation['symbol'] }})</div>
                    <div class="text-sm mb-2">
                        {{ $recommendation->formatted_price ?? '$'.number_format($recommendation->price_usd ?? $recommendation['price_usd'], 2) }}
                        <span class="{{ ($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0) > 0 ? 'text-success' : 'text-danger' }}">
                            {{ ($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0) > 0 ? '+' : '' }}
                            {{ number_format($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0, 2) }}%
                        </span>
                    </div>
                    <div class="clay-badge clay-badge-secondary mb-3">
                        {{ $recommendation->primary_category ?? $recommendation['primary_category'] ?? 'Umum' }}
                    </div>
                    <p class="text-sm mb-3 line-clamp-2">
                        {{ $recommendation->description ?? $recommendation['description'] ?? 'Tidak ada deskripsi' }}
                    </p>
                    <div class="flex justify-between items-center">
                        <div class="text-xs font-medium">Score: <span class="text-secondary">
                            {{ number_format($recommendation->recommendation_score ?? $recommendation['recommendation_score'] ?? 0, 2) }}
                        </span></div>
                        <a href="{{ route('panel.recommendations.project', $recommendation->id ?? $recommendation['id']) }}" class="clay-button clay-button-secondary py-1 px-2 text-xs">
                            <i class="fas fa-info-circle mr-1"></i> Detail
                        </a>
                    </div>
                </div>
                @endif
            @empty
                <div class="col-span-full clay-card p-6 text-center">
                    <p>Tidak ada rekomendasi personal yang tersedia saat ini.</p>
                    <p class="text-sm mt-2 text-gray-500">Mulai berinteraksi dengan proyek untuk mendapatkan rekomendasi yang lebih baik.</p>
                    <a href="{{ route('panel.recommendations.trending') }}" class="clay-button clay-button-primary mt-4">Lihat Trending</a>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Trending Projects Preview -->
    <div class="clay-card p-6 mb-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold flex items-center">
                <i class="fas fa-fire mr-2 text-warning"></i>
                Proyek Trending
            </h2>
            <a href="{{ route('panel.recommendations.trending') }}" class="clay-button clay-button-warning py-1.5 px-3 text-sm">
                Lihat Semua <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="clay-table min-w-full">
                <thead>
                    <tr>
                        <th class="py-2 px-4 text-left">#</th>
                        <th class="py-2 px-4 text-left">Project</th>
                        <th class="py-2 px-4 text-left">Harga</th>
                        <th class="py-2 px-4 text-left">24h %</th>
                        <th class="py-2 px-4 text-left">7d %</th>
                        <th class="py-2 px-4 text-left">Trend Score</th>
                        <th class="py-2 px-4 text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($trendingProjects ?? [] as $index => $project)
                        @if($index < 5)
                        <tr>
                            <td class="py-3 px-4">{{ $index + 1 }}</td>
                            <td class="py-3 px-4 font-medium">
                                <div class="flex items-center">
                                    @if($project->image)
                                        <img src="{{ $project->image }}" alt="{{ $project->symbol }}" class="w-6 h-6 mr-2 rounded-full">
                                    @endif
                                    {{ $project->name }} ({{ $project->symbol }})
                                </div>
                            </td>
                            <td class="py-3 px-4">{{ $project->formatted_price ?? '$'.number_format($project->price_usd, 2) }}</td>
                            <td class="py-3 px-4 {{ $project->price_change_percentage_24h > 0 ? 'text-success' : 'text-danger' }}">
                                {{ $project->price_change_percentage_24h > 0 ? '+' : '' }}{{ number_format($project->price_change_percentage_24h, 2) }}%
                            </td>
                            <td class="py-3 px-4 {{ $project->price_change_percentage_7d > 0 ? 'text-success' : 'text-danger' }}">
                                {{ $project->price_change_percentage_7d > 0 ? '+' : '' }}{{ number_format($project->price_change_percentage_7d, 2) }}%
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center">
                                    <div class="w-16 h-2 clay-progress overflow-hidden rounded-full mr-2">
                                        <div class="h-full bg-warning" style="width: {{ $project->trend_score }}%;"></div>
                                    </div>
                                    <span>{{ number_format($project->trend_score, 1) }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <a href="{{ route('panel.recommendations.project', $project->id) }}" class="clay-button clay-button-warning py-1 px-2 text-xs">
                                    <i class="fas fa-info-circle mr-1"></i> Detail
                                </a>
                            </td>
                        </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 px-4 text-center text-gray-500">Tidak ada data proyek trending</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Interactions -->
    <div class="clay-card p-6 mb-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold flex items-center">
                <i class="fas fa-history mr-2 text-info"></i>
                Interaksi Terbaru
            </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($interactions ?? [] as $index => $interaction)
                @if($index < 6)
                <div class="clay-card p-4 flex items-center">
                    <div class="mr-4">
                        @if($interaction->interaction_type == 'view')
                            <div class="clay-rounded-full bg-info/20 p-2">
                                <i class="fas fa-eye text-info"></i>
                            </div>
                        @elseif($interaction->interaction_type == 'favorite')
                            <div class="clay-rounded-full bg-secondary/20 p-2">
                                <i class="fas fa-heart text-secondary"></i>
                            </div>
                        @elseif($interaction->interaction_type == 'portfolio_add')
                            <div class="clay-rounded-full bg-success/20 p-2">
                                <i class="fas fa-folder-plus text-success"></i>
                            </div>
                        @else
                            <div class="clay-rounded-full bg-warning/20 p-2">
                                <i class="fas fa-info-circle text-warning"></i>
                            </div>
                        @endif
                    </div>
                    <div class="flex-grow">
                        <div class="font-medium">
                            {{-- Tipe interaksi dengan bahasa yang mudah dibaca --}}
                            @if($interaction->interaction_type == 'view')
                                Melihat detail
                            @elseif($interaction->interaction_type == 'favorite')
                                Menambahkan ke favorit
                            @elseif($interaction->interaction_type == 'portfolio_add')
                                Menambahkan ke portfolio
                            @elseif($interaction->interaction_type == 'research')
                                Meriset
                            @elseif($interaction->interaction_type == 'click')
                                Mengklik
                            @else
                                Berinteraksi dengan
                            @endif
                            <span class="font-bold">{{ $interaction->project->name }} ({{ $interaction->project->symbol }})</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ $interaction->created_at->diffForHumans() }}
                        </div>
                    </div>
                    <div>
                        <a href="{{ route('panel.recommendations.project', $interaction->project_id) }}" class="clay-button clay-button-info py-1 px-2 text-xs">
                            Detail
                        </a>
                    </div>
                </div>
                @endif
            @empty
                <div class="col-span-full clay-card p-6 text-center">
                    <p>Belum ada interaksi yang tercatat.</p>
                    <p class="text-sm mt-2 text-gray-500">Mulai berinteraksi dengan proyek untuk melihat riwayat aktivitas Anda.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Model Comparison -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-code-branch mr-2 text-primary"></i>
            Model Rekomendasi
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="clay-card bg-primary/10 p-4">
                <h3 class="font-bold mb-2 flex items-center">
                    <i class="fas fa-table mr-2"></i>
                    Feature-Enhanced CF
                </h3>
                <p class="text-sm mb-3">
                    Model berbasis SVD yang menggabungkan matrix factorization dengan content-based filtering berdasarkan fitur proyek.
                </p>
                <div class="clay-badge clay-badge-primary px-2 py-1 text-xs">
                    Hit Ratio: 0.8148
                </div>
            </div>

            <div class="clay-card bg-secondary/10 p-4">
                <h3 class="font-bold mb-2 flex items-center">
                    <i class="fas fa-brain mr-2"></i>
                    Neural CF
                </h3>
                <p class="text-sm mb-3">
                    Deep learning model yang menangkap pola kompleks dalam interaksi user-item menggunakan jaringan neural.
                </p>
                <div class="clay-badge clay-badge-secondary px-2 py-1 text-xs">
                    Hit Ratio: 0.7138
                </div>
            </div>

            <div class="clay-card bg-success/10 p-4">
                <h3 class="font-bold mb-2 flex items-center">
                    <i class="fas fa-layer-group mr-2"></i>
                    Enhanced Hybrid Model
                </h3>
                <p class="text-sm mb-3">
                    Gabungan kedua model dengan teknik ensemble canggih untuk hasil rekomendasi yang lebih akurat.
                </p>
                <div class="clay-badge clay-badge-success px-2 py-1 text-xs">
                    Hit Ratio: 0.8788
                </div>
            </div>
        </div>

        <div class="mt-6 clay-card bg-info/10 p-4">
            <div class="font-bold mb-2 flex items-center">
                <i class="fas fa-info-circle mr-2 text-info"></i>
                Tentang Model Hybrid
            </div>
            <p class="text-sm">
                Model hybrid menggabungkan kekuatan Feature-Enhanced CF dan Neural CF dengan pembobotan dinamis berdasarkan jumlah interaksi pengguna.
                Untuk pengguna dengan interaksi sedikit (cold-start), model lebih menekankan pada fitur proyek (FECF).
                Untuk pengguna aktif, model memanfaatkan pola interaksi yang dipelajari oleh komponen neural.
            </p>
        </div>
    </div>

    <!-- Technical Analysis -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-chart-line mr-2 text-warning"></i>
            Analisis Teknikal
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="clay-card bg-warning/10 p-4">
                <h3 class="font-bold mb-2">Short-Term Trading</h3>
                <div class="text-sm space-y-1">
                    <p><span class="font-medium">RSI:</span> 7 periode</p>
                    <p><span class="font-medium">MACD:</span> 8-17-9</p>
                    <p><span class="font-medium">Bollinger:</span> 10 periode</p>
                    <p><span class="font-medium">MA:</span> 10-30-60</p>
                </div>
            </div>

            <div class="clay-card bg-primary/10 p-4">
                <h3 class="font-bold mb-2">Standard Trading</h3>
                <div class="text-sm space-y-1">
                    <p><span class="font-medium">RSI:</span> 14 periode</p>
                    <p><span class="font-medium">MACD:</span> 12-26-9</p>
                    <p><span class="font-medium">Bollinger:</span> 20 periode</p>
                    <p><span class="font-medium">MA:</span> 20-50-200</p>
                </div>
            </div>

            <div class="clay-card bg-info/10 p-4">
                <h3 class="font-bold mb-2">Long-Term Trading</h3>
                <div class="text-sm space-y-1">
                    <p><span class="font-medium">RSI:</span> 21 periode</p>
                    <p><span class="font-medium">MACD:</span> 19-39-9</p>
                    <p><span class="font-medium">Bollinger:</span> 30 periode</p>
                    <p><span class="font-medium">MA:</span> 50-100-200</p>
                </div>
            </div>
        </div>

        <p class="mt-4 text-sm text-center">
            Lihat halaman detail proyek untuk mendapatkan sinyal trading berdasarkan analisis teknikal dengan periode indikator yang dapat dikonfigurasi.
        </p>
    </div>

    <!-- User Preferences -->
    @if(Auth::check())
    <div class="clay-card p-6">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-sliders-h mr-2 text-secondary"></i>
            Preferensi Investasi Anda
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="clay-card bg-secondary/10 p-4">
                <h3 class="font-bold mb-3">Profil Anda</h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span>Toleransi Risiko:</span>
                        <span class="font-medium">
                            @if(Auth::user()->risk_tolerance == 'low')
                                <span class="clay-badge clay-badge-success">Rendah</span>
                            @elseif(Auth::user()->risk_tolerance == 'medium')
                                <span class="clay-badge clay-badge-warning">Sedang</span>
                            @elseif(Auth::user()->risk_tolerance == 'high')
                                <span class="clay-badge clay-badge-danger">Tinggi</span>
                            @else
                                <span class="text-gray-400">Belum diatur</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span>Gaya Investasi:</span>
                        <span class="font-medium">
                            @if(Auth::user()->investment_style == 'conservative')
                                <span class="clay-badge clay-badge-info">Konservatif</span>
                            @elseif(Auth::user()->investment_style == 'balanced')
                                <span class="clay-badge clay-badge-warning">Seimbang</span>
                            @elseif(Auth::user()->investment_style == 'aggressive')
                                <span class="clay-badge clay-badge-danger">Agresif</span>
                            @else
                                <span class="text-gray-400">Belum diatur</span>
                            @endif
                        </span>
                    </div>
                </div>

                @if(!Auth::user()->risk_tolerance || !Auth::user()->investment_style)
                <div class="mt-4">
                    <a href="{{ route('panel.profile.edit') }}" class="clay-button clay-button-secondary py-1.5 px-3 text-sm">
                        <i class="fas fa-edit mr-1"></i> Lengkapi Profil
                    </a>
                </div>
                @endif
            </div>

            <div class="clay-card bg-primary/10 p-4">
                <h3 class="font-bold mb-3">Rekomendasi Sesuai Preferensi</h3>
                <p class="text-sm">
                    Sistem rekomendasi kami memperhitungkan preferensi Anda dalam memberikan rekomendasi proyek.
                    @if(Auth::user()->risk_tolerance && Auth::user()->investment_style)
                        Dengan profil <strong>{{ Auth::user()->risk_tolerance_text }}</strong> dan gaya investasi <strong>{{ Auth::user()->investment_style_text }}</strong>,
                        Anda akan mendapatkan rekomendasi proyek yang sesuai dengan preferensi tersebut.
                    @else
                        Lengkapi profil Anda untuk mendapatkan rekomendasi yang lebih personal!
                    @endif
                </p>

                <div class="mt-4">
                    <a href="{{ route('panel.recommendations.personal') }}" class="clay-button clay-button-primary py-1.5 px-3 text-sm">
                        <i class="fas fa-user-check mr-1"></i> Lihat Rekomendasi Personal
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

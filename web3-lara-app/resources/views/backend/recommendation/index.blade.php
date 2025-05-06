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

    <!-- Personal Recommendations Preview dengan Lazy Loading yang DIPERBAIKI -->
    <div class="clay-card p-6 mb-8" x-data="{ loading: true, recommendations: [] }">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold flex items-center">
                <i class="fas fa-user-check mr-2 text-secondary"></i>
                Rekomendasi Personal
            </h2>
            <a href="{{ route('panel.recommendations.personal') }}" class="clay-button clay-button-secondary py-1.5 px-3 text-sm">
                Lihat Semua <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>

        <!-- Loading Spinner -->
        <div x-show="loading" class="py-4 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-secondary"></div>
            <p class="mt-2 text-gray-500">Memuat rekomendasi personal...</p>
        </div>

        <!-- Rekomendasi Personal Content -->
        <div x-show="!loading" x-init="
            // PERBAIKAN: Prioritaskan data dari server jika tersedia
            @if(!empty($personalRecommendations))
                recommendations = {{ json_encode($personalRecommendations) }};
                loading = false;
            @else
                setTimeout(() => {
                    fetch('{{ route('panel.recommendations.personal') }}?format=json')
                        .then(response => response.json())
                        .then(data => {
                            // PERBAIKAN: Tangani berbagai format data
                            if (data.recommendations) {
                                recommendations = data.recommendations.slice(0, 4);
                            } else if (data.data) {
                                recommendations = data.data.slice(0, 4);
                            } else {
                                recommendations = data.slice(0, 4);
                            }
                            loading = false;
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            loading = false;
                        });
                }, 100);
            @endif">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <template x-for="(recommendation, index) in recommendations" :key="index" x-show="index < 4">
                    <div class="clay-card p-4 hover:translate-y-[-5px] transition-transform">
                        <div class="font-bold text-lg mb-2" x-text="recommendation.name + ' (' + recommendation.symbol + ')'"></div>
                        <div class="flex justify-between mb-2 text-sm">
                            <span x-text="'$' + (recommendation.current_price ? recommendation.current_price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0.00')"></span>
                            <span :class="(recommendation.price_change_percentage_24h || 0) > 0 ? 'text-success' : 'text-danger'"
                                x-text="((recommendation.price_change_percentage_24h || 0) > 0 ? '+' : '') + ((recommendation.price_change_percentage_24h || 0).toFixed(2)) + '$'">
                            </span>
                        </div>
                        <!-- PERBAIKAN: Tampilkan kategori dengan fallback -->
                        <div class="clay-badge clay-badge-secondary mb-3" x-text="recommendation.primary_category || recommendation.category || 'Umum'"></div>
                        <div class="flex justify-between items-center">
                            <div class="text-xs font-medium">Score: <span class="text-secondary"
                                x-text="(recommendation.recommendation_score || 0).toFixed(2)"></span></div>
                            <a :href="'/panel/recommendations/project/' + recommendation.id" class="clay-button clay-button-secondary py-1 px-2 text-xs">
                                <i class="fas fa-info-circle mr-1"></i> Detail
                            </a>
                        </div>
                    </div>
                </template>
            </div>

            <template x-if="recommendations.length === 0">
                <div class="col-span-full clay-card p-6 text-center">
                    <p>Tidak ada rekomendasi personal yang tersedia saat ini.</p>
                    <p class="text-sm mt-2 text-gray-500">Mulai berinteraksi dengan proyek untuk mendapatkan rekomendasi yang lebih baik.</p>
                    <a href="{{ route('panel.recommendations.trending') }}" class="clay-button clay-button-primary mt-4">Lihat Trending</a>
                </div>
            </template>
        </div>
    </div>

    <!-- Trending Projects Preview dengan Lazy Loading yang DIPERBAIKI -->
    <div class="clay-card p-6 mb-8" x-data="{ loading: true, trendingProjects: [] }">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold flex items-center">
                <i class="fas fa-fire mr-2 text-warning"></i>
                Proyek Trending
            </h2>
            <div class="flex space-x-2">
                <button @click="
                    loading = true;
                    fetch('{{ route('panel.recommendations.trending-refresh') }}')
                        .then(response => response.json())
                        .then(data => {
                            if (data.data) {
                                trendingProjects = data.data;
                            } else {
                                trendingProjects = data;
                            }
                            loading = false;
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            loading = false;
                        });"
                        class="clay-button clay-button-warning py-1.5 px-3 text-sm">
                    <i class="fas fa-sync-alt mr-1" :class="{'animate-spin': loading}"></i> Refresh
                </button>
                <a href="{{ route('panel.recommendations.trending') }}" class="clay-button clay-button-warning py-1.5 px-3 text-sm">
                    Lihat Semua <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>

        <!-- Loading Spinner -->
        <div x-show="loading" class="py-4 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-warning"></div>
            <p class="mt-2 text-gray-500">Memuat proyek trending...</p>
        </div>

        <!-- Trending Projects Table -->
        <div class="overflow-x-auto" x-show="!loading" x-init="
            // PERBAIKAN: Prioritaskan data dari server jika tersedia
            @if(!empty($trendingProjects))
                @if(is_object($trendingProjects) && method_exists($trendingProjects, 'items'))
                    trendingProjects = {{ json_encode($trendingProjects->items()) }};
                @else
                    trendingProjects = {{ json_encode(is_array($trendingProjects) && isset($trendingProjects['data']) ? $trendingProjects['data'] : $trendingProjects) }};
                @endif
                loading = false;
            @else
                setTimeout(() => {
                    fetch('{{ route('panel.recommendations.trending-refresh') }}')
                        .then(response => response.json())
                        .then(data => {
                            if (data.data) {
                                trendingProjects = data.data;
                            } else {
                                trendingProjects = data;
                            }
                            loading = false;
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            loading = false;
                        });
                }, 100);
            @endif">

            <table class="clay-table min-w-full">
                <thead>
                    <tr>
                        <th class="py-2 px-4 text-left">#</th>
                        <th class="py-2 px-4 text-left">Project</th>
                        <th class="py-2 px-4 text-left">Harga</th>
                        <th class="py-2 px-4 text-left">24h $</th>
                        <th class="py-2 px-4 text-left">7d %</th>
                        <th class="py-2 px-4 text-left">Trend Score</th>
                        <th class="py-2 px-4 text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(project, index) in trendingProjects" :key="index" x-show="index < 5">
                        <tr>
                            <td class="py-3 px-4" x-text="index + 1"></td>
                            <td class="py-3 px-4 font-medium">
                                <div class="flex items-center">
                                    <template x-if="project.image">
                                        <img :src="project.image" :alt="project.symbol" class="w-6 h-6 mr-2 rounded-full">
                                    </template>
                                    <div x-text="project.name + ' (' + project.symbol + ')'"></div>
                                </div>
                            </td>
                            <td class="py-3 px-4" x-text="'$' + (project.current_price ? project.current_price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0.00')"></td>
                            <!-- PERBAIKAN: Tampilkan price_change_percentage_24h dengan penanganan null yang lebih baik -->
                            <td class="py-3 px-4" :class="(project.price_change_percentage_24h || 0) > 0 ? 'text-success' : 'text-danger'"
                                x-text="((project.price_change_percentage_24h || 0) > 0 ? '+' : '') +
                                        ((project.price_change_percentage_24h || 0).toFixed(2)) + '$'"></td>
                            <td class="py-3 px-4" :class="(project.price_change_percentage_7d_in_currency || 0) > 0 ? 'text-success' : 'text-danger'"
                                x-text="((project.price_change_percentage_7d_in_currency || 0) > 0 ? '+' : '') +
                                        ((project.price_change_percentage_7d_in_currency || 0).toFixed(2)) + '%'"></td>
                            <td class="py-3 px-4">
                                <div class="flex items-center">
                                    <div class="w-16 h-2 clay-progress overflow-hidden rounded-full mr-2">
                                        <div class="h-full bg-warning" :style="'width: ' + Math.min(100, project.trend_score || 0) + '%;'"></div>
                                    </div>
                                    <span x-text="(project.trend_score || 0).toFixed(1)"></span>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <a :href="'/panel/recommendations/project/' + project.id" class="clay-button clay-button-warning py-1 px-2 text-xs">
                                    <i class="fas fa-info-circle mr-1"></i> Detail
                                </a>
                            </td>
                        </tr>
                    </template>

                    <template x-if="trendingProjects.length === 0">
                        <tr>
                            <td colspan="7" class="py-6 px-4 text-center text-gray-500">Tidak ada data proyek trending</td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Interactions dengan Lazy Loading -->
    <div class="clay-card p-6 mb-8" x-data="{ loading: true, interactions: [] }">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold flex items-center">
                <i class="fas fa-history mr-2 text-info"></i>
                Interaksi Terbaru
            </h2>
        </div>

        <!-- Loading Spinner -->
        <div x-show="loading" class="py-4 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-info"></div>
            <p class="mt-2 text-gray-500">Memuat interaksi terbaru...</p>
        </div>

        <!-- Recent Interactions Content -->
        <div x-show="!loading" x-init="
            setTimeout(() => {
                fetch('{{ route('panel.dashboard.load-interactions') }}')
                    .then(response => response.json())
                    .then(data => {
                        interactions = data;
                        loading = false;
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        loading = false;
                    });
            }, 200);">

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <template x-for="(interaction, index) in interactions" :key="index" x-show="index < 6">
                    <div class="clay-card p-4 flex items-center">
                        <div class="mr-4">
                            <template x-if="interaction.interaction_type === 'view'">
                                <div class="clay-rounded-full bg-info/20 p-2">
                                    <i class="fas fa-eye text-info"></i>
                                </div>
                            </template>
                            <template x-if="interaction.interaction_type === 'favorite'">
                                <div class="clay-rounded-full bg-secondary/20 p-2">
                                    <i class="fas fa-heart text-secondary"></i>
                                </div>
                            </template>
                            <template x-if="interaction.interaction_type === 'portfolio_add'">
                                <div class="clay-rounded-full bg-success/20 p-2">
                                    <i class="fas fa-folder-plus text-success"></i>
                                </div>
                            </template>
                            <template x-if="!['view', 'favorite', 'portfolio_add'].includes(interaction.interaction_type)">
                                <div class="clay-rounded-full bg-warning/20 p-2">
                                    <i class="fas fa-info-circle text-warning"></i>
                                </div>
                            </template>
                        </div>
                        <div class="flex-grow">
                            <div class="font-medium">
                                <template x-if="interaction.interaction_type === 'view'">
                                    <span>Melihat detail</span>
                                </template>
                                <template x-if="interaction.interaction_type === 'favorite'">
                                    <span>Menambahkan ke favorit</span>
                                </template>
                                <template x-if="interaction.interaction_type === 'portfolio_add'">
                                    <span>Menambahkan ke portfolio</span>
                                </template>
                                <template x-if="interaction.interaction_type === 'research'">
                                    <span>Meriset</span>
                                </template>
                                <template x-if="interaction.interaction_type === 'click'">
                                    <span>Mengklik</span>
                                </template>
                                <template x-if="!['view', 'favorite', 'portfolio_add', 'research', 'click'].includes(interaction.interaction_type)">
                                    <span>Berinteraksi dengan</span>
                                </template>

                                <span class="font-bold" x-text="interaction.project ? (interaction.project.name + ' (' + interaction.project.symbol + ')') : 'Unknown'"></span>
                            </div>
                            <div class="text-xs text-gray-500">
                                <span x-text="new Date(interaction.created_at).toLocaleString()"></span>
                            </div>
                        </div>
                        <div>
                            <a :href="'/panel/recommendations/project/' + interaction.project_id" class="clay-button clay-button-info py-1 px-2 text-xs">
                                Detail
                            </a>
                        </div>
                    </div>
                </template>

                <template x-if="interactions.length === 0">
                    <div class="col-span-full clay-card p-6 text-center">
                        <p>Belum ada interaksi yang tercatat.</p>
                        <p class="text-sm mt-2 text-gray-500">Mulai berinteraksi dengan proyek untuk melihat riwayat aktivitas Anda.</p>
                    </div>
                </template>
            </div>
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
            </div>

            <div class="clay-card bg-secondary/10 p-4">
                <h3 class="font-bold mb-2 flex items-center">
                    <i class="fas fa-brain mr-2"></i>
                    Neural CF
                </h3>
                <p class="text-sm mb-3">
                    Deep learning model yang menangkap pola kompleks dalam interaksi user-item menggunakan jaringan neural.
                </p>
            </div>

            <div class="clay-card bg-success/10 p-4">
                <h3 class="font-bold mb-2 flex items-center">
                    <i class="fas fa-layer-group mr-2"></i>
                    Enhanced Hybrid Model
                </h3>
                <p class="text-sm mb-3">
                    Gabungan kedua model dengan teknik ensemble canggih untuk hasil rekomendasi yang lebih akurat.
                </p>
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

    <!-- User Preferences dengan Lazy Loading -->
    @if(Auth::check())
    <div class="clay-card p-6" x-data="{ loaded: false, userPrefs: null }" x-init="
        setTimeout(() => {
            userPrefs = {
                risk_tolerance: '{{ Auth::user()->risk_tolerance }}',
                investment_style: '{{ Auth::user()->investment_style }}',
                preferred_categories: {{ json_encode(Auth::user()->profile ? Auth::user()->profile->preferred_categories : []) }},
                preferred_chains: {{ json_encode(Auth::user()->profile ? Auth::user()->profile->preferred_chains : []) }}
            };
            loaded = true;
        }, 300);">

        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-sliders-h mr-2 text-secondary"></i>
            Preferensi Investasi Anda
        </h2>

        <!-- Loading Placeholder -->
        <div x-show="!loaded" class="py-4 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-secondary"></div>
            <p class="mt-2 text-gray-500">Memuat preferensi Anda...</p>
        </div>

        <div x-show="loaded" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="clay-card bg-secondary/10 p-4">
                <h3 class="font-bold mb-3">Profil Anda</h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span>Toleransi Risiko:</span>
                        <span class="font-medium">
                            <template x-if="userPrefs.risk_tolerance === 'low'">
                                <span class="clay-badge clay-badge-success">Rendah</span>
                            </template>
                            <template x-if="userPrefs.risk_tolerance === 'medium'">
                                <span class="clay-badge clay-badge-warning">Sedang</span>
                            </template>
                            <template x-if="userPrefs.risk_tolerance === 'high'">
                                <span class="clay-badge clay-badge-danger">Tinggi</span>
                            </template>
                            <template x-if="!userPrefs.risk_tolerance">
                                <span class="text-gray-400">Belum diatur</span>
                            </template>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span>Gaya Investasi:</span>
                        <span class="font-medium">
                            <template x-if="userPrefs.investment_style === 'conservative'">
                                <span class="clay-badge clay-badge-info">Konservatif</span>
                            </template>
                            <template x-if="userPrefs.investment_style === 'balanced'">
                                <span class="clay-badge clay-badge-warning">Seimbang</span>
                            </template>
                            <template x-if="userPrefs.investment_style === 'aggressive'">
                                <span class="clay-badge clay-badge-danger">Agresif</span>
                            </template>
                            <template x-if="!userPrefs.investment_style">
                                <span class="text-gray-400">Belum diatur</span>
                            </template>
                        </span>
                    </div>
                </div>

                <template x-if="!userPrefs.risk_tolerance || !userPrefs.investment_style">
                    <div class="mt-4">
                        <a href="{{ route('panel.profile.edit') }}" class="clay-button clay-button-secondary py-1.5 px-3 text-sm">
                            <i class="fas fa-edit mr-1"></i> Lengkapi Profil
                        </a>
                    </div>
                </template>
            </div>

            <div class="clay-card bg-primary/10 p-4">
                <h3 class="font-bold mb-3">Rekomendasi Sesuai Preferensi</h3>
                <p class="text-sm">
                    Sistem rekomendasi kami memperhitungkan preferensi Anda dalam memberikan rekomendasi proyek.
                    <template x-if="userPrefs.risk_tolerance && userPrefs.investment_style">
                        <span>
                            Dengan profil <strong x-text="userPrefs.risk_tolerance === 'low' ? 'Rendah' : (userPrefs.risk_tolerance === 'medium' ? 'Sedang' : 'Tinggi')"></strong>
                            dan gaya investasi <strong x-text="userPrefs.investment_style === 'conservative' ? 'Konservatif' : (userPrefs.investment_style === 'balanced' ? 'Seimbang' : 'Agresif')"></strong>,
                            Anda akan mendapatkan rekomendasi proyek yang sesuai dengan preferensi tersebut.
                        </span>
                    </template>
                    <template x-if="!userPrefs.risk_tolerance || !userPrefs.investment_style">
                        <span>Lengkapi profil Anda untuk mendapatkan rekomendasi yang lebih personal!</span>
                    </template>
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

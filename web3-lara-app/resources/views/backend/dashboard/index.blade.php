@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Welcome Section -->
    <div class="clay-card p-6 md:p-8 mb-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
            <div>
                <h1 class="text-3xl font-bold mb-2">Selamat Datang, {{ $user->profile?->username ?? 'Pengguna' }}!</h1>
                <p class="text-gray-600">
                    Dapatkan rekomendasi personal proyek Web3 berdasarkan preferensi dan interaksi Anda.
                </p>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="{{ route('panel.recommendations') }}" class="clay-button clay-button-primary">
                    <i class="fas fa-star mr-2"></i> Lihat Semua Rekomendasi
                </a>
            </div>
        </div>
    </div>

    <!-- Profile Completion Widget (if profile is incomplete) -->
    @if(!$user->profile || !$user->profile->isComplete())
    <div class="clay-card p-6 mb-8 bg-info/5">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
            <div>
                <h2 class="text-xl font-bold mb-3 flex items-center">
                    <i class="fas fa-user-edit text-info mr-2"></i>
                    Lengkapi Profil Anda
                </h2>
                <p class="text-gray-600 mb-2">
                    Lengkapi profil Anda untuk mendapatkan rekomendasi yang lebih personal.
                </p>
                <div class="w-full md:w-80 mt-2">
                    <div class="flex justify-between text-sm mb-1">
                        <span>Kelengkapan Profil</span>
                        <span>{{ $user->profile ? $user->profile->completeness_percentage : 0 }}%</span>
                    </div>
                    <div class="clay-progress">
                        <div class="clay-progress-bar clay-progress-info"
                            style="width: {{ $user->profile ? $user->profile->completeness_percentage : 0 }}%">
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="{{ route('panel.profile.edit') }}" class="clay-button clay-button-info">
                    <i class="fas fa-edit mr-2"></i> Edit Profil
                </a>
            </div>
        </div>
    </div>
    @endif

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Portfolio Summary dengan Lazy Loading -->
        <div class="clay-card p-6" x-data="{ loading: true, portfolioData: null }">
            <h2 class="text-lg font-bold mb-3 flex items-center">
                <i class="fas fa-wallet text-success mr-2"></i>
                Ringkasan Portfolio
            </h2>

            <!-- Loading Spinner -->
            <div x-show="loading" class="py-4 text-center">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-success"></div>
                <p class="mt-2 text-gray-500">Memuat data portfolio...</p>
            </div>

            <!-- Portfolio Content (Loaded Lazily) -->
            <div x-show="!loading" x-init="
                setTimeout(() => {
                    fetch('{{ route('panel.dashboard.load-portfolio') }}')
                        .then(response => response.json())
                        .then(data => {
                            portfolioData = data;
                            loading = false;
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            portfolioData = {
                                total_value: 0,
                                total_cost: 0,
                                profit_loss: 0,
                                profit_loss_percentage: 0,
                                top_assets: []
                            };
                            loading = false;
                        });
                }, 500);" x-cloak>

                <template x-if="portfolioData && portfolioData.total_value > 0">
                    <div class="space-y-3">
                        <div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Nilai Total:</span>
                                <span class="font-bold" x-text="'$' + parseFloat(portfolioData.total_value).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Investasi Awal:</span>
                                <span x-text="'$' + parseFloat(portfolioData.total_cost).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Profit/Loss:</span>
                                <span :class="portfolioData.profit_loss >= 0 ? 'text-success' : 'text-danger'" class="font-bold"
                                    x-text="(portfolioData.profit_loss >= 0 ? '+' : '') + '$' + parseFloat(portfolioData.profit_loss).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) +
                                    ' (' + parseFloat(portfolioData.profit_loss_percentage).toFixed(2) + '%)'"></span>
                            </div>
                        </div>

                        <template x-if="portfolioData.top_assets && portfolioData.top_assets.length > 0">
                            <div>
                                <h3 class="text-sm font-bold mt-4 mb-2">Top Assets:</h3>
                                <div class="space-y-2">
                                    <template x-for="asset in portfolioData.top_assets" :key="asset.id">
                                        <div class="clay-card bg-primary/5 p-2 flex justify-between items-center">
                                            <div class="flex items-center">
                                                <template x-if="asset.image">
                                                    <img :src="asset.image" :alt="asset.symbol" class="w-6 h-6 rounded-full mr-2" loading="lazy">
                                                </template>
                                                <span x-text="asset.symbol"></span>
                                            </div>
                                            <span class="font-medium" x-text="'$' + parseFloat(asset.value).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <div class="mt-4">
                            <a href="{{ route('panel.portfolio') }}" class="clay-button clay-button-success py-1.5 px-3 text-sm">
                                Lihat Portfolio
                            </a>
                        </div>
                    </div>
                </template>

                <template x-if="!portfolioData || portfolioData.total_value === 0">
                    <div class="py-6 text-center text-gray-500">
                        <p class="mb-2">Anda belum memiliki portfolio.</p>
                        <a href="{{ route('panel.portfolio') }}" class="clay-button clay-button-success py-1.5 px-3 text-sm mt-4">
                            Buat Portfolio
                        </a>
                    </div>
                </template>
            </div>
        </div>

        <!-- Personal Preferences -->
        <div class="clay-card p-6">
            <h2 class="text-lg font-bold mb-3 flex items-center">
                <i class="fas fa-sliders-h text-primary mr-2"></i>
                Preferensi Personal
            </h2>
            <div class="space-y-3">
                <div>
                    <label class="block text-gray-600 text-sm">Toleransi Risiko:</label>
                    @if($user->profile && $user->profile->risk_tolerance)
                        <div class="clay-badge clay-badge-{{ $user->profile->risk_tolerance == 'low' ? 'success' : ($user->profile->risk_tolerance == 'medium' ? 'warning' : 'danger') }} py-1 px-2">
                            {{ $user->profile->risk_tolerance_text }}
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ $user->profile->risk_tolerance_description }}</p>
                    @else
                        <span class="text-gray-500">Belum diatur</span>
                    @endif
                </div>
                <div>
                    <label class="block text-gray-600 text-sm">Gaya Investasi:</label>
                    @if($user->profile && $user->profile->investment_style)
                        <div class="clay-badge clay-badge-{{ $user->profile->investment_style == 'conservative' ? 'info' : ($user->profile->investment_style == 'balanced' ? 'warning' : 'secondary') }} py-1 px-2">
                            {{ $user->profile->investment_style_text }}
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ $user->profile->investment_style_description }}</p>
                    @else
                        <span class="text-gray-500">Belum diatur</span>
                    @endif
                </div>
                <div>
                    <label class="block text-gray-600 text-sm">Kategori Favorit:</label>
                    @if($user->profile && !empty($user->profile->preferred_categories))
                        <div class="flex flex-wrap gap-2 mt-1">
                            @foreach($user->profile->preferred_categories as $category)
                                <span class="clay-badge clay-badge-primary py-1 px-2 text-xs">{{ $category }}</span>
                            @endforeach
                        </div>
                    @else
                        <span class="text-gray-500">Belum diatur</span>
                    @endif
                </div>
                <div>
                    <label class="block text-gray-600 text-sm">Blockchain Favorit:</label>
                    @if($user->profile && !empty($user->profile->preferred_chains))
                        <div class="flex flex-wrap gap-2 mt-1">
                            @foreach($user->profile->preferred_chains as $chain)
                                <span class="clay-badge clay-badge-secondary py-1 px-2 text-xs">{{ $chain }}</span>
                            @endforeach
                        </div>
                    @else
                        <span class="text-gray-500">Belum diatur</span>
                    @endif
                </div>
            </div>
            <div class="mt-4">
                <a href="{{ route('panel.profile.edit') }}" class="clay-button clay-button-primary py-1.5 px-3 text-sm">
                    Edit Preferensi
                </a>
            </div>
        </div>

        <!-- Recent Activity dengan Lazy Loading -->
        <div class="clay-card p-6" x-data="{ loading: true, interactions: [] }">
            <h2 class="text-lg font-bold mb-3 flex items-center">
                <i class="fas fa-history text-secondary mr-2"></i>
                Aktivitas Terbaru
            </h2>

            <!-- Loading Spinner -->
            <div x-show="loading" class="py-4 text-center">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-secondary"></div>
                <p class="mt-2 text-gray-500">Memuat aktivitas terbaru...</p>
            </div>

            <!-- Recent Activity Content (Loaded Lazily) -->
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
                            interactions = [];
                            loading = false;
                        });
                }, 1000);" x-cloak>

                <template x-if="interactions.length > 0">
                    <div class="space-y-2">
                        <template x-for="interaction in interactions" :key="interaction.id">
                            <div class="clay-card bg-secondary/5 p-2">
                                <div class="flex justify-between items-center">
                                    <div class="flex items-center">
                                        <template x-if="interaction.project && interaction.project.image">
                                            <img :src="interaction.project.image" :alt="interaction.project.symbol" class="w-6 h-6 rounded-full mr-2" loading="lazy">
                                        </template>
                                        <span class="font-medium" x-text="interaction.project ? interaction.project.symbol : 'Unknown'"></span>
                                    </div>
                                    <div>
                                        <template x-if="interaction.interaction_type === 'view'">
                                            <span class="clay-badge clay-badge-info py-0.5 px-1 text-xs">View</span>
                                        </template>
                                        <template x-if="interaction.interaction_type === 'favorite'">
                                            <span class="clay-badge clay-badge-secondary py-0.5 px-1 text-xs">Favorite</span>
                                        </template>
                                        <template x-if="interaction.interaction_type === 'portfolio_add'">
                                            <span class="clay-badge clay-badge-success py-0.5 px-1 text-xs">Portfolio</span>
                                        </template>
                                        <template x-if="!['view', 'favorite', 'portfolio_add'].includes(interaction.interaction_type)">
                                            <span class="clay-badge clay-badge-primary py-0.5 px-1 text-xs" x-text="interaction.interaction_type"></span>
                                        </template>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-500 mt-1" x-text="new Date(interaction.created_at).toLocaleString()"></div>
                            </div>
                        </template>

                        <!-- PERBAIKAN: Tidak perlu button "Lihat Aktivitas" karena sudah ada di dashboard -->
                    </div>
                </template>

                <template x-if="interactions.length === 0">
                    <div class="py-6 text-center text-gray-500">
                        <p>Belum ada aktivitas terbaru.</p>
                        <a href="{{ route('panel.recommendations') }}" class="clay-button clay-button-secondary py-1.5 px-3 text-sm mt-4">
                            Jelajahi Rekomendasi
                        </a>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Recommendations Section dengan Pagination -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Personal Recommendations -->
        <div class="clay-card p-6">
            <h2 class="text-xl font-bold mb-6 flex items-center">
                <i class="fas fa-star mr-2 text-warning"></i>
                Rekomendasi Personal
            </h2>

            @if(isset($personalRecommendations) && count($personalRecommendations) > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach($personalRecommendations as $recommendation)
                        <a href="{{ route('panel.recommendations.project', $recommendation['project_id'] ?? $recommendation['id'] ?? 0) }}"
                           class="clay-card hover:shadow-lg transition-shadow p-4"
                           loading="lazy">
                            <div class="flex justify-between items-center mb-3">
                                <div class="flex items-center">
                                    @if(isset($recommendation['image']) && $recommendation['image'])
                                        <img src="{{ $recommendation['image'] }}"
                                             alt="{{ $recommendation['symbol'] }}"
                                             class="w-8 h-8 rounded-full mr-2"
                                             loading="lazy">
                                    @endif
                                    <div>
                                        <div class="font-medium">{{ $recommendation['symbol'] ?? $recommendation['project_id'] }}</div>
                                        <div class="text-xs text-gray-500 truncate max-w-[120px]">{{ $recommendation['name'] ?? '' }}</div>
                                    </div>
                                </div>
                                <div class="clay-badge clay-badge-primary py-0.5 px-1 text-xs">
                                    {{ number_format($recommendation['recommendation_score'] ?? 0, 2) }}
                                </div>
                            </div>
                            @if(isset($recommendation['current_price']))
                                <div class="flex justify-between text-sm">
                                    <span>${{ number_format($recommendation['current_price'], 8) }}</span>
                                    <span class="{{ isset($recommendation['price_change_24h']) && $recommendation['price_change_24h'] >= 0 ? 'text-success' : 'text-danger' }}">
                                        @if(isset($recommendation['price_change_24h']))
                                            {{ ($recommendation['price_change_24h'] >= 0 ? '+' : '') . number_format($recommendation['price_change_24h'], 8) . '$' }}
                                        @else
                                            -
                                        @endif
                                    </span>
                                </div>
                            @endif
                        </a>
                    @endforeach
                </div>
                <div class="mt-6 text-center">
                    <a href="{{ route('panel.recommendations.personal') }}" class="clay-button clay-button-warning">
                        Lihat Semua Rekomendasi Personal
                    </a>
                </div>
            @else
                <div class="py-6 text-center text-gray-500">
                    <p class="mb-2">Belum ada rekomendasi personal. Interaksi dengan proyek untuk mendapatkan rekomendasi yang lebih baik.</p>
                    <a href="{{ route('panel.recommendations') }}" class="clay-button clay-button-warning py-1.5 px-3 text-sm mt-4">
                        Jelajahi Rekomendasi
                    </a>
                </div>
            @endif
        </div>

        <!-- Trending Projects dengan Lazy Loading -->
        <div class="clay-card p-6" x-data="{ loading: false, trendingProjects: [] }" x-init="
            // Load trending projects
            trendingProjects = {{ json_encode($trendingProjects ?? []) }};
            loading = false;
        ">
            <h2 class="text-xl font-bold mb-6 flex items-center">
                <i class="fas fa-chart-line mr-2 text-info"></i>
                Proyek Trending
                <!-- PERBAIKAN: Hapus button refresh karena tidak berguna -->
            </h2>

            <template x-if="trendingProjects && trendingProjects.length > 0">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <template x-for="project in trendingProjects" :key="project.id">
                        <a :href="`/panel/recommendations/project/${project.id}`" class="clay-card hover:shadow-lg transition-shadow p-4">
                            <div class="flex justify-between items-center mb-3">
                                <div class="flex items-center">
                                    <template x-if="project.image">
                                        <img :src="project.image" :alt="project.symbol" class="w-8 h-8 rounded-full mr-2" loading="lazy">
                                    </template>
                                    <div>
                                        <div class="font-medium" x-text="project.symbol"></div>
                                        <div class="text-xs text-gray-500 truncate max-w-[120px]" x-text="project.name"></div>
                                    </div>
                                </div>
                                <div class="clay-badge clay-badge-info py-0.5 px-1 text-xs">
                                    Trending
                                </div>
                            </div>
                            <template x-if="project.current_price">
                                <div class="flex justify-between text-sm">
                                    <span x-text="'$' + (project.current_price || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 8})"></span>
                                    <!-- PERBAIKAN: Handle undefined price_change_24h -->
                                    <span :class="(project.price_change_24h || 0) >= 0 ? 'text-success' : 'text-danger'">
                                        <template x-if="project.price_change_24h !== undefined && project.price_change_24h !== null">
                                            <span x-text="((project.price_change_24h || 0) >= 0 ? '+' : '') + (project.price_change_24h || 0).toFixed(8) + '$'"></span>
                                        </template>
                                        <template x-if="project.price_change_24h === undefined || project.price_change_24h === null">
                                            <span>-</span>
                                        </template>
                                    </span>
                                </div>
                            </template>
                        </a>
                    </template>
                </div>
            </template>

            <template x-if="!trendingProjects || trendingProjects.length === 0">
                <div class="py-6 text-center text-gray-500">
                    <p>Tidak ada data proyek trending tersedia saat ini.</p>
                </div>
            </template>

            <div class="mt-6 text-center">
                <a href="{{ route('panel.recommendations.trending') }}" class="clay-button clay-button-info">
                    Lihat Semua Proyek Trending
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="clay-card p-6">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-bolt mr-2 text-warning"></i>
            Aksi Cepat
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="{{ route('panel.recommendations') }}" class="clay-card p-4 bg-primary/10 text-center hover:translate-y-[-5px] transition-transform">
                <i class="fas fa-star text-3xl text-primary mb-2"></i>
                <div class="font-bold">Rekomendasi</div>
                <p class="text-sm mt-1">Lihat semua rekomendasi proyek</p>
            </a>

            <a href="{{ route('panel.portfolio') }}" class="clay-card p-4 bg-success/10 text-center hover:translate-y-[-5px] transition-transform">
                <i class="fas fa-wallet text-3xl text-success mb-2"></i>
                <div class="font-bold">Portfolio</div>
                <p class="text-sm mt-1">Kelola portfolio dan transaksi</p>
            </a>

            <a href="{{ route('panel.recommendations.trending') }}" class="clay-card p-4 bg-info/10 text-center hover:translate-y-[-5px] transition-transform">
                <i class="fas fa-chart-line text-3xl text-info mb-2"></i>
                <div class="font-bold">Trending</div>
                <p class="text-sm mt-1">Lihat proyek yang sedang trending</p>
            </a>

            <a href="{{ route('panel.profile.edit') }}" class="clay-card p-4 bg-secondary/10 text-center hover:translate-y-[-5px] transition-transform">
                <i class="fas fa-user-edit text-3xl text-secondary mb-2"></i>
                <div class="font-bold">Profil</div>
                <p class="text-sm mt-1">Edit profil dan preferensi</p>
            </a>
        </div>
    </div>
</div>
@endsection

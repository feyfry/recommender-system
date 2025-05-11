@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <h1 class="text-3xl font-bold mb-4 flex items-center">
            <div class="bg-secondary/20 p-2 clay-badge mr-3">
                <i class="fas fa-star text-secondary"></i>
            </div>
            Rekomendasi Personal
        </h1>
        <p class="text-lg">
            Rekomendasi proyek cryptocurrency yang dipersonalisasi khusus untuk Anda berdasarkan preferensi dan interaksi sebelumnya.
        </p>
    </div>

    <!-- PERBAIKAN: Tambahkan pesan khusus untuk pengguna cold-start -->
    @if($isColdStart ?? false)
    <div class="clay-alert clay-alert-info mb-6">
        <p class="flex items-center">
            <i class="fas fa-info-circle mr-2"></i>
            Anda terdeteksi sebagai pengguna baru (cold-start). Rekomendasi yang ditampilkan berikut adalah rekomendasi awal berdasarkan tren dan popularitas. Rekomendasi akan menjadi lebih personal setelah Anda berinteraksi dengan lebih banyak proyek.
        </p>
    </div>
    @endif

    <!-- Model Tabs dengan Lazy Loading -->
    <div class="clay-card p-6 mb-8" x-data="{
        activeTab: 'hybrid',
        loading: {
            hybrid: true,
            fecf: true,
            ncf: true
        },
        hybridRecommendations: [],
        fecfRecommendations: [],
        ncfRecommendations: []
    }">
        <div class="clay-tabs mb-6">
            <button @click="activeTab = 'hybrid'" :class="{ 'active': activeTab === 'hybrid' }" class="clay-tab">
                <i class="fas fa-code-branch mr-2"></i> Hybrid Model
            </button>
            <button @click="activeTab = 'fecf'" :class="{ 'active': activeTab === 'fecf' }" class="clay-tab">
                <i class="fas fa-table mr-2"></i> Feature-Enhanced CF
            </button>
            <button @click="activeTab = 'ncf'" :class="{ 'active': activeTab === 'ncf' }" class="clay-tab">
                <i class="fas fa-brain mr-2"></i> Neural CF
            </button>
        </div>

        <!-- Model Description -->
        <div class="clay-card bg-primary/5 p-4 mb-6">
            <div x-show="activeTab === 'hybrid'">
                <h3 class="font-bold text-lg mb-2">Enhanced Hybrid Model</h3>
                <p>Kombinasi dari dua pendekatan rekomendasi (FECF dan NCF) menggunakan teknik ensemble canggih, memberikan rekomendasi yang lebih presisi.</p>
            </div>
            <div x-show="activeTab === 'fecf'">
                <h3 class="font-bold text-lg mb-2">Feature-Enhanced Collaborative Filtering</h3>
                <p>Model yang menggunakan SVD (Singular Value Decomposition) dan menggabungkannya dengan informasi fitur proyek (kategori, chain, dll).</p>
            </div>
            <div x-show="activeTab === 'ncf'">
                <h3 class="font-bold text-lg mb-2">Neural Collaborative Filtering</h3>
                <p>Model deep learning yang menangkap pola kompleks dalam interaksi antara pengguna dan proyek cryptocurrency.</p>
            </div>
        </div>

        <!-- Initialization Script - Muat data dari server atau via AJAX -->
        <div x-init="
            @if(isset($hybridRecommendations) && !empty($hybridRecommendations))
                hybridRecommendations = {{ json_encode($hybridRecommendations) }};
                loading.hybrid = false;
            @else
                fetch('{{ route('panel.recommendations.personal') }}?model=hybrid&format=json')
                    .then(response => response.json())
                    .then(data => {
                        hybridRecommendations = data;
                        loading.hybrid = false;
                    })
                    .catch(error => {
                        console.error('Error loading hybrid recommendations:', error);
                        loading.hybrid = false;
                    });
            @endif

            @if(isset($fecfRecommendations) && !empty($fecfRecommendations))
                fecfRecommendations = {{ json_encode($fecfRecommendations) }};
                loading.fecf = false;
            @else
                document.querySelector('[x-data] button:nth-child(2)').addEventListener('click', function() {
                    if (fecfRecommendations.length === 0 && loading.fecf) {
                        fetch('{{ route('panel.recommendations.personal') }}?model=fecf&format=json')
                            .then(response => response.json())
                            .then(data => {
                                fecfRecommendations = data;
                                loading.fecf = false;
                            })
                            .catch(error => {
                                console.error('Error loading FECF recommendations:', error);
                                loading.fecf = false;
                            });
                    }
                });
            @endif

            @if(isset($ncfRecommendations) && !empty($ncfRecommendations))
                ncfRecommendations = {{ json_encode($ncfRecommendations) }};
                loading.ncf = false;
            @else
                document.querySelector('[x-data] button:nth-child(3)').addEventListener('click', function() {
                    if (ncfRecommendations.length === 0 && loading.ncf) {
                        fetch('{{ route('panel.recommendations.personal') }}?model=ncf&format=json')
                            .then(response => response.json())
                            .then(data => {
                                ncfRecommendations = data;
                                loading.ncf = false;
                            })
                            .catch(error => {
                                console.error('Error loading NCF recommendations:', error);
                                loading.ncf = false;
                            });
                    }
                });
            @endif
        ">
        </div>

        <!-- Loading Spinner untuk Hybrid -->
        <div x-show="activeTab === 'hybrid' && loading.hybrid" class="py-6 text-center">
            <div class="inline-block animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-secondary"></div>
            <p class="mt-3 text-gray-500">Memuat rekomendasi hybrid...</p>
        </div>

        <!-- Loading Spinner untuk FECF -->
        <div x-show="activeTab === 'fecf' && loading.fecf" class="py-6 text-center">
            <div class="inline-block animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-primary"></div>
            <p class="mt-3 text-gray-500">Memuat rekomendasi FECF...</p>
        </div>

        <!-- Loading Spinner untuk NCF -->
        <div x-show="activeTab === 'ncf' && loading.ncf" class="py-6 text-center">
            <div class="inline-block animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-info"></div>
            <p class="mt-3 text-gray-500">Memuat rekomendasi NCF...</p>
        </div>

        <!-- Hybrid Recommendations Content -->
        <div x-show="activeTab === 'hybrid' && !loading.hybrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <template x-for="(recommendation, index) in hybridRecommendations" :key="index">
                <div class="clay-card p-4 hover:translate-y-[-5px] transition-transform">
                    <div class="font-bold text-lg mb-2" x-text="recommendation.name + ' (' + recommendation.symbol + ')'"></div>
                    <div class="flex justify-between mb-2 text-sm">
                        <span x-text="'$' + (recommendation.current_price ? recommendation.current_price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 8}) : '0.00')"></span>
                        <span :class="(recommendation.price_change_24h || 0) >= 0 ? 'text-success' : 'text-danger'"
                            x-text="((recommendation.price_change_24h || 0) >= 0 ? '+' : '') +
                                    ((recommendation.price_change_24h || 0).toFixed(8)) + '$'">
                        </span>
                    </div>
                    <div class="clay-badge clay-badge-info mb-3" x-text="recommendation.primary_category || recommendation.category || 'Umum'"></div>
                    <div class="flex justify-between items-center">
                        <div class="text-xs font-medium">Score: <span class="text-primary"
                                x-text="(recommendation.recommendation_score || 0).toFixed(2)"></span></div>
                        <a :href="'/panel/recommendations/project/' + recommendation.id" class="clay-badge clay-badge-secondary px-2 py-1 text-xs">
                            <i class="fas fa-info-circle mr-1"></i> Detail
                        </a>
                    </div>
                </div>
            </template>

            <template x-if="hybridRecommendations.length === 0">
                <div class="col-span-full text-center py-8">
                    <div class="clay-alert clay-alert-info">
                        <p>Tidak ada rekomendasi hybrid yang tersedia saat ini.</p>
                        <p class="text-sm mt-2">Mulai berinteraksi dengan proyek untuk mendapatkan rekomendasi yang lebih baik.</p>

                        <!-- Tombol refresh manual untuk cold-start user -->
                        @if($isColdStart ?? false)
                        <button onclick="window.location.reload()" class="clay-button clay-button-primary mt-4">
                            <i class="fas fa-sync-alt mr-1"></i> Coba Muat Ulang
                        </button>
                        @endif
                    </div>
                </div>
            </template>
        </div>

        <!-- FECF Recommendations Content -->
        <div x-show="activeTab === 'fecf' && !loading.fecf" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <template x-for="(recommendation, index) in fecfRecommendations" :key="index">
                <div class="clay-card p-4 hover:translate-y-[-5px] transition-transform">
                    <div class="font-bold text-lg mb-2" x-text="recommendation.name + ' (' + recommendation.symbol + ')'"></div>
                    <div class="flex justify-between mb-2 text-sm">
                        <span x-text="'$' + (recommendation.current_price ? recommendation.current_price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 8}) : '0.00')"></span>
                        <span :class="(recommendation.price_change_24h || 0) >= 0 ? 'text-success' : 'text-danger'"
                            x-text="((recommendation.price_change_24h || 0) >= 0 ? '+' : '') +
                                    ((recommendation.price_change_24h || 0).toFixed(8)) + '$'">
                        </span>
                    </div>
                    <div class="clay-badge clay-badge-info mb-3" x-text="recommendation.primary_category || recommendation.category || 'Umum'"></div>
                    <div class="flex justify-between items-center">
                        <div class="text-xs font-medium">Score: <span class="text-primary"
                                x-text="(recommendation.recommendation_score || 0).toFixed(2)"></span></div>
                        <a :href="'/panel/recommendations/project/' + recommendation.id" class="clay-badge clay-badge-secondary px-2 py-1 text-xs">
                            <i class="fas fa-info-circle mr-1"></i> Detail
                        </a>
                    </div>
                </div>
            </template>

            <template x-if="fecfRecommendations.length === 0">
                <div class="col-span-full text-center py-8">
                    <div class="clay-alert clay-alert-info">
                        <p>Tidak ada rekomendasi FECF yang tersedia saat ini.</p>
                        <p class="text-sm mt-2">Mulai berinteraksi dengan proyek untuk mendapatkan rekomendasi yang lebih baik.</p>
                    </div>
                </div>
            </template>
        </div>

        <!-- NCF Recommendations Content -->
        <div x-show="activeTab === 'ncf' && !loading.ncf" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <template x-for="(recommendation, index) in ncfRecommendations" :key="index">
                <div class="clay-card p-4 hover:translate-y-[-5px] transition-transform">
                    <div class="font-bold text-lg mb-2" x-text="recommendation.name + ' (' + recommendation.symbol + ')'"></div>
                    <div class="flex justify-between mb-2 text-sm">
                        <span x-text="'$' + (recommendation.current_price ? recommendation.current_price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 8}) : '0.00')"></span>
                        <span :class="(recommendation.price_change_24h || 0) >= 0 ? 'text-success' : 'text-danger'"
                            x-text="((recommendation.price_change_24h || 0) >= 0 ? '+' : '') +
                                    ((recommendation.price_change_24h || 0).toFixed(8)) + '$'">
                        </span>
                    </div>
                    <div class="clay-badge clay-badge-info mb-3" x-text="recommendation.primary_category || recommendation.category || 'Umum'"></div>
                    <div class="flex justify-between items-center">
                        <div class="text-xs font-medium">Score: <span class="text-primary"
                                x-text="(recommendation.recommendation_score || 0).toFixed(2)"></span></div>
                        <a :href="'/panel/recommendations/project/' + recommendation.id" class="clay-badge clay-badge-secondary px-2 py-1 text-xs">
                            <i class="fas fa-info-circle mr-1"></i> Detail
                        </a>
                    </div>
                </div>
            </template>

            <template x-if="ncfRecommendations.length === 0">
                <div class="col-span-full text-center py-8">
                    <div class="clay-alert clay-alert-info">
                        <p>Tidak ada rekomendasi NCF yang tersedia saat ini.</p>
                        <p class="text-sm mt-2">Mulai berinteraksi dengan proyek untuk mendapatkan rekomendasi yang lebih baik.</p>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- User Preferences Information -->
    <div class="clay-card p-6 mb-8" x-data="{ loaded: false, prefs: {} }" x-init="
        setTimeout(() => {
            prefs = {
                risk_tolerance: '{{ Auth::user()->risk_tolerance }}',
                investment_style: '{{ Auth::user()->investment_style }}',
                preferred_categories: {{ json_encode(Auth::user()->profile->preferred_categories ?? []) }},
                preferred_chains: {{ json_encode(Auth::user()->profile->preferred_chains ?? []) }}
            };
            loaded = true;
        }, 100);">

        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-sliders-h mr-2 text-primary"></i>
            Preferensi Anda
        </h2>

        <!-- Loading Placeholder -->
        <div x-show="!loaded" class="py-4 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-primary"></div>
            <p class="mt-2 text-gray-500">Memuat preferensi Anda...</p>
        </div>

        <div x-show="loaded" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="clay-card bg-secondary/10 p-4">
                <h3 class="font-bold mb-3">Profil Investasi</h3>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span>Toleransi Risiko:</span>
                        <span class="font-medium">
                            <template x-if="prefs.risk_tolerance === 'low'">
                                <span class="clay-badge clay-badge-success">Rendah</span>
                            </template>
                            <template x-if="prefs.risk_tolerance === 'medium'">
                                <span class="clay-badge clay-badge-warning">Sedang</span>
                            </template>
                            <template x-if="prefs.risk_tolerance === 'high'">
                                <span class="clay-badge clay-badge-danger">Tinggi</span>
                            </template>
                            <template x-if="!prefs.risk_tolerance">
                                <span class="text-gray-400">Belum diatur</span>
                            </template>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span>Gaya Investasi:</span>
                        <span class="font-medium">
                            <template x-if="prefs.investment_style === 'conservative'">
                                <span class="clay-badge clay-badge-info">Konservatif</span>
                            </template>
                            <template x-if="prefs.investment_style === 'balanced'">
                                <span class="clay-badge clay-badge-warning">Seimbang</span>
                            </template>
                            <template x-if="prefs.investment_style === 'aggressive'">
                                <span class="clay-badge clay-badge-danger">Agresif</span>
                            </template>
                            <template x-if="!prefs.investment_style">
                                <span class="text-gray-400">Belum diatur</span>
                            </template>
                        </span>
                    </div>
                </div>

                <template x-if="!prefs.risk_tolerance || !prefs.investment_style">
                    <div class="mt-4">
                        <a href="{{ route('panel.profile.edit') }}" class="clay-button clay-button-secondary py-1.5 px-3 text-sm">
                            <i class="fas fa-edit mr-1"></i> Lengkapi Profil
                        </a>
                    </div>
                </template>
            </div>

            <div class="clay-card bg-info/10 p-4">
                <h3 class="font-bold mb-3">Kategori & Chain Pilihan</h3>

                <div class="mb-3">
                    <div class="font-medium mb-1">Kategori:</div>
                    <div class="flex flex-wrap gap-2">
                        <template x-if="prefs.preferred_categories && prefs.preferred_categories.length > 0">
                            <template x-for="(category, index) in prefs.preferred_categories" :key="index">
                                <span class="clay-badge clay-badge-primary" x-text="category"></span>
                            </template>
                        </template>
                        <template x-if="!prefs.preferred_categories || prefs.preferred_categories.length === 0">
                            <span class="text-gray-400">Belum ada kategori yang dipilih</span>
                        </template>
                    </div>
                </div>

                <div>
                    <div class="font-medium mb-1">Blockchain:</div>
                    <div class="flex flex-wrap gap-2">
                        <template x-if="prefs.preferred_chains && prefs.preferred_chains.length > 0">
                            <template x-for="(chain, index) in prefs.preferred_chains" :key="index">
                                <span class="clay-badge clay-badge-secondary" x-text="chain"></span>
                            </template>
                        </template>
                        <template x-if="!prefs.preferred_chains || prefs.preferred_chains.length === 0">
                            <span class="text-gray-400">Belum ada blockchain yang dipilih</span>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- How Recommendations Work -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-question-circle mr-2 text-info"></i>
            Bagaimana Rekomendasi Bekerja
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
            <div class="clay-card bg-primary/10 p-4">
                <h3 class="font-bold mb-2 flex items-center"><i class="fas fa-users mr-2"></i>Collaborative Filtering</h3>
                <p>Model ini merekomendasikan proyek berdasarkan pola dari pengguna yang memiliki minat serupa dengan Anda. Jika user A dan B memiliki preferensi yang sama dan user A menyukai proyek X, maka proyek X akan direkomendasikan ke user B.</p>
            </div>

            <div class="clay-card bg-secondary/10 p-4">
                <h3 class="font-bold mb-2 flex items-center"><i class="fas fa-tags mr-2"></i>Content-Based Filtering</h3>
                <p>Model ini menganalisis fitur dari proyek yang Anda sukai, seperti kategori (DeFi, NFT), blockchain (Ethereum, Solana), dan menyarankan proyek lain dengan karakteristik serupa.</p>
            </div>

            <div class="clay-card bg-success/10 p-4">
                <h3 class="font-bold mb-2 flex items-center"><i class="fas fa-code-branch mr-2"></i>Model Hybrid</h3>
                <p>Gabungan dari kedua pendekatan di atas, memberi Anda rekomendasi yang lebih presisi dan beragam. Sistem ini juga memperhitungkan preferensi risk-tolerance dan gaya investasi Anda.</p>
            </div>
        </div>
    </div>
</div>

<!-- Auto-refresh untuk memastikan rekomendasi cold-start dimuat -->
@if($isColdStart ?? false)
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Jika tidak ada rekomendasi hybrid setelah 5 detik, coba muat ulang
        setTimeout(function() {
            const hybridEmpty = document.querySelector('[x-data] div[x-show="activeTab === \'hybrid\' && !loading.hybrid"] template[x-if="hybridRecommendations.length === 0"]');
            if (hybridEmpty && window.getComputedStyle(hybridEmpty).display !== 'none') {
                window.location.reload();
            }
        }, 5000);
    });
</script>
@endif
@endsection

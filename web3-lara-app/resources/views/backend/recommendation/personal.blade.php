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
            Rekomendasi proyek cryptocurrency yang dipersonalisasi berdasarkan interaksi dan pola penggunaan Anda.
            Gunakan filter untuk menemukan proyek yang sesuai dengan minat Anda.
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

    <!-- Filter Cards -->
    <div class="clay-card p-4 sm:p-6 mb-8" x-data="{
        showFilters: false,
        category: '{{ $selectedCategory ?? '' }}',
        chain: '{{ $selectedChain ?? '' }}',
        strictFilter: {{ $strictFilter ? 'true' : 'false' }},
        categories: [],
        chains: [],
        loadingFilters: true
    }">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4 gap-3">
            <button @click="showFilters = !showFilters" class="clay-button clay-button-secondary w-full sm:w-auto">
                <i class="fas fa-filter mr-2"></i>
                <span x-text="showFilters ? 'Sembunyikan Filter' : 'Tampilkan Filter'"></span>
            </button>

            <div class="flex flex-wrap items-center gap-2" x-show="category || chain">
                <div class="flex flex-wrap items-center gap-1">
                    <span class="font-medium text-sm">Filter aktif:</span>
                    <template x-if="category">
                        <span class="clay-badge clay-badge-primary" x-text="category"></span>
                    </template>
                    <template x-if="chain">
                        <span class="clay-badge clay-badge-secondary" x-text="chain"></span>
                    </template>
                </div>
                <button @click="window.location.href='{{ route('panel.recommendations.personal') }}'" class="clay-button clay-button-danger py-1 px-2 text-sm">
                    <i class="fas fa-times mr-1"></i> Reset
                </button>
            </div>
        </div>

        <div x-show="showFilters" x-transition class="mt-4">
            <div x-init="
                // PERBAIKAN: Load kategori dengan fetch API langsung ke Laravel route
                fetch('{{ route('panel.recommendations.categories') }}?format=json&loadCategories=true')
                    .then(response => response.json())
                    .then(data => {
                        console.log('Kategori yang dimuat:', data);
                        categories = data.categories || [];
                        loadingFilters = false;
                    })
                    .catch(error => {
                        console.error('Error loading categories:', error);
                        categories = ['defi', 'nft', 'gaming', 'layer1', 'layer2'];
                        loadingFilters = false;
                    });

                // PERBAIKAN: Load chain dengan fetch API langsung ke Laravel route
                fetch('{{ route('panel.recommendations.chains') }}?format=json&part=chains_list')
                    .then(response => response.json())
                    .then(data => {
                        console.log('Chain yang dimuat:', data);
                        chains = data.chains || [];
                        loadingFilters = false;
                    })
                    .catch(error => {
                        console.error('Error loading chains:', error);
                        chains = ['ethereum', 'binance-smart-chain', 'polygon', 'solana', 'avalanche'];
                        loadingFilters = false;
                    });
            ">
                <form action="{{ route('panel.recommendations.personal') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <!-- Category Filter -->
                    <div class="clay-card bg-primary/5 p-3">
                        <label for="category" class="block font-medium text-gray-700 mb-2 text-sm">Kategori</label>
                        <div x-show="loadingFilters" class="py-2">
                            <div class="inline-block animate-spin rounded-full h-5 w-5 border-t-2 border-b-2 border-primary"></div>
                            <span class="ml-2 text-sm text-gray-500">Memuat...</span>
                        </div>
                        <select
                            x-show="!loadingFilters"
                            name="category"
                            id="category"
                            x-model="category"
                            class="clay-select w-full">
                            <option value="">Semua Kategori</option>
                            <template x-for="cat in categories" :key="cat">
                                <option :value="cat" :selected="cat === category" x-text="cat"></option>
                            </template>
                        </select>
                    </div>

                    <!-- Chain Filter -->
                    <div class="clay-card bg-secondary/5 p-3">
                        <label for="chain" class="block font-medium text-gray-700 mb-2 text-sm">Blockchain</label>
                        <div x-show="loadingFilters" class="py-2">
                            <div class="inline-block animate-spin rounded-full h-5 w-5 border-t-2 border-b-2 border-secondary"></div>
                            <span class="ml-2 text-sm text-gray-500">Memuat...</span>
                        </div>
                        <select
                            x-show="!loadingFilters"
                            name="chain"
                            id="chain"
                            x-model="chain"
                            class="clay-select w-full">
                            <option value="">Semua Blockchain</option>
                            <template x-for="ch in chains" :key="ch">
                                <option :value="ch" :selected="ch === chain" x-text="ch"></option>
                            </template>
                        </select>
                    </div>

                    <!-- Limit Filter -->
                    <div class="clay-card bg-info/5 p-3">
                        <label for="limit" class="block font-medium text-gray-700 mb-2 text-sm">Jumlah Rekomendasi</label>
                        <select name="limit" id="limit" class="clay-select w-full">
                            <option value="10" {{ request()->input('limit') == 10 ? 'selected' : '' }}>10 Rekomendasi</option>
                            <option value="20" {{ request()->input('limit') == 20 ? 'selected' : '' }}>20 Rekomendasi</option>
                            <option value="30" {{ request()->input('limit') == 30 ? 'selected' : '' }}>30 Rekomendasi</option>
                        </select>
                    </div>

                    <!-- Advanced Options -->
                    <div class="clay-card bg-warning/5 p-3">
                        <div class="flex items-center mb-3">
                            <input
                                type="checkbox"
                                name="strict_filter"
                                id="strict_filter"
                                value="1"
                                x-model="strictFilter"
                                class="mr-2">
                            <label for="strict_filter" class="font-medium text-sm">Filter Ketat</label>
                        </div>
                        <p class="text-xs text-gray-600 mb-3">
                            Opsi ini hanya menampilkan proyek yang benar-benar cocok dengan filter yang dipilih (tanpa fallback).
                        </p>

                        <button type="submit" class="clay-button clay-button-primary w-full">
                            <i class="fas fa-search mr-2"></i> Terapkan Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Model Tabs dengan Lazy Loading - CONSERVATIVE FIX -->
    <div class="clay-card p-6 mb-8" x-data="{
        activeTab: 'hybrid',
        loading: {
            hybrid: false,
            fecf: false,
            ncf: false
        },
        hybridRecommendations: [],
        fecfRecommendations: [],
        ncfRecommendations: []
    }">
        <div class="clay-tabs mb-6">
            <button @click="activeTab = 'hybrid'" :class="{ 'active': activeTab === 'hybrid' }" class="clay-tab">
                <i class="fas fa-code-branch mr-2"></i> Hybrid Model
            </button>
            <button @click="activeTab = 'fecf'; loadFecfData()" :class="{ 'active': activeTab === 'fecf' }" class="clay-tab">
                <i class="fas fa-table mr-2"></i> Feature-Enhanced CF
            </button>
            <button @click="activeTab = 'ncf'; loadNcfData()" :class="{ 'active': activeTab === 'ncf' }" class="clay-tab">
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

        <!-- PERBAIKAN: Methods untuk load data dengan proper implementation -->
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('recommendationTabs', () => ({
                    // ... existing data properties

                    // PERBAIKAN: Method untuk load FECF data
                    loadFecfData() {
                        if (this.fecfRecommendations.length > 0 || this.loading.fecf) {
                            return; // Sudah dimuat atau sedang loading
                        }

                        this.loading.fecf = true;
                        console.log('Loading FECF recommendations...');

                        const params = new URLSearchParams({
                            model: 'fecf',
                            format: 'json',
                            category: '{{ $selectedCategory ?? '' }}',
                            chain: '{{ $selectedChain ?? '' }}',
                            strict_filter: '{{ $strictFilter ? '1' : '0' }}',
                            limit: '{{ request()->input('limit', 10) }}'
                        });

                        fetch('{{ route('panel.recommendations.personal') }}?' + params.toString())
                            .then(response => response.json())
                            .then(data => {
                                console.log('FECF Response:', data);
                                this.fecfRecommendations = Array.isArray(data) ? data : [];
                                this.loading.fecf = false;
                            })
                            .catch(error => {
                                console.error('Error loading FECF recommendations:', error);
                                this.fecfRecommendations = [];
                                this.loading.fecf = false;
                            });
                    },

                    // PERBAIKAN: Method untuk load NCF data
                    loadNcfData() {
                        if (this.ncfRecommendations.length > 0 || this.loading.ncf) {
                            return; // Sudah dimuat atau sedang loading
                        }

                        this.loading.ncf = true;
                        console.log('Loading NCF recommendations...');

                        const params = new URLSearchParams({
                            model: 'ncf',
                            format: 'json',
                            category: '{{ $selectedCategory ?? '' }}',
                            chain: '{{ $selectedChain ?? '' }}',
                            strict_filter: '{{ $strictFilter ? '1' : '0' }}',
                            limit: '{{ request()->input('limit', 10) }}'
                        });

                        fetch('{{ route('panel.recommendations.personal') }}?' + params.toString())
                            .then(response => response.json())
                            .then(data => {
                                console.log('NCF Response:', data);
                                this.ncfRecommendations = Array.isArray(data) ? data : [];
                                this.loading.ncf = false;
                            })
                            .catch(error => {
                                console.error('Error loading NCF recommendations:', error);
                                this.ncfRecommendations = [];
                                this.loading.ncf = false;
                            });
                    }
                }))
            })
        </script>

        <!-- Initialization Script - Load Hybrid data from server -->
        <div x-init="
            @if(isset($hybridRecommendations) && !empty($hybridRecommendations))
                hybridRecommendations = {{ json_encode($hybridRecommendations) }};
                console.log('Hybrid recommendations loaded from server:', hybridRecommendations.length);
            @else
                console.log('No hybrid recommendations from server, will load via AJAX if needed');
                hybridRecommendations = [];
            @endif

            // PERBAIKAN: Pre-load FECF data jika sudah ada dari server
            @if(isset($fecfRecommendations) && !empty($fecfRecommendations))
                fecfRecommendations = {{ json_encode($fecfRecommendations) }};
                console.log('FECF recommendations loaded from server:', fecfRecommendations.length);
            @endif

            // PERBAIKAN: Pre-load NCF data jika sudah ada dari server
            @if(isset($ncfRecommendations) && !empty($ncfRecommendations))
                ncfRecommendations = {{ json_encode($ncfRecommendations) }};
                console.log('NCF recommendations loaded from server:', ncfRecommendations.length);
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
                    <div class="flex flex-wrap gap-2 mb-3">
                        <div class="clay-badge clay-badge-info" x-text="recommendation.primary_category || recommendation.category || 'Umum'"></div>
                        <div class="clay-badge clay-badge-secondary" x-text="recommendation.chain || 'Multiple'"></div>
                        <!-- Filter match badge -->
                        <template x-if="recommendation.filter_match">
                            <div class="clay-badge" :class="{
                                'clay-badge-success': recommendation.filter_match === 'exact',
                                'clay-badge-warning': recommendation.filter_match === 'category_only' || recommendation.filter_match === 'chain_only',
                                'clay-badge-info': recommendation.filter_match === 'chain_popular',
                                'clay-badge-secondary': recommendation.filter_match === 'fallback'
                            }">
                                <span x-text="recommendation.filter_match === 'exact' ? 'Akurat' :
                                    (recommendation.filter_match === 'category_only' ? 'Kategori' :
                                    (recommendation.filter_match === 'chain_only' ? 'Chain' :
                                    (recommendation.filter_match === 'chain_popular' ? 'Chain Populer' : 'Tambahan')))">
                                </span>
                            </div>
                        </template>
                    </div>
                    <div class="flex justify-between items-center">
                        <div class="text-xs font-medium">Score: <span class="text-primary"
                                x-text="(recommendation.recommendation_score || 0).toFixed(2)"></span></div>
                        <div class="flex space-x-2">
                            <a :href="'/panel/recommendations/project/' + recommendation.id" class="clay-badge clay-badge-secondary px-2 py-1 text-xs">
                                <i class="fas fa-info-circle mr-1"></i> Detail
                            </a>
                            <form method="POST" action="{{ route('panel.recommendations.add-favorite') }}" class="inline">
                                @csrf
                                <input type="hidden" name="project_id" :value="recommendation.id">
                                <button type="submit" class="clay-badge clay-badge-primary px-2 py-1 text-xs">
                                    <i class="fas fa-heart mr-1"></i> Sukai
                                </button>
                            </form>
                        </div>
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
                    <div class="flex flex-wrap gap-2 mb-3">
                        <div class="clay-badge clay-badge-info" x-text="recommendation.primary_category || recommendation.category || 'Umum'"></div>
                        <div class="clay-badge clay-badge-secondary" x-text="recommendation.chain || 'Multiple'"></div>
                        <!-- Filter match badge -->
                        <template x-if="recommendation.filter_match">
                            <div class="clay-badge" :class="{
                                'clay-badge-success': recommendation.filter_match === 'exact',
                                'clay-badge-warning': recommendation.filter_match === 'category_only' || recommendation.filter_match === 'chain_only',
                                'clay-badge-info': recommendation.filter_match === 'chain_popular',
                                'clay-badge-secondary': recommendation.filter_match === 'fallback'
                            }">
                                <span x-text="recommendation.filter_match === 'exact' ? 'Akurat' :
                                    (recommendation.filter_match === 'category_only' ? 'Kategori' :
                                    (recommendation.filter_match === 'chain_only' ? 'Chain' :
                                    (recommendation.filter_match === 'chain_popular' ? 'Chain Populer' : 'Tambahan')))">
                                </span>
                            </div>
                        </template>
                    </div>
                    <div class="flex justify-between items-center">
                        <div class="text-xs font-medium">Score: <span class="text-primary"
                                x-text="(recommendation.recommendation_score || 0).toFixed(2)"></span></div>
                        <div class="flex space-x-2">
                            <a :href="'/panel/recommendations/project/' + recommendation.id" class="clay-badge clay-badge-secondary px-2 py-1 text-xs">
                                <i class="fas fa-info-circle mr-1"></i> Detail
                            </a>
                            <form method="POST" action="{{ route('panel.recommendations.add-favorite') }}" class="inline">
                                @csrf
                                <input type="hidden" name="project_id" :value="recommendation.id">
                                <button type="submit" class="clay-badge clay-badge-primary px-2 py-1 text-xs">
                                    <i class="fas fa-heart mr-1"></i> Sukai
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </template>

            <template x-if="fecfRecommendations.length === 0">
                <div class="col-span-full text-center py-8">
                    <div class="clay-alert clay-alert-info">
                        <p>Tidak ada rekomendasi FECF yang tersedia saat ini.</p>
                        <p class="text-sm mt-2">FECF model mungkin tidak memiliki data yang cukup untuk filter yang dipilih.</p>
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
                    <div class="flex flex-wrap gap-2 mb-3">
                        <div class="clay-badge clay-badge-info" x-text="recommendation.primary_category || recommendation.category || 'Umum'"></div>
                        <div class="clay-badge clay-badge-secondary" x-text="recommendation.chain || 'Multiple'"></div>
                        <!-- Filter match badge -->
                        <template x-if="recommendation.filter_match">
                            <div class="clay-badge" :class="{
                                'clay-badge-success': recommendation.filter_match === 'exact',
                                'clay-badge-warning': recommendation.filter_match === 'category_only' || recommendation.filter_match === 'chain_only',
                                'clay-badge-info': recommendation.filter_match === 'chain_popular',
                                'clay-badge-secondary': recommendation.filter_match === 'fallback'
                            }">
                                <span x-text="recommendation.filter_match === 'exact' ? 'Akurat' :
                                    (recommendation.filter_match === 'category_only' ? 'Kategori' :
                                    (recommendation.filter_match === 'chain_only' ? 'Chain' :
                                    (recommendation.filter_match === 'chain_popular' ? 'Chain Populer' : 'Tambahan')))">
                                </span>
                            </div>
                        </template>
                    </div>
                    <div class="flex justify-between items-center">
                        <div class="text-xs font-medium">Score: <span class="text-primary"
                                x-text="(recommendation.recommendation_score || 0).toFixed(2)"></span></div>
                        <div class="flex space-x-2">
                            <a :href="'/panel/recommendations/project/' + recommendation.id" class="clay-badge clay-badge-secondary px-2 py-1 text-xs">
                                <i class="fas fa-info-circle mr-1"></i> Detail
                            </a>
                            <form method="POST" action="{{ route('panel.recommendations.add-favorite') }}" class="inline">
                                @csrf
                                <input type="hidden" name="project_id" :value="recommendation.id">
                                <button type="submit" class="clay-badge clay-badge-primary px-2 py-1 text-xs">
                                    <i class="fas fa-heart mr-1"></i> Sukai
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </template>

            <template x-if="ncfRecommendations.length === 0">
                <div class="col-span-full text-center py-8">
                    <div class="clay-alert clay-alert-info">
                        <p>Tidak ada rekomendasi NCF yang tersedia saat ini.</p>
                        <p class="text-sm mt-2">NCF model memerlukan lebih banyak data untuk menghasilkan rekomendasi.</p>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Kategori & Chain Info -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-info-circle mr-2 text-info"></i>
            Kategori & Blockchain
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Kategori -->
            <div class="clay-card bg-primary/10 p-4" x-data="{ showAllCategories: false }">
                <h3 class="font-bold mb-3 flex justify-between items-center">
                    <span>Kategori Proyek</span>
                    <button @click="showAllCategories = !showAllCategories" class="text-xs clay-button py-1 px-2"
                        :class="showAllCategories ? 'clay-button-secondary' : 'clay-button-primary'">
                        <span x-text="showAllCategories ? 'Sembunyikan' : 'Lihat Semua'"></span>
                    </button>
                </h3>

                <div class="flex flex-wrap gap-2">
                    <template x-for="(category, index) in {{ json_encode($categories ?? []) }}" :key="index">
                        <template x-if="index < 10 || showAllCategories">
                            <a :href="'{{ route('panel.recommendations.personal') }}?category=' + category"
                                class="clay-badge clay-badge-primary py-1 px-2 hover:translate-y-[-2px] transition-transform"
                                x-text="category">
                            </a>
                        </template>
                    </template>

                    <template x-if="!showAllCategories && {{ json_encode(count($categories ?? [])) }} > 10">
                        <span class="text-xs text-gray-500 mt-1">
                            ...dan {{ count($categories ?? []) - 10 }} kategori lainnya
                        </span>
                    </template>
                </div>
            </div>

            <!-- Blockchain -->
            <div class="clay-card bg-secondary/10 p-4" x-data="{ showAllChains: false }">
                <h3 class="font-bold mb-3 flex justify-between items-center">
                    <span>Blockchain</span>
                    <button @click="showAllChains = !showAllChains" class="text-xs clay-button py-1 px-2"
                        :class="showAllChains ? 'clay-button-secondary' : 'clay-button-secondary'">
                        <span x-text="showAllChains ? 'Sembunyikan' : 'Lihat Semua'"></span>
                    </button>
                </h3>

                <div class="flex flex-wrap gap-2">
                    <template x-for="(chain, index) in {{ json_encode($chains ?? []) }}" :key="index">
                        <template x-if="index < 10 || showAllChains">
                            <a :href="'{{ route('panel.recommendations.personal') }}?chain=' + chain"
                                class="clay-badge clay-badge-secondary py-1 px-2 hover:translate-y-[-2px] transition-transform"
                                x-text="chain">
                            </a>
                        </template>
                    </template>

                    <template x-if="!showAllChains && {{ json_encode(count($chains ?? [])) }} > 10">
                        <span class="text-xs text-gray-500 mt-1">
                            ...dan {{ count($chains ?? []) - 10 }} blockchain lainnya
                        </span>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- How Recommendations Work -->
    <div class="clay-card p-6">
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

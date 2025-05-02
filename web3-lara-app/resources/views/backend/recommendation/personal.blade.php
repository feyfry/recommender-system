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
    @php
        $interactionCount = \App\Models\Interaction::where('user_id', Auth::user()->user_id)->count();
        $isColdStart = $interactionCount < 5;
    @endphp

    @if($isColdStart)
    <div class="clay-alert clay-alert-info mb-6">
        <p class="flex items-center">
            <i class="fas fa-info-circle mr-2"></i>
            Anda terdeteksi sebagai pengguna baru (cold-start). Rekomendasi yang ditampilkan berikut adalah rekomendasi awal berdasarkan tren dan popularitas. Rekomendasi akan menjadi lebih personal setelah Anda berinteraksi dengan lebih banyak proyek.
        </p>
    </div>
    @endif

    <!-- Model Tabs -->
    <div class="clay-card p-6 mb-8">
        <div x-data="{ activeTab: 'hybrid' }">
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

            <!-- PERBAIKAN: Tambahkan spinner loading untuk indikasi visual -->
            <div id="loading-recommendations" class="hidden text-center py-4">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                <p class="mt-2">Memuat rekomendasi...</p>
            </div>

            <!-- Recommendation List -->
            <div x-show="activeTab === 'hybrid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($hybridRecommendations ?? [] as $recommendation)
                <!-- PERBAIKAN: Tambahkan cek data lebih ketat -->
                @if(isset($recommendation['id']) && isset($recommendation['name']) && isset($recommendation['recommendation_score']))
                <div class="clay-card p-4 hover:translate-y-[-5px] transition-transform">
                    <div class="font-bold text-lg mb-2">{{ $recommendation['name'] ?? 'Unknown Project' }} ({{ $recommendation['symbol'] ?? 'N/A' }})</div>
                    <div class="text-sm mb-2">
                        {{ '$'.number_format($recommendation['price_usd'] ?? 0, 2) }}
                        <span class="{{ ($recommendation['price_change_24h'] ?? 0) > 0 ? 'text-success' : 'text-danger' }}">
                            {{ ($recommendation['price_change_24h'] ?? 0) > 0 ? '+' : '' }}
                            {{ number_format($recommendation['price_change_24h'] ?? 0, 2) }}%
                        </span>
                    </div>
                    <div class="clay-badge clay-badge-info mb-3">
                        {{ $recommendation['primary_category'] ?? $recommendation['category'] ?? 'Umum' }}
                    </div>
                    <p class="text-sm mb-3 line-clamp-2">
                        {{ $recommendation['description'] ?? 'Tidak ada deskripsi' }}
                    </p>
                    <div class="flex justify-between items-center">
                        <div class="text-xs font-medium">Score: <span class="text-primary">
                            {{ number_format($recommendation['recommendation_score'] ?? 0, 2) }}
                        </span></div>
                        <a href="{{ route('panel.recommendations.project', $recommendation['id'] ?? 'unknown') }}" class="clay-badge clay-badge-secondary px-2 py-1 text-xs">
                            <i class="fas fa-info-circle mr-1"></i> Detail
                        </a>
                    </div>
                </div>
                @endif
                @empty
                <div class="col-span-full text-center py-8">
                    <div class="clay-alert clay-alert-info">
                        <p>Tidak ada rekomendasi hybrid yang tersedia saat ini.</p>
                        <p class="text-sm mt-2">Mulai berinteraksi dengan proyek untuk mendapatkan rekomendasi yang lebih baik.</p>

                        <!-- PERBAIKAN: Tombol refresh manual untuk cold-start user -->
                        @if($isColdStart)
                        <button onclick="window.location.reload()" class="clay-button clay-button-primary mt-4">
                            <i class="fas fa-sync-alt mr-1"></i> Coba Muat Ulang
                        </button>
                        @endif
                    </div>
                </div>
                @endforelse
            </div>

            <div x-show="activeTab === 'fecf'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($fecfRecommendations ?? [] as $recommendation)
                <!-- PERBAIKAN: Standarisasi format data dari berbagai sumber -->
                @php
                    // Ubah dari object atau array menjadi array standar
                    $rec = is_object($recommendation) ? (array)$recommendation : $recommendation;

                    // Pastikan key-key utama ada
                    if (!isset($rec['id']) && isset($rec->id)) $rec['id'] = $rec->id;
                    if (!isset($rec['name']) && isset($rec->name)) $rec['name'] = $rec->name;
                    if (!isset($rec['symbol']) && isset($rec->symbol)) $rec['symbol'] = $rec->symbol;

                    // Pastikan price_usd ada
                    if (!isset($rec['price_usd']) && isset($rec['current_price'])) {
                        $rec['price_usd'] = $rec['current_price'];
                    } elseif (!isset($rec['price_usd']) && isset($rec->price_usd)) {
                        $rec['price_usd'] = $rec->price_usd;
                    } elseif (!isset($rec['price_usd']) && isset($rec->current_price)) {
                        $rec['price_usd'] = $rec->current_price;
                    }

                    // Pastikan score ada
                    if (!isset($rec['recommendation_score']) && isset($rec->recommendation_score)) {
                        $rec['recommendation_score'] = $rec->recommendation_score;
                    }

                    // Pastikan category/primary_category ada
                    $category = $rec['primary_category'] ?? $rec['category'] ?? $rec->primary_category ?? $rec->category ?? 'Umum';
                @endphp

                @if(isset($rec['id']) && isset($rec['name']))
                <div class="clay-card p-4 hover:translate-y-[-5px] transition-transform">
                    <div class="font-bold text-lg mb-2">{{ $rec['name'] }} ({{ $rec['symbol'] ?? 'N/A' }})</div>
                    <div class="text-sm mb-2">
                        {{ '$'.number_format($rec['price_usd'] ?? 0, 2) }}
                        <span class="{{ ($rec['price_change_24h'] ?? $rec['price_change_percentage_24h'] ?? 0) > 0 ? 'text-success' : 'text-danger' }}">
                            {{ ($rec['price_change_24h'] ?? $rec['price_change_percentage_24h'] ?? 0) > 0 ? '+' : '' }}
                            {{ number_format($rec['price_change_24h'] ?? $rec['price_change_percentage_24h'] ?? 0, 2) }}%
                        </span>
                    </div>
                    <div class="clay-badge clay-badge-info mb-3">
                        {{ $category }}
                    </div>
                    <p class="text-sm mb-3 line-clamp-2">
                        {{ $rec['description'] ?? 'Tidak ada deskripsi' }}
                    </p>
                    <div class="flex justify-between items-center">
                        <div class="text-xs font-medium">Score: <span class="text-primary">
                            {{ number_format($rec['recommendation_score'] ?? 0, 2) }}
                        </span></div>
                        <a href="{{ route('panel.recommendations.project', $rec['id']) }}" class="clay-badge clay-badge-secondary px-2 py-1 text-xs">
                            <i class="fas fa-info-circle mr-1"></i> Detail
                        </a>
                    </div>
                </div>
                @endif
                @empty
                <div class="col-span-full text-center py-8">
                    <div class="clay-alert clay-alert-info">
                        <p>Tidak ada rekomendasi FECF yang tersedia saat ini.</p>
                        <p class="text-sm mt-2">Mulai berinteraksi dengan proyek untuk mendapatkan rekomendasi yang lebih baik.</p>

                        @if($isColdStart)
                        <button onclick="window.location.reload()" class="clay-button clay-button-primary mt-4">
                            <i class="fas fa-sync-alt mr-1"></i> Coba Muat Ulang
                        </button>
                        @endif
                    </div>
                </div>
                @endforelse
            </div>

            <div x-show="activeTab === 'ncf'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($ncfRecommendations ?? [] as $recommendation)
                <!-- PERBAIKAN: Standarisasi format data dari berbagai sumber -->
                @php
                    // Ubah dari object atau array menjadi array standar
                    $rec = is_object($recommendation) ? (array)$recommendation : $recommendation;

                    // Pastikan key-key utama ada
                    if (!isset($rec['id']) && isset($rec->id)) $rec['id'] = $rec->id;
                    if (!isset($rec['name']) && isset($rec->name)) $rec['name'] = $rec->name;
                    if (!isset($rec['symbol']) && isset($rec->symbol)) $rec['symbol'] = $rec->symbol;

                    // Pastikan price_usd ada
                    if (!isset($rec['price_usd']) && isset($rec['current_price'])) {
                        $rec['price_usd'] = $rec['current_price'];
                    } elseif (!isset($rec['price_usd']) && isset($rec->price_usd)) {
                        $rec['price_usd'] = $rec->price_usd;
                    } elseif (!isset($rec['price_usd']) && isset($rec->current_price)) {
                        $rec['price_usd'] = $rec->current_price;
                    }

                    // Pastikan score ada
                    if (!isset($rec['recommendation_score']) && isset($rec->recommendation_score)) {
                        $rec['recommendation_score'] = $rec->recommendation_score;
                    }

                    // Pastikan category/primary_category ada
                    $category = $rec['primary_category'] ?? $rec['category'] ?? $rec->primary_category ?? $rec->category ?? 'Umum';
                @endphp

                @if(isset($rec['id']) && isset($rec['name']))
                <div class="clay-card p-4 hover:translate-y-[-5px] transition-transform">
                    <div class="font-bold text-lg mb-2">{{ $rec['name'] }} ({{ $rec['symbol'] ?? 'N/A' }})</div>
                    <div class="text-sm mb-2">
                        {{ '$'.number_format($rec['price_usd'] ?? 0, 2) }}
                        <span class="{{ ($rec['price_change_24h'] ?? $rec['price_change_percentage_24h'] ?? 0) > 0 ? 'text-success' : 'text-danger' }}">
                            {{ ($rec['price_change_24h'] ?? $rec['price_change_percentage_24h'] ?? 0) > 0 ? '+' : '' }}
                            {{ number_format($rec['price_change_24h'] ?? $rec['price_change_percentage_24h'] ?? 0, 2) }}%
                        </span>
                    </div>
                    <div class="clay-badge clay-badge-info mb-3">
                        {{ $category }}
                    </div>
                    <p class="text-sm mb-3 line-clamp-2">
                        {{ $rec['description'] ?? 'Tidak ada deskripsi' }}
                    </p>
                    <div class="flex justify-between items-center">
                        <div class="text-xs font-medium">Score: <span class="text-primary">
                            {{ number_format($rec['recommendation_score'] ?? 0, 2) }}
                        </span></div>
                        <a href="{{ route('panel.recommendations.project', $rec['id']) }}" class="clay-badge clay-badge-secondary px-2 py-1 text-xs">
                            <i class="fas fa-info-circle mr-1"></i> Detail
                        </a>
                    </div>
                </div>
                @endif
                @empty
                <div class="col-span-full text-center py-8">
                    <div class="clay-alert clay-alert-info">
                        <p>Tidak ada rekomendasi NCF yang tersedia saat ini.</p>
                        <p class="text-sm mt-2">Mulai berinteraksi dengan proyek untuk mendapatkan rekomendasi yang lebih baik.</p>

                        @if($isColdStart)
                        <button onclick="window.location.reload()" class="clay-button clay-button-primary mt-4">
                            <i class="fas fa-sync-alt mr-1"></i> Coba Muat Ulang
                        </button>
                        @endif
                    </div>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- User Preferences Information -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-sliders-h mr-2 text-primary"></i>
            Preferensi Anda
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="clay-card bg-secondary/10 p-4">
                <h3 class="font-bold mb-3">Profil Investasi</h3>
                <div class="space-y-2">
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

            <div class="clay-card bg-info/10 p-4">
                <h3 class="font-bold mb-3">Kategori & Chain Pilihan</h3>

                <div class="mb-3">
                    <div class="font-medium mb-1">Kategori:</div>
                    <div class="flex flex-wrap gap-2">
                        @forelse(Auth::user()->profile->preferred_categories ?? [] as $category)
                            <span class="clay-badge clay-badge-primary">{{ $category }}</span>
                        @empty
                            <span class="text-gray-400">Belum ada kategori yang dipilih</span>
                        @endforelse
                    </div>
                </div>

                <div>
                    <div class="font-medium mb-1">Blockchain:</div>
                    <div class="flex flex-wrap gap-2">
                        @forelse(Auth::user()->profile->preferred_chains ?? [] as $chain)
                            <span class="clay-badge clay-badge-secondary">{{ $chain }}</span>
                        @empty
                            <span class="text-gray-400">Belum ada blockchain yang dipilih</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Interaction History -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-history mr-2 text-warning"></i>
            Riwayat Interaksi Terbaru
        </h2>

        <div class="overflow-x-auto">
            <table class="clay-table min-w-full">
                <thead>
                    <tr>
                        <th class="py-2 px-4 text-left">Proyek</th>
                        <th class="py-2 px-4 text-left">Tipe Interaksi</th>
                        <th class="py-2 px-4 text-left">Waktu</th>
                        <th class="py-2 px-4 text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($interactions ?? [] as $interaction)
                    <tr>
                        <td class="py-2 px-4 font-medium">
                            <div class="flex items-center">
                                @if($interaction->project && $interaction->project->image)
                                    <img src="{{ $interaction->project->image }}" alt="{{ $interaction->project->symbol }}" class="w-6 h-6 mr-2 rounded-full">
                                @endif
                                {{ $interaction->project ? $interaction->project->name : 'Unknown' }}
                                ({{ $interaction->project ? $interaction->project->symbol : '?' }})
                            </div>
                        </td>
                        <td class="py-2 px-4">
                            @if($interaction->interaction_type == 'view')
                                <span class="clay-badge clay-badge-info">View</span>
                            @elseif($interaction->interaction_type == 'favorite')
                                <span class="clay-badge clay-badge-secondary">Favorite</span>
                            @elseif($interaction->interaction_type == 'portfolio_add')
                                <span class="clay-badge clay-badge-success">Portfolio</span>
                            @else
                                <span class="clay-badge clay-badge-warning">{{ $interaction->interaction_type }}</span>
                            @endif
                        </td>
                        <td class="py-2 px-4 text-gray-500 text-sm">{{ $interaction->created_at->diffForHumans() }}</td>
                        <td class="py-2 px-4">
                            <a href="{{ route('panel.recommendations.project', $interaction->project_id) }}" class="clay-badge clay-badge-info py-1 px-2 text-xs">
                                <i class="fas fa-info-circle mr-1"></i> Detail
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="py-4 px-4 text-center">
                            Belum ada interaksi yang tercatat

                            <!-- PERBAIKAN: Tambahkan tombol untuk mulai menjelajah -->
                            <div class="mt-3">
                                <a href="{{ route('panel.recommendations.trending') }}" class="clay-button clay-button-warning py-1.5 px-3 text-sm">
                                    <i class="fas fa-fire mr-1"></i> Jelajahi Proyek Trending
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
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

<!-- PERBAIKAN: Auto-refresh untuk memastikan rekomendasi cold-start dimuat -->
@if($isColdStart)
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const loadingIndicator = document.getElementById('loading-recommendations');

        // Tampilkan loading indicator
        if (loadingIndicator) {
            loadingIndicator.classList.remove('hidden');
        }

        // Jika tidak ada rekomendasi, coba muat ulang setelah 5 detik
        const hasHybridRecommendations = {{ !empty($hybridRecommendations) ? 'true' : 'false' }};
        const hasFecfRecommendations = {{ !empty($fecfRecommendations) ? 'true' : 'false' }};
        const hasNcfRecommendations = {{ !empty($ncfRecommendations) ? 'true' : 'false' }};

        if (!hasHybridRecommendations && !hasFecfRecommendations && !hasNcfRecommendations) {
            setTimeout(function() {
                window.location.reload();
            }, 5000);
        } else {
            // Sembunyikan loading indicator jika sudah ada rekomendasi
            if (loadingIndicator) {
                loadingIndicator.classList.add('hidden');
            }
        }
    });
</script>
@endif
@endsection

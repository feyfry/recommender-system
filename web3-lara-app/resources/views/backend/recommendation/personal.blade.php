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
                    <div class="flex items-center mt-2">
                        <div class="clay-badge clay-badge-success mr-2">Hit Ratio: 0.8788</div>
                        <div class="clay-badge clay-badge-primary">NDCG: 0.2954</div>
                    </div>
                </div>
                <div x-show="activeTab === 'fecf'">
                    <h3 class="font-bold text-lg mb-2">Feature-Enhanced Collaborative Filtering</h3>
                    <p>Model yang menggunakan SVD (Singular Value Decomposition) dan menggabungkannya dengan informasi fitur proyek (kategori, chain, dll).</p>
                    <div class="flex items-center mt-2">
                        <div class="clay-badge clay-badge-success mr-2">Hit Ratio: 0.8148</div>
                        <div class="clay-badge clay-badge-primary">NDCG: 0.2945</div>
                    </div>
                </div>
                <div x-show="activeTab === 'ncf'">
                    <h3 class="font-bold text-lg mb-2">Neural Collaborative Filtering</h3>
                    <p>Model deep learning yang menangkap pola kompleks dalam interaksi antara pengguna dan proyek cryptocurrency.</p>
                    <div class="flex items-center mt-2">
                        <div class="clay-badge clay-badge-success mr-2">Hit Ratio: 0.7138</div>
                        <div class="clay-badge clay-badge-primary">NDCG: 0.1986</div>
                    </div>
                </div>
            </div>

            <!-- Recommendation List -->
            <div x-show="activeTab === 'hybrid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($hybridRecommendations ?? [] as $recommendation)
                <div class="clay-card p-4 hover:translate-y-[-5px] transition-transform">
                    <div class="font-bold text-lg mb-2">{{ $recommendation->name ?? $recommendation['name'] }} ({{ $recommendation->symbol ?? $recommendation['symbol'] }})</div>
                    <div class="text-sm mb-2">
                        {{ $recommendation->formatted_price ?? '$'.number_format($recommendation->price_usd ?? $recommendation['price_usd'], 2) }}
                        <span class="{{ ($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0) > 0 ? 'text-success' : 'text-danger' }}">
                            {{ ($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0) > 0 ? '+' : '' }}
                            {{ number_format($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0, 2) }}%
                        </span>
                    </div>
                    <div class="clay-badge clay-badge-info mb-3">
                        {{ $recommendation->primary_category ?? $recommendation['primary_category'] ?? 'Umum' }}
                    </div>
                    <p class="text-sm mb-3 line-clamp-2">
                        {{ $recommendation->description ?? $recommendation['description'] ?? 'Tidak ada deskripsi' }}
                    </p>
                    <div class="flex justify-between items-center">
                        <div class="text-xs font-medium">Score: <span class="text-primary">
                            {{ number_format($recommendation->recommendation_score ?? $recommendation['recommendation_score'] ?? 0, 2) }}
                        </span></div>
                        <a href="{{ route('panel.recommendations.project', $recommendation->id ?? $recommendation['id']) }}" class="clay-badge clay-badge-secondary px-2 py-1 text-xs">
                            <i class="fas fa-info-circle mr-1"></i> Detail
                        </a>
                    </div>
                </div>
                @empty
                <div class="col-span-full text-center py-8">
                    <div class="clay-alert clay-alert-info">
                        <p>Tidak ada rekomendasi hybrid yang tersedia saat ini.</p>
                        <p class="text-sm mt-2">Mulai berinteraksi dengan proyek untuk mendapatkan rekomendasi yang lebih baik.</p>
                    </div>
                </div>
                @endforelse
            </div>

            <div x-show="activeTab === 'fecf'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($fecfRecommendations ?? [] as $recommendation)
                <div class="clay-card p-4 hover:translate-y-[-5px] transition-transform">
                    <div class="font-bold text-lg mb-2">{{ $recommendation->name ?? $recommendation['name'] }} ({{ $recommendation->symbol ?? $recommendation['symbol'] }})</div>
                    <div class="text-sm mb-2">
                        {{ $recommendation->formatted_price ?? '$'.number_format($recommendation->price_usd ?? $recommendation['price_usd'], 2) }}
                        <span class="{{ ($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0) > 0 ? 'text-success' : 'text-danger' }}">
                            {{ ($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0) > 0 ? '+' : '' }}
                            {{ number_format($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0, 2) }}%
                        </span>
                    </div>
                    <div class="clay-badge clay-badge-info mb-3">
                        {{ $recommendation->primary_category ?? $recommendation['primary_category'] ?? 'Umum' }}
                    </div>
                    <p class="text-sm mb-3 line-clamp-2">
                        {{ $recommendation->description ?? $recommendation['description'] ?? 'Tidak ada deskripsi' }}
                    </p>
                    <div class="flex justify-between items-center">
                        <div class="text-xs font-medium">Score: <span class="text-primary">
                            {{ number_format($recommendation->recommendation_score ?? $recommendation['recommendation_score'] ?? 0, 2) }}
                        </span></div>
                        <a href="{{ route('panel.recommendations.project', $recommendation->id ?? $recommendation['id']) }}" class="clay-badge clay-badge-secondary px-2 py-1 text-xs">
                            <i class="fas fa-info-circle mr-1"></i> Detail
                        </a>
                    </div>
                </div>
                @empty
                <div class="col-span-full text-center py-8">
                    <div class="clay-alert clay-alert-info">
                        <p>Tidak ada rekomendasi FECF yang tersedia saat ini.</p>
                        <p class="text-sm mt-2">Mulai berinteraksi dengan proyek untuk mendapatkan rekomendasi yang lebih baik.</p>
                    </div>
                </div>
                @endforelse
            </div>

            <div x-show="activeTab === 'ncf'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($ncfRecommendations ?? [] as $recommendation)
                <div class="clay-card p-4 hover:translate-y-[-5px] transition-transform">
                    <div class="font-bold text-lg mb-2">{{ $recommendation->name ?? $recommendation['name'] }} ({{ $recommendation->symbol ?? $recommendation['symbol'] }})</div>
                    <div class="text-sm mb-2">
                        {{ $recommendation->formatted_price ?? '$'.number_format($recommendation->price_usd ?? $recommendation['price_usd'], 2) }}
                        <span class="{{ ($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0) > 0 ? 'text-success' : 'text-danger' }}">
                            {{ ($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0) > 0 ? '+' : '' }}
                            {{ number_format($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0, 2) }}%
                        </span>
                    </div>
                    <div class="clay-badge clay-badge-info mb-3">
                        {{ $recommendation->primary_category ?? $recommendation['primary_category'] ?? 'Umum' }}
                    </div>
                    <p class="text-sm mb-3 line-clamp-2">
                        {{ $recommendation->description ?? $recommendation['description'] ?? 'Tidak ada deskripsi' }}
                    </p>
                    <div class="flex justify-between items-center">
                        <div class="text-xs font-medium">Score: <span class="text-primary">
                            {{ number_format($recommendation->recommendation_score ?? $recommendation['recommendation_score'] ?? 0, 2) }}
                        </span></div>
                        <a href="{{ route('panel.recommendations.project', $recommendation->id ?? $recommendation['id']) }}" class="clay-badge clay-badge-secondary px-2 py-1 text-xs">
                            <i class="fas fa-info-circle mr-1"></i> Detail
                        </a>
                    </div>
                </div>
                @empty
                <div class="col-span-full text-center py-8">
                    <div class="clay-alert clay-alert-info">
                        <p>Tidak ada rekomendasi NCF yang tersedia saat ini.</p>
                        <p class="text-sm mt-2">Mulai berinteraksi dengan proyek untuk mendapatkan rekomendasi yang lebih baik.</p>
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
                                @if($interaction->project->image)
                                    <img src="{{ $interaction->project->image }}" alt="{{ $interaction->project->symbol }}" class="w-6 h-6 mr-2 rounded-full">
                                @endif
                                {{ $interaction->project->name }} ({{ $interaction->project->symbol }})
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
                        <td colspan="4" class="py-4 px-4 text-center">Belum ada interaksi yang tercatat</td>
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
@endsection

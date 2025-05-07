@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <h1 class="text-3xl font-bold mb-4 flex items-center">
            <div class="bg-primary/20 p-2 clay-badge mr-3">
                <i class="fas fa-link text-primary"></i>
            </div>
            Blockchain
        </h1>
        <p class="text-lg">
            Telusuri rekomendasi proyek cryptocurrency berdasarkan blockchain untuk menemukan investasi yang sesuai dengan preferensi teknologi Anda.
        </p>
    </div>

    <!-- Chain Selection dengan Lazy Loading -->
    <div class="clay-card p-6 mb-8" x-data="{
        loading: true,
        chains: [],
        selectedChain: '{{ $selectedChain ?? 'ethereum' }}'
    }">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-filter mr-2 text-primary"></i>
            Pilih Blockchain
        </h2>

        <!-- Loading Indicator -->
        <div x-show="loading" class="py-4 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-primary"></div>
            <p class="mt-2 text-gray-500">Memuat daftar blockchain...</p>
        </div>

        <!-- Chains Selection Content -->
        <div x-show="!loading" x-init="
            @if(isset($chains) && !empty($chains))
                chains = {{ json_encode($chains) }};
                loading = false;
            @else
                fetch('{{ route('panel.recommendations.chains') }}?format=json&part=chains_list')
                    .then(response => response.json())
                    .then(data => {
                        chains = data.chains || [];
                        loading = false;
                    })
                    .catch(error => {
                        console.error('Error loading chains:', error);
                        // Default chains jika gagal
                        chains = ['ethereum', 'binance-smart-chain', 'polygon', 'solana', 'avalanche'];
                        loading = false;
                    });
            @endif
        ">
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                <template x-for="chain in chains" :key="chain">
                    <a :href="'{{ route('panel.recommendations.chains') }}?chain=' + chain.toLowerCase()"
                       :class="chain.toLowerCase() == selectedChain ? 'bg-primary/20 border-2 border-primary' : 'bg-primary/5'"
                       class="clay-card p-3 text-center hover:translate-y-[-2px] transition-transform">
                        <div class="font-bold mb-1" x-text="chain"></div>
                    </a>
                </template>

                <template x-if="chains.length === 0">
                    <div class="col-span-full clay-card p-4 text-center">
                        <p>Tidak ada blockchain yang tersedia.</p>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Chain Projects dengan Lazy Loading -->
    <div class="clay-card p-6 mb-8" x-data="{
        loading: true,
        chainRecommendations: [],
        selectedChain: '{{ $selectedChain ?? 'ethereum' }}'
    }">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold flex items-center">
                <i class="fas fa-list mr-2 text-primary"></i>
                <span>Proyek <span x-text="selectedChain.charAt(0).toUpperCase() + selectedChain.slice(1)"></span></span>
            </h2>

            <div class="flex space-x-2">
                <button @click="
                    loading = true;
                    fetch('{{ route('panel.recommendations.chains') }}?chain=' + selectedChain + '&format=json&refresh=true')
                        .then(response => response.json())
                        .then(data => {
                            chainRecommendations = data.recommendations || [];
                            loading = false;
                        })
                        .catch(error => {
                            console.error('Error refreshing chains:', error);
                            loading = false;
                        });"
                    type="button" class="clay-button py-1.5 px-3 text-sm">
                    <i class="fas fa-sync-alt mr-1" :class="{'animate-spin': loading}"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div x-show="loading" class="py-6 text-center">
            <div class="inline-block animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-primary"></div>
            <p class="mt-3 text-gray-500">Memuat rekomendasi blockchain...</p>
        </div>

        <!-- Chain Recommendations Grid -->
        <div x-show="!loading" x-init="
            @if(isset($chainRecommendations) && !empty($chainRecommendations))
                chainRecommendations = {{ json_encode($chainRecommendations) }};
                loading = false;
            @else
                fetch('{{ route('panel.recommendations.chains') }}?chain=' + selectedChain + '&format=json')
                    .then(response => response.json())
                    .then(data => {
                        chainRecommendations = data.recommendations || [];
                        loading = false;
                    })
                    .catch(error => {
                        console.error('Error loading chain recommendations:', error);
                        chainRecommendations = [];
                        loading = false;
                    });
            @endif
        ">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <template x-for="(recommendation, index) in chainRecommendations" :key="index">
                    <div class="clay-card p-4 hover:translate-y-[-5px] transition-transform">
                        <div class="font-bold text-lg mb-2" x-text="recommendation.name + ' (' + recommendation.symbol + ')'"></div>
                        <div class="flex justify-between mb-2 text-sm">
                            <span x-text="'$' + (recommendation.current_price ? recommendation.current_price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0.00')"></span>
                            <span :class="(recommendation.price_change_24h || 0) >= 0 ? 'text-success' : 'text-danger'"
                                x-text="((recommendation.price_change_24h || 0) >= 0 ? '+' : '') +
                                        ((recommendation.price_change_24h || 0).toFixed(2)) + '$'">
                            </span>
                        </div>
                        <div class="clay-badge clay-badge-primary mb-3" x-text="recommendation.primary_category || 'General'"></div>
                        <div class="flex justify-between items-center">
                            <div class="text-xs font-medium">Score: <span class="text-primary"
                                x-text="(recommendation.recommendation_score || 0).toFixed(2)"></span></div>
                            <div class="flex space-x-2">
                                <a :href="'/panel/recommendations/project/' + recommendation.id" class="clay-badge clay-badge-primary py-1 px-2 text-xs">
                                    <i class="fas fa-info-circle"></i> Detail
                                </a>
                                <form method="POST" action="{{ route('panel.recommendations.add-favorite') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="project_id" :value="recommendation.id">
                                    <button type="submit" class="clay-badge clay-badge-secondary py-1 px-2 text-xs">
                                        <i class="fas fa-heart"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </template>

                <template x-if="chainRecommendations.length === 0">
                    <div class="col-span-full clay-card p-6 text-center">
                        <p>Tidak ada proyek yang tersedia untuk blockchain ini.</p>
                        <p class="text-sm mt-2 text-gray-500">Coba pilih blockchain lain atau kembali nanti.</p>
                    </div>
                </template>
            </div>

            <!-- Show More Button (for future pagination support) -->
            <template x-if="chainRecommendations.length >= 16">
                <div class="mt-6 text-center">
                    <button @click="
                        loading = true;
                        const currentCount = chainRecommendations.length;
                        fetch('{{ route('panel.recommendations.chains') }}?chain=' + selectedChain + '&format=json&offset=' + currentCount)
                            .then(response => response.json())
                            .then(data => {
                                if (data.recommendations && data.recommendations.length > 0) {
                                    chainRecommendations = [...chainRecommendations, ...data.recommendations];
                                }
                                loading = false;
                            })
                            .catch(error => {
                                console.error('Error loading more projects:', error);
                                loading = false;
                            });"
                        class="clay-button clay-button-primary py-2 px-4">
                        <i class="fas fa-plus-circle mr-2"></i> Muat Lebih Banyak
                    </button>
                </div>
            </template>
        </div>
    </div>

    <!-- Multi-Chain Strategy -->
    <div class="clay-card p-6">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-globe mr-2 text-info"></i>
            Strategi Investasi Multi-Chain
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="clay-card bg-success/10 p-4">
                <h3 class="font-bold mb-2">Kelebihan Diversifikasi Blockchain</h3>
                <ul class="text-sm space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Mengurangi ketergantungan pada satu teknologi blockchain</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Memanfaatkan pertumbuhan berbagai ekosistem</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Mengakses aplikasi dan fitur unik dari setiap blockchain</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Menyeimbangkan risiko dan peluang inovasi</span>
                    </li>
                </ul>
            </div>

            <div class="clay-card bg-warning/10 p-4">
                <h3 class="font-bold mb-2">Pertimbangan Penting</h3>
                <ul class="text-sm space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-exclamation-circle text-warning mt-1 mr-2"></i>
                        <span>Biaya dan kompleksitas mengelola aset di berbagai chain</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-exclamation-circle text-warning mt-1 mr-2"></i>
                        <span>Perbedaan keamanan dan risiko tiap blockchain</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-exclamation-circle text-warning mt-1 mr-2"></i>
                        <span>Bridge blockchain dan risiko interoperabilitas</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-exclamation-circle text-warning mt-1 mr-2"></i>
                        <span>Tingkat kematangan ekosistem yang berbeda</span>
                    </li>
                </ul>
            </div>

            <div class="clay-card bg-info/10 p-4">
                <h3 class="font-bold mb-2">Pendekatan Investasi</h3>
                <div class="space-y-3 text-sm">
                    <div>
                        <p class="font-medium text-primary">Ethereum & Layer-2:</p>
                        <p>Fokus pada ekosistem Ethereum untuk aplikasi DeFi utama dan Layer-2 untuk skalabilitas.</p>
                    </div>
                    <div>
                        <p class="font-medium text-secondary">Blockchain Alternatif:</p>
                        <p>Alokasikan sebagian ke blockchain alternatif yang menawarkan fitur dan aplikasi unik.</p>
                    </div>
                    <div>
                        <p class="font-medium text-info">Cross-Chain Projects:</p>
                        <p>Investasi pada proyek yang memfasilitasi interoperabilitas antar blockchain.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

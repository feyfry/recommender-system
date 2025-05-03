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

    <!-- Chain Selection -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-filter mr-2 text-primary"></i>
            Pilih Blockchain
        </h2>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
            @forelse($chains ?? [] as $chain)
                <a href="{{ route('panel.recommendations.chains', ['chain' => strtolower($chain)]) }}"
                   class="clay-card {{ strtolower($chain) == $selectedChain ? 'bg-primary/20 border-2 border-primary' : 'bg-primary/5' }} p-3 text-center hover:translate-y-[-2px] transition-transform">
                    <div class="font-bold mb-1">{{ $chain }}</div>
                </a>
            @empty
                <div class="col-span-full clay-card p-4 text-center">
                    <p>Tidak ada blockchain yang tersedia.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Chain Projects -->
    <div class="clay-card p-6 mb-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold flex items-center">
                <i class="fas fa-list mr-2 text-primary"></i>
                Proyek {{ ucfirst($selectedChain ?? 'Blockchain') }}
            </h2>

            <div class="flex space-x-2">
                <button type="button" class="clay-button py-1.5 px-3 text-sm">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @forelse($chainRecommendations ?? [] as $recommendation)
            <div class="clay-card p-4 hover:translate-y-[-5px] transition-transform">
                <div class="font-bold text-lg mb-2">{{ $recommendation['name'] ?? 'Unknown Project' }} ({{ $recommendation['symbol'] ?? 'N/A' }})</div>
                <div class="text-sm mb-2">
                    {{ '$'.number_format($recommendation['current_price'] ?? 0, 2) }}
                    <span class="{{ ($recommendation['price_change_percentage_24h'] ?? 0) > 0 ? 'text-success' : 'text-danger' }}">
                        {{ ($recommendation['price_change_percentage_24h'] ?? 0) > 0 ? '+' : '' }}
                        {{ number_format($recommendation['price_change_percentage_24h'] ?? 0, 2) }}%
                    </span>
                </div>
                <div class="clay-badge clay-badge-primary mb-3">
                    {{ $recommendation['primary_category'] ?? 'General' }}
                </div>
                <p class="text-sm mb-3 line-clamp-2">
                    {{ $recommendation['description'] ?? 'Tidak ada deskripsi' }}
                </p>
                <div class="flex justify-between items-center">
                    <div class="text-xs font-medium">Score: <span class="text-primary">
                        {{ number_format($recommendation['recommendation_score'] ?? 0, 2) }}
                    </span></div>
                    <div class="flex space-x-2">
                        <a href="{{ route('panel.recommendations.project', $recommendation['id'] ?? 'unknown') }}" class="clay-badge clay-badge-primary py-1 px-2 text-xs">
                            <i class="fas fa-info-circle"></i>
                        </a>
                        <form method="POST" action="{{ route('panel.recommendations.add-favorite') }}" class="inline">
                            @csrf
                            <input type="hidden" name="project_id" value="{{ $recommendation['id'] ?? 'unknown' }}">
                            <button type="submit" class="clay-badge clay-badge-secondary py-1 px-2 text-xs">
                                <i class="fas fa-heart"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-span-full clay-card p-6 text-center">
                <p>Tidak ada proyek yang tersedia untuk blockchain ini.</p>
                <p class="text-sm mt-2 text-gray-500">Coba pilih blockchain lain atau kembali nanti.</p>
            </div>
            @endforelse
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

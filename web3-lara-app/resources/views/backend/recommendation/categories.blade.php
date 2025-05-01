@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <h1 class="text-3xl font-bold mb-4 flex items-center">
            <div class="bg-info/20 p-2 clay-badge mr-3">
                <i class="fas fa-tags text-info"></i>
            </div>
            Kategori
        </h1>
        <p class="text-lg">
            Telusuri rekomendasi proyek cryptocurrency berdasarkan kategori untuk menemukan investasi yang sesuai dengan minat Anda.
        </p>
    </div>

    <!-- Category Selection -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-filter mr-2 text-primary"></i>
            Pilih Kategori
        </h2>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
            @forelse($categories ?? [] as $category)
                <a href="{{ route('panel.recommendations.categories', ['category' => strtolower($category)]) }}"
                   class="clay-card {{ strtolower($category) == $selectedCategory ? 'bg-info/20 border-2 border-info' : 'bg-info/5' }} p-3 text-center hover:translate-y-[-2px] transition-transform">
                    <div class="font-bold mb-1">{{ $category }}</div>
                </a>
            @empty
                <div class="col-span-full clay-card p-4 text-center">
                    <p>Tidak ada kategori yang tersedia.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Category Description -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-info-circle mr-2 text-info"></i>
            Tentang Kategori: {{ ucfirst($selectedCategory ?? 'Pilih kategori') }}
        </h2>

        <div class="clay-card bg-info/10 p-4">
            @if($selectedCategory == 'defi')
                <p>
                    <strong>DeFi (Decentralized Finance)</strong> adalah ekosistem aplikasi keuangan yang dibangun di atas blockchain.
                    Proyek DeFi bertujuan menyediakan layanan keuangan seperti pinjaman, tabungan, asuransi, dan pertukaran tanpa
                    bergantung pada perantara tradisional seperti bank dan lembaga keuangan lainnya.
                </p>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Lending</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">DEX</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Yield Farming</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Stablecoins</div>
                </div>
            @elseif($selectedCategory == 'nft')
                <p>
                    <strong>NFT (Non-Fungible Token)</strong> adalah token digital unik yang mewakili kepemilikan aset digital atau fisik.
                    Proyek NFT mencakup platform untuk menciptakan, memperdagangkan, dan menggunakan token yang tidak dapat dipertukarkan,
                    seperti karya seni digital, koleksi, game item, dan aset virtual lainnya.
                </p>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Marketplace</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Digital Art</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Collectibles</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Virtual Land</div>
                </div>
            @elseif($selectedCategory == 'gaming')
                <p>
                    <strong>Gaming</strong> atau <strong>GameFi</strong> menggabungkan game dengan mekanisme finansial blockchain.
                    Proyek ini menawarkan game di mana pemain dapat memiliki, memperdagangkan, dan mendapatkan keuntungan dari aset dalam game.
                    Model play-to-earn memungkinkan pemain menghasilkan cryptocurrency melalui aktivitas dalam game.
                </p>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Play-to-Earn</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Metaverse</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">In-Game Assets</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Game Guilds</div>
                </div>
            @elseif($selectedCategory == 'layer1')
                <p>
                    <strong>Layer-1</strong> adalah blockchain dasar atau protokol utama seperti Bitcoin, Ethereum, dan Solana.
                    Proyek ini menyediakan infrastruktur dasar untuk aplikasi terdesentralisasi (dApps) dan memiliki
                    mekanisme konsensus sendiri serta keamanan jaringan.
                </p>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Smart Contracts</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Consensus Mechanisms</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Network Security</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Native Crypto</div>
                </div>
            @elseif($selectedCategory == 'layer2')
                <p>
                    <strong>Layer-2</strong> adalah solusi yang dibangun di atas blockchain Layer-1 untuk meningkatkan skalabilitas dan efisiensi.
                    Proyek ini fokus pada pengolahan transaksi off-chain, mengurangi biaya transaksi, dan meningkatkan
                    kapasitas pemrosesan transaksi tanpa mengorbankan keamanan layer-1.
                </p>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Rollups</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">State Channels</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Sidechains</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Plasma</div>
                </div>
            @else
                <p>
                    Pilih kategori dari daftar di atas untuk melihat deskripsi dan proyek terkait.
                    Kategori membantu Anda menemukan proyek cryptocurrency yang sesuai dengan minat dan strategi investasi Anda.
                </p>
            @endif
        </div>
    </div>

    <!-- Category Projects -->
    <div class="clay-card p-6 mb-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold flex items-center">
                <i class="fas fa-list mr-2 text-primary"></i>
                Proyek {{ ucfirst($selectedCategory ?? 'Kategori') }}
            </h2>

            <div class="flex space-x-2">
                <button type="button" class="clay-button py-1.5 px-3 text-sm">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @forelse($categoryRecommendations ?? [] as $recommendation)
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
                    {{ $recommendation->chain ?? $recommendation['chain'] ?? 'Multiple' }}
                </div>
                <p class="text-sm mb-3 line-clamp-2">
                    {{ $recommendation->description ?? $recommendation['description'] ?? 'Tidak ada deskripsi' }}
                </p>
                <div class="flex justify-between items-center">
                    <div class="text-xs font-medium">Score: <span class="text-info">
                        {{ number_format($recommendation->recommendation_score ?? $recommendation['recommendation_score'] ?? 0, 2) }}
                    </span></div>
                    <div class="flex space-x-2">
                        <a href="{{ route('panel.recommendations.project', $recommendation->id ?? $recommendation['id']) }}" class="clay-badge clay-badge-info py-1 px-2 text-xs">
                            <i class="fas fa-info-circle"></i>
                        </a>
                        <form method="POST" action="{{ route('panel.recommendations.add-favorite') }}" class="inline">
                            @csrf
                            <input type="hidden" name="project_id" value="{{ $recommendation->id ?? $recommendation['id'] }}">
                            <button type="submit" class="clay-badge clay-badge-secondary py-1 px-2 text-xs">
                                <i class="fas fa-heart"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-span-full clay-card p-6 text-center">
                <p>Tidak ada proyek yang tersedia untuk kategori ini.</p>
                <p class="text-sm mt-2 text-gray-500">Coba pilih kategori lain atau kembali nanti.</p>
            </div>
            @endforelse
        </div>
    </div>

    <!-- Investment Strategy -->
    <div class="clay-card p-6">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-chess mr-2 text-secondary"></i>
            Strategi Investasi Berbasis Kategori
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="clay-card bg-success/10 p-4">
                <h3 class="font-bold mb-2">Portofolio Terdiversifikasi</h3>
                <p class="text-sm mb-3">
                    Mengalokasikan investasi di berbagai kategori untuk mengurangi risiko dan memanfaatkan pertumbuhan di berbagai sektor.
                </p>
                <div class="clay-progress h-3 mb-2">
                    <div class="clay-progress-bar bg-primary" style="width: 25%"></div>
                    <div class="clay-progress-bar bg-secondary" style="width: 15%"></div>
                    <div class="clay-progress-bar bg-success" style="width: 20%"></div>
                    <div class="clay-progress-bar bg-warning" style="width: 15%"></div>
                    <div class="clay-progress-bar bg-info" style="width: 25%"></div>
                </div>
                <div class="text-xs text-gray-600">
                    <span class="inline-block mr-3">Layer-1: 25%</span>
                    <span class="inline-block mr-3">DeFi: 25%</span>
                    <span class="inline-block mr-3">Gaming: 20%</span>
                    <span class="inline-block mr-3">NFT: 15%</span>
                    <span class="inline-block">Layer-2: 15%</span>
                </div>
            </div>

            <div class="clay-card bg-warning/10 p-4">
                <h3 class="font-bold mb-2">Faktor yang Perlu Dipertimbangkan</h3>
                <ul class="text-sm space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-warning mt-1 mr-2"></i>
                        <span>Potensi pertumbuhan kategori</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-warning mt-1 mr-2"></i>
                        <span>Adopsi dan penggunaan di dunia nyata</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-warning mt-1 mr-2"></i>
                        <span>Dukungan komunitas dan pengembang</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-warning mt-1 mr-2"></i>
                        <span>Regulasi dan tantangan khusus kategori</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-warning mt-1 mr-2"></i>
                        <span>Tren dan arah perkembangan teknologi</span>
                    </li>
                </ul>
            </div>

            <div class="clay-card bg-info/10 p-4">
                <h3 class="font-bold mb-2">Pendekatan Sesuai Profil Risiko</h3>
                <div class="space-y-3 text-sm">
                    <div>
                        <p class="font-medium text-success">Risiko Rendah:</p>
                        <p>Fokus pada Layer-1 established, stablecoin, dan proyek DeFi dengan sejarah panjang.</p>
                    </div>
                    <div>
                        <p class="font-medium text-warning">Risiko Menengah:</p>
                        <p>Campuran Layer-1, DeFi, dan NFT dengan rekam jejak yang terbukti.</p>
                    </div>
                    <div>
                        <p class="font-medium text-danger">Risiko Tinggi:</p>
                        <p>Alokasi lebih besar untuk GameFi, NFT, dan proyek inovatif baru.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

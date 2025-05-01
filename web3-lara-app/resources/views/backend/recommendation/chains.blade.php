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

    <!-- Chain Description -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-info-circle mr-2 text-info"></i>
            Tentang Blockchain: {{ ucfirst($selectedChain ?? 'Pilih blockchain') }}
        </h2>

        <div class="clay-card bg-primary/10 p-4">
            @if($selectedChain == 'ethereum')
                <p>
                    <strong>Ethereum</strong> adalah blockchain terdesentralisasi dengan kemampuan smart contract yang menjadi
                    fondasi bagi sebagian besar aplikasi DeFi, NFT, dan dApps. Ethereum menggunakan token ETH sebagai cryptocurrency
                    utama dan sedang dalam proses transisi ke Proof of Stake melalui upgrade Ethereum 2.0.
                </p>
                <div class="grid grid-cols-2 gap-3 mt-3">
                    <div class="clay-card bg-success/10 p-2 text-sm">
                        <div class="font-medium">Biaya Gas</div>
                        <div>Sedang-Tinggi</div>
                    </div>
                    <div class="clay-card bg-success/10 p-2 text-sm">
                        <div class="font-medium">TPS</div>
                        <div>~15-30</div>
                    </div>
                    <div class="clay-card bg-success/10 p-2 text-sm">
                        <div class="font-medium">Konsensus</div>
                        <div>Proof of Stake</div>
                    </div>
                    <div class="clay-card bg-success/10 p-2 text-sm">
                        <div class="font-medium">Dominasi</div>
                        <div>Sangat Tinggi</div>
                    </div>
                </div>
            @elseif($selectedChain == 'binance-smart-chain' || $selectedChain == 'bsc')
                <p>
                    <strong>Binance Smart Chain (BSC)</strong> adalah blockchain yang dikembangkan oleh Binance dengan
                    fokus pada biaya transaksi rendah dan throughput tinggi. BSC kompatibel dengan Ethereum Virtual Machine (EVM)
                    dan menggunakan BNB sebagai token utama. BSC menggunakan mekanisme konsensus Proof of Staked Authority (PoSA).
                </p>
                <div class="grid grid-cols-2 gap-3 mt-3">
                    <div class="clay-card bg-success/10 p-2 text-sm">
                        <div class="font-medium">Biaya Gas</div>
                        <div>Sangat Rendah</div>
                    </div>
                    <div class="clay-card bg-success/10 p-2 text-sm">
                        <div class="font-medium">TPS</div>
                        <div>~60-100</div>
                    </div>
                    <div class="clay-card bg-success/10 p-2 text-sm">
                        <div class="font-medium">Konsensus</div>
                        <div>PoSA</div>
                    </div>
                    <div class="clay-card bg-success/10 p-2 text-sm">
                        <div class="font-medium">Dominasi</div>
                        <div>Tinggi</div>
                    </div>
                </div>
            @elseif($selectedChain == 'solana')
                <p>
                    <strong>Solana</strong> adalah blockchain high-performance yang dirancang untuk aplikasi terdesentralisasi
                    dan marketplace crypto. Solana mengklaim mampu menangani hingga 65.000 transaksi per detik dengan biaya transaksi
                    sangat rendah melalui mekanisme konsensus Proof of History (PoH) dan Proof of Stake (PoS).
                </p>
                <div class="grid grid-cols-2 gap-3 mt-3">
                    <div class="clay-card bg-success/10 p-2 text-sm">
                        <div class="font-medium">Biaya Gas</div>
                        <div>Sangat Rendah</div>
                    </div>
                    <div class="clay-card bg-success/10 p-2 text-sm">
                        <div class="font-medium">TPS</div>
                        <div>~2.000-65.000</div>
                    </div>
                    <div class="clay-card bg-success/10 p-2 text-sm">
                        <div class="font-medium">Konsensus</div>
                        <div>PoH & PoS</div>
                    </div>
                    <div class="clay-card bg-success/10 p-2 text-sm">
                        <div class="font-medium">Dominasi</div>
                        <div>Menengah</div>
                    </div>
                </div>
            @elseif($selectedChain == 'polygon')
                <p>
                    <strong>Polygon</strong> (sebelumnya Matic Network) adalah platform scaling untuk Ethereum
                    yang bertujuan mengatasi masalah skalabilitas dengan menyediakan transaksi yang lebih cepat dan lebih murah.
                    Polygon menggunakan kombinasi Plasma Framework dan Proof of Stake untuk mencapai konsensus.
                </p>
                <div class="grid grid-cols-2 gap-3 mt-3">
                    <div class="clay-card bg-success/10 p-2 text-sm">
                        <div class="font-medium">Biaya Gas</div>
                        <div>Sangat Rendah</div>
                    </div>
                    <div class="clay-card bg-success/10 p-2 text-sm">
                        <div class="font-medium">TPS</div>
                        <div>~7.000</div>
                    </div>
                    <div class="clay-card bg-success/10 p-2 text-sm">
                        <div class="font-medium">Konsensus</div>
                        <div>PoS</div>
                    </div>
                    <div class="clay-card bg-success/10 p-2 text-sm">
                        <div class="font-medium">Dominasi</div>
                        <div>Menengah</div>
                    </div>
                </div>
            @elseif($selectedChain == 'avalanche')
                <p>
                    <strong>Avalanche</strong> adalah platform blockchain yang menggunakan mekanisme konsensus inovatif
                    untuk mencapai throughput tinggi, konfirmasi cepat, dan skalabilitas. Avalanche terdiri dari tiga blockchain
                    berbeda (Exchange Chain, Platform Chain, dan Contract Chain) untuk fungsi yang berbeda.
                </p>
                <div class="grid grid-cols-2 gap-3 mt-3">
                    <div class="clay-card bg-success/10 p-2 text-sm">
                        <div class="font-medium">Biaya Gas</div>
                        <div>Rendah</div>
                    </div>
                    <div class="clay-card bg-success/10 p-2 text-sm">
                        <div class="font-medium">TPS</div>
                        <div>~4.500</div>
                    </div>
                    <div class="clay-card bg-success/10 p-2 text-sm">
                        <div class="font-medium">Konsensus</div>
                        <div>Avalanche</div>
                    </div>
                    <div class="clay-card bg-success/10 p-2 text-sm">
                        <div class="font-medium">Dominasi</div>
                        <div>Menengah</div>
                    </div>
                </div>
            @else
                <p>
                    Pilih blockchain dari daftar di atas untuk melihat deskripsi dan proyek terkait.
                    Setiap blockchain memiliki karakteristik unik yang mempengaruhi skalabilitas, kecepatan, biaya, dan
                    jenis aplikasi yang dapat dibangun di atasnya.
                </p>
            @endif
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
                <div class="font-bold text-lg mb-2">{{ $recommendation->name ?? $recommendation['name'] }} ({{ $recommendation->symbol ?? $recommendation['symbol'] }})</div>
                <div class="text-sm mb-2">
                    {{ $recommendation->formatted_price ?? '$'.number_format($recommendation->price_usd ?? $recommendation['price_usd'], 2) }}
                    <span class="{{ ($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0) > 0 ? 'text-success' : 'text-danger' }}">
                        {{ ($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0) > 0 ? '+' : '' }}
                        {{ number_format($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0, 2) }}%
                    </span>
                </div>
                <div class="clay-badge clay-badge-primary mb-3">
                    {{ $recommendation->primary_category ?? $recommendation['primary_category'] ?? 'General' }}
                </div>
                <p class="text-sm mb-3 line-clamp-2">
                    {{ $recommendation->description ?? $recommendation['description'] ?? 'Tidak ada deskripsi' }}
                </p>
                <div class="flex justify-between items-center">
                    <div class="text-xs font-medium">Score: <span class="text-primary">
                        {{ number_format($recommendation->recommendation_score ?? $recommendation['recommendation_score'] ?? 0, 2) }}
                    </span></div>
                    <div class="flex space-x-2">
                        <a href="{{ route('panel.recommendations.project', $recommendation->id ?? $recommendation['id']) }}" class="clay-badge clay-badge-primary py-1 px-2 text-xs">
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
                <p>Tidak ada proyek yang tersedia untuk blockchain ini.</p>
                <p class="text-sm mt-2 text-gray-500">Coba pilih blockchain lain atau kembali nanti.</p>
            </div>
            @endforelse
        </div>
    </div>

    <!-- Chain Comparison -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-chart-bar mr-2 text-secondary"></i>
            Perbandingan Blockchain Populer
        </h2>

        <div class="overflow-x-auto">
            <table class="clay-table min-w-full">
                <thead>
                    <tr>
                        <th class="py-3 px-4 text-left">Blockchain</th>
                        <th class="py-3 px-4 text-left">Transaksi/detik</th>
                        <th class="py-3 px-4 text-left">Biaya Transaksi</th>
                        <th class="py-3 px-4 text-left">Konsensus</th>
                        <th class="py-3 px-4 text-left">Smart Contract</th>
                        <th class="py-3 px-4 text-left">Ekosistem</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="py-3 px-4 font-medium">Ethereum</td>
                        <td class="py-3 px-4">15-30</td>
                        <td class="py-3 px-4">Tinggi</td>
                        <td class="py-3 px-4">PoS</td>
                        <td class="py-3 px-4">Solidity</td>
                        <td class="py-3 px-4">
                            <div class="clay-progress w-24 h-2">
                                <div class="clay-progress-bar bg-success" style="width: 95%"></div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-3 px-4 font-medium">Binance Smart Chain</td>
                        <td class="py-3 px-4">60-100</td>
                        <td class="py-3 px-4">Sangat Rendah</td>
                        <td class="py-3 px-4">PoSA</td>
                        <td class="py-3 px-4">Solidity</td>
                        <td class="py-3 px-4">
                            <div class="clay-progress w-24 h-2">
                                <div class="clay-progress-bar bg-success" style="width: 75%"></div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-3 px-4 font-medium">Solana</td>
                        <td class="py-3 px-4">2.000-65.000</td>
                        <td class="py-3 px-4">Sangat Rendah</td>
                        <td class="py-3 px-4">PoH & PoS</td>
                        <td class="py-3 px-4">Rust</td>
                        <td class="py-3 px-4">
                            <div class="clay-progress w-24 h-2">
                                <div class="clay-progress-bar bg-success" style="width: 65%"></div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-3 px-4 font-medium">Polygon</td>
                        <td class="py-3 px-4">~7.000</td>
                        <td class="py-3 px-4">Sangat Rendah</td>
                        <td class="py-3 px-4">PoS</td>
                        <td class="py-3 px-4">Solidity</td>
                        <td class="py-3 px-4">
                            <div class="clay-progress w-24 h-2">
                                <div class="clay-progress-bar bg-success" style="width: 60%"></div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-3 px-4 font-medium">Avalanche</td>
                        <td class="py-3 px-4">~4.500</td>
                        <td class="py-3 px-4">Rendah</td>
                        <td class="py-3 px-4">Avalanche</td>
                        <td class="py-3 px-4">Solidity</td>
                        <td class="py-3 px-4">
                            <div class="clay-progress w-24 h-2">
                                <div class="clay-progress-bar bg-success" style="width: 55%"></div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
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

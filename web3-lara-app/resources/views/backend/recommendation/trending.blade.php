@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <h1 class="text-3xl font-bold mb-4 flex items-center">
            <div class="bg-warning/20 p-2 clay-badge mr-3">
                <i class="fas fa-fire text-warning"></i>
            </div>
            Proyek Trending
        </h1>
        <p class="text-lg">
            Daftar proyek cryptocurrency yang saat ini sedang trending berdasarkan metrik popularitas, pertumbuhan, dan aktivitas pasar terbaru.
        </p>
    </div>

    <!-- Trending Info -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-info-circle mr-2 text-info"></i>
            Tentang Trending Score
        </h2>

        <div class="flex flex-col md:flex-row gap-6">
            <div class="md:w-1/2">
                <p class="mb-4">
                    <strong>Trending Score</strong> dihitung berdasarkan kombinasi beberapa faktor berikut:
                </p>
                <ul class="space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Perubahan harga (1h, 24h, 7d)</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Pertumbuhan volume perdagangan</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Peningkatan aktivitas sosial media</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Aktivitas developer</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Pertumbuhan jumlah pengguna/holder</span>
                    </li>
                </ul>
            </div>

            <div class="md:w-1/2">
                <div class="clay-card bg-warning/10 p-4">
                    <div class="font-bold mb-2 flex items-center">
                        <i class="fas fa-bolt mr-2 text-warning"></i>
                        Tips Penggunaan
                    </div>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-start">
                            <i class="fas fa-arrow-right text-warning mt-1 mr-2"></i>
                            <span>Proyek trending sering menunjukkan momentum jangka pendek yang kuat</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-arrow-right text-warning mt-1 mr-2"></i>
                            <span>Lakukan penelitian tambahan sebelum berinvestasi pada proyek trending</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-arrow-right text-warning mt-1 mr-2"></i>
                            <span>Gunakan indikator teknikal dan analisis fundamental sebagai pelengkap</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-arrow-right text-warning mt-1 mr-2"></i>
                            <span>Perhatikan bahwa tren dapat berubah dengan cepat di pasar crypto</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Trending Projects List -->
    <div class="clay-card p-6 mb-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold flex items-center">
                <i class="fas fa-list mr-2 text-primary"></i>
                Daftar Proyek Trending
            </h2>

            <div class="flex space-x-2">
                <button type="button" class="clay-button py-1.5 px-3 text-sm">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="clay-table min-w-full">
                <thead>
                    <tr>
                        <th class="py-3 px-4 text-left">Rank</th>
                        <th class="py-3 px-4 text-left">Proyek</th>
                        <th class="py-3 px-4 text-left">Harga</th>
                        <th class="py-3 px-4 text-left">24h %</th>
                        <th class="py-3 px-4 text-left">7d %</th>
                        <th class="py-3 px-4 text-left">Volume 24h</th>
                        <th class="py-3 px-4 text-left">Market Cap</th>
                        <th class="py-3 px-4 text-left">Trend Score</th>
                        <th class="py-3 px-4 text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($trendingProjects ?? [] as $index => $project)
                    <tr>
                        <td class="py-3 px-4 font-bold">{{ $index + 1 }}</td>
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                @if(isset($project['image']) && $project['image'])
                                    <img src="{{ $project['image'] }}" alt="{{ $project['symbol'] }}" class="w-8 h-8 rounded-full mr-3">
                                @endif
                                <div>
                                    <div class="font-medium">{{ $project['name'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $project['symbol'] }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4 font-medium">{{ $project['formatted_price'] ?? '$'.number_format($project['price_usd'], 2) }}</td>
                        <td class="py-3 px-4 {{ ($project['price_change_percentage_24h'] ?? 0) > 0 ? 'text-success' : 'text-danger' }}">
                            {{ ($project['price_change_percentage_24h'] ?? 0) > 0 ? '+' : '' }}{{ number_format($project['price_change_percentage_24h'] ?? 0, 2) }}%
                        </td>
                        <td class="py-3 px-4 {{ ($project['price_change_percentage_7d'] ?? 0) > 0 ? 'text-success' : 'text-danger' }}">
                            {{ ($project['price_change_percentage_7d'] ?? 0) > 0 ? '+' : '' }}{{ number_format($project['price_change_percentage_7d'] ?? 0, 2) }}%
                        </td>
                        <td class="py-3 px-4">${{ number_format($project['volume_24h'] ?? 0) }}</td>
                        <td class="py-3 px-4">${{ number_format($project['market_cap'] ?? 0) }}</td>
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                <span class="font-medium mr-2">{{ number_format($project['trend_score'] ?? 0, 1) }}</span>
                                <div class="clay-progress w-16 h-2">
                                    <div class="clay-progress-bar clay-progress-warning" style="width: {{ min(100, $project['trend_score'] ?? 0) }}%"></div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex space-x-2">
                                <a href="{{ route('panel.recommendations.project', $project['id']) }}" class="clay-badge clay-badge-info py-1 px-2 text-xs">
                                    <i class="fas fa-info-circle"></i> Detail
                                </a>
                                <form method="POST" action="{{ route('panel.recommendations.add-favorite') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="project_id" value="{{ $project['id'] }}">
                                    <button type="submit" class="clay-badge clay-badge-secondary py-1 px-2 text-xs">
                                        <i class="fas fa-heart"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-6 px-4 text-center">
                            <p class="text-gray-500">Tidak ada data proyek trending</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Trending Insights -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="clay-card p-6">
            <h2 class="text-lg font-bold mb-4 flex items-center">
                <i class="fas fa-chart-bar mr-2 text-secondary"></i>
                Kategori Trending
            </h2>

            <div class="space-y-4">
                <div class="clay-card bg-secondary/5 p-3">
                    <div class="flex justify-between mb-1">
                        <span class="font-medium">DeFi</span>
                        <span class="clay-badge clay-badge-secondary py-0.5 px-2">12 proyek</span>
                    </div>
                    <div class="clay-progress">
                        <div class="clay-progress-bar clay-progress-secondary" style="width: 85%"></div>
                    </div>
                </div>
                <div class="clay-card bg-secondary/5 p-3">
                    <div class="flex justify-between mb-1">
                        <span class="font-medium">GameFi</span>
                        <span class="clay-badge clay-badge-secondary py-0.5 px-2">8 proyek</span>
                    </div>
                    <div class="clay-progress">
                        <div class="clay-progress-bar clay-progress-secondary" style="width: 65%"></div>
                    </div>
                </div>
                <div class="clay-card bg-secondary/5 p-3">
                    <div class="flex justify-between mb-1">
                        <span class="font-medium">NFT</span>
                        <span class="clay-badge clay-badge-secondary py-0.5 px-2">7 proyek</span>
                    </div>
                    <div class="clay-progress">
                        <div class="clay-progress-bar clay-progress-secondary" style="width: 60%"></div>
                    </div>
                </div>
                <div class="clay-card bg-secondary/5 p-3">
                    <div class="flex justify-between mb-1">
                        <span class="font-medium">Layer-2</span>
                        <span class="clay-badge clay-badge-secondary py-0.5 px-2">5 proyek</span>
                    </div>
                    <div class="clay-progress">
                        <div class="clay-progress-bar clay-progress-secondary" style="width: 45%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="clay-card p-6">
            <h2 class="text-lg font-bold mb-4 flex items-center">
                <i class="fas fa-link mr-2 text-info"></i>
                Blockchain Trending
            </h2>

            <div class="space-y-4">
                <div class="clay-card bg-info/5 p-3">
                    <div class="flex justify-between mb-1">
                        <span class="font-medium">Ethereum</span>
                        <span class="clay-badge clay-badge-info py-0.5 px-2">15 proyek</span>
                    </div>
                    <div class="clay-progress">
                        <div class="clay-progress-bar clay-progress-info" style="width: 90%"></div>
                    </div>
                </div>
                <div class="clay-card bg-info/5 p-3">
                    <div class="flex justify-between mb-1">
                        <span class="font-medium">Solana</span>
                        <span class="clay-badge clay-badge-info py-0.5 px-2">9 proyek</span>
                    </div>
                    <div class="clay-progress">
                        <div class="clay-progress-bar clay-progress-info" style="width: 75%"></div>
                    </div>
                </div>
                <div class="clay-card bg-info/5 p-3">
                    <div class="flex justify-between mb-1">
                        <span class="font-medium">Binance Smart Chain</span>
                        <span class="clay-badge clay-badge-info py-0.5 px-2">7 proyek</span>
                    </div>
                    <div class="clay-progress">
                        <div class="clay-progress-bar clay-progress-info" style="width: 55%"></div>
                    </div>
                </div>
                <div class="clay-card bg-info/5 p-3">
                    <div class="flex justify-between mb-1">
                        <span class="font-medium">Polygon</span>
                        <span class="clay-badge clay-badge-info py-0.5 px-2">5 proyek</span>
                    </div>
                    <div class="clay-progress">
                        <div class="clay-progress-bar clay-progress-info" style="width: 40%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

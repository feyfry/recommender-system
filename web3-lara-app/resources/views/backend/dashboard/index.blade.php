@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Welcome Banner -->
    <div class="clay-card p-6 mb-8">
        <div class="flex flex-col md:flex-row items-center">
            <div class="md:w-2/3">
                <h1 class="text-3xl md:text-4xl font-bold mb-4">
                    <span class="clay-badge clay-badge-warning p-1 inline-block">Selamat Datang</span>
                    <span class="block mt-2">{{ Auth::user()->profile ? Auth::user()->profile->username : Auth::user()->wallet_address }}</span>
                </h1>
                <p class="text-lg">
                    Temukan rekomendasi cryptocurrency terbaik berdasarkan popularitas dan tren investasi.
                </p>
            </div>
            <div class="md:w-1/3 mt-6 md:mt-0 flex justify-center">
                <div class="clay-card bg-secondary/10 p-4">
                    <i class="fas fa-shield-alt text-6xl text-secondary"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- User Info Card -->
        <div class="clay-card p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <div class="bg-info/20 p-2 clay-badge mr-2">
                    <i class="fas fa-user text-info"></i>
                </div>
                Info Akun
            </h2>

            <div class="space-y-3">
                <div>
                    <label class="text-sm text-gray-600">Wallet Address:</label>
                    <p class="font-mono text-sm break-all">{{ Auth::user()->wallet_address }}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-600">Login Terakhir:</label>
                    <p>{{ Auth::user()->last_login ? Auth::user()->last_login->translatedFormat('d F Y H:i:s') : 'Pertama kali login' }}</p>
                </div>

                <div class="pt-2">
                    <a href="{{ route('panel.profile.edit') }}" class="clay-button clay-button-info py-1.5 px-3 inline-block font-medium">
                        <i class="fas fa-edit mr-1"></i> Edit Profil
                    </a>
                </div>
            </div>
        </div>

        <!-- Preferences Card -->
        <div class="clay-card p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <div class="bg-secondary/20 p-2 clay-badge mr-2">
                    <i class="fas fa-sliders-h text-secondary"></i>
                </div>
                Preferensi Investasi
            </h2>

            <div class="space-y-3">
                <div>
                    <label class="text-sm text-gray-600">Toleransi Risiko:</label>
                    <p class="font-medium">
                        @if(Auth::user()->risk_tolerance == 'low')
                            <span class="clay-badge clay-badge-success">Rendah</span>
                        @elseif(Auth::user()->risk_tolerance == 'medium')
                            <span class="clay-badge clay-badge-warning">Sedang</span>
                        @elseif(Auth::user()->risk_tolerance == 'high')
                            <span class="clay-badge clay-badge-danger">Tinggi</span>
                        @else
                            <span class="text-gray-400">Belum diatur</span>
                        @endif
                    </p>
                </div>
                <div>
                    <label class="text-sm text-gray-600">Gaya Investasi:</label>
                    <p class="font-medium">
                        @if(Auth::user()->investment_style == 'conservative')
                            <span class="clay-badge clay-badge-info">Konservatif</span>
                        @elseif(Auth::user()->investment_style == 'balanced')
                            <span class="clay-badge clay-badge-warning">Seimbang</span>
                        @elseif(Auth::user()->investment_style == 'aggressive')
                            <span class="clay-badge clay-badge-danger">Agresif</span>
                        @else
                            <span class="text-gray-400">Belum diatur</span>
                        @endif
                    </p>
                </div>

                <div class="pt-4">
                    <p class="text-sm">
                        @if(!Auth::user()->risk_tolerance || !Auth::user()->investment_style)
                            Lengkapi profil Anda untuk mendapatkan rekomendasi yang lebih personal!
                        @else
                            Preferensi Anda digunakan untuk menghasilkan rekomendasi personal.
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="clay-card p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <div class="bg-success/20 p-2 clay-badge mr-2">
                    <i class="fas fa-bolt text-success"></i>
                </div>
                Aksi Cepat
            </h2>

            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('panel.recommendations.trending') }}" class="clay-card bg-warning/20 p-3 text-center">
                    <div class="font-bold"><i class="fas fa-chart-line mb-1"></i></div>
                    <div class="text-xs mt-1">Proyek Trending</div>
                </a>

                <a href="{{ route('panel.recommendations.personal') }}" class="clay-card bg-secondary/20 p-3 text-center">
                    <div class="font-bold"><i class="fas fa-star mb-1"></i></div>
                    <div class="text-xs mt-1">Rekomendasi Personal</div>
                </a>

                <a href="{{ route('panel.recommendations') }}" class="clay-card bg-info/20 p-3 text-center">
                    <div class="font-bold"><i class="fas fa-chart-bar mb-1"></i></div>
                    <div class="text-xs mt-1">Analisis Teknikal</div>
                </a>

                <a href="{{ route('panel.portfolio') }}" class="clay-card bg-success/20 p-3 text-center">
                    <div class="font-bold"><i class="fas fa-wallet mb-1"></i></div>
                    <div class="text-xs mt-1">Portfolio</div>
                </a>
            </div>
        </div>
    </div>

    <!-- Admin Panel Access (jika pengguna adalah admin) -->
    @if(Auth::user()->isAdmin())
        <div class="clay-card p-6 mb-8">
            <h2 class="text-2xl font-bold mb-6 flex items-center">
                <div class="bg-secondary/20 p-2 clay-badge mr-3">
                    <i class="fas fa-shield-alt text-secondary"></i>
                </div>
                Panel Admin
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <a href="{{ route('admin.dashboard') }}" class="clay-card bg-secondary/10 p-4 text-center hover:translate-y-[-5px] transition-transform">
                    <div class="font-bold"><i class="fas fa-tachometer-alt mb-2 text-xl"></i></div>
                    <div>Dashboard Admin</div>
                    <div class="text-xs mt-1">Panel utama admin</div>
                </a>

                <a href="{{ route('admin.users') }}" class="clay-card bg-warning/10 p-4 text-center hover:translate-y-[-5px] transition-transform">
                    <div class="font-bold"><i class="fas fa-users mb-2 text-xl"></i></div>
                    <div>Manajemen Pengguna</div>
                    <div class="text-xs mt-1">Kelola pengguna sistem</div>
                </a>

                <a href="{{ route('admin.projects') }}" class="clay-card bg-success/10 p-4 text-center hover:translate-y-[-5px] transition-transform">
                    <div class="font-bold"><i class="fas fa-project-diagram mb-2 text-xl"></i></div>
                    <div>Manajemen Proyek</div>
                    <div class="text-xs mt-1">Kelola data proyek</div>
                </a>

                <a href="{{ route('admin.data-sync') }}" class="clay-card bg-info/10 p-4 text-center hover:translate-y-[-5px] transition-transform">
                    <div class="font-bold"><i class="fas fa-sync mb-2 text-xl"></i></div>
                    <div>Sinkronisasi Data</div>
                    <div class="text-xs mt-1">Update dan sinkronisasi</div>
                </a>
            </div>
        </div>
    @endif

    <!-- Trending Projects Section -->
    <div class="clay-card p-6 mb-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold flex items-center">
                <div class="bg-warning/20 p-2 clay-badge mr-3">
                    <i class="fas fa-fire text-warning"></i>
                </div>
                Trending Projects
            </h2>
            <a href="{{ route('panel.recommendations.trending') }}" class="clay-badge clay-badge-warning py-1 px-2 inline-block">
                Lihat Semua <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="clay-table min-w-full">
                <thead>
                    <tr>
                        <th class="py-2 px-4 text-left">#</th>
                        <th class="py-2 px-4 text-left">Project</th>
                        <th class="py-2 px-4 text-left">Harga</th>
                        <th class="py-2 px-4 text-left">24h %</th>
                        <th class="py-2 px-4 text-left">Kategori</th>
                        <th class="py-2 px-4 text-left">Aksi</th>
                    </tr>
                </thead>
                <!-- filepath: c:\laragon\www\web3-recommendation-system\web3-lara-app\resources\views\backend\dashboard\index.blade.php -->
                <tbody>
                    @forelse($trendingProjects ?? [] as $index => $project)
                    <tr>
                        <td class="py-2 px-4">{{ $index + 1 }}</td>
                        <td class="py-2 px-4 font-medium">
                            <div class="flex items-center">
                                @if(isset($project['image']) && $project['image'])
                                    <img src="{{ $project['image'] }}" alt="{{ $project['symbol'] }}" class="w-6 h-6 mr-2 rounded-full">
                                @endif
                                {{ $project['name'] }} ({{ $project['symbol'] }})
                            </div>
                        </td>
                        <td class="py-2 px-4">{{ $project['formatted_price'] ?? '$'.number_format($project['price_usd'], 2) }}</td>
                        <td class="py-2 px-4 {{ ($project['price_change_percentage_24h'] ?? 0) > 0 ? 'text-clay-success' : 'text-clay-danger' }}">
                            {{ ($project['price_change_percentage_24h'] ?? 0) > 0 ? '+' : '' }}{{ number_format($project['price_change_percentage_24h'] ?? 0, 2) }}%
                        </td>
                        <td class="py-2 px-4">{{ $project['primary_category'] ?? 'N/A' }}</td>
                        <td class="py-2 px-4">
                            <a href="{{ route('panel.recommendations.project', $project['id']) }}" class="clay-button clay-button-info py-1 px-2 text-xs">
                                <i class="fas fa-info-circle mr-1"></i> Detail
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-4 px-4 text-center">Tidak ada data proyek trending</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recommendations Section -->
    <div class="clay-card p-6 mb-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold flex items-center">
                <div class="bg-secondary/20 p-2 clay-badge mr-3">
                    <i class="fas fa-star text-secondary"></i>
                </div>
                Rekomendasi Untuk Anda
            </h2>
            <a href="{{ route('panel.recommendations.personal') }}" class="clay-badge clay-badge-secondary py-1 px-2 inline-block">
                Lihat Semua <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @forelse($personalRecommendations ?? [] as $recommendation)
            <div class="clay-card p-4 hover:translate-y-[-5px] transition-transform">
                <div class="font-bold text-lg mb-2">{{ $recommendation->name ?? $recommendation['name'] }} ({{ $recommendation->symbol ?? $recommendation['symbol'] }})</div>
                <div class="text-sm mb-2">
                    {{ $recommendation->formatted_price ?? '$'.number_format($recommendation->price_usd ?? $recommendation['price_usd'], 2) }}
                    <span class="{{ ($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0) > 0 ? 'text-clay-success' : 'text-clay-danger' }}">
                        {{ ($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0) > 0 ? '+' : '' }}
                        {{ number_format($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0, 2) }}%
                    </span>
                </div>
                <div class="clay-badge mb-3">
                    {{ $recommendation->primary_category ?? $recommendation['primary_category'] ?? 'Umum' }}
                </div>
                <p class="text-sm mb-3 line-clamp-2">
                    {{ $recommendation->description ?? $recommendation['description'] ?? 'Tidak ada deskripsi' }}
                </p>
                <div class="flex justify-between items-center">
                    <div class="text-xs font-medium">Score: <span class="text-clay-primary">
                        {{ number_format($recommendation->recommendation_score ?? $recommendation['recommendation_score'] ?? 0, 2) }}
                    </span></div>
                    <a href="{{ route('panel.recommendations.project', $recommendation->id ?? $recommendation['id']) }}" class="clay-button clay-button-info py-1 px-2 text-xs">
                        <i class="fas fa-info-circle mr-1"></i> Detail
                    </a>
                </div>
            </div>
            @empty
            <div class="col-span-full text-center py-8">
                <p>Tidak ada rekomendasi personal yang tersedia saat ini.</p>
                <p class="text-sm mt-2">Mulai berinteraksi dengan proyek untuk mendapatkan rekomendasi yang lebih baik.</p>
            </div>
            @endforelse
        </div>
    </div>

    <!-- Portfolio & Quick Stats Section -->
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 mb-8">
        <!-- Portfolio Summary -->
        <div class="clay-card p-6 lg:col-span-3">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold flex items-center">
                    <div class="bg-success/20 p-2 clay-badge mr-3">
                        <i class="fas fa-wallet text-success"></i>
                    </div>
                    Portfolio
                </h2>
                <a href="{{ route('panel.portfolio') }}" class="clay-badge clay-badge-success py-1 px-2 inline-block">
                    Lihat Detail <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>

            @if(isset($portfolioSummary) && $portfolioSummary['total_value'] > 0)
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="clay-card bg-success/10 p-3">
                        <div class="text-sm text-gray-600">Total Nilai</div>
                        <div class="text-xl font-bold">${{ number_format($portfolioSummary['total_value'], 2) }}</div>
                    </div>
                    <div class="clay-card bg-{{ $portfolioSummary['profit_loss'] >= 0 ? 'success' : 'danger' }}/10 p-3">
                        <div class="text-sm text-gray-600">Profit/Loss</div>
                        <div class="text-xl font-bold {{ $portfolioSummary['profit_loss'] >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $portfolioSummary['profit_loss'] >= 0 ? '+' : '' }}${{ number_format($portfolioSummary['profit_loss'], 2) }}
                            ({{ number_format($portfolioSummary['profit_loss_percentage'], 2) }}%)
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h3 class="font-medium mb-2">Aset Teratas</h3>
                    <div class="space-y-2">
                        @foreach($portfolioSummary['top_assets'] ?? [] as $asset)
                        <div class="clay-card p-2 flex justify-between items-center">
                            <div class="flex items-center">
                                @if($asset['image'])
                                    <img src="{{ $asset['image'] }}" class="w-6 h-6 mr-2 rounded-full" alt="{{ $asset['symbol'] }}">
                                @endif
                                <span>{{ $asset['name'] }} ({{ $asset['symbol'] }})</span>
                            </div>
                            <div>${{ number_format($asset['value'], 2) }}</div>
                        </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="text-center py-8">
                    <p class="text-lg mb-4">Anda belum memiliki portfolio.</p>
                    <a href="{{ route('panel.portfolio') }}" class="clay-button clay-button-success">
                        <i class="fas fa-plus mr-1"></i> Tambahkan Aset
                    </a>
                </div>
            @endif
        </div>

        <!-- Quick Analysis -->
        <div class="clay-card p-6 lg:col-span-2">
            <h2 class="text-2xl font-bold mb-6 flex items-center">
                <div class="bg-info/20 p-2 clay-badge mr-3">
                    <i class="fas fa-chart-pie text-info"></i>
                </div>
                Analisis Cepat
            </h2>

            <div class="space-y-4">
                <div class="clay-card bg-warning/10 p-3">
                    <div class="font-bold mb-2">Market Overview</div>
                    <div class="flex justify-between">
                        <span>Fear & Greed Index:</span>
                        <span class="font-medium">65 (Greed)</span>
                    </div>
                    <div class="flex justify-between">
                        <span>BTC Dominance:</span>
                        <span class="font-medium">52.3%</span>
                    </div>
                </div>

                <div class="clay-card bg-secondary/10 p-3">
                    <div class="font-bold mb-2">Trending Categories</div>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <span class="clay-badge clay-badge-primary">DeFi</span>
                        <span class="clay-badge clay-badge-secondary">GameFi</span>
                        <span class="clay-badge clay-badge-info">Layer-2</span>
                        <span class="clay-badge clay-badge-success">NFT</span>
                        <span class="clay-badge clay-badge-warning">Meme</span>
                    </div>
                </div>

                <div class="clay-card bg-info/10 p-3">
                    <div class="font-bold mb-2">Top Gainers 24h</div>
                    <div class="space-y-1 mt-1">
                        <div class="flex justify-between text-sm">
                            <span>PEPE</span>
                            <span class="text-success">+14.5%</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span>SOL</span>
                            <span class="text-success">+8.2%</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span>SHIB</span>
                            <span class="text-success">+6.7%</span>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-6">
                    <a href="{{ route('panel.recommendations') }}" class="clay-button clay-button-info">
                        <i class="fas fa-chart-line mr-1"></i> Detail Analisis
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="clay-card p-6 mb-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold flex items-center">
                <div class="bg-warning/20 p-2 clay-badge mr-3">
                    <i class="fas fa-history text-warning"></i>
                </div>
                Aktivitas Terbaru
            </h2>
        </div>

        <div class="space-y-4">
            @forelse($recentInteractions ?? [] as $interaction)
                <div class="clay-card p-3 flex items-center">
                    <div class="mr-4">
                        @if($interaction->interaction_type == 'view')
                            <div class="clay-badge clay-badge-info p-2 rounded-full">
                                <i class="fas fa-eye"></i>
                            </div>
                        @elseif($interaction->interaction_type == 'favorite')
                            <div class="clay-badge clay-badge-secondary p-2 rounded-full">
                                <i class="fas fa-heart"></i>
                            </div>
                        @elseif($interaction->interaction_type == 'portfolio_add')
                            <div class="clay-badge clay-badge-success p-2 rounded-full">
                                <i class="fas fa-folder-plus"></i>
                            </div>
                        @else
                            <div class="clay-badge clay-badge-warning p-2 rounded-full">
                                <i class="fas fa-info-circle"></i>
                            </div>
                        @endif
                    </div>
                    <div class="flex-grow">
                        <div class="font-medium">
                            {{-- Tipe interaksi dengan bahasa yang mudah dibaca --}}
                            @if($interaction->interaction_type == 'view')
                                Melihat detail
                            @elseif($interaction->interaction_type == 'favorite')
                                Menambahkan ke favorit
                            @elseif($interaction->interaction_type == 'portfolio_add')
                                Menambahkan ke portfolio
                            @elseif($interaction->interaction_type == 'research')
                                Meriset
                            @elseif($interaction->interaction_type == 'click')
                                Mengklik
                            @else
                                Berinteraksi dengan
                            @endif
                            <span class="font-bold">{{ $interaction->project->name }} ({{ $interaction->project->symbol }})</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ $interaction->created_at->diffForHumans() }}
                        </div>
                    </div>
                    <div>
                        <a href="{{ route('panel.recommendations.project', $interaction->project_id) }}" class="clay-badge clay-badge-warning py-1 px-2 text-xs">
                            Detail
                        </a>
                    </div>
                </div>
            @empty
                <div class="text-center py-6">
                    <p>Belum ada aktivitas yang tercatat.</p>
                    <p class="text-sm mt-2">Mulai berinteraksi dengan proyek untuk melihat riwayat aktivitas Anda.</p>
                </div>
            @endforelse

            @if(isset($recentInteractions) && count($recentInteractions) > 0)
                <div class="text-center mt-4">
                    <a href="#" class="clay-button clay-button-warning">
                        <i class="fas fa-history mr-1"></i> Lihat Semua Aktivitas
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

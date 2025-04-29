@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4">
    <!-- Welcome Banner -->
    <div class="neo-brutalism bg-white p-8 mb-8">
        <div class="flex flex-col md:flex-row items-center">
            <div class="md:w-2/3">
                <h1 class="text-3xl md:text-4xl font-bold mb-4">
                    <span class="bg-brutal-yellow rotate-[-1deg] neo-brutalism-sm px-2 py-1 inline-block">Selamat Datang</span>
                    <span class="block mt-2">{{ Auth::user()->profile ? Auth::user()->profile->username : Auth::user()->wallet_address }}</span>
                </h1>
                <p class="text-lg">
                    Temukan rekomendasi cryptocurrency terbaik berdasarkan popularitas dan tren investasi.
                </p>
            </div>
            <div class="md:w-1/3 mt-6 md:mt-0 flex justify-center">
                <div class="neo-brutalism-sm bg-brutal-pink p-4 rotate-[-3deg] transform hover:rotate-[1deg] transition duration-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- User Info Card -->
        <div class="neo-brutalism bg-white p-6 rotate-[-1deg]">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <div class="bg-brutal-blue p-1.5 neo-brutalism-sm mr-2 rotate-[2deg]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                Info Akun
            </h2>

            <div class="space-y-3">
                <div>
                    <label class="text-sm text-gray-600">User ID:</label>
                    <p class="font-medium">{{ Auth::user()->user_id }}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-600">Wallet Address:</label>
                    <p class="font-mono text-sm break-all">{{ Auth::user()->wallet_address }}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-600">Login Terakhir:</label>
                    <p>{{ Auth::user()->last_login ? Auth::user()->last_login->translatedFormat('d F Y H:i:s') : 'Pertama kali login' }}</p>
                </div>

                <div class="pt-2">
                    <a href="{{ route('panel.profile.edit') }}" class="neo-brutalism-sm bg-brutal-yellow py-1.5 px-3 inline-block font-medium hover:rotate-[1deg] transform transition">
                        Edit Profil
                    </a>
                </div>
            </div>
        </div>

        <!-- Preferences Card -->
        <div class="neo-brutalism bg-white p-6 rotate-[1deg]">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <div class="bg-brutal-pink p-1.5 neo-brutalism-sm mr-2 rotate-[-2deg]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                </div>
                Preferensi Investasi
            </h2>

            <div class="space-y-3">
                <div>
                    <label class="text-sm text-gray-600">Toleransi Risiko:</label>
                    <p class="font-medium">
                        @if(Auth::user()->risk_tolerance == 'low')
                            <span class="neo-brutalism-sm bg-brutal-green/20 px-2 py-0.5 inline-block">Rendah</span>
                        @elseif(Auth::user()->risk_tolerance == 'medium')
                            <span class="neo-brutalism-sm bg-brutal-yellow/20 px-2 py-0.5 inline-block">Sedang</span>
                        @elseif(Auth::user()->risk_tolerance == 'high')
                            <span class="neo-brutalism-sm bg-brutal-pink/20 px-2 py-0.5 inline-block">Tinggi</span>
                        @else
                            <span class="text-gray-400">Belum diatur</span>
                        @endif
                    </p>
                </div>
                <div>
                    <label class="text-sm text-gray-600">Gaya Investasi:</label>
                    <p class="font-medium">
                        @if(Auth::user()->investment_style == 'conservative')
                            <span class="neo-brutalism-sm bg-brutal-blue/20 px-2 py-0.5 inline-block">Konservatif</span>
                        @elseif(Auth::user()->investment_style == 'balanced')
                            <span class="neo-brutalism-sm bg-brutal-orange/20 px-2 py-0.5 inline-block">Seimbang</span>
                        @elseif(Auth::user()->investment_style == 'aggressive')
                            <span class="neo-brutalism-sm bg-brutal-pink/20 px-2 py-0.5 inline-block">Agresif</span>
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
        <div class="neo-brutalism bg-white p-6 rotate-[-0.5deg]">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <div class="bg-brutal-green p-1.5 neo-brutalism-sm mr-2 rotate-[2deg]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                Aksi Cepat
            </h2>

            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('panel.recommendations.trending') }}" class="neo-brutalism-sm bg-brutal-yellow p-3 text-center hover:rotate-[1deg] transform transition">
                    <div class="font-bold">Trending</div>
                    <div class="text-xs mt-1">Proyek terpopuler</div>
                </a>

                <a href="{{ route('panel.recommendations.personal') }}" class="neo-brutalism-sm bg-brutal-pink p-3 text-center hover:rotate-[-1deg] transform transition">
                    <div class="font-bold">Rekomendasi</div>
                    <div class="text-xs mt-1">Untuk Anda</div>
                </a>

                <a href="{{ route('panel.recommendations') }}" class="neo-brutalism-sm bg-brutal-blue p-3 text-center hover:rotate-[1deg] transform transition">
                    <div class="font-bold">Analisis</div>
                    <div class="text-xs mt-1">Teknikal & Sinyal</div>
                </a>

                <a href="{{ route('panel.portfolio') }}" class="neo-brutalism-sm bg-brutal-green p-3 text-center hover:rotate-[-1deg] transform transition">
                    <div class="font-bold">Portfolio</div>
                    <div class="text-xs mt-1">Kelola aset Anda</div>
                </a>
            </div>
        </div>
    </div>

    <!-- Trending Projects Section -->
    <div class="neo-brutalism bg-white p-6 rotate-[0.5deg] mb-8">
        <h2 class="text-2xl font-bold mb-6 flex items-center">
            <div class="bg-brutal-orange p-2 neo-brutalism-sm mr-3 rotate-[-2deg]">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
            </div>
            Trending Projects
            <a href="{{ route('panel.recommendations.trending') }}" class="ml-auto text-sm bg-brutal-orange/20 neo-brutalism-sm px-2 py-1 inline-block">
                Lihat Semua
            </a>
        </h2>

        <div class="overflow-x-auto">
            <table class="min-w-full neo-brutalism-sm">
                <thead class="bg-brutal-yellow">
                    <tr>
                        <th class="py-2 px-4 text-left">#</th>
                        <th class="py-2 px-4 text-left">Project</th>
                        <th class="py-2 px-4 text-left">Harga</th>
                        <th class="py-2 px-4 text-left">24h %</th>
                        <th class="py-2 px-4 text-left">Kategori</th>
                        <th class="py-2 px-4 text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($trendingProjects ?? [] as $index => $project)
                    <tr class="border-b border-black">
                        <td class="py-2 px-4">{{ $index + 1 }}</td>
                        <td class="py-2 px-4 font-medium">
                            <div class="flex items-center">
                                @if($project->image)
                                    <img src="{{ $project->image }}" alt="{{ $project->symbol }}" class="w-6 h-6 mr-2 rounded-full">
                                @endif
                                {{ $project->name }} ({{ $project->symbol }})
                            </div>
                        </td>
                        <td class="py-2 px-4">{{ $project->formatted_price ?? '$'.number_format($project->price_usd, 2) }}</td>
                        <td class="py-2 px-4 {{ $project->price_change_percentage_24h > 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $project->price_change_percentage_24h > 0 ? '+' : '' }}{{ number_format($project->price_change_percentage_24h, 2) }}%
                        </td>
                        <td class="py-2 px-4">{{ $project->primary_category }}</td>
                        <td class="py-2 px-4">
                            <a href="{{ route('panel.recommendations.project', $project->id) }}" class="neo-brutalism-sm bg-brutal-blue/20 px-2 py-1 text-xs">
                                Detail
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr class="border-b border-black">
                        <td colspan="6" class="py-2 px-4 text-center">Tidak ada data proyek trending</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recommendations Section -->
    <div class="neo-brutalism bg-white p-6 rotate-[-0.5deg] mb-8">
        <h2 class="text-2xl font-bold mb-6 flex items-center">
            <div class="bg-brutal-pink p-2 neo-brutalism-sm mr-3 rotate-[2deg]">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            Rekomendasi Untuk Anda
            <a href="{{ route('panel.recommendations.personal') }}" class="ml-auto text-sm bg-brutal-pink/20 neo-brutalism-sm px-2 py-1 inline-block">
                Lihat Semua
            </a>
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @forelse($personalRecommendations ?? [] as $recommendation)
            <div class="neo-brutalism-sm bg-white p-4 rotate-{{ rand(-1, 1) }}deg hover:rotate-0 transition duration-300">
                <div class="font-bold text-lg mb-2">{{ $recommendation->name ?? $recommendation['name'] }} ({{ $recommendation->symbol ?? $recommendation['symbol'] }})</div>
                <div class="text-sm mb-2">
                    {{ $recommendation->formatted_price ?? '$'.number_format($recommendation->price_usd ?? $recommendation['price_usd'], 2) }}
                    <span class="{{ ($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0) > 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ ($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0) > 0 ? '+' : '' }}
                        {{ number_format($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0, 2) }}%
                    </span>
                </div>
                <div class="bg-brutal-blue/10 px-2 py-0.5 text-xs inline-block mb-3">
                    {{ $recommendation->primary_category ?? $recommendation['primary_category'] ?? 'Umum' }}
                </div>
                <p class="text-sm mb-3 line-clamp-2">
                    {{ $recommendation->description ?? $recommendation['description'] ?? 'Tidak ada deskripsi' }}
                </p>
                <div class="flex justify-between items-center">
                    <div class="text-xs font-medium">Score: <span class="text-brutal-blue">
                        {{ number_format($recommendation->recommendation_score ?? $recommendation['recommendation_score'] ?? 0, 2) }}
                    </span></div>
                    <a href="{{ route('panel.recommendations.project', $recommendation->id ?? $recommendation['id']) }}" class="text-xs bg-brutal-pink/20 neo-brutalism-sm px-2 py-1">
                        Detail
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
        <div class="neo-brutalism bg-white p-6 rotate-[1deg] lg:col-span-3">
            <h2 class="text-2xl font-bold mb-6 flex items-center">
                <div class="bg-brutal-green p-2 neo-brutalism-sm mr-3 rotate-[-2deg]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
                Portfolio
                <a href="{{ route('panel.portfolio') }}" class="ml-auto text-sm bg-brutal-green/20 neo-brutalism-sm px-2 py-1 inline-block">
                    Lihat Detail
                </a>
            </h2>

            @if(isset($portfolioSummary) && $portfolioSummary['total_value'] > 0)
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="neo-brutalism-sm bg-brutal-green/10 p-3">
                        <div class="text-sm text-gray-600">Total Nilai</div>
                        <div class="text-xl font-bold">${{ number_format($portfolioSummary['total_value'], 2) }}</div>
                    </div>
                    <div class="neo-brutalism-sm bg-{{ $portfolioSummary['profit_loss'] >= 0 ? 'brutal-green' : 'brutal-pink' }}/10 p-3">
                        <div class="text-sm text-gray-600">Profit/Loss</div>
                        <div class="text-xl font-bold {{ $portfolioSummary['profit_loss'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $portfolioSummary['profit_loss'] >= 0 ? '+' : '' }}${{ number_format($portfolioSummary['profit_loss'], 2) }}
                            ({{ number_format($portfolioSummary['profit_loss_percentage'], 2) }}%)
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h3 class="font-medium mb-2">Aset Teratas</h3>
                    <div class="space-y-2">
                        @foreach($portfolioSummary['top_assets'] ?? [] as $asset)
                        <div class="flex justify-between items-center neo-brutalism-sm p-2">
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
                    <a href="{{ route('panel.portfolio') }}" class="neo-brutalism-sm bg-brutal-green px-4 py-2 font-medium inline-block">
                        Tambahkan Aset
                    </a>
                </div>
            @endif
        </div>

        <!-- Quick Analysis -->
        <div class="neo-brutalism bg-white p-6 rotate-[-1deg] lg:col-span-2">
            <h2 class="text-2xl font-bold mb-6 flex items-center">
                <div class="bg-brutal-blue p-2 neo-brutalism-sm mr-3 rotate-[2deg]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                    </svg>
                </div>
                Analisis Cepat
            </h2>

            <div class="space-y-4">
                <div class="neo-brutalism-sm p-3 bg-brutal-yellow/10 rotate-[0.5deg]">
                    <div class="font-bold">Market Overview</div>
                    <div class="flex justify-between">
                        <span>Fear & Greed Index:</span>
                        <span class="font-medium">65 (Greed)</span>
                    </div>
                    <div class="flex justify-between">
                        <span>BTC Dominance:</span>
                        <span class="font-medium">52.3%</span>
                    </div>
                </div>

                <div class="neo-brutalism-sm p-3 bg-brutal-pink/10 rotate-[-0.5deg]">
                    <div class="font-bold">Trending Categories</div>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <span class="bg-white px-2 py-0.5 text-xs neo-brutalism-sm">DeFi</span>
                        <span class="bg-white px-2 py-0.5 text-xs neo-brutalism-sm">GameFi</span>
                        <span class="bg-white px-2 py-0.5 text-xs neo-brutalism-sm">Layer-2</span>
                        <span class="bg-white px-2 py-0.5 text-xs neo-brutalism-sm">NFT</span>
                        <span class="bg-white px-2 py-0.5 text-xs neo-brutalism-sm">Meme</span>
                    </div>
                </div>

                <div class="neo-brutalism-sm p-3 bg-brutal-blue/10 rotate-[0.5deg]">
                    <div class="font-bold">Top Gainers 24h</div>
                    <div class="space-y-1 mt-1">
                        <div class="flex justify-between text-sm">
                            <span>PEPE</span>
                            <span class="text-green-600">+14.5%</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span>SOL</span>
                            <span class="text-green-600">+8.2%</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span>SHIB</span>
                            <span class="text-green-600">+6.7%</span>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-6">
                    <a href="{{ route('panel.recommendations') }}" class="neo-brutalism-sm bg-brutal-blue px-4 py-2 font-medium">
                        Detail Analisis
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="neo-brutalism bg-white p-6 rotate-[0.5deg] mb-8">
        <h2 class="text-2xl font-bold mb-6 flex items-center">
            <div class="bg-brutal-orange p-2 neo-brutalism-sm mr-3 rotate-[-2deg]">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            Aktivitas Terbaru
        </h2>

        <div class="space-y-4">
            @forelse($recentInteractions ?? [] as $interaction)
                <div class="neo-brutalism-sm p-3 bg-white flex items-center">
                    <div class="mr-4">
                        @if($interaction->interaction_type == 'view')
                            <div class="bg-brutal-blue/20 p-2 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </div>
                        @elseif($interaction->interaction_type == 'favorite')
                            <div class="bg-brutal-pink/20 p-2 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                            </div>
                        @elseif($interaction->interaction_type == 'portfolio_add')
                            <div class="bg-brutal-green/20 p-2 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2h-2m-4-1v8m0 0l3-3m-3 3l-3-3" />
                                </svg>
                            </div>
                        @else
                            <div class="bg-brutal-yellow/20 p-2 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
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
                        <div class="text-xs text-gray-600">
                            {{ $interaction->created_at->diffForHumans() }}
                        </div>
                    </div>
                    <div>
                        <a href="{{ route('panel.recommendations.project', $interaction->project_id) }}" class="text-xs bg-brutal-orange/20 neo-brutalism-sm px-2 py-1">
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
                    <a href="#" class="neo-brutalism-sm bg-brutal-orange/80 py-2 px-4 font-medium inline-block">
                        Lihat Semua Aktivitas
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- Admin Panel Access (jika pengguna adalah admin) -->
    @if(Auth::user()->isAdmin())
        <div class="neo-brutalism bg-white p-6 rotate-[-0.5deg] mb-8">
            <h2 class="text-2xl font-bold mb-6 flex items-center">
                <div class="bg-brutal-pink p-2 neo-brutalism-sm mr-3 rotate-[2deg]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                    </svg>
                </div>
                Panel Admin
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <a href="{{ route('admin.dashboard') }}" class="neo-brutalism-sm bg-brutal-pink p-4 text-center hover:rotate-[1deg] transform transition">
                    <div class="font-bold">Dashboard Admin</div>
                    <div class="text-xs mt-1">Panel utama admin</div>
                </a>

                <a href="{{ route('admin.users') }}" class="neo-brutalism-sm bg-brutal-yellow p-4 text-center hover:rotate-[-1deg] transform transition">
                    <div class="font-bold">Manajemen Pengguna</div>
                    <div class="text-xs mt-1">Kelola pengguna sistem</div>
                </a>

                <a href="{{ route('admin.projects') }}" class="neo-brutalism-sm bg-brutal-green p-4 text-center hover:rotate-[1deg] transform transition">
                    <div class="font-bold">Manajemen Proyek</div>
                    <div class="text-xs mt-1">Kelola data proyek</div>
                </a>

                <a href="{{ route('admin.data-sync') }}" class="neo-brutalism-sm bg-brutal-blue p-4 text-center hover:rotate-[-1deg] transform transition">
                    <div class="font-bold">Sinkronisasi Data</div>
                    <div class="text-xs mt-1">Update dan sinkronisasi</div>
                </a>
            </div>
        </div>
    @endif
</div>
@endsection

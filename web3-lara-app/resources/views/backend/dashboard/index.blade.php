@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4">
    <!-- Welcome Banner -->
    <div class="neo-brutalism bg-white p-8 mb-8">
        <div class="flex flex-col md:flex-row items-center">
            <div class="md:w-2/3">
                <h1 class="text-3xl md:text-4xl font-bold mb-4">
                    <span class="bg-brutal-yellow rotate-[-1deg] neo-brutalism-sm px-2 py-1 inline-block">Selamat Datang</span>
                    <span class="block mt-2">{{ Auth::user()->username ? Auth::user()->username : 'di Web3 Recommender' }}!</span>
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
                    <p>{{ Auth::user()->last_login ? Auth::user()->last_login->setTimezone('Asia/Jakarta')->translatedFormat('d F Y H:i:s') : 'Pertama kali login' }}</p>
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
                <a href="#trending" class="neo-brutalism-sm bg-brutal-yellow p-3 text-center hover:rotate-[1deg] transform transition">
                    <div class="font-bold">Trending</div>
                    <div class="text-xs mt-1">Proyek terpopuler</div>
                </a>

                <a href="#recommendations" class="neo-brutalism-sm bg-brutal-pink p-3 text-center hover:rotate-[-1deg] transform transition">
                    <div class="font-bold">Rekomendasi</div>
                    <div class="text-xs mt-1">Untuk Anda</div>
                </a>

                <a href="#analysis" class="neo-brutalism-sm bg-brutal-blue p-3 text-center hover:rotate-[1deg] transform transition">
                    <div class="font-bold">Analisis</div>
                    <div class="text-xs mt-1">Teknikal & Sinyal</div>
                </a>

                <a href="#portfolio" class="neo-brutalism-sm bg-brutal-green p-3 text-center hover:rotate-[-1deg] transform transition">
                    <div class="font-bold">Portfolio</div>
                    <div class="text-xs mt-1">Kelola aset Anda</div>
                </a>
            </div>
        </div>
    </div>

    <!-- Trending Projects Section -->
    <div id="trending" class="neo-brutalism bg-white p-6 rotate-[0.5deg] mb-8">
        <h2 class="text-2xl font-bold mb-6 flex items-center">
            <div class="bg-brutal-orange p-2 neo-brutalism-sm mr-3 rotate-[-2deg]">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
            </div>
            Trending Projects
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
                    <!-- Placeholder data - Anda dapat menggantinya dengan data dari controller -->
                    <tr class="border-b border-black">
                        <td class="py-2 px-4">1</td>
                        <td class="py-2 px-4 font-medium">Bitcoin (BTC)</td>
                        <td class="py-2 px-4">$51,240.32</td>
                        <td class="py-2 px-4 text-green-600">+2.4%</td>
                        <td class="py-2 px-4">Layer-1</td>
                        <td class="py-2 px-4">
                            <button class="neo-brutalism-sm bg-brutal-blue/20 px-2 py-1 text-xs">
                                Detail
                            </button>
                        </td>
                    </tr>
                    <tr class="border-b border-black">
                        <td class="py-2 px-4">2</td>
                        <td class="py-2 px-4 font-medium">Ethereum (ETH)</td>
                        <td class="py-2 px-4">$2,450.18</td>
                        <td class="py-2 px-4 text-green-600">+1.8%</td>
                        <td class="py-2 px-4">Layer-1</td>
                        <td class="py-2 px-4">
                            <button class="neo-brutalism-sm bg-brutal-blue/20 px-2 py-1 text-xs">
                                Detail
                            </button>
                        </td>
                    </tr>
                    <tr class="border-b border-black">
                        <td class="py-2 px-4">3</td>
                        <td class="py-2 px-4 font-medium">Solana (SOL)</td>
                        <td class="py-2 px-4">$118.75</td>
                        <td class="py-2 px-4 text-red-600">-0.9%</td>
                        <td class="py-2 px-4">Layer-1</td>
                        <td class="py-2 px-4">
                            <button class="neo-brutalism-sm bg-brutal-blue/20 px-2 py-1 text-xs">
                                Detail
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-2 px-4">4</td>
                        <td class="py-2 px-4 font-medium">Arbitrum (ARB)</td>
                        <td class="py-2 px-4">$1.24</td>
                        <td class="py-2 px-4 text-green-600">+5.2%</td>
                        <td class="py-2 px-4">Layer-2</td>
                        <td class="py-2 px-4">
                            <button class="neo-brutalism-sm bg-brutal-blue/20 px-2 py-1 text-xs">
                                Detail
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recommendations Section -->
    <div id="recommendations" class="neo-brutalism bg-white p-6 rotate-[-0.5deg] mb-8">
        <h2 class="text-2xl font-bold mb-6 flex items-center">
            <div class="bg-brutal-pink p-2 neo-brutalism-sm mr-3 rotate-[2deg]">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            Rekomendasi Untuk Anda
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Placeholder cards - Ganti dengan data dari controller -->
            <div class="neo-brutalism-sm bg-white p-4 rotate-[-1deg] hover:rotate-[0deg] transition duration-300">
                <div class="font-bold text-lg mb-2">Aave (AAVE)</div>
                <div class="text-sm mb-2">$82.13 <span class="text-green-600">+3.1%</span></div>
                <div class="bg-brutal-blue/10 px-2 py-0.5 text-xs inline-block mb-3">DeFi</div>
                <p class="text-sm mb-3">Platform pinjaman berbasis Ethereum dengan berbagai aset digital.</p>
                <div class="text-xs font-medium">Score: <span class="text-brutal-blue">0.92</span></div>
            </div>

            <div class="neo-brutalism-sm bg-white p-4 rotate-[1deg] hover:rotate-[0deg] transition duration-300">
                <div class="font-bold text-lg mb-2">Polygon (MATIC)</div>
                <div class="text-sm mb-2">$0.58 <span class="text-red-600">-1.2%</span></div>
                <div class="bg-brutal-green/10 px-2 py-0.5 text-xs inline-block mb-3">Scaling</div>
                <p class="text-sm mb-3">Solusi layer-2 untuk Ethereum yang cepat dan biaya rendah.</p>
                <div class="text-xs font-medium">Score: <span class="text-brutal-blue">0.89</span></div>
            </div>

            <div class="neo-brutalism-sm bg-white p-4 rotate-[-0.5deg] hover:rotate-[0deg] transition duration-300">
                <div class="font-bold text-lg mb-2">Chainlink (LINK)</div>
                <div class="text-sm mb-2">$14.36 <span class="text-green-600">+0.8%</span></div>
                <div class="bg-brutal-pink/10 px-2 py-0.5 text-xs inline-block mb-3">Oracle</div>
                <p class="text-sm mb-3">Jaringan oracle terdesentralisasi untuk smart contracts.</p>
                <div class="text-xs font-medium">Score: <span class="text-brutal-blue">0.87</span></div>
            </div>

            <div class="neo-brutalism-sm bg-white p-4 rotate-[0.5deg] hover:rotate-[0deg] transition duration-300">
                <div class="font-bold text-lg mb-2">Uniswap (UNI)</div>
                <div class="text-sm mb-2">$7.21 <span class="text-green-600">+2.4%</span></div>
                <div class="bg-brutal-yellow/10 px-2 py-0.5 text-xs inline-block mb-3">DEX</div>
                <p class="text-sm mb-3">Protokol pertukaran terdesentralisasi terkemuka di Ethereum.</p>
                <div class="text-xs font-medium">Score: <span class="text-brutal-blue">0.85</span></div>
            </div>
        </div>

        <div class="mt-4 text-center">
            <button class="neo-brutalism-sm bg-brutal-pink/80 px-4 py-2 font-medium">
                Lihat Lebih Banyak
            </button>
        </div>
    </div>

    <!-- Portfolio & Quick Stats Section -->
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 mb-8">
        <!-- Portfolio Summary -->
        <div id="portfolio" class="neo-brutalism bg-white p-6 rotate-[1deg] lg:col-span-3">
            <h2 class="text-2xl font-bold mb-6 flex items-center">
                <div class="bg-brutal-green p-2 neo-brutalism-sm mr-3 rotate-[-2deg]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
                Portfolio
            </h2>

            <div class="text-center py-8">
                <p class="text-lg mb-4">Anda belum memiliki portfolio.</p>
                <button class="neo-brutalism-sm bg-brutal-green px-4 py-2 font-medium">
                    Tambahkan Aset
                </button>
            </div>
        </div>

        <!-- Quick Analysis -->
        <div id="analysis" class="neo-brutalism bg-white p-6 rotate-[-1deg] lg:col-span-2">
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
                    </div>
                </div>

                <div class="text-center mt-6">
                    <button class="neo-brutalism-sm bg-brutal-blue px-4 py-2 font-medium">
                        Detail Analisis
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

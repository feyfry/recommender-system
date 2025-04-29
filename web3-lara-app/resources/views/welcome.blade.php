<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Web3 Recommender System') }}</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brutal: {
                            yellow: '#FFDE59',
                            pink: '#FF5F7E',
                            blue: '#65CEFF',
                            green: '#7AE582',
                            orange: '#FF914D'
                        }
                    }
                }
            }
        }
    </script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Space Grotesk', sans-serif;
            background-color: #f8f8f8;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23bdbdbd' fill-opacity='0.2'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .neo-brutalism {
            box-shadow: 6px 6px 0 0 #000;
            border: 3px solid #000;
        }
        .big-neo-brutalism {
            box-shadow: 10px 10px 0 0 #000;
            border: 4px solid #000;
        }
        .neo-brutalism-sm {
            box-shadow: 4px 4px 0 0 #000;
            border: 2px solid #000;
        }
        .neo-shadow {
            text-shadow: 2px 2px 0 #000;
        }
        .noisy-bg {
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
            background-blend-mode: multiply;
            background-size: 100px;
            opacity: 0.05;
        }
        .gradient-text {
            background: linear-gradient(90deg, #ff9962 0%, #FF5F7E 50%, #65CEFF 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-fill-color: transparent;
        }
    </style>
</head>
<body>
    <div class="relative min-h-screen overflow-hidden">
        <!-- Animated Circles Background -->
        <div class="absolute left-0 top-0 w-full h-full overflow-hidden z-0">
            <div class="absolute -left-10 -top-10 w-40 h-40 rounded-full bg-brutal-yellow opacity-70"></div>
            <div class="absolute right-10 top-1/4 w-64 h-64 rounded-full bg-brutal-blue opacity-60"></div>
            <div class="absolute left-1/3 bottom-20 w-52 h-52 rounded-full bg-brutal-pink opacity-60"></div>
            <div class="absolute -right-20 -bottom-20 w-72 h-72 rounded-full bg-brutal-green opacity-60"></div>
            <div class="absolute inset-0 noisy-bg"></div>
        </div>

        <!-- Navigation -->
        <nav class="relative z-10 p-4">
            <div class="container mx-auto flex justify-between items-center">
                <div class="flex items-center">
                    <div class="mr-4 neo-brutalism-sm bg-brutal-orange p-2 rotate-[-3deg]">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hop-icon lucide-hop"><path d="M10.82 16.12c1.69.6 3.91.79 5.18.85.55.03 1-.42.97-.97-.06-1.27-.26-3.5-.85-5.18"/><path d="M11.5 6.5c1.64 0 5-.38 6.71-1.07.52-.2.55-.82.12-1.17A10 10 0 0 0 4.26 18.33c.35.43.96.4 1.17-.12.69-1.71 1.07-5.07 1.07-6.71 1.34.45 3.1.9 4.88.62a.88.88 0 0 0 .73-.74c.3-2.14-.15-3.5-.61-4.88"/><path d="M15.62 16.95c.2.85.62 2.76.5 4.28a.77.77 0 0 1-.9.7 16.64 16.64 0 0 1-4.08-1.36"/><path d="M16.13 21.05c1.65.63 3.68.84 4.87.91a.9.9 0 0 0 .96-.96 17.68 17.68 0 0 0-.9-4.87"/><path d="M16.94 15.62c.86.2 2.77.62 4.29.5a.77.77 0 0 0 .7-.9 16.64 16.64 0 0 0-1.36-4.08"/><path d="M17.99 5.52a20.82 20.82 0 0 1 3.15 4.5.8.8 0 0 1-.68 1.13c-2.33.2-5.3-.32-8.27-1.57"/><path d="M4.93 4.93 3 3a.7.7 0 0 1 0-1"/><path d="M9.58 12.18c1.24 2.98 1.77 5.95 1.57 8.28a.8.8 0 0 1-1.13.68 20.82 20.82 0 0 1-4.5-3.15"/></svg>
                    </div>
                    <span class="text-xl font-bold">Web3 Recommender</span>
                </div>
                <div>
                    @auth
                        <a href="{{ route('panel.dashboard') }}" class="neo-brutalism-sm bg-brutal-blue py-1.5 px-4 font-medium hover:rotate-[1deg] transform transition">
                            Dashboard
                        </a>
                    @else
                    <a href="{{ route('login') }}" class="neo-brutalism-sm bg-brutal-green py-1.5 px-4 font-medium hover:rotate-[1deg] transform transition">
                        Login
                    </a>
                    @endauth
                </div>
            </div>
        </nav>

        <!-- Content -->
        <div class="container mx-auto px-4 py-8 relative z-10">
            <!-- Hero Section -->
            <div class="big-neo-brutalism bg-white p-8 sm:p-12 transform transition hover:rotate-0 duration-300 mb-12">
                <h1 class="text-4xl sm:text-5xl md:text-6xl font-bold tracking-tight mb-4 text-gray-900 relative">
                    <span class="relative inline-block">
                        Web3
                        <span class="absolute -top-1 right-0 transform translate-x-1/2 -translate-y-1/2 rotate-12 bg-brutal-yellow px-3 py-1 text-sm font-bold neo-brutalism">
                            NEW
                        </span>
                    </span>
                    <br>
                    <span class="text-brutal-pink">Recommender</span>
                    <span class="text-brutal-blue">System</span>
                </h1>

                <p class="mt-6 text-xl sm:text-2xl font-medium text-gray-700 max-w-2xl">
                    Sistem rekomendasi untuk proyek Web3 (cryptocurrency, token, NFT, DeFi) berbasis popularitas, tren investasi, dan analisis teknikal.
                </p>

                <div class="mt-10">
                    <a href="{{ route('login') }}" class="inline-block bg-brutal-green neo-brutalism px-8 py-4 text-xl font-bold text-black transform transition hover:-translate-y-1 hover:bg-brutal-green/90 rotate-[-2deg] hover:rotate-0">
                        Login dengan Web3 Wallet
                    </a>
                </div>
            </div>

            <!-- Intro Section -->
            <div class="neo-brutalism bg-white p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block bg-brutal-yellow neo-brutalism-sm p-2">📋 Deskripsi</h2>
                <p class="text-lg mb-6">
                    Sistem ini menggunakan data dari CoinGecko API untuk menyediakan rekomendasi proyek Web3 berdasarkan:
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                    <div class="neo-brutalism-sm bg-brutal-blue p-4 rotate-1">
                        <p class="font-bold">💹 Metrik Popularitas</p>
                        <p>Market cap, volume, metrik sosial</p>
                    </div>
                    <div class="neo-brutalism-sm bg-brutal-pink p-4 -rotate-1">
                        <p class="font-bold">📈 Tren Investasi</p>
                        <p>Perubahan harga, sentimen pasar</p>
                    </div>
                    <div class="neo-brutalism-sm bg-brutal-green p-4 rotate-2">
                        <p class="font-bold">👤 Interaksi Pengguna</p>
                        <p>View, favorite, portfolio</p>
                    </div>
                    <div class="neo-brutalism-sm bg-brutal-yellow p-4 -rotate-2">
                        <p class="font-bold">🏷️ Fitur Proyek</p>
                        <p>DeFi, GameFi, Layer-1, dll</p>
                    </div>
                </div>

                <p class="text-lg mb-4">
                    Sistem ini mengimplementasikan beberapa pendekatan rekomendasi:
                </p>
                <ol class="list-decimal list-inside space-y-2 pl-4">
                    <li><strong>Feature-Enhanced Collaborative Filtering</strong> menggunakan scikit-learn SVD</li>
                    <li><strong>Neural Collaborative Filtering</strong> menggunakan PyTorch</li>
                    <li><strong>Enhanced Hybrid Model</strong> yang menggabungkan kedua pendekatan dengan teknik ensemble canggih</li>
                </ol>
            </div>

            <!-- Models Comparison Section -->
            <div class="neo-brutalism bg-white p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block bg-brutal-orange neo-brutalism-sm p-2">🚀 Fitur Utama</h2>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Feature-Enhanced CF -->
                    <div class="neo-brutalism-sm bg-brutal-yellow p-6 rotate-[-1deg]">
                        <h3 class="text-xl font-bold mb-2">Feature-Enhanced CF</h3>
                        <p class="mb-4">Model berbasis SVD yang menggabungkan collaborative filtering dengan informasi fitur proyek.</p>
                        <ul class="space-y-1 text-sm">
                            <li>✅ Menangani pengguna baru (cold-start)</li>
                            <li>✅ Merekomendasikan item berbasis kesamaan fitur</li>
                            <li>✅ Efektif dengan data sparse</li>
                        </ul>
                    </div>

                    <!-- Neural CF -->
                    <div class="neo-brutalism-sm bg-brutal-blue p-6 rotate-[1deg]">
                        <h3 class="text-xl font-bold mb-2">Neural CF</h3>
                        <p class="mb-4">Model deep learning yang menangkap pola kompleks dalam interaksi user-item.</p>
                        <ul class="space-y-1 text-sm">
                            <li>✅ Personalisasi tingkat tinggi</li>
                            <li>✅ Pola interaksi non-linear</li>
                            <li>✅ Akurasi tinggi untuk pengguna aktif</li>
                        </ul>
                    </div>

                    <!-- Hybrid Model -->
                    <div class="neo-brutalism-sm bg-brutal-pink p-6 rotate-[-0.5deg]">
                        <h3 class="text-xl font-bold mb-2">Enhanced Hybrid Model</h3>
                        <p class="mb-4">Model yang menggabungkan kekuatan kedua pendekatan dengan teknik ensemble.</p>
                        <ul class="space-y-1 text-sm">
                            <li>✅ Normalisasi skor dengan transformasi sigmoid</li>
                            <li>✅ Tiga metode ensemble (weighted, max, rank fusion)</li>
                            <li>✅ Pembobotan dinamis berdasarkan interaksi user</li>
                        </ul>
                    </div>
                </div>

                <!-- Additional Features -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="neo-brutalism-sm bg-brutal-green p-5 rotate-[0.5deg]">
                        <h3 class="text-xl font-bold mb-3">Analisis Teknikal dengan Periode Dinamis</h3>
                        <p class="mb-4">Dukungan lengkap untuk analisis teknikal dengan periode indikator yang dapat dikonfigurasi.</p>
                        <div class="mb-2 text-sm font-bold">Preset Trading Style:</div>
                        <div class="grid grid-cols-3 gap-2 text-sm">
                            <div class="bg-white p-2 neo-brutalism-sm rotate-[-1deg]">Short-Term</div>
                            <div class="bg-white p-2 neo-brutalism-sm">Standard</div>
                            <div class="bg-white p-2 neo-brutalism-sm rotate-[1deg]">Long-Term</div>
                        </div>
                    </div>

                    <div class="neo-brutalism-sm bg-white p-5 rotate-[-0.5deg]">
                        <h3 class="text-xl font-bold mb-3">Cold-Start Solution</h3>
                        <p class="mb-3">Rekomendasi cerdas bahkan untuk pengguna tanpa interaksi sebelumnya.</p>
                        <div class="overflow-hidden neo-brutalism-sm">
                            <table class="min-w-full bg-white text-sm">
                                <thead class="bg-brutal-blue">
                                    <tr>
                                        <th class="p-2">Model</th>
                                        <th class="p-2">Hit Ratio</th>
                                        <th class="p-2">NDCG</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="border-b border-black">
                                        <td class="p-2 font-medium">FECF</td>
                                        <td class="p-2">0.5238</td>
                                        <td class="p-2">0.1684</td>
                                    </tr>
                                    <tr>
                                        <td class="p-2 font-medium">Hybrid</td>
                                        <td class="p-2 font-bold">0.5783</td>
                                        <td class="p-2 font-bold">0.1958</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="neo-brutalism bg-white p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block bg-brutal-pink neo-brutalism-sm p-2">📊 Performa Model</h2>

                <div class="overflow-x-auto">
                    <div class="neo-brutalism-sm overflow-hidden bg-white inline-block min-w-full">
                        <table class="min-w-full">
                            <thead class="bg-brutal-yellow">
                                <tr>
                                    <th class="p-3 text-left">Model</th>
                                    <th class="p-3 text-left">Precision</th>
                                    <th class="p-3 text-left">Recall</th>
                                    <th class="p-3 text-left">F1</th>
                                    <th class="p-3 text-left">NDCG</th>
                                    <th class="p-3 text-left">Hit Ratio</th>
                                    <th class="p-3 text-left">MRR</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b border-black">
                                    <td class="p-3 font-medium">FECF</td>
                                    <td class="p-3">0.1316</td>
                                    <td class="p-3">0.3855</td>
                                    <td class="p-3">0.1826</td>
                                    <td class="p-3">0.2945</td>
                                    <td class="p-3">0.8148</td>
                                    <td class="p-3">0.4001</td>
                                </tr>
                                <tr class="border-b border-black">
                                    <td class="p-3 font-medium">NCF</td>
                                    <td class="p-3">0.1098</td>
                                    <td class="p-3">0.2802</td>
                                    <td class="p-3">0.1458</td>
                                    <td class="p-3">0.1986</td>
                                    <td class="p-3">0.7138</td>
                                    <td class="p-3">0.2974</td>
                                </tr>
                                <tr>
                                    <td class="p-3 font-medium bg-brutal-green/20">hybrid</td>
                                    <td class="p-3 font-bold">0.1461</td>
                                    <td class="p-3 font-bold">0.4045</td>
                                    <td class="p-3 font-bold">0.1987</td>
                                    <td class="p-3 font-bold">0.2954</td>
                                    <td class="p-3 font-bold">0.8788</td>
                                    <td class="p-3 font-bold">0.3923</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-6 text-sm">
                    <p class="font-bold">Model hybrid yang ditingkatkan mengungguli kedua model dasar dalam hampir semua metrik, dengan peningkatan paling signifikan pada:</p>
                    <ul class="mt-2 space-y-1">
                        <li>• Recall: +4.9% vs FECF</li>
                        <li>• Hit Ratio: +7.9% vs FECF</li>
                    </ul>
                </div>
            </div>

            <!-- Technical Analysis -->
            <div class="neo-brutalism bg-white p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block bg-brutal-blue neo-brutalism-sm p-2">📈 Analisis Teknikal</h2>

                <p class="mb-6 text-lg">
                    Komponen analisis teknikal sekarang mendukung periode indikator yang sepenuhnya dapat dikonfigurasi,
                    memungkinkan penyesuaian untuk berbagai gaya trading.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Short Term -->
                    <div class="neo-brutalism-sm p-5 bg-brutal-orange/20 rotate-[-0.5deg]">
                        <h3 class="text-lg font-bold mb-3">Short-Term Trading</h3>
                        <div class="text-sm space-y-1">
                            <p><span class="font-medium">RSI:</span> 7 periode</p>
                            <p><span class="font-medium">MACD:</span> 8-17-9</p>
                            <p><span class="font-medium">Bollinger:</span> 10 periode</p>
                            <p><span class="font-medium">Stochastic:</span> 7K, 3D</p>
                            <p><span class="font-medium">MA:</span> 10-30-60</p>
                        </div>
                    </div>

                    <!-- Standard -->
                    <div class="neo-brutalism-sm p-5 bg-brutal-green/20 rotate-[0.5deg]">
                        <h3 class="text-lg font-bold mb-3">Standard Trading</h3>
                        <div class="text-sm space-y-1">
                            <p><span class="font-medium">RSI:</span> 14 periode</p>
                            <p><span class="font-medium">MACD:</span> 12-26-9</p>
                            <p><span class="font-medium">Bollinger:</span> 20 periode</p>
                            <p><span class="font-medium">Stochastic:</span> 14K, 3D</p>
                            <p><span class="font-medium">MA:</span> 20-50-200</p>
                        </div>
                    </div>

                    <!-- Long Term -->
                    <div class="neo-brutalism-sm p-5 bg-brutal-blue/20 rotate-[-0.5deg]">
                        <h3 class="text-lg font-bold mb-3">Long-Term Trading</h3>
                        <div class="text-sm space-y-1">
                            <p><span class="font-medium">RSI:</span> 21 periode</p>
                            <p><span class="font-medium">MACD:</span> 19-39-9</p>
                            <p><span class="font-medium">Bollinger:</span> 30 periode</p>
                            <p><span class="font-medium">Stochastic:</span> 21K, 7D</p>
                            <p><span class="font-medium">MA:</span> 50-100-200</p>
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                    <h3 class="text-xl font-bold mb-4">Indikator Teknikal yang Didukung</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="neo-brutalism-sm bg-brutal-pink/30 p-3 rotate-[-1deg]">
                            <p class="font-bold">Indikator Tren</p>
                            <p class="text-sm">Moving Averages, MACD, ADX</p>
                        </div>
                        <div class="neo-brutalism-sm bg-brutal-yellow/30 p-3 rotate-[1deg]">
                            <p class="font-bold">Indikator Momentum</p>
                            <p class="text-sm">RSI, Stochastic, CCI</p>
                        </div>
                        <div class="neo-brutalism-sm bg-brutal-blue/30 p-3 rotate-[-1deg]">
                            <p class="font-bold">Indikator Volatilitas</p>
                            <p class="text-sm">Bollinger Bands, ATR</p>
                        </div>
                        <div class="neo-brutalism-sm bg-brutal-green/30 p-3 rotate-[1deg]">
                            <p class="font-bold">Indikator Volume</p>
                            <p class="text-sm">OBV, MFI, Chaikin A/D</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Domain Characteristics -->
            <div class="neo-brutalism bg-white p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block bg-brutal-yellow neo-brutalism-sm p-2">💡 Karakteristik Domain</h2>

                <p class="text-lg mb-6">
                    Domain cryptocurrency memiliki karakteristik unik yang mempengaruhi kinerja sistem rekomendasi:
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div class="neo-brutalism-sm bg-brutal-pink/20 p-4 rotate-[1deg]">
                        <p class="font-bold">Volatilitas Tinggi</p>
                        <p class="text-sm">Perubahan harga dan popularitas yang cepat membuat pola interaksi berubah-ubah</p>
                    </div>

                    <div class="neo-brutalism-sm bg-brutal-blue/20 p-4 rotate-[-1deg]">
                        <p class="font-bold">Pengaruh Eksternal</p>
                        <p class="text-sm">Keputusan investasi dipengaruhi oleh berita, media sosial, dan sentimen pasar</p>
                    </div>

                    <div class="neo-brutalism-sm bg-brutal-green/20 p-4 rotate-[0.5deg]">
                        <p class="font-bold">Data Sparsity</p>
                        <p class="text-sm">Pengguna cenderung berinteraksi dengan sedikit token, menghasilkan matriks yang sparse</p>
                    </div>

                    <div class="neo-brutalism-sm bg-brutal-yellow/20 p-4 rotate-[-0.5deg]">
                        <p class="font-bold">Dominasi Popularitas</p>
                        <p class="text-sm">Proyek populer (Bitcoin, Ethereum) mendominasi interaksi, menciptakan distribusi long-tail</p>
                    </div>

                    <div class="neo-brutalism-sm bg-brutal-orange/20 p-4 rotate-[1deg]">
                        <p class="font-bold">Konteks Temporal</p>
                        <p class="text-sm">Waktu sangat mempengaruhi relevansi rekomendasi dalam domain crypto</p>
                    </div>
                </div>
            </div>

            <!-- API Section -->
            <div class="neo-brutalism bg-white p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block bg-brutal-green neo-brutalism-sm p-2">🌐 API Reference</h2>

                <p class="text-lg mb-6">
                    Sistem ini menyediakan RESTful API yang komprehensif menggunakan FastAPI.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="neo-brutalism-sm bg-white p-4">
                        <h3 class="text-lg font-bold mb-3">Endpoint Rekomendasi</h3>
                        <ul class="space-y-2 text-sm font-mono">
                            <li class="bg-brutal-blue/10 p-2"><span class="font-bold">POST</span> /recommend/projects</li>
                            <li class="bg-brutal-blue/10 p-2"><span class="font-bold">GET</span> /recommend/trending</li>
                            <li class="bg-brutal-blue/10 p-2"><span class="font-bold">GET</span> /recommend/popular</li>
                            <li class="bg-brutal-blue/10 p-2"><span class="font-bold">GET</span> /recommend/similar/{project_id}</li>
                        </ul>
                    </div>

                    <div class="neo-brutalism-sm bg-white p-4">
                        <h3 class="text-lg font-bold mb-3">Endpoint Analisis</h3>
                        <ul class="space-y-2 text-sm font-mono">
                            <li class="bg-brutal-pink/10 p-2"><span class="font-bold">POST</span> /analysis/trading-signals</li>
                            <li class="bg-brutal-pink/10 p-2"><span class="font-bold">POST</span> /analysis/indicators</li>
                            <li class="bg-brutal-pink/10 p-2"><span class="font-bold">GET</span> /analysis/market-events/{project_id}</li>
                            <li class="bg-brutal-pink/10 p-2"><span class="font-bold">GET</span> /analysis/price-prediction/{project_id}</li>
                        </ul>
                    </div>
                </div>

                <div class="mt-6">
                    <h3 class="text-lg font-bold mb-3">Contoh Response Format</h3>
                    <div class="neo-brutalism-sm bg-black text-green-400 p-4 font-mono text-xs overflow-x-auto whitespace-pre">
{
  "project_id": "bitcoin",
  "action": "buy",
  "confidence": 0.85,
  "strong_signal": true,
  "evidence": [
    "RSI is oversold at 28.50 (periode 7)",
    "MACD crossed above signal line (bullish) - (8/17/9)",
    "Price below lower Bollinger Band (oversold) - (periode 10)"
  ],
  "target_price": 52500.0,
  "personalized_message": "Signal matches your balanced risk profile"
}
                    </div>
                </div>
            </div>

            <!-- CTA Section -->
            <div class="big-neo-brutalism bg-white p-8 sm:p-10 text-center mb-12">
                <h2 class="text-3xl font-bold mb-6">Mulai Sekarang!</h2>
                <p class="text-xl mb-8">Login dengan Web3 wallet dan dapatkan rekomendasi personal untuk investasi cryptocurrency Anda.</p>
                <a href="{{ route('login') }}" class="inline-block bg-brutal-pink neo-brutalism p-4 text-xl font-bold text-black transform transition hover:translate-y-[-4px] hover:rotate-[1deg]">
                    <span class="flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4" />
                        </svg>
                        Hubungkan Wallet
                    </span>
                </a>
            </div>

            <!-- Footer -->
            <div class="text-center py-8">
                <div class="inline-block neo-brutalism-sm bg-brutal-blue py-2 px-4 text-sm font-bold rotate-[1deg]">
                    SKRIPSI
                </div>
                <p class="mt-4 font-medium">
                    Pengembangan Sistem Rekomendasi Berbasis Popularitas dan Tren Investasi<br>dengan Antarmuka Web3: Komparasi Pendekatan Neural CF dan Feature-Enhanced CF
                </p>
                <p class="mt-3 text-sm">&copy; 2025 Web3 Recommender System</p>
            </div>
        </div>
    </div>
</body>
</html>

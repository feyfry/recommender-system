<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Web3 Recommender System') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Styles -->
    <link rel="stylesheet" href="{{ asset('backend/assets/css/claymorphism.css') }}">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Custom Tailwind Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#6366f1',
                        secondary: '#ec4899',
                        success: '#10b981',
                        warning: '#f59e0b',
                        danger: '#ef4444',
                        info: '#3b82f6',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
</head>
<body class="min-h-screen">
    <!-- Clay Gradient Background -->
    <div class="clay-gradient-bg">
        <div class="clay-blob clay-blob-1"></div>
        <div class="clay-blob clay-blob-2"></div>
        <div class="clay-blob clay-blob-3"></div>
    </div>

    <div class="relative min-h-screen">
        <!-- Navigation -->
        <nav class="relative z-10 p-4">
            <div class="container mx-auto flex justify-between items-center">
                <div class="flex items-center">
                    <div class="bg-primary p-2 rounded-lg shadow-lg mr-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hop"><path d="M10.82 16.12c1.69.6 3.91.79 5.18.85.55.03 1-.42.97-.97-.06-1.27-.26-3.5-.85-5.18"/><path d="M11.5 6.5c1.64 0 5-.38 6.71-1.07.52-.2.55-.82.12-1.17A10 10 0 0 0 4.26 18.33c.35.43.96.4 1.17-.12.69-1.71 1.07-5.07 1.07-6.71 1.34.45 3.1.9 4.88.62a.88.88 0 0 0 .73-.74c.3-2.14-.15-3.5-.61-4.88"/><path d="M15.62 16.95c.2.85.62 2.76.5 4.28a.77.77 0 0 1-.9.7 16.64 16.64 0 0 1-4.08-1.36"/><path d="M16.13 21.05c1.65.63 3.68.84 4.87.91a.9.9 0 0 0 .96-.96 17.68 17.68 0 0 0-.9-4.87"/><path d="M16.94 15.62c.86.2 2.77.62 4.29.5a.77.77 0 0 0 .7-.9 16.64 16.64 0 0 0-1.36-4.08"/><path d="M17.99 5.52a20.82 20.82 0 0 1 3.15 4.5.8.8 0 0 1-.68 1.13c-2.33.2-5.3-.32-8.27-1.57"/><path d="M4.93 4.93 3 3a.7.7 0 0 1 0-1"/><path d="M9.58 12.18c1.24 2.98 1.77 5.95 1.57 8.28a.8.8 0 0 1-1.13.68 20.82 20.82 0 0 1-4.5-3.15"/></svg>
                    </div>
                    <span class="text-xl font-bold">Web3 Recommender</span>
                </div>
                <div>
                    @auth
                        <a href="{{ route('panel.dashboard') }}" class="clay-button clay-button-primary">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="clay-button clay-button-success">
                            Login
                        </a>
                    @endauth
                </div>
            </div>
        </nav>

        <!-- Content -->
        <div class="container mx-auto px-4 py-8 relative z-10">
            <!-- Hero Section -->
            <div class="clay-card clay-card-lg p-8 sm:p-12 transform transition hover:translate-y-[-5px] mb-12">
                <h1 class="text-4xl sm:text-5xl md:text-6xl font-bold tracking-tight mb-4 text-primary relative">
                    <span class="relative inline-block">
                        Web3
                        <span class="absolute -top-1 right-0 transform translate-x-1/2 -translate-y-1/2 rotate-12 clay-badge clay-badge-warning font-bold">
                            BARU
                        </span>
                    </span>
                    <br>
                    <span class="text-secondary">Recommender</span>
                    <span class="text-info">System</span>
                </h1>

                <p class="mt-6 text-xl sm:text-2xl font-medium text-gray-700 max-w-2xl">
                    Sistem rekomendasi untuk proyek Web3 (cryptocurrency, token, NFT, DeFi) berbasis popularitas, tren investasi, dan analisis teknikal.
                </p>

                <div class="mt-10">
                    <a href="{{ route('login') }}" class="clay-button clay-button-success px-8 py-4 text-lg font-bold transform transition hover:translate-y-[-5px]">
                        <i class="fas fa-wallet mr-2"></i> Login dengan Web3 Wallet
                    </a>
                </div>
            </div>

            <!-- Intro Section -->
            <div class="clay-card p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block clay-badge clay-badge-primary p-2 text-lg">📋 Deskripsi</h2>
                <p class="text-lg mb-6">
                    Sistem ini menggunakan data dari CoinGecko API untuk menyediakan rekomendasi proyek Web3 berdasarkan:
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="clay-card bg-primary/10 p-6">
                        <p class="font-bold flex items-center text-lg mb-2"><i class="fas fa-chart-line mr-2"></i> Metrik Popularitas</p>
                        <p>Market cap, volume, metrik sosial</p>
                    </div>
                    <div class="clay-card bg-secondary/10 p-6">
                        <p class="font-bold flex items-center text-lg mb-2"><i class="fas fa-chart-bar mr-2"></i> Tren Investasi</p>
                        <p>Perubahan harga, sentimen pasar</p>
                    </div>
                    <div class="clay-card bg-success/10 p-6">
                        <p class="font-bold flex items-center text-lg mb-2"><i class="fas fa-user-check mr-2"></i> Interaksi Pengguna</p>
                        <p>View, favorite, portfolio</p>
                    </div>
                    <div class="clay-card bg-warning/10 p-6">
                        <p class="font-bold flex items-center text-lg mb-2"><i class="fas fa-tags mr-2"></i> Fitur Proyek</p>
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
            <div class="clay-card p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block clay-badge clay-badge-secondary p-2 text-lg">🚀 Fitur Utama</h2>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Feature-Enhanced CF -->
                    <div class="clay-card bg-warning/10 p-6">
                        <h3 class="text-xl font-bold mb-2">Feature-Enhanced CF</h3>
                        <p class="mb-4">Model berbasis SVD yang menggabungkan collaborative filtering dengan informasi fitur proyek.</p>
                        <ul class="space-y-1 text-sm">
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Menangani pengguna baru (cold-start)</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Merekomendasikan item berbasis kesamaan fitur</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Efektif dengan data sparse</li>
                        </ul>
                    </div>

                    <!-- Neural CF -->
                    <div class="clay-card bg-info/10 p-6">
                        <h3 class="text-xl font-bold mb-2">Neural CF</h3>
                        <p class="mb-4">Model deep learning yang menangkap pola kompleks dalam interaksi user-item.</p>
                        <ul class="space-y-1 text-sm">
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Personalisasi tingkat tinggi</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Pola interaksi non-linear</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Akurasi tinggi untuk pengguna aktif</li>
                        </ul>
                    </div>

                    <!-- Hybrid Model -->
                    <div class="clay-card bg-secondary/10 p-6">
                        <h3 class="text-xl font-bold mb-2">Enhanced Hybrid Model</h3>
                        <p class="mb-4">Model yang menggabungkan kekuatan kedua pendekatan dengan teknik ensemble.</p>
                        <ul class="space-y-1 text-sm">
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Normalisasi skor dengan transformasi sigmoid</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Tiga metode ensemble (weighted, max, rank fusion)</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Pembobotan dinamis berdasarkan interaksi user</li>
                        </ul>
                    </div>
                </div>

                <!-- Additional Features -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="clay-card bg-success/10 p-6">
                        <h3 class="text-xl font-bold mb-3 flex items-center"><i class="fas fa-chart-pie mr-2"></i>Analisis Teknikal dengan Periode Dinamis</h3>
                        <p class="mb-4">Dukungan lengkap untuk analisis teknikal dengan periode indikator yang dapat dikonfigurasi.</p>
                        <div class="mb-2 text-sm font-bold">Preset Trading Style:</div>
                        <div class="grid grid-cols-3 gap-2 text-sm">
                            <div class="clay-badge clay-badge-primary">Short-Term</div>
                            <div class="clay-badge clay-badge-warning">Standard</div>
                            <div class="clay-badge clay-badge-secondary">Long-Term</div>
                        </div>
                    </div>

                    <div class="clay-card bg-info/10 p-6">
                        <h3 class="text-xl font-bold mb-3 flex items-center"><i class="fas fa-snowflake mr-2"></i>Cold-Start Solution</h3>
                        <p class="mb-3">Rekomendasi cerdas bahkan untuk pengguna tanpa interaksi sebelumnya.</p>
                        <div class="clay-table overflow-hidden">
                            <table class="min-w-full bg-white">
                                <thead>
                                    <tr>
                                        <th class="p-2 text-left">Model</th>
                                        <th class="p-2 text-left">Hit Ratio</th>
                                        <th class="p-2 text-left">NDCG</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="p-2 font-medium">FECF</td>
                                        <td class="p-2">0.5238</td>
                                        <td class="p-2">0.1684</td>
                                    </tr>
                                    <tr>
                                        <td class="p-2 font-medium">Hybrid</td>
                                        <td class="p-2 font-bold text-success">0.5783</td>
                                        <td class="p-2 font-bold text-success">0.1958</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="clay-card p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block clay-badge clay-badge-info p-2 text-lg">📊 Performa Model</h2>

                <div class="overflow-x-auto">
                    <div class="clay-table overflow-hidden inline-block min-w-full">
                        <table class="min-w-full">
                            <thead>
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
                                <tr class="border-b border-gray-100">
                                    <td class="p-3 font-medium">FECF</td>
                                    <td class="p-3">0.1316</td>
                                    <td class="p-3">0.3855</td>
                                    <td class="p-3">0.1826</td>
                                    <td class="p-3">0.2945</td>
                                    <td class="p-3">0.8148</td>
                                    <td class="p-3">0.4001</td>
                                </tr>
                                <tr class="border-b border-gray-100">
                                    <td class="p-3 font-medium">NCF</td>
                                    <td class="p-3">0.1098</td>
                                    <td class="p-3">0.2802</td>
                                    <td class="p-3">0.1458</td>
                                    <td class="p-3">0.1986</td>
                                    <td class="p-3">0.7138</td>
                                    <td class="p-3">0.2974</td>
                                </tr>
                                <tr>
                                    <td class="p-3 font-medium bg-success/10">hybrid</td>
                                    <td class="p-3 font-bold text-success">0.1461</td>
                                    <td class="p-3 font-bold text-success">0.4045</td>
                                    <td class="p-3 font-bold text-success">0.1987</td>
                                    <td class="p-3 font-bold text-success">0.2954</td>
                                    <td class="p-3 font-bold text-success">0.8788</td>
                                    <td class="p-3 font-bold text-success">0.3923</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-6 text-sm">
                    <p class="font-bold">Model hybrid yang ditingkatkan mengungguli kedua model dasar dalam hampir semua metrik, dengan peningkatan paling signifikan pada:</p>
                    <ul class="mt-2 space-y-1">
                        <li class="flex items-center"><i class="fas fa-arrow-up text-success mr-2"></i> Recall: +4.9% vs FECF</li>
                        <li class="flex items-center"><i class="fas fa-arrow-up text-success mr-2"></i> Hit Ratio: +7.9% vs FECF</li>
                    </ul>
                </div>
            </div>

            <!-- Technical Analysis -->
            <div class="clay-card p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block clay-badge clay-badge-success p-2 text-lg">📈 Analisis Teknikal</h2>

                <p class="mb-6 text-lg">
                    Komponen analisis teknikal sekarang mendukung periode indikator yang sepenuhnya dapat dikonfigurasi,
                    memungkinkan penyesuaian untuk berbagai gaya trading.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Short Term -->
                    <div class="clay-card bg-warning/10 p-5">
                        <h3 class="text-lg font-bold mb-3 flex items-center"><i class="fas fa-bolt mr-2"></i>Short-Term Trading</h3>
                        <div class="text-sm space-y-1">
                            <p><span class="font-medium">RSI:</span> 7 periode</p>
                            <p><span class="font-medium">MACD:</span> 8-17-9</p>
                            <p><span class="font-medium">Bollinger:</span> 10 periode</p>
                            <p><span class="font-medium">Stochastic:</span> 7K, 3D</p>
                            <p><span class="font-medium">MA:</span> 10-30-60</p>
                        </div>
                    </div>

                    <!-- Standard -->
                    <div class="clay-card bg-success/10 p-5">
                        <h3 class="text-lg font-bold mb-3 flex items-center"><i class="fas fa-balance-scale mr-2"></i>Standard Trading</h3>
                        <div class="text-sm space-y-1">
                            <p><span class="font-medium">RSI:</span> 14 periode</p>
                            <p><span class="font-medium">MACD:</span> 12-26-9</p>
                            <p><span class="font-medium">Bollinger:</span> 20 periode</p>
                            <p><span class="font-medium">Stochastic:</span> 14K, 3D</p>
                            <p><span class="font-medium">MA:</span> 20-50-200</p>
                        </div>
                    </div>

                    <!-- Long Term -->
                    <div class="clay-card bg-info/10 p-5">
                        <h3 class="text-lg font-bold mb-3 flex items-center"><i class="fas fa-mountain mr-2"></i>Long-Term Trading</h3>
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
                        <div class="clay-card bg-secondary/10 p-3">
                            <p class="font-bold">Indikator Tren</p>
                            <p class="text-sm">Moving Averages, MACD, ADX</p>
                        </div>
                        <div class="clay-card bg-warning/10 p-3">
                            <p class="font-bold">Indikator Momentum</p>
                            <p class="text-sm">RSI, Stochastic, CCI</p>
                        </div>
                        <div class="clay-card bg-info/10 p-3">
                            <p class="font-bold">Indikator Volatilitas</p>
                            <p class="text-sm">Bollinger Bands, ATR</p>
                        </div>
                        <div class="clay-card bg-success/10 p-3">
                            <p class="font-bold">Indikator Volume</p>
                            <p class="text-sm">OBV, MFI, Chaikin A/D</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Domain Characteristics -->
            <div class="clay-card p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block clay-badge clay-badge-warning p-2 text-lg">💡 Karakteristik Domain</h2>

                <p class="text-lg mb-6">
                    Domain cryptocurrency memiliki karakteristik unik yang mempengaruhi kinerja sistem rekomendasi:
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div class="clay-card bg-secondary/10 p-4">
                        <p class="font-bold flex items-center"><i class="fas fa-bolt mr-2"></i>Volatilitas Tinggi</p>
                        <p class="text-sm">Perubahan harga dan popularitas yang cepat membuat pola interaksi berubah-ubah</p>
                    </div>

                    <div class="clay-card bg-info/10 p-4">
                        <p class="font-bold flex items-center"><i class="fas fa-globe mr-2"></i>Pengaruh Eksternal</p>
                        <p class="text-sm">Keputusan investasi dipengaruhi oleh berita, media sosial, dan sentimen pasar</p>
                    </div>

                    <div class="clay-card bg-success/10 p-4">
                        <p class="font-bold flex items-center"><i class="fas fa-database mr-2"></i>Data Sparsity</p>
                        <p class="text-sm">Pengguna cenderung berinteraksi dengan sedikit token, menghasilkan matriks yang sparse</p>
                    </div>

                    <div class="clay-card bg-warning/10 p-4">
                        <p class="font-bold flex items-center"><i class="fas fa-chart-line mr-2"></i>Dominasi Popularitas</p>
                        <p class="text-sm">Proyek populer (Bitcoin, Ethereum) mendominasi interaksi, menciptakan distribusi long-tail</p>
                    </div>

                    <div class="clay-card bg-primary/10 p-4">
                        <p class="font-bold flex items-center"><i class="fas fa-clock mr-2"></i>Konteks Temporal</p>
                        <p class="text-sm">Waktu sangat mempengaruhi relevansi rekomendasi dalam domain crypto</p>
                    </div>
                </div>
            </div>

            <!-- API Section -->
            <div class="clay-card p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block clay-badge clay-badge-primary p-2 text-lg">🌐 API Reference</h2>

                <p class="text-lg mb-6">
                    Sistem ini menyediakan RESTful API yang komprehensif menggunakan FastAPI.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="clay-card bg-primary/5 p-4">
                        <h3 class="text-lg font-bold mb-3 flex items-center"><i class="fas fa-code mr-2"></i>Endpoint Rekomendasi</h3>
                        <ul class="space-y-2 text-sm font-mono">
                            <li class="clay-card bg-primary/10 p-2"><span class="font-bold text-primary">POST</span> /recommend/projects</li>
                            <li class="clay-card bg-primary/10 p-2"><span class="font-bold text-primary">GET</span> /recommend/trending</li>
                            <li class="clay-card bg-primary/10 p-2"><span class="font-bold text-primary">GET</span> /recommend/popular</li>
                            <li class="clay-card bg-primary/10 p-2"><span class="font-bold text-primary">GET</span> /recommend/similar/{project_id}</li>
                        </ul>
                    </div>

                    <div class="clay-card bg-secondary/5 p-4">
                        <h3 class="text-lg font-bold mb-3 flex items-center"><i class="fas fa-chart-bar mr-2"></i>Endpoint Analisis</h3>
                        <ul class="space-y-2 text-sm font-mono">
                            <li class="clay-card bg-secondary/10 p-2"><span class="font-bold text-secondary">POST</span> /analysis/trading-signals</li>
                            <li class="clay-card bg-secondary/10 p-2"><span class="font-bold text-secondary">POST</span> /analysis/indicators</li>
                            <li class="clay-card bg-secondary/10 p-2"><span class="font-bold text-secondary">GET</span> /analysis/market-events/{project_id}</li>
                            <li class="clay-card bg-secondary/10 p-2"><span class="font-bold text-secondary">GET</span> /analysis/price-prediction/{project_id}</li>
                        </ul>
                    </div>
                </div>

                <div class="mt-6">
                    <h3 class="text-lg font-bold mb-3">Contoh Response Format</h3>
                    <div class="clay-card bg-gray-800 text-green-400 p-4 font-mono text-xs overflow-x-auto whitespace-pre">
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
            <div class="clay-card clay-card-lg p-8 sm:p-10 text-center mb-12">
                <h2 class="text-3xl font-bold mb-6">Mulai Sekarang!</h2>
                <p class="text-xl mb-8">Login dengan Web3 wallet dan dapatkan rekomendasi personal untuk investasi cryptocurrency Anda.</p>
                <a href="{{ route('login') }}" class="clay-button clay-button-secondary px-8 py-4 text-xl font-bold transform transition hover:translate-y-[-5px]">
                    <i class="fas fa-wallet mr-2"></i> Hubungkan Wallet
                </a>
            </div>

            <!-- Footer -->
            <div class="text-center py-8">
                <div class="clay-badge clay-badge-info py-2 px-4 text-sm font-bold inline-block mb-4">
                    SKRIPSI
                </div>
                <p class="mt-4 font-medium">
                    Pengembangan Sistem Rekomendasi Berbasis Popularitas dan Tren Investasi<br>Cryptocurrency dengan Metode: Neural CF, Feature-Enhanced CF dan Hybrid
                </p>
                <p class="mt-3 text-sm">&copy; 2025 Web3 Recommender System</p>
            </div>
        </div>
    </div>
</body>
</html>

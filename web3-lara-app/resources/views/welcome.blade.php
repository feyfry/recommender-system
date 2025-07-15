<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Crypto Recommender System') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css'])
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
                    <span class="text-xl font-bold">Crypto Recommender</span>
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
                        Crypto
                        <span class="absolute -top-1 right-0 transform translate-x-1/2 -translate-y-1/2 rotate-12 clay-badge clay-badge-warning font-bold text-xs">
                            v3.1
                        </span>
                    </span>
                    <br>
                    <span class="text-secondary">Recommender</span>
                    <span class="text-info">System</span>
                </h1>

                <p class="mt-6 text-xl sm:text-2xl font-medium text-gray-700 max-w-3xl">
                    Sistem rekomendasi untuk proyek Web3 (cryptocurrency, token, DeFi) berbasis popularitas, tren investasi, dan analisis teknikal dengan dukungan penuh untuk periode indikator dinamis.
                </p>

                <div class="mt-10">
                    <a href="{{ route('login') }}" class="clay-button clay-button-success px-8 py-4 text-lg font-bold transform transition hover:translate-y-[-5px]">
                        <i class="fas fa-wallet mr-2"></i> Login dengan Crypto Wallet
                    </a>
                </div>
            </div>

            {{-- Latest Updates --}}
            <div class="clay-card p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block clay-badge clay-badge-danger p-2 text-lg">🚀 Update Terbaru (Juni 2025)</h2>
                <div class="mt-1 clay-card bg-warning/10 p-4">
                    <ul class="text-sm space-y-1">
                        <li>⚡ Enhanced Multi-Chain Blockchain Analytics</li>
                        <li>🔧 Critical Score Validation & Normalization</li>
                        <li>📊 Comprehensive USD Volume Calculation</li>
                        <li>🚀 Native Token Focus & Spam Detection</li>
                    </ul>
                </div>
            </div>

            <!-- System Overview -->
            <div class="clay-card p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block clay-badge clay-badge-primary p-2 text-lg">📋 Sistem Overview</h2>
                <p class="text-lg mb-6">
                    Sistem ini menggunakan data dari <strong>CoinGecko API</strong> untuk menyediakan rekomendasi proyek Web3 berdasarkan:
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
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
                        <p>View, favorite, portfolio add dengan bobot realistis</p>
                    </div>
                    <div class="clay-card bg-warning/10 p-6">
                        <p class="font-bold flex items-center text-lg mb-2"><i class="fas fa-tags mr-2"></i> Fitur Proyek</p>
                        <p>DeFi, GameFi, Layer-1, NFT, dll</p>
                    </div>
                    <div class="clay-card bg-info/10 p-6">
                        <p class="font-bold flex items-center text-lg mb-2"><i class="fas fa-chart-area mr-2"></i> Analisis Teknikal</p>
                        <p>RSI, MACD, Bollinger Bands dengan periode dinamis</p>
                    </div>
                    <div class="clay-card bg-danger/10 p-6">
                        <p class="font-bold flex items-center text-lg mb-2"><i class="fas fa-seedling mr-2"></i> Maturitas Proyek</p>
                        <p>Usia, aktivitas developer, engagement sosial</p>
                    </div>
                </div>

                <div class="clay-card bg-success/10 p-6">
                    <h3 class="text-xl font-bold mb-4">⚡ Multi-Chain Analytics (Update Terbaru)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="font-bold mb-2">Supported Chains:</p>
                            <div class="flex flex-wrap gap-2">
                                <span class="clay-badge clay-badge-primary text-xs">Ethereum (ETH)</span>
                                <span class="clay-badge clay-badge-warning text-xs">BSC (BNB)</span>
                                <span class="clay-badge clay-badge-secondary text-xs">Polygon (MATIC)</span>
                                <span class="clay-badge clay-badge-danger text-xs">Avalanche (AVAX)</span>
                            </div>
                        </div>
                        <div>
                            <p class="font-bold mb-2">Features:</p>
                            <ul class="text-sm space-y-1">
                                <li>🎯 Native-Focused Portfolio Analysis</li>
                                <li>💰 Comprehensive USD Volume Calculation</li>
                                <li>🛡️ Smart Spam Detection</li>
                                <li>🔗 Cross-Chain Transaction Analytics</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Model Implementation -->
            <div class="clay-card p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block clay-badge clay-badge-secondary p-2 text-lg">🤖 Implementasi Model</h2>

                <p class="text-lg mb-6">
                    Sistem mengimplementasikan tiga pendekatan rekomendasi dengan perbaikan signifikan:
                </p>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Feature-Enhanced CF -->
                    <div class="clay-card bg-warning/10 p-6">
                        <h3 class="text-xl font-bold mb-4 flex items-center">
                            <i class="fas fa-cogs mr-2"></i> Feature-Enhanced CF
                        </h3>
                        <p class="mb-4">Model berbasis scikit-learn SVD yang menggabungkan collaborative filtering dengan informasi fitur proyek.</p>
                        <ul class="space-y-2 text-sm">
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Sangat efektif untuk cold-start users</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Robust normalization (percentile-based)</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Score validation ketat [0,1]</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Fallback scoring system</li>
                        </ul>
                    </div>

                    <!-- Neural CF -->
                    <div class="clay-card bg-info/10 p-6">
                        <h3 class="text-xl font-bold mb-4 flex items-center">
                            <i class="fas fa-brain mr-2"></i> Neural CF
                        </h3>
                        <p class="mb-4">Model PyTorch dengan arsitektur CryptoNCFModel yang ditingkatkan untuk domain cryptocurrency.</p>
                        <ul class="space-y-2 text-sm">
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Dual-path Architecture (GMF + MLP)</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Residual connections & Layer normalization</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Enhanced negative sampling (category-aware)</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Optimal untuk 30+ interaksi per user</li>
                        </ul>
                    </div>

                    <!-- Hybrid Model -->
                    <div class="clay-card bg-secondary/10 p-6">
                        <h3 class="text-xl font-bold mb-4 flex items-center">
                            <i class="fas fa-layer-group mr-2"></i> Hybrid Model
                        </h3>
                        <p class="mb-4">Selective Ensemble dengan adaptive weighting berdasarkan jumlah interaksi pengguna.</p>
                        <ul class="space-y-2 text-sm">
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Confidence analysis & agreement detection</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Adaptive weighting (cold-start → NCF dominan)</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> IQR-based normalization</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Input & output validation</li>
                        </ul>
                    </div>
                </div>

                <!-- Adaptive Weighting Logic -->
                <div class="clay-card bg-primary/10 p-6">
                    <h3 class="text-xl font-bold mb-4">📊 Adaptive Weighting Logic</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                        <div class="clay-card bg-white p-3">
                            <p class="font-bold text-warning">< 10 interaksi</p>
                            <p>FECF 95%, NCF 5%</p>
                            <p class="text-xs text-gray-600">Cold start</p>
                        </div>
                        <div class="clay-card bg-white p-3">
                            <p class="font-bold text-info">10-20 interaksi</p>
                            <p>FECF 80%, NCF 20%</p>
                            <p class="text-xs text-gray-600">Low interactions</p>
                        </div>
                        <div class="clay-card bg-white p-3">
                            <p class="font-bold text-success">30-50 interaksi</p>
                            <p>FECF 50%, NCF 50%</p>
                            <p class="text-xs text-gray-600">Base weights</p>
                        </div>
                        <div class="clay-card bg-white p-3">
                            <p class="font-bold text-secondary">50-100 interaksi</p>
                            <p>FECF 45%, NCF 55%</p>
                            <p class="text-xs text-gray-600">NCF mulai unggul</p>
                        </div>
                        <div class="clay-card bg-white p-3">
                            <p class="font-bold text-danger">100+ interaksi</p>
                            <p>FECF 40%, NCF 60%</p>
                            <p class="text-xs text-gray-600">NCF dominan</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Latest Performance Results -->
            <div class="clay-card p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block clay-badge clay-badge-info p-2 text-lg">📊 Hasil Evaluasi Terbaru</h2>

                <div class="clay-card bg-success/10 p-4 mb-6">
                    <p class="font-bold text-success mb-2">🎉 Performa Optimal pada Min Interactions = 30 (19 Test Users)</p>
                    <p class="text-sm">Model hybrid menunjukkan performa terbaik dengan pengguna yang memiliki 30+ interaksi.</p>
                </div>

                <!-- Performance by Min Interactions -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Min Interactions = 30 -->
                    <div class="clay-card bg-warning/10 p-6">
                        <h3 class="text-lg font-bold mb-4">🏆 Min Interactions = 30 (Optimal)</h3>
                        <div class="overflow-x-auto">
                            <table class="clay-table min-w-full text-sm">
                                <thead>
                                    <tr>
                                        <th class="p-2 text-left">Model</th>
                                        <th class="p-2 text-left">Precision@10</th>
                                        <th class="p-2 text-left">Hit Ratio@10</th>
                                        <th class="p-2 text-left">MRR</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="p-2 font-medium">FECF</td>
                                        <td class="p-2">0.3368</td>
                                        <td class="p-2">0.8947</td>
                                        <td class="p-2">0.5005</td>
                                    </tr>
                                    <tr>
                                        <td class="p-2 font-medium">NCF</td>
                                        <td class="p-2">0.3526</td>
                                        <td class="p-2">0.8421</td>
                                        <td class="p-2">0.4888</td>
                                    </tr>
                                    <tr class="bg-success/10">
                                        <td class="p-2 font-bold">Hybrid</td>
                                        <td class="p-2 font-bold text-success">0.3842</td>
                                        <td class="p-2 font-bold text-success">0.8947</td>
                                        <td class="p-2 font-bold text-success">0.6365</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Min Interactions = 10 -->
                    <div class="clay-card bg-info/10 p-6">
                        <h3 class="text-lg font-bold mb-4">📈 Min Interactions = 10 (141 Test Users)</h3>
                        <div class="overflow-x-auto">
                            <table class="clay-table min-w-full text-sm">
                                <thead>
                                    <tr>
                                        <th class="p-2 text-left">Model</th>
                                        <th class="p-2 text-left">Precision@10</th>
                                        <th class="p-2 text-left">Hit Ratio@10</th>
                                        <th class="p-2 text-left">MRR</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="p-2 font-medium">FECF</td>
                                        <td class="p-2">0.2099</td>
                                        <td class="p-2">0.8085</td>
                                        <td class="p-2">0.5446</td>
                                    </tr>
                                    <tr>
                                        <td class="p-2 font-medium">NCF</td>
                                        <td class="p-2">0.1567</td>
                                        <td class="p-2">0.5780</td>
                                        <td class="p-2">0.3536</td>
                                    </tr>
                                    <tr class="bg-success/10">
                                        <td class="p-2 font-bold">Hybrid</td>
                                        <td class="p-2 font-bold text-success">0.2355</td>
                                        <td class="p-2 font-bold text-success">0.7730</td>
                                        <td class="p-2 font-bold text-success">0.4564</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Cold-Start Performance -->
                <div class="clay-card bg-secondary/10 p-6">
                    <h3 class="text-lg font-bold mb-4">❄️ Cold-Start Performance (5 runs average)</h3>
                    <div class="overflow-x-auto">
                        <table class="clay-table min-w-full text-sm">
                            <thead>
                                <tr>
                                    <th class="p-2 text-left">Model</th>
                                    <th class="p-2 text-left">Precision</th>
                                    <th class="p-2 text-left">Recall</th>
                                    <th class="p-2 text-left">Hit Ratio</th>
                                    <th class="p-2 text-left">NDCG</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="bg-success/10">
                                    <td class="p-2 font-bold">cold_start_fecf</td>
                                    <td class="p-2 font-bold text-success">0.1307±0.0154</td>
                                    <td class="p-2 font-bold text-success">0.4337±0.0511</td>
                                    <td class="p-2 font-bold text-success">0.6373±0.0472</td>
                                    <td class="p-2 font-bold text-success">0.3249±0.0305</td>
                                </tr>
                                <tr>
                                    <td class="p-2 font-medium">cold_start_hybrid</td>
                                    <td class="p-2">0.1176±0.0130</td>
                                    <td class="p-2">0.3899±0.0435</td>
                                    <td class="p-2">0.5371±0.0582</td>
                                    <td class="p-2">0.2604±0.0275</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-sm mt-3 text-gray-600">FECF masih unggul untuk cold-start scenarios karena kemampuan content-based filtering yang kuat.</p>
                </div>

                <!-- Key Insights -->
                <div class="mt-6 clay-card bg-primary/10 p-4">
                    <h4 class="font-bold mb-2">🔑 Key Insights:</h4>
                    <ul class="text-sm space-y-1">
                        <li>• <strong>Hybrid Model:</strong> Mengungguli semua model pada users dengan 30+ interaksi (MRR: 0.6365)</li>
                        <li>• <strong>NCF Performance:</strong> Meningkat drastis 125% dari min=10 ke min=30 interactions</li>
                        <li>• <strong>FECF Reliability:</strong> Konsisten untuk cold-start dengan Hit Ratio tinggi</li>
                        <li>• <strong>Data Sparsity:</strong> 98.68% sparsity menantang model, namun hybrid tetap optimal</li>
                    </ul>
                </div>
            </div>

            <!-- Technical Analysis Features -->
            <div class="clay-card p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block clay-badge clay-badge-success p-2 text-lg">📈 Analisis Teknikal Dinamis</h2>

                <p class="mb-6 text-lg">
                    Sistem mendukung analisis teknikal dengan periode indikator yang sepenuhnya dapat dikonfigurasi, termasuk deteksi market regime otomatis.
                </p>

                <!-- Market Regime Detection -->
                <div class="clay-card bg-warning/10 p-6 mb-6">
                    <h3 class="text-xl font-bold mb-4">🔍 Deteksi Market Regime Otomatis</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                        <div class="clay-card bg-success/20 p-3">
                            <p class="font-bold text-success">Trending Bullish</p>
                            <p>Tren naik dengan volatilitas normal</p>
                        </div>
                        <div class="clay-card bg-success/10 p-3">
                            <p class="font-bold text-success">Trending Bullish Volatile</p>
                            <p>Tren naik dengan volatilitas tinggi</p>
                        </div>
                        <div class="clay-card bg-danger/20 p-3">
                            <p class="font-bold text-danger">Trending Bearish</p>
                            <p>Tren turun dengan volatilitas normal</p>
                        </div>
                        <div class="clay-card bg-danger/10 p-3">
                            <p class="font-bold text-danger">Trending Bearish Volatile</p>
                            <p>Tren turun dengan volatilitas tinggi</p>
                        </div>
                        <div class="clay-card bg-secondary/10 p-3">
                            <p class="font-bold text-secondary">Ranging Low Volatility</p>
                            <p>Pasar sideways dengan volatilitas rendah</p>
                        </div>
                        <div class="clay-card bg-info/10 p-3">
                            <p class="font-bold text-info">Volatile Sideways</p>
                            <p>Volatilitas ekstrem tanpa arah jelas</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
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

                <!-- Supported Indicators -->
                <div class="clay-card bg-secondary/10 p-6">
                    <h3 class="text-xl font-bold mb-4">📊 Indikator Teknikal yang Didukung</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                        <div class="clay-card bg-primary/10 p-3">
                            <p class="font-bold text-primary">Indikator Tren</p>
                            <p>Moving Averages, MACD, ADX</p>
                        </div>
                        <div class="clay-card bg-warning/10 p-3">
                            <p class="font-bold text-warning">Indikator Momentum</p>
                            <p>RSI, Stochastic, CCI</p>
                        </div>
                        <div class="clay-card bg-info/10 p-3">
                            <p class="font-bold text-info">Indikator Volatilitas</p>
                            <p>Bollinger Bands, ATR</p>
                        </div>
                        <div class="clay-card bg-success/10 p-3">
                            <p class="font-bold text-success">Indikator Volume</p>
                            <p>OBV, MFI, Chaikin A/D</p>
                        </div>
                        <div class="clay-card bg-secondary/10 p-3">
                            <p class="font-bold text-secondary">Ichimoku Cloud</p>
                            <p>Pembentukan Pivot Points</p>
                        </div>
                        <div class="clay-card bg-danger/10 p-3">
                            <p class="font-bold text-danger">ML Prediction</p>
                            <p>LSTM, ARIMA, Simple Models</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Filtering System -->
            <div class="clay-card p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block clay-badge clay-badge-warning p-2 text-lg">🔍 Sistem Filtering yang Disempurnakan</h2>

                <p class="text-lg mb-6">
                    Sistem rekomendasi mendukung filtering rekomendasi yang canggih dengan berbagai tingkat kecocokan.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="clay-card bg-primary/10 p-6">
                        <h3 class="text-xl font-bold mb-4">🎯 Filter Multi-Dimensi</h3>
                        <ul class="space-y-2 text-sm">
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Kombinasi filter kategori dan chain</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Mode strict untuk filtering tanpa fallback</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Dukungan filter untuk semua model</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Fuzzy matching untuk kategori majemuk</li>
                        </ul>
                    </div>

                    <div class="clay-card bg-warning/10 p-6">
                        <h3 class="text-xl font-bold mb-4">📊 Filter Match Classification</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">exact</span>
                                <span class="clay-badge clay-badge-success text-xs">Cocok persis</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-medium">category_only</span>
                                <span class="clay-badge clay-badge-primary text-xs">Kategori saja</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-medium">chain_only</span>
                                <span class="clay-badge clay-badge-secondary text-xs">Chain saja</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-medium">chain_popular</span>
                                <span class="clay-badge clay-badge-info text-xs">Populer di chain</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-medium">fallback</span>
                                <span class="clay-badge clay-badge-warning text-xs">Cadangan</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Architecture -->
            <div class="clay-card p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block clay-badge clay-badge-info p-2 text-lg">🏗️ Arsitektur Sistem</h2>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Backend Architecture -->
                    <div class="clay-card bg-primary/10 p-6">
                        <h3 class="text-xl font-bold mb-4">🐍 Recommendation Engine (Python)</h3>
                        <ul class="space-y-2 text-sm">
                            <li class="flex items-center"><i class="fas fa-cog mr-2 text-primary"></i> FastAPI REST API</li>
                            <li class="flex items-center"><i class="fas fa-cog mr-2 text-primary"></i> PyTorch Neural Networks</li>
                            <li class="flex items-center"><i class="fas fa-cog mr-2 text-primary"></i> Scikit-learn SVD</li>
                            <li class="flex items-center"><i class="fas fa-cog mr-2 text-primary"></i> TA-Lib Technical Analysis</li>
                            <li class="flex items-center"><i class="fas fa-cog mr-2 text-primary"></i> CoinGecko API Integration</li>
                            <li class="flex items-center"><i class="fas fa-cog mr-2 text-primary"></i> PostgreSQL Database</li>
                        </ul>
                    </div>

                    <!-- Frontend Architecture -->
                    <div class="clay-card bg-secondary/10 p-6">
                        <h3 class="text-xl font-bold mb-4">🌐 Web Application (Laravel)</h3>
                        <ul class="space-y-2 text-sm">
                            <li class="flex items-center"><i class="fas fa-globe mr-2 text-secondary"></i> Laravel 12.x Framework</li>
                            <li class="flex items-center"><i class="fas fa-globe mr-2 text-secondary"></i> Web3 Wallet Authentication</li>
                            <li class="flex items-center"><i class="fas fa-globe mr-2 text-secondary"></i> Multi-Chain Portfolio Analytics</li>
                            <li class="flex items-center"><i class="fas fa-globe mr-2 text-secondary"></i> Transaction Management System</li>
                            <li class="flex items-center"><i class="fas fa-globe mr-2 text-secondary"></i> Alpine.js Reactivity</li>
                            <li class="flex items-center"><i class="fas fa-globe mr-2 text-secondary"></i> Tailwind CSS Styling</li>
                        </ul>
                    </div>
                </div>

                <!-- Data Flow -->
                <div class="clay-card bg-success/10 p-6 mt-6">
                    <h3 class="text-xl font-bold mb-4">🔄 Data Flow</h3>
                    <div class="flex flex-wrap items-center justify-center gap-4 text-sm">
                        <div class="clay-badge clay-badge-primary px-4 py-2">CoinGecko API</div>
                        <i class="fas fa-arrow-right text-primary"></i>
                        <div class="clay-badge clay-badge-warning px-4 py-2">Data Collection</div>
                        <i class="fas fa-arrow-right text-warning"></i>
                        <div class="clay-badge clay-badge-info px-4 py-2">Feature Engineering</div>
                        <i class="fas fa-arrow-right text-info"></i>
                        <div class="clay-badge clay-badge-success px-4 py-2">Model Training</div>
                        <i class="fas fa-arrow-right text-success"></i>
                        <div class="clay-badge clay-badge-secondary px-4 py-2">API Endpoints</div>
                        <i class="fas fa-arrow-right text-secondary"></i>
                        <div class="clay-badge clay-badge-danger px-4 py-2">Web Interface</div>
                    </div>
                </div>
            </div>

            <!-- API Documentation -->
            <div class="clay-card p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block clay-badge clay-badge-primary p-2 text-lg">🌐 API Documentation</h2>

                <p class="text-lg mb-6">
                    Sistem menyediakan RESTful API yang komprehensif dengan dukungan periode indikator dinamis.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Recommendation Endpoints -->
                    <div class="clay-card bg-primary/10 p-4">
                        <h3 class="text-lg font-bold mb-3 flex items-center"><i class="fas fa-star mr-2"></i>Recommendation</h3>
                        <ul class="space-y-2 text-sm font-mono">
                            <li class="clay-card bg-primary/20 p-2"><span class="font-bold text-primary">POST</span> /recommend/projects</li>
                            <li class="clay-card bg-primary/20 p-2"><span class="font-bold text-primary">GET</span> /recommend/trending</li>
                            <li class="clay-card bg-primary/20 p-2"><span class="font-bold text-primary">GET</span> /recommend/popular</li>
                            <li class="clay-card bg-primary/20 p-2"><span class="font-bold text-primary">GET</span> /recommend/similar/{id}</li>
                        </ul>
                    </div>

                    <!-- Analysis Endpoints -->
                    <div class="clay-card bg-secondary/10 p-4">
                        <h3 class="text-lg font-bold mb-3 flex items-center"><i class="fas fa-chart-line mr-2"></i>Technical Analysis</h3>
                        <ul class="space-y-2 text-sm font-mono">
                            <li class="clay-card bg-secondary/20 p-2"><span class="font-bold text-secondary">POST</span> /analysis/trading-signals</li>
                            <li class="clay-card bg-secondary/20 p-2"><span class="font-bold text-secondary">POST</span> /analysis/indicators</li>
                            <li class="clay-card bg-secondary/20 p-2"><span class="font-bold text-secondary">GET</span> /analysis/market-events/{id}</li>
                            <li class="clay-card bg-secondary/20 p-2"><span class="font-bold text-secondary">GET</span> /analysis/price-prediction/{id}</li>
                        </ul>
                    </div>

                    <!-- Blockchain Endpoints -->
                    <div class="clay-card bg-success/10 p-4">
                        <h3 class="text-lg font-bold mb-3 flex items-center"><i class="fas fa-link mr-2"></i>Blockchain</h3>
                        <ul class="space-y-2 text-sm font-mono">
                            <li class="clay-card bg-success/20 p-2"><span class="font-bold text-success">GET</span> /blockchain/portfolio/{wallet}</li>
                            <li class="clay-card bg-success/20 p-2"><span class="font-bold text-success">GET</span> /blockchain/transactions/{wallet}</li>
                            <li class="clay-card bg-success/20 p-2"><span class="font-bold text-success">GET</span> /blockchain/analytics/{wallet}</li>
                        </ul>
                    </div>
                </div>

                <!-- API Example -->
                <div class="clay-card bg-gray-900 text-green-400 p-6">
                    <h3 class="text-lg font-bold mb-4 text-white">📝 Example API Response</h3>
                    <pre class="font-mono text-xs overflow-x-auto whitespace-pre-wrap">{
  "user_id": "user_123",
  "model_type": "hybrid",
  "recommendations": [
    {
      "id": "bitcoin",
      "name": "Bitcoin",
      "symbol": "BTC",
      "current_price": 50000,
      "recommendation_score": 0.95,
      "filter_match": "exact",
      "primary_category": "layer-1",
      "chain": "bitcoin"
    }
  ],
  "is_cold_start": false,
  "exact_match_count": 10,
  "timestamp": "2025-06-23T10:30:00Z"
}</pre>
                </div>
            </div>

            <!-- Data Sparsity Challenge -->
            <div class="clay-card p-8 mb-12">
                <h2 class="text-3xl font-bold mb-6 inline-block clay-badge clay-badge-warning p-2 text-lg">⚡ Tantangan Data Sparsity</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="clay-card bg-danger/10 p-6">
                        <h3 class="text-xl font-bold mb-4 text-danger">📊 Data Statistics</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span>Total Interactions:</span>
                                <span class="font-bold">65,837</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total Users:</span>
                                <span class="font-bold">5,000</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total Items:</span>
                                <span class="font-bold">1,000</span>
                            </div>
                            <div class="flex justify-between border-t pt-2">
                                <span>Matrix Sparsity:</span>
                                <span class="font-bold text-danger">98.68%</span>
                            </div>
                            <p class="text-sm text-gray-600">Hanya 1.32% matriks yang terisi</p>
                        </div>
                    </div>

                    <div class="clay-card bg-success/10 p-6">
                        <h3 class="text-xl font-bold mb-4 text-success">🛠️ Solusi yang Diterapkan</h3>
                        <ul class="space-y-2 text-sm">
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Enhanced negative sampling (ratio 3:1)</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Content-based fallback untuk cold-start</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Adaptive weighting berdasarkan interaksi</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Stratified split untuk data imbalance</li>
                            <li class="flex items-center"><i class="fas fa-check-circle text-success mr-2"></i> Curriculum learning untuk NCF</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- CTA Section -->
            <div class="clay-card clay-card-lg p-8 sm:p-10 text-center mb-12">
                <h2 class="text-3xl font-bold mb-6">🚀 Mulai Eksplorasi Web3!</h2>
                <p class="text-xl mb-8">
                    Login dengan crypto wallet Anda dan dapatkan rekomendasi personal yang akurat berdasarkan AI terbaru,
                    analisis teknikal dinamis, dan blockchain analytics multi-chain.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                    <a href="{{ route('login') }}" class="clay-button clay-button-success px-8 py-4 text-xl font-bold transform transition hover:translate-y-[-5px]">
                        <i class="fas fa-wallet mr-2"></i> Hubungkan Wallet
                    </a>
                    <div class="clay-badge clay-badge-warning px-4 py-2 text-sm">
                        MetaMask, WalletConnect, dan lainnya
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center py-8">
                <div class="clay-badge clay-badge-info py-2 px-4 text-sm font-bold inline-block mb-4">
                    FINAL PROJECT - v3.1 (Juni 2025)
                </div>
                <p class="mt-4 font-medium">
                    <strong>Sistem Rekomendasi Crypto berbasis AI</strong><br>
                    Feature-Enhanced Collaborative Filtering & Neural Collaborative Filtering<br>
                    dengan Enhanced Multi-Chain Blockchain Analytics
                </p>
                <div class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-center items-center gap-4 flex-wrap">
                        <span class="clay-badge clay-badge-primary">Machine Learning</span>
                        <span class="clay-badge clay-badge-secondary">Deep Learning</span>
                        <span class="clay-badge clay-badge-success">Technical Analysis</span>
                        <span class="clay-badge clay-badge-warning">Multi-Chain</span>
                        <span class="clay-badge clay-badge-info">Web3</span>
                    </div>
                    <p class="mt-3">&copy; 2025 Crypto Recommender System</p>
                    <p class="text-xs text-gray-500">
                        Last Updated: Juni 2025 | Enhanced Multi-Chain Analytics & Score Validation
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

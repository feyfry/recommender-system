@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4">
    <!-- Header Section -->
    <div class="clay-card mb-8">
        <div class="flex flex-col md:flex-row items-center">
            <div class="md:w-2/3">
                <h1 class="text-3xl md:text-4xl font-bold mb-4">
                    <span class="clay-badge clay-badge-primary">Rekomendasi</span>
                    <span class="block mt-2">Cryptocurrency & Token</span>
                </h1>
                <p class="text-lg text-clay-text">
                    Dapatkan rekomendasi proyek Web3 berdasarkan popularitas, tren, dan preferensi personal Anda. Sistem rekomendasi kami menggunakan model hybrid yang menggabungkan collaborative filtering dan feature-based approach.
                </p>
            </div>
            <div class="md:w-1/3 mt-6 md:mt-0 flex justify-center">
                <div class="clay-rounded-full bg-clay-primary w-24 h-24 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-14 w-14 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Cards Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <a href="{{ route('panel.recommendations.personal') }}" class="clay-card p-6 hover:shadow-lg transition-all">
            <div class="flex items-center mb-4">
                <div class="clay-rounded-full bg-clay-primary w-12 h-12 flex items-center justify-center mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold">Personal</h2>
            </div>
            <p class="text-clay-text-light">
                Rekomendasi khusus untuk Anda berdasarkan interaksi dan preferensi Anda.
            </p>
        </a>

        <a href="{{ route('panel.recommendations.trending') }}" class="clay-card p-6 hover:shadow-lg transition-all">
            <div class="flex items-center mb-4">
                <div class="clay-rounded-full bg-clay-danger w-12 h-12 flex items-center justify-center mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold">Trending</h2>
            </div>
            <p class="text-clay-text-light">
                Proyek-proyek yang sedang populer dan memiliki momentum pasar.
            </p>
        </a>

        <a href="{{ route('panel.recommendations.popular') }}" class="clay-card p-6 hover:shadow-lg transition-all">
            <div class="flex items-center mb-4">
                <div class="clay-rounded-full bg-clay-warning w-12 h-12 flex items-center justify-center mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold">Popular</h2>
            </div>
            <p class="text-clay-text-light">
                Proyek dengan popularitas tinggi berdasarkan metrik sosial dan penggunaan.
            </p>
        </a>

        <a href="{{ route('panel.recommendations.categories') }}" class="clay-card p-6 hover:shadow-lg transition-all">
            <div class="flex items-center mb-4">
                <div class="clay-rounded-full bg-clay-success w-12 h-12 flex items-center justify-center mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold">Kategori</h2>
            </div>
            <p class="text-clay-text-light">
                Temukan proyek berdasarkan kategori seperti DeFi, NFT, GameFi, dll.
            </p>
        </a>
    </div>

    <!-- Personal Recommendations Preview -->
    <div class="clay-card mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-clay-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                Untuk Anda
            </h2>
            <a href="{{ route('panel.recommendations.personal') }}" class="clay-button">
                Lihat Semua
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @forelse($personalRecommendations ?? [] as $index => $recommendation)
                @if($index < 4)
                <div class="clay-card-sm hover:shadow-lg transition-all">
                    <div class="font-bold text-lg mb-2">{{ $recommendation->name ?? $recommendation['name'] }} ({{ $recommendation->symbol ?? $recommendation['symbol'] }})</div>
                    <div class="text-sm mb-2">
                        {{ $recommendation->formatted_price ?? '$'.number_format($recommendation->price_usd ?? $recommendation['price_usd'], 2) }}
                        <span class="{{ ($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0) > 0 ? 'text-clay-success' : 'text-clay-danger' }}">
                            {{ ($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0) > 0 ? '+' : '' }}
                            {{ number_format($recommendation->price_change_percentage_24h ?? $recommendation['price_change_percentage_24h'] ?? 0, 2) }}%
                        </span>
                    </div>
                    <div class="clay-badge inline-block mb-3">
                        {{ $recommendation->primary_category ?? $recommendation['primary_category'] ?? 'Umum' }}
                    </div>
                    <p class="text-sm mb-3 line-clamp-2 text-clay-text-light">
                        {{ $recommendation->description ?? $recommendation['description'] ?? 'Tidak ada deskripsi' }}
                    </p>
                    <div class="flex justify-between items-center">
                        <div class="text-xs font-medium">Score: <span class="text-clay-primary">
                            {{ number_format($recommendation->recommendation_score ?? $recommendation['recommendation_score'] ?? 0, 2) }}
                        </span></div>
                        <a href="{{ route('panel.recommendations.project', $recommendation->id ?? $recommendation['id']) }}" class="clay-button">
                            Detail
                        </a>
                    </div>
                </div>
                @endif
            @empty
                <div class="col-span-full clay-inset p-6 text-center">
                    <p>Tidak ada rekomendasi personal yang tersedia saat ini.</p>
                    <p class="text-sm mt-2 text-clay-text-light">Mulai berinteraksi dengan proyek untuk mendapatkan rekomendasi yang lebih baik.</p>
                    <a href="{{ route('panel.recommendations.trending') }}" class="clay-button clay-button-primary mt-4">Lihat Trending</a>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Trending Projects Preview -->
    <div class="clay-card mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-clay-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
                Trending Projects
            </h2>
            <a href="{{ route('panel.recommendations.trending') }}" class="clay-button">
                Lihat Semua
            </a>
        </div>

        <div class="overflow-x-auto">
            <div class="clay-inset p-1">
                <table class="clay-table">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 text-left">#</th>
                            <th class="py-2 px-4 text-left">Project</th>
                            <th class="py-2 px-4 text-left">Harga</th>
                            <th class="py-2 px-4 text-left">24h %</th>
                            <th class="py-2 px-4 text-left">7d %</th>
                            <th class="py-2 px-4 text-left">Trend Score</th>
                            <th class="py-2 px-4 text-left">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($trendingProjects ?? [] as $index => $project)
                            @if($index < 5)
                            <tr>
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
                                <td class="py-2 px-4 {{ $project->price_change_percentage_24h > 0 ? 'text-clay-success' : 'text-clay-danger' }}">
                                    {{ $project->price_change_percentage_24h > 0 ? '+' : '' }}{{ number_format($project->price_change_percentage_24h, 2) }}%
                                </td>
                                <td class="py-2 px-4 {{ $project->price_change_percentage_7d > 0 ? 'text-clay-success' : 'text-clay-danger' }}">
                                    {{ $project->price_change_percentage_7d > 0 ? '+' : '' }}{{ number_format($project->price_change_percentage_7d, 2) }}%
                                </td>
                                <td class="py-2 px-4">
                                    <div class="flex items-center">
                                        <div class="w-16 h-2 clay-inset overflow-hidden rounded-full mr-2">
                                            <div class="h-full bg-clay-danger" style="width: {{ $project->trend_score }}%;"></div>
                                        </div>
                                        <span>{{ number_format($project->trend_score, 1) }}</span>
                                    </div>
                                </td>
                                <td class="py-2 px-4">
                                    <a href="{{ route('panel.recommendations.project', $project->id) }}" class="clay-button">
                                        Detail
                                    </a>
                                </td>
                            </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="7" class="py-6 px-4 text-center text-clay-text-light">Tidak ada data proyek trending</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Interactions -->
    <div class="clay-card mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-clay-info" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                Interaksi Terbaru
            </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @forelse($interactions ?? [] as $index => $interaction)
                @if($index < 6)
                <div class="clay-card-sm flex items-center">
                    <div class="mr-3 flex-shrink-0">
                        @if($interaction->interaction_type == 'view')
                            <div class="clay-rounded-full bg-clay-info w-10 h-10 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </div>
                        @elseif($interaction->interaction_type == 'favorite')
                            <div class="clay-rounded-full bg-clay-danger w-10 h-10 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                            </div>
                        @elseif($interaction->interaction_type == 'portfolio_add')
                            <div class="clay-rounded-full bg-clay-success w-10 h-10 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2h-2m-4-1v8m0 0l3-3m-3 3l-3-3" />
                                </svg>
                            </div>
                        @else
                            <div class="clay-rounded-full bg-clay-warning w-10 h-10 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        @endif
                    </div>
                    <div class="flex-grow min-w-0">
                        <div class="font-medium truncate">
                            {{ $interaction->project->name }} ({{ $interaction->project->symbol }})
                        </div>
                        <div class="text-xs text-clay-text-light">
                            {{ $interaction->created_at->diffForHumans() }}
                        </div>
                    </div>
                    <div class="flex-shrink-0 ml-2">
                        <a href="{{ route('panel.recommendations.project', $interaction->project_id) }}" class="clay-button">
                            Detail
                        </a>
                    </div>
                </div>
                @endif
            @empty
                <div class="col-span-full clay-inset p-6 text-center">
                    <p>Belum ada interaksi yang tercatat.</p>
                    <p class="text-sm mt-2 text-clay-text-light">Mulai berinteraksi dengan proyek untuk mendapatkan riwayat aktivitas Anda.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Additional Info -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <!-- Model Info -->
        <div class="clay-card">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-clay-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                </svg>
                Sistem Rekomendasi
            </h2>
            <div class="clay-inset p-4 mb-4">
                <p class="text-sm mb-2">Sistem rekomendasi kami menggunakan model hybrid yang menggabungkan:</p>
                <ul class="text-sm space-y-1 pl-5 list-disc">
                    <li><span class="font-medium">Feature-Enhanced CF:</span> Berbasis SVD untuk menangani cold-start</li>
                    <li><span class="font-medium">Neural CF:</span> Deep learning untuk menangkap pola interaksi kompleks</li>
                    <li><span class="font-medium">Enhanced Hybrid Model:</span> Teknik ensemble canggih</li>
                </ul>
            </div>
            <div class="clay-card-sm p-3">
                <div class="text-sm font-medium mb-1">Performa Model Hybrid:</div>
                <div class="grid grid-cols-3 gap-2 text-xs">
                    <div>Precision: <span class="font-medium">0.1461</span></div>
                    <div>Recall: <span class="font-medium">0.4045</span></div>
                    <div>Hit Ratio: <span class="font-medium">0.8788</span></div>
                </div>
            </div>
        </div>

        <!-- Technical Analysis Info -->
        <div class="clay-card">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-clay-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Analisis Teknikal
            </h2>
            <div class="clay-inset p-4 mb-4">
                <p class="text-sm mb-2">Fitur analisis teknikal dengan periode indikator yang dapat dikonfigurasi:</p>
                <div class="grid grid-cols-3 gap-2 text-sm">
                    <div class="clay-card-sm p-2 text-center">
                        <div class="font-medium mb-1">Short-Term</div>
                        <div class="text-xs text-clay-text-light">RSI: 7, MACD: 8-17-9</div>
                    </div>
                    <div class="clay-card-sm p-2 text-center">
                        <div class="font-medium mb-1">Standard</div>
                        <div class="text-xs text-clay-text-light">RSI: 14, MACD: 12-26-9</div>
                    </div>
                    <div class="clay-card-sm p-2 text-center">
                        <div class="font-medium mb-1">Long-Term</div>
                        <div class="text-xs text-clay-text-light">RSI: 21, MACD: 19-39-9</div>
                    </div>
                </div>
            </div>
            <div class="clay-alert clay-alert-info text-sm flex items-start">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    Lihat halaman detail proyek untuk mendapatkan analisis teknikal dan sinyal trading sesuai dengan toleransi risiko Anda.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

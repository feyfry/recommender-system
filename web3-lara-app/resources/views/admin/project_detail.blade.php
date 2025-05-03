@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
            <div>
                <h1 class="text-3xl font-bold mb-3 flex items-center">
                    <div class="bg-success/20 p-2 clay-badge mr-3">
                        <i class="fas fa-project-diagram text-success"></i>
                    </div>
                    Detail Proyek
                </h1>
                <p class="text-lg">
                    Informasi lengkap tentang proyek dan aktivitasnya.
                </p>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="{{ route('admin.projects') }}" class="clay-button clay-button-info">
                    <i class="fas fa-arrow-left mr-2"></i> Kembali ke Daftar
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Project Info -->
        <div class="lg:col-span-1">
            <div class="clay-card p-6 mb-6">
                <h2 class="text-xl font-bold mb-6 flex items-center">
                    <i class="fas fa-info-circle mr-2 text-primary"></i>
                    Informasi Proyek
                </h2>

                <div class="flex justify-center mb-6">
                    <div class="w-24 h-24 rounded-full overflow-hidden">
                        @if($project->image)
                            <img src="{{ $project->image }}" alt="{{ $project->name }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full bg-gray-200 flex items-center justify-center">
                                <i class="fas fa-coin text-4xl text-gray-400"></i>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm text-gray-600">Nama</label>
                        <div class="font-medium">{{ $project->name }}</div>
                    </div>

                    <div>
                        <label class="block text-sm text-gray-600">Simbol</label>
                        <div class="font-medium">{{ $project->symbol }}</div>
                    </div>

                    <div>
                        <label class="block text-sm text-gray-600">ID</label>
                        <div class="font-mono text-xs break-all">{{ $project->id }}</div>
                    </div>

                    <div>
                        <label class="block text-sm text-gray-600">Kategori</label>
                        <div class="font-medium">
                            <span class="clay-badge clay-badge-primary py-1 px-2">{{ $project->primary_category ?? 'Tidak Diketahui' }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-gray-600">Blockchain</label>
                        <div class="font-medium">
                            <span class="clay-badge clay-badge-secondary py-1 px-2">{{ $project->chain ?? 'Tidak Diketahui' }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-gray-600">Harga Saat Ini</label>
                        <div class="font-medium">{{ $project->formatted_price }}</div>
                    </div>

                    <div>
                        <label class="block text-sm text-gray-600">Persentase Perubahan 24 Jam</label>
                        <div class="font-medium {{ $project->price_change_percentage_24h >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $project->formatted_price_change }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-gray-600">Market Cap</label>
                        <div class="font-medium">{{ $project->formatted_market_cap }}</div>
                    </div>

                    <div>
                        <label class="block text-sm text-gray-600">Popularity Score</label>
                        <div class="flex items-center">
                            <span class="font-medium mr-2">{{ number_format($project->popularity_score, 1) }}</span>
                            <div class="clay-progress w-32 h-2">
                                <div class="clay-progress-bar clay-progress-primary" style="width: {{ min(100, $project->popularity_score) }}%"></div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-gray-600">Trend Score</label>
                        <div class="flex items-center">
                            <span class="font-medium mr-2">{{ number_format($project->trend_score, 1) }}</span>
                            <div class="clay-progress w-32 h-2">
                                <div class="clay-progress-bar clay-progress-warning" style="width: {{ min(100, $project->trend_score) }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="clay-card p-6">
                <h2 class="text-lg font-bold mb-4 flex items-center">
                    <i class="fas fa-chart-line mr-2 text-secondary"></i>
                    Sinyal Trading
                </h2>

                @if(!empty($tradingSignals))
                <div class="space-y-4">
                    <div class="clay-card bg-{{ $tradingSignals['action'] == 'buy' ? 'success' : ($tradingSignals['action'] == 'sell' ? 'danger' : 'warning') }}/10 p-4">
                        <div class="font-bold text-xl mb-2 flex items-center">
                            @if($tradingSignals['action'] == 'buy')
                                <i class="fas fa-arrow-up text-success mr-2"></i> BELI
                            @elseif($tradingSignals['action'] == 'sell')
                                <i class="fas fa-arrow-down text-danger mr-2"></i> JUAL
                            @else
                                <i class="fas fa-minus text-warning mr-2"></i> HOLD
                            @endif
                        </div>

                        <div class="text-sm mb-3">
                            <span class="font-medium">Confidence Score:</span>
                            <div class="clay-progress mt-1">
                                <div class="clay-progress-bar clay-progress-{{ $tradingSignals['action'] == 'buy' ? 'success' : ($tradingSignals['action'] == 'sell' ? 'danger' : 'warning') }}" style="width: {{ $tradingSignals['confidence'] * 100 }}%"></div>
                            </div>
                            <div class="text-right">{{ number_format($tradingSignals['confidence'] * 100, 0) }}%</div>
                        </div>

                        @if(!empty($tradingSignals['evidence']))
                        <div class="mt-4">
                            <p class="font-medium">Evidence:</p>
                            <ul class="list-disc list-inside text-sm mt-2 space-y-1">
                                @foreach($tradingSignals['evidence'] as $evidence)
                                <li>{{ $evidence }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endif

                        @if(!empty($tradingSignals['target_price']))
                        <div class="mt-4 font-medium">
                            Target Price: ${{ number_format($tradingSignals['target_price'], 2) }}
                        </div>
                        @endif
                    </div>
                </div>
                @else
                <div class="text-center py-6 text-gray-500">
                    <p>Tidak ada data sinyal trading tersedia.</p>
                </div>
                @endif
            </div>
        </div>

        <!-- Project Activities -->
        <div class="lg:col-span-2">
            <!-- Tabs Navigation -->
            <div x-data="{ activeTab: 'interactions' }">
                <!-- Tab Headers -->
                <div class="clay-tabs mb-6">
                    <button @click="activeTab = 'interactions'" :class="{ 'active': activeTab === 'interactions' }" class="clay-tab">
                        <i class="fas fa-exchange-alt mr-2"></i> Interaksi
                    </button>
                    <button @click="activeTab = 'recommendations'" :class="{ 'active': activeTab === 'recommendations' }" class="clay-tab">
                        <i class="fas fa-star mr-2"></i> Rekomendasi
                    </button>
                    <button @click="activeTab = 'portfolios'" :class="{ 'active': activeTab === 'portfolios' }" class="clay-tab">
                        <i class="fas fa-wallet mr-2"></i> Portfolio
                    </button>
                    <button @click="activeTab = 'transactions'" :class="{ 'active': activeTab === 'transactions' }" class="clay-tab">
                        <i class="fas fa-money-bill-wave mr-2"></i> Transaksi
                    </button>
                    <button @click="activeTab = 'description'" :class="{ 'active': activeTab === 'description' }" class="clay-tab">
                        <i class="fas fa-file-alt mr-2"></i> Deskripsi
                    </button>
                </div>

                <!-- Tab Contents -->
                <!-- Interactions Tab -->
                <div x-show="activeTab === 'interactions'" class="clay-card p-6 mb-6">
                    <h3 class="text-xl font-bold mb-4">Interaksi Pengguna</h3>

                    <!-- Interaction Statistics -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        @php
                            $viewCount = 0;
                            $favoriteCount = 0;
                            $portfolioCount = 0;
                            $otherCount = 0;

                            foreach($interactionStats ?? [] as $stat) {
                                if($stat->interaction_type == 'view') {
                                    $viewCount = $stat->count;
                                } elseif($stat->interaction_type == 'favorite') {
                                    $favoriteCount = $stat->count;
                                } elseif($stat->interaction_type == 'portfolio_add') {
                                    $portfolioCount = $stat->count;
                                } else {
                                    $otherCount += $stat->count;
                                }
                            }

                            $totalInteractions = $viewCount + $favoriteCount + $portfolioCount + $otherCount;
                        @endphp

                        <div class="clay-card bg-primary/10 p-3 text-center">
                            <div class="text-2xl font-bold">{{ $totalInteractions }}</div>
                            <div class="text-sm">Total Interaksi</div>
                        </div>

                        <div class="clay-card bg-info/10 p-3 text-center">
                            <div class="text-2xl font-bold">{{ $viewCount }}</div>
                            <div class="text-sm">View</div>
                        </div>

                        <div class="clay-card bg-secondary/10 p-3 text-center">
                            <div class="text-2xl font-bold">{{ $favoriteCount }}</div>
                            <div class="text-sm">Favorite</div>
                        </div>

                        <div class="clay-card bg-success/10 p-3 text-center">
                            <div class="text-2xl font-bold">{{ $portfolioCount }}</div>
                            <div class="text-sm">Portfolio Add</div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="clay-table min-w-full">
                            <thead>
                                <tr>
                                    <th class="py-2 px-4 text-left">Pengguna</th>
                                    <th class="py-2 px-4 text-left">Tipe Interaksi</th>
                                    <th class="py-2 px-4 text-left">Waktu</th>
                                    <th class="py-2 px-4 text-left">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($interactions ?? [] as $interaction)
                                <tr>
                                    <td class="py-2 px-4">
                                        <div class="flex items-center">
                                            @if($interaction->user->profile && $interaction->user->profile->avatar_url)
                                                <img src="{{ asset($interaction->user->profile->avatar_url) }}" alt="{{ $interaction->user->profile->username ?? $interaction->user->user_id }}" class="w-6 h-6 rounded-full mr-2">
                                            @endif
                                            <span class="font-medium">{{ $interaction->user->profile->username ?? substr($interaction->user->user_id, 0, 10) . '...' }}</span>
                                        </div>
                                    </td>
                                    <td class="py-2 px-4">
                                        @if($interaction->interaction_type == 'view')
                                            <span class="clay-badge clay-badge-info">View</span>
                                        @elseif($interaction->interaction_type == 'favorite')
                                            <span class="clay-badge clay-badge-secondary">Favorite</span>
                                        @elseif($interaction->interaction_type == 'portfolio_add')
                                            <span class="clay-badge clay-badge-success">Portfolio</span>
                                        @else
                                            <span class="clay-badge clay-badge-warning">{{ $interaction->interaction_type }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-4 text-gray-500 text-sm">{{ $interaction->created_at->diffForHumans() }}</td>
                                    <td class="py-2 px-4">
                                        <a href="{{ route('admin.users.detail', $interaction->user->user_id) }}" class="clay-badge clay-badge-info py-1 px-2 text-xs">
                                            <i class="fas fa-user"></i> Detail User
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="py-4 px-4 text-center text-gray-500">Belum ada interaksi untuk proyek ini.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recommendations Tab -->
                <div x-show="activeTab === 'recommendations'" class="clay-card p-6 mb-6">
                    <h3 class="text-xl font-bold mb-4">Rekomendasi</h3>
                    <div class="overflow-x-auto">
                        <table class="clay-table min-w-full">
                            <thead>
                                <tr>
                                    <th class="py-2 px-4 text-left">Pengguna</th>
                                    <th class="py-2 px-4 text-left">Tipe Rekomendasi</th>
                                    <th class="py-2 px-4 text-left">Score</th>
                                    <th class="py-2 px-4 text-left">Rank</th>
                                    <th class="py-2 px-4 text-left">Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recommendations ?? [] as $recommendation)
                                <tr>
                                    <td class="py-2 px-4">
                                        <div class="flex items-center">
                                            @if($recommendation->user->profile && $recommendation->user->profile->avatar_url)
                                                <img src="{{ asset($recommendation->user->profile->avatar_url) }}" alt="{{ $recommendation->user->profile->username ?? $recommendation->user->user_id }}" class="w-6 h-6 rounded-full mr-2">
                                            @endif
                                            <span class="font-medium">{{ $recommendation->user->profile->username ?? substr($recommendation->user->user_id, 0, 10) . '...' }}</span>
                                        </div>
                                    </td>
                                    <td class="py-2 px-4">
                                        <span class="clay-badge clay-badge-primary">{{ $recommendation->recommendation_type }}</span>
                                    </td>
                                    <td class="py-2 px-4 font-medium">{{ number_format($recommendation->score, 4) }}</td>
                                    <td class="py-2 px-4">{{ $recommendation->rank }}</td>
                                    <td class="py-2 px-4 text-gray-500 text-sm">{{ $recommendation->created_at->format('j M Y H:i') }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="py-4 px-4 text-center text-gray-500">Belum ada rekomendasi untuk proyek ini.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Portfolios Tab -->
                <div x-show="activeTab === 'portfolios'" class="clay-card p-6 mb-6">
                    <h3 class="text-xl font-bold mb-4">Portfolio Pengguna</h3>
                    <div class="overflow-x-auto">
                        <table class="clay-table min-w-full">
                            <thead>
                                <tr>
                                    <th class="py-2 px-4 text-left">Pengguna</th>
                                    <th class="py-2 px-4 text-left">Jumlah</th>
                                    <th class="py-2 px-4 text-left">Harga Avg.</th>
                                    <th class="py-2 px-4 text-left">Nilai Total</th>
                                    <th class="py-2 px-4 text-left">Profit/Loss</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($portfolios ?? [] as $portfolio)
                                <tr>
                                    <td class="py-2 px-4">
                                        <div class="flex items-center">
                                            @if($portfolio->user->profile && $portfolio->user->profile->avatar_url)
                                                <img src="{{ asset($portfolio->user->profile->avatar_url) }}" alt="{{ $portfolio->user->profile->username ?? $portfolio->user->user_id }}" class="w-6 h-6 rounded-full mr-2">
                                            @endif
                                            <span class="font-medium">{{ $portfolio->user->profile->username ?? substr($portfolio->user->user_id, 0, 10) . '...' }}</span>
                                        </div>
                                    </td>
                                    <td class="py-2 px-4 font-medium">{{ number_format($portfolio->amount, 6) }}</td>
                                    <td class="py-2 px-4">${{ number_format($portfolio->average_buy_price, 2) }}</td>
                                    <td class="py-2 px-4 font-medium">${{ number_format($portfolio->current_value, 2) }}</td>
                                    <td class="py-2 px-4 {{ $portfolio->profit_loss_value >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $portfolio->profit_loss_value >= 0 ? '+' : '' }}${{ number_format($portfolio->profit_loss_value, 2) }}
                                        <span class="text-xs">({{ number_format($portfolio->profit_loss_percentage, 2) }}%)</span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="py-4 px-4 text-center text-gray-500">Belum ada portfolio yang berisi proyek ini.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Transactions Tab -->
                <div x-show="activeTab === 'transactions'" class="clay-card p-6 mb-6">
                    <h3 class="text-xl font-bold mb-4">Transaksi</h3>
                    <div class="overflow-x-auto">
                        <table class="clay-table min-w-full">
                            <thead>
                                <tr>
                                    <th class="py-2 px-4 text-left">Pengguna</th>
                                    <th class="py-2 px-4 text-left">Tipe</th>
                                    <th class="py-2 px-4 text-left">Jumlah</th>
                                    <th class="py-2 px-4 text-left">Harga</th>
                                    <th class="py-2 px-4 text-left">Total Nilai</th>
                                    <th class="py-2 px-4 text-left">Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($transactions ?? [] as $transaction)
                                <tr>
                                    <td class="py-2 px-4">
                                        <div class="flex items-center">
                                            @if($transaction->user->profile && $transaction->user->profile->avatar_url)
                                                <img src="{{ asset($transaction->user->profile->avatar_url) }}" alt="{{ $transaction->user->profile->username ?? $transaction->user->user_id }}" class="w-6 h-6 rounded-full mr-2">
                                            @endif
                                            <span class="font-medium">{{ $transaction->user->profile->username ?? substr($transaction->user->user_id, 0, 10) . '...' }}</span>
                                        </div>
                                    </td>
                                    <td class="py-2 px-4">
                                        @if($transaction->transaction_type == 'buy')
                                            <span class="clay-badge clay-badge-success">Buy</span>
                                        @else
                                            <span class="clay-badge clay-badge-danger">Sell</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-4 font-medium">{{ number_format($transaction->amount, 6) }}</td>
                                    <td class="py-2 px-4">${{ number_format($transaction->price, 2) }}</td>
                                    <td class="py-2 px-4 font-medium">${{ number_format($transaction->total_value, 2) }}</td>
                                    <td class="py-2 px-4 text-gray-500 text-sm">{{ $transaction->created_at->format('j M Y H:i') }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="py-4 px-4 text-center text-gray-500">Belum ada transaksi untuk proyek ini.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Description Tab -->
                <div x-show="activeTab === 'description'" class="clay-card p-6 mb-6">
                    <h3 class="text-xl font-bold mb-4">Deskripsi Proyek</h3>

                    @if($project->description)
                        <div class="prose max-w-none">
                            {{ $project->description }}
                        </div>
                    @else
                        <div class="text-center py-6 text-gray-500">
                            <p>Tidak ada deskripsi untuk proyek ini.</p>
                        </div>
                    @endif

                    @if(!empty($project->categories))
                    <div class="mt-6">
                        <h4 class="font-bold mb-2">Kategori:</h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($project->categories as $category)
                                <span class="clay-badge clay-badge-primary">{{ $category }}</span>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @if(!empty($project->platforms))
                    <div class="mt-6">
                        <h4 class="font-bold mb-2">Platform:</h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($project->platforms as $platform => $address)
                                <span class="clay-badge clay-badge-secondary">{{ $platform }}</span>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div class="clay-card bg-info/10 p-4">
                            <h4 class="font-bold mb-2">Info Pasar:</h4>
                            <div class="space-y-2 text-sm">
                                @if($project->market_cap)
                                <div class="flex justify-between">
                                    <span>Market Cap:</span>
                                    <span class="font-medium">{{ $project->formatted_market_cap }}</span>
                                </div>
                                @endif

                                @if($project->total_volume)
                                <div class="flex justify-between">
                                    <span>Volume 24h:</span>
                                    <span class="font-medium">${{ number_format($project->total_volume, 0) }}</span>
                                </div>
                                @endif

                                @if($project->circulating_supply)
                                <div class="flex justify-between">
                                    <span>Circulating Supply:</span>
                                    <span class="font-medium">{{ number_format($project->circulating_supply, 0) }} {{ $project->symbol }}</span>
                                </div>
                                @endif

                                @if($project->total_supply)
                                <div class="flex justify-between">
                                    <span>Total Supply:</span>
                                    <span class="font-medium">{{ number_format($project->total_supply, 0) }} {{ $project->symbol }}</span>
                                </div>
                                @endif

                                @if($project->max_supply)
                                <div class="flex justify-between">
                                    <span>Max Supply:</span>
                                    <span class="font-medium">{{ number_format($project->max_supply, 0) }} {{ $project->symbol }}</span>
                                </div>
                                @endif
                            </div>
                        </div>

                        <div class="clay-card bg-secondary/10 p-4">
                            <h4 class="font-bold mb-2">Metrik Sosial:</h4>
                            <div class="space-y-2 text-sm">
                                @if($project->social_engagement_score)
                                <div class="flex justify-between">
                                    <span>Engagement Score:</span>
                                    <span class="font-medium">{{ number_format($project->social_engagement_score, 1) }}</span>
                                </div>
                                @endif

                                @if($project->developer_activity_score)
                                <div class="flex justify-between">
                                    <span>Developer Activity:</span>
                                    <span class="font-medium">{{ number_format($project->developer_activity_score, 1) }}</span>
                                </div>
                                @endif

                                @if($project->twitter_followers)
                                <div class="flex justify-between">
                                    <span>Twitter Followers:</span>
                                    <span class="font-medium">{{ number_format($project->twitter_followers, 0) }}</span>
                                </div>
                                @endif

                                @if($project->telegram_channel_user_count)
                                <div class="flex justify-between">
                                    <span>Telegram Members:</span>
                                    <span class="font-medium">{{ number_format($project->telegram_channel_user_count, 0) }}</span>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-bolt mr-2 text-warning"></i>
            Aksi Cepat
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="{{ route('admin.data-sync') }}?project_id={{ $project->id }}" class="clay-card p-4 bg-success/10 hover:translate-y-[-5px] transition-transform text-center">
                <i class="fas fa-sync text-3xl text-success mb-2"></i>
                <div class="font-bold">Sinkronisasi Data</div>
                <p class="text-sm mt-1">Perbarui data proyek dari API</p>
            </a>

            <a href="{{ route('panel.recommendations.project', $project->id) }}" class="clay-card p-4 bg-info/10 hover:translate-y-[-5px] transition-transform text-center">
                <i class="fas fa-eye text-3xl text-info mb-2"></i>
                <div class="font-bold">Lihat di Frontend</div>
                <p class="text-sm mt-1">Lihat tampilan proyek di halaman publik</p>
            </a>

            <a href="{{ route('admin.train-models') }}?project_id={{ $project->id }}" class="clay-card p-4 bg-warning/10 hover:translate-y-[-5px] transition-transform text-center">
                <i class="fas fa-brain text-3xl text-warning mb-2"></i>
                <div class="font-bold">Latih Model</div>
                <p class="text-sm mt-1">Latih model rekomendasi dengan data terbaru</p>
            </a>
        </div>
    </div>
</div>
@endsection

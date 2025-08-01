@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
            <div>
                <h1 class="text-3xl font-bold mb-3 flex items-center">
                    <div class="bg-primary/20 p-2 clay-badge mr-3">
                        <i class="fas fa-user text-primary"></i>
                    </div>
                    Detail Pengguna
                </h1>
                <p class="text-lg">
                    Informasi lengkap tentang pengguna dan aktivitasnya.
                </p>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="{{ route('admin.users') }}" class="clay-button clay-button-info">
                    <i class="fas fa-arrow-left mr-2"></i> Kembali ke Daftar
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- User Profile Info -->
        <div class="lg:col-span-1">
            <div class="clay-card p-6 mb-6">
                <h2 class="text-xl font-bold mb-6 flex items-center">
                    <i class="fas fa-id-card mr-2 text-primary"></i>
                    Profil Pengguna
                </h2>

                <div class="flex justify-center mb-6">
                    <div class="clay-avatar clay-avatar-lg">
                        @if($user->profile && $user->profile->avatar_url)
                            <img src="{{ asset($user->profile->avatar_url) }}" alt="Avatar" class="w-full h-full object-cover">
                        @else
                            <i class="fas fa-user text-4xl"></i>
                        @endif
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label for="username" class="block text-sm text-gray-600">Username</label>
                        <div class="font-medium">{{ $user->profile?->username ?? 'Belum diatur' }}</div>
                    </div>

                    <div>
                        <label for="user-id" class="block text-sm text-gray-600">User ID</label>
                        <div class="font-medium">{{ $user->user_id }}</div>
                    </div>

                    <div>
                        <label for="wallet-address" class="block text-sm text-gray-600">Wallet Address</label>
                        <div class="font-mono text-xs break-all">{{ $user->wallet_address }}</div>
                    </div>

                    <div>
                        <label for="role" class="block text-sm text-gray-600">Peran</label>
                        <div class="flex justify-between items-center">
                            @if($user->role == 'admin')
                                <span class="clay-badge clay-badge-success py-1 px-2">Admin</span>
                            @elseif($user->role == 'community')
                                <span class="clay-badge clay-badge-warning py-1 px-2">Community</span>
                            @else
                                <span class="clay-badge clay-badge-secondary py-1 px-2">{{ $user->role }}</span>
                            @endif
                            <button type="button" class="clay-button clay-button-info py-1 px-2 text-xs" onclick="openEditRoleModal('{{ $user->user_id }}', '{{ $user->role }}')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </div>
                    </div>

                    <div>
                        <label for="created-at" class="block text-sm text-gray-600">Tanggal Registrasi</label>
                        <div class="font-medium">{{ $user->created_at->format('j F Y H:i') }}</div>
                    </div>

                    <div>
                        <label for="last-login" class="block text-sm text-gray-600">Login Terakhir</label>
                        <div class="font-medium">{{ $user->last_login ? $user->last_login->format('j F Y H:i') : 'Belum pernah' }}</div>
                    </div>
                </div>
            </div>

            <!-- PERBAIKAN: Bagian Statistik Interaksi dengan Progress Bar yang Robust -->
            <div class="clay-card p-6">
                <h2 class="text-lg font-bold mb-4 flex items-center">
                    <i class="fas fa-chart-pie mr-2 text-secondary"></i>
                    Statistik Interaksi
                </h2>

                <div class="space-y-4">
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

                    <div class="clay-card bg-primary/10 p-3">
                        <div class="flex justify-between mb-1">
                            <span>Total Interaksi</span>
                            <span class="font-bold">{{ $totalInteractions }}</span>
                        </div>
                    </div>

                    <!-- ⚡ ROBUST FIX: View Progress Bar dengan inline styles -->
                    <div class="clay-card bg-info/10 p-3">
                        <div class="flex justify-between mb-2">
                            <span>View</span>
                            <span class="font-bold">{{ $viewCount }}</span>
                        </div>
                        <!-- Progress Bar dengan inline styles yang pasti terlihat -->
                        <div style="width: 100%; height: 10px; background-color: #e5e7eb; border-radius: 5px; overflow: hidden;">
                            <div style="height: 100%; background: linear-gradient(135deg, #3b82f6, #1e40af); border-radius: 5px; width: {{ $totalInteractions > 0 ? ($viewCount / $totalInteractions) * 100 : 0 }}%; transition: width 0.3s ease-in-out;"></div>
                        </div>
                        <div class="text-xs text-right mt-1">{{ $totalInteractions > 0 ? number_format(($viewCount / $totalInteractions) * 100, 1) : 0 }}%</div>
                    </div>

                    <!-- ⚡ ROBUST FIX: Liked Progress Bar dengan inline styles -->
                    <div class="clay-card bg-secondary/10 p-3">
                        <div class="flex justify-between mb-2">
                            <span>Liked</span>
                            <span class="font-bold">{{ $favoriteCount }}</span>
                        </div>
                        <!-- Progress Bar dengan inline styles yang pasti terlihat -->
                        <div style="width: 100%; height: 10px; background-color: #e5e7eb; border-radius: 5px; overflow: hidden;">
                            <div style="height: 100%; background: linear-gradient(135deg, #6b7280, #374151); border-radius: 5px; width: {{ $totalInteractions > 0 ? ($favoriteCount / $totalInteractions) * 100 : 0 }}%; transition: width 0.3s ease-in-out;"></div>
                        </div>
                        <div class="text-xs text-right mt-1">{{ $totalInteractions > 0 ? number_format(($favoriteCount / $totalInteractions) * 100, 1) : 0 }}%</div>
                    </div>

                    <!-- ⚡ ROBUST FIX: Portfolio Add Progress Bar dengan inline styles -->
                    <div class="clay-card bg-success/10 p-3">
                        <div class="flex justify-between mb-2">
                            <span>Portfolio Add</span>
                            <span class="font-bold">{{ $portfolioCount }}</span>
                        </div>
                        <!-- Progress Bar dengan inline styles yang pasti terlihat -->
                        <div style="width: 100%; height: 10px; background-color: #e5e7eb; border-radius: 5px; overflow: hidden;">
                            <div style="height: 100%; background: linear-gradient(135deg, #10b981, #047857); border-radius: 5px; width: {{ $totalInteractions > 0 ? ($portfolioCount / $totalInteractions) * 100 : 0 }}%; transition: width 0.3s ease-in-out;"></div>
                        </div>
                        <div class="text-xs text-right mt-1">{{ $totalInteractions > 0 ? number_format(($portfolioCount / $totalInteractions) * 100, 1) : 0 }}%</div>
                    </div>

                    <!-- ⚡ ROBUST FIX: Lainnya Progress Bar dengan inline styles -->
                    <div class="clay-card bg-warning/10 p-3">
                        <div class="flex justify-between mb-2">
                            <span>Lainnya</span>
                            <span class="font-bold">{{ $otherCount }}</span>
                        </div>
                        <!-- Progress Bar dengan inline styles yang pasti terlihat -->
                        <div style="width: 100%; height: 10px; background-color: #e5e7eb; border-radius: 5px; overflow: hidden;">
                            <div style="height: 100%; background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 5px; width: {{ $totalInteractions > 0 ? ($otherCount / $totalInteractions) * 100 : 0 }}%; transition: width 0.3s ease-in-out;"></div>
                        </div>
                        <div class="text-xs text-right mt-1">{{ $totalInteractions > 0 ? number_format(($otherCount / $totalInteractions) * 100, 1) : 0 }}%</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Activities -->
        <div class="lg:col-span-2">
            <!-- Tabs Navigation -->
            <div x-data="{ activeTab: 'interactions' }">
                <!-- Tab Headers -->
                <div class="clay-tabs mb-6">
                    <button @click="activeTab = 'interactions'" :class="{ 'active': activeTab === 'interactions' }" class="clay-tab">
                        <i class="fas fa-exchange-alt mr-2"></i> Interaksi
                    </button>
                    <button @click="activeTab = 'portfolio'" :class="{ 'active': activeTab === 'portfolio' }" class="clay-tab">
                        <i class="fas fa-wallet mr-2"></i> Portfolio
                    </button>
                    <button @click="activeTab = 'transactions'" :class="{ 'active': activeTab === 'transactions' }" class="clay-tab">
                        <i class="fas fa-money-bill-wave mr-2"></i> Transaksi
                    </button>
                </div>

                <!-- Tab Contents -->
                <!-- Interactions Tab -->
                <div x-show="activeTab === 'interactions'" class="clay-card p-6 mb-6">
                    <h3 class="text-xl font-bold mb-4">Interaksi Pengguna</h3>
                    <div class="overflow-x-auto">
                        <table class="clay-table min-w-full">
                            <thead>
                                <tr>
                                    <th class="py-2 px-4 text-left">Proyek</th>
                                    <th class="py-2 px-4 text-left">Tipe Interaksi</th>
                                    <th class="py-2 px-4 text-left">Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($interactions ?? [] as $interaction)
                                <tr>
                                    <td class="py-2 px-4">
                                        <div class="flex items-center">
                                            @if($interaction->project->image)
                                                <img src="{{ $interaction->project->image }}" alt="{{ $interaction->project->symbol }}" class="w-6 h-6 rounded-full mr-2">
                                            @endif
                                            <span class="font-medium">{{ $interaction->project->name }} ({{ $interaction->project->symbol }})</span>
                                        </div>
                                    </td>
                                    <td class="py-2 px-4">
                                        @if($interaction->interaction_type == 'view')
                                            <span class="clay-badge clay-badge-info">View</span>
                                        @elseif($interaction->interaction_type == 'favorite')
                                            <span class="clay-badge clay-badge-secondary">Liked</span>
                                        @elseif($interaction->interaction_type == 'portfolio_add')
                                            <span class="clay-badge clay-badge-success">Portfolio</span>
                                        @else
                                            <span class="clay-badge clay-badge-warning">{{ $interaction->interaction_type }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-4 text-gray-500 text-sm">{{ $interaction->created_at->diffForHumans() }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="py-4 px-4 text-center text-gray-500">Pengguna belum memiliki interaksi.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Portfolio Tab -->
                <div x-show="activeTab === 'portfolio'" class="clay-card p-6 mb-6">
                    <h3 class="text-xl font-bold mb-4">Portfolio Pengguna</h3>
                    <div class="overflow-x-auto">
                        <table class="clay-table min-w-full">
                            <thead>
                                <tr>
                                    <th class="py-2 px-4 text-left">Proyek</th>
                                    <th class="py-2 px-4 text-left">Jumlah</th>
                                    <th class="py-2 px-4 text-left">Harga Avg.</th>
                                    <th class="py-2 px-4 text-left">Harga Saat Ini</th>
                                    <th class="py-2 px-4 text-left">Nilai Total</th>
                                    <th class="py-2 px-4 text-left">Profit/Loss</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $totalValue = 0;
                                    $totalProfitLoss = 0;
                                @endphp
                                @forelse($portfolios ?? [] as $portfolio)
                                @php
                                    $totalValue += $portfolio->current_value;
                                    $totalProfitLoss += $portfolio->profit_loss_value;
                                @endphp
                                <tr>
                                    <td class="py-2 px-4">
                                        <div class="flex items-center">
                                            @if($portfolio->project->image)
                                                <img src="{{ $portfolio->project->image }}" alt="{{ $portfolio->project->symbol }}" class="w-6 h-6 rounded-full mr-2">
                                            @endif
                                            <span class="font-medium">{{ $portfolio->project->name }} ({{ $portfolio->project->symbol }})</span>
                                        </div>
                                    </td>
                                    <td class="py-2 px-4 font-medium">{{ number_format($portfolio->amount, 6) }}</td>
                                    <td class="py-2 px-4">${{ number_format($portfolio->average_buy_price, 2) }}</td>
                                    <td class="py-2 px-4">${{ number_format($portfolio->project->current_price, 2) }}</td>
                                    <td class="py-2 px-4 font-medium">${{ number_format($portfolio->current_value, 2) }}</td>
                                    <td class="py-2 px-4 {{ $portfolio->profit_loss_value >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $portfolio->profit_loss_value >= 0 ? '+' : '' }}${{ number_format($portfolio->profit_loss_value, 2) }}
                                        <span class="text-xs">({{ number_format($portfolio->profit_loss_percentage, 2) }}%)</span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="py-4 px-4 text-center text-gray-500">Pengguna belum memiliki portfolio.</td>
                                </tr>
                                @endforelse
                                @if(count($portfolios ?? []) > 0)
                                <tr class="bg-gray-50">
                                    <td colspan="4" class="py-3 px-4 font-bold text-right">Total:</td>
                                    <td class="py-3 px-4 font-bold">${{ number_format($totalValue, 2) }}</td>
                                    <td class="py-3 px-4 font-bold {{ $totalProfitLoss >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $totalProfitLoss >= 0 ? '+' : '' }}${{ number_format($totalProfitLoss, 2) }}
                                    </td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Transactions Tab -->
                <div x-show="activeTab === 'transactions'" class="clay-card p-6 mb-6">
                    <h3 class="text-xl font-bold mb-4">Transaksi Pengguna</h3>
                    <div class="overflow-x-auto">
                        <table class="clay-table min-w-full">
                            <thead>
                                <tr>
                                    <th class="py-2 px-4 text-left">Tanggal</th>
                                    <th class="py-2 px-4 text-left">Proyek</th>
                                    <th class="py-2 px-4 text-left">Tipe</th>
                                    <th class="py-2 px-4 text-left">Jumlah</th>
                                    <th class="py-2 px-4 text-left">Harga</th>
                                    <th class="py-2 px-4 text-left">Total Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($transactions ?? [] as $transaction)
                                <tr>
                                    <td class="py-2 px-4 text-sm">{{ $transaction->created_at->format('j M Y H:i') }}</td>
                                    <td class="py-2 px-4">
                                        <div class="flex items-center">
                                            @if($transaction->project->image)
                                                <img src="{{ $transaction->project->image }}" alt="{{ $transaction->project->symbol }}" class="w-6 h-6 rounded-full mr-2">
                                            @endif
                                            <span class="font-medium">{{ $transaction->project->symbol }}</span>
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
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="py-4 px-4 text-center text-gray-500">Pengguna belum memiliki transaksi.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Role Modal -->
<div id="edit-role-modal" class="fixed inset-0 z-50 hidden">
    <div class="clay-modal-backdrop"></div>
    <div class="clay-modal max-w-md">
        <div class="clay-modal-header">
            <h3 class="text-xl font-bold">Edit Peran Pengguna</h3>
        </div>
        <form action="#" method="POST" id="edit-role-form">
            @csrf
            @method('PUT')
            <div class="clay-modal-body">
                <div class="space-y-4">
                    <div>
                        <label for="modal-user-id" class="block font-medium mb-2">User ID</label>
                        <div id="modal-user-id" class="clay-input py-2"></div>
                    </div>

                    <div>
                        <label for="role" class="block font-medium mb-2">Peran</label>
                        <select name="role" id="modal-role" class="clay-select">
                            <option value="admin">Admin</option>
                            <option value="community">Community</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="clay-modal-footer">
                <button type="button" class="clay-button" onclick="document.getElementById('edit-role-modal').classList.add('hidden')">
                    Batal
                </button>
                <button type="submit" class="clay-button clay-button-primary">
                    <i class="fas fa-save mr-1"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function openEditRoleModal(userId, role) {
        // Set form action
        document.getElementById('edit-role-form').action = "{{ url('panel/admin/users') }}/" + userId + "/role";

        // Set user ID display
        document.getElementById('modal-user-id').textContent = userId;

        // Set current role
        document.getElementById('modal-role').value = role;

        // Show modal
        document.getElementById('edit-role-modal').classList.remove('hidden');
    }
</script>
@endpush

@endsection

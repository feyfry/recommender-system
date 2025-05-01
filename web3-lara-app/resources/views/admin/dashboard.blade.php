@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <h1 class="text-3xl font-bold mb-4 flex items-center">
            <div class="bg-secondary/20 p-2 clay-badge mr-3">
                <i class="fas fa-shield-alt text-secondary"></i>
            </div>
            Dashboard Admin
        </h1>
        <p class="text-lg">
            Panel kontrol untuk mengelola pengguna, proyek, dan sinkronisasi data rekomendasi.
        </p>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- User Stats -->
        <div class="clay-card p-6">
            <h2 class="text-lg font-bold mb-4 flex items-center">
                <i class="fas fa-users mr-2 text-primary"></i>
                Statistik Pengguna
            </h2>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span>Total Pengguna:</span>
                    <span class="font-bold">{{ $userStats['total'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Pengguna Aktif:</span>
                    <span class="font-bold">{{ $userStats['active'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Pengguna Baru:</span>
                    <span class="font-bold">{{ $userStats['new'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Admin:</span>
                    <span class="font-bold">{{ $userStats['admin'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Community:</span>
                    <span class="font-bold">{{ $userStats['community'] ?? 0 }}</span>
                </div>
            </div>
            <div class="mt-4">
                <a href="{{ route('admin.users') }}" class="clay-button clay-button-primary py-1.5 px-3 text-sm">
                    Kelola Pengguna
                </a>
            </div>
        </div>

        <!-- Project Stats -->
        <div class="clay-card p-6">
            <h2 class="text-lg font-bold mb-4 flex items-center">
                <i class="fas fa-project-diagram mr-2 text-success"></i>
                Statistik Proyek
            </h2>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span>Total Proyek:</span>
                    <span class="font-bold">{{ $projectStats['total'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Proyek Trending:</span>
                    <span class="font-bold">{{ $projectStats['trending'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Proyek Popular:</span>
                    <span class="font-bold">{{ $projectStats['popular'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Kategori:</span>
                    <span class="font-bold">{{ $projectStats['categories'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Blockchain:</span>
                    <span class="font-bold">{{ $projectStats['chains'] ?? 0 }}</span>
                </div>
            </div>
            <div class="mt-4">
                <a href="{{ route('admin.projects') }}" class="clay-button clay-button-success py-1.5 px-3 text-sm">
                    Kelola Proyek
                </a>
            </div>
        </div>

        <!-- Interaction Stats -->
        <div class="clay-card p-6">
            <h2 class="text-lg font-bold mb-4 flex items-center">
                <i class="fas fa-exchange-alt mr-2 text-info"></i>
                Statistik Interaksi
            </h2>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span>Total Interaksi:</span>
                    <span class="font-bold">{{ $interactionStats['total'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>View:</span>
                    <span class="font-bold">{{ $interactionStats['views'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Favorite:</span>
                    <span class="font-bold">{{ $interactionStats['favorites'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Portfolio:</span>
                    <span class="font-bold">{{ $interactionStats['portfolio_adds'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Interaksi Terbaru:</span>
                    <span class="font-bold">{{ $interactionStats['recent'] ?? 0 }}</span>
                </div>
            </div>
            <div class="mt-4">
                <a href="{{ route('admin.activity-logs') }}" class="clay-button clay-button-info py-1.5 px-3 text-sm">
                    Lihat Log Aktivitas
                </a>
            </div>
        </div>

        <!-- Transaction Stats -->
        <div class="clay-card p-6">
            <h2 class="text-lg font-bold mb-4 flex items-center">
                <i class="fas fa-money-bill-wave mr-2 text-warning"></i>
                Statistik Transaksi
            </h2>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span>Total Transaksi:</span>
                    <span class="font-bold">{{ $transactionStats['total'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Pembelian (Buy):</span>
                    <span class="font-bold">{{ $transactionStats['buy'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Penjualan (Sell):</span>
                    <span class="font-bold">{{ $transactionStats['sell'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Volume Total:</span>
                    <span class="font-bold">${{ number_format($transactionStats['volume'] ?? 0, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Transaksi Terbaru:</span>
                    <span class="font-bold">{{ $transactionStats['recent'] ?? 0 }}</span>
                </div>
            </div>
            <div class="mt-4">
                <a href="{{ route('admin.data-sync') }}" class="clay-button clay-button-warning py-1.5 px-3 text-sm">
                    Kelola Sinkronisasi
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Recent Activity -->
        <div class="clay-card p-6 lg:col-span-2">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-history mr-2 text-secondary"></i>
                Aktivitas Terbaru
            </h2>

            <div class="overflow-x-auto">
                <table class="clay-table min-w-full">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 text-left">Pengguna</th>
                            <th class="py-2 px-4 text-left">Tipe Aktivitas</th>
                            <th class="py-2 px-4 text-left">Deskripsi</th>
                            <th class="py-2 px-4 text-left">Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentActivity ?? [] as $activity)
                        <tr>
                            <td class="py-2 px-4 font-medium">{{ $activity->user?->profile?->username ?? $activity->user?->wallet_address ?? 'Sistem' }}</td>
                            <td class="py-2 px-4">
                                @if($activity->activity_type == 'login')
                                    <span class="clay-badge clay-badge-info">Login</span>
                                @elseif($activity->activity_type == 'project_interaction')
                                    <span class="clay-badge clay-badge-success">Interaksi</span>
                                @elseif($activity->activity_type == 'admin_action')
                                    <span class="clay-badge clay-badge-warning">Admin</span>
                                @elseif($activity->activity_type == 'transaction')
                                    <span class="clay-badge clay-badge-primary">Transaksi</span>
                                @else
                                    <span class="clay-badge clay-badge-secondary">{{ $activity->activity_type }}</span>
                                @endif
                            </td>
                            <td class="py-2 px-4">{{ $activity->description }}</td>
                            <td class="py-2 px-4 text-gray-500 text-sm">{{ $activity->created_at->diffForHumans() }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="py-4 px-4 text-center text-gray-500">Tidak ada aktivitas terbaru.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4 text-right">
                <a href="{{ route('admin.activity-logs') }}" class="clay-button clay-button-secondary py-1.5 px-3 text-sm">
                    Lihat Semua Log <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>

        <!-- User & Project Highlights -->
        <div class="flex flex-col gap-6">
            <!-- Active Users -->
            <div class="clay-card p-6">
                <h2 class="text-lg font-bold mb-4 flex items-center">
                    <i class="fas fa-user-check mr-2 text-primary"></i>
                    Pengguna Paling Aktif
                </h2>
                <div class="space-y-2">
                    @forelse($mostActiveUsers ?? [] as $index => $activeUser)
                    <div class="clay-card bg-primary/5 p-3 flex justify-between items-center">
                        <div class="flex items-center">
                            <div class="clay-rounded-full bg-primary/20 w-8 h-8 flex items-center justify-center mr-2">
                                <span class="font-bold">{{ $index + 1 }}</span>
                            </div>
                            <span>{{ $activeUser->user?->profile?->username ?? substr($activeUser->user_id, 0, 10) . '...' }}</span>
                        </div>
                        <span class="clay-badge clay-badge-primary">{{ $activeUser->activity_count }} aktivitas</span>
                    </div>
                    @empty
                    <div class="text-center py-4 text-gray-500">
                        <p>Belum ada data pengguna aktif.</p>
                    </div>
                    @endforelse
                </div>
            </div>

            <!-- Popular Projects -->
            <div class="clay-card p-6">
                <h2 class="text-lg font-bold mb-4 flex items-center">
                    <i class="fas fa-trophy mr-2 text-warning"></i>
                    Proyek Terpopuler
                </h2>
                <div class="space-y-2">
                    @forelse($mostInteractedProjects ?? [] as $index => $interactedProject)
                    <div class="clay-card bg-warning/5 p-3 flex justify-between items-center">
                        <div class="flex items-center">
                            @if($interactedProject->project?->image)
                                <img src="{{ $interactedProject->project->image }}" alt="{{ $interactedProject->project->symbol }}" class="w-6 h-6 rounded-full mr-2">
                            @endif
                            <span>{{ $interactedProject->project?->name ?? 'Unknown' }} ({{ $interactedProject->project?->symbol ?? '?' }})</span>
                        </div>
                        <span class="clay-badge clay-badge-warning">{{ $interactedProject->interaction_count }} interaksi</span>
                    </div>
                    @empty
                    <div class="text-center py-4 text-gray-500">
                        <p>Belum ada data proyek terpopuler.</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Action Buttons -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-bolt mr-2 text-info"></i>
            Aksi Cepat
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="{{ route('admin.users') }}" class="clay-card p-4 bg-primary/10 text-center hover:translate-y-[-5px] transition-transform">
                <i class="fas fa-users text-3xl text-primary mb-2"></i>
                <div class="font-bold">Kelola Pengguna</div>
                <p class="text-sm mt-1">Lihat dan kelola semua pengguna sistem</p>
            </a>

            <a href="{{ route('admin.projects') }}" class="clay-card p-4 bg-success/10 text-center hover:translate-y-[-5px] transition-transform">
                <i class="fas fa-project-diagram text-3xl text-success mb-2"></i>
                <div class="font-bold">Kelola Proyek</div>
                <p class="text-sm mt-1">Kelola proyek cryptocurrency</p>
            </a>

            <a href="{{ route('admin.data-sync') }}" class="clay-card p-4 bg-warning/10 text-center hover:translate-y-[-5px] transition-transform">
                <i class="fas fa-sync text-3xl text-warning mb-2"></i>
                <div class="font-bold">Sinkronisasi Data</div>
                <p class="text-sm mt-1">Sinkronkan data dengan engine rekomendasi</p>
            </a>

            <a href="{{ route('admin.activity-logs') }}" class="clay-card p-4 bg-info/10 text-center hover:translate-y-[-5px] transition-transform">
                <i class="fas fa-history text-3xl text-info mb-2"></i>
                <div class="font-bold">Log Aktivitas</div>
                <p class="text-sm mt-1">Lihat semua aktivitas sistem</p>
            </a>
        </div>
    </div>
</div>
@endsection

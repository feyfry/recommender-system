@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <h1 class="text-3xl font-bold mb-4 flex items-center">
            <div class="bg-info/20 p-2 clay-badge mr-3">
                <i class="fas fa-history text-info"></i>
            </div>
            Log Aktivitas
        </h1>
        <p class="text-lg">
            Pantau semua aktivitas pengguna dan sistem dalam aplikasi.
        </p>
    </div>

    <!-- Filters -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-filter mr-2 text-secondary"></i>
            Filter Log
        </h2>

        <form action="{{ route('admin.activity-logs') }}" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label for="activity_type" class="block mb-2 font-medium">Tipe Aktivitas</label>
                <select name="activity_type" id="activity_type" class="clay-select w-full">
                    <option value="">-- Semua Tipe --</option>
                    @foreach($activityTypes as $type)
                        <option value="{{ $type }}" {{ $filters['activity_type'] ?? '' == $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="user_id" class="block mb-2 font-medium">User ID</label>
                <input type="text" name="user_id" id="user_id" class="clay-input w-full" placeholder="ID pengguna..." value="{{ $filters['user_id'] ?? '' }}">
            </div>

            <div>
                <label for="date_from" class="block mb-2 font-medium">Tanggal Mulai</label>
                <input type="date" name="date_from" id="date_from" class="clay-input w-full" value="{{ $filters['date_from'] ?? '' }}">
            </div>

            <div>
                <label for="date_to" class="block mb-2 font-medium">Tanggal Akhir</label>
                <input type="date" name="date_to" id="date_to" class="clay-input w-full" value="{{ $filters['date_to'] ?? '' }}">
            </div>

            <div class="flex items-end space-x-2 md:col-span-2 lg:col-span-4">
                <button type="submit" class="clay-button clay-button-primary py-2 px-4">
                    <i class="fas fa-search mr-1"></i> Cari
                </button>
                <a href="{{ route('admin.activity-logs') }}" class="clay-button py-2 px-4">
                    <i class="fas fa-times mr-1"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Activity Stats -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-chart-pie mr-2 text-warning"></i>
            Statistik Aktivitas
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            @php
                $totalLogs = $logs->total();
                $loginCount = 0;
                $logoutCount = 0;
                $interactionCount = 0;
                $adminActionCount = 0;
                $otherCount = 0;

                foreach ($activityTypes as $type) {
                    if ($type == 'login') {
                        $loginCount = \App\Models\ActivityLog::ofType('login')->count();
                    } elseif ($type == 'logout') {
                        $logoutCount = \App\Models\ActivityLog::ofType('logout')->count();
                    } elseif ($type == 'project_interaction') {
                        $interactionCount = \App\Models\ActivityLog::ofType('project_interaction')->count();
                    } elseif ($type == 'admin_action') {
                        $adminActionCount = \App\Models\ActivityLog::ofType('admin_action')->count();
                    } else {
                        $otherCount += \App\Models\ActivityLog::ofType($type)->count();
                    }
                }
            @endphp

            <div class="clay-card bg-primary/10 p-4 text-center">
                <div class="text-2xl font-bold">{{ $totalLogs }}</div>
                <div class="text-sm">Total Log</div>
            </div>

            <div class="clay-card bg-success/10 p-4 text-center">
                <div class="text-2xl font-bold">{{ $loginCount }}</div>
                <div class="text-sm">Login</div>
            </div>

            <div class="clay-card bg-info/10 p-4 text-center">
                <div class="text-2xl font-bold">{{ $interactionCount }}</div>
                <div class="text-sm">Interaksi</div>
            </div>

            <div class="clay-card bg-warning/10 p-4 text-center">
                <div class="text-2xl font-bold">{{ $adminActionCount }}</div>
                <div class="text-sm">Aksi Admin</div>
            </div>

            <div class="clay-card bg-secondary/10 p-4 text-center">
                <div class="text-2xl font-bold">{{ $logoutCount + $otherCount }}</div>
                <div class="text-sm">Lainnya</div>
            </div>
        </div>
    </div>

    <!-- Activity Logs List -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-list mr-2 text-info"></i>
            Daftar Log
        </h2>

        <div class="overflow-x-auto">
            <table class="clay-table min-w-full">
                <thead>
                    <tr>
                        <th class="py-3 px-4 text-left">Waktu</th>
                        <th class="py-3 px-4 text-left">Pengguna</th>
                        <th class="py-3 px-4 text-left">Tipe Aktivitas</th>
                        <th class="py-3 px-4 text-left">Deskripsi</th>
                        <th class="py-3 px-4 text-left">IP Address</th>
                        <th class="py-3 px-4 text-left">User Agent</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td class="py-3 px-4 text-sm">{{ $log->created_at->format('j M Y H:i:s') }}</td>
                        <td class="py-3 px-4">
                            @if($log->user)
                            <div class="flex items-center">
                                @if($log->user->profile && $log->user->profile->avatar_url)
                                    <img src="{{ asset($log->user->profile->avatar_url) }}" alt="{{ $log->user->profile->username ?? $log->user->user_id }}" class="w-6 h-6 rounded-full mr-2">
                                @endif
                                <a href="{{ route('admin.users.detail', $log->user->user_id) }}" class="font-medium text-primary">
                                    {{ $log->user->profile->username ?? substr($log->user->user_id, 0, 10) . '...' }}
                                </a>
                            </div>
                            @else
                                <span class="text-gray-500">Sistem</span>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            @if($log->activity_type == 'login')
                                <span class="clay-badge clay-badge-success">Login</span>
                            @elseif($log->activity_type == 'logout')
                                <span class="clay-badge clay-badge-info">Logout</span>
                            @elseif($log->activity_type == 'project_interaction')
                                <span class="clay-badge clay-badge-primary">Interaksi</span>
                            @elseif($log->activity_type == 'admin_action')
                                <span class="clay-badge clay-badge-warning">Admin</span>
                            @elseif($log->activity_type == 'transaction')
                                <span class="clay-badge clay-badge-secondary">Transaksi</span>
                            @elseif($log->activity_type == 'profile_update')
                                <span class="clay-badge clay-badge-info">Update Profil</span>
                            @else
                                <span class="clay-badge clay-badge-info">{{ $log->activity_type }}</span>
                            @endif
                        </td>
                        <td class="py-3 px-4">{{ $log->description }}</td>
                        <td class="py-3 px-4 font-mono text-xs">{{ $log->ip_address }}</td>
                        <td class="py-3 px-4 truncate max-w-[200px] text-xs text-gray-500" title="{{ $log->user_agent }}">
                            {{ Str::limit($log->user_agent, 30) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-6 px-4 text-center">
                            <p class="text-gray-500">Tidak ada log aktivitas yang ditemukan.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($logs->hasPages())
        <div class="mt-6">
            {{ $logs->appends(request()->query())->links() }}
        </div>
        @endif
    </div>

    <!-- Activity Timeline -->
    <div class="clay-card p-6">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-stream mr-2 text-primary"></i>
            Timeline Aktivitas
        </h2>

        <div class="relative">
            <!-- Timeline Line -->
            <div class="absolute left-5 top-0 bottom-0 w-0.5 bg-primary/20"></div>

            <!-- Timeline Events -->
            <div class="space-y-6 relative">
                @forelse($logs->take(15) as $index => $log)
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-full bg-primary/10 relative z-10">
                        @if($log->activity_type == 'login')
                            <i class="fas fa-sign-in-alt text-success"></i>
                        @elseif($log->activity_type == 'logout')
                            <i class="fas fa-sign-out-alt text-info"></i>
                        @elseif($log->activity_type == 'project_interaction')
                            <i class="fas fa-exchange-alt text-primary"></i>
                        @elseif($log->activity_type == 'admin_action')
                            <i class="fas fa-cog text-warning"></i>
                        @elseif($log->activity_type == 'transaction')
                            <i class="fas fa-money-bill-wave text-secondary"></i>
                        @else
                            <i class="fas fa-dot-circle text-info"></i>
                        @endif
                    </div>
                    <div class="ml-4 flex-1">
                        <div class="clay-card bg-white p-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-bold">
                                        {{ $log->user ? ($log->user->profile->username ?? substr($log->user->user_id, 0, 10) . '...') : 'Sistem' }}
                                    </div>
                                    <div class="text-sm">{{ $log->description }}</div>
                                </div>
                                <div class="text-xs text-gray-500">{{ $log->created_at->diffForHumans() }}</div>
                            </div>
                            <div class="mt-2 flex justify-between items-center text-xs text-gray-500">
                                <div>{{ $log->activity_type }}</div>
                                <div>{{ $log->ip_address }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-6 text-gray-500">
                    <p>Tidak ada aktivitas yang tersedia.</p>
                </div>
                @endforelse
            </div>
        </div>

        <div class="flex justify-end mt-8">
            <a href="{{ route('admin.dashboard') }}" class="clay-button clay-button-info">
                <i class="fas fa-arrow-left mr-2"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>
</div>
@endsection

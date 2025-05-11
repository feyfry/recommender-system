@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <h1 class="text-3xl font-bold mb-4 flex items-center">
            <div class="bg-info/20 p-2 clay-badge mr-3">
                <i class="fas fa-exchange-alt text-info"></i>
            </div>
            Semua Interaksi
        </h1>
        <p class="text-lg">
            Daftar lengkap interaksi pengguna dengan proyek dalam sistem.
        </p>
    </div>

    <!-- Filters -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-filter mr-2 text-secondary"></i>
            Filter Interaksi
        </h2>

        <form action="{{ route('admin.interactions') }}" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="type" class="block mb-2 font-medium">Tipe Interaksi</label>
                <select name="type" id="type" class="clay-select w-full">
                    <option value="">-- Semua Tipe --</option>
                    @foreach($interactionTypes as $type)
                        <option value="{{ $type }}" {{ ($filters['type'] ?? '') == $type ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $type)) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="from_date" class="block mb-2 font-medium">Dari Tanggal</label>
                <input type="date" name="from_date" id="from_date" class="clay-input w-full"
                       value="{{ $filters['from_date'] ?? '' }}">
            </div>

            <div>
                <label for="to_date" class="block mb-2 font-medium">Sampai Tanggal</label>
                <input type="date" name="to_date" id="to_date" class="clay-input w-full"
                       value="{{ $filters['to_date'] ?? '' }}">
            </div>

            <div class="flex items-end space-x-2">
                <button type="submit" class="clay-button clay-button-primary py-2 px-4">
                    <i class="fas fa-search mr-1"></i> Cari
                </button>
                <a href="{{ route('admin.interactions') }}" class="clay-button py-2 px-4">
                    <i class="fas fa-times mr-1"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Interactions Stats -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-chart-pie mr-2 text-warning"></i>
            Statistik Interaksi
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            @php
                $typeStats = [
                    'view' => 0,
                    'favorite' => 0,
                    'portfolio_add' => 0,
                    'research' => 0,
                ];

                // Hitung statistik dari data yang sudah di-filter
                foreach($interactions as $interaction) {
                    if(isset($typeStats[$interaction->interaction_type])) {
                        $typeStats[$interaction->interaction_type]++;
                    }
                }
            @endphp

            <div class="clay-card bg-info/10 p-4 text-center">
                <div class="text-3xl font-bold mb-2">{{ $typeStats['view'] }}</div>
                <div class="text-sm">View</div>
            </div>

            <div class="clay-card bg-secondary/10 p-4 text-center">
                <div class="text-3xl font-bold mb-2">{{ $typeStats['favorite'] }}</div>
                <div class="text-sm">Favorite</div>
            </div>

            <div class="clay-card bg-success/10 p-4 text-center">
                <div class="text-3xl font-bold mb-2">{{ $typeStats['portfolio_add'] }}</div>
                <div class="text-sm">Portfolio Add</div>
            </div>

            <div class="clay-card bg-primary/10 p-4 text-center">
                <div class="text-3xl font-bold mb-2">{{ $typeStats['research'] }}</div>
                <div class="text-sm">Research</div>
            </div>
        </div>
    </div>

    <!-- Interactions List -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-list mr-2 text-info"></i>
            Daftar Interaksi
        </h2>

        <div class="overflow-x-auto">
            <table class="clay-table min-w-full">
                <thead>
                    <tr>
                        <th class="py-3 px-4 text-left">ID</th>
                        <th class="py-3 px-4 text-left">Pengguna</th>
                        <th class="py-3 px-4 text-left">Proyek</th>
                        <th class="py-3 px-4 text-left">Tipe</th>
                        <th class="py-3 px-4 text-left">Weight</th>
                        <th class="py-3 px-4 text-left">
                            <a href="{{ route('admin.interactions', array_merge($filters ?? [], ['sort' => 'created_at', 'direction' => (($filters['sort'] ?? '') == 'created_at' && ($filters['direction'] ?? '') == 'desc') ? 'asc' : 'desc'])) }}" class="flex items-center">
                                Waktu
                                @if(($filters['sort'] ?? '') == 'created_at')
                                    <i class="fas fa-sort-{{ ($filters['direction'] ?? '') == 'asc' ? 'up' : 'down' }} ml-1"></i>
                                @endif
                            </a>
                        </th>
                        <th class="py-3 px-4 text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($interactions as $interaction)
                    <tr>
                        <td class="py-3 px-4 font-mono text-xs">{{ $interaction->id }}</td>
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                @if($interaction->user->profile && $interaction->user->profile->avatar_url)
                                    <img src="{{ asset($interaction->user->profile->avatar_url) }}" alt="Avatar" class="w-6 h-6 rounded-full mr-2">
                                @endif
                                <div>
                                    <div class="font-medium">{{ $interaction->user->profile->username ?? substr($interaction->user->user_id, 0, 10) . '...' }}</div>
                                    <div class="text-xs text-gray-500">{{ substr($interaction->user->user_id, 0, 10) }}...</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                @if($interaction->project && $interaction->project->image)
                                    <img src="{{ $interaction->project->image }}" alt="{{ $interaction->project->symbol }}" class="w-6 h-6 rounded-full mr-2">
                                @endif
                                <div>
                                    <div class="font-medium">{{ $interaction->project->name ?? 'Unknown' }}</div>
                                    <div class="text-xs text-gray-500">{{ $interaction->project->symbol ?? '-' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            @switch($interaction->interaction_type)
                                @case('view')
                                    <span class="clay-badge clay-badge-info">View</span>
                                    @break
                                @case('favorite')
                                    <span class="clay-badge clay-badge-secondary">Favorite</span>
                                    @break
                                @case('portfolio_add')
                                    <span class="clay-badge clay-badge-success">Portfolio</span>
                                    @break
                                @case('research')
                                    <span class="clay-badge clay-badge-primary">Research</span>
                                    @break
                                @default
                                    <span class="clay-badge">{{ $interaction->interaction_type }}</span>
                            @endswitch
                        </td>
                        <td class="py-3 px-4">{{ $interaction->weight }}</td>
                        <td class="py-3 px-4 text-sm">{{ $interaction->created_at->format('j M Y H:i') }}</td>
                        <td class="py-3 px-4">
                            <div class="flex space-x-2">
                                <a href="{{ route('admin.users.detail', $interaction->user->user_id) }}"
                                   class="clay-badge clay-badge-primary py-1 px-2 text-xs">
                                    <i class="fas fa-user"></i> User
                                </a>
                                <a href="{{ route('admin.projects.detail', $interaction->project->id) }}"
                                   class="clay-badge clay-badge-success py-1 px-2 text-xs">
                                    <i class="fas fa-project-diagram"></i> Proyek
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-6 px-4 text-center">
                            <p class="text-gray-500">Tidak ada interaksi yang ditemukan.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($interactions->hasPages())
        <div class="mt-6">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <p class="text-sm text-gray-600">
                        Menampilkan {{ $interactions->firstItem() }} sampai {{ $interactions->lastItem() }}
                        dari {{ $interactions->total() }} interaksi
                    </p>
                </div>

                <div class="flex space-x-2">
                    {{-- Previous Button --}}
                    @if ($interactions->onFirstPage())
                        <span class="clay-button clay-button-secondary py-1.5 px-3 text-sm opacity-50 cursor-not-allowed">
                            <i class="fas fa-chevron-left"></i>
                        </span>
                    @else
                        <a href="{{ $interactions->previousPageUrl() }}" class="clay-button clay-button-secondary py-1.5 px-3 text-sm">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($interactions->getUrlRange(max(1, $interactions->currentPage() - 2), min($interactions->lastPage(), $interactions->currentPage() + 2)) as $page => $url)
                        @if ($page == $interactions->currentPage())
                            <span class="clay-button clay-button-primary py-1.5 px-3 text-sm">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="clay-button clay-button-secondary py-1.5 px-3 text-sm">{{ $page }}</a>
                        @endif
                    @endforeach

                    {{-- Next Button --}}
                    @if ($interactions->hasMorePages())
                        <a href="{{ $interactions->nextPageUrl() }}" class="clay-button clay-button-secondary py-1.5 px-3 text-sm">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    @else
                        <span class="clay-button clay-button-secondary py-1.5 px-3 text-sm opacity-50 cursor-not-allowed">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

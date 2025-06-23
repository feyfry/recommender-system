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
            <!-- PERBAIKAN: Preserve pagination dan sorting dalam form -->
            @if(request()->has('page') && request()->page > 1)
                <input type="hidden" name="page" value="1">
            @endif
            @if(request()->has('sort'))
                <input type="hidden" name="sort" value="{{ request()->sort }}">
            @endif
            @if(request()->has('direction'))
                <input type="hidden" name="direction" value="{{ request()->direction }}">
            @endif

            <div>
                <label for="type" class="block mb-2 font-medium">Tipe Interaksi</label>
                <select name="type" id="type" class="clay-select w-full">
                    <option value="">-- Semua Tipe --</option>
                    @foreach($interactionTypes as $value => $label)
                        <option value="{{ $value }}" {{ ($filters['type'] ?? '') == $value ? 'selected' : '' }}>
                            {{ $label }}
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

    <!-- PERBAIKAN: Interactions Stats berdasarkan total filtered data -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-chart-pie mr-2 text-warning"></i>
            Statistik Interaksi
            @if(!empty($filters['type']) || !empty($filters['from_date']) || !empty($filters['to_date']))
                <span class="clay-badge clay-badge-info ml-2 text-xs">Berdasarkan Filter</span>
            @else
                <span class="clay-badge clay-badge-primary ml-2 text-xs">Total Keseluruhan</span>
            @endif
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="clay-card bg-primary/10 p-4 text-center">
                <div class="text-3xl font-bold mb-2">{{ number_format($totalStats['total']) }}</div>
                <div class="text-sm">Total Interaksi</div>
                @if(!empty($filters['type']) || !empty($filters['from_date']) || !empty($filters['to_date']))
                    <div class="text-xs text-gray-500 mt-1">Hasil Filter</div>
                @endif
            </div>

            <div class="clay-card bg-info/10 p-4 text-center">
                <div class="text-3xl font-bold mb-2">{{ number_format($totalStats['view']) }}</div>
                <div class="text-sm">View</div>
            </div>

            <div class="clay-card bg-secondary/10 p-4 text-center">
                <div class="text-3xl font-bold mb-2">{{ number_format($totalStats['favorite']) }}</div>
                <div class="text-sm">Liked</div>
            </div>

            <div class="clay-card bg-success/10 p-4 text-center">
                <div class="text-3xl font-bold mb-2">{{ number_format($totalStats['portfolio_add']) }}</div>
                <div class="text-sm">Portfolio Add</div>
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
                            <!-- PERBAIKAN: Preserve filter parameters in sorting links -->
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
                                    <span class="clay-badge clay-badge-secondary">Liked</span>
                                    @break
                                @case('portfolio_add')
                                    <span class="clay-badge clay-badge-success">Portfolio</span>
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
                            @if(!empty($filters['type']) || !empty($filters['from_date']) || !empty($filters['to_date']))
                                <p class="text-gray-500">Tidak ada interaksi yang sesuai dengan filter yang dipilih.</p>
                                <a href="{{ route('admin.interactions') }}" class="clay-button clay-button-secondary mt-2 py-1 px-3 text-sm">
                                    <i class="fas fa-times mr-1"></i> Hapus Filter
                                </a>
                            @else
                                <p class="text-gray-500">Tidak ada interaksi yang ditemukan.</p>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PERBAIKAN: Pagination dengan preserved query parameters -->
        @if($interactions->hasPages())
        <div class="mt-6">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <p class="text-sm text-gray-600">
                        Menampilkan {{ $interactions->firstItem() }} sampai {{ $interactions->lastItem() }}
                        dari {{ number_format($interactions->total()) }} interaksi
                        @if(!empty($filters['type']) || !empty($filters['from_date']) || !empty($filters['to_date']))
                            <span class="clay-badge clay-badge-info text-xs ml-1">Hasil Filter</span>
                        @endif
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

    <!-- PERBAIKAN: Filter Status Info -->
    @if(!empty($filters['type']) || !empty($filters['from_date']) || !empty($filters['to_date']))
    <div class="clay-card p-4 mb-8 bg-blue-50 border-l-4 border-blue-400">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-sm font-medium text-blue-800">Filter Aktif:</h3>
                <div class="flex flex-wrap gap-2 mt-1">
                    @if(!empty($filters['type']))
                        <span class="clay-badge clay-badge-info text-xs">
                            Tipe: {{ $interactionTypes[$filters['type']] ?? $filters['type'] }}
                        </span>
                    @endif
                    @if(!empty($filters['from_date']))
                        <span class="clay-badge clay-badge-info text-xs">
                            Dari: {{ \Carbon\Carbon::parse($filters['from_date'])->format('j M Y') }}
                        </span>
                    @endif
                    @if(!empty($filters['to_date']))
                        <span class="clay-badge clay-badge-info text-xs">
                            Sampai: {{ \Carbon\Carbon::parse($filters['to_date'])->format('j M Y') }}
                        </span>
                    @endif
                </div>
            </div>
            <a href="{{ route('admin.interactions') }}" class="clay-button clay-button-secondary py-1 px-3 text-sm">
                <i class="fas fa-times mr-1"></i> Hapus Semua Filter
            </a>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // PERBAIKAN: Automatically submit form when filter changes untuk UX yang lebih baik
    const typeSelect = document.getElementById('type');
    const fromDateInput = document.getElementById('from_date');
    const toDateInput = document.getElementById('to_date');

    function autoSubmitForm() {
        // Submit form setelah delay kecil untuk memberikan feedback visual
        setTimeout(() => {
            typeSelect.closest('form').submit();
        }, 100);
    }

    // Auto-submit untuk select type
    if (typeSelect) {
        typeSelect.addEventListener('change', autoSubmitForm);
    }

    // Auto-submit untuk date inputs (hanya ketika both date tersedia atau clear)
    if (fromDateInput && toDateInput) {
        [fromDateInput, toDateInput].forEach(input => {
            input.addEventListener('change', function() {
                // Submit jika both dates diisi atau both kosong
                const fromDate = fromDateInput.value;
                const toDate = toDateInput.value;

                if ((fromDate && toDate) || (!fromDate && !toDate)) {
                    autoSubmitForm();
                }
            });
        });
    }
});
</script>
@endpush

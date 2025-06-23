@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <h1 class="text-3xl font-bold mb-4 flex items-center">
            <div class="bg-primary/20 p-2 clay-badge mr-3">
                <i class="fas fa-users text-primary"></i>
            </div>
            Manajemen Pengguna
        </h1>
        <p class="text-lg">
            Kelola semua pengguna dalam sistem rekomendasi.
        </p>
    </div>

    <!-- Filters -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-filter mr-2 text-secondary"></i>
            Filter Pengguna
        </h2>

        <form action="{{ route('admin.users') }}" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4" id="filterForm">
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
                <label for="role" class="block mb-2 font-medium">Peran</label>
                <select name="role" id="role" class="clay-select w-full">
                    <option value="">-- Semua Peran --</option>
                    <option value="admin" {{ ($filters['role'] ?? '') == 'admin' ? 'selected' : '' }}>Admin</option>
                    <option value="community" {{ ($filters['role'] ?? '') == 'community' ? 'selected' : '' }}>Community</option>
                </select>
            </div>

            <div>
                <label for="search" class="block mb-2 font-medium">Pencarian</label>
                <input type="text" name="search" id="search" class="clay-input w-full"
                       placeholder="Cari user_id atau wallet..."
                       value="{{ $filters['search'] ?? '' }}">
            </div>

            <!-- Opsi sorting -->
            <div>
                <label for="sort" class="block mb-2 font-medium">Urutkan Berdasarkan</label>
                <select name="sort" id="sort" class="clay-select w-full">
                    <option value="created_at" {{ ($filters['sort'] ?? '') == 'created_at' ? 'selected' : '' }}>Tanggal Bergabung</option>
                    <option value="last_login" {{ ($filters['sort'] ?? '') == 'last_login' ? 'selected' : '' }}>Login Terakhir</option>
                    <option value="interactions" {{ ($filters['sort'] ?? '') == 'interactions' ? 'selected' : '' }}>Jumlah Interaksi</option>
                </select>
            </div>

            <div>
                <label for="direction" class="block mb-2 font-medium">Arah Urutan</label>
                <select name="direction" id="direction" class="clay-select w-full">
                    <option value="desc" {{ ($filters['direction'] ?? 'desc') == 'desc' ? 'selected' : '' }}>Terbanyak/Terbaru</option>
                    <option value="asc" {{ ($filters['direction'] ?? 'desc') == 'asc' ? 'selected' : '' }}>Tersedikit/Terlama</option>
                </select>
            </div>

            <div class="md:col-span-4 flex items-end space-x-2">
                <button type="submit" class="clay-button clay-button-primary py-2 px-4">
                    <i class="fas fa-search mr-1"></i> Cari
                </button>
                <a href="{{ route('admin.users') }}" class="clay-button py-2 px-4">
                    <i class="fas fa-times mr-1"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Role Statistics -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-chart-pie mr-2 text-warning"></i>
            Statistik Peran
            @if(!empty($filters['role']) || !empty($filters['search']))
                <span class="clay-badge clay-badge-info ml-2 text-xs">Berdasarkan Filter</span>
            @else
                <span class="clay-badge clay-badge-primary ml-2 text-xs">Total Keseluruhan</span>
            @endif
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="clay-card bg-primary/10 p-4 text-center">
                <div class="text-3xl font-bold mb-2">
                    {{ $users->total() }}
                </div>
                <div class="text-sm">Total Pengguna</div>
                @if(!empty($filters['role']) || !empty($filters['search']))
                    <div class="text-xs text-gray-500 mt-1">Hasil Filter</div>
                @endif
            </div>

            @php
                $adminCount = 0;
                $communityCount = 0;
                $otherCount = 0;
            @endphp
            @foreach($roleStats ?? [] as $roleStat)
                @if($roleStat->role == 'admin')
                    @php $adminCount = $roleStat->count; @endphp
                @elseif($roleStat->role == 'community')
                    @php $communityCount = $roleStat->count; @endphp
                @else
                    @php $otherCount += $roleStat->count; @endphp
                @endif
            @endforeach

            <div class="clay-card bg-success/10 p-4 text-center">
                <div class="text-3xl font-bold mb-2">
                    {{ $adminCount }}
                </div>
                <div class="text-sm">Admin</div>
            </div>

            <div class="clay-card bg-warning/10 p-4 text-center">
                <div class="text-3xl font-bold mb-2">
                    {{ $communityCount }}
                </div>
                <div class="text-sm">Community</div>
            </div>

            <div class="clay-card bg-secondary/10 p-4 text-center">
                <div class="text-3xl font-bold mb-2">
                    {{ $otherCount }}
                </div>
                <div class="text-sm">Lainnya</div>
            </div>
        </div>
    </div>

    <!-- Users List -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-list mr-2 text-info"></i>
            Daftar Pengguna
        </h2>

        <div class="overflow-x-auto">
            <table class="clay-table min-w-full">
                <thead>
                    <tr>
                        <th class="py-3 px-4 text-left">User ID</th>
                        <th class="py-3 px-4 text-left">Wallet Address</th>
                        <th class="py-3 px-4 text-left">Username</th>
                        <th class="py-3 px-4 text-left">Peran</th>
                        <!-- PERBAIKAN: Kolom Interaksi dengan link sortable yang preserve filter -->
                        <th class="py-3 px-4 text-left">
                            <a href="{{ route('admin.users', array_merge($filters ?? [], ['sort' => 'interactions', 'direction' => (($filters['sort'] ?? '') == 'interactions' && ($filters['direction'] ?? 'desc') == 'desc') ? 'asc' : 'desc'])) }}"
                               class="flex items-center hover:text-primary">
                                Interaksi
                                @if(($filters['sort'] ?? '') == 'interactions')
                                    <i class="fas fa-sort-{{ ($filters['direction'] ?? 'desc') == 'asc' ? 'up' : 'down' }} ml-1"></i>
                                @endif
                            </a>
                        </th>
                        <th class="py-3 px-4 text-left">
                            <a href="{{ route('admin.users', array_merge($filters ?? [], ['sort' => 'created_at', 'direction' => (($filters['sort'] ?? '') == 'created_at' && ($filters['direction'] ?? 'desc') == 'desc') ? 'asc' : 'desc'])) }}" class="flex items-center">
                                Bergabung
                                @if(($filters['sort'] ?? '') == 'created_at')
                                    <i class="fas fa-sort-{{ ($filters['direction'] ?? 'desc') == 'asc' ? 'up' : 'down' }} ml-1"></i>
                                @endif
                            </a>
                        </th>
                        <th class="py-3 px-4 text-left">
                            <a href="{{ route('admin.users', array_merge($filters ?? [], ['sort' => 'last_login', 'direction' => (($filters['sort'] ?? '') == 'last_login' && ($filters['direction'] ?? 'desc') == 'desc') ? 'asc' : 'desc'])) }}" class="flex items-center">
                                Login Terakhir
                                @if(($filters['sort'] ?? '') == 'last_login')
                                    <i class="fas fa-sort-{{ ($filters['direction'] ?? 'desc') == 'asc' ? 'up' : 'down' }} ml-1"></i>
                                @endif
                            </a>
                        </th>
                        <th class="py-3 px-4 text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    <tr>
                        <td class="py-3 px-4 font-medium">{{ $user->user_id }}</td>
                        <td class="py-3 px-4 font-mono text-xs">{{ Str::limit($user->wallet_address, 16) }}</td>
                        <td class="py-3 px-4">{{ $user->profile?->username ?? '-' }}</td>
                        <td class="py-3 px-4">
                            @if($user->role == 'admin')
                                <span class="clay-badge clay-badge-success">Admin</span>
                            @elseif($user->role == 'community')
                                <span class="clay-badge clay-badge-warning">Community</span>
                            @else
                                <span class="clay-badge clay-badge-secondary">{{ $user->role }}</span>
                            @endif
                        </td>
                        <!-- PERBAIKAN: Tampilkan jumlah interaksi -->
                        <td class="py-3 px-4">
                            <span class="clay-badge clay-badge-info">
                                {{ isset($user->interaction_count) ? $user->interaction_count : $user->interactions->count() }} interaksi
                            </span>
                        </td>
                        <td class="py-3 px-4 text-sm">{{ $user->created_at->format('j M Y') }}</td>
                        <td class="py-3 px-4 text-sm">{{ $user->last_login ? $user->last_login->diffForHumans() : 'Belum pernah' }}</td>
                        <td class="py-3 px-4">
                            <div class="flex space-x-2">
                                <a href="{{ route('admin.users.detail', $user->user_id) }}" class="clay-badge clay-badge-info py-1 px-2 text-xs">
                                    <i class="fas fa-info-circle"></i> Detail
                                </a>
                                <button type="button" class="clay-badge clay-badge-warning py-1 px-2 text-xs" onclick="openEditRoleModal('{{ $user->user_id }}', '{{ $user->role }}')">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-6 px-4 text-center">
                            @if(!empty($filters['role']) || !empty($filters['search']))
                                <p class="text-gray-500">Tidak ada pengguna yang sesuai dengan filter yang dipilih.</p>
                                <a href="{{ route('admin.users') }}" class="clay-button clay-button-secondary mt-2 py-1 px-3 text-sm">
                                    <i class="fas fa-times mr-1"></i> Hapus Filter
                                </a>
                            @else
                                <p class="text-gray-500">Tidak ada pengguna yang ditemukan.</p>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PERBAIKAN: Pagination dengan preserved query parameters -->
        @if($users->hasPages())
            <div class="mt-6">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <div class="mb-4 md:mb-0">
                        <p class="text-sm text-gray-600">
                            Menampilkan {{ $users->firstItem() }} sampai {{ $users->lastItem() }}
                            dari {{ number_format($users->total()) }} pengguna
                            @if(!empty($filters['role']) || !empty($filters['search']))
                                <span class="clay-badge clay-badge-info text-xs ml-1">Hasil Filter</span>
                            @endif
                        </p>
                    </div>

                    <div class="flex space-x-2">
                        {{-- Previous Button --}}
                        @if ($users->onFirstPage())
                            <span class="clay-button clay-button-secondary py-1.5 px-3 text-sm opacity-50 cursor-not-allowed">
                                <i class="fas fa-chevron-left"></i>
                            </span>
                        @else
                            <a href="{{ $users->previousPageUrl() }}" class="clay-button clay-button-secondary py-1.5 px-3 text-sm">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        @endif

                        {{-- Pagination Elements --}}
                        @foreach ($users->getUrlRange(max(1, $users->currentPage() - 2), min($users->lastPage(), $users->currentPage() + 2)) as $page => $url)
                            @if ($page == $users->currentPage())
                                <span class="clay-button clay-button-primary py-1.5 px-3 text-sm">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="clay-button clay-button-secondary py-1.5 px-3 text-sm">{{ $page }}</a>
                            @endif
                        @endforeach

                        {{-- Next Button --}}
                        @if ($users->hasMorePages())
                            <a href="{{ $users->nextPageUrl() }}" class="clay-button clay-button-secondary py-1.5 px-3 text-sm">
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
    @if(!empty($filters['role']) || !empty($filters['search']))
    <div class="clay-card p-4 mb-8 bg-blue-50 border-l-4 border-blue-400">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-sm font-medium text-blue-800">Filter Aktif:</h3>
                <div class="flex flex-wrap gap-2 mt-1">
                    @if(!empty($filters['role']))
                        <span class="clay-badge clay-badge-info text-xs">
                            Role: {{ ucfirst($filters['role']) }}
                        </span>
                    @endif
                    @if(!empty($filters['search']))
                        <span class="clay-badge clay-badge-info text-xs">
                            Pencarian: "{{ $filters['search'] }}"
                        </span>
                    @endif
                </div>
            </div>
            <a href="{{ route('admin.users') }}" class="clay-button clay-button-secondary py-1 px-3 text-sm">
                <i class="fas fa-times mr-1"></i> Hapus Semua Filter
            </a>
        </div>
    </div>
    @endif
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

    document.addEventListener('DOMContentLoaded', function() {
        // PERBAIKAN: Auto-submit form untuk UX yang lebih baik
        const roleSelect = document.getElementById('role');
        const searchInput = document.getElementById('search');
        const sortSelect = document.getElementById('sort');
        const directionSelect = document.getElementById('direction');

        function autoSubmitForm() {
            // Submit form setelah delay kecil
            setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 100);
        }

        // Auto-submit untuk select role
        if (roleSelect) {
            roleSelect.addEventListener('change', autoSubmitForm);
        }

        // Auto-submit untuk sorting options
        if (sortSelect) {
            sortSelect.addEventListener('change', autoSubmitForm);
        }

        if (directionSelect) {
            directionSelect.addEventListener('change', autoSubmitForm);
        }

        // Auto-submit untuk search dengan debounce
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (searchInput.value.length >= 3 || searchInput.value.length === 0) {
                        autoSubmitForm();
                    }
                }, 500); // 500ms debounce
            });
        }
    });
</script>
@endpush

@endsection

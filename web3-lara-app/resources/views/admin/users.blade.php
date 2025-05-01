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

        <form action="{{ route('admin.users') }}" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="role" class="block mb-2 font-medium">Peran</label>
                <select name="role" id="role" class="clay-select w-full">
                    <option value="">-- Semua Peran --</option>
                    <option value="admin" {{ $filters['role'] ?? '' == 'admin' ? 'selected' : '' }}>Admin</option>
                    <option value="community" {{ $filters['role'] ?? '' == 'community' ? 'selected' : '' }}>Community</option>
                </select>
            </div>

            <div>
                <label for="search" class="block mb-2 font-medium">Pencarian</label>
                <input type="text" name="search" id="search" class="clay-input w-full" placeholder="Cari user_id atau wallet..." value="{{ $filters['search'] ?? '' }}">
            </div>

            <div class="flex items-end space-x-2">
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
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="clay-card bg-primary/10 p-4 text-center">
                <div class="text-3xl font-bold mb-2">
                    {{ $users->total() }}
                </div>
                <div class="text-sm">Total Pengguna</div>
            </div>

            @php $adminCount = 0; $communityCount = 0; $otherCount = 0; @endphp
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
                        <th class="py-3 px-4 text-left">Bergabung</th>
                        <th class="py-3 px-4 text-left">Login Terakhir</th>
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
                        <td colspan="7" class="py-6 px-4 text-center">
                            <p class="text-gray-500">Tidak ada pengguna yang ditemukan.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($users->hasPages())
        <div class="mt-6">
            {{ $users->appends(request()->query())->links() }}
        </div>
        @endif
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

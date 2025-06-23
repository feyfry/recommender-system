@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
            <div>
                <h1 class="text-3xl font-bold mb-4 flex items-center">
                    <div class="bg-success/20 p-2 clay-badge mr-3">
                        <i class="fas fa-project-diagram text-success"></i>
                    </div>
                    Manajemen Proyek
                </h1>
                <p class="text-gray-600">Kelola semua proyek cryptocurrency dalam sistem rekomendasi.</p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-filter mr-2 text-secondary"></i>
            Filter Proyek
        </h2>

        <form action="{{ route('admin.projects') }}" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4" id="filterForm">
            <!-- PERBAIKAN: Preserve sort and direction saat filtering -->
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
                <label for="category" class="block mb-2 font-medium">Kategori</label>
                <select name="category" id="category" class="clay-select w-full">
                    <option value="">-- Semua Kategori --</option>
                    @foreach($categories as $category)
                        <option value="{{ $category }}"
                            {{ ($filters['category'] ?? '') == $category ? 'selected' : '' }}>
                            {{ ucfirst($category) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="chain" class="block mb-2 font-medium">Chain</label>
                <select name="chain" id="chain" class="clay-select w-full">
                    <option value="">-- Semua Chain --</option>
                    @foreach($chains as $chain)
                        <option value="{{ $chain }}"
                            {{ ($filters['chain'] ?? '') == $chain ? 'selected' : '' }}>
                            {{ ucfirst($chain) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="search" class="block mb-2 font-medium">Pencarian</label>
                <input type="text" name="search" id="search" class="clay-input w-full"
                    placeholder="Cari nama, simbol, atau ID..."
                    value="{{ $filters['search'] ?? '' }}">
            </div>

            <div class="flex items-end space-x-2">
                <button type="submit" class="clay-button clay-button-primary py-2 px-4">
                    <i class="fas fa-search mr-1"></i> Cari
                </button>
                <a href="{{ route('admin.projects') }}" class="clay-button py-2 px-4">
                    <i class="fas fa-times mr-1"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- PERBAIKAN: Projects Stats dengan statistik yang akurat -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-chart-pie mr-2 text-warning"></i>
            Statistik Proyek
            @if(!empty($filters['category']) || !empty($filters['chain']) || !empty($filters['search']))
                <span class="clay-badge clay-badge-info ml-2 text-xs">Berdasarkan Filter</span>
            @else
                <span class="clay-badge clay-badge-primary ml-2 text-xs">Total Keseluruhan</span>
            @endif
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="clay-card bg-primary/10 p-4 text-center">
                <div class="text-3xl font-bold mb-2">
                    {{ number_format($projectStats['total']) }}
                </div>
                <div class="text-sm">Total Proyek</div>
                @if(!empty($filters['category']) || !empty($filters['chain']) || !empty($filters['search']))
                    <div class="text-xs text-gray-500 mt-1">Hasil Filter</div>
                @endif
            </div>

            <div class="clay-card bg-success/10 p-4 text-center">
                <div class="text-3xl font-bold mb-2">
                    {{ number_format($projectStats['categories']) }}
                </div>
                <div class="text-sm">Kategori</div>
            </div>

            <div class="clay-card bg-info/10 p-4 text-center">
                <div class="text-3xl font-bold mb-2">
                    {{ number_format($projectStats['chains']) }}
                </div>
                <div class="text-sm">Chain</div>
            </div>

            <!-- PERBAIKAN: Statistik Trending yang akurat dari query keseluruhan -->
            <div class="clay-card bg-warning/10 p-4 text-center">
                <div class="text-3xl font-bold mb-2">
                    {{ number_format($projectStats['trending']) }}
                </div>
                <div class="text-sm">Trending (Score > 70)</div>
                @if(!empty($filters['category']) || !empty($filters['chain']) || !empty($filters['search']))
                    <div class="text-xs text-gray-500 mt-1">Dalam Filter</div>
                @endif
            </div>

            <div class="clay-card bg-secondary/10 p-4 text-center">
                <div class="text-3xl font-bold mb-2">
                    {{ number_format($projectStats['popular']) }}
                </div>
                <div class="text-sm">Popular (Score > 70)</div>
                @if(!empty($filters['category']) || !empty($filters['chain']) || !empty($filters['search']))
                    <div class="text-xs text-gray-500 mt-1">Dalam Filter</div>
                @endif
            </div>
        </div>
    </div>

    <!-- Projects List -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-list mr-2 text-info"></i>
            Daftar Proyek
        </h2>

        <div class="overflow-x-auto">
            <table class="clay-table min-w-full">
                <thead>
                    <tr>
                        <th class="py-3 px-4 text-left">ID</th>
                        <th class="py-3 px-4 text-left">Proyek</th>
                        <th class="py-3 px-4 text-left">Harga</th>
                        <th class="py-3 px-4 text-left">24h %</th>
                        <th class="py-3 px-4 text-left">Kategori</th>
                        <th class="py-3 px-4 text-left">Chain</th>
                        <th class="py-3 px-4 text-left">
                            <!-- PERBAIKAN: Preserve filter parameters in sorting links -->
                            <a href="{{ route('admin.projects', array_merge($filters ?? [], ['sort' => 'popularity_score', 'direction' => (($filters['sort'] ?? '') == 'popularity_score' && ($filters['direction'] ?? '') == 'desc') ? 'asc' : 'desc'])) }}" class="flex items-center">
                                Popularitas
                                @if(($filters['sort'] ?? '') == 'popularity_score')
                                    <i class="fas fa-sort-{{ ($filters['direction'] ?? '') == 'asc' ? 'up' : 'down' }} ml-1"></i>
                                @endif
                            </a>
                        </th>
                        <th class="py-3 px-4 text-left">
                            <a href="{{ route('admin.projects', array_merge($filters ?? [], ['sort' => 'trend_score', 'direction' => (($filters['sort'] ?? '') == 'trend_score' && ($filters['direction'] ?? '') == 'desc') ? 'asc' : 'desc'])) }}" class="flex items-center">
                                Trend
                                @if(($filters['sort'] ?? '') == 'trend_score')
                                    <i class="fas fa-sort-{{ ($filters['direction'] ?? '') == 'asc' ? 'up' : 'down' }} ml-1"></i>
                                @endif
                            </a>
                        </th>
                        <th class="py-3 px-4 text-left">
                            <a href="{{ route('admin.projects', array_merge($filters ?? [], ['sort' => 'interactions', 'direction' => (($filters['sort'] ?? '') == 'interactions' && ($filters['direction'] ?? '') == 'desc') ? 'asc' : 'desc'])) }}" class="flex items-center">
                                Interaksi
                                @if(($filters['sort'] ?? '') == 'interactions')
                                    <i class="fas fa-sort-{{ ($filters['direction'] ?? '') == 'asc' ? 'up' : 'down' }} ml-1"></i>
                                @endif
                            </a>
                        </th>
                        <th class="py-3 px-4 text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($projects as $project)
                    <tr>
                        <td class="py-3 px-4 font-mono text-xs">{{ $project->id }}</td>
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                @if($project->image)
                                    <img src="{{ $project->image }}" alt="{{ $project->symbol }}" class="w-8 h-8 rounded-full mr-3">
                                @endif
                                <div>
                                    <div class="font-medium">{{ $project->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $project->symbol }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4 font-medium">{{ $project->formatted_price }}</td>
                        <td class="py-3 px-4 {{ $project->price_change_percentage_24h > 0 ? 'text-success' : 'text-danger' }}">
                            {{ $project->price_change_percentage_24h > 0 ? '+' : '' }}{{ number_format($project->price_change_percentage_24h, 2) }}%
                        </td>
                        <td class="py-3 px-4">{{ $project->clean_primary_category }}</td>
                        <td class="py-3 px-4">{{ $project->clean_chain }}</td>
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                <span class="font-medium mr-2">{{ number_format($project->popularity_score, 1) }}</span>
                                <div class="clay-progress w-12 h-2">
                                    <div class="clay-progress-bar clay-progress-primary" style="width: {{ min(100, $project->popularity_score) }}%"></div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                <span class="font-medium mr-2">{{ number_format($project->trend_score, 1) }}</span>
                                <div class="clay-progress w-12 h-2">
                                    <div class="clay-progress-bar clay-progress-warning" style="width: {{ min(100, $project->trend_score) }}%"></div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            @if(isset($project->interaction_count))
                                <span class="clay-badge clay-badge-info text-xs">{{ $project->interaction_count }}</span>
                            @else
                                <span class="clay-badge clay-badge-secondary text-xs">0</span>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex space-x-2">
                                <a href="{{ route('admin.projects.detail', $project->id) }}" class="clay-badge clay-badge-info py-1 px-2 text-xs">
                                    <i class="fas fa-info-circle"></i> Detail
                                </a>
                                <a href="{{ route('panel.recommendations.project', $project->id) }}" class="clay-badge clay-badge-primary py-1 px-2 text-xs">
                                    <i class="fas fa-eye"></i> Lihat
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="py-6 px-4 text-center">
                            @if(!empty($filters['category']) || !empty($filters['chain']) || !empty($filters['search']))
                                <p class="text-gray-500">Tidak ada proyek yang sesuai dengan filter yang dipilih.</p>
                                <a href="{{ route('admin.projects') }}" class="clay-button clay-button-secondary mt-2 py-1 px-3 text-sm">
                                    <i class="fas fa-times mr-1"></i> Hapus Filter
                                </a>
                            @else
                                <p class="text-gray-500">Tidak ada proyek yang ditemukan.</p>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PERBAIKAN: Pagination dengan preserved query parameters -->
        @if($projects->hasPages())
            <div class="mt-6">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <div class="mb-4 md:mb-0">
                        <p class="text-sm text-gray-600">
                            Menampilkan {{ $projects->firstItem() }} sampai {{ $projects->lastItem() }}
                            dari {{ number_format($projects->total()) }} proyek
                            @if(!empty($filters['category']) || !empty($filters['chain']) || !empty($filters['search']))
                                <span class="clay-badge clay-badge-info text-xs ml-1">Hasil Filter</span>
                            @endif
                        </p>
                    </div>

                    <div class="flex space-x-2">
                        {{-- Previous Button --}}
                        @if ($projects->onFirstPage())
                            <span class="clay-button clay-button-secondary py-1.5 px-3 text-sm opacity-50 cursor-not-allowed">
                                <i class="fas fa-chevron-left"></i>
                            </span>
                        @else
                            <a href="{{ $projects->previousPageUrl() }}" class="clay-button clay-button-secondary py-1.5 px-3 text-sm">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        @endif

                        {{-- Pagination Elements --}}
                        @foreach ($projects->getUrlRange(max(1, $projects->currentPage() - 2), min($projects->lastPage(), $projects->currentPage() + 2)) as $page => $url)
                            @if ($page == $projects->currentPage())
                                <span class="clay-button clay-button-primary py-1.5 px-3 text-sm">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="clay-button clay-button-secondary py-1.5 px-3 text-sm">{{ $page }}</a>
                            @endif
                        @endforeach

                        {{-- Next Button --}}
                        @if ($projects->hasMorePages())
                            <a href="{{ $projects->nextPageUrl() }}" class="clay-button clay-button-secondary py-1.5 px-3 text-sm">
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
    @if(!empty($filters['category']) || !empty($filters['chain']) || !empty($filters['search']))
    <div class="clay-card p-4 mb-8 bg-blue-50 border-l-4 border-blue-400">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-sm font-medium text-blue-800">Filter Aktif:</h3>
                <div class="flex flex-wrap gap-2 mt-1">
                    @if(!empty($filters['category']))
                        <span class="clay-badge clay-badge-info text-xs">
                            Kategori: {{ ucfirst($filters['category']) }}
                        </span>
                    @endif
                    @if(!empty($filters['chain']))
                        <span class="clay-badge clay-badge-info text-xs">
                            Chain: {{ ucfirst($filters['chain']) }}
                        </span>
                    @endif
                    @if(!empty($filters['search']))
                        <span class="clay-badge clay-badge-info text-xs">
                            Pencarian: "{{ $filters['search'] }}"
                        </span>
                    @endif
                </div>
            </div>
            <a href="{{ route('admin.projects') }}" class="clay-button clay-button-secondary py-1 px-3 text-sm">
                <i class="fas fa-times mr-1"></i> Hapus Semua Filter
            </a>
        </div>
    </div>
    @endif

    <!-- Quick Action -->
    <div class="clay-card p-6">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-bolt mr-2 text-warning"></i>
            Aksi Cepat
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <a href="{{ route('admin.data-sync') }}" class="clay-card p-4 bg-success/10 hover:translate-y-[-5px] transition-transform text-center">
                <i class="fas fa-sync text-3xl text-success mb-2"></i>
                <div class="font-bold">Sinkronisasi Data Proyek</div>
                <p class="text-sm mt-1">Perbarui data proyek dari engine rekomendasi</p>
            </a>

            <a href="{{ route('admin.data-sync') }}?action=train" class="clay-card p-4 bg-secondary/10 hover:translate-y-[-5px] transition-transform text-center">
                <i class="fas fa-brain text-3xl text-secondary mb-2"></i>
                <div class="font-bold">Latih Model Rekomendasi</div>
                <p class="text-sm mt-1">Latih model dengan data proyek terbaru</p>
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // PERBAIKAN: Auto-submit form untuk UX yang lebih baik
        const categorySelect = document.getElementById('category');
        const chainSelect = document.getElementById('chain');
        const searchInput = document.getElementById('search');

        function autoSubmitForm() {
            // Submit form setelah delay kecil
            setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 100);
        }

        // Auto-submit untuk select category
        if (categorySelect) {
            categorySelect.addEventListener('change', autoSubmitForm);
        }

        // Auto-submit untuk select chain
        if (chainSelect) {
            chainSelect.addEventListener('change', autoSubmitForm);
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

        // Preserve form state pada page load
        const urlParams = new URLSearchParams(window.location.search);

        // Set selected value untuk kategori
        const categoryParam = urlParams.get('category');
        if (categoryParam && categorySelect) {
            categorySelect.value = categoryParam;
        }

        // Set selected value untuk chain
        const chainParam = urlParams.get('chain');
        if (chainParam && chainSelect) {
            chainSelect.value = chainParam;
        }
    });
</script>
@endpush

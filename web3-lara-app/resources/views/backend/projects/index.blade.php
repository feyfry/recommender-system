@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-4 sm:p-6 mb-6">
        <h1 class="text-3xl font-bold mb-4 flex items-center">
            <div class="bg-info/20 p-2 clay-badge mr-3">
                <i class="fas fa-project-diagram text-info"></i>
            </div>
            Proyek Cryptocurrency
        </h1>
        <p class="text-lg">
            Jelajahi semua proyek cryptocurrency yang tersedia dalam sistem. Interaksi Anda dengan proyek akan membantu sistem rekomendasi memberikan saran yang lebih personal.
        </p>
    </div>

    @if(session('success'))
    <div class="clay-alert clay-alert-success mb-6">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            {{ session('success') }}
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="clay-alert clay-alert-danger mb-6">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            {{ session('error') }}
        </div>
    </div>
    @endif

    @if(session('info'))
    <div class="clay-alert clay-alert-info mb-6">
        <div class="flex items-center">
            <i class="fas fa-info-circle mr-2"></i>
            {{ session('info') }}
        </div>
    </div>
    @endif

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="clay-card bg-primary/10 p-4 text-center">
            <div class="text-2xl font-bold mb-1">{{ number_format($projectsCount['total']) }}</div>
            <div class="text-sm text-gray-600">Total Proyek</div>
        </div>
        <div class="clay-card bg-warning/10 p-4 text-center">
            <div class="text-2xl font-bold mb-1">{{ number_format($projectsCount['trending']) }}</div>
            <div class="text-sm text-gray-600">Proyek Trending</div>
        </div>
        <div class="clay-card bg-success/10 p-4 text-center">
            <div class="text-2xl font-bold mb-1">{{ number_format($projectsCount['popular']) }}</div>
            <div class="text-sm text-gray-600">Proyek Populer</div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="clay-card p-4 mb-6" x-data="{ showFilters: false }">
        <div class="flex justify-between items-center mb-3">
            <h2 class="text-xl font-bold">
                <i class="fas fa-filter mr-2 text-secondary"></i>
                Filter Proyek
            </h2>
            <button @click="showFilters = !showFilters" class="clay-button clay-button-secondary py-1.5 px-3 text-sm">
                <i class="fas" :class="showFilters ? 'fa-eye-slash' : 'fa-eye'"></i>
                <span x-text="showFilters ? ' Sembunyikan Filter' : ' Tampilkan Filter'"></span>
            </button>
        </div>

        <div x-show="showFilters" x-transition>
            <form action="{{ route('panel.projects') }}" method="GET" id="filterForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Kategori Filter -->
                <div>
                    <label for="category" class="block text-sm font-medium mb-1">Kategori</label>
                    <select name="category" id="category" class="clay-select w-full">
                        <option value="">Semua Kategori</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}" {{ ($filters['category'] ?? '') == $category ? 'selected' : '' }}>
                                {{ ucfirst($category) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Chain Filter -->
                <div>
                    <label for="chain" class="block text-sm font-medium mb-1">Blockchain</label>
                    <select name="chain" id="chain" class="clay-select w-full">
                        <option value="">Semua Chain</option>
                        @foreach($chains as $chain)
                            <option value="{{ $chain }}" {{ ($filters['chain'] ?? '') == $chain ? 'selected' : '' }}>
                                {{ ucfirst($chain) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Pencarian -->
                <div>
                    <label for="search" class="block text-sm font-medium mb-1">Pencarian</label>
                    <input type="text" name="search" id="search" class="clay-input w-full" placeholder="Cari nama, simbol..." value="{{ $filters['search'] ?? '' }}">
                </div>

                <!-- Sort -->
                <div>
                    <div class="flex justify-between">
                        <label for="sort" class="block text-sm font-medium mb-1">Urutkan</label>
                        <input type="hidden" name="direction" value="{{ ($filters['direction'] ?? 'desc') == 'desc' ? 'desc' : 'asc' }}">
                        <a href="#" onclick="toggleDirection(); return false;" class="text-sm text-primary mb-1">
                            <i class="fas fa-sort{{ ($filters['direction'] ?? 'desc') == 'desc' ? '-down' : '-up' }}"></i>
                            {{ ($filters['direction'] ?? 'desc') == 'desc' ? 'Turun' : 'Naik' }}
                        </a>
                    </div>
                    <select name="sort" id="sort" class="clay-select w-full">
                        <option value="popularity_score" {{ ($filters['sort'] ?? 'popularity_score') == 'popularity_score' ? 'selected' : '' }}>Popularitas</option>
                        <option value="trend_score" {{ ($filters['sort'] ?? '') == 'trend_score' ? 'selected' : '' }}>Trending</option>
                        <option value="market_cap" {{ ($filters['sort'] ?? '') == 'market_cap' ? 'selected' : '' }}>Market Cap</option>
                        <option value="current_price" {{ ($filters['sort'] ?? '') == 'current_price' ? 'selected' : '' }}>Harga</option>
                        <option value="symbol" {{ ($filters['sort'] ?? '') == 'symbol' ? 'selected' : '' }}>Simbol</option>
                        <option value="name" {{ ($filters['sort'] ?? '') == 'name' ? 'selected' : '' }}>Nama</option>
                    </select>
                </div>

                <div class="col-span-full flex justify-end gap-2">
                    <button type="submit" class="clay-button clay-button-primary">
                        <i class="fas fa-search mr-1"></i> Terapkan Filter
                    </button>
                    <a href="{{ route('panel.projects') }}" class="clay-button clay-button-danger">
                        <i class="fas fa-times mr-1"></i> Reset Filter
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Filters -->
    @if(($filters['category'] ?? false) || ($filters['chain'] ?? false) || ($filters['search'] ?? false))
    <div class="clay-card bg-info/10 p-3 mb-6">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-medium text-sm">Filter aktif:</span>

            @if($filters['category'] ?? false)
            <span class="clay-badge clay-badge-primary">
                Kategori: {{ $filters['category'] }}
            </span>
            @endif

            @if($filters['chain'] ?? false)
            <span class="clay-badge clay-badge-secondary">
                Chain: {{ $filters['chain'] }}
            </span>
            @endif

            @if($filters['search'] ?? false)
            <span class="clay-badge clay-badge-info">
                Pencarian: "{{ $filters['search'] }}"
            </span>
            @endif

            <a href="{{ route('panel.projects') }}" class="clay-badge clay-badge-danger py-1 px-2 text-xs">
                <i class="fas fa-times mr-1"></i> Hapus Semua Filter
            </a>
        </div>
    </div>
    @endif

    <!-- Projects Grid -->
    <div class="clay-card p-4 mb-6">
        <h2 class="text-xl font-bold mb-4">
            <i class="fas fa-list-alt mr-2 text-primary"></i>
            Daftar Proyek
        </h2>

        @if($projects->isEmpty())
            <div class="text-center py-8">
                <p class="text-gray-500 mb-2">Tidak ada proyek yang ditemukan dengan filter yang diterapkan.</p>
                <a href="{{ route('panel.projects') }}" class="clay-button clay-button-primary mt-2">
                    <i class="fas fa-sync mr-1"></i> Reset Filter
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach($projects as $project)
                <div class="clay-card p-4 hover:translate-y-[-5px] transition-transform">
                    <div class="flex items-center mb-3">
                        @if($project->image)
                            <img src="{{ $project->image }}" alt="{{ $project->symbol }}" class="w-10 h-10 rounded-full mr-3">
                        @else
                            <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center mr-3">
                                <span class="text-gray-500 font-bold">{{ strtoupper(substr($project->symbol, 0, 2)) }}</span>
                            </div>
                        @endif
                        <div>
                            <h3 class="font-bold text-base">{{ $project->name }}</h3>
                            <div class="text-sm text-gray-500">{{ strtoupper($project->symbol) }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 mb-3 text-sm">
                        <div class="clay-badge bg-gray-100">
                            <span class="font-medium">Harga:</span>
                            <span>${{ number_format($project->current_price, $project->current_price < 1 ? 8 : 2) }}</span>
                        </div>

                        <div class="clay-badge {{ $project->price_change_percentage_24h > 0 ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger' }}">
                            <span>24h:</span>
                            <span>{{ $project->price_change_percentage_24h > 0 ? '+' : '' }}{{ number_format($project->price_change_percentage_24h, 2) }}%</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 mb-3">
                        @if($project->primary_category)
                            <div class="clay-badge clay-badge-primary text-xs">
                                {{ $project->primary_category }}
                            </div>
                        @endif

                        @if($project->chain)
                            <div class="clay-badge clay-badge-secondary text-xs">
                                {{ $project->chain }}
                            </div>
                        @endif
                    </div>

                    <div class="flex gap-1 justify-between items-center mb-3">
                        <div class="text-xs">
                            <span>Popularitas:</span>
                            <div class="clay-progress w-16 h-2 inline-block align-middle mx-1">
                                <div class="clay-progress-bar bg-primary" style="width: {{ min(100, $project->popularity_score) }}%"></div>
                            </div>
                            <span class="font-medium">{{ number_format($project->popularity_score, 1) }}</span>
                        </div>
                    </div>

                    <div class="flex justify-between items-center">
                        <a href="{{ route('panel.recommendations.project', $project->id) }}" class="clay-button clay-button-primary py-1 px-2 text-xs">
                            <i class="fas fa-info-circle mr-1"></i> Detail
                        </a>
                        <div class="flex gap-1">
                            <!-- IMPROVED: Like button dengan loading state -->
                            <form method="POST" action="{{ route('panel.projects.favorite') }}" class="inline"
                                  onsubmit="handleFormSubmit(this, 'favorite')">
                                @csrf
                                <input type="hidden" name="project_id" value="{{ $project->id }}">
                                <button type="submit" class="clay-button clay-button-secondary py-1 px-2 text-xs favorite-btn"
                                        title="Sukai proyek ini">
                                    <i class="fas fa-heart"></i>
                                </button>
                            </form>

                            <!-- IMPROVED: Add to Portfolio button dengan loading state dan feedback yang lebih baik -->
                            <form method="POST" action="{{ route('panel.projects.add-portfolio') }}" class="inline"
                                  onsubmit="handleFormSubmit(this, 'portfolio')">
                                @csrf
                                <input type="hidden" name="project_id" value="{{ $project->id }}">
                                <button type="submit" class="clay-button clay-button-info py-1 px-2 text-xs portfolio-btn"
                                        title="Tambah ke portfolio & catat transaksi"
                                        data-project-name="{{ $project->name }}"
                                        data-project-symbol="{{ $project->symbol }}">
                                    <i class="fas fa-wallet"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <!-- Info pages -->
                    <div class="mb-4 md:mb-0">
                        <span class="text-sm text-gray-600">
                            Menampilkan {{ $projects->firstItem() }}
                            sampai {{ $projects->lastItem() }}
                            dari {{ $projects->total() }} proyek
                        </span>
                    </div>

                    <!-- Pagination buttons -->
                    <div class="flex justify-center space-x-2">
                        <!-- Previous -->
                        <a href="{{ $projects->previousPageUrl() }}"
                           class="clay-button clay-button-secondary py-1.5 px-3 text-sm {{ !$projects->previousPageUrl() ? 'opacity-50 cursor-not-allowed' : '' }}"
                           {{ !$projects->previousPageUrl() ? 'disabled' : '' }}>
                            <i class="fas fa-chevron-left"></i>
                        </a>

                        <!-- Page Numbers -->
                        @foreach ($projects->getUrlRange(max(1, $projects->currentPage() - 2),
                                                      min($projects->lastPage(), $projects->currentPage() + 2)) as $page => $url)
                            <a href="{{ $url }}"
                               class="clay-button {{ $page == $projects->currentPage() ? 'clay-button-primary' : 'clay-button-secondary' }} py-1.5 px-3 text-sm">
                                {{ $page }}
                            </a>
                        @endforeach

                        <!-- Next -->
                        <a href="{{ $projects->nextPageUrl() }}"
                           class="clay-button clay-button-secondary py-1.5 px-3 text-sm {{ !$projects->nextPageUrl() ? 'opacity-50 cursor-not-allowed' : '' }}"
                           {{ !$projects->nextPageUrl() ? 'disabled' : '' }}>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Tips dan Informasi -->
    <div class="clay-card p-4">
        <h2 class="text-xl font-bold mb-4">
            <i class="fas fa-lightbulb mr-2 text-warning"></i>
            Tips Interaksi
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div class="clay-card p-3 bg-primary/5">
                <h3 class="font-bold mb-2"><i class="fas fa-eye mr-2"></i> Lihat Detail (View)</h3>
                <p>Setiap kali Anda melihat detail proyek, sistem rekomendasi akan mencatat minat Anda dan meningkatkan akurasi rekomendasi.</p>
            </div>

            <div class="clay-card p-3 bg-secondary/5">
                <h3 class="font-bold mb-2"><i class="fas fa-heart mr-2"></i> Sukai (Liked)</h3>
                <p>Sukai proyek untuk menunjukkan minat yang lebih kuat, yang akan meningkatkan kemungkinan proyek serupa direkomendasikan.</p>
            </div>

            <div class="clay-card p-3 bg-info/5">
                <h3 class="font-bold mb-2"><i class="fas fa-wallet mr-2"></i> Portfolio (Portfolio Add)</h3>
                <p>Tambahkan proyek ke portfolio Anda untuk mencatat transaksi dan menandakan bahwa Anda berinteraksi secara signifikan dengan proyek tersebut.</p>
            </div>
        </div>

        <!-- Additional Tips -->
        <div class="mt-4 clay-card bg-warning/10 p-3">
            <h3 class="font-bold mb-2">
                <i class="fas fa-info-circle mr-2 text-warning"></i>
                Cara Menggunakan "Tambah ke Portfolio"
            </h3>
            <ul class="text-sm space-y-1">
                <li>• Klik tombol <i class="fas fa-wallet text-info"></i> untuk menambahkan proyek ke sistem pencatatan transaksi</li>
                <li>• Anda akan diarahkan ke halaman Transaction Management dengan proyek yang sudah dipilih</li>
                <li>• Lengkapi detail transaksi (beli/jual, jumlah, harga) untuk mencatat aktivitas trading Anda</li>
                <li>• Data ini membantu sistem memberikan rekomendasi yang lebih akurat berdasarkan aktivitas trading Anda</li>
            </ul>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg p-6 max-w-sm w-full mx-4 text-center">
        <div class="w-16 h-16 flex items-center justify-center mx-auto mb-4">
            <svg class="animate-spin h-12 w-12 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
        <h3 class="text-lg font-bold mb-2" id="loadingTitle">Memproses...</h3>
        <p class="text-gray-600 text-sm" id="loadingMessage">Sedang memproses permintaan Anda</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function toggleDirection() {
        let directionInput = document.querySelector('input[name="direction"]');
        directionInput.value = directionInput.value === 'desc' ? 'asc' : 'desc';
        document.getElementById('filterForm').submit();
    }

    // IMPROVED: Handle form submission dengan loading state dan feedback yang lebih baik
    function handleFormSubmit(form, actionType) {
        const button = form.querySelector('button[type="submit"]');
        const icon = button.querySelector('i');
        const originalIcon = icon.className;

        // Disable button dan ubah icon
        button.disabled = true;
        icon.className = 'fas fa-spinner fa-spin';

        // Show loading overlay dengan pesan yang sesuai
        showLoadingOverlay(actionType, form);

        // Reset setelah delay jika ada masalah
        setTimeout(() => {
            button.disabled = false;
            icon.className = originalIcon;
            hideLoadingOverlay();
        }, 10000); // 10 detik timeout

        return true; // Allow form submission
    }

    function showLoadingOverlay(actionType, form) {
        const overlay = document.getElementById('loadingOverlay');
        const title = document.getElementById('loadingTitle');
        const message = document.getElementById('loadingMessage');

        if (actionType === 'favorite') {
            title.textContent = 'Menambahkan ke Favorit...';
            message.textContent = 'Sistem sedang mencatat preferensi Anda';
        } else if (actionType === 'portfolio') {
            const projectName = form.querySelector('[data-project-name]')?.dataset.projectName || 'proyek';
            const projectSymbol = form.querySelector('[data-project-symbol]')?.dataset.projectSymbol || '';

            title.textContent = 'Menyiapkan Portfolio...';
            message.textContent = `Mengarahkan ke halaman transaksi untuk ${projectName} (${projectSymbol})`;
        }

        overlay.classList.remove('hidden');
    }

    function hideLoadingOverlay() {
        document.getElementById('loadingOverlay').classList.add('hidden');
    }

    // Auto-hide loading overlay jika halaman dimuat kembali
    window.addEventListener('beforeunload', hideLoadingOverlay);

    // Simple notification system untuk feedback
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 clay-alert clay-alert-${type} max-w-sm transform transition-all duration-300`;
        notification.style.transform = 'translateX(100%)';
        notification.innerHTML = `
            <div class="flex items-center justify-between">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-3">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        document.body.appendChild(notification);

        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);

        // Auto remove after 4 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 300);
            }
        }, 4000);
    }
</script>
@endpush
</document>
</document_content>

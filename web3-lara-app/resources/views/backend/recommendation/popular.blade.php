@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <h1 class="text-3xl font-bold mb-4 flex items-center">
            <div class="bg-success/20 p-2 clay-badge mr-3">
                <i class="fas fa-trophy text-success"></i>
            </div>
            Proyek Popular
        </h1>
        <p class="text-lg">
            Daftar proyek cryptocurrency yang paling populer berdasarkan volume trading, market cap, dan metrik sosial.
        </p>
    </div>

    <!-- Popularity Info -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-info-circle mr-2 text-info"></i>
            Tentang Popularity Score
        </h2>

        <div class="flex flex-col md:flex-row gap-6">
            <div class="md:w-1/2">
                <p class="mb-4">
                    <strong>Popularity Score</strong> adalah metrik yang menunjukkan popularitas proyek berdasarkan:
                </p>
                <ul class="space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Market capitalization</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Volume perdagangan 24 jam</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Jumlah followers di media sosial</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Level adopsi dan penggunaan</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Aktivitas komunitas</span>
                    </li>
                </ul>
            </div>

            <div class="md:w-1/2">
                <div class="clay-card bg-success/10 p-4">
                    <div class="font-bold mb-2 flex items-center">
                        <i class="fas fa-lightbulb mr-2 text-success"></i>
                        Perbedaan dengan Trending
                    </div>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-start">
                            <i class="fas fa-arrow-right text-success mt-1 mr-2"></i>
                            <span><strong>Popular:</strong> Menunjukkan proyek yang secara konsisten memiliki popularitas tinggi</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-arrow-right text-success mt-1 mr-2"></i>
                            <span><strong>Trending:</strong> Menunjukkan proyek yang sedang naik popularitasnya (momentum)</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-arrow-right text-success mt-1 mr-2"></i>
                            <span>Proyek populer cenderung lebih stabil dan established di pasar</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-arrow-right text-success mt-1 mr-2"></i>
                            <span>Proyek trending menunjukkan pergerakan harga dan aktivitas yang lebih dinamis</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Popular Projects List dengan Pagination dan Lazy Loading -->
    <div class="clay-card p-6 mb-8" x-data="{
        loading: true,
        popularProjects: [],
        currentPage: {{ request()->get('page', 1) }},
        perPage: {{ request()->get('per_page', 20) }},
        totalPages: 1
    }">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold flex items-center">
                <i class="fas fa-list mr-2 text-primary"></i>
                Daftar Proyek Popular
            </h2>

            <div class="flex space-x-2">
                <button @click="
                    loading = true;
                    fetch('{{ route('panel.recommendations.popular') }}?format=json&page=' + currentPage + '&per_page=' + perPage)
                        .then(response => response.json())
                        .then(data => {
                            if (Array.isArray(data)) {
                                popularProjects = data;
                                loading = false;
                            } else if (data.data) {
                                popularProjects = data.data;
                                totalPages = data.last_page || 1;
                                loading = false;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            loading = false;
                        });"
                    class="clay-button py-1.5 px-3 text-sm">
                    <i class="fas fa-sync-alt mr-1" :class="{'animate-spin': loading}"></i> Refresh
                </button>

                <select x-model="perPage" @change="
                    loading = true;
                    window.location.href = '{{ route('panel.recommendations.popular') }}?page=' + currentPage + '&per_page=' + perPage;"
                    class="clay-select py-2 px-8 text-sm">
                    <option value="10">10 per halaman</option>
                    <option value="20">20 per halaman</option>
                    <option value="50">50 per halaman</option>
                    <option value="100">100 per halaman</option>
                </select>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div x-show="loading" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-success"></div>
            <p class="mt-4 text-gray-500">Memuat daftar proyek populer...</p>
        </div>

        <!-- Projects Table -->
        <div x-show="!loading" x-init="
            // PERBAIKAN: Inisialisasi data pagination dengan benar
            @if(isset($popularProjects) && !empty($popularProjects))
                @if(is_object($popularProjects) && method_exists($popularProjects, 'lastPage'))
                    totalPages = {{ $popularProjects->lastPage() }};
                    currentPage = {{ $popularProjects->currentPage() }};
                    popularProjects = {{ json_encode($popularProjects->items()) }};
                @elseif(is_array($popularProjects) && isset($popularProjects['data']))
                    popularProjects = {{ json_encode($popularProjects['data']) }};
                    totalPages = {{ $popularProjects['last_page'] ?? 1 }};
                    currentPage = {{ $popularProjects['current_page'] ?? 1 }};
                @else
                    popularProjects = {{ json_encode($popularProjects) }};
                @endif
                loading = false;
            @else
                // PERBAIKAN: Ambil data dengan pagination
                fetch('{{ route('panel.recommendations.popular') }}?page=' + currentPage + '&per_page=' + perPage + '&format=json')
                    .then(response => response.json())
                    .then(data => {
                        // PERBAIKAN: Tangani berbagai format data
                        if (data.data) {
                            popularProjects = data.data;
                            totalPages = data.last_page || 1;
                            currentPage = data.current_page || 1;
                        } else {
                            popularProjects = data;
                        }
                        loading = false;
                    })
                    .catch(error => {
                        console.error('Error loading popular projects:', error);
                        loading = false;
                    });
            @endif
        ">
            <div class="overflow-x-auto">
                <table class="clay-table min-w-full">
                    <thead>
                        <tr>
                            <th class="py-3 px-4 text-left">Rank</th>
                            <th class="py-3 px-4 text-left">Proyek</th>
                            <th class="py-3 px-4 text-left">Harga</th>
                            <th class="py-3 px-4 text-left">24h $</th>
                            <th class="py-3 px-4 text-left">Volume 24h</th>
                            <th class="py-3 px-4 text-left">Market Cap</th>
                            <th class="py-3 px-4 text-left">Popularity Score</th>
                            <th class="py-3 px-4 text-left">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(project, index) in popularProjects" :key="index">
                            <tr>
                                <td class="py-3 px-4 font-bold" x-text="((currentPage - 1) * perPage) + index + 1"></td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <template x-if="project.image">
                                            <img :src="project.image" :alt="project.symbol" class="w-8 h-8 rounded-full mr-3" loading="lazy">
                                        </template>
                                        <div>
                                            <div class="font-medium" x-text="project.name"></div>
                                            <div class="text-xs text-gray-500" x-text="project.symbol"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4 font-medium" x-text="'$' + (project.current_price ? project.current_price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0.00')"></td>
                                <td class="py-3 px-4" :class="(project.price_change_24h || 0) >= 0 ? 'text-success' : 'text-danger'"
                                    x-text="((project.price_change_24h || 0) >= 0 ? '+' : '') +
                                            ((project.price_change_24h || 0).toFixed(2)) + '$'">
                                </td>
                                <td class="py-3 px-4" x-text="'$' + (project.total_volume ? project.total_volume.toLocaleString(undefined, {maximumFractionDigits: 0}) : '0')"></td>
                                <td class="py-3 px-4" x-text="'$' + (project.market_cap ? project.market_cap.toLocaleString(undefined, {maximumFractionDigits: 0}) : '0')"></td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <span class="font-medium mr-2" x-text="(project.popularity_score || 0).toFixed(1)"></span>
                                        <div class="clay-progress w-16 h-2">
                                            <div class="clay-progress-bar clay-progress-success" :style="'width: ' + Math.min(100, project.popularity_score || 0) + '%'"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex space-x-2">
                                        <a :href="'/panel/recommendations/project/' + project.id" class="clay-badge clay-badge-info py-1 px-2 text-xs">
                                            <i class="fas fa-info-circle"></i> Detail
                                        </a>
                                        <form method="POST" action="{{ route('panel.recommendations.add-favorite') }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="project_id" :value="project.id">
                                            <button type="submit" class="clay-badge clay-badge-secondary py-1 px-2 text-xs">
                                                <i class="fas fa-heart"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <template x-if="popularProjects.length === 0">
                            <tr>
                                <td colspan="8" class="py-6 px-4 text-center">
                                    <p class="text-gray-500">Tidak ada data proyek populer</p>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-6 flex justify-between items-center">
                <div>
                    <span class="text-sm text-gray-600">
                        Menampilkan <span x-text="((currentPage - 1) * perPage) + 1"></span>
                        sampai <span x-text="Math.min(currentPage * perPage, ((currentPage - 1) * perPage) + popularProjects.length)"></span>
                        @if(is_object($popularProjects) && method_exists($popularProjects, 'total'))
                        dari <span>{{ $popularProjects->total() }}</span> proyek
                        @elseif(is_array($popularProjects) && isset($popularProjects['total']))
                        dari <span>{{ $popularProjects['total'] }}</span> proyek
                        @else
                        <span x-text="'dari ' + (totalPages * perPage) + ' proyek'"></span>
                        @endif
                    </span>
                </div>

                <div class="flex space-x-2">
                    <button
                        @click="if (currentPage > 1) { loading = true; window.location.href = '{{ route('panel.recommendations.popular') }}?page=' + (currentPage - 1) + '&per_page=' + perPage; }"
                        :disabled="currentPage <= 1"
                        :class="currentPage <= 1 ? 'opacity-50 cursor-not-allowed' : ''"
                        class="clay-button clay-button-secondary py-1.5 px-3 text-sm">
                        <i class="fas fa-chevron-left"></i>
                    </button>

                    <!-- PERBAIKAN: Tampilkan halaman yang benar sesuai totalPages -->
                    <template x-for="page in Math.min(5, totalPages)" :key="page">
                        <button
                            @click="if (page !== currentPage) { loading = true; window.location.href = '{{ route('panel.recommendations.popular') }}?page=' + page + '&per_page=' + perPage; }"
                            :class="page === parseInt(currentPage) ? 'clay-button-primary' : 'clay-button-secondary'"
                            class="clay-button py-1.5 px-3 text-sm">
                            <span x-text="page"></span>
                        </button>
                    </template>

                    <button
                        @click="if (currentPage < totalPages) { loading = true; window.location.href = '{{ route('panel.recommendations.popular') }}?page=' + (parseInt(currentPage) + 1) + '&per_page=' + perPage; }"
                        :disabled="currentPage >= totalPages"
                        :class="currentPage >= totalPages ? 'opacity-50 cursor-not-allowed' : ''"
                        class="clay-button clay-button-secondary py-1.5 px-3 text-sm">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Popularity vs Performance -->
    <div class="clay-card p-6">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-chart-bar mr-2 text-primary"></i>
            Popularitas vs Performa
        </h2>

        <div class="clay-card bg-primary/10 p-4 mb-6">
            <p class="text-sm">
                Popularitas tidak selalu berkorelasi langsung dengan performa investasi. Proyek cryptocurrency dengan
                popularitas tinggi cenderung memiliki kapitalisasi pasar yang besar dan volume perdagangan yang tinggi,
                yang dapat mengurangi volatilitas, namun juga dapat membatasi potensi pertumbuhan dalam jangka pendek.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="clay-card bg-success/10 p-4">
                <h3 class="font-bold mb-2">Kelebihan Proyek Populer</h3>
                <ul class="text-sm space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Likuiditas yang lebih tinggi</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Volatilitas yang lebih rendah</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Komunitas yang lebih kuat</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Transparansi yang lebih baik</span>
                    </li>
                </ul>
            </div>

            <div class="clay-card bg-warning/10 p-4">
                <h3 class="font-bold mb-2">Pertimbangan Investasi</h3>
                <ul class="text-sm space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-exclamation-circle text-warning mt-1 mr-2"></i>
                        <span>Proyek populer mungkin memiliki pertumbuhan yang lebih lambat</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-exclamation-circle text-warning mt-1 mr-2"></i>
                        <span>Popularitas saja bukan indikator kualitas fundamental</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-exclamation-circle text-warning mt-1 mr-2"></i>
                        <span>Lakukan penelitian mendalam terhadap setiap proyek</span>
                    </li>
                </ul>
            </div>

            <div class="clay-card bg-info/10 p-4">
                <h3 class="font-bold mb-2">Strategi Portfolio</h3>
                <ul class="text-sm space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-lightbulb text-info mt-1 mr-2"></i>
                        <span>Kombinasikan proyek populer dengan proyek trending</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-lightbulb text-info mt-1 mr-2"></i>
                        <span>Sesuaikan dengan toleransi risiko Anda</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-lightbulb text-info mt-1 mr-2"></i>
                        <span>Diversifikasi antar kategori dan blockchain</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

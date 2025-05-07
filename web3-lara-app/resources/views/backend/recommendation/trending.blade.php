@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <h1 class="text-3xl font-bold mb-4 flex items-center">
            <div class="bg-warning/20 p-2 clay-badge mr-3">
                <i class="fas fa-fire text-warning"></i>
            </div>
            Proyek Trending
        </h1>
        <p class="text-lg">
            Daftar proyek cryptocurrency yang saat ini sedang trending berdasarkan metrik popularitas, pertumbuhan, dan aktivitas pasar terbaru.
        </p>
    </div>

    <!-- Trending Info -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-info-circle mr-2 text-info"></i>
            Tentang Trending Score
        </h2>

        <div class="flex flex-col md:flex-row gap-6">
            <div class="md:w-1/2">
                <p class="mb-4">
                    <strong>Trending Score</strong> dihitung berdasarkan kombinasi beberapa faktor berikut:
                </p>
                <ul class="space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Perubahan harga (1h, 24h, 7d)</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Pertumbuhan volume perdagangan</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Peningkatan aktivitas sosial media</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Aktivitas developer</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Pertumbuhan jumlah pengguna/holder</span>
                    </li>
                </ul>
            </div>

            <div class="md:w-1/2">
                <div class="clay-card bg-warning/10 p-4">
                    <div class="font-bold mb-2 flex items-center">
                        <i class="fas fa-bolt mr-2 text-warning"></i>
                        Tips Penggunaan
                    </div>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-start">
                            <i class="fas fa-arrow-right text-warning mt-1 mr-2"></i>
                            <span>Proyek trending sering menunjukkan momentum jangka pendek yang kuat</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-arrow-right text-warning mt-1 mr-2"></i>
                            <span>Lakukan penelitian tambahan sebelum berinvestasi pada proyek trending</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-arrow-right text-warning mt-1 mr-2"></i>
                            <span>Gunakan indikator teknikal dan analisis fundamental sebagai pelengkap</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-arrow-right text-warning mt-1 mr-2"></i>
                            <span>Perhatikan bahwa tren dapat berubah dengan cepat di pasar crypto</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Trending Projects List dengan Pagination dan Lazy Loading -->
    <div class="clay-card p-6 mb-8" x-data="{
        loading: true,
        trendingProjects: [],
        currentPage: {{ request()->get('page', 1) }},
        perPage: {{ request()->get('per_page', 20) }},
        totalPages: 1
    }">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold flex items-center">
                <i class="fas fa-list mr-2 text-primary"></i>
                Daftar Proyek Trending
            </h2>

            <div class="flex space-x-2">
                <button @click="
                    loading = true;
                    fetch('{{ route('panel.recommendations.trending-refresh') }}')
                        .then(response => response.json())
                        .then(data => {
                            if (Array.isArray(data)) {
                                trendingProjects = data;
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
                    window.location.href = '{{ route('panel.recommendations.trending') }}?page=' + currentPage + '&per_page=' + perPage;"
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
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-warning"></div>
            <p class="mt-4 text-gray-500">Memuat daftar proyek trending...</p>
        </div>

        <!-- Projects Table -->
        <div x-show="!loading" x-init="
            // PERBAIKAN: Inisialisasi data pagination dengan benar
            @if(isset($trendingProjects) && !empty($trendingProjects))
                @if(is_object($trendingProjects) && method_exists($trendingProjects, 'lastPage'))
                    totalPages = {{ $trendingProjects->lastPage() }};
                    currentPage = {{ $trendingProjects->currentPage() }};
                    trendingProjects = {{ json_encode($trendingProjects->items()) }};
                @elseif(is_array($trendingProjects) && isset($trendingProjects['data']))
                    trendingProjects = {{ json_encode($trendingProjects['data']) }};
                    totalPages = {{ $trendingProjects['last_page'] ?? 1 }};
                    currentPage = {{ $trendingProjects['current_page'] ?? 1 }};
                @else
                    trendingProjects = {{ json_encode($trendingProjects) }};
                @endif
                loading = false;
            @else
                // PERBAIKAN: Ambil data dengan pagination
                fetch('{{ route('panel.recommendations.trending') }}?page=' + currentPage + '&per_page=' + perPage + '&format=json')
                    .then(response => response.json())
                    .then(data => {
                        // PERBAIKAN: Tangani berbagai format data
                        if (data.data) {
                            trendingProjects = data.data;
                            totalPages = data.last_page || 1;
                            currentPage = data.current_page || 1;
                        } else {
                            trendingProjects = data;
                        }
                        loading = false;
                    })
                    .catch(error => {
                        console.error('Error loading trending projects:', error);
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
                            <th class="py-3 px-4 text-left">7d %</th>
                            <th class="py-3 px-4 text-left">Volume 24h</th>
                            <th class="py-3 px-4 text-left">Market Cap</th>
                            <th class="py-3 px-4 text-left">Trend Score</th>
                            <th class="py-3 px-4 text-left">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(project, index) in trendingProjects" :key="index">
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
                                <td class="py-3 px-4" :class="(project.price_change_percentage_7d_in_currency || 0) > 0 ? 'text-success' : 'text-danger'"
                                    x-text="((project.price_change_percentage_7d_in_currency || 0) > 0 ? '+' : '') + ((project.price_change_percentage_7d_in_currency || 0).toFixed(2)) + '%'"></td>
                                <td class="py-3 px-4" x-text="'$' + (project.total_volume ? project.total_volume.toLocaleString(undefined, {maximumFractionDigits: 0}) : '0')"></td>
                                <td class="py-3 px-4" x-text="'$' + (project.market_cap ? project.market_cap.toLocaleString(undefined, {maximumFractionDigits: 0}) : '0')"></td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <span class="font-medium mr-2" x-text="(project.trend_score || 0).toFixed(1)"></span>
                                        <div class="clay-progress w-16 h-2">
                                            <div class="clay-progress-bar clay-progress-warning" :style="'width: ' + Math.min(100, project.trend_score || 0) + '%'"></div>
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

                        <template x-if="trendingProjects.length === 0">
                            <tr>
                                <td colspan="9" class="py-6 px-4 text-center">
                                    <p class="text-gray-500">Tidak ada data proyek trending</p>
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
                        sampai <span x-text="Math.min(currentPage * perPage, ((currentPage - 1) * perPage) + trendingProjects.length)"></span>
                        @if(is_object($trendingProjects) && method_exists($trendingProjects, 'total'))
                        dari <span>{{ $trendingProjects->total() }}</span> proyek
                        @elseif(is_array($trendingProjects) && isset($trendingProjects['total']))
                        dari <span>{{ $trendingProjects['total'] }}</span> proyek
                        @else
                        <span x-text="'dari ' + (totalPages * perPage) + ' proyek'"></span>
                        @endif
                    </span>
                </div>

                <div class="flex space-x-2">
                    <button
                        @click="if (currentPage > 1) { loading = true; window.location.href = '{{ route('panel.recommendations.trending') }}?page=' + (currentPage - 1) + '&per_page=' + perPage; }"
                        :disabled="currentPage <= 1"
                        :class="currentPage <= 1 ? 'opacity-50 cursor-not-allowed' : ''"
                        class="clay-button clay-button-secondary py-1.5 px-3 text-sm">
                        <i class="fas fa-chevron-left mr-1"></i> Sebelumnya
                    </button>

                    <!-- PERBAIKAN: Tampilkan halaman yang benar sesuai totalPages -->
                    <template x-for="page in Math.min(5, totalPages)" :key="page">
                        <button
                            @click="if (page !== currentPage) { loading = true; window.location.href = '{{ route('panel.recommendations.trending') }}?page=' + page + '&per_page=' + perPage; }"
                            :class="page === parseInt(currentPage) ? 'clay-button-primary' : 'clay-button-secondary'"
                            class="clay-button py-1.5 px-3 text-sm">
                            <span x-text="page"></span>
                        </button>
                    </template>

                    <button
                        @click="if (currentPage < totalPages) { loading = true; window.location.href = '{{ route('panel.recommendations.trending') }}?page=' + (parseInt(currentPage) + 1) + '&per_page=' + perPage; }"
                        :disabled="currentPage >= totalPages"
                        :class="currentPage >= totalPages ? 'opacity-50 cursor-not-allowed' : ''"
                        class="clay-button clay-button-secondary py-1.5 px-3 text-sm">
                        Selanjutnya <i class="fas fa-chevron-right ml-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

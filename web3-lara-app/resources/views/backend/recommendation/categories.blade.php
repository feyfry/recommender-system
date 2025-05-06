@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <h1 class="text-3xl font-bold mb-4 flex items-center">
            <div class="bg-info/20 p-2 clay-badge mr-3">
                <i class="fas fa-tags text-info"></i>
            </div>
            Kategori
        </h1>
        <p class="text-lg">
            Telusuri rekomendasi proyek cryptocurrency berdasarkan kategori untuk menemukan investasi yang sesuai dengan minat Anda.
        </p>
    </div>

    <!-- Category Selection dengan Lazy Loading -->
    <div class="clay-card p-6 mb-8" x-data="{ categoriesLoaded: false, allCategories: [], currentCategory: '{{ $selectedCategory ?? 'defi' }}' }">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-filter mr-2 text-primary"></i>
            Pilih Kategori
        </h2>

        <!-- Loading Indicator -->
        <div x-show="!categoriesLoaded" class="py-4 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-primary"></div>
            <p class="mt-2 text-gray-500">Memuat daftar kategori...</p>
        </div>

        <!-- Category Grid -->
        <div x-show="categoriesLoaded" x-init="
            @if(isset($categories) && !empty($categories))
                allCategories = {{ json_encode($categories) }};
                categoriesLoaded = true;
            @else
                fetch('{{ route('panel.recommendations.categories') }}?format=json&loadCategories=true')
                    .then(response => response.json())
                    .then(data => {
                        allCategories = data.categories || [];
                        categoriesLoaded = true;
                    })
                    .catch(error => {
                        console.error('Error loading categories:', error);
                        allCategories = ['defi', 'nft', 'gaming', 'layer1', 'layer2']; // Fallback
                        categoriesLoaded = true;
                    });
            @endif
        ">
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                <template x-for="category in allCategories" :key="category">
                    <a :href="'{{ route('panel.recommendations.categories') }}?category=' + category.toLowerCase()"
                       :class="category.toLowerCase() == currentCategory ? 'bg-info/20 border-2 border-info' : 'bg-info/5'"
                       class="clay-card p-3 text-center hover:translate-y-[-2px] transition-transform">
                        <div class="font-bold mb-1" x-text="category"></div>
                    </a>
                </template>

                <template x-if="allCategories.length === 0">
                    <div class="col-span-full clay-card p-4 text-center">
                        <p>Tidak ada kategori yang tersedia.</p>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Category Description -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-info-circle mr-2 text-info"></i>
            Tentang Kategori: <span class="capitalize">{{ $selectedCategory ?? 'Pilih kategori' }}</span>
        </h2>

        <div class="clay-card bg-info/10 p-4">
            @if($selectedCategory == 'defi')
                <p>
                    <strong>DeFi (Decentralized Finance)</strong> adalah ekosistem aplikasi keuangan yang dibangun di atas blockchain.
                    Proyek DeFi bertujuan menyediakan layanan keuangan seperti pinjaman, tabungan, asuransi, dan pertukaran tanpa
                    bergantung pada perantara tradisional seperti bank dan lembaga keuangan lainnya.
                </p>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Lending</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">DEX</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Yield Farming</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Stablecoins</div>
                </div>
            @elseif($selectedCategory == 'nft')
                <p>
                    <strong>NFT (Non-Fungible Token)</strong> adalah token digital unik yang mewakili kepemilikan aset digital atau fisik.
                    Proyek NFT mencakup platform untuk menciptakan, memperdagangkan, dan menggunakan token yang tidak dapat dipertukarkan,
                    seperti karya seni digital, koleksi, game item, dan aset virtual lainnya.
                </p>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Marketplace</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Digital Art</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Collectibles</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Virtual Land</div>
                </div>
            @elseif($selectedCategory == 'gaming')
                <p>
                    <strong>Gaming</strong> atau <strong>GameFi</strong> menggabungkan game dengan mekanisme finansial blockchain.
                    Proyek ini menawarkan game di mana pemain dapat memiliki, memperdagangkan, dan mendapatkan keuntungan dari aset dalam game.
                    Model play-to-earn memungkinkan pemain menghasilkan cryptocurrency melalui aktivitas dalam game.
                </p>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Play-to-Earn</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Metaverse</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">In-Game Assets</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Game Guilds</div>
                </div>
            @elseif($selectedCategory == 'layer1')
                <p>
                    <strong>Layer-1</strong> adalah blockchain dasar atau protokol utama seperti Bitcoin, Ethereum, dan Solana.
                    Proyek ini menyediakan infrastruktur dasar untuk aplikasi terdesentralisasi (dApps) dan memiliki
                    mekanisme konsensus sendiri serta keamanan jaringan.
                </p>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Smart Contracts</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Consensus Mechanisms</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Network Security</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Native Crypto</div>
                </div>
            @elseif($selectedCategory == 'layer2')
                <p>
                    <strong>Layer-2</strong> adalah solusi yang dibangun di atas blockchain Layer-1 untuk meningkatkan skalabilitas dan efisiensi.
                    Proyek ini fokus pada pengolahan transaksi off-chain, mengurangi biaya transaksi, dan meningkatkan
                    kapasitas pemrosesan transaksi tanpa mengorbankan keamanan layer-1.
                </p>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Rollups</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">State Channels</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Sidechains</div>
                    <div class="clay-badge clay-badge-info py-1 px-2 inline-block">Plasma</div>
                </div>
            @else
                <p>
                    Pilih kategori dari daftar di atas untuk melihat deskripsi dan proyek terkait.
                    Kategori membantu Anda menemukan proyek cryptocurrency yang sesuai dengan minat dan strategi investasi Anda.
                </p>
            @endif
        </div>
    </div>

    <!-- Category Projects dengan Lazy Loading dan Pagination -->
    <div class="clay-card p-6 mb-8" x-data="{
        loading: true,
        categoryProjects: [],
        currentPage: 1,
        perPage: 16,
        totalPages: 1
    }">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold flex items-center">
                <i class="fas fa-list mr-2 text-primary"></i>
                Proyek <span class="capitalize">{{ $selectedCategory ?? 'Kategori' }}</span>
            </h2>

            <div class="flex space-x-2">
                <button
                    @click="
                        loading = true;
                        fetch('{{ route('panel.recommendations.categories') }}?category={{ $selectedCategory }}&format=json&refresh=true')
                            .then(response => response.json())
                            .then(data => {
                                categoryProjects = data.projects || [];
                                totalPages = data.total_pages || 1;
                                loading = false;
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
                    fetch('{{ route('panel.recommendations.categories') }}?category={{ $selectedCategory }}&format=json&per_page=' + perPage)
                        .then(response => response.json())
                        .then(data => {
                            categoryProjects = data.projects || [];
                            totalPages = data.total_pages || 1;
                            loading = false;
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            loading = false;
                        });"
                    class="clay-select py-1.5 px-2 text-sm">
                    <option value="8">8 per halaman</option>
                    <option value="16" selected>16 per halaman</option>
                    <option value="32">32 per halaman</option>
                </select>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div x-show="loading" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary"></div>
            <p class="mt-4 text-gray-500">Memuat proyek kategori...</p>
        </div>

        <!-- Projects Grid -->
        <div x-show="!loading" x-init="
            @if(isset($categoryRecommendations) && !empty($categoryRecommendations))
                categoryProjects = {{ json_encode($categoryRecommendations) }};
                loading = false;
            @else
                fetch('{{ route('panel.recommendations.categories') }}?category={{ $selectedCategory }}&format=json')
                    .then(response => response.json())
                    .then(data => {
                        categoryProjects = data.projects || [];
                        totalPages = data.total_pages || 1;
                        loading = false;
                    })
                    .catch(error => {
                        console.error('Error loading category projects:', error);
                        loading = false;
                    });
            @endif">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <template x-for="(project, index) in categoryProjects" :key="index">
                    <div class="clay-card p-4 hover:translate-y-[-5px] transition-transform">
                        <div class="font-bold text-lg mb-2" x-text="project.name + ' (' + project.symbol + ')'"></div>
                        <div class="flex justify-between mb-2 text-sm">
                            <span x-text="'$' + (project.current_price ? project.current_price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0.00')"></span>
                            <span :class="(project.price_change_percentage_24h || 0) > 0 ? 'text-success' : 'text-danger'"
                                  x-text="((project.price_change_percentage_24h || 0) > 0 ? '+' : '') +
                                          ((project.price_change_percentage_24h || 0).toFixed(2)) + '$'"></span>
                        </div>
                        <div class="clay-badge clay-badge-info mb-3" x-text="project.chain || 'Multiple'"></div>
                        <div class="flex justify-between items-center">
                            <div class="text-xs font-medium">Score: <span class="text-info"
                                x-text="(project.recommendation_score || 0).toFixed(2)"></span></div>
                            <div class="flex space-x-2">
                                <a :href="'/panel/recommendations/project/' + project.id" class="clay-badge clay-badge-info py-1 px-2 text-xs">
                                    <i class="fas fa-info-circle"></i>
                                </a>
                                <form method="POST" action="{{ route('panel.recommendations.add-favorite') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="project_id" :value="project.id">
                                    <button type="submit" class="clay-badge clay-badge-secondary py-1 px-2 text-xs">
                                        <i class="fas fa-heart"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </template>

                <template x-if="categoryProjects.length === 0">
                    <div class="col-span-full clay-card p-6 text-center">
                        <p>Tidak ada proyek yang tersedia untuk kategori ini.</p>
                        <p class="text-sm mt-2 text-gray-500">Coba pilih kategori lain atau kembali nanti.</p>
                    </div>
                </template>
            </div>

            <!-- Pagination -->
            <div class="mt-6 flex justify-center" x-show="totalPages > 1">
                <div class="flex space-x-2">
                    <button
                        @click="
                            if (currentPage > 1) {
                                currentPage--;
                                loading = true;
                                fetch('{{ route('panel.recommendations.categories') }}?category={{ $selectedCategory }}&format=json&page=' + currentPage + '&per_page=' + perPage)
                                    .then(response => response.json())
                                    .then(data => {
                                        categoryProjects = data.projects || [];
                                        loading = false;
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        loading = false;
                                    });
                            }"
                        :disabled="currentPage <= 1"
                        :class="currentPage <= 1 ? 'opacity-50 cursor-not-allowed' : ''"
                        class="clay-button clay-button-secondary py-1.5 px-3 text-sm">
                        <i class="fas fa-chevron-left mr-1"></i> Sebelumnya
                    </button>

                    <template x-for="page in Math.min(5, totalPages)">
                        <button
                            @click="
                                if (page !== currentPage) {
                                    currentPage = page;
                                    loading = true;
                                    fetch('{{ route('panel.recommendations.categories') }}?category={{ $selectedCategory }}&format=json&page=' + currentPage + '&per_page=' + perPage)
                                        .then(response => response.json())
                                        .then(data => {
                                            categoryProjects = data.projects || [];
                                            loading = false;
                                        })
                                        .catch(error => {
                                            console.error('Error:', error);
                                            loading = false;
                                        });
                                }"
                            :class="page === currentPage ? 'clay-button-primary' : 'clay-button-secondary'"
                            class="clay-button py-1.5 px-3 text-sm">
                            <span x-text="page"></span>
                        </button>
                    </template>

                    <button
                        @click="
                            if (currentPage < totalPages) {
                                currentPage++;
                                loading = true;
                                fetch('{{ route('panel.recommendations.categories') }}?category={{ $selectedCategory }}&format=json&page=' + currentPage + '&per_page=' + perPage)
                                    .then(response => response.json())
                                    .then(data => {
                                        categoryProjects = data.projects || [];
                                        loading = false;
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        loading = false;
                                    });
                            }"
                        :disabled="currentPage >= totalPages"
                        :class="currentPage >= totalPages ? 'opacity-50 cursor-not-allowed' : ''"
                        class="clay-button clay-button-secondary py-1.5 px-3 text-sm">
                        Selanjutnya <i class="fas fa-chevron-right ml-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Investment Strategy dengan Lazy Loading -->
    <div class="clay-card p-6" x-data="{ strategyLoaded: false }">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-chess mr-2 text-secondary"></i>
            Strategi Investasi Berbasis Kategori
        </h2>

        <!-- Loading Indicator -->
        <div x-show="!strategyLoaded" class="py-6 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-secondary"></div>
            <p class="mt-2 text-gray-500">Memuat strategi investasi...</p>
        </div>

        <!-- Strategy Content -->
        <div x-show="strategyLoaded" x-init="setTimeout(() => strategyLoaded = true, 300)" class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="clay-card bg-success/10 p-4">
                <h3 class="font-bold mb-2">Portofolio Terdiversifikasi</h3>
                <p class="text-sm mb-3">
                    Mengalokasikan investasi di berbagai kategori untuk mengurangi risiko dan memanfaatkan pertumbuhan di berbagai sektor.
                </p>
                <div class="clay-progress h-3 mb-2">
                    <div class="clay-progress-bar bg-primary" style="width: 25%"></div>
                    <div class="clay-progress-bar bg-secondary" style="width: 15%"></div>
                    <div class="clay-progress-bar bg-success" style="width: 20%"></div>
                    <div class="clay-progress-bar bg-warning" style="width: 15%"></div>
                    <div class="clay-progress-bar bg-info" style="width: 25%"></div>
                </div>
                <div class="text-xs text-gray-600">
                    <span class="inline-block mr-3">Layer-1: 25%</span>
                    <span class="inline-block mr-3">DeFi: 25%</span>
                    <span class="inline-block mr-3">Gaming: 20%</span>
                    <span class="inline-block mr-3">NFT: 15%</span>
                    <span class="inline-block">Layer-2: 15%</span>
                </div>
            </div>

            <div class="clay-card bg-warning/10 p-4">
                <h3 class="font-bold mb-2">Faktor yang Perlu Dipertimbangkan</h3>
                <ul class="text-sm space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-warning mt-1 mr-2"></i>
                        <span>Potensi pertumbuhan kategori</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-warning mt-1 mr-2"></i>
                        <span>Adopsi dan penggunaan di dunia nyata</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-warning mt-1 mr-2"></i>
                        <span>Dukungan komunitas dan pengembang</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-warning mt-1 mr-2"></i>
                        <span>Regulasi dan tantangan khusus kategori</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-warning mt-1 mr-2"></i>
                        <span>Tren dan arah perkembangan teknologi</span>
                    </li>
                </ul>
            </div>

            <div class="clay-card bg-info/10 p-4">
                <h3 class="font-bold mb-2">Pendekatan Sesuai Profil Risiko</h3>
                <div class="space-y-3 text-sm">
                    <div>
                        <p class="font-medium text-success">Risiko Rendah:</p>
                        <p>Fokus pada Layer-1 established, stablecoin, dan proyek DeFi dengan sejarah panjang.</p>
                    </div>
                    <div>
                        <p class="font-medium text-warning">Risiko Menengah:</p>
                        <p>Campuran Layer-1, DeFi, dan NFT dengan rekam jejak yang terbukti.</p>
                    </div>
                    <div>
                        <p class="font-medium text-danger">Risiko Tinggi:</p>
                        <p>Alokasi lebih besar untuk GameFi, NFT, dan proyek inovatif baru.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

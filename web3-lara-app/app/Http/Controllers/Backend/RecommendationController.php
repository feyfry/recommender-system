<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Interaction;
use App\Models\Project;
use App\Models\Recommendation;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecommendationController extends Controller
{
    /**
     * URL API untuk rekomendasi
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * Konstruktor untuk mengatur URL API
     */
    public function __construct()
    {
        $this->apiUrl = env('RECOMMENDATION_API_URL', 'http://localhost:8001');
    }

    /**
     * Menampilkan halaman dashboard rekomendasi
     */
    public function index()
    {
        $user   = Auth::user();
        $userId = $user->user_id;

        // DIOPTIMALKAN: Gunakan cara cache yang konsisten
        $personalRecommendations = Cache::remember("rec_personal_{$userId}_10", 15, function () use ($userId) {
            $recommendations = $this->getPersonalRecommendations($userId, 'hybrid', 10);
            return $this->normalizeRecommendationData($recommendations);
        });

        // Cache untuk data yang sering diakses
        $trendingProjects = Cache::remember('rec_trending_8', 30, function () {
            return $this->getTrendingProjects(8);
        });

        $popularProjects = Cache::remember('rec_popular_8', 30, function () {
            return $this->getPopularProjects(8);
        });

        // DIOPTIMALKAN: Batasi jumlah interaksi yang dimuat
        $interactions = Cache::remember("rec_interactions_{$userId}", 10, function () use ($userId) {
            return Interaction::forUser($userId)
                ->with(['project' => function ($query) {
                    // Hanya select kolom yang dibutuhkan
                    $query->select('id', 'name', 'symbol', 'image');
                }])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        });

        return view('backend.recommendation.index', [
            'personalRecommendations' => $personalRecommendations,
            'trendingProjects'        => $trendingProjects,
            'popularProjects'         => $popularProjects,
            'interactions'            => $interactions,
        ]);
    }

    /**
     * Mendapatkan rekomendasi personal untuk pengguna
     * DIOPTIMALKAN: Menambahkan parameter filtering (kategori, chain, strict_filter, dll)
     */
    public function personal(Request $request)
    {
        $user   = Auth::user();
        $userId = $user->user_id;

        // Ambil parameter filter
        $modelType = $request->input('model_type', 'hybrid');
        $category = $request->input('category');
        $chain = $request->input('chain');
        $limit = $request->input('limit', 10);
        $strictFilter = $request->boolean('strict_filter', false);

        // Ambil format jika ada (untuk AJAX requests)
        $format = $request->input('format');

        // DIOPTIMALKAN: Deteksi cold-start
        $interactionCount = Cache::remember("user_interactions_count_{$userId}", 30, function () use ($userId) {
            return Interaction::where('user_id', $userId)->count();
        });

        $isColdStart = $interactionCount < 10; // Jika interaksi kurang dari 10, anggap cold-start

        // Dapatkan daftar kategori dan chain untuk dropdown
        $categories = Cache::remember('all_categories', 60, function () { // 1 jam
            return $this->getCategories();
        });

        $chains = Cache::remember('all_chains', 60, function () { // 1 jam
            return $this->getChains();
        });

        // PERBAIKAN: Buat cache key yang menyertakan semua parameter filter
        $hybridCacheKey = "rec_personal_hybrid_{$userId}_" . md5(json_encode([
            'limit' => $limit,
            'category' => $category,
            'chain' => $chain,
            'strict' => $strictFilter
        ]));

        $fecfCacheKey = "rec_personal_fecf_{$userId}_" . md5(json_encode([
            'limit' => $limit,
            'category' => $category,
            'chain' => $chain,
            'strict' => $strictFilter
        ]));

        $ncfCacheKey = "rec_personal_ncf_{$userId}_" . md5(json_encode([
            'limit' => $limit,
            'category' => $category,
            'chain' => $chain,
            'strict' => $strictFilter
        ]));

        // Ambil rekomendasi untuk semua model dengan parameter filter yang sama
        $hybridRecommendations = Cache::remember($hybridCacheKey, 15, function () use ($userId, $limit, $category, $chain, $strictFilter) {
            $recommendations = $this->getPersonalRecommendations($userId, 'hybrid', $limit, $category, $chain, $strictFilter);
            return $this->normalizeRecommendationData($recommendations);
        });

        $fecfRecommendations = Cache::remember($fecfCacheKey, 15, function () use ($userId, $limit, $category, $chain, $strictFilter) {
            $recommendations = $this->getPersonalRecommendations($userId, 'fecf', $limit, $category, $chain, $strictFilter);
            return $this->normalizeRecommendationData($recommendations);
        });

        $ncfRecommendations = Cache::remember($ncfCacheKey, 15, function () use ($userId, $limit, $category, $chain, $strictFilter) {
            $recommendations = $this->getPersonalRecommendations($userId, 'ncf', $limit, $category, $chain, $strictFilter);
            return $this->normalizeRecommendationData($recommendations);
        });

        // Jika format JSON diminta (untuk AJAX requests), kembalikan data sebagai JSON
        if ($format === 'json') {
            $modelRequested = $request->input('model', 'hybrid');

            switch ($modelRequested) {
                case 'fecf':
                    return response()->json($fecfRecommendations);
                case 'ncf':
                    return response()->json($ncfRecommendations);
                case 'hybrid':
                default:
                    return response()->json($hybridRecommendations);
            }
        }

        // Untuk request normal (HTML), tampilkan view
        return view('backend.recommendation.personal', [
            'hybridRecommendations' => $hybridRecommendations,
            'fecfRecommendations'   => $fecfRecommendations,
            'ncfRecommendations'    => $ncfRecommendations,
            'interactions'          => $this->getUserInteractions($userId, 10),
            'user'                  => $user,
            'isColdStart'           => $isColdStart,
            'categories'            => $categories,
            'chains'                => $chains,
            'selectedCategory'      => $category,
            'selectedChain'         => $chain,
            'strictFilter'          => $strictFilter,
        ]);
    }

    /**
     * DIOPTIMALKAN: Mendapatkan interaksi pengguna dengan paginasi dan caching
     */
    private function getUserInteractions($userId, $limit = 10)
    {
        return Cache::remember("user_interactions_{$userId}_{$limit}", 10, function () use ($userId, $limit) {
            return Interaction::forUser($userId)
                ->select(['id', 'user_id', 'project_id', 'interaction_type', 'created_at'])
                ->with(['project' => function ($query) {
                    $query->select(['id', 'name', 'symbol', 'image']);
                }])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Endpoint kategori untuk mendapatkan daftar kategori atau redirect ke halaman rekomendasi personal dengan filter kategori
     * PERBAIKAN: Perbaiki implementasi categories untuk mengembalikan data yang benar
     */
    public function categories(Request $request)
    {
        // PERBAIKAN: Untuk request AJAX atau jika hanya meminta daftar kategori
        if ($request->input('format') === 'json' || $request->input('loadCategories') === 'true') {
            $categories = $this->getCategories();

            return response()->json([
                'categories' => $categories
            ]);
        }

        // PERBAIKAN: Untuk request normal, redirect ke personal dengan filter kategori
        $category = $request->input('category', 'defi');
        return redirect()->route('panel.recommendations.personal', [
            'category' => $category
        ]);
    }

    /**
     * Endpoint chain untuk mendapatkan daftar chain atau redirect ke halaman rekomendasi personal dengan filter chain
     * PERBAIKAN: Perbaiki implementasi chains untuk mengembalikan data yang benar
     */
    public function chains(Request $request)
    {
        // PERBAIKAN: Untuk request AJAX atau jika hanya meminta daftar chain
        if ($request->input('format') === 'json' || $request->input('part') === 'chains_list') {
            $chains = $this->getChains();

            return response()->json([
                'chains' => $chains
            ]);
        }

        // PERBAIKAN: Untuk request normal, redirect ke personal dengan filter chain
        $chain = $request->input('chain', 'ethereum');
        return redirect()->route('panel.recommendations.personal', [
            'chain' => $chain
        ]);
    }

    /**
     * Menampilkan halaman proyek trending dengan pagination
     */
    public function trending(Request $request)
    {
        // Ambil parameter pagination dari request
        $page    = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);

        // Format JSON untuk AJAX request
        if ($request->has('format') && $request->format === 'json') {
            $trendingProjects = $this->getTrendingProjects($perPage, $page);
            return response()->json($trendingProjects);
        }

        // DIOPTIMALKAN: Cache untuk data yang sering diakses dengan parameter pagination
        $cacheKey         = "rec_trending_{$perPage}_page_{$page}";
        $trendingProjects = Cache::remember($cacheKey, 30, function () use ($perPage, $page) {
            return $this->getTrendingProjects($perPage, $page);
        });

        return view('backend.recommendation.trending', [
            'trendingProjects' => $trendingProjects,
        ]);
    }

    /**
     * Menampilkan halaman proyek populer
     */
    public function popular(Request $request)
    {
        // Ambil parameter pagination dari request
        $page    = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);

        // Format JSON untuk AJAX request
        if ($request->has('format') && $request->format === 'json') {
            $popularProjects = $this->getPopularProjects($perPage, $page);
            return response()->json($popularProjects);
        }

        // DIOPTIMALKAN: Cache untuk data yang sering diakses
        $cacheKey        = "rec_popular_{$perPage}_page_{$page}";
        $popularProjects = Cache::remember($cacheKey, 30, function () use ($perPage, $page) {
            return $this->getPopularProjects($perPage, $page);
        });

        return view('backend.recommendation.popular', [
            'popularProjects' => $popularProjects,
        ]);
    }

    /**
     * Menampilkan detail proyek - INTERAKSI PENTING UNTUK SISTEM REKOMENDASI
     */
    public function projectDetail($projectId, Request $request)
    {
        $user   = Auth::user();
        $userId = $user->user_id;

        // Cek apakah ini adalah request AJAX atau refresh
        $isAjax    = $request->ajax();
        $isRefresh = $request->input('refresh') === 'true';

        // Format JSON untuk AJAX request
        if ($request->has('format') && $request->format === 'json') {
            $part = $request->input('part', 'all');

            if ($part === 'similar') {
                return response()->json([
                    'similar_projects' => $this->getSimilarProjects($projectId, 8),
                ]);
            } else if ($part === 'trading') {
                return response()->json([
                    'trading_signals' => $this->getTradingSignals($projectId, $user->risk_tolerance ?? 'medium'),
                ]);
            }
        }

        // DIOPTIMALKAN: Cache cold-start status
        $isColdStart = Cache::remember("user_cold_start_{$userId}", 30, function () use ($userId) {
            $interactionCount = Interaction::where('user_id', $userId)->count();
            return $interactionCount < 10;
        });

        // Coba cari proyek di database lokal terlebih dahulu
        $project                 = Project::find($projectId);
        $projectExistsInDatabase = $project ? true : false;

        // DIOPTIMALKAN: Caching untuk project detail
        if (!$project) {
            $cacheKey    = "api_project_detail_{$projectId}";
            $projectData = Cache::remember($cacheKey, 60, function () use ($projectId) {
                try {
                    // Coba mendapatkan detail proyek langsung dari API
                    $directResponse = Http::timeout(3)->get("{$this->apiUrl}/project/{$projectId}")->json();

                    if (! empty($directResponse) && isset($directResponse['id']) && $directResponse['id'] == $projectId) {
                        return $directResponse;
                    }

                    // Fallback ke similar endpoint
                    $similarResponse = Http::timeout(3)->get("{$this->apiUrl}/recommend/similar/{$projectId}", [
                        'limit' => 5,
                    ])->json();

                    if (is_array($similarResponse)) {
                        foreach ($similarResponse as $item) {
                            if (isset($item['id']) && $item['id'] == $projectId) {
                                return $item;
                            }
                        }
                    }

                    return null;
                } catch (\Exception $e) {
                    Log::error("Error getting project detail: " . $e->getMessage());
                    return null;
                }
            });

            if ($projectData) {
                // Buat objek Project sementara
                $project                = new Project();
                $project->id            = $projectId;
                $project->name          = $projectData['name'] ?? 'Unknown Project';
                $project->symbol        = $projectData['symbol'] ?? 'N/A';
                $project->image         = $projectData['image'] ?? null;
                $project->current_price = $projectData['current_price'] ?? 0;

                // PERBAIKAN: Pastikan mendapatkan data perubahan harga dengan benar
                // Coba cari berbagai kemungkinan nama field
                $project->price_change_percentage_24h =
                $projectData['price_change_percentage_24h'] ??
                $projectData['price_change_24h'] ??
                $projectData['price_change_percentage_24h_in_currency'] ?? 0;

                $project->price_change_percentage_7d_in_currency =
                $projectData['price_change_percentage_7d_in_currency'] ??
                $projectData['price_change_7d'] ??
                $projectData['price_change_percentage_7d'] ?? 0;

                $project->market_cap       = $projectData['market_cap'] ?? 0;
                $project->total_volume     = $projectData['total_volume'] ?? 0;
                $project->primary_category = $projectData['primary_category'] ?? $projectData['category'] ?? 'Uncategorized';
                $project->chain            = $projectData['chain'] ?? 'Unknown';
                $project->description      = $projectData['description'] ?? 'No description available.';
                $project->popularity_score = $projectData['popularity_score'] ?? 0;
                $project->trend_score      = $projectData['trend_score'] ?? 0;
                $project->exists           = false;
                $project->is_from_api      = true;
            }
        }

        // DIOPTIMALKAN: Mencatat interaksi hanya sekali, bukan setiap refresh
        if ($project && $projectExistsInDatabase && !$isAjax && !$isRefresh) {
            // Cek apakah sudah ada interaksi view dalam 5 menit terakhir
            $recentView = Interaction::where('user_id', $userId)
                ->where('project_id', $projectId)
                ->where('interaction_type', 'view')
                ->where('created_at', '>=', now()->subMinutes(5))
                ->exists();

            if (!$recentView) {
                $this->recordInteraction($userId, $projectId, 'view');
            }
        }

        $similarProjects = [];
        $tradingSignals  = null;

        // DIOPTIMALKAN: Gunakan cache untuk similar projects dan trading signals
        if ($project) {
            $similarProjects = Cache::remember("similar_projects_{$projectId}_8", 60, function () use ($projectId) {
                return $this->getSimilarProjects($projectId, 8);
            });

            $tradingSignals = Cache::remember(
                "trading_signals_{$projectId}_{$user->risk_tolerance}",
                30,
                function () use ($projectId, $user) {
                    return $this->getTradingSignals($projectId, $user->risk_tolerance ?? 'medium');
                }
            );
        }

        return view('backend.recommendation.project_detail', [
            'project'         => $project,
            'similarProjects' => $similarProjects,
            'tradingSignals'  => $tradingSignals,
            'isColdStart'     => $isColdStart,
            'projectInDb'     => $projectExistsInDatabase,
        ]);
    }

    /**
     * Tambahkan ke favorit - INTERAKSI PENTING UNTUK SISTEM REKOMENDASI
     */
    public function addToFavorites(Request $request)
    {
        $user      = Auth::user();
        $projectId = $request->input('project_id');

        // PENTING: Catat interaksi favorite karena ini penting untuk sistem rekomendasi
        $this->recordInteraction($user->user_id, $projectId, 'favorite');

        return redirect()->back()->with('success', 'Proyek berhasil ditambahkan ke favorit.');
    }

    /**
     * Tambahkan proyek ke portfolio - INTERAKSI PENTING UNTUK SISTEM REKOMENDASI
     */
    public function addToPortfolio(Request $request)
    {
        $user = Auth::user();
        $projectId = $request->input('project_id');

        if (!$projectId) {
            return redirect()->back()->with('error', 'ID proyek diperlukan');
        }

        // PENTING: Catat interaksi portfolio_add karena ini penting untuk sistem rekomendasi
        $interaction = $this->recordInteraction($user->user_id, $projectId, 'portfolio_add');

        if ($interaction) {
            // Redirect ke halaman portfolio untuk menambahkan detail transaksi
            return redirect()->route('panel.portfolio.transactions', ['add_project' => $projectId])
                ->with('success', 'Interaksi proyek berhasil direkam & ditambahkan. Silakan tambahkan detail transaksi.');
        } else {
            return redirect()->back()->with('error', 'Gagal menambahkan proyek.');
        }
    }

    /**
     * Menambahkan interaksi pengguna - FUNGSI CORE UNTUK REKOMENDASI
     * DIOPTIMALKAN: Menambahkan validasi proyek dan pengelolaan foreign key error
     */
    private function recordInteraction($userId, $projectId, $interactionType, $weight = 1)
    {
        // Validasi tipe interaksi
        if (!in_array($interactionType, Interaction::$validTypes)) {
            Log::warning("Tipe interaksi tidak valid: {$interactionType}");
            return null;
        }

        // Verifikasi proyek ada di database
        $projectExists = Cache::remember("project_exists_{$projectId}", 60, function () use ($projectId) {
            return Project::where('id', $projectId)->exists();
        });

        if (!$projectExists) {
            Log::warning("Tidak dapat mencatat interaksi '{$interactionType}' untuk proyek '{$projectId}': Proyek tidak ditemukan di database");
            return null;
        }

        try {
            // PERBAIKAN: Gunakan method createInteraction dengan built-in duplicate prevention
            $interaction = Interaction::createInteraction(
                $userId,
                $projectId,
                $interactionType,
                $weight,
                [
                    'source' => 'web',
                    'timestamp' => now()->timestamp,
                ]
            );

            // DIOPTIMALKAN: Hapus caches terkait rekomendasi
            $this->clearUserRecommendationCaches($userId);

            // DIOPTIMALKAN: Update jumlah interaksi pada cache cold-start
            Cache::forget("user_cold_start_{$userId}");
            Cache::forget("user_interactions_count_{$userId}");

            return $interaction;
        } catch (\Exception $e) {
            Log::error("Error recording interaction: " . $e->getMessage());
            return null;
        }
    }

    /**
     * DIOPTIMALKAN: Menghapus semua cache terkait rekomendasi untuk user
     */
    private function clearUserRecommendationCaches($userId)
    {
        $cacheKeys = [
            "rec_personal_{$userId}_10",
            "rec_personal_hybrid_{$userId}",
            "rec_personal_fecf_{$userId}",
            "rec_personal_ncf_{$userId}",
            "dashboard_personal_recs_{$userId}",
            "rec_interactions_{$userId}",
            "user_interactions_{$userId}_10",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Menormalisasi data rekomendasi untuk memastikan format konsisten
     * DIOPTIMALKAN: Perbaikan untuk handling object, types, dan field perubahan harga
     */
    private function normalizeRecommendationData($recommendations)
    {
        if (empty($recommendations)) {
            return [];
        }

        // Jika string, kembalikan array kosong
        if (is_string($recommendations)) {
            Log::warning("Data rekomendasi berupa string: {$recommendations}");
            return [];
        }

        $normalized = [];

        foreach ($recommendations as $key => $item) {
            // Skip jika item adalah string
            if (is_string($item)) {
                continue;
            }

            // Konversi object ke array jika diperlukan
            $data = is_object($item) ? (array) $item : $item;

            // Pastikan ID ada
            $id = $data['id'] ?? ($data['project_id'] ?? "unknown-{$key}");
            if ($id === "unknown-{$key}" && isset($data->id)) {
                $id = $data->id;
            } else if ($id === "unknown-{$key}" && isset($data->project_id)) {
                $id = $data->project_id;
            }

            // PERBAIKAN: Ekstraksi data price change dengan lebih detail dan komentar

            // 1. Price Change 24h (nilai absolut dalam USD)
            $priceChange24h = null;
            if (isset($data['price_change_24h'])) {
                $priceChange24h = $data['price_change_24h'];
            } elseif (isset($data->price_change_24h)) {
                $priceChange24h = $data->price_change_24h;
            } elseif (isset($data['price_change_24h_in_currency'])) {
                $priceChange24h = $data['price_change_24h_in_currency'];
            } elseif (isset($data->price_change_24h_in_currency)) {
                $priceChange24h = $data->price_change_24h_in_currency;
            }

            // 2. Price Change Percentage 24h (dalam persen)
            $priceChangePercentage24h = null;
            if (isset($data['price_change_percentage_24h'])) {
                $priceChangePercentage24h = $data['price_change_percentage_24h'];
            } elseif (isset($data->price_change_percentage_24h)) {
                $priceChangePercentage24h = $data->price_change_percentage_24h;
            } elseif (isset($data['price_change_percentage_24h_in_currency'])) {
                $priceChangePercentage24h = $data['price_change_percentage_24h_in_currency'];
            } elseif (isset($data->price_change_percentage_24h_in_currency)) {
                $priceChangePercentage24h = $data->price_change_percentage_24h_in_currency;
            }

            // 3. Price Change 7d (nilai persentase)
            $priceChangePercentage7d = null;
            if (isset($data['price_change_percentage_7d_in_currency'])) {
                $priceChangePercentage7d = $data['price_change_percentage_7d_in_currency'];
            } elseif (isset($data->price_change_percentage_7d_in_currency)) {
                $priceChangePercentage7d = $data->price_change_percentage_7d_in_currency;
            } elseif (isset($data['price_change_percentage_7d'])) {
                $priceChangePercentage7d = $data['price_change_percentage_7d'];
            } elseif (isset($data->price_change_percentage_7d)) {
                $priceChangePercentage7d = $data->price_change_percentage_7d;
            } elseif (isset($data['price_change_7d'])) {
                $priceChangePercentage7d = $data['price_change_7d'];
            } elseif (isset($data->price_change_7d)) {
                $priceChangePercentage7d = $data->price_change_7d;
            }

            // Extract filter_match if available (new field)
            $filterMatch = null;
            if (isset($data['filter_match'])) {
                $filterMatch = $data['filter_match'];
            } elseif (isset($data->filter_match)) {
                $filterMatch = $data->filter_match;
            }

            // Pastikan semua property yang diperlukan ada
            $normalized[] = [
                'id'                                     => $id,
                'name'                                   => $data['name'] ?? ($data->name ?? 'Unknown'),
                'symbol'                                 => $data['symbol'] ?? ($data->symbol ?? 'N/A'),
                'image'                                  => $data['image'] ?? ($data->image ?? null),
                // Simpan kedua nilai perubahan (absolut dan persentase) jika tersedia
                'price_change_24h'                       => $priceChange24h !== null ? floatval($priceChange24h) : null,
                'price_change_percentage_24h'            => $priceChangePercentage24h !== null ? floatval($priceChangePercentage24h) : 0,
                'price_change_percentage_7d_in_currency' => $priceChangePercentage7d !== null ? floatval($priceChangePercentage7d) : 0,
                'market_cap'                             => floatval($data['market_cap'] ?? ($data->market_cap ?? 0)),
                'total_volume'                           => floatval($data['total_volume'] ?? ($data->total_volume ?? 0)),
                'primary_category'                       => $data['primary_category'] ?? ($data->primary_category ?? $data['category'] ?? ($data->category ?? 'Uncategorized')),
                'chain'                                  => $data['chain'] ?? ($data->chain ?? 'Multiple'),
                'description'                            => $data['description'] ?? ($data->description ?? null),
                'popularity_score'                       => floatval($data['popularity_score'] ?? ($data->popularity_score ?? 0)),
                'trend_score'                            => floatval($data['trend_score'] ?? ($data->trend_score ?? 0)),
                // PERBAIKAN: Standardisasi score untuk konsistensi
                'recommendation_score'                   => floatval($data['recommendation_score'] ??
                    ($data->recommendation_score ??
                        $data['similarity_score'] ??
                        ($data->similarity_score ??
                            $data['score'] ??
                            ($data->score ?? 0.5)))),
                // PERBAIKAN: Tambahkan field filter_match
                'filter_match'                          => $filterMatch,
                'current_price'                          => floatval($data['current_price'] ?? ($data->current_price ?? 0)),
            ];
        }

        return $normalized;
    }

    /**
     * Mendapatkan rekomendasi personal
     */
    private function getPersonalRecommendations($userId, $modelType = 'hybrid', $limit = 10, $category = null, $chain = null, $strictFilter = false)
    {
        // CLEANED: Parameter request hanya yang benar-benar digunakan
        $requestParams = [
            'user_id'             => $userId,
            'model_type'          => $modelType,
            'num_recommendations' => $limit,
            'exclude_known'       => true,
        ];

        // Tambahkan parameter filter jika ada
        if (!empty($category)) {
            $requestParams['category'] = $category;
        }

        if (!empty($chain)) {
            $requestParams['chain'] = $chain;
        }

        // Tambahkan parameter strict_filter
        if ($strictFilter) {
            $requestParams['strict_filter'] = true;
        }

        // STANDARDIZED: Buat cache key berdasarkan semua parameter
        $cacheParams = $requestParams;
        unset($cacheParams['user_id']); // Tidak perlu dalam cache key karena sudah digunakan dalam prefiks
        $cacheKey = "personal_recommendations_{$userId}_{$modelType}_" . md5(json_encode($cacheParams));

        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            return $cachedData;
        }

        try {
            // CLEANED: Deteksi cold-start users dengan caching (tanpa risk tolerance logic)
            $interactionCount = Cache::remember("user_interactions_count_{$userId}", 30, function () use ($userId) {
                return Interaction::where('user_id', $userId)->count();
            });

            $isColdStart = $interactionCount < 10;
            $timeout     = $isColdStart ? 5 : 3; // 5 detik untuk cold-start, 3 detik untuk regular

            // Log request params untuk debugging
            Log::info("Mengirim permintaan rekomendasi ke API", [
                'user_id' => $userId,
                'model' => $modelType,
                'params' => $requestParams
            ]);

            $response = Http::timeout($timeout)->post("{$this->apiUrl}/recommend/projects", $requestParams);

            // Validasi respons
            if ($response->successful() && isset($response['recommendations']) && !empty($response['recommendations'])) {
                // Log exact_match_count untuk debugging filter
                if (isset($response['exact_match_count'])) {
                    Log::info("Jumlah exact match: " . $response['exact_match_count']);
                }

                // Simpan ke cache untuk 15 menit - konsisten dengan waktu cache lainnya
                Cache::put($cacheKey, $response['recommendations'], 15);
                return $response['recommendations'];
            } else {
                // Log error respons
                Log::warning("Respons API rekomendasi tidak valid", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                // Fallback untuk cold-start users
                if ($isColdStart) {
                    return $this->getTrendingProjects($limit);
                }
                return [];
            }
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan rekomendasi personal: " . $e->getMessage());

            // Fallback untuk cold-start atau error
            if ($interactionCount < 10) {
                return $this->getTrendingProjects($limit);
            }

            // Fallback ke trending projects jika error
            return $this->getTrendingProjects($limit);
        }
    }

    /**
     * Mendapatkan proyek trending - DIOPTIMALKAN dengan pagination yang benar
     */
    private function getTrendingProjects($perPage = 20, $page = 1)
    {
        try {
            // Cache key berdasarkan pagination
            $cacheKey = $page > 1 ?
            "trending_projects_paginated_{$perPage}_page_{$page}" :
            "trending_projects_{$perPage}";

            $cachedData = Cache::get($cacheKey);

            if ($cachedData) {
                return $cachedData;
            }

            // DIOPTIMALKAN: Timeout rendah dan error handling
            $response = Http::timeout(2)->get("{$this->apiUrl}/recommend/trending", [
                'limit' => 100, // Ambil data lebih banyak untuk pagination client-side
            ])->json();

            // Jika response valid dan berbentuk array
            if (! empty($response) && is_array($response)) {
                // PERBAIKAN: Implementasi pagination yang benar
                // Jika API tidak support pagination, kita lakukan di sisi Laravel
                $totalItems = count($response);
                $items      = array_slice($response, ($page - 1) * $perPage, $perPage);

                // Normalkan data untuk memastikan konsistensi fields
                $normalizedItems = $this->normalizeRecommendationData($items);

                // Buat paginator
                $paginator = new LengthAwarePaginator(
                    $normalizedItems,
                    $totalItems,
                    $perPage,
                    $page,
                    ['path' => request()->url(), 'query' => request()->query()]
                );

                // Cache 30 menit untuk hasil pagination
                Cache::put($cacheKey, $paginator, 30);
                return $paginator;
            }

            // Fallback ke database
            $query = Project::orderBy('trend_score', 'desc');

            $projects = $query->paginate($perPage, ['*'], 'page', $page);

            // Cache 30 menit untuk fallback
            Cache::put($cacheKey, $projects, 30);
            return $projects;
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan proyek trending: " . $e->getMessage());

            // Fallback ke database dengan pagination
            return Project::orderBy('trend_score', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
        }
    }

    /**
     * Mendapatkan proyek populer - DIOPTIMALKAN dengan pagination
     */
    private function getPopularProjects($perPage = 20, $page = 1)
    {
        $cacheKey = "popular_projects_{$perPage}_page_{$page}";
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            return $cachedData;
        }

        try {
            // PERBAIKAN: Pastikan API mengurutkan berdasarkan popularity_score
            $response = Http::timeout(2)->get("{$this->apiUrl}/recommend/popular", [
                'limit' => 100, // Ambil data lebih banyak untuk pagination client-side
                'sort' => 'popularity_score', // TAMBAHKAN: Parameter sort
                'order' => 'desc' // TAMBAHKAN: Parameter order
            ])->json();

            // Jika response valid dan berbentuk array
            if (!empty($response) && is_array($response)) {
                // PERBAIKAN: Pastikan data diurutkan berdasarkan popularity_score
                usort($response, function($a, $b) {
                    $scoreA = $a['popularity_score'] ?? 0;
                    $scoreB = $b['popularity_score'] ?? 0;
                    return $scoreB <=> $scoreA; // Descending order
                });

                // PERBAIKAN: Implementasi pagination yang benar
                $totalItems = count($response);
                $items = array_slice($response, ($page - 1) * $perPage, $perPage);

                // Normalkan data untuk memastikan konsistensi fields
                $normalizedItems = $this->normalizeRecommendationData($items);

                // Buat paginator
                $paginator = new LengthAwarePaginator(
                    $normalizedItems,
                    $totalItems,
                    $perPage,
                    $page,
                    ['path' => request()->url(), 'query' => request()->query()]
                );

                // Cache 30 menit untuk hasil pagination
                Cache::put($cacheKey, $paginator, 30);
                return $paginator;
            }

            // Fallback ke database dengan pengurutan yang benar
            $projects = Project::orderBy('popularity_score', 'desc') // PERBAIKAN: Pastikan DESC
                ->paginate($perPage, ['*'], 'page', $page);

            // Cache 30 menit untuk fallback
            Cache::put($cacheKey, $projects, 30);
            return $projects;
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan proyek populer: " . $e->getMessage());

            // Fallback ke database dengan pagination dan pengurutan yang benar
            return Project::orderBy('popularity_score', 'desc') // PERBAIKAN: Pastikan DESC
                ->paginate($perPage, ['*'], 'page', $page);
        }
    }

    /**
     * Mendapatkan proyek serupa - DIOPTIMALKAN
     */
    private function getSimilarProjects($projectId, $limit = 8)
    {
        $cacheKey   = "similar_projects_{$projectId}_{$limit}";
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            return $cachedData;
        }

        try {
            $response = Http::timeout(2)->get("{$this->apiUrl}/recommend/similar/{$projectId}", [
                'limit' => $limit,
            ])->json();

            if (! empty($response)) {
                // PERBAIKAN: Normalisasi data untuk konsistensi
                $normalizedData = $this->normalizeRecommendationData($response);
                Cache::put($cacheKey, $normalizedData, 120);
                return $normalizedData;
            }

            // Fallback dengan related projects
            $project = Project::find($projectId);
            if (! $project) {
                return [];
            }

            $similarProjects = Project::where('id', '!=', $projectId)
                ->where(function ($query) use ($project) {
                    $query->where('primary_category', $project->primary_category)
                        ->orWhere('chain', $project->chain);
                })
                ->orderBy('popularity_score', 'desc')
                ->limit($limit)
                ->get();

            Cache::put($cacheKey, $similarProjects, 120);
            return $similarProjects;
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan proyek serupa: " . $e->getMessage());

            // Fallback dengan logika yang sama
            $project = Project::find($projectId);
            if (! $project) {
                return [];
            }

            return Project::where('id', '!=', $projectId)
                ->where(function ($query) use ($project) {
                    $query->where('primary_category', $project->primary_category)
                        ->orWhere('chain', $project->chain);
                })
                ->orderBy('popularity_score', 'desc')
                ->limit($limit)
                ->get();
        }
    }

    /**
     * Mendapatkan sinyal trading - DIOPTIMALKAN
     */
    private function getTradingSignals($projectId, $riskTolerance = 'medium')
    {
        $cacheKey   = "trading_signals_{$projectId}_{$riskTolerance}";
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            return $cachedData;
        }

        try {
            $response = Http::timeout(3)->post("{$this->apiUrl}/analysis/trading-signals", [
                'project_id'     => $projectId,
                'days'           => 30,
                'interval'       => '1d',
                'risk_tolerance' => $riskTolerance,
                'trading_style'  => 'standard',
            ])->json();

            if (! empty($response)) {
                Cache::put($cacheKey, $response, 30);
                return $response;
            }

            // Placeholder data jika respons kosong
            $placeholderData = [
                'project_id'           => $projectId,
                'action'               => 'hold',
                'confidence'           => 0.5,
                'evidence'             => [
                    'Data tidak tersedia saat ini',
                    'Coba lagi nanti',
                ],
                'personalized_message' => 'Data analisis teknikal tidak tersedia saat ini.',
            ];

            Cache::put($cacheKey, $placeholderData, 10); // Cache lebih pendek untuk data placeholder
            return $placeholderData;
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan sinyal trading: " . $e->getMessage());

            return [
                'project_id'           => $projectId,
                'action'               => 'hold',
                'confidence'           => 0.5,
                'evidence'             => [
                    'Data tidak tersedia saat ini',
                    'Coba lagi nanti',
                ],
                'personalized_message' => 'Data analisis teknikal tidak tersedia saat ini.',
            ];
        }
    }

    /**
     * PERBAIKAN: Mendapatkan daftar kategori yang lebih lengkap dan dibersihkan
     */
    private function getCategories()
    {
        try {
            // PERBAIKAN: Ambil kategori langsung dari database Laravel tanpa request API
            $categories = Project::select('primary_category')
                ->distinct()
                ->whereNotNull('primary_category')
                ->where('primary_category', '!=', '')
                ->get()
                ->pluck('primary_category')
                ->toArray();

            $cleanCategories = [];

            foreach ($categories as $category) {
                // Coba parse jika category dalam format JSON array
                if (str_starts_with($category, '[') && str_ends_with($category, ']')) {
                    try {
                        // Clean up potential nested quotes
                        $cleaned_value = $category;
                        // Replace double quotes if needed
                        if (str_starts_with($cleaned_value, '"[') && str_ends_with($cleaned_value, ']"')) {
                            $cleaned_value = substr($cleaned_value, 1, -1);
                        }

                        $parsed = json_decode($cleaned_value, true);
                        if (is_array($parsed) && !empty($parsed)) {
                            foreach ($parsed as $cat) {
                                if (!empty($cat) && strtolower($cat) !== 'unknown') {
                                    $cleanCategories[] = $cat;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // Jika parsing gagal, gunakan nilai asli
                        if (!empty($category) && strtolower($category) !== 'unknown') {
                            $cleanCategories[] = $category;
                        }
                    }
                } else {
                    // Jika bukan format JSON, gunakan nilai asli jika valid
                    if (!empty($category) && strtolower($category) !== 'unknown') {
                        $cleanCategories[] = $category;
                    }
                }
            }

            // Kembalikan kategori unik dan urut abjad
            return collect($cleanCategories)
                ->unique()
                ->sort()
                ->values()
                ->toArray();

        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan daftar kategori: " . $e->getMessage());

            // Fallback ke kategori default jika terjadi error
            return ['defi', 'nft', 'gaming', 'layer1', 'layer2', 'stablecoin', 'exchange'];
        }
    }

    /**
     * PERBAIKAN: Mendapatkan daftar chains yang lebih lengkap
     */
    private function getChains()
    {
        try {
            // PERBAIKAN: Ambil chain langsung dari database Laravel tanpa request API
            $chains = Project::select('chain')
                ->distinct()
                ->whereNotNull('chain')
                ->where('chain', '!=', '')
                ->where('chain', '!=', 'unknown')
                ->orderBy('chain')
                ->get()
                ->pluck('chain')
                ->toArray();

            $cleanChains = [];

            foreach ($chains as $chain) {
                // Bersihkan format array jika ada
                if (str_starts_with($chain, '[') && str_ends_with($chain, ']')) {
                    try {
                        $parsed = json_decode($chain, true);
                        if (is_array($parsed) && !empty($parsed)) {
                            foreach ($parsed as $ch) {
                                if (!empty($ch) && strtolower($ch) !== 'unknown') {
                                    $cleanChains[] = $ch;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // Jika parsing gagal, gunakan nilai asli
                        if (!empty($chain) && strtolower($chain) !== 'unknown') {
                            $cleanChains[] = $chain;
                        }
                    }
                } else {
                    // Jika bukan format array, gunakan nilai asli
                    if (!empty($chain) && strtolower($chain) !== 'unknown') {
                        $cleanChains[] = $chain;
                    }
                }
            }

            // Kembalikan chains unik dan urut abjad
            return collect($cleanChains)
                ->unique()
                ->sort()
                ->values()
                ->toArray();

        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan daftar chains: " . $e->getMessage());

            // Fallback ke chains default jika terjadi error
            return ['ethereum', 'binance-smart-chain', 'polygon', 'solana', 'avalanche'];
        }
    }
}

<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
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
     */
    public function personal()
    {
        $user   = Auth::user();
        $userId = $user->user_id;

        // DIOPTIMALKAN: Cached recommendations dengan normalisasi yang konsisten
        $hybridRecommendations = Cache::remember("rec_personal_hybrid_{$userId}", 15, function () use ($userId) {
            $recommendations = $this->getPersonalRecommendations($userId, 'hybrid', 10);
            return $this->normalizeRecommendationData($recommendations);
        });

        $fecfRecommendations = Cache::remember("rec_personal_fecf_{$userId}", 15, function () use ($userId) {
            $recommendations = $this->getPersonalRecommendations($userId, 'fecf', 10);
            return $this->normalizeRecommendationData($recommendations);
        });

        $ncfRecommendations = Cache::remember("rec_personal_ncf_{$userId}", 15, function () use ($userId) {
            $recommendations = $this->getPersonalRecommendations($userId, 'ncf', 10);
            return $this->normalizeRecommendationData($recommendations);
        });

        // DIOPTIMALKAN: Deteksi cold-start user tanpa query tambahan
        $interactionCount = Cache::remember("user_interactions_count_{$userId}", 30, function () use ($userId) {
            return Interaction::where('user_id', $userId)->count();
        });

        $isColdStart = $interactionCount < 5;

        return view('backend.recommendation.personal', [
            'hybridRecommendations' => $hybridRecommendations,
            'fecfRecommendations'   => $fecfRecommendations,
            'ncfRecommendations'    => $ncfRecommendations,
            'interactions'          => $this->getUserInteractions($userId, 10),
            'user'                  => $user,
            'isColdStart'           => $isColdStart,
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
     * AJAX endpoint untuk refresh trending projects
     */
    public function refreshTrending()
    {
        // Clear cache dan ambil data baru
        Cache::forget('rec_trending_4');
        Cache::forget('dashboard_trending_projects');
        $trendingProjects = $this->getTrendingProjects(4);

        return response()->json($trendingProjects);
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
     * Menampilkan halaman kategori
     */
    public function categories(Request $request)
    {
        $user     = Auth::user();
        $userId   = $user->user_id;
        $category = $request->input('category', 'defi');

        // DIOPTIMALKAN: Cache rekomendasi kategori
        $cacheKey                = "rec_category_{$userId}_{$category}_16";
        $categoryRecommendations = Cache::remember($cacheKey, 15, function () use ($userId, $category) {
            $recommendations = $this->getCategoryRecommendations($userId, $category, 16);
            return $this->normalizeRecommendationData($recommendations);
        });

                                                                          // DIOPTIMALKAN: Cache untuk daftar kategori
        $categories = Cache::remember('all_categories', 60, function () { // 1 jam
            return $this->getCategories();
        });

        // Catat aktivitas hanya untuk kategori yang jarang dikunjungi
        if (! in_array($category, ['defi', 'gaming', 'layer1', 'nft'])) {
            ActivityLog::logViewRecommendation($user, request(), 'category-' . $category);
        }

        return view('backend.recommendation.categories', [
            'categoryRecommendations' => $categoryRecommendations,
            'selectedCategory'        => $category,
            'categories'              => $categories,
        ]);
    }

    /**
     * Menampilkan halaman blockchain
     */
    public function chains(Request $request)
    {
        $user   = Auth::user();
        $userId = $user->user_id;
        $chain  = $request->input('chain', 'ethereum');

        // DIOPTIMALKAN: Cache rekomendasi blockchain
        $cacheKey             = "rec_chain_{$userId}_{$chain}_16";
        $chainRecommendations = Cache::remember($cacheKey, 15, function () use ($userId, $chain) {
            $recommendations = $this->getChainRecommendations($userId, $chain, 16);
            return $this->normalizeRecommendationData($recommendations);
        });

                                                                  // DIOPTIMALKAN: Cache untuk daftar blockchain
        $chains = Cache::remember('all_chains', 60, function () { // 1 jam
            return $this->getChains();
        });

        // Catat aktivitas hanya untuk chain yang jarang dikunjungi
        if (! in_array($chain, ['ethereum', 'binance-smart-chain', 'polygon', 'solana'])) {
            ActivityLog::logViewRecommendation($user, request(), 'chain-' . $chain);
        }

        return view('backend.recommendation.chains', [
            'chainRecommendations' => $chainRecommendations,
            'selectedChain'        => $chain,
            'chains'               => $chains,
        ]);
    }

    /**
     * Menampilkan detail proyek - INTERAKSI PENTING UNTUK SISTEM REKOMENDASI
     */
    public function projectDetail($projectId, Request $request)
    {
        $user   = Auth::user();
        $userId = $user->user_id;

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
            return $interactionCount < 5;
        });

        // Coba cari proyek di database lokal terlebih dahulu
        $project                 = Project::find($projectId);
        $projectExistsInDatabase = $project ? true : false;

        // DIOPTIMALKAN: Caching untuk project detail
        if (! $project) {
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

        // DIOPTIMALKAN: Mencatat interaksi menggunakan job di background untuk mengurangi waktu respons
        if ($project && $projectExistsInDatabase) {
            $this->recordInteraction($userId, $projectId, 'view');
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

        // PENTING: Interaksi favorite adalah aktivitas penting untuk log dan sistem rekomendasi
        ActivityLog::logInteraction($user, request(), $projectId, 'favorite');

        return redirect()->back()->with('success', 'Proyek berhasil ditambahkan ke favorit.');
    }

    /**
     * Menambahkan interaksi pengguna - FUNGSI CORE UNTUK REKOMENDASI
     * DIOPTIMALKAN: Menambahkan validasi proyek dan pengelolaan foreign key error
     */
    private function recordInteraction($userId, $projectId, $interactionType, $weight = 1)
    {
        // Validasi tipe interaksi
        if (! in_array($interactionType, Interaction::$validTypes)) {
            Log::warning("Tipe interaksi tidak valid: {$interactionType}");
            return null;
        }

        // Verifikasi proyek ada di database
        $projectExists = Cache::remember("project_exists_{$projectId}", 60, function () use ($projectId) {
            return Project::where('id', $projectId)->exists();
        });

        if (! $projectExists) {
            Log::warning("Tidak dapat mencatat interaksi '{$interactionType}' untuk proyek '{$projectId}': Proyek tidak ditemukan di database");
            return null;
        }

        try {
            // Catat interaksi di database lokal
            $interaction = Interaction::create([
                'user_id'          => $userId,
                'project_id'       => $projectId,
                'interaction_type' => $interactionType,
                'weight'           => $weight,
                'context'          => [
                    'source'    => 'web',
                    'timestamp' => now()->timestamp,
                ],
            ]);

            // DIOPTIMALKAN: Kirim interaksi ke API dengan penanganan error yang lebih baik
            try {
                Http::timeout(2)->post("{$this->apiUrl}/interactions/record", [
                    'user_id'          => $userId,
                    'project_id'       => $projectId,
                    'interaction_type' => $interactionType,
                    'weight'           => $weight,
                    'context'          => [
                        'source'    => 'web',
                        'timestamp' => now()->timestamp,
                    ],
                ]);
            } catch (\Exception $e) {
                Log::error("Gagal mengirim interaksi ke API: " . $e->getMessage());
            }

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
     * DIOPTIMALKAN: Perbaikan untuk handling object dan types
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
            // PERBAIKAN: Menangani kemungkinan nama field berbeda untuk perubahan harga
            // Cek berbagai kemungkinan field perubahan harga 24h
            $priceChange24h = 0;
            if (isset($data['price_change_percentage_24h'])) {
                $priceChange24h = $data['price_change_percentage_24h'];
            } elseif (isset($data['price_change_24h'])) {
                // Jika hanya ada price_change_24h tanpa percentage, kita perlu mengkonversi
                // Jika nilainya terlalu besar (langsung dalam percentage), kita gunakan langsung
                $priceChange24h = abs($data['price_change_24h']) > 10 ? $data['price_change_24h'] : $data['price_change_24h'];
            } elseif (isset($data['price_change_percentage_24h_in_currency'])) {
                $priceChange24h = $data['price_change_percentage_24h_in_currency'];
            } elseif (isset($data->price_change_percentage_24h)) {
                $priceChange24h = $data->price_change_percentage_24h;
            } elseif (isset($data->price_change_24h)) {
                $priceChange24h = $data->price_change_24h;
            }

            // Sama untuk perubahan harga 7d
            $priceChange7d = 0;
            if (isset($data['price_change_percentage_7d_in_currency'])) {
                $priceChange7d = $data['price_change_percentage_7d_in_currency'];
            } elseif (isset($data['price_change_percentage_7d'])) {
                $priceChange7d = $data['price_change_percentage_7d'];
            } elseif (isset($data['price_change_7d'])) {
                $priceChange7d = $data['price_change_7d'];
            } elseif (isset($data->price_change_percentage_7d_in_currency)) {
                $priceChange7d = $data->price_change_percentage_7d_in_currency;
            } elseif (isset($data->price_change_percentage_7d)) {
                $priceChange7d = $data->price_change_percentage_7d;
            } elseif (isset($data->price_change_7d)) {
                $priceChange7d = $data->price_change_7d;
            }

            // Pastikan semua property yang diperlukan ada
            $normalized[] = [
                'id'                                     => $id,
                'name'                                   => $data['name'] ?? ($data->name ?? 'Unknown'),
                'symbol'                                 => $data['symbol'] ?? ($data->symbol ?? 'N/A'),
                'image'                                  => $data['image'] ?? ($data->image ?? null),
                'current_price'                          => floatval($data['current_price'] ?? ($data->current_price ?? 0)),
                'price_change_percentage_24h'            => floatval($priceChange24h),
                'price_change_percentage_7d_in_currency' => floatval($priceChange7d),
                'market_cap'                             => floatval($data['market_cap'] ?? ($data->market_cap ?? 0)),
                'total_volume'                           => floatval($data['total_volume'] ?? ($data->total_volume ?? 0)),
                'primary_category'                       => $data['primary_category'] ?? ($data->primary_category ?? $data['category'] ?? ($data->category ?? 'Uncategorized')),
                'chain'                                  => $data['chain'] ?? ($data->chain ?? 'Multiple'),
                'popularity_score'                       => floatval($data['popularity_score'] ?? ($data->popularity_score ?? 0)),
                'trend_score'                            => floatval($data['trend_score'] ?? ($data->trend_score ?? 0)),
                // PERBAIKAN: Standardisasi score untuk konsistensi
                'recommendation_score'                   => floatval($data['recommendation_score'] ??
                    ($data->recommendation_score ??
                        $data['similarity_score'] ??
                        ($data->similarity_score ??
                            $data['score'] ??
                            ($data->score ?? 0.5)))),
            ];
        }

        return $normalized;
    }

    /**
     * Mendapatkan rekomendasi personal - DIOPTIMALKAN
     */
    private function getPersonalRecommendations($userId, $modelType = 'hybrid', $limit = 10)
    {
        // STANDARDIZED: Pendekatan cache yang konsisten
        $cacheKey   = "personal_recommendations_{$userId}_{$modelType}_{$limit}";
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            return $cachedData;
        }

        try {
            // DIOPTIMALKAN: Deteksi cold-start users dengan caching
            $interactionCount = Cache::remember("user_interactions_count_{$userId}", 30, function () use ($userId) {
                return Interaction::where('user_id', $userId)->count();
            });

            $isColdStart = $interactionCount < 5;
            $timeout     = $isColdStart ? 5 : 2; // 5 detik untuk cold-start, 2 detik untuk regular

            $response = Http::timeout($timeout)->post("{$this->apiUrl}/recommend/projects", [
                'user_id'             => $userId,
                'model_type'          => $modelType,
                'num_recommendations' => $limit,
                'exclude_known'       => true,
                'risk_tolerance'      => Auth::user()->risk_tolerance ?? 'medium',
                'investment_style'    => Auth::user()->investment_style ?? 'balanced',
            ]);

            // Validasi respons
            if ($response->successful() && isset($response['recommendations']) && ! empty($response['recommendations'])) {
                // PERBAIKAN: Simpan ke cache untuk 15 menit - konsisten dengan waktu cache lainnya
                Cache::put($cacheKey, $response['recommendations'], 15);
                return $response['recommendations'];
            } else {
                // Fallback untuk cold-start users
                if ($isColdStart) {
                    return $this->getTrendingProjects($limit);
                }
                return [];
            }
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan rekomendasi personal: " . $e->getMessage());

            // Fallback untuk cold-start atau error
            if ($interactionCount < 5) {
                return $this->getTrendingProjects($limit);
            }

            // Fallback ke data lokal
            return Recommendation::where('user_id', $userId)
                ->where('recommendation_type', $modelType)
                ->orderBy('rank')
                ->limit($limit)
                ->get()
                ->toArray();
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
        $cacheKey   = "popular_projects_{$perPage}_page_{$page}";
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            return $cachedData;
        }

        try {
            $response = Http::timeout(2)->get("{$this->apiUrl}/recommend/popular", [
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
            $projects = Project::orderBy('popularity_score', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            // Cache 30 menit untuk fallback
            Cache::put($cacheKey, $projects, 30);
            return $projects;
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan proyek populer: " . $e->getMessage());

            return Project::orderBy('popularity_score', 'desc')
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
     * Mendapatkan rekomendasi berdasarkan kategori - DIOPTIMALKAN
     */
    private function getCategoryRecommendations($userId, $category, $limit = 16)
    {
        $cacheKey   = "category_recommendations_{$userId}_{$category}_{$limit}";
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            return $cachedData;
        }

        try {
            $response = Http::timeout(2)->post("{$this->apiUrl}/recommend/projects", [
                'user_id'             => $userId,
                'model_type'          => 'hybrid',
                'num_recommendations' => $limit,
                'exclude_known'       => true,
                'category'            => $category,
                'risk_tolerance'      => Auth::user()->risk_tolerance ?? 'medium',
                'investment_style'    => Auth::user()->investment_style ?? 'balanced',
            ])->json();

            if (isset($response['recommendations']) && ! empty($response['recommendations'])) {
                Cache::put($cacheKey, $response['recommendations'], 30);
                return $response['recommendations'];
            }

            // Fallback ke database local
            $categoryProjects = Project::where('primary_category', $category)
                ->orderBy('popularity_score', 'desc')
                ->limit($limit)
                ->get();

            Cache::put($cacheKey, $categoryProjects, 30);
            return $categoryProjects;
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan rekomendasi kategori: " . $e->getMessage());

            return Project::where('primary_category', $category)
                ->orderBy('popularity_score', 'desc')
                ->limit($limit)
                ->get();
        }
    }

    /**
     * Mendapatkan rekomendasi berdasarkan blockchain - DIOPTIMALKAN
     */
    private function getChainRecommendations($userId, $chain, $limit = 16)
    {
        $cacheKey   = "chain_recommendations_{$userId}_{$chain}_{$limit}";
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            return $cachedData;
        }

        try {
            $response = Http::timeout(2)->post("{$this->apiUrl}/recommend/projects", [
                'user_id'             => $userId,
                'model_type'          => 'hybrid',
                'num_recommendations' => $limit,
                'exclude_known'       => true,
                'chain'               => $chain,
                'risk_tolerance'      => Auth::user()->risk_tolerance ?? 'medium',
                'investment_style'    => Auth::user()->investment_style ?? 'balanced',
            ])->json();

            if (isset($response['recommendations']) && ! empty($response['recommendations'])) {
                Cache::put($cacheKey, $response['recommendations'], 30);
                return $response['recommendations'];
            }

            // Fallback ke database local
            $chainProjects = Project::where('chain', $chain)
                ->orderBy('popularity_score', 'desc')
                ->limit($limit)
                ->get();

            Cache::put($cacheKey, $chainProjects, 30);
            return $chainProjects;
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan rekomendasi blockchain: " . $e->getMessage());

            return Project::where('chain', $chain)
                ->orderBy('popularity_score', 'desc')
                ->limit($limit)
                ->get();
        }
    }

    /**
     * Mendapatkan daftar kategori - DIOPTIMALKAN
     */
    private function getCategories()
    {
        $cacheKey   = "categories_list";
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            return $cachedData;
        }

        $categories = Project::select('primary_category')
            ->distinct()
            ->whereNotNull('primary_category')
            ->orderBy('primary_category')
            ->pluck('primary_category')
            ->toArray();

        Cache::put($cacheKey, $categories, 60 * 1); // 1 jam
        return $categories;
    }

    /**
     * Mendapatkan daftar blockchain - DIOPTIMALKAN
     */
    private function getChains()
    {
        $cacheKey   = "chains_list";
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            return $cachedData;
        }

        $chains = Project::select('chain')
            ->distinct()
            ->whereNotNull('chain')
            ->orderBy('chain')
            ->pluck('chain')
            ->toArray();

        Cache::put($cacheKey, $chains, 60 * 1); // 1 jam
        return $chains;
    }
}

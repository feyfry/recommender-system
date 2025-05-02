<?php
namespace App\Http\Controllers\Backend;

use App\Models\User;
use App\Models\Project;
use App\Models\ApiCache;
use App\Models\ActivityLog;
use App\Models\Interaction;
use Illuminate\Http\Request;
use App\Models\Recommendation;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

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
        $user = Auth::user();
        $userId = $user->user_id;

        // Gunakan sistem cache untuk data yang sering diakses
        $personalRecommendations = Cache::remember("rec_personal_{$userId}_10", 15, function() use ($userId) {
            return $this->getPersonalRecommendations($userId, 'hybrid', 10);
        });

        $trendingProjects = Cache::remember('rec_trending_8', 30, function() {
            return $this->getTrendingProjects(8);
        });

        $popularProjects = Cache::remember('rec_popular_8', 30, function() {
            return $this->getPopularProjects(8);
        });

        // DIOPTIMALKAN: Cache hasil query database
        $interactions = Cache::remember("rec_interactions_{$userId}", 10, function() use ($userId) {
            return Interaction::forUser($userId)
                ->with('project')
                ->recent()
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        });

        // DIOPTIMALKAN: Tidak mencatat aktivitas view dashboard
        // Karena ini adalah halaman yang sering dikunjungi

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
        $user = Auth::user();
        $userId = $user->user_id;

        // DIOPTIMALKAN: Menggunakan Cache untuk mengurangi beban API
        $hybridRecommendations = Cache::remember("rec_personal_hybrid_{$userId}", 15, function() use ($userId) {
            return $this->normalizeRecommendationData($this->getPersonalRecommendations($userId, 'hybrid', 10));
        });

        $fecfRecommendations = Cache::remember("rec_personal_fecf_{$userId}", 15, function() use ($userId) {
            return $this->normalizeRecommendationData($this->getPersonalRecommendations($userId, 'fecf', 10));
        });

        $ncfRecommendations = Cache::remember("rec_personal_ncf_{$userId}", 15, function() use ($userId) {
            return $this->normalizeRecommendationData($this->getPersonalRecommendations($userId, 'ncf', 10));
        });

        // DIOPTIMALKAN: Tidak catat aktivitas untuk halaman yang sering dikunjungi ini

        return view('backend.recommendation.personal', [
            'hybridRecommendations' => $hybridRecommendations,
            'fecfRecommendations'   => $fecfRecommendations,
            'ncfRecommendations'    => $ncfRecommendations,
            'user'                  => $user,
        ]);
    }

    /**
     * Menampilkan halaman proyek trending
     */
    public function trending()
    {
        // DIOPTIMALKAN: Cache untuk data yang sering diakses
        $trendingProjects = Cache::remember('rec_trending_20', 30, function() {
            return $this->getTrendingProjects(20);
        });

        // DIOPTIMALKAN: Tidak catat aktivitas untuk halaman yang sering dikunjungi ini

        return view('backend.recommendation.trending', [
            'trendingProjects' => $trendingProjects,
        ]);
    }

    /**
     * Menampilkan halaman proyek populer
     */
    public function popular()
    {
        // DIOPTIMALKAN: Cache untuk data yang sering diakses
        $popularProjects = Cache::remember('rec_popular_20', 30, function() {
            return $this->getPopularProjects(20);
        });

        // DIOPTIMALKAN: Tidak catat aktivitas untuk halaman yang sering dikunjungi ini

        return view('backend.recommendation.popular', [
            'popularProjects' => $popularProjects,
        ]);
    }

    /**
     * Menampilkan halaman kategori
     */
    public function categories(Request $request)
    {
        $user = Auth::user();
        $userId = $user->user_id;
        $category = $request->input('category', 'defi');

        // DIOPTIMALKAN: Cache rekomendasi kategori
        $cacheKey = "rec_category_{$userId}_{$category}_16";
        $categoryRecommendations = Cache::remember($cacheKey, 15, function() use ($userId, $category) {
            return $this->normalizeRecommendationData($this->getCategoryRecommendations($userId, $category, 16));
        });

        // DIOPTIMALKAN: Cache untuk daftar kategori
        $categories = Cache::remember('all_categories', 1440, function() { // 24 jam
            return $this->getCategories();
        });

        // Catat aktivitas hanya untuk kategori yang jarang dikunjungi
        if (!in_array($category, ['defi', 'gaming', 'layer1', 'nft'])) {
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
        $user = Auth::user();
        $userId = $user->user_id;
        $chain = $request->input('chain', 'ethereum');

        // DIOPTIMALKAN: Cache rekomendasi blockchain
        $cacheKey = "rec_chain_{$userId}_{$chain}_16";
        $chainRecommendations = Cache::remember($cacheKey, 15, function() use ($userId, $chain) {
            return $this->normalizeRecommendationData($this->getChainRecommendations($userId, $chain, 16));
        });

        // DIOPTIMALKAN: Cache untuk daftar blockchain
        $chains = Cache::remember('all_chains', 1440, function() { // 24 jam
            return $this->getChains();
        });

        // Catat aktivitas hanya untuk chain yang jarang dikunjungi
        if (!in_array($chain, ['ethereum', 'binance-smart-chain', 'polygon', 'solana'])) {
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
    public function projectDetail($projectId)
    {
        $user = Auth::user();
        $userId = $user->user_id;

        // Coba cari proyek di database lokal terlebih dahulu
        $project = Project::find($projectId);
        $similarProjects = [];
        $tradingSignals = null;
        $isColdStart = false;
        $projectExistsInDatabase = $project ? true : false;

        // Cek apakah pengguna adalah cold-start user
        $interactionCount = Interaction::where('user_id', $userId)->count();
        $isColdStart = $interactionCount < 5; // Pengguna dianggap cold-start jika memiliki kurang dari 5 interaksi

        // Jika proyek tidak ditemukan di database lokal, coba ambil dari API
        if (!$project) {
            try {
                // DIOPTIMALKAN: Gunakan timeout yang lebih rendah
                $response = Http::timeout(3)->get("{$this->apiUrl}/recommend/similar/{$projectId}", [
                    'limit' => 1,
                ])->json();

                if (!empty($response) && isset($response[0])) {
                    // Buat objek Project sementara dari respons API
                    $projectData = $response[0];
                    $project = new Project();
                    $project->id = $projectId;
                    $project->name = $projectData['name'] ?? 'Unknown Project';
                    $project->symbol = $projectData['symbol'] ?? 'N/A';
                    $project->image = $projectData['image'] ?? null;
                    $project->price_usd = $projectData['price_usd'] ?? $projectData['current_price'] ?? 0;
                    $project->price_change_percentage_24h = $projectData['price_change_24h'] ?? 0;
                    $project->price_change_percentage_7d = $projectData['price_change_7d'] ?? 0;
                    $project->market_cap = $projectData['market_cap'] ?? 0;
                    $project->volume_24h = $projectData['volume_24h'] ?? $projectData['total_volume'] ?? 0;
                    $project->primary_category = $projectData['primary_category'] ?? $projectData['category'] ?? 'Uncategorized';
                    $project->chain = $projectData['chain'] ?? 'Unknown';
                    $project->description = $projectData['description'] ?? 'No description available.';
                    $project->popularity_score = $projectData['popularity_score'] ?? 0;
                    $project->trend_score = $projectData['trend_score'] ?? 0;

                    // Tandai proyek ini sebagai data dari API, bukan dari database
                    $project->exists = false;
                    $project->is_from_api = true;
                }
            } catch (\Exception $e) {
                Log::error("Gagal mendapatkan info proyek dari API: " . $e->getMessage());
            }
        }

        // Jika project ditemukan (baik dari database atau API), ambil similarProjects dan tradingSignals
        if ($project) {
            // DIOPTIMALKAN: Cache hasil untuk proyek serupa
            $similarProjects = Cache::remember("similar_projects_{$projectId}_8", 60, function() use ($projectId) {
                return $this->getSimilarProjects($projectId, 8);
            });

            // DIOPTIMALKAN: Cache hasil untuk sinyal trading
            $tradingSignals = Cache::remember(
                "trading_signals_{$projectId}_{$user->risk_tolerance}",
                30,
                function() use ($projectId, $user) {
                    return $this->getTradingSignals($projectId, $user->risk_tolerance ?? 'medium');
                }
            );

            // PENTING: Catat interaksi view HANYA jika proyek ada di database
            // Ini untuk mencegah foreign key violation
            if ($projectExistsInDatabase) {
                $this->recordInteraction($userId, $projectId, 'view');
                ActivityLog::logInteraction($user, request(), $projectId, 'view');
            } else {
                Log::info("Interaksi view tidak dicatat untuk '{$projectId}' karena proyek tidak ada di database");
            }
        }

        return view('backend.recommendation.project_detail', [
            'project'         => $project,
            'similarProjects' => $similarProjects,
            'tradingSignals'  => $tradingSignals,
            'isColdStart'     => $isColdStart,
            'projectInDb'     => $projectExistsInDatabase
        ]);
    }

    /**
     * Memeriksa apakah pengguna adalah cold-start user
     */
    private function isUserColdStart($userId)
    {
        $interactionCount = Interaction::where('user_id', $userId)->count();
        return $interactionCount < 3; // Pengguna dianggap cold-start jika memiliki kurang dari 3 interaksi
    }

    /**
     * Tambahkan ke favorit - INTERAKSI PENTING UNTUK SISTEM REKOMENDASI
     */
    public function addToFavorites(Request $request)
    {
        $user = Auth::user();
        $projectId = $request->input('project_id');

        // PENTING: Catat interaksi favorite karena ini penting untuk sistem rekomendasi
        $this->recordInteraction($user->user_id, $projectId, 'favorite');

        // PENTING: Interaksi favorite adalah aktivitas penting untuk log dan sistem rekomendasi
        ActivityLog::logInteraction($user, request(), $projectId, 'favorite');

        return redirect()->back()->with('success', 'Proyek berhasil ditambahkan ke favorit.');
    }

    /**
     * Menambahkan interaksi pengguna - FUNGSI CORE UNTUK REKOMENDASI
     */
    private function recordInteraction($userId, $projectId, $interactionType, $weight = 1)
    {
        // Validasi tipe interaksi
        if (!in_array($interactionType, Interaction::$validTypes)) {
            throw new \InvalidArgumentException("Tipe interaksi tidak valid: {$interactionType}");
        }

        // PENTING: Verifikasi dulu apakah proyek ada di database sebelum mencatat interaksi
        // untuk mencegah foreign key violation
        $projectExists = Project::where('id', $projectId)->exists();

        if (!$projectExists) {
            Log::warning("Tidak dapat mencatat interaksi '{$interactionType}' untuk proyek '{$projectId}': Proyek tidak ditemukan di database");
            return null;
        }

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

        // Kirim interaksi ke API dengan penanganan error yang lebih baik
        try {
            // DIOPTIMALKAN: Gunakan timeout yang lebih rendah (2 detik)
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
            // Log error tapi tetap lanjutkan eksekusi
            Log::error("Gagal mengirim interaksi ke API: " . $e->getMessage());
        }

        // DIOPTIMALKAN: Hapus cache yang terkait saat ada interaksi baru
        // untuk memastikan rekomendasi berikutnya merefleksikan interaksi ini
        $cacheKeys = [
            "rec_personal_{$userId}_10",
            "rec_personal_hybrid_{$userId}",
            "rec_personal_fecf_{$userId}",
            "rec_personal_ncf_{$userId}",
            "rec_interactions_{$userId}",
            "dashboard_personal_recs_{$userId}",
            "dashboard_interactions_{$userId}"
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        return $interaction;
    }

    /**
     * Menormalisasi data rekomendasi untuk memastikan format konsisten
     *
     * @param mixed $recommendations Data rekomendasi dari berbagai sumber
     * @return array Data yang sudah dinormalisasi
     */
    private function normalizeRecommendationData($recommendations)
    {
        if (empty($recommendations)) {
            return [];
        }

        // Jika string, kembalikan array kosong dan log warning
        if (is_string($recommendations)) {
            Log::warning("Data rekomendasi berupa string: {$recommendations}");
            return [];
        }

        $normalized = [];

        foreach ($recommendations as $key => $item) {
            // Jika item adalah string, skip item ini
            if (is_string($item)) {
                Log::warning("Item rekomendasi berupa string, dilewati: {$item}");
                continue;
            }

            // Konversi object ke array jika diperlukan
            $data = is_object($item) ? (array) $item : $item;

            // Pastikan semua property yang diperlukan ada
            $normalized[] = [
                'id' => $data['id'] ?? ($data->id ?? "unknown-{$key}"),
                'name' => $data['name'] ?? ($data->name ?? 'Unknown'),
                'symbol' => $data['symbol'] ?? ($data->symbol ?? 'N/A'),
                'image' => $data['image'] ?? ($data->image ?? null),
                'price_usd' => $data['price_usd'] ?? ($data->price_usd ?? $data['current_price'] ?? ($data->current_price ?? 0)),
                'price_change_percentage_24h' => $data['price_change_percentage_24h'] ?? ($data->price_change_percentage_24h ?? 0),
                'price_change_percentage_7d' => $data['price_change_percentage_7d'] ?? ($data->price_change_percentage_7d ?? 0),
                'market_cap' => $data['market_cap'] ?? ($data->market_cap ?? 0),
                'volume_24h' => $data['volume_24h'] ?? ($data->volume_24h ?? $data['total_volume'] ?? ($data->total_volume ?? 0)),
                'primary_category' => $data['primary_category'] ?? ($data->primary_category ?? $data['category'] ?? ($data->category ?? 'Uncategorized')),
                'chain' => $data['chain'] ?? ($data->chain ?? 'Multiple'),
                'description' => $data['description'] ?? ($data->description ?? null),
                'popularity_score' => $data['popularity_score'] ?? ($data->popularity_score ?? 0),
                'trend_score' => $data['trend_score'] ?? ($data->trend_score ?? 0),
                'recommendation_score' => $data['recommendation_score'] ?? ($data->recommendation_score ?? $data['similarity_score'] ?? ($data->similarity_score ?? 0.5)),
            ];
        }

        return $normalized;
    }

    /**
     * Mendapatkan rekomendasi personal
     */
    private function getPersonalRecommendations($userId, $modelType = 'hybrid', $limit = 10)
    {
        // Cek cache terlebih dahulu
        $cacheKey = "personal_recommendations_{$userId}_{$modelType}_{$limit}";
        $cachedData = ApiCache::findMatch($cacheKey, []);

        if ($cachedData) {
            return $cachedData->response;
        }

        // Ambil data dari API jika tidak ada cache
        try {
            // DIOPTIMALKAN: Gunakan timeout yang lebih rendah
            $response = Http::timeout(3)->post("{$this->apiUrl}/recommend/projects", [
                'user_id'             => $userId,
                'model_type'          => $modelType,
                'num_recommendations' => $limit,
                'exclude_known'       => true,
                'risk_tolerance'      => Auth::user()->risk_tolerance ?? 'medium',
                'investment_style'    => Auth::user()->investment_style ?? 'balanced',
            ]);

            // Simpan ke cache untuk 30 menit
            ApiCache::store($cacheKey, [], $response, 30);

            // Apakah respons berhasil dan memiliki format yang benar
            if ($response->successful() && isset($response['recommendations'])) {
                return $response['recommendations'];
            } else {
                // Log kesalahan dan kembalikan array kosong jika data tidak sesuai format
                Log::warning("Format respons API tidak valid: " . $response->body());
                return [];
            }
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan rekomendasi personal: " . $e->getMessage());

            // Fallback ke data dari database lokal
            return Recommendation::where('user_id', $userId)
                ->where('recommendation_type', $modelType)
                ->orderBy('rank')
                ->limit($limit)
                ->get()
                ->toArray();
        }
    }

    /**
     * Mendapatkan proyek trending
     */
    private function getTrendingProjects($limit = 10)
    {
        // Cek cache terlebih dahulu
        $cacheKey = "trending_projects_{$limit}";
        $cachedData = ApiCache::findMatch($cacheKey, []);

        if ($cachedData) {
            return $cachedData->response;
        }

        // Ambil data dari API jika tidak ada cache
        try {
            // DIOPTIMALKAN: Gunakan timeout untuk menghindari penantian yang terlalu lama
            $response = Http::timeout(3)->get("{$this->apiUrl}/recommend/trending", [
                'limit' => $limit,
            ])->json();

            // Simpan ke cache untuk 60 menit
            ApiCache::store($cacheKey, [], $response, 60);

            return $response;
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan proyek trending: " . $e->getMessage());

            // Fallback ke data dari database lokal
            return Project::orderBy('trend_score', 'desc')
                ->limit($limit)
                ->get();
        }
    }

    /**
     * Mendapatkan proyek populer
     */
    private function getPopularProjects($limit = 10)
    {
        // Cek cache terlebih dahulu
        $cacheKey = "popular_projects_{$limit}";
        $cachedData = ApiCache::findMatch($cacheKey, []);

        if ($cachedData) {
            return $cachedData->response;
        }

        // Ambil data dari API jika tidak ada cache
        try {
            // DIOPTIMALKAN: Gunakan timeout
            $response = Http::timeout(3)->get("{$this->apiUrl}/recommend/popular", [
                'limit' => $limit,
            ])->json();

            // Simpan ke cache untuk 60 menit
            ApiCache::store($cacheKey, [], $response, 60);

            return $response;
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan proyek populer: " . $e->getMessage());

            // Fallback ke data dari database lokal
            return Project::orderBy('popularity_score', 'desc')
                ->limit($limit)
                ->get();
        }
    }

    /**
     * Mendapatkan proyek serupa
     */
    private function getSimilarProjects($projectId, $limit = 8)
    {
        // Cek cache terlebih dahulu
        $cacheKey = "similar_projects_{$projectId}_{$limit}";
        $cachedData = ApiCache::findMatch($cacheKey, []);

        if ($cachedData) {
            return $cachedData->response;
        }

        // Ambil data dari API jika tidak ada cache
        try {
            // DIOPTIMALKAN: Gunakan timeout
            $response = Http::timeout(3)->get("{$this->apiUrl}/recommend/similar/{$projectId}", [
                'limit' => $limit,
            ])->json();

            // Simpan ke cache untuk 120 menit
            ApiCache::store($cacheKey, [], $response, 120);

            return $response;
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan proyek serupa: " . $e->getMessage());

            // Fallback ke data dari database lokal
            $project = Project::find($projectId);
            if (!$project) {
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
     * Mendapatkan sinyal trading
     */
    private function getTradingSignals($projectId, $riskTolerance = 'medium')
    {
        // Cek cache terlebih dahulu
        $cacheKey = "trading_signals_{$projectId}_{$riskTolerance}";
        $cachedData = ApiCache::findMatch($cacheKey, []);

        if ($cachedData) {
            return $cachedData->response;
        }

        // Ambil data dari API jika tidak ada cache
        try {
            // DIOPTIMALKAN: Gunakan timeout
            $response = Http::timeout(3)->post("{$this->apiUrl}/analysis/trading-signals", [
                'project_id'     => $projectId,
                'days'           => 30,
                'interval'       => '1d',
                'risk_tolerance' => $riskTolerance,
                'trading_style'  => 'standard',
            ])->json();

            // Simpan ke cache untuk 30 menit
            ApiCache::store($cacheKey, [], $response, 30);

            return $response;
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan sinyal trading: " . $e->getMessage());

            // Fallback ke data placeholder sederhana
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
     * Mendapatkan rekomendasi berdasarkan kategori
     */
    private function getCategoryRecommendations($userId, $category, $limit = 16)
    {
        // Cek cache terlebih dahulu
        $cacheKey = "category_recommendations_{$userId}_{$category}_{$limit}";
        $cachedData = ApiCache::findMatch($cacheKey, []);

        if ($cachedData) {
            return $cachedData->response;
        }

        // Ambil data dari API jika tidak ada cache
        try {
            // DIOPTIMALKAN: Gunakan timeout
            $response = Http::timeout(3)->post("{$this->apiUrl}/recommend/projects", [
                'user_id'             => $userId,
                'model_type'          => 'hybrid',
                'num_recommendations' => $limit,
                'exclude_known'       => true,
                'category'            => $category,
                'risk_tolerance'      => Auth::user()->risk_tolerance ?? 'medium',
                'investment_style'    => Auth::user()->investment_style ?? 'balanced',
            ])->json();

            // Simpan ke cache untuk 30 menit
            ApiCache::store($cacheKey, [], $response, 30);

            return $response['recommendations'] ?? [];
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan rekomendasi kategori: " . $e->getMessage());

            // Fallback ke data dari database lokal
            return Project::where('primary_category', $category)
                ->orderBy('popularity_score', 'desc')
                ->limit($limit)
                ->get();
        }
    }

    /**
     * Mendapatkan rekomendasi berdasarkan blockchain
     */
    private function getChainRecommendations($userId, $chain, $limit = 16)
    {
        // Cek cache terlebih dahulu
        $cacheKey = "chain_recommendations_{$userId}_{$chain}_{$limit}";
        $cachedData = ApiCache::findMatch($cacheKey, []);

        if ($cachedData) {
            return $cachedData->response;
        }

        // Ambil data dari API jika tidak ada cache
        try {
            // DIOPTIMALKAN: Gunakan timeout
            $response = Http::timeout(3)->post("{$this->apiUrl}/recommend/projects", [
                'user_id'             => $userId,
                'model_type'          => 'hybrid',
                'num_recommendations' => $limit,
                'exclude_known'       => true,
                'chain'               => $chain,
                'risk_tolerance'      => Auth::user()->risk_tolerance ?? 'medium',
                'investment_style'    => Auth::user()->investment_style ?? 'balanced',
            ])->json();

            // Simpan ke cache untuk 30 menit
            ApiCache::store($cacheKey, [], $response, 30);

            return $response['recommendations'] ?? [];
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan rekomendasi blockchain: " . $e->getMessage());

            // Fallback ke data dari database lokal
            return Project::where('chain', $chain)
                ->orderBy('popularity_score', 'desc')
                ->limit($limit)
                ->get();
        }
    }

    /**
     * Mendapatkan daftar kategori
     */
    private function getCategories()
    {
        // Cek cache terlebih dahulu
        $cacheKey = "categories_list";
        $cachedData = ApiCache::findMatch($cacheKey, []);

        if ($cachedData) {
            return $cachedData->response;
        }

        // Ambil data dari database
        $categories = Project::select('primary_category')
            ->distinct()
            ->whereNotNull('primary_category')
            ->orderBy('primary_category')
            ->pluck('primary_category')
            ->toArray();

        // Simpan ke cache untuk 24 jam
        ApiCache::store($cacheKey, [], $categories, 60 * 24);

        return $categories;
    }

    /**
     * Mendapatkan daftar blockchain
     */
    private function getChains()
    {
        // Cek cache terlebih dahulu
        $cacheKey = "chains_list";
        $cachedData = ApiCache::findMatch($cacheKey, []);

        if ($cachedData) {
            return $cachedData->response;
        }

        // Ambil data dari database
        $chains = Project::select('chain')
            ->distinct()
            ->whereNotNull('chain')
            ->orderBy('chain')
            ->pluck('chain')
            ->toArray();

        // Simpan ke cache untuk 24 jam
        ApiCache::store($cacheKey, [], $chains, 60 * 24);

        return $chains;
    }
}

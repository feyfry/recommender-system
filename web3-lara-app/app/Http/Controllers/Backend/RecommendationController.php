<?php
namespace App\Http\Controllers\Backend;

use App\Models\User;
use App\Models\Project;
use App\Models\ApiCache;
use App\Models\ActivityLog;
use App\Models\Interaction;
use Illuminate\Http\Request;
use App\Models\Recommendation;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
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
        $this->apiUrl = env('RECOMMENDATION_API_URL', 'http://localhost:8000');
    }

    /**
     * Menampilkan halaman dashboard rekomendasi
     */
    public function index()
    {
        $user = Auth::user();

        // Ambil rekomendasi personal terbaru
        $personalRecommendations = $this->getPersonalRecommendations($user->user_id, 'hybrid', 10);

        // Ambil project trending
        $trendingProjects = $this->getTrendingProjects(8);

        // Ambil project populer
        $popularProjects = $this->getPopularProjects(8);

        // Ambil data interaksi pengguna
        $interactions = Interaction::forUser($user->user_id)
            ->with('project')
            ->recent()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Catat aktivitas
        ActivityLog::logViewRecommendation($user, request(), 'dashboard');

        return view('backend.recommendation.index', [
            'personalRecommendations' => $personalRecommendations,
            'trendingProjects'        => $trendingProjects,
            'popularProjects'         => $popularProjects,
            'interactions'            => $interactions,
        ]);
    }

    /**
     * Menampilkan halaman rekomendasi personal
     */
    public function personal()
    {
        $user = Auth::user();

        // Ambil rekomendasi dari berbagai model
        $hybridRecommendations = $this->getPersonalRecommendations($user->user_id, 'hybrid', 10);
        $fecfRecommendations   = $this->getPersonalRecommendations($user->user_id, 'fecf', 10);
        $ncfRecommendations    = $this->getPersonalRecommendations($user->user_id, 'ncf', 10);

        // Catat aktivitas
        ActivityLog::logViewRecommendation($user, request(), 'personal');

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
        $user = Auth::user();

        // Ambil project trending
        $trendingProjects = $this->getTrendingProjects(20);

        // Catat aktivitas
        ActivityLog::logViewRecommendation($user, request(), 'trending');

        return view('backend.recommendation.trending', [
            'trendingProjects' => $trendingProjects,
        ]);
    }

    /**
     * Menampilkan halaman proyek populer
     */
    public function popular()
    {
        $user = Auth::user();

        // Ambil project populer
        $popularProjects = $this->getPopularProjects(20);

        // Catat aktivitas
        ActivityLog::logViewRecommendation($user, request(), 'popular');

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
        $category = $request->input('category', 'defi');

        // Ambil rekomendasi berdasarkan kategori
        $categoryRecommendations = $this->getCategoryRecommendations($user->user_id, $category, 16);

        // Ambil daftar kategori untuk dropdown
        $categories = $this->getCategories();

        // Catat aktivitas
        ActivityLog::logViewRecommendation($user, request(), 'category-' . $category);

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
        $user  = Auth::user();
        $chain = $request->input('chain', 'ethereum');

        // Ambil rekomendasi berdasarkan blockchain
        $chainRecommendations = $this->getChainRecommendations($user->user_id, $chain, 16);

        // Ambil daftar blockchain untuk dropdown
        $chains = $this->getChains();

        // Catat aktivitas
        ActivityLog::logViewRecommendation($user, request(), 'chain-' . $chain);

        return view('backend.recommendation.chains', [
            'chainRecommendations' => $chainRecommendations,
            'selectedChain'        => $chain,
            'chains'               => $chains,
        ]);
    }

    /**
     * Menampilkan detail proyek
     */
    public function projectDetail($projectId)
    {
        $user = Auth::user();

        // Ambil detail proyek
        $project = Project::findOrFail($projectId);

        // Ambil proyek serupa
        $similarProjects = $this->getSimilarProjects($projectId, 8);

        // Ambil sinyal trading
        $tradingSignals = $this->getTradingSignals($projectId, $user->risk_tolerance ?? 'medium');

        // Catat interaksi view
        $this->recordInteraction($user->user_id, $projectId, 'view');

        // Catat aktivitas
        ActivityLog::logInteraction($user, request(), $projectId, 'view');

        return view('backend.recommendation.project_detail', [
            'project'         => $project,
            'similarProjects' => $similarProjects,
            'tradingSignals'  => $tradingSignals,
        ]);
    }

    /**
     * Tambahkan ke favorit
     */
    public function addToFavorites(Request $request)
    {
        $user      = Auth::user();
        $projectId = $request->input('project_id');

        // Catat interaksi favorite
        $this->recordInteraction($user->user_id, $projectId, 'favorite');

        // Catat aktivitas
        ActivityLog::logInteraction($user, request(), $projectId, 'favorite');

        return redirect()->back()->with('success', 'Proyek berhasil ditambahkan ke favorit.');
    }

    /**
     * Menambahkan interaksi pengguna
     */
    private function recordInteraction($userId, $projectId, $interactionType, $weight = 1)
    {
        // Validasi tipe interaksi
        if (! in_array($interactionType, Interaction::$validTypes)) {
            throw new \InvalidArgumentException("Tipe interaksi tidak valid: {$interactionType}");
        }

        // Catat interaksi di database lokal
        Interaction::create([
            'user_id'          => $userId,
            'project_id'       => $projectId,
            'interaction_type' => $interactionType,
            'weight'           => $weight,
            'context'          => [
                'source'    => 'web',
                'timestamp' => now()->timestamp,
            ],
        ]);

        // Kirim interaksi ke API
        try {
            Http::post("{$this->apiUrl}/interactions/record", [
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

        return true;
    }

    /**
     * Mendapatkan rekomendasi personal
     */
    private function getPersonalRecommendations($userId, $modelType = 'hybrid', $limit = 10)
    {
        // Cek cache terlebih dahulu
        $cacheKey   = "personal_recommendations_{$userId}_{$modelType}_{$limit}";
        $cachedData = ApiCache::findMatch($cacheKey, []);

        if ($cachedData) {
            return $cachedData->response;
        }

        // Ambil data dari API jika tidak ada cache
        try {
            $response = Http::post("{$this->apiUrl}/recommend/projects", [
                'user_id'             => $userId,
                'model_type'          => $modelType,
                'num_recommendations' => $limit,
                'exclude_known'       => true,
                'risk_tolerance'      => Auth::user()->risk_tolerance ?? 'medium',
                'investment_style'    => Auth::user()->investment_style ?? 'balanced',
            ])->json();

            // Simpan ke cache untuk 30 menit
            ApiCache::store($cacheKey, [], $response, 30);

            return $response['recommendations'] ?? [];
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan rekomendasi personal: " . $e->getMessage());

            // Fallback ke rekomendasi dari database lokal
            return Recommendation::where('user_id', $userId)
                ->where('recommendation_type', $modelType)
                ->orderBy('rank')
                ->limit($limit)
                ->get();
        }
    }

    /**
     * Mendapatkan proyek trending
     */
    private function getTrendingProjects($limit = 10)
    {
        // Cek cache terlebih dahulu
        $cacheKey   = "trending_projects_{$limit}";
        $cachedData = ApiCache::findMatch($cacheKey, []);

        if ($cachedData) {
            return $cachedData->response;
        }

        // Ambil data dari API jika tidak ada cache
        try {
            $response = Http::get("{$this->apiUrl}/recommend/trending", [
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
        $cacheKey   = "popular_projects_{$limit}";
        $cachedData = ApiCache::findMatch($cacheKey, []);

        if ($cachedData) {
            return $cachedData->response;
        }

        // Ambil data dari API jika tidak ada cache
        try {
            $response = Http::get("{$this->apiUrl}/recommend/popular", [
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
        $cacheKey   = "similar_projects_{$projectId}_{$limit}";
        $cachedData = ApiCache::findMatch($cacheKey, []);

        if ($cachedData) {
            return $cachedData->response;
        }

        // Ambil data dari API jika tidak ada cache
        try {
            $response = Http::get("{$this->apiUrl}/recommend/similar/{$projectId}", [
                'limit' => $limit,
            ])->json();

            // Simpan ke cache untuk 120 menit
            ApiCache::store($cacheKey, [], $response, 120);

            return $response;
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan proyek serupa: " . $e->getMessage());

            // Fallback ke data dari database lokal
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
     * Mendapatkan sinyal trading
     */
    private function getTradingSignals($projectId, $riskTolerance = 'medium')
    {
        // Cek cache terlebih dahulu
        $cacheKey   = "trading_signals_{$projectId}_{$riskTolerance}";
        $cachedData = ApiCache::findMatch($cacheKey, []);

        if ($cachedData) {
            return $cachedData->response;
        }

        // Ambil data dari API jika tidak ada cache
        try {
            $response = Http::post("{$this->apiUrl}/analysis/trading-signals", [
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
        $cacheKey   = "category_recommendations_{$userId}_{$category}_{$limit}";
        $cachedData = ApiCache::findMatch($cacheKey, []);

        if ($cachedData) {
            return $cachedData->response;
        }

        // Ambil data dari API jika tidak ada cache
        try {
            $response = Http::post("{$this->apiUrl}/recommend/projects", [
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
        $cacheKey   = "chain_recommendations_{$userId}_{$chain}_{$limit}";
        $cachedData = ApiCache::findMatch($cacheKey, []);

        if ($cachedData) {
            return $cachedData->response;
        }

        // Ambil data dari API jika tidak ada cache
        try {
            $response = Http::post("{$this->apiUrl}/recommend/projects", [
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
        $cacheKey   = "categories_list";
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
        $cacheKey   = "chains_list";
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

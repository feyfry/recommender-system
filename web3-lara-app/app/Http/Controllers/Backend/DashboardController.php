<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Interaction;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\Recommendation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
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
     * Menampilkan dashboard pengguna dengan lazy loading
     */
    public function index(Request $request)
    {
        $user   = Auth::user();
        $userId = $user->user_id;

        // DIOPTIMALKAN: Load data yang penting sekaligus
        // dan sisanya lazy loaded melalui AJAX
        $data = [
            'user'                    => $user,
            // Load rekomendasi personal pada load awal
            'personalRecommendations' => Cache::remember("dashboard_personal_recs_{$userId}", 30, function () use ($userId) {
                $recommendations = $this->getPersonalRecommendations($userId, 'hybrid', 4);
                return $this->normalizeRecommendations($recommendations);
            }),
            // Load trending projects pada load awal dengan jumlah kecil
            'trendingProjects'        => Cache::remember('dashboard_trending_projects', 60, function () {
                return $this->getTrendingProjects(4);
            }),
            // Inisialisasi data portfolio dan interactions dengan kosong
            // untuk diisi dengan lazy loading
            'portfolioSummary'        => null,
            'recentInteractions'      => null,
        ];

        return view('backend.dashboard.index', $data);
    }

    /**
     * AJAX endpoint untuk load data portfolio asynchronously
     */
    public function loadPortfolio()
    {
        $user   = Auth::user();
        $userId = $user->user_id;

        $portfolioSummary = Cache::remember("dashboard_portfolio_{$userId}", 30, function () use ($userId) {
            return $this->getPortfolioSummary($userId);
        });

        return response()->json($portfolioSummary);
    }

    /**
     * AJAX endpoint untuk load interaksi terbaru asynchronously
     */
    public function loadInteractions()
    {
        $user   = Auth::user();
        $userId = $user->user_id;

        $recentInteractions = Cache::remember("dashboard_interactions_{$userId}", 15, function () use ($userId) {
            return Interaction::forUser($userId)
                ->select(['id', 'user_id', 'project_id', 'interaction_type', 'created_at'])
                ->with(['project' => function ($query) {
                    // Hanya select kolom yang dibutuhkan untuk optimasi
                    $query->select('id', 'name', 'symbol', 'image');
                }])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();
        });

        return response()->json($recentInteractions);
    }

    /**
     * Mendapatkan ringkasan portofolio pengguna (Dioptimalkan untuk performa)
     */
    private function getPortfolioSummary($userId)
    {
        $portfolios = Portfolio::forUser($userId)
            ->with(['project' => function ($query) {
                // Hanya select kolom yang dibutuhkan untuk optimasi
                $query->select('id', 'name', 'symbol', 'image', 'current_price');
            }])
            ->get();

        if ($portfolios->isEmpty()) {
            return [
                'total_value'            => 0,
                'total_cost'             => 0,
                'profit_loss'            => 0,
                'profit_loss_percentage' => 0,
                'top_assets'             => [],
            ];
        }

        $totalValue = 0;
        $totalCost  = 0;
        $assets     = [];

        foreach ($portfolios as $portfolio) {
            $currentValue = $portfolio->current_value;
            $totalValue += $currentValue;
            $totalCost += $portfolio->initial_value;

            $assets[] = [
                'id'     => $portfolio->project->id,
                'name'   => $portfolio->project->name,
                'symbol' => $portfolio->project->symbol,
                'image'  => $portfolio->project->image,
                'value'  => $currentValue,
                'amount' => $portfolio->amount,
                'price'  => $portfolio->project->current_price,
            ];
        }

        // Urutkan aset berdasarkan nilai (dari besar ke kecil)
        usort($assets, function ($a, $b) {
            return $b['value'] <=> $a['value'];
        });

        // Ambil 3 aset teratas
        $topAssets = array_slice($assets, 0, 3);

        $profitLoss           = $totalValue - $totalCost;
        $profitLossPercentage = $totalCost > 0 ? ($profitLoss / $totalCost) * 100 : 0;

        return [
            'total_value'            => $totalValue,
            'total_cost'             => $totalCost,
            'profit_loss'            => $profitLoss,
            'profit_loss_percentage' => $profitLossPercentage,
            'top_assets'             => $topAssets,
        ];
    }

    /**
     * DITAMBAHKAN: Normalisasi rekomendasi - memastikan konsistensi dengan RecommendationController
     */
    private function normalizeRecommendations($recommendations)
    {
        if (empty($recommendations)) {
            return [];
        }

        if (is_string($recommendations)) {
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

            // Pastikan semua property yang diperlukan ada
            $normalized[] = [
                'id'                                     => $id,
                'name'                                   => $data['name'] ?? ($data->name ?? 'Unknown'),
                'symbol'                                 => $data['symbol'] ?? ($data->symbol ?? 'N/A'),
                'image'                                  => $data['image'] ?? ($data->image ?? null),
                'current_price'                          => floatval($data['current_price'] ?? ($data->current_price ?? 0)),
                'price_change_24h'                       => floatval($data['price_change_24h'] ?? ($data->price_change_24h ?? 0)),
                'price_change_percentage_7d_in_currency' => floatval($data['price_change_percentage_7d_in_currency'] ?? ($data->price_change_percentage_7d_in_currency ?? 0)),
                'market_cap'                             => floatval($data['market_cap'] ?? ($data->market_cap ?? 0)),
                'total_volume'                           => floatval($data['total_volume'] ?? ($data->total_volume ?? 0)),
                'primary_category'                       => $data['primary_category'] ?? ($data->primary_category ?? $data['category'] ?? ($data->category ?? 'Uncategorized')),
                'chain'                                  => $data['chain'] ?? ($data->chain ?? 'Multiple'),
                'description'                            => $data['description'] ?? ($data->description ?? null),
                'popularity_score'                       => floatval($data['popularity_score'] ?? ($data->popularity_score ?? 0)),
                'trend_score'                            => floatval($data['trend_score'] ?? ($data->trend_score ?? 0)),
                // PERBAIKAN: Standardisasi score untuk konsistensi dengan RecommendationController
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
     * Mendapatkan rekomendasi personal untuk pengguna (Dioptimalkan)
     */
    private function getPersonalRecommendations($userId, $modelType = 'hybrid', $limit = 10)
    {
        try {
            // DIOPTIMALKAN: Cache key yang konsisten dengan RecommendationController
            $cacheKey   = "personal_recommendations_{$userId}_{$modelType}_{$limit}";
            $cachedData = Cache::get($cacheKey);

            if ($cachedData) {
                return $cachedData;
            }

            // DIOPTIMALKAN: Gunakan timeout yang lebih rendah untuk HTTP requests
            $response = Http::timeout(2)->post("{$this->apiUrl}/recommend/projects", [
                'user_id'             => $userId,
                'model_type'          => $modelType,
                'num_recommendations' => $limit,
                'exclude_known'       => true,
                'risk_tolerance'      => Auth::user()->risk_tolerance ?? 'medium',
                'investment_style'    => Auth::user()->investment_style ?? 'balanced',
            ]);

            // Apakah respons berhasil dan memiliki format yang benar
            if ($response->successful() && isset($response['recommendations'])) {
                // Cache result for 30 minutes
                Cache::put($cacheKey, $response['recommendations'], 30);
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
     * Mendapatkan proyek trending (Dioptimalkan)
     */
    private function getTrendingProjects($limit = 10)
    {
        try {
            // DIOPTIMALKAN: Cache key yang konsisten
            $cacheKey   = "trending_projects_{$limit}";
            $cachedData = Cache::get($cacheKey);

            if ($cachedData) {
                return $cachedData;
            }

            // DIOPTIMALKAN: Gunakan timeout yang lebih rendah untuk HTTP requests
            $response = Http::timeout(2)->get("{$this->apiUrl}/recommend/trending", [
                'limit' => $limit,
            ])->json();

            // Cache result for 60 minutes
            if (! empty($response)) {
                Cache::put($cacheKey, $response, 60);
                return $response;
            }

            // Fallback ke database local
            $projects = Project::select('id', 'name', 'symbol', 'image', 'current_price', 'price_change_percentage_24h')
                ->orderBy('trend_score', 'desc')
                ->limit($limit)
                ->get();

            Cache::put($cacheKey, $projects, 60);
            return $projects;
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan proyek trending: " . $e->getMessage());

            // Fallback ke data dari database lokal
            return Project::select('id', 'name', 'symbol', 'image', 'current_price', 'price_change_percentage_24h')
                ->orderBy('trend_score', 'desc')
                ->limit($limit)
                ->get();
        }
    }
}

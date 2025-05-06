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
                return $this->getPersonalRecommendations($userId, 'hybrid', 4);
            }),
            // Load trending projects pada load awal dengan jumlah kecil
            'trendingProjects'        => Cache::remember('dashboard_trending_projects', 60, function () {
                return $this->getTrendingProjects(4);
            }),
            // Inisialisasi data portfolio dan interactions dengan kosong
            // untuk mengurangi load awal, akan dimuat dengan lazy loading
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
     * AJAX endpoint untuk refresh trending projects
     */
    public function refreshTrending()
    {
        // Clear cache dan ambil data baru
        Cache::forget('dashboard_trending_projects');
        $trendingProjects = $this->getTrendingProjects(4);

        return response()->json($trendingProjects);
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
     * Mendapatkan rekomendasi personal untuk pengguna (Dioptimalkan)
     */
    private function getPersonalRecommendations($userId, $modelType = 'hybrid', $limit = 10)
    {
        try {
            // DIOPTIMALKAN: Cache hasil API untuk mengurangi panggilan
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
            // DIOPTIMALKAN: Cache hasil API untuk mengurangi panggilan
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
            Cache::put($cacheKey, $response, 60);
            return $response;
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

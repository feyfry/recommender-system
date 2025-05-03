<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Interaction;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\Recommendation;
use App\Models\User;
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
     * Menampilkan dashboard pengguna
     */
    public function index()
    {
        $user   = Auth::user();
        $userId = $user->user_id;

        // Gunakan caching untuk meningkatkan performa
        $personalRecommendations = Cache::remember("dashboard_personal_recs_{$userId}", 30, function () use ($userId) {
            return $this->getPersonalRecommendations($userId, 'hybrid', 4);
        });

        $trendingProjects = Cache::remember('dashboard_trending_projects', 60, function () {
            return $this->getTrendingProjects(4);
        });

        // Ambil interaksi terbaru pengguna
        $recentInteractions = Cache::remember("dashboard_interactions_{$userId}", 15, function () use ($userId) {
            return Interaction::forUser($userId)
                ->with('project')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();
        });

        // Ambil dan hitung data portofolio jika ada
        $portfolioSummary = Cache::remember("dashboard_portfolio_{$userId}", 30, function () use ($userId) {
            return $this->getPortfolioSummary($userId);
        });

        // DIOPTIMALKAN: Tidak lagi mencatat aktivitas untuk setiap kunjungan dashboard
        // Perubahan ini sendiri akan meningkatkan kinerja secara signifikan

        return view('backend.dashboard.index', [
            'personalRecommendations' => $personalRecommendations,
            'trendingProjects'        => $trendingProjects,
            'recentInteractions'      => $recentInteractions,
            'portfolioSummary'        => $portfolioSummary,
            'user'                    => $user,
        ]);
    }

    /**
     * Mendapatkan ringkasan portofolio pengguna
     */
    private function getPortfolioSummary($userId)
    {
        $portfolios = Portfolio::forUser($userId)->with('project')->get();

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
     * Mendapatkan rekomendasi personal untuk pengguna
     */
    private function getPersonalRecommendations($userId, $modelType = 'hybrid', $limit = 10)
    {
        try {
            // DIOPTIMALKAN: Gunakan timeout yang lebih rendah untuk HTTP requests
            // untuk menghindari menunggu terlalu lama jika API lambat
            $response = Http::timeout(3)->post("{$this->apiUrl}/recommend/projects", [
                'user_id'             => $userId,
                'model_type'          => $modelType,
                'num_recommendations' => $limit,
                'exclude_known'       => true,
                'risk_tolerance'      => Auth::user()->risk_tolerance ?? 'medium',
                'investment_style'    => Auth::user()->investment_style ?? 'balanced',
            ]);

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
        try {
            // DIOPTIMALKAN: Gunakan timeout yang lebih rendah untuk HTTP requests
            $response = Http::timeout(3)->get("{$this->apiUrl}/recommend/trending", [
                'limit' => $limit,
            ])->json();

            return $response;
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan proyek trending: " . $e->getMessage());

            // Fallback ke data dari database lokal
            return Project::orderBy('trend_score', 'desc')
                ->limit($limit)
                ->get();
        }
    }
}

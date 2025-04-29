<?php
namespace App\Http\Controllers\Backend;

use App\Models\User;
use App\Models\Project;
use App\Models\Portfolio;
use App\Models\ActivityLog;
use App\Models\Interaction;
use Illuminate\Http\Request;
use App\Models\Recommendation;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

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
        $this->apiUrl = env('RECOMMENDATION_API_URL', 'http://localhost:8000');
    }

    /**
     * Menampilkan dashboard pengguna
     */
    public function index()
    {
        $user = Auth::user();

        // Ambil rekomendasi personal terbaru
        $personalRecommendations = $this->getPersonalRecommendations($user->user_id, 'hybrid', 4);

        // Ambil proyek trending
        $trendingProjects = $this->getTrendingProjects(4);

        // Ambil interaksi terbaru pengguna
        $recentInteractions = Interaction::forUser($user->user_id)
            ->with('project')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Ambil dan hitung data portofolio jika ada
        $portfolioSummary = $this->getPortfolioSummary($user->user_id);

        // Catat aktivitas
        ActivityLog::create([
            'user_id'       => $user->user_id,
            'activity_type' => 'dashboard_view',
            'description'   => 'Melihat dashboard',
            'ip_address'    => request()->ip(),
            'user_agent'    => request()->userAgent(),
        ]);

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
                'price'  => $portfolio->project->price_usd,
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
            // Coba ambil dari API
            $response = Http::post("{$this->apiUrl}/recommend/projects", [
                'user_id'             => $userId,
                'model_type'          => $modelType,
                'num_recommendations' => $limit,
                'exclude_known'       => true,
                'risk_tolerance'      => Auth::user()->risk_tolerance ?? 'medium',
                'investment_style'    => Auth::user()->investment_style ?? 'balanced',
            ])->json();

            return $response['recommendations'] ?? [];
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan rekomendasi personal: " . $e->getMessage());

            // Fallback ke data dari database lokal
            return Recommendation::where('user_id', $userId)
                ->where('recommendation_type', $modelType)
                ->with('project')
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
        try {
            // Coba ambil dari API
            $response = Http::get("{$this->apiUrl}/recommend/trending", [
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

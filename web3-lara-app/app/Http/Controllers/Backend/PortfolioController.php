<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use App\Models\PriceAlert;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PortfolioController extends Controller
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
     * BARU: Menampilkan halaman overview dengan data onchain real
     */
    public function index()
    {
        $user = Auth::user();
        $walletAddress = $user->wallet_address;

        // Ambil data portfolio onchain real
        $onchainPortfolio = Cache::remember("onchain_portfolio_{$walletAddress}", 5, function () use ($walletAddress) {
            return $this->getOnchainPortfolio($walletAddress);
        });

        // Ambil data manual transactions
        $manualPortfolios = Portfolio::forUser($user->user_id)
            ->with('project')
            ->get();

        // Hitung nilai total manual portfolio
        $manualTotalValue = 0;
        $manualTotalCost = 0;

        foreach ($manualPortfolios as $portfolio) {
            $manualTotalValue += $portfolio->current_value;
            $manualTotalCost += $portfolio->initial_value;
        }

        // Distribusi berdasarkan data onchain
        $onchainCategoryDistribution = $this->calculateOnchainCategoryDistribution($onchainPortfolio);
        $onchainChainDistribution = $this->calculateOnchainChainDistribution($onchainPortfolio);

        return view('backend.portfolio.index', [
            // Data Onchain (Real)
            'onchainPortfolio' => $onchainPortfolio,
            'onchainCategoryDistribution' => $onchainCategoryDistribution,
            'onchainChainDistribution' => $onchainChainDistribution,

            // Data Manual (Transaction Management)
            'manualPortfolios' => $manualPortfolios,
            'manualTotalValue' => $manualTotalValue,
            'manualTotalCost' => $manualTotalCost,
            'manualProfitLoss' => $manualTotalValue - $manualTotalCost,
            'manualProfitLossPercentage' => $manualTotalCost > 0 ? (($manualTotalValue - $manualTotalCost) / $manualTotalCost) * 100 : 0,

            // Combined data
            'walletAddress' => $walletAddress,
        ]);
    }

    /**
     * BARU: Menampilkan halaman onchain analytics
     */
    public function onchainAnalytics()
    {
        $user = Auth::user();
        $walletAddress = $user->wallet_address;

        // Ambil data analytics dari API
        $analytics = Cache::remember("onchain_analytics_{$walletAddress}", 10, function () use ($walletAddress) {
            return $this->getOnchainAnalytics($walletAddress);
        });

        // Ambil transaksi terbaru
        $recentTransactions = Cache::remember("onchain_transactions_{$walletAddress}", 5, function () use ($walletAddress) {
            return $this->getOnchainTransactions($walletAddress, 50);
        });

        return view('backend.portfolio.onchain_analytics', [
            'analytics' => $analytics,
            'recentTransactions' => $recentTransactions,
            'walletAddress' => $walletAddress,
        ]);
    }

    /**
     * DIUBAH: Transaction Management (dulu: transactions)
     */
    public function transactionManagement()
    {
        $user = Auth::user();

        // Ambil data transaksi manual
        $transactions = Transaction::forUser($user->user_id)
            ->with('project')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Ambil statistik volume transaksi manual
        $volumeStats = Transaction::getTotalVolume($user->user_id);

        // Ambil proyek dengan transaksi terbanyak
        $mostTradedProjects = Transaction::getMostTradedProjects($user->user_id, 5);

        // Statistik pengaruh rekomendasi terhadap transaksi
        $recommendationInfluence = Transaction::getRecommendationInfluence($user->user_id);

        return view('backend.portfolio.transaction_management', [
            'transactions' => $transactions,
            'volumeStats' => $volumeStats,
            'mostTradedProjects' => $mostTradedProjects,
            'recommendationInfluence' => $recommendationInfluence,
        ]);
    }

    /**
     * Menampilkan halaman price alerts (existing)
     */
    public function priceAlerts()
    {
        $user = Auth::user();

        // Ambil data alert harga
        $activeAlerts = PriceAlert::forUser($user->user_id)
            ->active()
            ->with('project')
            ->orderBy('created_at', 'desc')
            ->get();

        $triggeredAlerts = PriceAlert::forUser($user->user_id)
            ->triggered()
            ->with('project')
            ->orderBy('triggered_at', 'desc')
            ->limit(10)
            ->get();

        // Ambil statistik alert
        $alertStats = PriceAlert::getAlertStats($user->user_id);

        return view('backend.portfolio.price_alerts', [
            'activeAlerts' => $activeAlerts,
            'triggeredAlerts' => $triggeredAlerts,
            'alertStats' => $alertStats,
        ]);
    }

    /**
     * BARU: AJAX endpoint untuk refresh onchain data
     */
    public function refreshOnchainData()
    {
        $user = Auth::user();
        $walletAddress = $user->wallet_address;

        try {
            // Clear cache dan ambil data fresh
            Cache::forget("onchain_portfolio_{$walletAddress}");
            Cache::forget("onchain_analytics_{$walletAddress}");
            Cache::forget("onchain_transactions_{$walletAddress}");

            $onchainPortfolio = $this->getOnchainPortfolio($walletAddress);
            $analytics = $this->getOnchainAnalytics($walletAddress);

            return response()->json([
                'success' => true,
                'portfolio' => $onchainPortfolio,
                'analytics' => $analytics,
                'message' => 'Data onchain berhasil diperbarui'
            ]);

        } catch (\Exception $e) {
            Log::error("Error refreshing onchain data: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data onchain: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menambahkan transaksi baru (existing - no change)
     */
    public function addTransaction(Request $request)
    {
        $user = Auth::user();

        // Validasi input
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'transaction_type' => 'required|in:buy,sell',
            'amount' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Ambil data proyek
        $project = Project::find($request->project_id);

        // Hitung total nilai transaksi
        $totalValue = $request->amount * $request->price;

        // Simpan transaksi
        $transaction = Transaction::create([
            'user_id' => $user->user_id,
            'project_id' => $request->project_id,
            'transaction_type' => $request->transaction_type,
            'amount' => $request->amount,
            'price' => $request->price,
            'total_value' => $totalValue,
            'transaction_hash' => $request->transaction_hash,
            'followed_recommendation' => $request->has('followed_recommendation'),
            'recommendation_id' => $request->recommendation_id,
        ]);

        // Update atau buat portfolio
        if ($request->transaction_type === 'buy') {
            $this->processBuyTransaction($user->user_id, $request->project_id, $request->amount, $request->price);
        } else {
            $this->processSellTransaction($user->user_id, $request->project_id, $request->amount, $request->price);
        }

        return redirect()->route('panel.portfolio.transaction-management')
            ->with('success', 'Transaksi berhasil ditambahkan.');
    }

    /**
     * Menambahkan price alert baru (existing - no change)
     */
    public function addPriceAlert(Request $request)
    {
        $user = Auth::user();

        // Validasi input
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'target_price' => 'required|numeric|min:0',
            'alert_type' => 'required|in:above,below',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Simpan price alert
        PriceAlert::create([
            'user_id' => $user->user_id,
            'project_id' => $request->project_id,
            'target_price' => $request->target_price,
            'alert_type' => $request->alert_type,
            'is_triggered' => false,
        ]);

        return redirect()->route('panel.portfolio.price-alerts')
            ->with('success', 'Alert harga berhasil ditambahkan.');
    }

    /**
     * Menghapus price alert (existing - no change)
     */
    public function deletePriceAlert($id)
    {
        $user = Auth::user();

        // Cari price alert
        $priceAlert = PriceAlert::where('id', $id)
            ->where('user_id', $user->user_id)
            ->first();

        if (!$priceAlert) {
            return redirect()->route('panel.portfolio.price-alerts')
                ->with('error', 'Alert harga tidak ditemukan.');
        }

        // Hapus price alert
        $priceAlert->delete();

        return redirect()->route('panel.portfolio.price-alerts')
            ->with('success', 'Alert harga berhasil dihapus.');
    }

    /**
     * BARU: Mendapatkan portfolio onchain dari API
     */
    private function getOnchainPortfolio($walletAddress)
    {
        try {
            $response = Http::timeout(10)->get("{$this->apiUrl}/blockchain/portfolio/{$walletAddress}", [
                'chains' => ['eth', 'bsc', 'polygon']
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Enrich dengan data project dari database lokal
                $data = $this->enrichPortfolioWithProjectData($data);

                return $data;
            } else {
                Log::warning("Failed to fetch onchain portfolio: " . $response->body());
                return $this->getEmptyPortfolioData();
            }

        } catch (\Exception $e) {
            Log::error("Error fetching onchain portfolio: " . $e->getMessage());
            return $this->getEmptyPortfolioData();
        }
    }

    /**
     * BARU: Mendapatkan analytics onchain dari API
     */
    private function getOnchainAnalytics($walletAddress)
    {
        try {
            $response = Http::timeout(10)->get("{$this->apiUrl}/blockchain/analytics/{$walletAddress}", [
                'days' => 30
            ]);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::warning("Failed to fetch onchain analytics: " . $response->body());
                return $this->getEmptyAnalyticsData();
            }

        } catch (\Exception $e) {
            Log::error("Error fetching onchain analytics: " . $e->getMessage());
            return $this->getEmptyAnalyticsData();
        }
    }

    /**
     * BARU: Mendapatkan transaksi onchain dari API
     */
    private function getOnchainTransactions($walletAddress, $limit = 50)
    {
        try {
            $response = Http::timeout(10)->get("{$this->apiUrl}/blockchain/transactions/{$walletAddress}", [
                'limit' => $limit,
                'chains' => ['eth', 'bsc', 'polygon']
            ]);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::warning("Failed to fetch onchain transactions: " . $response->body());
                return [];
            }

        } catch (\Exception $e) {
            Log::error("Error fetching onchain transactions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * BARU: Enrich portfolio data dengan informasi proyek dari database
     */
    private function enrichPortfolioWithProjectData($portfolioData)
    {
        if (!isset($portfolioData['token_balances'])) {
            return $portfolioData;
        }

        foreach ($portfolioData['token_balances'] as &$token) {
            // Cari project berdasarkan symbol
            $project = Project::where('symbol', $token['token_symbol'])->first();

            if ($project) {
                $token['project_data'] = [
                    'id' => $project->id,
                    'name' => $project->name,
                    'image' => $project->image,
                    'current_price' => $project->current_price,
                    'price_change_24h' => $project->price_change_percentage_24h,
                    'primary_category' => $project->primary_category,
                ];

                // Hitung USD value jika ada harga
                if ($project->current_price && $token['balance']) {
                    $token['usd_value'] = $token['balance'] * $project->current_price;
                }
            }
        }

        return $portfolioData;
    }

    /**
     * BARU: Hitung distribusi kategori dari portfolio onchain
     */
    private function calculateOnchainCategoryDistribution($portfolioData)
    {
        if (!isset($portfolioData['token_balances'])) {
            return [];
        }

        $categories = [];

        foreach ($portfolioData['token_balances'] as $token) {
            if (isset($token['project_data']['primary_category']) && isset($token['usd_value'])) {
                $category = $token['project_data']['primary_category'] ?: 'Unknown';

                if (!isset($categories[$category])) {
                    $categories[$category] = [
                        'primary_category' => $category,
                        'value' => 0,
                        'project_count' => 0
                    ];
                }

                $categories[$category]['value'] += $token['usd_value'];
                $categories[$category]['project_count']++;
            }
        }

        return array_values($categories);
    }

    /**
     * BARU: Hitung distribusi chain dari portfolio onchain
     */
    private function calculateOnchainChainDistribution($portfolioData)
    {
        $chains = [];

        // Native balances
        if (isset($portfolioData['native_balances'])) {
            foreach ($portfolioData['native_balances'] as $balance) {
                $chain = $balance['chain'] ?? 'Unknown';

                if (!isset($chains[$chain])) {
                    $chains[$chain] = [
                        'chain' => $chain,
                        'value' => 0,
                        'project_count' => 0
                    ];
                }

                // Approximate USD value (simplified)
                $estimatedValue = $balance['balance'] * 100; // Rough estimate
                $chains[$chain]['value'] += $estimatedValue;
                $chains[$chain]['project_count']++;
            }
        }

        // Token balances
        if (isset($portfolioData['token_balances'])) {
            foreach ($portfolioData['token_balances'] as $token) {
                $chain = $token['chain'] ?? 'Unknown';

                if (!isset($chains[$chain])) {
                    $chains[$chain] = [
                        'chain' => $chain,
                        'value' => 0,
                        'project_count' => 0
                    ];
                }

                if (isset($token['usd_value'])) {
                    $chains[$chain]['value'] += $token['usd_value'];
                    $chains[$chain]['project_count']++;
                }
            }
        }

        return array_values($chains);
    }

    /**
     * BARU: Return empty portfolio data structure
     */
    private function getEmptyPortfolioData()
    {
        return [
            'wallet_address' => '',
            'total_usd_value' => 0,
            'native_balances' => [],
            'token_balances' => [],
            'last_updated' => now()->toISOString(),
            'chains_scanned' => []
        ];
    }

    /**
     * BARU: Return empty analytics data structure
     */
    private function getEmptyAnalyticsData()
    {
        return [
            'wallet_address' => '',
            'total_transactions' => 0,
            'unique_tokens_traded' => 0,
            'total_volume_usd' => 0,
            'most_traded_tokens' => [],
            'transaction_frequency' => [],
            'chains_activity' => []
        ];
    }

    /**
     * Memproses transaksi pembelian (existing - no change)
     */
    private function processBuyTransaction($userId, $projectId, $amount, $price)
    {
        // Cek apakah sudah ada portfolio
        $portfolio = Portfolio::where('user_id', $userId)
            ->where('project_id', $projectId)
            ->first();

        if ($portfolio) {
            // Update portfolio yang sudah ada
            $newTotalAmount = $portfolio->amount + $amount;
            $newTotalCost = $portfolio->amount * $portfolio->average_buy_price + $amount * $price;
            $newAveragePrice = $newTotalCost / $newTotalAmount;

            $portfolio->amount = $newTotalAmount;
            $portfolio->average_buy_price = $newAveragePrice;
            $portfolio->save();
        } else {
            // Buat portfolio baru
            Portfolio::create([
                'user_id' => $userId,
                'project_id' => $projectId,
                'amount' => $amount,
                'average_buy_price' => $price,
            ]);
        }
    }

    /**
     * Memproses transaksi penjualan (existing - no change)
     */
    private function processSellTransaction($userId, $projectId, $amount, $price)
    {
        // Cek apakah ada portfolio
        $portfolio = Portfolio::where('user_id', $userId)
            ->where('project_id', $projectId)
            ->first();

        if (!$portfolio) {
            // Tidak ada portfolio untuk dijual
            return false;
        }

        // Pastikan jumlah yang dijual tidak melebihi yang dimiliki
        if ($amount > $portfolio->amount) {
            // Jumlah yang dijual terlalu banyak
            return false;
        }

        // Update portfolio
        $newAmount = $portfolio->amount - $amount;

        if ($newAmount > 0) {
            // Masih ada sisa
            $portfolio->amount = $newAmount;
            $portfolio->save();
        } else {
            // Habis, hapus portfolio
            $portfolio->delete();
        }

        return true;
    }
}

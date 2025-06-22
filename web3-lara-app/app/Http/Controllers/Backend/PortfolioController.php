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
     * ⚡ ENHANCED: Timeout yang lebih agresif dan retry logic
     */
    protected $apiTimeout = 60; // ⚡ REDUCED: 1 menit untuk native-focused API calls
    protected $maxRetries = 2;

    /**
     * Konstruktor untuk mengatur URL API
     */
    public function __construct()
    {
        $this->apiUrl = env('RECOMMENDATION_API_URL', 'http://localhost:8001');
    }

    /**
     * ⚡ ENHANCED: Menampilkan halaman overview dengan lazy loading yang lebih baik
     */
    public function index()
    {
        $user          = Auth::user();
        $walletAddress = $user->wallet_address;

        // Check API status
        $apiStatus = $this->checkApiStatus();

        // Ambil data manual transactions
        $manualPortfolios = Portfolio::forUser($user->user_id)
            ->with('project')
            ->get();

        // Hitung nilai total manual portfolio
        $manualTotalValue = 0;
        $manualTotalCost  = 0;

        foreach ($manualPortfolios as $portfolio) {
            $manualTotalValue += $portfolio->current_value;
            $manualTotalCost += $portfolio->initial_value;
        }

        return view('backend.portfolio.index', [
            // Manual Portfolio (Transaction Management)
            'manualPortfolios'           => $manualPortfolios,
            'manualTotalValue'           => $manualTotalValue,
            'manualTotalCost'            => $manualTotalCost,
            'manualProfitLoss'           => $manualTotalValue - $manualTotalCost,
            'manualProfitLossPercentage' => $manualTotalCost > 0 ? (($manualTotalValue - $manualTotalCost) / $manualTotalCost) * 100 : 0,

            // Combined data
            'walletAddress'              => $walletAddress,
            'apiStatus'                  => $apiStatus,
            'apiUrl'                     => $this->apiUrl,
        ]);
    }

    /**
     * ⚡ ENHANCED: Menampilkan halaman onchain analytics dengan lazy loading dan better caching
     */
    public function onchainAnalytics()
    {
        $user          = Auth::user();
        $walletAddress = $user->wallet_address;

        // ⚡ LAZY LOADING: Tidak load data saat page load, hanya kirim wallet address
        // Data akan di-load via AJAX setelah page ready

        // Check API status
        $apiStatus = $this->checkApiStatus();

        return view('backend.portfolio.onchain_analytics', [
            'walletAddress' => $walletAddress,
            'apiStatus'     => $apiStatus,
            'apiUrl'        => $this->apiUrl,
            // ⚡ REMOVED: analytics dan recentTransactions - akan di-load via AJAX
        ]);
    }

    /**
     * ⚡ NEW: AJAX endpoint untuk load analytics data dengan strong caching
     */
    public function loadAnalyticsData()
    {
        $user          = Auth::user();
        $walletAddress = $user->wallet_address;

        try {
            // ⚡ STRONG CACHING: 30 menit untuk analytics (lebih lama dari portfolio)
            $analytics = Cache::remember("onchain_analytics_v3_{$walletAddress}", 30, function () use ($walletAddress) {
                try {
                    return $this->getOnchainAnalytics($walletAddress);
                } catch (\Exception $e) {
                    Log::warning("Failed to fetch onchain analytics for {$walletAddress}: " . $e->getMessage());
                    return $this->getEmptyAnalyticsData();
                }
            });

            // ⚡ STRONG CACHING: 15 menit untuk transactions
            $recentTransactions = Cache::remember("onchain_transactions_v3_{$walletAddress}", 15, function () use ($walletAddress) {
                try {
                    return $this->getOnchainTransactions($walletAddress, 50);
                } catch (\Exception $e) {
                    Log::warning("Failed to fetch onchain transactions for {$walletAddress}: " . $e->getMessage());
                    return [];
                }
            });

            return response()->json([
                'success'      => true,
                'analytics'    => $analytics,
                'transactions' => $recentTransactions,
                'cached'       => true,
                'optimization' => 'native_token_focus',
            ]);

        } catch (\Exception $e) {
            Log::error("Error loading analytics data for {$walletAddress}: " . $e->getMessage());

            return response()->json([
                'success'      => false,
                'message'      => 'Gagal memuat data analytics: ' . $e->getMessage(),
                'analytics'    => $this->getEmptyAnalyticsData(),
                'transactions' => [],
            ], 500);
        }
    }

    /**
     * Transaction Management (dulu: transactions)
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
            'transactions'            => $transactions,
            'volumeStats'             => $volumeStats,
            'mostTradedProjects'      => $mostTradedProjects,
            'recommendationInfluence' => $recommendationInfluence,
            'apiUrl'                  => $this->apiUrl,
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
            'activeAlerts'    => $activeAlerts,
            'triggeredAlerts' => $triggeredAlerts,
            'alertStats'      => $alertStats,
            'apiUrl'          => $this->apiUrl,
        ]);
    }

    /**
     * ⚡ ENHANCED: AJAX endpoint untuk refresh onchain data dengan smart cache management
     */
    public function refreshOnchainData()
    {
        $user          = Auth::user();
        $walletAddress = $user->wallet_address;

        try {
            // ⚡ Check API status terlebih dahulu
            $apiStatus = $this->checkApiStatus();
            if (! $apiStatus['available']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blockchain API tidak tersedia saat ini: ' . $apiStatus['error'],
                ], 503);
            }

            // ⚡ SMART CACHE MANAGEMENT: Clear dengan versioning
            $cacheKeys = [
                "onchain_portfolio_{$walletAddress}",
                "onchain_portfolio_v2_{$walletAddress}",
                "onchain_portfolio_v3_{$walletAddress}",
                "onchain_analytics_{$walletAddress}",
                "onchain_analytics_v2_{$walletAddress}",
                "onchain_analytics_v3_{$walletAddress}",
                "onchain_transactions_{$walletAddress}",
                "onchain_transactions_v2_{$walletAddress}",
                "onchain_transactions_v3_{$walletAddress}",
            ];

            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }

            $onchainPortfolio = $this->getOnchainPortfolio($walletAddress);
            $analytics        = $this->getOnchainAnalytics($walletAddress);

            // ⚡ IMMEDIATE CACHE: Store fresh data untuk subsequent requests
            Cache::put("onchain_portfolio_v3_{$walletAddress}", $onchainPortfolio, 30); // 30 minutes
            Cache::put("onchain_analytics_v3_{$walletAddress}", $analytics, 30);        // 30 minutes

            return response()->json([
                'success'      => true,
                'portfolio'    => $onchainPortfolio,
                'analytics'    => $analytics,
                'message'      => 'Data onchain berhasil diperbarui dengan fokus native token',
                'cached_for'   => '30 minutes',
                'optimization' => 'native_token_focus_enabled',
            ]);

        } catch (\Exception $e) {
            Log::error("Error refreshing onchain data for {$walletAddress}: " . $e->getMessage());

            // ⚡ ENHANCED: Better error categorization
            $errorMessage = 'Gagal memperbarui data onchain';

            if (str_contains($e->getMessage(), 'timeout') || str_contains($e->getMessage(), 'Operation timed out')) {
                $errorMessage = 'Request timeout - API blockchain sedang lambat. Sistem fokus pada native tokens untuk speed.';
            } elseif (str_contains($e->getMessage(), 'Connection refused')) {
                $errorMessage = 'API blockchain tidak dapat diakses. Pastikan service sedang berjalan.';
            } elseif (str_contains($e->getMessage(), 'cURL error')) {
                $errorMessage = 'Koneksi ke API blockchain gagal. Periksa konfigurasi jaringan.';
            }

            return response()->json([
                'success'       => false,
                'message'       => $errorMessage,
                'error_details' => $e->getMessage(),
                'cache_cleared' => true,
            ], 500);
        }
    }

    /**
     * ⚡ ENHANCED: Check API status dengan timeout yang tepat
     */
    private function checkApiStatus()
    {
        try {
            $response = Http::timeout(10)->get("{$this->apiUrl}/health");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'available'       => true,
                    'status'          => 'healthy',
                    'blockchain_apis' => $data['blockchain_apis'] ?? [],
                    'optimization'    => $data['optimization_status'] ?? [],
                    'response_time'   => $response->transferStats->getTransferTime() ?? 0,
                ];
            } else {
                return [
                    'available' => false,
                    'status'    => 'error',
                    'error'     => "API returned status {$response->status()}",
                ];
            }
        } catch (\Exception $e) {
            return [
                'available' => false,
                'status'    => 'unreachable',
                'error'     => $e->getMessage(),
            ];
        }
    }

    /**
     * ⚡ ENHANCED: Mendapatkan portfolio onchain dengan retry logic dan extended timeout
     */
    private function getOnchainPortfolio($walletAddress)
    {
        $attempt   = 0;
        $lastError = null;

        while ($attempt < $this->maxRetries) {
            try {
                $response = Http::timeout($this->apiTimeout)->get("{$this->apiUrl}/blockchain/portfolio/{$walletAddress}", [
                    'chains' => ['eth', 'bsc', 'polygon'],
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    // ⚡ IMPROVED: Validasi structure response
                    if (! isset($data['wallet_address'])) {
                        Log::warning("Invalid portfolio response structure for {$walletAddress}");
                        return $this->getEmptyPortfolioData();
                    }

                    // ⚡ ENHANCED: Enrich dengan data project dari database lokal
                    $data = $this->enrichPortfolioWithProjectData($data);

                    Log::info("Successfully fetched and enriched onchain portfolio for {$walletAddress}: " .
                        count($data['native_balances'] ?? []) . " native + " .
                        count($data['token_balances'] ?? []) . " tokens, Total USD: $" .
                        number_format($data['total_usd_value'] ?? 0, 8) .
                        ", Filtered: " . ($data['filtered_tokens_count'] ?? 0) . " spam tokens");

                    return $data;
                } else {
                    $lastError = "Blockchain API returned error {$response->status()}: " . $response->body();
                    Log::warning($lastError . " for portfolio {$walletAddress} (attempt " . ($attempt + 1) . ")");
                }

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Log::error("Exception fetching onchain portfolio for {$walletAddress} (attempt " . ($attempt + 1) . "): " . $lastError);

                // If it's timeout on last attempt, throw immediately
                if ($attempt == $this->maxRetries - 1 || str_contains($lastError, 'timeout')) {
                    throw $e;
                }
            }

            $attempt++;

            // Wait before retry (exponential backoff)
            if ($attempt < $this->maxRetries) {
                sleep(pow(2, $attempt)); // 2s, 4s, 8s...
            }
        }

        // If all attempts failed, throw the last error
        throw new \Exception($lastError ?: 'Unknown error after multiple attempts');
    }

    /**
     * ⚡ ENHANCED: Mendapatkan analytics onchain dengan retry logic
     */
    private function getOnchainAnalytics($walletAddress)
    {
        $attempt   = 0;
        $lastError = null;

        while ($attempt < $this->maxRetries) {
            try {
                $response = Http::timeout($this->apiTimeout)->get("{$this->apiUrl}/blockchain/analytics/{$walletAddress}", [
                    'days' => 30,
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    // ⚡ Validasi structure response
                    if (! isset($data['wallet_address'])) {
                        Log::warning("Invalid analytics response structure for {$walletAddress}");
                        return $this->getEmptyAnalyticsData();
                    }

                    Log::info("Successfully fetched onchain analytics for {$walletAddress}: " .
                        ($data['total_transactions'] ?? 0) . " transactions, Volume: $" .
                        number_format($data['total_volume_usd'] ?? 0, 8));

                    return $data;
                } else {
                    $lastError = "Blockchain API returned error {$response->status()}: " . $response->body();
                    Log::warning($lastError . " for analytics {$walletAddress} (attempt " . ($attempt + 1) . ")");
                }

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Log::error("Exception fetching onchain analytics for {$walletAddress} (attempt " . ($attempt + 1) . "): " . $lastError);

                if ($attempt == $this->maxRetries - 1 || str_contains($lastError, 'timeout')) {
                    throw $e;
                }
            }

            $attempt++;
            if ($attempt < $this->maxRetries) {
                sleep(pow(2, $attempt));
            }
        }

        throw new \Exception($lastError ?: 'Unknown error after multiple attempts');
    }

    /**
     * ⚡ ENHANCED: Mendapatkan transaksi onchain dengan retry logic
     */
    private function getOnchainTransactions($walletAddress, $limit = 50)
    {
        $attempt   = 0;
        $lastError = null;

        while ($attempt < $this->maxRetries) {
            try {
                $response = Http::timeout($this->apiTimeout)->get("{$this->apiUrl}/blockchain/transactions/{$walletAddress}", [
                    'limit'  => $limit,
                    'chains' => ['eth', 'bsc', 'polygon'],
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    // ⚡ Validasi response adalah array
                    if (! is_array($data)) {
                        Log::warning("Invalid transactions response format for {$walletAddress}");
                        return [];
                    }

                    Log::info("Successfully fetched onchain transactions for {$walletAddress}: " . count($data) . " transactions");

                    return $data;
                } else {
                    $lastError = "Blockchain API returned error {$response->status()}: " . $response->body();
                    Log::warning($lastError . " for transactions {$walletAddress} (attempt " . ($attempt + 1) . ")");
                }

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Log::error("Exception fetching onchain transactions for {$walletAddress} (attempt " . ($attempt + 1) . "): " . $lastError);

                if ($attempt == $this->maxRetries - 1 || str_contains($lastError, 'timeout')) {
                    throw $e;
                }
            }

            $attempt++;
            if ($attempt < $this->maxRetries) {
                sleep(pow(2, $attempt));
            }
        }

        throw new \Exception($lastError ?: 'Unknown error after multiple attempts');
    }

    /**
     * ⚡ ENHANCED: Enrich portfolio data dengan informasi proyek yang lebih comprehensive dan spam filtering
     */
    private function enrichPortfolioWithProjectData($portfolioData)
    {
        // Enrich native balances
        if (isset($portfolioData['native_balances'])) {
            foreach ($portfolioData['native_balances'] as &$balance) {
                // Cari project berdasarkan symbol native
                $project = Project::where('symbol', $balance['token_symbol'])->first();

                if ($project) {
                    $balance['project_data'] = [
                        'id'               => $project->id,
                        'name'             => $project->name,
                        'image'            => $project->image,
                        'current_price'    => $project->current_price,
                        'price_change_24h' => $project->price_change_percentage_24h,
                        'primary_category' => $project->primary_category ?: 'Layer-1',
                    ];
                } else {
                    // Default category untuk native tokens yang tidak ada di database
                    $balance['project_data'] = [
                        'id'               => $balance['token_symbol'],
                        'name'             => $balance['token_name'],
                        'image'            => null,
                        'current_price'    => null,
                        'price_change_24h' => null,
                        'primary_category' => 'Layer-1',
                    ];
                }
            }
        }

        // Enrich token balances dengan spam filtering
        if (isset($portfolioData['token_balances'])) {
            foreach ($portfolioData['token_balances'] as &$token) {
                // Skip spam tokens untuk enrichment
                if (isset($token['is_spam']) && $token['is_spam']) {
                    $token['project_data'] = [
                        'id'               => $token['token_address'],
                        'name'             => $token['token_name'],
                        'image'            => null,
                        'current_price'    => null,
                        'price_change_24h' => null,
                        'primary_category' => 'Spam/Scam',
                    ];
                    continue;
                }

                // Cari project berdasarkan symbol, tapi prioritaskan yang sesuai dengan chain
                $project = Project::where('symbol', $token['token_symbol'])
                    ->orderByRaw("CASE WHEN chain = ? THEN 1 ELSE 2 END", [$token['chain']])
                    ->first();

                if ($project) {
                    $token['project_data'] = [
                        'id'               => $project->id,
                        'name'             => $project->name,
                        'image'            => $project->image,
                        'current_price'    => $project->current_price,
                        'price_change_24h' => $project->price_change_percentage_24h,
                        'primary_category' => $project->primary_category ?: $this->inferCategoryFromTokenName($token['token_name']),
                    ];
                } else {
                    // ⚡ AUTO: Infer kategori dari nama token jika tidak ada di database
                    $token['project_data'] = [
                        'id'               => $token['token_address'],
                        'name'             => $token['token_name'],
                        'image'            => null,
                        'current_price'    => null,
                        'price_change_24h' => null,
                        'primary_category' => $this->inferCategoryFromTokenName($token['token_name']),
                    ];
                }
            }
        }

        return $portfolioData;
    }

    /**
     * ⚡ ENHANCED: Auto-infer kategori dengan spam detection
     */
    private function inferCategoryFromTokenName($tokenName)
    {
        $tokenName = strtolower($tokenName);

        // ⚡ ENHANCED: Detect spam/scam first
        $spamPatterns = ['claim', 'reward', 'airdrop', 'visit', 'free', '.com', '.net'];
        foreach ($spamPatterns as $pattern) {
            if (strpos($tokenName, $pattern) !== false) {
                return 'Spam/Scam';
            }
        }

        // Auto-detect patterns untuk kategori
        if (strpos($tokenName, 'wrapped') !== false || strpos($tokenName, 'weth') !== false) {
            return 'DeFi';
        }

        if (strpos($tokenName, 'stablecoin') !== false || strpos($tokenName, 'usd') !== false ||
            strpos($tokenName, 'tether') !== false || strpos($tokenName, 'usdc') !== false) {
            return 'Stablecoin';
        }

        if (strpos($tokenName, 'game') !== false || strpos($tokenName, 'play') !== false) {
            return 'Gaming';
        }

        if (strpos($tokenName, 'nft') !== false || strpos($tokenName, 'collectible') !== false) {
            return 'NFT';
        }

        if (strpos($tokenName, 'meme') !== false || strpos($tokenName, 'doge') !== false ||
            strpos($tokenName, 'shib') !== false) {
            return 'Meme';
        }

        // Default untuk token yang tidak dikenal
        return 'Other';
    }

    /**
     * ⚡ ENHANCED: Return empty portfolio data dengan spam filtering info
     */
    private function getEmptyPortfolioData()
    {
        return [
            'wallet_address'        => '',
            'total_usd_value'       => 0.0,
            'native_balances'       => [],
            'token_balances'        => [],
            'last_updated'          => now()->toISOString(),
            'chains_scanned'        => [],
            'filtered_tokens_count' => 0,
            'error_info'            => 'API not available or returned empty data',
            'optimization'          => 'native_token_focus',
        ];
    }

    /**
     * ⚡ ENHANCED: Return empty analytics data dengan spam filtering info
     */
    private function getEmptyAnalyticsData()
    {
        return [
            'wallet_address'        => '',
            'total_transactions'    => 0,
            'unique_tokens_traded'  => 0,
            'total_volume_usd'      => 0.0,
            'most_traded_tokens'    => [],
            'transaction_frequency' => [],
            'chains_activity'       => [],
            'error_info'            => 'API not available or returned empty data',
            'optimization'          => 'native_token_focus',
        ];
    }

    // ... Rest of the methods remain the same (addTransaction, addPriceAlert, etc.)

    /**
     * Menambahkan transaksi baru (existing - no change)
     */
    public function addTransaction(Request $request)
    {
        $user = Auth::user();

        // Validasi input
        $validator = Validator::make($request->all(), [
            'project_id'       => 'required|exists:projects,id',
            'transaction_type' => 'required|in:buy,sell',
            'amount'           => 'required|numeric|min:0',
            'price'            => 'required|numeric|min:0',
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
            'user_id'                 => $user->user_id,
            'project_id'              => $request->project_id,
            'transaction_type'        => $request->transaction_type,
            'amount'                  => $request->amount,
            'price'                   => $request->price,
            'total_value'             => $totalValue,
            'transaction_hash'        => $request->transaction_hash,
            'followed_recommendation' => $request->has('followed_recommendation'),
            'recommendation_id'       => $request->recommendation_id,
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
            'project_id'   => 'required|exists:projects,id',
            'target_price' => 'required|numeric|min:0',
            'alert_type'   => 'required|in:above,below',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Simpan price alert
        PriceAlert::create([
            'user_id'      => $user->user_id,
            'project_id'   => $request->project_id,
            'target_price' => $request->target_price,
            'alert_type'   => $request->alert_type,
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

        if (! $priceAlert) {
            return redirect()->route('panel.portfolio.price-alerts')
                ->with('error', 'Alert harga tidak ditemukan.');
        }

        // Hapus price alert
        $priceAlert->delete();

        return redirect()->route('panel.portfolio.price-alerts')
            ->with('success', 'Alert harga berhasil dihapus.');
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

        if (! $portfolio) {
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

    /**
     * ⚡ ENHANCED: Clear user cache helper
     */
    private function clearUserPortfolioCache($userId)
    {
        $walletAddress = Auth::user()->wallet_address;

        $cacheKeys = [
            "onchain_portfolio_{$walletAddress}",
            "onchain_portfolio_v2_{$walletAddress}",
            "onchain_portfolio_v3_{$walletAddress}",
            "onchain_analytics_{$walletAddress}",
            "onchain_analytics_v2_{$walletAddress}",
            "onchain_analytics_v3_{$walletAddress}",
            "onchain_transactions_{$walletAddress}",
            "onchain_transactions_v2_{$walletAddress}",
            "onchain_transactions_v3_{$walletAddress}",
            "dashboard_personal_recs_{$userId}",
            "user_portfolio_{$userId}",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        Log::info("Cleared portfolio cache for user {$userId} and wallet {$walletAddress}");
    }

    /**
     * ⚡ ENHANCED: Helper untuk format error messages
     */
    private function formatApiError($error, $context = 'general')
    {
        $baseMessage = 'Terjadi kesalahan saat mengakses blockchain API';

        if (str_contains($error, 'timeout') || str_contains($error, 'timed out')) {
            return [
                'message' => 'Request timeout - API blockchain sedang lambat',
                'details' => 'Sistem sedang memproses native tokens untuk speed. Coba lagi dalam 10-30 detik.',
                'type' => 'timeout'
            ];
        }

        if (str_contains($error, '503') || str_contains($error, '500')) {
            return [
                'message' => 'API blockchain sedang tidak tersedia',
                'details' => 'Service sedang maintenance atau overload. Coba lagi dalam beberapa menit.',
                'type' => 'server_error'
            ];
        }

        if (str_contains($error, 'Connection refused') || str_contains($error, 'network')) {
            return [
                'message' => 'Masalah koneksi jaringan',
                'details' => 'Periksa koneksi internet dan status API blockchain.',
                'type' => 'network_error'
            ];
        }

        if (str_contains($error, 'rate limit') || str_contains($error, '429')) {
            return [
                'message' => 'Rate limit tercapai',
                'details' => 'Terlalu banyak request. Tunggu beberapa saat sebelum mencoba lagi.',
                'type' => 'rate_limit'
            ];
        }

        return [
            'message' => $baseMessage,
            'details' => substr($error, 0, 200), // Limit error details
            'type' => 'unknown'
        ];
    }

    /**
     * ⚡ NEW: Get portfolio summary for dashboard
     */
    public function getPortfolioSummary()
    {
        $user = Auth::user();
        $walletAddress = $user->wallet_address;

        try {
            // Try to get cached portfolio data
            $portfolioData = Cache::remember("portfolio_summary_v3_{$walletAddress}", 15, function () use ($walletAddress) {
                try {
                    return $this->getOnchainPortfolio($walletAddress);
                } catch (\Exception $e) {
                    Log::warning("Failed to fetch portfolio summary for {$walletAddress}: " . $e->getMessage());
                    return $this->getEmptyPortfolioData();
                }
            });

            // Get manual portfolio data
            $manualPortfolios = Portfolio::forUser($user->user_id)->with('project')->get();
            $manualTotal = $manualPortfolios->sum('current_value');

            return response()->json([
                'success' => true,
                'data' => [
                    'onchain_total' => $portfolioData['total_usd_value'] ?? 0,
                    'manual_total' => $manualTotal,
                    'combined_total' => ($portfolioData['total_usd_value'] ?? 0) + $manualTotal,
                    'native_assets' => count($portfolioData['native_balances'] ?? []),
                    'token_assets' => count(array_filter($portfolioData['token_balances'] ?? [], function($token) {
                        return !($token['is_spam'] ?? false);
                    })),
                    'filtered_spam' => $portfolioData['filtered_tokens_count'] ?? 0,
                    'last_updated' => $portfolioData['last_updated'] ?? now(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting portfolio summary for {$walletAddress}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat ringkasan portfolio',
                'error' => $this->formatApiError($e->getMessage(), 'summary')
            ], 500);
        }
    }

    /**
     * ⚡ NEW: Validate wallet address format
     */
    private function isValidWalletAddress($address)
    {
        // Basic validation for Ethereum-like addresses
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
    }

    /**
     * ⚡ NEW: Get supported chains list
     */
    public function getSupportedChains()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'chains' => [
                    [
                        'id' => 'eth',
                        'name' => 'Ethereum',
                        'native_symbol' => 'ETH',
                        'explorer' => 'https://etherscan.io'
                    ],
                    [
                        'id' => 'bsc',
                        'name' => 'Binance Smart Chain',
                        'native_symbol' => 'BNB',
                        'explorer' => 'https://bscscan.com'
                    ],
                    [
                        'id' => 'polygon',
                        'name' => 'Polygon',
                        'native_symbol' => 'MATIC',
                        'explorer' => 'https://polygonscan.com'
                    ],
                    [
                        'id' => 'avalanche',
                        'name' => 'Avalanche',
                        'native_symbol' => 'AVAX',
                        'explorer' => 'https://snowtrace.io'
                    ]
                ],
                'native_tokens_supported' => ['ETH', 'BNB', 'MATIC', 'AVAX', 'WETH'],
                'optimization' => 'native_token_focus'
            ]
        ]);
    }

    /**
     * ⚡ NEW: Force refresh all cache for user
     */
    public function forceRefreshCache(Request $request)
    {
        $user = Auth::user();
        $walletAddress = $user->wallet_address;

        try {
            // Validate wallet address
            if (!$this->isValidWalletAddress($walletAddress)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format wallet address tidak valid'
                ], 400);
            }

            // Clear all user caches
            $this->clearUserPortfolioCache($user->user_id);

            // Additional cache keys specific to portfolio
            $additionalCacheKeys = [
                "portfolio_summary_v3_{$walletAddress}",
                "rec_personal_{$user->user_id}_10",
                "rec_personal_hybrid_{$user->user_id}",
            ];

            foreach ($additionalCacheKeys as $key) {
                Cache::forget($key);
            }

            return response()->json([
                'success' => true,
                'message' => 'Semua cache berhasil dibersihkan',
                'wallet_address' => $walletAddress,
                'next_action' => 'refresh_page_or_load_data'
            ]);

        } catch (\Exception $e) {
            Log::error("Error force refreshing cache for user {$user->user_id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal membersihkan cache',
                'error' => $this->formatApiError($e->getMessage(), 'cache')
            ], 500);
        }
    }

    /**
     * ⚡ NEW: Get API status with detailed info
     */
    public function getApiStatus()
    {
        try {
            $apiStatus = $this->checkApiStatus();

            return response()->json([
                'success' => true,
                'data' => $apiStatus,
                'timestamp' => now(),
                'recommendations' => $this->getApiStatusRecommendations($apiStatus)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengecek status API',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ⚡ NEW: Get recommendations based on API status
     */
    private function getApiStatusRecommendations($apiStatus)
    {
        $recommendations = [];

        if (!$apiStatus['available']) {
            $recommendations[] = 'API blockchain tidak tersedia - coba lagi dalam beberapa menit';
            $recommendations[] = 'Pastikan koneksi internet stabil';
            $recommendations[] = 'Periksa status API di health check endpoint';
        } else {
            $recommendations[] = 'API blockchain berjalan normal';
            $recommendations[] = 'Sistem telah dioptimasi untuk native tokens (ETH, BNB, MATIC, AVAX)';

            if (isset($apiStatus['response_time']) && $apiStatus['response_time'] > 5) {
                $recommendations[] = 'Response time agak lambat - pertimbangkan untuk refresh cache';
            }
        }

        return $recommendations;
    }

    /**
     * ⚡ NEW: Export portfolio data
     */
    public function exportPortfolioData(Request $request)
    {
        $user = Auth::user();
        $walletAddress = $user->wallet_address;
        $format = $request->input('format', 'json'); // json, csv

        try {
            // Get fresh data
            $onchainData = $this->getOnchainPortfolio($walletAddress);
            $manualPortfolios = Portfolio::forUser($user->user_id)->with('project')->get();

            $exportData = [
                'user_id' => $user->user_id,
                'wallet_address' => $walletAddress,
                'export_timestamp' => now()->toISOString(),
                'onchain_portfolio' => $onchainData,
                'manual_portfolio' => $manualPortfolios->toArray(),
                'summary' => [
                    'onchain_total_usd' => $onchainData['total_usd_value'] ?? 0,
                    'manual_total_usd' => $manualPortfolios->sum('current_value'),
                    'native_assets_count' => count($onchainData['native_balances'] ?? []),
                    'token_assets_count' => count($onchainData['token_balances'] ?? []),
                    'spam_filtered_count' => $onchainData['filtered_tokens_count'] ?? 0,
                ]
            ];

            if ($format === 'csv') {
                // Simplified CSV export for portfolio holdings
                $csvData = [];

                // Add native balances
                foreach ($onchainData['native_balances'] ?? [] as $balance) {
                    $csvData[] = [
                        'type' => 'native',
                        'symbol' => $balance['token_symbol'],
                        'name' => $balance['token_name'],
                        'balance' => $balance['balance'],
                        'usd_value' => $balance['usd_value'] ?? 0,
                        'chain' => $balance['chain'],
                        'is_spam' => 'false'
                    ];
                }

                // Add token balances (non-spam only)
                foreach ($onchainData['token_balances'] ?? [] as $token) {
                    if (!($token['is_spam'] ?? false)) {
                        $csvData[] = [
                            'type' => 'token',
                            'symbol' => $token['token_symbol'],
                            'name' => $token['token_name'],
                            'balance' => $token['balance'],
                            'usd_value' => $token['usd_value'] ?? 0,
                            'chain' => $token['chain'],
                            'is_spam' => 'false'
                        ];
                    }
                }

                $filename = "portfolio_{$walletAddress}_" . now()->format('Y-m-d_H-i-s') . '.csv';

                return response()->streamDownload(function () use ($csvData) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['type', 'symbol', 'name', 'balance', 'usd_value', 'chain', 'is_spam']);
                    foreach ($csvData as $row) {
                        fputcsv($file, $row);
                    }
                    fclose($file);
                }, $filename, [
                    'Content-Type' => 'text/csv',
                ]);
            }

            // JSON export
            $filename = "portfolio_{$walletAddress}_" . now()->format('Y-m-d_H-i-s') . '.json';

            return response()->json($exportData)
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");

        } catch (\Exception $e) {
            Log::error("Error exporting portfolio data for {$walletAddress}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengexport data portfolio',
                'error' => $this->formatApiError($e->getMessage(), 'export')
            ], 500);
        }
    }

    /**
     * Memproses tran saksi pembelian (existing - no change)
     */
    private function processBuyTransaction($userId, $projectId, $amount, $price)
    {
        // Cek apakah sudah ada portfolio
        $portfolio = Portfolio::where('user_id', $userId)
            ->where('project_id', $projectId)
            ->first();

        if ($portfolio) {
            // Update portfolio yang sudah ada
            $newTotalAmount  = $portfolio->amount + $amount;
            $newTotalCost    = $portfolio->amount * $portfolio->average_buy_price + $amount * $price;
            $newAveragePrice = $newTotalCost / $newTotalAmount;

            $portfolio->amount            = $newTotalAmount;
            $portfolio->average_buy_price = $newAveragePrice;
            $portfolio->save();
        } else {
            // Buat portfolio baru
            Portfolio::create([
                'user_id'           => $userId,
                'project_id'        => $projectId,
                'amount'            => $amount,
                'average_buy_price' => $price,
            ]);
        }
    }
}

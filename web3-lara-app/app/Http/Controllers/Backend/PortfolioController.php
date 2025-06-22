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

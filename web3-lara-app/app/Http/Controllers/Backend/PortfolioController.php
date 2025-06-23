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
     * ⚡ FIXED: AJAX endpoint untuk load analytics data dengan better chain handling dan cache differentiation
     */
    public function loadAnalyticsData(Request $request)
    {
        $user          = Auth::user();
        $walletAddress = $user->wallet_address;
        $selectedChain = $request->get('chain'); // Chain selection parameter

        try {
            // ⚡ FIXED: Enhanced cache key dengan differentiation yang lebih baik
            $chainIdentifier = $selectedChain ?: 'all_chains';
            $cacheKey = "onchain_analytics_fixed_v8_{$walletAddress}_{$chainIdentifier}_" . md5($selectedChain ?: 'all');

            $analytics = Cache::remember($cacheKey, 10, function () use ($walletAddress, $selectedChain) {
                try {
                    return $this->getOnchainAnalytics($walletAddress, $selectedChain);
                } catch (\Exception $e) {
                    Log::warning("Failed to fetch analytics for {$walletAddress}: " . $e->getMessage());
                    return $this->getEmptyAnalyticsData($selectedChain);
                }
            });

            // ⚡ FIXED: Enhanced cache key untuk transactions dengan proper differentiation
            $transactionsCacheKey = "onchain_transactions_fixed_v8_{$walletAddress}_{$chainIdentifier}_" . md5(($selectedChain ?: 'all') . '_trans');

            $recentTransactions = Cache::remember($transactionsCacheKey, 10, function () use ($walletAddress, $selectedChain) {
                try {
                    return $this->getOnchainTransactions($walletAddress, 100, $selectedChain);
                } catch (\Exception $e) {
                    Log::warning("Failed to fetch transactions for {$walletAddress}: " . $e->getMessage());
                    return [];
                }
            });

            // Available chains untuk dropdown
            $availableChains = $this->getAvailableChains();

            return response()->json([
                'success'           => true,
                'analytics'         => $analytics,
                'transactions'      => $recentTransactions,
                'available_chains'  => $availableChains,
                'selected_chain'    => $selectedChain,
                'cached'            => true,
                'optimization'      => 'multi_chain_fixed_v8',
                'debug_info'        => [
                    'analytics_total_txs' => $analytics['total_transactions'] ?? 0,
                    'transactions_count' => count($recentTransactions),
                    'chains_activity' => $analytics['chains_activity'] ?? [],
                    'most_traded_count' => count($analytics['most_traded_tokens'] ?? []),
                    'native_tokens_count' => count($analytics['native_token_summary'] ?? []),
                    'total_volume_usd' => $analytics['total_volume_usd'] ?? 0,
                    'selected_chain' => $selectedChain,
                    'cache_key_analytics' => $cacheKey,
                    'cache_key_transactions' => $transactionsCacheKey
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error loading analytics data for {$walletAddress}: " . $e->getMessage());

            return response()->json([
                'success'          => false,
                'message'          => 'Gagal memuat data analytics: ' . $e->getMessage(),
                'analytics'        => $this->getEmptyAnalyticsData($selectedChain),
                'transactions'     => [],
                'available_chains' => $this->getAvailableChains(),
                'selected_chain'   => $selectedChain,
                'error_details'    => $e->getMessage()
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

            // ⚡ ENHANCED: Clear dengan versioning yang lebih comprehensive
            $cacheKeys = [
                "onchain_portfolio_{$walletAddress}",
                "onchain_portfolio_v2_{$walletAddress}",
                "onchain_portfolio_v3_{$walletAddress}",
                "onchain_analytics_{$walletAddress}",
                "onchain_analytics_v2_{$walletAddress}",
                "onchain_analytics_v3_{$walletAddress}",
                "onchain_analytics_fixed_v7_{$walletAddress}",
                "onchain_analytics_fixed_v8_{$walletAddress}_all_chains",
                "onchain_transactions_{$walletAddress}",
                "onchain_transactions_v2_{$walletAddress}",
                "onchain_transactions_v3_{$walletAddress}",
                "onchain_transactions_fixed_v7_{$walletAddress}",
                "onchain_transactions_fixed_v8_{$walletAddress}_all_chains",
            ];

            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }

            $onchainPortfolio = $this->getOnchainPortfolio($walletAddress);
            $analytics        = $this->getOnchainAnalytics($walletAddress);

            // ⚡ IMMEDIATE CACHE: Store fresh data untuk subsequent requests
            Cache::put("onchain_portfolio_v3_{$walletAddress}", $onchainPortfolio, 30); // 30 minutes
            Cache::put("onchain_analytics_fixed_v8_{$walletAddress}_all_chains_" . md5('all'), $analytics, 30);        // 30 minutes

            return response()->json([
                'success'      => true,
                'portfolio'    => $onchainPortfolio,
                'analytics'    => $analytics,
                'message'      => 'Data onchain berhasil diperbarui dengan fokus native token',
                'cached_for'   => '30 minutes',
                'optimization' => 'native_token_focus_enabled_v8',
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
     * ⚡ FIXED: Enhanced analytics dengan better endpoint calls dan comprehensive logging
     */
    private function getOnchainAnalytics($walletAddress, $selectedChain = null)
    {
        $attempt   = 0;
        $lastError = null;

        while ($attempt < $this->maxRetries) {
            try {
                // ⚡ FIXED: Build request params dengan chain selection
                $params = [
                    'days' => 30,
                ];

                if ($selectedChain) {
                    $params['chain'] = $selectedChain;
                }

                Log::info("Fetching analytics for {$walletAddress} with params: " . json_encode($params));

                $response = Http::timeout($this->apiTimeout)->get("{$this->apiUrl}/blockchain/analytics/{$walletAddress}", $params);

                if ($response->successful()) {
                    $data = $response->json();

                    // ⚡ ENHANCED: Log response untuk debugging dengan lebih detail
                    Log::info("Analytics response for {$walletAddress}: " . json_encode([
                        'total_transactions' => $data['total_transactions'] ?? 0,
                        'unique_tokens_traded' => $data['unique_tokens_traded'] ?? 0,
                        'total_volume_usd' => $data['total_volume_usd'] ?? 0,
                        'chains_activity' => $data['chains_activity'] ?? [],
                        'most_traded_count' => count($data['most_traded_tokens'] ?? []),
                        'native_tokens_count' => count($data['native_token_summary'] ?? []),
                        'selected_chain' => $data['selected_chain'] ?? null,
                        'errors' => $data['errors_encountered'] ?? []
                    ]));

                    // ⚡ ENHANCED: Return data dengan validation
                    if (!isset($data['total_transactions'])) {
                        Log::warning("Analytics response missing required fields for {$walletAddress}");
                        return $this->getEmptyAnalyticsData($selectedChain);
                    }

                    return $data;
                } else {
                    $lastError = "API returned error {$response->status()}: " . $response->body();
                    Log::warning($lastError . " for analytics {$walletAddress} (attempt " . ($attempt + 1) . ")");
                }

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Log::error("Exception fetching analytics for {$walletAddress} (attempt " . ($attempt + 1) . "): " . $lastError);

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
     * ⚡ FIXED: Enhanced transactions dengan proper chain filtering dan unique cache keys
     */
    private function getOnchainTransactions($walletAddress, $limit = 100, $selectedChain = null)
    {
        $attempt   = 0;
        $lastError = null;

        while ($attempt < $this->maxRetries) {
            try {
                // ⚡ FIXED: Build request params dengan enhanced chain filtering
                $params = [
                    'limit'  => $limit,
                ];

                if ($selectedChain) {
                    // ⚡ FIXED: Send as array of single chain untuk filtering
                    $params['chains'] = [$selectedChain];
                } else {
                    // ⚡ All supported chains
                    $params['chains'] = ['eth', 'bsc', 'polygon', 'avalanche'];
                }

                Log::info("Fetching transactions for {$walletAddress} with params: " . json_encode($params));

                $response = Http::timeout($this->apiTimeout)->get("{$this->apiUrl}/blockchain/transactions/{$walletAddress}", $params);

                if ($response->successful()) {
                    $data = $response->json();

                    // ⚡ FIXED: Validate dan filter response
                    if (! is_array($data)) {
                        Log::warning("Invalid transactions response format for {$walletAddress}");
                        return [];
                    }

                    // ⚡ ENHANCED: Additional filtering untuk ensure correct chain dan format timestamps
                    $filteredData = [];
                    foreach ($data as $tx) {
                        // Skip transactions yang tidak valid
                        if (!isset($tx['tx_hash']) || empty($tx['tx_hash'])) {
                            continue;
                        }

                        // ⚡ FIXED: Chain filtering - more strict
                        if ($selectedChain && isset($tx['chain'])) {
                            // Normalize chain names untuk comparison
                            $txChain = strtolower($tx['chain']);
                            $filterChain = strtolower($selectedChain);

                            // Handle ethereum/eth variations
                            if ($filterChain === 'eth') $filterChain = 'ethereum';
                            if ($txChain === 'eth') $txChain = 'ethereum';

                            if ($txChain !== $filterChain) {
                                continue;
                            }
                        }

                        // ⚡ FIXED: Format timestamp properly
                        if (isset($tx['timestamp'])) {
                            try {
                                // Convert to proper format if needed
                                if (is_string($tx['timestamp'])) {
                                    $timestamp = new \DateTime($tx['timestamp']);
                                    $tx['timestamp'] = $timestamp->format('Y-m-d\TH:i:s.u\Z');
                                }
                            } catch (\Exception $e) {
                                Log::warning("Timestamp formatting error: " . $e->getMessage());
                                // Keep original timestamp if formatting fails
                            }
                        }

                        $filteredData[] = $tx;
                    }

                    $chainInfo = $selectedChain ? " (chain: {$selectedChain})" : " (all chains)";
                    Log::info("Successfully fetched " . count($filteredData) . " transactions for {$walletAddress}{$chainInfo} (filtered from " . count($data) . " total)");

                    return $filteredData;

                } else {
                    $lastError = "API returned error {$response->status()}: " . $response->body();
                    Log::warning($lastError . " for transactions {$walletAddress} (attempt " . ($attempt + 1) . ")");
                }

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Log::error("Exception fetching transactions for {$walletAddress} (attempt " . ($attempt + 1) . "): " . $lastError);

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
     * ⚡ NEW: Get available chains untuk dropdown selection
     */
    private function getAvailableChains()
    {
        return [
            [
                'value' => null,
                'label' => 'All Chains (Multi-Chain)',
                'icon'  => 'fas fa-globe',
                'color' => 'primary'
            ],
            [
                'value' => 'eth',
                'label' => 'Ethereum (ETH)',
                'icon'  => 'fab fa-ethereum',
                'color' => 'blue'
            ],
            [
                'value' => 'bsc',
                'label' => 'Binance Smart Chain (BNB)',
                'icon'  => 'fas fa-coins',
                'color' => 'yellow'
            ],
            [
                'value' => 'polygon',
                'label' => 'Polygon (MATIC)',
                'icon'  => 'fas fa-project-diagram',
                'color' => 'purple'
            ],
            [
                'value' => 'avalanche',
                'label' => 'Avalanche (AVAX)',
                'icon'  => 'fas fa-mountain',
                'color' => 'red'
            ]
        ];
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
     * ⚡ FIXED: Enhanced empty data dengan proper structure dan debug info
     */
    private function getEmptyAnalyticsData($selectedChain = null)
    {
        return [
            'wallet_address'        => '',
            'total_transactions'    => 0,
            'unique_tokens_traded'  => 0,
            'total_volume_usd'      => 0.0,
            'most_traded_tokens'    => [],
            'native_token_summary'  => [], // ⚡ NEW: Separate native tokens
            'transaction_frequency' => [],
            'chains_activity'       => [],
            'selected_chain'        => $selectedChain,
            'chain_specific_data'   => null,
            'cross_chain_volume'    => 0.0,
            'chain_dominance'       => [],
            'diversification_score' => 0.0,
            'chains_processed'      => [],
            'errors_encountered'    => ['No data available'],
            'error_info'            => 'API not available or returned empty data',
            'optimization'          => 'multi_chain_fixed_v8',
        ];
    }

    /**
     * ⚡ FIXED: Enhanced refresh dengan better cache clearing dan debug info
     */
    public function refreshAnalyticsData(Request $request)
    {
        $user          = Auth::user();
        $walletAddress = $user->wallet_address;
        $selectedChain = $request->get('chain');

        try {
            // ⚡ Check API status terlebih dahulu
            $apiStatus = $this->checkApiStatus();
            if (! $apiStatus['available']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blockchain API tidak tersedia saat ini: ' . $apiStatus['error'],
                ], 503);
            }

            // ⚡ FIXED: Enhanced cache clearing dengan v8 versioning
            $chainIdentifier = $selectedChain ?: 'all_chains';
            $cacheKeysToForget = [
                // Old versions
                "onchain_analytics_v4_{$walletAddress}",
                "onchain_transactions_v4_{$walletAddress}",
                "onchain_analytics_fixed_v5_{$walletAddress}",
                "onchain_transactions_fixed_v5_{$walletAddress}",
                "onchain_analytics_fixed_v7_{$walletAddress}",
                "onchain_transactions_fixed_v7_{$walletAddress}",
                // New versions v8 dengan berbagai chain combinations
                "onchain_analytics_fixed_v8_{$walletAddress}_{$chainIdentifier}_" . md5($selectedChain ?: 'all'),
                "onchain_transactions_fixed_v8_{$walletAddress}_{$chainIdentifier}_" . md5(($selectedChain ?: 'all') . '_trans'),
                "onchain_analytics_fixed_v8_{$walletAddress}_all_chains_" . md5('all'),
                "onchain_transactions_fixed_v8_{$walletAddress}_all_chains_" . md5('all_trans'),
            ];

            // Add chain-specific cache keys if selected
            if ($selectedChain) {
                $cacheKeysToForget[] = "onchain_analytics_fixed_v8_{$walletAddress}_{$selectedChain}_" . md5($selectedChain);
                $cacheKeysToForget[] = "onchain_transactions_fixed_v8_{$walletAddress}_{$selectedChain}_" . md5($selectedChain . '_trans');
            }

            foreach ($cacheKeysToForget as $key) {
                Cache::forget($key);
            }

            Log::info("Cleared cache keys for {$walletAddress}: " . implode(', ', $cacheKeysToForget));

            // Fetch fresh data
            $analytics = $this->getOnchainAnalytics($walletAddress, $selectedChain);
            $transactions = $this->getOnchainTransactions($walletAddress, 100, $selectedChain);

            // ⚡ Store fresh data in cache dengan proper keys
            $analyticsCacheKey = "onchain_analytics_fixed_v8_{$walletAddress}_{$chainIdentifier}_" . md5($selectedChain ?: 'all');
            $transactionsCacheKey = "onchain_transactions_fixed_v8_{$walletAddress}_{$chainIdentifier}_" . md5(($selectedChain ?: 'all') . '_trans');

            Cache::put($analyticsCacheKey, $analytics, 10);
            Cache::put($transactionsCacheKey, $transactions, 10);

            $chainInfo = $selectedChain ? " untuk chain {$selectedChain}" : " untuk semua chains";

            return response()->json([
                'success'           => true,
                'analytics'         => $analytics,
                'transactions'      => $transactions,
                'available_chains'  => $this->getAvailableChains(),
                'selected_chain'    => $selectedChain,
                'message'           => "Data analytics{$chainInfo} berhasil diperbarui",
                'cached_for'        => '10 minutes',
                'optimization'      => 'multi_chain_fixed_v8',
                'debug_info'        => [
                    'analytics_total_txs' => $analytics['total_transactions'] ?? 0,
                    'transactions_count' => count($transactions),
                    'chains_activity' => $analytics['chains_activity'] ?? [],
                    'most_traded_count' => count($analytics['most_traded_tokens'] ?? []),
                    'native_tokens_count' => count($analytics['native_token_summary'] ?? []),
                    'total_volume_usd' => $analytics['total_volume_usd'] ?? 0,
                    'cache_keys_cleared' => count($cacheKeysToForget),
                    'selected_chain' => $selectedChain,
                    'cache_key_analytics' => $analyticsCacheKey,
                    'cache_key_transactions' => $transactionsCacheKey
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error refreshing analytics data for {$walletAddress}: " . $e->getMessage());

            return response()->json([
                'success'       => false,
                'message'       => 'Gagal memperbarui data analytics: ' . $e->getMessage(),
                'error_details' => $e->getMessage(),
                'cache_cleared' => true,
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

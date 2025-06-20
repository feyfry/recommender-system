<?php
namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Transaction extends Model
{
    use HasFactory;

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'project_id',
        'transaction_type',
        'source',
        'blockchain_chain',
        'from_address',
        'to_address',
        'token_address',
        'token_symbol',
        'block_number',
        'gas_used',
        'gas_price',
        'blockchain_timestamp',
        'amount',
        'price',
        'total_value',
        'transaction_hash',
        'is_verified',
        'raw_data',
        'last_sync_at',
        'notes',
        'exchange_platform',
        'followed_recommendation',
        'recommendation_id',
    ];

    /**
     * Atribut yang harus dikonversi ke tipe data native.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount'                  => 'float',
        'price'                   => 'float',
        'total_value'             => 'float',
        'gas_used'                => 'integer',
        'gas_price'               => 'float',
        'block_number'            => 'integer',
        'blockchain_timestamp'    => 'datetime',
        'last_sync_at'            => 'datetime',
        'followed_recommendation' => 'boolean',
        'is_verified'             => 'boolean',
        'raw_data'                => 'array',
    ];

    /**
     * Tipe-tipe transaksi valid.
     *
     * @var array<string>
     */
    public static $validTypes = [
        'buy',
        'sell',
        'transfer', // For API-synced transactions that are neither buy nor sell
    ];

    /**
     * Source types untuk transaksi
     *
     * @var array<string>
     */
    public static $validSources = [
        'manual',
        'api_sync',
    ];

    /**
     * Supported blockchain chains
     *
     * @var array<string>
     */
    public static $supportedChains = [
        'eth'       => 'Ethereum',
        'bsc'       => 'Binance Smart Chain',
        'polygon'   => 'Polygon',
        'avalanche' => 'Avalanche',
        'fantom'    => 'Fantom',
        'arbitrum'  => 'Arbitrum',
        'optimism'  => 'Optimism',
    ];

    /**
     * Mendapatkan relasi ke User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Mendapatkan relasi ke Project.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }

    /**
     * Mendapatkan relasi ke Recommendation.
     */
    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(Recommendation::class);
    }

    /**
     * Scope untuk filter transaksi berdasarkan pengguna.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope untuk filter transaksi berdasarkan tipe.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('transaction_type', $type);
    }

    /**
     * Scope untuk filter transaksi berdasarkan source.
     */
    public function scopeOfSource($query, $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Scope untuk filter transaksi berdasarkan blockchain.
     */
    public function scopeOnChain($query, $chain)
    {
        return $query->where('blockchain_chain', $chain);
    }

    /**
     * Scope untuk transaksi terbaru.
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope untuk transaksi yang mengikuti rekomendasi.
     */
    public function scopeFollowedRecommendation($query)
    {
        return $query->where('followed_recommendation', true);
    }

    /**
     * Scope untuk transaksi manual.
     */
    public function scopeManual($query)
    {
        return $query->where('source', 'manual');
    }

    /**
     * Scope untuk transaksi dari API sync.
     */
    public function scopeApiSynced($query)
    {
        return $query->where('source', 'api_sync');
    }

    /**
     * Scope untuk transaksi yang terverifikasi.
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Accessor untuk formatted blockchain chain name.
     */
    public function getFormattedChainAttribute(): string
    {
        if (! $this->blockchain_chain) {
            return 'Manual';
        }

        return self::$supportedChains[$this->blockchain_chain] ?? ucfirst($this->blockchain_chain);
    }

    /**
     * Accessor untuk formatted source.
     */
    public function getFormattedSourceAttribute(): string
    {
        return match ($this->source) {
            'manual' => 'Manual Entry',
            'api_sync' => 'Auto-Synced',
            default => ucfirst($this->source)
        };
    }

    /**
     * Accessor untuk blockchain explorer URL.
     */
    public function getExplorerUrlAttribute(): ?string
    {
        if (! $this->transaction_hash || ! $this->blockchain_chain) {
            return null;
        }

        $explorers = [
            'eth'       => 'https://etherscan.io/tx/',
            'bsc'       => 'https://bscscan.com/tx/',
            'polygon'   => 'https://polygonscan.com/tx/',
            'avalanche' => 'https://snowtrace.io/tx/',
            'fantom'    => 'https://ftmscan.com/tx/',
            'arbitrum'  => 'https://arbiscan.io/tx/',
            'optimism'  => 'https://optimistic.etherscan.io/tx/',
        ];

        $baseUrl = $explorers[$this->blockchain_chain] ?? null;

        return $baseUrl ? $baseUrl . $this->transaction_hash : null;
    }

    /**
     * Check if transaction is from blockchain API.
     */
    public function isApiSynced(): bool
    {
        return $this->source === 'api_sync';
    }

    /**
     * Check if transaction is manual entry.
     */
    public function isManual(): bool
    {
        return $this->source === 'manual';
    }

    /**
     * Get gas cost in USD (approximate).
     */
    public function getGasCostUsdAttribute(): ?float
    {
        if (! $this->gas_used || ! $this->gas_price || ! $this->blockchain_chain) {
            return null;
        }

                             // Approximate ETH price (should be fetched from real-time API in production)
        $ethPriceUsd = 2500; // This should come from price API

                                                                  // Calculate gas cost in ETH
        $gasCostEth = ($this->gas_used * $this->gas_price) / 1e9; // Convert Gwei to ETH

        return $gasCostEth * $ethPriceUsd;
    }

    /**
     * Sync user transactions from blockchain APIs.
     */
    public static function syncUserTransactions($userId, $walletAddresses = [], $chains = ['eth', 'bsc', 'polygon'])
    {
        $apiUrl = env('RECOMMENDATION_API_URL', 'http://localhost:8001');

        try {
            $syncData = [
                'user_id'          => $userId,
                'wallet_addresses' => $walletAddresses,
                'chains'           => $chains,
                'limit'            => 100,
            ];

            Log::info("Starting transaction sync for user {$userId}", $syncData);

            $response = Http::timeout(30)->post("{$apiUrl}/transactions/sync", $syncData);

            if ($response->successful()) {
                $data         = $response->json();
                $transactions = $data['transactions'] ?? [];

                $syncedCount = 0;
                $errors      = [];

                foreach ($transactions as $txData) {
                    try {
                        // Check for existing transaction to avoid duplicates
                        $existing = self::where('user_id', $userId)
                            ->where('transaction_hash', $txData['tx_hash'])
                            ->where('blockchain_chain', $txData['chain'])
                            ->first();

                        if ($existing) {
                            continue; // Skip duplicate
                        }

                        // Try to match with project
                        $projectId = self::findProjectByTokenSymbol($txData['token_symbol']);

                        $transaction = self::create([
                            'user_id'              => $userId,
                            'project_id'           => $projectId,
                            'transaction_type'     => $txData['transaction_type'],
                            'source'               => $txData['source'] ?? 'api_sync',
                            'blockchain_chain'     => $txData['chain'],
                            'from_address'         => $txData['from_address'],
                            'to_address'           => $txData['to_address'],
                            'token_address'        => $txData['token_address'],
                            'token_symbol'         => $txData['token_symbol'],
                            'block_number'         => $txData['block_number'],
                            'gas_used'             => $txData['gas_used'],
                            'gas_price'            => $txData['gas_price'],
                            'blockchain_timestamp' => Carbon::parse($txData['timestamp']),
                            'amount'               => $txData['value'],
                            'price'                => 0, // Will be updated with historical price
                            'total_value'          => 0, // Will be calculated
                            'transaction_hash'     => $txData['tx_hash'],
                            'is_verified'          => $txData['is_verified'] ?? true,
                            'raw_data'             => $txData['raw_data'] ?? $txData,
                            'last_sync_at'         => now(),
                        ]);

                        // Update price and total value with historical data
                        self::updateHistoricalPrice($transaction);

                        $syncedCount++;

                    } catch (\Exception $e) {
                        $errors[] = "Error syncing transaction {$txData['tx_hash']}: " . $e->getMessage();
                        Log::error("Error syncing transaction", [
                            'tx_hash' => $txData['tx_hash'],
                            'error'   => $e->getMessage(),
                        ]);
                    }
                }

                Log::info("Transaction sync completed", [
                    'user_id'      => $userId,
                    'synced_count' => $syncedCount,
                    'error_count'  => count($errors),
                ]);

                return [
                    'success'      => true,
                    'synced_count' => $syncedCount,
                    'total_found'  => count($transactions),
                    'errors'       => $errors,
                ];

            } else {
                Log::error("Transaction sync API failed", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error'   => 'API request failed: ' . $response->body(),
                ];
            }

        } catch (\Exception $e) {
            Log::error("Transaction sync error", [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Find project by token symbol.
     */
    private static function findProjectByTokenSymbol($tokenSymbol)
    {
        if (! $tokenSymbol) {
            return null;
        }

        $project = Project::where('symbol', strtoupper($tokenSymbol))->first();

        return $project ? $project->id : null;
    }

    /**
     * Update historical price for transaction.
     */
    private static function updateHistoricalPrice($transaction)
    {
        try {
            if (! $transaction->project_id || ! $transaction->blockchain_timestamp) {
                return;
            }

            // For now, use current price as approximation
            // In production, you should fetch historical price from price API
            $project = $transaction->project;
            if ($project && $project->current_price) {
                $transaction->update([
                    'price'       => $project->current_price,
                    'total_value' => $transaction->amount * $project->current_price,
                ]);
            }

        } catch (\Exception $e) {
            Log::warning("Error updating historical price for transaction {$transaction->id}: " . $e->getMessage());
        }
    }

    /**
     * Mendapatkan statistik transaksi per hari.
     */
    public static function getDailyStats($userId = null, $days = 30)
    {
        $query = self::selectRaw('DATE(created_at) as date, transaction_type, source, COUNT(*) as count, SUM(total_value) as value')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date', 'transaction_type', 'source')
            ->orderBy('date');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Mendapatkan statistik per blockchain.
     */
    public static function getChainStats($userId = null, $days = 30)
    {
        $query = self::selectRaw('blockchain_chain, COUNT(*) as count, SUM(total_value) as total_value')
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('blockchain_chain')
            ->groupBy('blockchain_chain')
            ->orderBy('count', 'desc');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Mendapatkan volume transaksi total dengan breakdown source.
     */
    public static function getTotalVolume($userId = null, $days = 30)
    {
        $query = self::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('transaction_type, source, SUM(total_value) as total_value, COUNT(*) as count');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->groupBy('transaction_type', 'source')->get();
    }

    /**
     * Mendapatkan proyek dengan transaksi terbanyak (termasuk dari API sync).
     */
    public static function getMostTradedProjects($userId = null, $limit = 10)
    {
        $query = self::with('project')
            ->when($userId, function ($q) use ($userId) {
                return $q->where('user_id', $userId);
            });

        return $query->get()
            ->groupBy('project_id')
            ->map(function ($transactions) {
                $project = $transactions->first()->project;
                return (object) [
                    'id' => $project->id,
                    'name' => $project->name,
                    'symbol' => $project->symbol,
                    'image' => $project->image,
                    'transaction_count' => $transactions->count(),
                    'total_value' => $transactions->sum('total_value'),
                    'manual_count' => $transactions->where('source', 'manual')->count(),
                    'api_count' => $transactions->where('source', 'api_sync')->count(),
                ];
            })
            ->sortByDesc('transaction_count')
            ->take($limit)
            ->values();
    }

    /**
     * Hitung pengaruh rekomendasi terhadap transaksi dengan breakdown source.
     */
    public static function getRecommendationInfluence($userId = null, $days = 30)
    {
        $query = self::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('followed_recommendation, source, COUNT(*) as count,
                        SUM(total_value) as total_value,
                        AVG(CASE WHEN transaction_type = ? THEN price ELSE NULL END) as avg_buy_price,
                        AVG(CASE WHEN transaction_type = ? THEN price ELSE NULL END) as avg_sell_price',
                        ['buy', 'sell']);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->groupBy('followed_recommendation', 'source')->get();
    }

    /**
     * Get sync status for user.
     */
    public static function getSyncStatus($userId)
    {
        $lastSync = self::where('user_id', $userId)
            ->where('source', 'api_sync')
            ->max('last_sync_at');

        $apiSyncedCount = self::where('user_id', $userId)
            ->where('source', 'api_sync')
            ->count();

        $manualCount = self::where('user_id', $userId)
            ->where('source', 'manual')
            ->count();

        return [
            'last_sync'        => $lastSync ? Carbon::parse($lastSync) : null,
            'api_synced_count' => $apiSyncedCount,
            'manual_count'     => $manualCount,
            'total_count'      => $apiSyncedCount + $manualCount,
            'needs_sync'       => ! $lastSync || Carbon::parse($lastSync)->diffInHours(now()) > 24,
        ];
    }
}

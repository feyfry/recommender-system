<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    /**
     * URL API untuk rekomendasi dan transaction sync
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
     * Menampilkan halaman transaksi dengan support auto-sync
     */
    public function index()
    {
        $user = Auth::user();

        // Ambil data transaksi dengan pagination dan filter
        $transactions = Transaction::forUser($user->user_id)
            ->with(['project' => function ($query) {
                $query->select('id', 'name', 'symbol', 'image', 'current_price');
            }])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Hitung statistik volume transaksi dengan breakdown source
        $volumeStats = Transaction::getTotalVolume($user->user_id);

        // Ambil proyek dengan transaksi terbanyak
        $mostTradedProjects = Transaction::getMostTradedProjects($user->user_id, 5);

        // Statistik pengaruh rekomendasi terhadap transaksi
        $recommendationInfluence = Transaction::getRecommendationInfluence($user->user_id);

        // Get sync status
        $syncStatus = Transaction::getSyncStatus($user->user_id);

        // Get chain statistics
        $chainStats = Transaction::getChainStats($user->user_id);

        return view('backend.portfolio.transactions', [
            'transactions'            => $transactions,
            'volumeStats'             => $volumeStats,
            'mostTradedProjects'      => $mostTradedProjects,
            'recommendationInfluence' => $recommendationInfluence,
            'syncStatus'              => $syncStatus,
            'chainStats'              => $chainStats,
            'supportedChains'         => Transaction::$supportedChains,
        ]);
    }

    /**
     * Menambahkan transaksi manual
     */
    public function addTransaction(Request $request)
    {
        $user = Auth::user();

        // Validasi input dengan field tambahan
        $validator = Validator::make($request->all(), [
            'project_id'              => 'required|exists:projects,id',
            'transaction_type'        => 'required|in:buy,sell',
            'amount'                  => 'required|numeric|min:0',
            'price'                   => 'required|numeric|min:0',
            'transaction_hash'        => 'nullable|string|max:255',
            'notes'                   => 'nullable|string|max:1000',
            'exchange_platform'       => 'nullable|string|max:100',
            'followed_recommendation' => 'boolean',
            'recommendation_id'       => 'nullable|exists:recommendations,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            // Ambil data proyek
            $project = Project::find($request->project_id);

            // Hitung total nilai transaksi
            $totalValue = $request->amount * $request->price;

            // Simpan transaksi manual
            $transaction = Transaction::create([
                'user_id'                 => $user->user_id,
                'project_id'              => $request->project_id,
                'transaction_type'        => $request->transaction_type,
                'source'                  => 'manual',
                'amount'                  => $request->amount,
                'price'                   => $request->price,
                'total_value'             => $totalValue,
                'transaction_hash'        => $request->transaction_hash,
                'notes'                   => $request->notes,
                'exchange_platform'       => $request->exchange_platform,
                'followed_recommendation' => $request->has('followed_recommendation'),
                'recommendation_id'       => $request->recommendation_id,
                'is_verified'             => true, // Manual transactions are always verified
                'last_sync_at'            => null, // Manual transactions don't need sync
                'raw_data'                => [
                    'source'     => 'manual_entry',
                    'entered_at' => now()->toISOString(),
                    'user_input' => [
                        'project_id'        => $request->project_id,
                        'transaction_type'  => $request->transaction_type,
                        'amount'            => $request->amount,
                        'price'             => $request->price,
                        'notes'             => $request->notes,
                        'exchange_platform' => $request->exchange_platform,
                    ],
                ],
            ]);

            // Update atau buat portfolio
            if ($request->transaction_type === 'buy') {
                $this->processBuyTransaction($user->user_id, $request->project_id, $request->amount, $request->price);
            } else {
                $this->processSellTransaction($user->user_id, $request->project_id, $request->amount, $request->price);
            }

            // Clear cache
            $this->clearTransactionCaches($user->user_id);

            return redirect()->route('panel.portfolio.transactions')
                ->with('success', 'Transaksi manual berhasil ditambahkan.');

        } catch (\Exception $e) {
            Log::error("Error adding manual transaction: " . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Gagal menambahkan transaksi: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Sync transaksi dari blockchain APIs
     */
    public function syncTransactions(Request $request)
    {
        $user = Auth::user();

        // Validasi input
        $validator = Validator::make($request->all(), [
            'wallet_addresses'   => 'required|array|min:1',
            'wallet_addresses.*' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'chains'             => 'required|array|min:1',
            'chains.*'           => 'required|string|in:eth,bsc,polygon,avalanche,fantom,arbitrum,optimism',
            'limit'              => 'nullable|integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            Log::info("Starting transaction sync for user {$user->user_id}", [
                'wallet_addresses' => $request->wallet_addresses,
                'chains'           => $request->chains,
            ]);

            // Call the recommendation engine API
            $syncResult = Transaction::syncUserTransactions(
                $user->user_id,
                $request->wallet_addresses,
                $request->chains ?? ['eth', 'bsc', 'polygon']
            );

            if ($syncResult['success']) {
                // Clear caches
                $this->clearTransactionCaches($user->user_id);

                return response()->json([
                    'success' => true,
                    'message' => "Berhasil sync {$syncResult['synced_count']} transaksi dari {$syncResult['total_found']} transaksi yang ditemukan.",
                    'data'    => [
                        'synced_count' => $syncResult['synced_count'],
                        'total_found'  => $syncResult['total_found'],
                        'errors'       => $syncResult['errors'] ?? [],
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal melakukan sinkronisasi: ' . $syncResult['error'],
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error("Error in transaction sync: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat sinkronisasi: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test wallet address untuk cek apakah bisa sync
     */
    public function testWallet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wallet_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $response = Http::timeout(15)->get("{$this->apiUrl}/transactions/test/wallet/{$request->wallet_address}");

            if ($response->successful()) {
                $data = $response->json();

                return response()->json([
                    'success' => true,
                    'message' => "Ditemukan {$data['transactions_found']} transaksi untuk wallet ini.",
                    'data'    => $data,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal melakukan test wallet: ' . $response->body(),
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error("Error testing wallet: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat test wallet: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get transaction sync status
     */
    public function getSyncStatus()
    {
        $user = Auth::user();

        try {
            $syncStatus = Transaction::getSyncStatus($user->user_id);

            return response()->json([
                'success' => true,
                'data'    => $syncStatus,
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting sync status: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mendapatkan status sync: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get real-time prices for portfolio calculation
     */
    public function updatePrices(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'symbols'   => 'required|array|min:1',
            'symbols.*' => 'required|string',
            'source'    => 'nullable|string|in:binance,coingecko,multiple',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $response = Http::timeout(10)->post("{$this->apiUrl}/transactions/prices/update", [
                'symbols' => $request->symbols,
                'source'  => $request->source ?? 'multiple',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return response()->json([
                    'success' => true,
                    'data'    => $data,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mendapatkan harga: ' . $response->body(),
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error("Error updating prices: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat update harga: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export transactions to CSV
     */
    public function exportTransactions(Request $request)
    {
        $user = Auth::user();

        try {
            $query = Transaction::forUser($user->user_id)
                ->with('project:id,name,symbol');

            // Apply filters
            if ($request->has('source') && $request->source !== 'all') {
                $query->where('source', $request->source);
            }

            if ($request->has('chain') && $request->chain !== 'all') {
                $query->where('blockchain_chain', $request->chain);
            }

            if ($request->has('type') && $request->type !== 'all') {
                $query->where('transaction_type', $request->type);
            }

            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            $transactions = $query->orderBy('created_at', 'desc')->get();

            // Generate CSV
            $filename = "transactions_export_" . now()->format('Y_m_d_H_i_s') . ".csv";
            $headers  = [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function () use ($transactions) {
                $file = fopen('php://output', 'w');

                // Add BOM for Excel compatibility
                fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

                // Header
                fputcsv($file, [
                    'Date',
                    'Type',
                    'Source',
                    'Project',
                    'Symbol',
                    'Amount',
                    'Price',
                    'Total Value',
                    'Chain',
                    'Transaction Hash',
                    'Gas Used',
                    'Gas Price (Gwei)',
                    'Notes',
                ]);

                // Data
                foreach ($transactions as $transaction) {
                    fputcsv($file, [
                        $transaction->created_at->format('Y-m-d H:i:s'),
                        ucfirst($transaction->transaction_type),
                        $transaction->formatted_source,
                        $transaction->project->name ?? 'Unknown',
                        $transaction->project->symbol ?? $transaction->token_symbol ?? 'N/A',
                        $transaction->amount,
                        $transaction->price,
                        $transaction->total_value,
                        $transaction->formatted_chain,
                        $transaction->transaction_hash,
                        $transaction->gas_used,
                        $transaction->gas_price,
                        $transaction->notes,
                    ]);
                }

                fclose($file);
            };

            return response()->streamDownload($callback, $filename, $headers);

        } catch (\Exception $e) {
            Log::error("Error exporting transactions: " . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Gagal mengexport transaksi: ' . $e->getMessage());
        }
    }

    /**
     * Delete transaction (only manual transactions)
     */
    public function deleteTransaction($id)
    {
        $user = Auth::user();

        try {
            $transaction = Transaction::where('id', $id)
                ->where('user_id', $user->user_id)
                ->where('source', 'manual') // Only allow deletion of manual transactions
                ->first();

            if (! $transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaksi tidak ditemukan atau tidak dapat dihapus.',
                ], 404);
            }

            // Reverse portfolio changes if needed
            if ($transaction->project_id) {
                if ($transaction->transaction_type === 'buy') {
                    $this->processSellTransaction(
                        $user->user_id,
                        $transaction->project_id,
                        $transaction->amount,
                        $transaction->price
                    );
                } else {
                    $this->processBuyTransaction(
                        $user->user_id,
                        $transaction->project_id,
                        $transaction->amount,
                        $transaction->price
                    );
                }
            }

            $transaction->delete();

            // Clear cache
            $this->clearTransactionCaches($user->user_id);

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil dihapus.',
            ]);

        } catch (\Exception $e) {
            Log::error("Error deleting transaction: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus transaksi: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get transaction details
     */
    public function getTransactionDetails($id)
    {
        $user = Auth::user();

        try {
            $transaction = Transaction::where('id', $id)
                ->where('user_id', $user->user_id)
                ->with('project:id,name,symbol,image,current_price')
                ->first();

            if (! $transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaksi tidak ditemukan.',
                ], 404);
            }

            $details = [
                'id'                   => $transaction->id,
                'transaction_hash'     => $transaction->transaction_hash,
                'type'                 => $transaction->transaction_type,
                'source'               => $transaction->formatted_source,
                'amount'               => $transaction->amount,
                'price'                => $transaction->price,
                'total_value'          => $transaction->total_value,
                'created_at'           => $transaction->created_at->format('Y-m-d H:i:s'),
                'blockchain_timestamp' => $transaction->blockchain_timestamp?->format('Y-m-d H:i:s'),
                'chain'                => $transaction->formatted_chain,
                'gas_used'             => $transaction->gas_used,
                'gas_price'            => $transaction->gas_price,
                'gas_cost_usd'         => $transaction->gas_cost_usd,
                'from_address'         => $transaction->from_address,
                'to_address'           => $transaction->to_address,
                'token_address'        => $transaction->token_address,
                'token_symbol'         => $transaction->token_symbol,
                'block_number'         => $transaction->block_number,
                'explorer_url'         => $transaction->explorer_url,
                'notes'                => $transaction->notes,
                'exchange_platform'    => $transaction->exchange_platform,
                'is_verified'          => $transaction->is_verified,
                'project'              => $transaction->project,
                'can_delete'           => $transaction->isManual(), // Only manual transactions can be deleted
            ];

            return response()->json([
                'success' => true,
                'data'    => $details,
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting transaction details: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mendapatkan detail transaksi: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Memproses transaksi pembelian
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

    /**
     * Memproses transaksi penjualan
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
     * Clear transaction-related caches
     */
    private function clearTransactionCaches($userId)
    {
        $cacheKeys = [
            "dashboard_portfolio_{$userId}",
            "portfolio_summary_{$userId}",
            "transaction_stats_{$userId}",
            "most_traded_projects_{$userId}",
            "portfolio_distribution_{$userId}",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }
}

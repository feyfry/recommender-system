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
use Illuminate\Support\Facades\Validator;

class PortfolioController extends Controller
{
    /**
     * Menampilkan halaman utama portfolio
     */
    public function index()
    {
        $user = Auth::user();

        // Ambil data portfolio pengguna
        $portfolios = Portfolio::forUser($user->user_id)
            ->with('project')
            ->get();

        // Hitung nilai total portfolio
        $totalValue = 0;
        $totalCost  = 0;

        foreach ($portfolios as $portfolio) {
            $totalValue += $portfolio->current_value;
            $totalCost += $portfolio->initial_value;
        }

        // Hitung distribusi kategori
        $categoryDistribution = Portfolio::getCategoryDistribution($user->user_id);

        // Hitung distribusi blockchain
        $chainDistribution = Portfolio::getChainDistribution($user->user_id);

        return view('backend.portfolio.index', [
            'portfolios'           => $portfolios,
            'totalValue'           => $totalValue,
            'totalCost'            => $totalCost,
            'profitLoss'           => $totalValue - $totalCost,
            'profitLossPercentage' => $totalCost > 0 ? (($totalValue - $totalCost) / $totalCost) * 100 : 0,
            'categoryDistribution' => $categoryDistribution,
            'chainDistribution'    => $chainDistribution,
        ]);
    }

    /**
     * Menampilkan halaman transaksi - Redirect ke TransactionController
     */
    public function transactions()
    {
        return redirect()->route('panel.portfolio.transactions');
    }

    /**
     * Menampilkan halaman price alerts
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
        ]);
    }

    /**
     * Menambahkan transaksi baru
     */
    // public function addTransaction(Request $request)
    // {
    //     $user = Auth::user();

    //     // Validasi input
    //     $validator = Validator::make($request->all(), [
    //         'project_id'       => 'required|exists:projects,id',
    //         'transaction_type' => 'required|in:buy,sell',
    //         'amount'           => 'required|numeric|min:0',
    //         'price'            => 'required|numeric|min:0',
    //     ]);

    //     if ($validator->fails()) {
    //         return redirect()->back()
    //             ->withErrors($validator)
    //             ->withInput();
    //     }

    //     // Ambil data proyek
    //     $project = Project::find($request->project_id);

    //     // Hitung total nilai transaksi
    //     $totalValue = $request->amount * $request->price;

    //     // Simpan transaksi
    //     $transaction = Transaction::create([
    //         'user_id'                 => $user->user_id,
    //         'project_id'              => $request->project_id,
    //         'transaction_type'        => $request->transaction_type,
    //         'amount'                  => $request->amount,
    //         'price'                   => $request->price,
    //         'total_value'             => $totalValue,
    //         'transaction_hash'        => $request->transaction_hash,
    //         'followed_recommendation' => $request->has('followed_recommendation'),
    //         'recommendation_id'       => $request->recommendation_id,
    //     ]);

    //     // Update atau buat portfolio
    //     if ($request->transaction_type === 'buy') {
    //         $this->processBuyTransaction($user->user_id, $request->project_id, $request->amount, $request->price);
    //     } else {
    //         $this->processSellTransaction($user->user_id, $request->project_id, $request->amount, $request->price);
    //     }

    //     return redirect()->route('panel.portfolio.transactions')
    //         ->with('success', 'Transaksi berhasil ditambahkan.');
    // }

    /**
     * Menambahkan price alert baru
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
     * Menghapus price alert
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
}

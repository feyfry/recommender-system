<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'amount',
        'price',
        'total_value',
        'transaction_hash',
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
        'followed_recommendation' => 'boolean',
    ];

    /**
     * Tipe-tipe transaksi valid.
     *
     * @var array<string>
     */
    public static $validTypes = [
        'buy',
        'sell',
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
     * Mendapatkan statistik transaksi per hari.
     */
    public static function getDailyStats($userId = null, $days = 30)
    {
        $query = self::selectRaw('DATE(created_at) as date, transaction_type, COUNT(*) as count, SUM(total_value) as value')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date', 'transaction_type')
            ->orderBy('date');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Mendapatkan volume transaksi total.
     */
    public static function getTotalVolume($userId = null, $days = 30)
    {
        $query = self::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('transaction_type, SUM(total_value) as total_value');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->groupBy('transaction_type')->get();
    }

    /**
     * Mendapatkan proyek dengan transaksi terbanyak.
     */
    public static function getMostTradedProjects($userId = null, $limit = 10)
    {
        $query = self::join('projects', 'transactions.project_id', '=', 'projects.id')
            ->selectRaw('projects.id, projects.name, projects.symbol, projects.image,
                        COUNT(*) as transaction_count,
                        SUM(transactions.total_value) as total_value')
            ->groupBy('projects.id', 'projects.name', 'projects.symbol', 'projects.image')
            ->orderBy('transaction_count', 'desc')
            ->limit($limit);

        if ($userId) {
            $query->where('transactions.user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Hitung pengaruh rekomendasi terhadap transaksi.
     */
    public static function getRecommendationInfluence($userId = null, $days = 30)
    {
        $query = self::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('followed_recommendation, COUNT(*) as count,
                        SUM(total_value) as total_value,
                        AVG(CASE
                            WHEN transaction_type = \'buy\' THEN price
                            ELSE NULL
                        END) as avg_buy_price,
                        AVG(CASE
                            WHEN transaction_type = \'sell\' THEN price
                            ELSE NULL
                        END) as avg_sell_price');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->groupBy('followed_recommendation')->get();
    }
}

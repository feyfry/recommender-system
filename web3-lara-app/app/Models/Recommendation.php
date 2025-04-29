<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recommendation extends Model
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
        'score',
        'rank',
        'recommendation_type',
        'category_filter',
        'chain_filter',
        'action_type',
        'confidence_score',
        'target_price',
        'explanation',
        'expires_at',
    ];

    /**
     * Atribut yang harus dikonversi ke tipe data native.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'score'            => 'float',
        'rank'             => 'integer',
        'confidence_score' => 'float',
        'target_price'     => 'float',
        'expires_at'       => 'datetime',
    ];

    /**
     * Tipe-tipe rekomendasi yang valid.
     *
     * @var array<string>
     */
    public static $validTypes = [
        'item-based',
        'user-based',
        'feature-enhanced',
        'hybrid',
        'ncf',
        'popular',
        'trending',
    ];

    /**
     * Tipe-tipe aksi yang valid.
     *
     * @var array<string>
     */
    public static $validActions = [
        'buy',
        'sell',
        'hold',
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
     * Scope untuk rekomendasi yang belum kadaluarsa.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope untuk rekomendasi dengan tipe tertentu.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('recommendation_type', $type);
    }

    /**
     * Scope untuk rekomendasi teratas.
     */
    public function scopeTopRanked($query, $limit = 10)
    {
        return $query->orderBy('rank')->limit($limit);
    }

    /**
     * Scope untuk rekomendasi berdasarkan aksi.
     */
    public function scopeWithAction($query, $action)
    {
        return $query->where('action_type', $action);
    }

    /**
     * Scope untuk rekomendasi dalam kategori tertentu.
     */
    public function scopeInCategory($query, $category)
    {
        return $query->where('category_filter', $category);
    }

    /**
     * Scope untuk rekomendasi dalam blockchain tertentu.
     */
    public function scopeOnChain($query, $chain)
    {
        return $query->where('chain_filter', $chain);
    }

    /**
     * Mengembalikan rekomendasi top trending untuk semua pengguna.
     */
    public static function getTopTrending($limit = 10)
    {
        return self::join('projects', 'recommendations.project_id', '=', 'projects.id')
            ->select('recommendations.*', 'projects.name', 'projects.symbol', 'projects.image')
            ->where('recommendation_type', 'trending')
            ->orderBy('score', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Mengembalikan rekomendasi top popular untuk semua pengguna.
     */
    public static function getTopPopular($limit = 10)
    {
        return self::join('projects', 'recommendations.project_id', '=', 'projects.id')
            ->select('recommendations.*', 'projects.name', 'projects.symbol', 'projects.image')
            ->where('recommendation_type', 'popular')
            ->orderBy('score', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Mengembalikan statistik akurasi rekomendasi (apakah harga target tercapai).
     */
    public static function getAccuracyStats($userId = null, $days = 30)
    {
        $query = self::join('projects', 'recommendations.project_id', '=', 'projects.id')
            ->whereNotNull('target_price')
            ->whereNotNull('action_type')
            ->where('recommendations.created_at', '>=', now()->subDays($days))
            ->selectRaw('action_type,
                COUNT(*) as total,
                SUM(CASE
                    WHEN action_type = "buy" AND projects.price_usd <= target_price THEN 1
                    WHEN action_type = "sell" AND projects.price_usd >= target_price THEN 1
                    ELSE 0
                END) as success_count');

        if ($userId) {
            $query->where('recommendations.user_id', $userId);
        }

        return $query->groupBy('action_type')->get();
    }
}

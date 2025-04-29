<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'notification_type',
        'title',
        'content',
        'is_read',
        'data',
    ];

    /**
     * Atribut yang harus dikonversi ke tipe data native.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_read' => 'boolean',
        'data'    => 'array',
    ];

    /**
     * Tipe notifikasi yang valid.
     *
     * @var array<string>
     */
    public static $validTypes = [
        'price_alert',
        'new_recommendation',
        'portfolio_update',
        'market_event',
        'system',
    ];

    /**
     * Mendapatkan relasi ke User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Scope untuk filter notifikasi yang belum dibaca.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope untuk filter notifikasi berdasarkan pengguna.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope untuk filter notifikasi berdasarkan tipe.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('notification_type', $type);
    }

    /**
     * Scope untuk notifikasi terbaru.
     */
    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Menandai notifikasi sebagai sudah dibaca.
     */
    public function markAsRead()
    {
        $this->is_read = true;
        return $this->save();
    }

    /**
     * Menandai semua notifikasi pengguna sebagai sudah dibaca.
     */
    public static function markAllAsRead($userId)
    {
        return self::forUser($userId)->unread()->update(['is_read' => true]);
    }

    /**
     * Membuat notifikasi price alert.
     */
    public static function createPriceAlert($userId, $priceAlert, $currentPrice)
    {
        $project = $priceAlert->project;
        $type    = $priceAlert->alert_type === 'above' ? 'naik di atas' : 'turun di bawah';

        return self::create([
            'user_id'           => $userId,
            'notification_type' => 'price_alert',
            'title'             => "Alert Harga {$project->symbol}",
            'content'           => "Harga {$project->name} ({$project->symbol}) telah {$type} target Anda \${$priceAlert->target_price}. Harga saat ini: \${$currentPrice}.",
            'data'              => [
                'price_alert_id' => $priceAlert->id,
                'project_id'     => $project->id,
                'project_symbol' => $project->symbol,
                'target_price'   => $priceAlert->target_price,
                'current_price'  => $currentPrice,
                'alert_type'     => $priceAlert->alert_type,
            ],
        ]);
    }

    /**
     * Membuat notifikasi rekomendasi baru.
     */
    public static function createRecommendation($userId, $recommendation)
    {
        $project    = $recommendation->project;
        $actionText = match ($recommendation->action_type) {
            'buy' => 'BELI',
            'sell' => 'JUAL',
            'hold' => 'TAHAN',
            default => 'PERHATIKAN'
        };

        return self::create([
            'user_id'           => $userId,
            'notification_type' => 'new_recommendation',
            'title'             => "Rekomendasi {$actionText}: {$project->symbol}",
            'content'           => "Sistem merekomendasikan untuk {$actionText} {$project->name} ({$project->symbol}) dengan kepercayaan " .
            number_format($recommendation->confidence_score * 100, 0) . "%.",
            'data'              => [
                'recommendation_id' => $recommendation->id,
                'project_id'        => $project->id,
                'project_symbol'    => $project->symbol,
                'action_type'       => $recommendation->action_type,
                'confidence_score'  => $recommendation->confidence_score,
                'target_price'      => $recommendation->target_price,
            ],
        ]);
    }

    /**
     * Membuat notifikasi event pasar.
     */
    public static function createMarketEvent($userId, $projectId, $eventType, $message)
    {
        $project = Project::find($projectId);

        if (! $project) {
            return null;
        }

        $titles = [
            'pump'            => "Pump Terdeteksi: {$project->symbol}",
            'dump'            => "Dump Terdeteksi: {$project->symbol}",
            'high_volatility' => "Volatilitas Tinggi: {$project->symbol}",
            'volume_spike'    => "Lonjakan Volume: {$project->symbol}",
        ];

        $title = $titles[$eventType] ?? "Event Pasar: {$project->symbol}";

        return self::create([
            'user_id'           => $userId,
            'notification_type' => 'market_event',
            'title'             => $title,
            'content'           => $message,
            'data'              => [
                'project_id'     => $project->id,
                'project_symbol' => $project->symbol,
                'event_type'     => $eventType,
            ],
        ]);
    }

    /**
     * Mendapatkan jumlah notifikasi yang belum dibaca.
     */
    public static function getUnreadCount($userId)
    {
        return self::forUser($userId)->unread()->count();
    }

    /**
     * Mendapatkan ringkasan notifikasi berdasarkan tipe.
     */
    public static function getTypeSummary($userId)
    {
        return self::forUser($userId)
            ->selectRaw('notification_type, COUNT(*) as count, COUNT(CASE WHEN is_read = 0 THEN 1 END) as unread_count')
            ->groupBy('notification_type')
            ->get();
    }
}

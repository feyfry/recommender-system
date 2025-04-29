<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceAlert extends Model
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
        'target_price',
        'alert_type',
        'is_triggered',
        'triggered_at',
    ];

    /**
     * Atribut yang harus dikonversi ke tipe data native.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'target_price' => 'float',
        'is_triggered' => 'boolean',
        'triggered_at' => 'datetime',
    ];

    /**
     * Tipe alert yang valid.
     *
     * @var array<string>
     */
    public static $validTypes = [
        'above',
        'below',
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
     * Scope untuk filter alert yang belum terpicu.
     */
    public function scopeActive($query)
    {
        return $query->where('is_triggered', false);
    }

    /**
     * Scope untuk filter alert yang sudah terpicu.
     */
    public function scopeTriggered($query)
    {
        return $query->where('is_triggered', true);
    }

    /**
     * Scope untuk filter alert berdasarkan pengguna.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope untuk filter alert berdasarkan proyek.
     */
    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope untuk filter alert berdasarkan tipe.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('alert_type', $type);
    }

    /**
     * Memeriksa apakah alert harus terpicu berdasarkan harga saat ini.
     */
    public function shouldTrigger($currentPrice)
    {
        if ($this->is_triggered) {
            return false;
        }

        if ($this->alert_type === 'above' && $currentPrice >= $this->target_price) {
            return true;
        }

        if ($this->alert_type === 'below' && $currentPrice <= $this->target_price) {
            return true;
        }

        return false;
    }

    /**
     * Menandai alert sebagai terpicu.
     */
    public function trigger()
    {
        $this->is_triggered = true;
        $this->triggered_at = now();
        return $this->save();
    }

    /**
     * Mendapatkan deskripsi yang mudah dibaca.
     */
    public function getDescriptionAttribute()
    {
        $type = $this->alert_type === 'above' ? 'naik di atas' : 'turun di bawah';
        return "{$this->project->name} ({$this->project->symbol}) {$type} \${$this->target_price}";
    }

    /**
     * Mendapatkan pesan notifikasi.
     */
    public function getNotificationMessageAttribute()
    {
        $type         = $this->alert_type === 'above' ? 'naik di atas' : 'turun di bawah';
        $currentPrice = $this->project->price_usd;

        return "Harga {$this->project->name} ({$this->project->symbol}) telah {$type} target Anda \${$this->target_price}. Harga saat ini: \${$currentPrice}.";
    }

    /**
     * Mendapatkan persentase jarak dari harga saat ini ke target.
     */
    public function getPercentageToTargetAttribute()
    {
        $currentPrice = $this->project->price_usd;

        if ($currentPrice == 0) {
            return 0;
        }

        return (($this->target_price - $currentPrice) / $currentPrice) * 100;
    }

    /**
     * Mendapatkan alerts yang perlu diperiksa apakah sudah terpicu.
     */
    public static function getAlertsToCheck()
    {
        return self::active()
            ->with('project')
            ->get();
    }

    /**
     * Mendapatkan statistik alert berdasarkan tipe.
     */
    public static function getAlertStats($userId = null)
    {
        $query = self::selectRaw('alert_type, is_triggered, COUNT(*) as count')
            ->groupBy('alert_type', 'is_triggered');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }
}

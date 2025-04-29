<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interaction extends Model
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
        'interaction_type',
        'weight',
        'context',
        'session_id',
    ];

    /**
     * Atribut yang harus dikonversi ke tipe data native.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'context' => 'array',
        'weight'  => 'integer',
    ];

    /**
     * Daftar tipe interaksi yang valid.
     *
     * @var array<string>
     */
    public static $validTypes = [
        'view',
        'favorite',
        'portfolio_add',
        'research',
        'click',
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
     * Scope filter berdasarkan tipe interaksi.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('interaction_type', $type);
    }

    /**
     * Scope filter berdasarkan user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope filter berdasarkan project.
     */
    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope untuk interaksi dalam rentang waktu tertentu.
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Mendapatkan data statistik interaksi per hari.
     */
    public static function getDailyStats($userId = null, $days = 30)
    {
        $query = self::selectRaw('DATE(created_at) as date, interaction_type, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date', 'interaction_type')
            ->orderBy('date');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Mendapatkan distribusi interaksi berdasarkan tipe.
     */
    public static function getTypeDistribution($userId = null)
    {
        $query = self::selectRaw('interaction_type, COUNT(*) as count')
            ->groupBy('interaction_type')
            ->orderBy('count', 'desc');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }
}

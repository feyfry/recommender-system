<?php
namespace App\Models;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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

    /**
     * Override method save untuk mengirim interaksi ke engine rekomendasi
     */
    public function save(array $options = [])
    {
        // PERBAIKAN: Cek duplikasi sebelum menyimpan
        if (!$this->exists && $this->isDuplicate()) {
            Log::info("Interaction duplikat terdeteksi, skip: {$this->user_id}:{$this->project_id}:{$this->interaction_type}");
            return false;
        }

        $result = parent::save($options);

        // Kirim ke engine rekomendasi setelah disimpan di database
        if ($result && !$this->wasRecentlyCreated) {
            // Hanya kirim ke engine untuk interaksi yang baru dibuat
            try {
                $this->sendToRecommendationEngine();
            } catch (\Exception $e) {
                Log::error("Gagal mengirim interaksi ke engine rekomendasi: " . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Cek apakah interaksi ini duplikat
     */
    protected function isDuplicate()
    {
        // Cek interaksi serupa dalam 60 detik terakhir
        return static::where('user_id', $this->user_id)
            ->where('project_id', $this->project_id)
            ->where('interaction_type', $this->interaction_type)
            ->where('created_at', '>=', now()->subSeconds(60))
            ->exists();
    }

    /**
     * Kirim interaksi ke engine rekomendasi
     */
    public function sendToRecommendationEngine(): bool
    {
        $apiUrl = env('RECOMMENDATION_API_URL', 'http://localhost:8001');

        try {
            // Siapkan data untuk dikirim
            $data = [
                'user_id' => $this->user_id,
                'project_id' => $this->project_id,
                'interaction_type' => $this->interaction_type,
                'weight' => $this->weight,
                'context' => $this->context,
                'timestamp' => $this->created_at->toIso8601String(), // Format ISO8601 dengan timezone
            ];

            // PERBAIKAN: Tambahkan header untuk mencegah duplikasi di sisi API
            $response = Http::timeout(2)
                ->withHeaders([
                    'X-Interaction-ID' => md5("{$this->user_id}:{$this->project_id}:{$this->interaction_type}:{$this->created_at->timestamp}")
                ])
                ->post("{$apiUrl}/interactions/record", $data);

            if ($response->successful()) {
                return true;
            } else {
                Log::warning("Gagal mengirim interaksi ke engine: " . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Error mengirim interaksi ke engine: " . $e->getMessage());
            return false;
        }
    }
}

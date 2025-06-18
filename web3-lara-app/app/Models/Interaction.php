<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Interaction extends Model
{
    use HasFactory;

    /**
     * Flag untuk mencegah duplicate call ke API
     */
    private $apiCallSent = false;

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
        'portfolio_add'
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
     * PERBAIKAN: Method untuk format timestamp yang konsisten
     */
    private function getFormattedTimestamp()
    {
        // Format: Y-m-d\TH:i:s.u (dengan microseconds, tanpa timezone)
        return $this->created_at->format('Y-m-d\TH:i:s.u');
    }

    /**
     * PERBAIKAN: Override method save dengan duplicate prevention
     */
    public function save(array $options = [])
    {
        // PERBAIKAN: Cek duplikasi sebelum menyimpan
        if (! $this->exists && $this->isDuplicate()) {
            Log::info("Interaction duplikat terdeteksi, skip: {$this->user_id}:{$this->project_id}:{$this->interaction_type}");
            return false;
        }

        $result = parent::save($options);

        // PERBAIKAN: Kirim ke engine rekomendasi hanya sekali dan hanya untuk record baru
        if ($result && $this->wasRecentlyCreated && ! $this->apiCallSent) {
            $this->apiCallSent = true; // Set flag untuk mencegah duplicate call

            try {
                $this->sendToRecommendationEngine();
                Log::info("Interaction berhasil dikirim ke engine: {$this->user_id}:{$this->project_id}:{$this->interaction_type}");
            } catch (\Exception $e) {
                Log::error("Gagal mengirim interaksi ke engine rekomendasi: " . $e->getMessage());
                $this->apiCallSent = false; // Reset flag jika gagal
            }
        }

        return $result;
    }

    /**
     * PERBAIKAN: Method create static dengan duplicate check yang lebih robust
     */
    public static function createInteraction($userId, $projectId, $interactionType, $weight = 1, $context = null)
    {
        // Cek duplikasi dalam rentang waktu yang lebih ketat (30 detik)
        $existingInteraction = static::where('user_id', $userId)
            ->where('project_id', $projectId)
            ->where('interaction_type', $interactionType)
            ->where('created_at', '>=', now()->subSeconds(30))
            ->first();

        if ($existingInteraction) {
            Log::info("Duplicate interaction prevented: {$userId}:{$projectId}:{$interactionType}");
            return $existingInteraction;
        }

        return static::create([
            'user_id'          => $userId,
            'project_id'       => $projectId,
            'interaction_type' => $interactionType,
            'weight'           => $weight,
            'context'          => $context ?? [
                'source'    => 'web',
                'timestamp' => now()->timestamp,
            ],
        ]);
    }

    /**
     * Cek apakah interaksi ini duplikat
     */
    protected function isDuplicate()
    {
        // PERBAIKAN: Cek interaksi serupa dalam 30 detik terakhir (lebih ketat)
        return static::where('user_id', $this->user_id)
            ->where('project_id', $this->project_id)
            ->where('interaction_type', $this->interaction_type)
            ->where('created_at', '>=', now()->subSeconds(30))
            ->exists();
    }

    /**
     * PERBAIKAN: Kirim interaksi ke engine rekomendasi dengan format timestamp yang konsisten
     */
    public function sendToRecommendationEngine(): bool
    {
        $apiUrl = env('RECOMMENDATION_API_URL', 'http://localhost:8001');

        try {
            // PERBAIKAN: Gunakan format timestamp yang konsisten
            $data = [
                'user_id'          => $this->user_id,
                'project_id'       => $this->project_id,
                'interaction_type' => $this->interaction_type,
                'weight'           => $this->weight,
                'context'          => $this->context,
                'timestamp'        => $this->getFormattedTimestamp(), // PERBAIKAN: Gunakan format konsisten
            ];

            // PERBAIKAN: Tambahkan unique identifier untuk mencegah duplicate processing di API
            $uniqueId = md5("{$this->user_id}:{$this->project_id}:{$this->interaction_type}:{$this->created_at->timestamp}");

            Log::info("Mengirim interaksi ke engine: {$uniqueId}", $data);

            $response = Http::timeout(2)
                ->withHeaders([
                    'X-Interaction-ID' => $uniqueId,
                    'X-Source'         => 'Laravel-App',
                ])
                ->post("{$apiUrl}/interactions/record", $data);

            if ($response->successful()) {
                Log::info("Interaction berhasil dikirim ke engine rekomendasi: {$uniqueId}");
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

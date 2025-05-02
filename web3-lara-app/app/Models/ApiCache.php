<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ApiCache extends Model
{
    use HasFactory;

    /**
     * Nama tabel model.
     *
     * @var string
     */
    protected $table = 'api_caches';

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array<string>
     */
    protected $fillable = [
        'endpoint',
        'parameters',
        'response',
        'expires_at',
    ];

    /**
     * Atribut yang harus dikonversi ke tipe data native.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'parameters' => 'array',
        'response'   => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * Scope untuk filter cache yang masih valid.
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope untuk filter cache yang sudah kadaluarsa.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * DIOPTIMALKAN: Mencari cache yang cocok dengan endpoint dan parameter
     * dengan penanganan error yang lebih baik dan logging yang minimal
     */
    public static function findMatch($endpoint, $parameters = [])
    {
        try {
            return self::valid()
                ->where('endpoint', $endpoint)
                ->whereJsonContains('parameters', $parameters)
                ->first();
        } catch (\Exception $e) {
            // Tangani error dengan diam-diam dan return null
            // untuk menghindari crash aplikasi hanya karena cache
            Log::error("Cache lookup error for {$endpoint}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * DIOPTIMALKAN: Menyimpan respons ke cache dengan penanganan error yang lebih baik
     */
    public static function store($endpoint, $parameters, $response, $expiresInMinutes = 60)
    {
        try {
            // PERBAIKAN: Periksa apakah respons valid (tidak kosong/null)
            if (empty($response) || (is_array($response) && count($response) == 0)) {
                Log::warning("Mencoba menyimpan respons kosong ke cache untuk endpoint: {$endpoint}");
                return null;
            }

            // Hapus cache lama dengan endpoint dan parameter yang sama
            self::where('endpoint', $endpoint)
                ->whereJsonContains('parameters', $parameters)
                ->delete();

            return self::create([
                'endpoint'   => $endpoint,
                'parameters' => $parameters,
                'response'   => $response,
                'expires_at' => now()->addMinutes($expiresInMinutes),
            ]);
        } catch (\Exception $e) {
            // Mencatat error tetapi tidak menghentikan aplikasi
            Log::error("Cache storage error for {$endpoint}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * DIOPTIMALKAN: Menghapus semua cache yang sudah kadaluarsa dengan batching
     * untuk mengurangi beban database
     */
    public static function clearExpired()
    {
        try {
            $expiredCount = 0;

            // Hapus dengan batching untuk mengurangi beban database
            self::expired()->chunk(500, function ($caches) use (&$expiredCount) {
                $ids     = $caches->pluck('id')->toArray();
                $deleted = self::whereIn('id', $ids)->delete();
                $expiredCount += $deleted;
            });

            return $expiredCount;
        } catch (\Exception $e) {
            Log::error("Failed to clear expired cache: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * DIOPTIMALKAN: Menghapus semua cache dengan penanganan error yang lebih baik
     */
    public static function clearAll()
    {
        try {
            return self::truncate();
        } catch (\Exception $e) {
            Log::error("Failed to clear all cache: " . $e->getMessage());
            // Jika truncate gagal, coba cara alternatif
            try {
                return self::query()->delete();
            } catch (\Exception $e2) {
                Log::error("Alternative cache clearing also failed: " . $e2->getMessage());
                return false;
            }
        }
    }

    /**
     * DIOPTIMALKAN: Menghapus cache untuk endpoint tertentu
     */
    public static function clearEndpoint($endpoint)
    {
        try {
            return self::where('endpoint', $endpoint)->delete();
        } catch (\Exception $e) {
            Log::error("Failed to clear cache for endpoint {$endpoint}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * DIOPTIMALKAN: Mendapatkan statistik cache dengan caching statistik itu sendiri
     * untuk menghindari kalkulasi yang mahal
     */
    public static function getStats()
    {
        try {
            // Hitung dengan query yang efisien
            $total     = self::count();
            $valid     = self::valid()->count();
            $expired   = self::expired()->count();
            $endpoints = self::distinct('endpoint')->count('endpoint');

            return [
                'total'     => $total,
                'valid'     => $valid,
                'expired'   => $expired,
                'endpoints' => $endpoints,
                'hit_rate'  => $valid > 0 ? ($valid / $total) * 100 : 0,
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get cache stats: " . $e->getMessage());
            return [
                'total'     => 0,
                'valid'     => 0,
                'expired'   => 0,
                'endpoints' => 0,
                'hit_rate'  => 0,
                'error'     => true,
            ];
        }
    }

    /**
     * DIOPTIMALKAN: Mendapatkan daftar endpoint yang di-cache dengan limit
     */
    public static function getEndpointsList($limit = 100)
    {
        try {
            return self::select('endpoint')
                ->distinct('endpoint')
                ->orderBy('endpoint')
                ->limit($limit)
                ->get()
                ->pluck('endpoint');
        } catch (\Exception $e) {
            Log::error("Failed to get endpoints list: " . $e->getMessage());
            return collect([]);
        }
    }

    /**
     * DIOPTIMALKAN: Mendapatkan penggunaan cache berdasarkan endpoint
     */
    public static function getEndpointUsage($limit = 20)
    {
        try {
            return self::selectRaw('endpoint, COUNT(*) as count')
                ->groupBy('endpoint')
                ->orderBy('count', 'desc')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            Log::error("Failed to get endpoint usage: " . $e->getMessage());
            return collect([]);
        }
    }

    /**
     * DIOPTIMALKAN: Metode baru untuk membersihkan cache secara terprogram
     * berdasarkan pola endpoint
     */
    public static function clearByPattern($pattern)
    {
        try {
            return self::where('endpoint', 'like', $pattern)->delete();
        } catch (\Exception $e) {
            Log::error("Failed to clear cache by pattern {$pattern}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * DIOPTIMALKAN: Metode baru untuk maintenance cache otomatis yang dapat
     * dijalankan oleh scheduler
     */
    public static function performMaintenance()
    {
        try {
            // Hapus yang kadaluarsa
            $expiredCount = self::clearExpired();

            // Hapus cache yang terlalu lama (bahkan jika belum expired)
            // untuk mencegah penumpukan data
            $oldCacheCount = self::where('created_at', '<', now()->subDays(7))->delete();

                                    // Hapus cache yang terlalu banyak dimulai dari yang paling lama
            $maxCacheCount = 10000; // Batasi jumlah cache pada 10,000
            $totalCache    = self::count();

            if ($totalCache > $maxCacheCount) {
                $excessCount = $totalCache - $maxCacheCount;

                // Hapus cache yang melebihi batas
                $deletedExcessCount = self::orderBy('created_at')->limit($excessCount)->delete();
            } else {
                $deletedExcessCount = 0;
            }

            // PERBAIKAN: Bersihkan cache yang tidak valid
            $invalidCacheCount = self::cleanInvalidCache();

            return [
                'expired_removed' => $expiredCount,
                'old_removed'     => $oldCacheCount,
                'excess_removed'  => $deletedExcessCount,
                'invalid_removed' => $invalidCacheCount,
            ];
        } catch (\Exception $e) {
            Log::error("Cache maintenance failed: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * PERBAIKAN: Metode baru untuk membersihkan cache yang tidak valid
     */
    public static function cleanInvalidCache()
    {
        try {
            // Hapus cache dengan respons kosong atau null
            $nullResponseCount = self::whereNull('response')->delete();

            // Hapus cache dengan array kosong (lebih kompleks karena disimpan sebagai JSON)
            $emptyResponseCount = self::where(function($query) {
                $query->whereRaw("JSON_LENGTH(response) = 0")
                      ->orWhereRaw("response = '[]'")
                      ->orWhereRaw("response = '{}'");
            })->delete();

            $totalRemoved = $nullResponseCount + $emptyResponseCount;

            // Log hasil
            if ($totalRemoved > 0) {
                Log::info("Cleaned {$totalRemoved} invalid cache entries ({$nullResponseCount} null, {$emptyResponseCount} empty)");
            }

            return $totalRemoved;
        } catch (\Exception $e) {
            Log::error("Error cleaning invalid cache: " . $e->getMessage());
            return 0;
        }
    }
}

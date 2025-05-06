<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
            return null;
        }
    }

    /**
     * DIOPTIMALKAN: Menyimpan respons ke cache dengan penanganan error yang lebih baik
     * dan TTL yang lebih lama
     */
    public static function store($endpoint, $parameters, $response, $expiresInMinutes = 120)
    {
        try {
            // PERBAIKAN: Periksa apakah respons valid (tidak kosong/null)
            if (empty($response) || (is_array($response) && count($response) == 0)) {
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
            return null;
        }
    }

    /**
     * DIOPTIMALKAN: Menghapus cache yang sudah kadaluarsa dengan batching
     * dan limit yang lebih agresif
     */
    public static function clearExpired()
    {
        try {
            $expiredCount = 0;

            // Hapus dengan batching dan limit untuk mengurangi beban database
            self::expired()->chunk(200, function ($caches) use (&$expiredCount) {
                $ids     = $caches->pluck('id')->toArray();
                $deleted = self::whereIn('id', $ids)->limit(1000)->delete();
                $expiredCount += $deleted;
            });

            return $expiredCount;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * DIOPTIMALKAN: Hapus secara batching untuk mengurangi beban database
     */
    public static function clearAll()
    {
        try {
            $count = 0;
            // Batching delete untuk mengurangi beban database
            self::chunk(500, function ($caches) use (&$count) {
                $ids     = $caches->pluck('id')->toArray();
                $deleted = self::whereIn('id', $ids)->delete();
                $count += $deleted;
            });
            return $count;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * DIOPTIMALKAN: Menghapus cache untuk endpoint tertentu dengan limit
     */
    public static function clearEndpoint($endpoint)
    {
        try {
            return self::where('endpoint', $endpoint)->limit(500)->delete();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * DIOPTIMALKAN: Mendapatkan statistik cache dengan pagination
     */
    public static function getStats($page = 1, $perPage = 100)
    {
        try {
            // Hitung dengan query yang efisien
            $total     = self::count();
            $valid     = self::valid()->count();
            $expired   = $total - $valid;
            $endpoints = self::distinct('endpoint')->count('endpoint');

            return [
                'total'     => $total,
                'valid'     => $valid,
                'expired'   => $expired,
                'endpoints' => $endpoints,
                'hit_rate'  => $valid > 0 ? ($valid / $total) * 100 : 0,
            ];
        } catch (\Exception $e) {
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
     * DIOPTIMALKAN: Mendapatkan daftar endpoint yang di-cache dengan pagination
     */
    public static function getEndpointsList($page = 1, $perPage = 50)
    {
        try {
            return self::select('endpoint')
                ->distinct('endpoint')
                ->orderBy('endpoint')
                ->paginate($perPage, ['*'], 'page', $page);
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    /**
     * DIOPTIMALKAN: Mendapatkan penggunaan cache berdasarkan endpoint dengan pagination
     */
    public static function getEndpointUsage($page = 1, $perPage = 20)
    {
        try {
            return self::selectRaw('endpoint, COUNT(*) as count')
                ->groupBy('endpoint')
                ->orderBy('count', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    /**
     * DIOPTIMALKAN: Menghapus cache berdasarkan pola dengan batching dan limit
     */
    public static function clearByPattern($pattern)
    {
        try {
            $count = 0;
            self::where('endpoint', 'like', $pattern)
                ->chunk(200, function ($caches) use (&$count) {
                    $ids     = $caches->pluck('id')->toArray();
                    $deleted = self::whereIn('id', $ids)->limit(500)->delete();
                    $count += $deleted;
                });
            return $count;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * DIOPTIMALKAN: Metode maintenance dengan batching dan limit
     */
    public static function performMaintenance()
    {
        try {
            // Hapus yang kadaluarsa dengan limit
            $expiredCount = self::where('expires_at', '<', now())
                ->limit(1000)
                ->delete();

            // Hapus cache yang terlalu lama dengan limit
            $oldCacheCount = self::where('created_at', '<', now()->subDays(7))
                ->limit(1000)
                ->delete();

                                    // Hapus cache yang terlalu banyak dimulai dari yang paling lama
            $maxCacheCount = 10000; // Batasi jumlah cache pada 10,000
            $totalCache    = self::count();

            $deletedExcessCount = 0;
            if ($totalCache > $maxCacheCount) {
                $excessCount = $totalCache - $maxCacheCount;

                // Hapus dengan batching
                self::orderBy('created_at')
                    ->limit($excessCount < 1000 ? $excessCount : 1000)
                    ->delete();
                $deletedExcessCount = $excessCount < 1000 ? $excessCount : 1000;
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
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * DIOPTIMALKAN: Metode untuk membersihkan cache yang tidak valid dengan limit
     */
    public static function cleanInvalidCache()
    {
        try {
            // Hapus cache dengan respons kosong atau null dengan limit
            $nullResponseCount = self::whereNull('response')
                ->limit(500)
                ->delete();

            // Hapus cache dengan array kosong dengan limit
            $emptyResponseCount = self::where(function ($query) {
                $query->whereRaw("JSON_LENGTH(response) = 0")
                    ->orWhereRaw("response = '[]'")
                    ->orWhereRaw("response = '{}'");
            })
                ->limit(500)
                ->delete();

            $totalRemoved = $nullResponseCount + $emptyResponseCount;

            return $totalRemoved;
        } catch (\Exception $e) {
            return 0;
        }
    }
}

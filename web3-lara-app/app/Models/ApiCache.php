<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiCache extends Model
{
    use HasFactory;

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
     * Mencari cache yang cocok dengan endpoint dan parameter.
     */
    public static function findMatch($endpoint, $parameters = [])
    {
        return self::valid()
            ->where('endpoint', $endpoint)
            ->whereJsonContains('parameters', $parameters)
            ->first();
    }

    /**
     * Menyimpan respons ke cache.
     */
    public static function store($endpoint, $parameters, $response, $expiresInMinutes = 60)
    {
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
    }

    /**
     * Menghapus semua cache yang sudah kadaluarsa.
     */
    public static function clearExpired()
    {
        return self::expired()->delete();
    }

    /**
     * Menghapus semua cache.
     */
    public static function clearAll()
    {
        return self::truncate();
    }

    /**
     * Menghapus cache untuk endpoint tertentu.
     */
    public static function clearEndpoint($endpoint)
    {
        return self::where('endpoint', $endpoint)->delete();
    }

    /**
     * Mendapatkan statistik cache.
     */
    public static function getStats()
    {
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
    }

    /**
     * Mendapatkan daftar endpoint yang di-cache.
     */
    public static function getEndpointsList()
    {
        return self::select('endpoint')
            ->distinct('endpoint')
            ->orderBy('endpoint')
            ->get()
            ->pluck('endpoint');
    }

    /**
     * Mendapatkan penggunaan cache berdasarkan endpoint.
     */
    public static function getEndpointUsage()
    {
        return self::selectRaw('endpoint, COUNT(*) as count')
            ->groupBy('endpoint')
            ->orderBy('count', 'desc')
            ->get();
    }
}

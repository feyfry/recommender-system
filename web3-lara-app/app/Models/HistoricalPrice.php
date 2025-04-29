<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoricalPrice extends Model
{
    use HasFactory;

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array<string>
     */
    protected $fillable = [
        'project_id',
        'timestamp',
        'price',
        'volume',
        'market_cap',
    ];

    /**
     * Atribut yang harus dikonversi ke tipe data native.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'timestamp'  => 'datetime',
        'price'      => 'float',
        'volume'     => 'float',
        'market_cap' => 'float',
    ];

    /**
     * Mendapatkan relasi ke Project.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }

    /**
     * Scope untuk filter data berdasarkan rentang waktu.
     */
    public function scopeInTimeRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('timestamp', [$startDate, $endDate]);
    }

    /**
     * Scope untuk filter data berdasarkan jumlah hari terakhir.
     */
    public function scopeLastDays($query, $days = 30)
    {
        return $query->where('timestamp', '>=', now()->subDays($days));
    }

    /**
     * Scope untuk filter data berdasarkan proyek.
     */
    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Mendapatkan data harga untuk satu proyek dalam periode tertentu.
     */
    public static function getProjectPriceData($projectId, $days = 30, $interval = '1d')
    {
        $query = self::forProject($projectId)
            ->lastDays($days)
            ->orderBy('timestamp');

        // Jika interval bukan harian, kita perlu melakukan agregasi
        if ($interval !== '1d') {
            // Parse interval
            $value = (int) substr($interval, 0, -1);
            $unit  = substr($interval, -1);

            $timeFormat = match ($unit) {
                'h' => 'Y-m-d H:00', // Per jam
                'd' => 'Y-m-d',      // Per hari
                'w' => 'Y-W',        // Per minggu
                'm' => 'Y-m',        // Per bulan
                default => 'Y-m-d H:i'
            };

            return $query->get()
                ->groupBy(function ($item) use ($timeFormat) {
                    return $item->timestamp->format($timeFormat);
                })
                ->map(function ($group) {
                    return [
                        'timestamp' => $group->first()->timestamp,
                        'open'      => $group->first()->price,
                        'close'     => $group->last()->price,
                        'high'      => $group->max('price'),
                        'low'       => $group->min('price'),
                        'volume'    => $group->sum('volume'),
                        'avg_price' => $group->avg('price'),
                    ];
                })
                ->values();
        }

        return $query->get();
    }

    /**
     * Mendapatkan data untuk analisis teknikal.
     */
    public static function getTechnicalAnalysisData($projectId, $days = 30)
    {
        $priceData = self::getProjectPriceData($projectId, $days);

        if ($priceData->isEmpty()) {
            return null;
        }

        // Format data untuk indikator teknikal
        $timestamps = $priceData->pluck('timestamp')->map(function ($date) {
            return $date->format('Y-m-d');
        })->toArray();

        $prices  = $priceData->pluck('price')->toArray();
        $volumes = $priceData->pluck('volume')->toArray();

        return [
            'timestamps' => $timestamps,
            'prices'     => $prices,
            'volumes'    => $volumes,
            'data'       => $priceData,
        ];
    }

    /**
     * Mendapatkan perubahan persentase harga dari awal hingga akhir periode.
     */
    public static function getPriceChangePercentage($projectId, $days = 30)
    {
        $data = self::forProject($projectId)
            ->lastDays($days)
            ->orderBy('timestamp')
            ->get();

        if ($data->count() < 2) {
            return 0;
        }

        $startPrice = $data->first()->price;
        $endPrice   = $data->last()->price;

        if ($startPrice == 0) {
            return 0;
        }

        return (($endPrice - $startPrice) / $startPrice) * 100;
    }

    /**
     * Mendapatkan tingkat volatilitas (standar deviasi perubahan harian).
     */
    public static function getVolatility($projectId, $days = 30)
    {
        $data = self::forProject($projectId)
            ->lastDays($days)
            ->orderBy('timestamp')
            ->get();

        if ($data->count() < 2) {
            return 0;
        }

        // Hitung perubahan harian dalam persentase
        $dailyChanges  = [];
        $previousPrice = null;

        foreach ($data as $item) {
            if ($previousPrice !== null && $previousPrice != 0) {
                $dailyChanges[] = (($item->price - $previousPrice) / $previousPrice) * 100;
            }
            $previousPrice = $item->price;
        }

        // Hitung standar deviasi
        $mean     = array_sum($dailyChanges) / count($dailyChanges);
        $variance = 0;

        foreach ($dailyChanges as $change) {
            $variance += pow($change - $mean, 2);
        }

        return sqrt($variance / count($dailyChanges));
    }

    /**
     * Mendapatkan ringkasan metrik harga.
     */
    public static function getPriceSummary($projectId, $days = 30)
    {
        $data = self::forProject($projectId)
            ->lastDays($days)
            ->orderBy('timestamp')
            ->get();

        if ($data->isEmpty()) {
            return null;
        }

        return [
            'current_price'           => $data->last()->price,
            'high_price'              => $data->max('price'),
            'low_price'               => $data->min('price'),
            'avg_price'               => $data->avg('price'),
            'price_change'            => $data->last()->price - $data->first()->price,
            'price_change_percentage' => self::getPriceChangePercentage($projectId, $days),
            'volatility'              => self::getVolatility($projectId, $days),
            'volume_avg'              => $data->avg('volume'),
            'first_date'              => $data->first()->timestamp,
            'last_date'               => $data->last()->timestamp,
        ];
    }
}

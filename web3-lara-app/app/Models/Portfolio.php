<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Portfolio extends Model
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
        'amount',
        'average_buy_price',
    ];

    /**
     * Atribut yang harus dikonversi ke tipe data native.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount'            => 'float',
        'average_buy_price' => 'float',
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
     * Mendapatkan nilai investasi saat ini.
     */
    public function getCurrentValueAttribute()
    {
        return $this->amount * $this->project->price_usd;
    }

    /**
     * Mendapatkan nilai investasi awal.
     */
    public function getInitialValueAttribute()
    {
        return $this->amount * $this->average_buy_price;
    }

    /**
     * Mendapatkan keuntungan/kerugian dalam nilai USD.
     */
    public function getProfitLossValueAttribute()
    {
        return $this->current_value - $this->initial_value;
    }

    /**
     * Mendapatkan keuntungan/kerugian dalam persentase.
     */
    public function getProfitLossPercentageAttribute()
    {
        if ($this->initial_value == 0) {
            return 0;
        }

        return ($this->profit_loss_value / $this->initial_value) * 100;
    }

    /**
     * Scope untuk filter portofolio berdasarkan pengguna.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Menghitung total nilai portofolio untuk pengguna tertentu.
     */
    public static function getTotalValue($userId)
    {
        $portfolios = self::forUser($userId)->with('project')->get();
        $total      = 0;

        foreach ($portfolios as $portfolio) {
            $total += $portfolio->current_value;
        }

        return $total;
    }

    /**
     * Mendapatkan distribusi portofolio berdasarkan kategori proyek.
     */
    public static function getCategoryDistribution($userId)
    {
        return self::forUser($userId)
            ->join('projects', 'portfolios.project_id', '=', 'projects.id')
            ->selectRaw('projects.primary_category,
                        SUM(portfolios.amount * projects.price_usd) as value,
                        COUNT(*) as project_count')
            ->groupBy('projects.primary_category')
            ->get();
    }

    /**
     * Mendapatkan distribusi portofolio berdasarkan chain proyek.
     */
    public static function getChainDistribution($userId)
    {
        return self::forUser($userId)
            ->join('projects', 'portfolios.project_id', '=', 'projects.id')
            ->selectRaw('projects.chain,
                        SUM(portfolios.amount * projects.price_usd) as value,
                        COUNT(*) as project_count')
            ->groupBy('projects.chain')
            ->get();
    }

    /**
     * Mendapatkan data performa portofolio selama periode tertentu.
     */
    public static function getPerformanceData($userId, $days = 30)
    {
        $portfolios = self::forUser($userId)->with(['project.historicalPrices' => function ($query) use ($days) {
            $query->where('timestamp', '>=', now()->subDays($days))
                ->orderBy('timestamp');
        }])->get();

        $performanceData = [];

        foreach ($portfolios as $portfolio) {
            foreach ($portfolio->project->historicalPrices as $price) {
                $date = $price->timestamp->format('Y-m-d');

                if (! isset($performanceData[$date])) {
                    $performanceData[$date] = 0;
                }

                $performanceData[$date] += $portfolio->amount * $price->price;
            }
        }

        // Konversi ke array untuk visualisasi
        $result = [];
        foreach ($performanceData as $date => $value) {
            $result[] = [
                'date'  => $date,
                'value' => $value,
            ];
        }

        return collect($result)->sortBy('date')->values()->all();
    }
}

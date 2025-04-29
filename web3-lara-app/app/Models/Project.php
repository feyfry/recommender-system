<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Project extends Model
{
    use HasFactory;

    /**
     * Kunci utama adalah ID (bukan auto-increment).
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Kunci utama tidak auto-increment.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Tipe kolom kunci utama.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array<string>
     */
    protected $fillable = [
        'id',
        'name',
        'symbol',
        'categories',
        'platforms',
        'market_cap',
        'volume_24h',
        'price_usd',
        'price_change_24h',
        'price_change_percentage_24h',
        'price_change_percentage_7d',
        'price_change_percentage_1h',
        'price_change_percentage_30d',
        'image',
        'popularity_score',
        'trend_score',
        'social_score',
        'social_engagement_score',
        'developer_activity_score',
        'maturity_score',
        'sentiment_positive',
        'description',
        'twitter_followers',
        'telegram_channel_user_count',
        'github_stars',
        'github_forks',
        'github_subscribers',
        'chain',
        'primary_category',
        'genesis_date',
        'all_time_high',
        'all_time_high_date',
        'all_time_low',
        'all_time_low_date',
        'circulating_supply',
        'total_supply',
        'max_supply',
        'fully_diluted_valuation',
    ];

    /**
     * Atribut yang harus dikonversi ke tipe data native.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'categories'         => 'array',
        'platforms'          => 'array',
        'genesis_date'       => 'date',
        'all_time_high_date' => 'datetime',
        'all_time_low_date'  => 'datetime',
    ];

    /**
     * Mendapatkan interaksi untuk proyek ini.
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class, 'project_id', 'id');
    }

    /**
     * Mendapatkan harga historis untuk proyek ini.
     */
    public function historicalPrices(): HasMany
    {
        return $this->hasMany(HistoricalPrice::class, 'project_id', 'id');
    }

    /**
     * Mendapatkan rekomendasi untuk proyek ini.
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class, 'project_id', 'id');
    }

    /**
     * Mendapatkan portfolios yang berisi proyek ini.
     */
    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class, 'project_id', 'id');
    }

    /**
     * Mendapatkan transactions untuk proyek ini.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'project_id', 'id');
    }

    /**
     * Mendapatkan price alerts untuk proyek ini.
     */
    public function priceAlerts(): HasMany
    {
        return $this->hasMany(PriceAlert::class, 'project_id', 'id');
    }

    /**
     * Mendapatkan users yang memiliki interaksi dengan proyek ini.
     */
    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            Interaction::class,
            'project_id', // Kunci asing di tabel interactions
            'user_id',    // Kunci asing di tabel users
            'id',         // Kunci utama di tabel projects
            'user_id'     // Kunci utama di tabel interactions yang merujuk ke users
        );
    }

    /**
     * Mendapatkan harga terbaru.
     */
    public function getLatestPriceAttribute()
    {
        return $this->historicalPrices()
            ->orderBy('timestamp', 'desc')
            ->first();
    }

    /**
     * Format market cap dengan format yang bagus.
     */
    public function getFormattedMarketCapAttribute(): string
    {
        if (is_null($this->market_cap)) {
            return 'Tidak diketahui';
        }

        // Format market cap dalam milyar/juta/triliun/dst
        if ($this->market_cap >= 1_000_000_000_000) {
            return 'Rp' . number_format($this->market_cap / 1_000_000_000_000, 2) . ' T';
        } else if ($this->market_cap >= 1_000_000_000) {
            return 'Rp' . number_format($this->market_cap / 1_000_000_000, 2) . ' M';
        } else if ($this->market_cap >= 1_000_000) {
            return 'Rp' . number_format($this->market_cap / 1_000_000, 2) . ' Jt';
        } else {
            return 'Rp' . number_format($this->market_cap, 2);
        }
    }

    /**
     * Format harga dengan format yang bagus.
     */
    public function getFormattedPriceAttribute(): string
    {
        if (is_null($this->price_usd)) {
            return 'Tidak diketahui';
        }

        // Format harga berdasarkan besar nilainya
        if ($this->price_usd < 0.00001) {
            return '$' . number_format($this->price_usd, 8);
        } else if ($this->price_usd < 0.01) {
            return '$' . number_format($this->price_usd, 6);
        } else if ($this->price_usd < 1) {
            return '$' . number_format($this->price_usd, 4);
        } else {
            return '$' . number_format($this->price_usd, 2);
        }
    }

    /**
     * Mendapatkan perubahan harga 24 jam dengan formatnya.
     */
    public function getFormattedPriceChangeAttribute(): string
    {
        if (is_null($this->price_change_percentage_24h)) {
            return '0.00%';
        }

        $prefix = $this->price_change_percentage_24h >= 0 ? '+' : '';
        return $prefix . number_format($this->price_change_percentage_24h, 2) . '%';
    }
}

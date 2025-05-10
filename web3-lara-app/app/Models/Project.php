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
        'symbol',
        'name',
        'image',
        'current_price',
        'market_cap',
        'market_cap_rank',
        'fully_diluted_valuation',
        'total_volume',
        'high_24h',
        'low_24h',
        'price_change_24h',
        'price_change_percentage_24h',
        'market_cap_change_24h',
        'market_cap_change_percentage_24h',
        'circulating_supply',
        'total_supply',
        'max_supply',
        'ath',
        'ath_change_percentage',
        'ath_date',
        'atl',
        'atl_change_percentage',
        'atl_date',
        'roi',
        'last_updated',
        'price_change_percentage_1h_in_currency',
        'price_change_percentage_24h_in_currency',
        'price_change_percentage_30d_in_currency',
        'price_change_percentage_7d_in_currency',
        'query_category',
        'platforms',
        'categories',
        'twitter_followers',
        'github_stars',
        'github_subscribers',
        'github_forks',
        'description',
        'genesis_date',
        'sentiment_votes_up_percentage',
        'telegram_channel_user_count',
        'primary_category',
        'chain',
        'popularity_score',
        'trend_score',
        'developer_activity_score',
        'social_engagement_score',
        'description_length',
        'age_days',
        'maturity_score',
        'is_trending',
    ];

    /**
     * Atribut yang harus dikonversi ke tipe data native.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'categories'    => 'array',
        'platforms'     => 'array',
        'roi'           => 'array',
        'genesis_date'  => 'date',
        'ath_date'      => 'datetime',
        'atl_date'      => 'datetime',
        'last_updated'  => 'datetime',
        'is_trending'   => 'boolean',
    ];

    /**
     * Mendapatkan interaksi untuk proyek ini.
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class, 'project_id', 'id');
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
        if (is_null($this->current_price)) {
            return 'Tidak diketahui';
        }

        // Format harga berdasarkan besar nilainya
        if ($this->current_price < 0.00001) {
            return '$' . number_format($this->current_price, 8);
        } else if ($this->current_price < 0.01) {
            return '$' . number_format($this->current_price, 6);
        } else if ($this->current_price < 1) {
            return '$' . number_format($this->current_price, 4);
        } else {
            return '$' . number_format($this->current_price, 2);
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

<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'wallet_address',
        'nonce',
        'role',
        'last_login',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'nonce',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_login' => 'datetime',
        ];
    }

    /**
     * Mendapatkan relasi ke profil pengguna.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * Mendapatkan relasi ke interaksi pengguna.
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class, 'user_id', 'user_id');
    }

    /**
     * Mendapatkan relasi ke rekomendasi pengguna.
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class, 'user_id', 'user_id');
    }

    /**
     * Mendapatkan relasi ke portfolio pengguna.
     */
    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class, 'user_id', 'user_id');
    }

    /**
     * Mendapatkan relasi ke transaksi pengguna.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_id', 'user_id');
    }

    /**
     * Mendapatkan relasi ke price alerts pengguna.
     */
    public function priceAlerts(): HasMany
    {
        return $this->hasMany(PriceAlert::class, 'user_id', 'user_id');
    }

    /**
     * Mendapatkan relasi ke notifikasi pengguna.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id', 'user_id');
    }

    /**
     * Mendapatkan relasi ke log aktivitas pengguna.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_id', 'user_id');
    }

    /**
     * Periksa apakah pengguna memiliki peran tertentu/spesifik role.
     *
     * @param string $role
     * @return bool
     */
    public function hasRole($role)
    {
        return $this->role === $role;
    }

    /**
     * Periksa apakah pengguna memiliki salah satu peran yang diberikan.
     *
     * @param array|string $roles
     * @return bool
     */
    public function hasAnyRole($roles)
    {
        return in_array($this->role, (array) $roles);
    }

    /**
     * Periksa apakah pengguna adalah admin.
     *
     * @return bool
     */
    public function isAdmin()
    {
        return $this->hasRole('admin');
    }

    /**
     * Periksa apakah pengguna adalah community.
     *
     * @return bool
     */
    public function isCommunity()
    {
        return $this->hasRole('community');
    }

    /**
     * Periksa apakah pengguna memiliki notifikasi yang belum dibaca.
     *
     * @return bool
     */
    public function hasUnreadNotifications()
    {
        return $this->notifications()->unread()->exists();
    }

    /**
     * Mendapatkan jumlah notifikasi yang belum dibaca.
     *
     * @return int
     */
    public function getUnreadNotificationsCountAttribute()
    {
        return $this->notifications()->unread()->count();
    }

    /**
     * Mendapatkan total nilai portfolio.
     *
     * @return float
     */
    public function getTotalPortfolioValueAttribute()
    {
        return Portfolio::getTotalValue($this->user_id);
    }

    /**
     * Mendapatkan jumlah project berbeda di portfolio.
     *
     * @return int
     */
    public function getPortfolioProjectCountAttribute()
    {
        return $this->portfolios()->count();
    }

    /**
     * Mendapatkan jumlah total interaksi.
     *
     * @return int
     */
    public function getTotalInteractionsAttribute()
    {
        return $this->interactions()->count();
    }

    /**
     * Mendapatkan tanggal registrasi yang diformat.
     *
     * @return string
     */
    public function getFormattedCreatedAtAttribute()
    {
        return $this->created_at->format('j F Y');
    }

    /**
     * Mendapatkan tanggal login terakhir yang diformat.
     *
     * @return string|null
     */
    public function getFormattedLastLoginAttribute()
    {
        return $this->last_login ? $this->last_login->format('j F Y H:i') : null;
    }

    /**
     * Scope pencarian berdasarkan alamat wallet.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearchWallet($query, $search)
    {
        return $query->where('wallet_address', 'like', "%{$search}%");
    }

    /**
     * Scope pencarian berdasarkan role.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $role
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithRole($query, $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope untuk pengguna yang baru mendaftar.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $days
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNewUsers($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope untuk pengguna yang aktif.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $days
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query, $days = 30)
    {
        return $query->where('last_login', '>=', now()->subDays($days));
    }

    /**
     * Mendapatkan username dari relasi profile, atau wallet address jika tidak ada profile.
     *
     * @return string
     */
    public function getDisplayNameAttribute()
    {
        return $this->profile?->username ?? substr($this->wallet_address, 0, 10) . '...';
    }

    /**
     * Mendapatkan toleransi risiko dari profile.
     *
     * @return string|null
     */
    public function getRiskToleranceAttribute()
    {
        return $this->profile?->risk_tolerance;
    }

    /**
     * Mendapatkan gaya investasi dari profile.
     *
     * @return string|null
     */
    public function getInvestmentStyleAttribute()
    {
        return $this->profile?->investment_style;
    }
}

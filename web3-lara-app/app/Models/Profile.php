<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory;

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'username',
        'avatar_url',
        'preferences',
        'risk_tolerance',
        'investment_style',
        'notification_settings',
    ];

    /**
     * Atribut yang harus dikonversi ke tipe data native.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'preferences'           => 'array',
        'notification_settings' => 'array',
    ];

    /**
     * Daftar tipe risk tolerance yang valid.
     *
     * @var array<string>
     */
    public static $validRiskTolerances = [
        'low',
        'medium',
        'high',
    ];

    /**
     * Daftar tipe gaya investasi yang valid.
     *
     * @var array<string>
     */
    public static $validInvestmentStyles = [
        'conservative',
        'balanced',
        'aggressive',
    ];

    /**
     * Mendapatkan relasi ke User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mendapatkan Avatar URL dengan default.
     *
     * @return string
     */
    public function getAvatarUrlAttribute($value)
    {
        if ($value) {
            return $value;
        }

        // Jika tidak ada avatar, gunakan avatar berdasarkan wallet address
        $hash = md5(strtolower($this->user->wallet_address));
        return "https://www.gravatar.com/avatar/{$hash}?d=identicon&s=200";
    }

    /**
     * Mendapatkan deskripsi risk tolerance yang mudah dibaca.
     *
     * @return string
     */
    public function getRiskToleranceTextAttribute()
    {
        return match ($this->risk_tolerance) {
            'low' => 'Rendah',
            'medium' => 'Sedang',
            'high' => 'Tinggi',
            default => 'Belum diatur'
        };
    }

    /**
     * Mendapatkan deskripsi gaya investasi yang mudah dibaca.
     *
     * @return string
     */
    public function getInvestmentStyleTextAttribute()
    {
        return match ($this->investment_style) {
            'conservative' => 'Konservatif',
            'balanced' => 'Seimbang',
            'aggressive' => 'Agresif',
            default => 'Belum diatur'
        };
    }

    /**
     * Mendapatkan deskripsi gaya investasi.
     *
     * @return string
     */
    public function getInvestmentStyleDescriptionAttribute()
    {
        return match ($this->investment_style) {
            'conservative' => 'Fokus pada proyek dengan kapitalisasi pasar besar dan volatilitas rendah',
            'balanced' => 'Campuran antara proyek established dan proyek yang sedang berkembang',
            'aggressive' => 'Mencakup proyek baru dengan potensi pertumbuhan tinggi namun risiko lebih besar',
            default => 'Belum diatur'
        };
    }

    /**
     * Mendapatkan deskripsi toleransi risiko.
     *
     * @return string
     */
    public function getRiskToleranceDescriptionAttribute()
    {
        return match ($this->risk_tolerance) {
            'low' => 'Anda lebih menyukai investasi yang stabil dengan risiko rendah',
            'medium' => 'Anda mencari keseimbangan antara risiko dan potensi keuntungan',
            'high' => 'Anda siap mengambil risiko lebih besar untuk potensi keuntungan yang lebih tinggi',
            default => 'Belum diatur'
        };
    }

    /**
     * Mendapatkan warna untuk risk tolerance.
     *
     * @return string
     */
    public function getRiskToleranceColorAttribute()
    {
        return match ($this->risk_tolerance) {
            'low' => 'brutal-green',
            'medium' => 'brutal-yellow',
            'high' => 'brutal-pink',
            default => 'gray'
        };
    }

    /**
     * Mendapatkan warna untuk gaya investasi.
     *
     * @return string
     */
    public function getInvestmentStyleColorAttribute()
    {
        return match ($this->investment_style) {
            'conservative' => 'brutal-blue',
            'balanced' => 'brutal-orange',
            'aggressive' => 'brutal-pink',
            default => 'gray'
        };
    }

    /**
     * Mendapatkan preferensi kategori.
     *
     * @return array
     */
    public function getPreferredCategoriesAttribute()
    {
        return $this->preferences['categories'] ?? [];
    }

    /**
     * Mendapatkan preferensi chain.
     *
     * @return array
     */
    public function getPreferredChainsAttribute()
    {
        return $this->preferences['chains'] ?? [];
    }

    /**
     * Memeriksa apakah profil sudah lengkap.
     *
     * @return bool
     */
    public function isComplete()
    {
        return ! empty($this->username) &&
        ! empty($this->risk_tolerance) &&
        ! empty($this->investment_style);
    }

    /**
     * Mendapatkan persentase kelengkapan profil.
     *
     * @return int
     */
    public function getCompletenessPercentageAttribute()
    {
        $fields = [
            $this->username,
            $this->risk_tolerance,
            $this->investment_style,
            ! empty($this->preferences['categories']),
            ! empty($this->preferences['chains']),
        ];

        $filledFields = count(array_filter($fields));
        return (int) ($filledFields / count($fields) * 100);
    }
}

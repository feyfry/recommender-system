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
     * SIMPLIFIED: Memeriksa apakah profil sudah lengkap (hanya username)
     *
     * @return bool
     */
    public function isComplete()
    {
        return !empty($this->username);
    }

    /**
     * SIMPLIFIED: Mendapatkan persentase kelengkapan profil (hanya username)
     *
     * @return int
     */
    public function getCompletenessPercentageAttribute()
    {
        return !empty($this->username) ? 100 : 0;
    }
}

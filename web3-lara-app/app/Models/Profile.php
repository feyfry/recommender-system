<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Testing\Fluent\Concerns\Has;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory;

    protected $table = 'profiles';

    protected $fillable = [
        'user_id',
        'username',
        'avatar_url',
        'preferences',
        'risk_tolerance',
        'investment_style',
        'notification_settings'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

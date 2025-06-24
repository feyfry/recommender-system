<?php

namespace App\Providers;

use App\Models\ApiCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Mengatur default TTL cache menjadi 60 menit untuk semua cache
        // jika tidak ditentukan secara eksplisit
        if (method_exists(Cache::class, 'setDefaultCacheTime')) {
            Cache::setDefaultCacheTime(60);
        }
    }
}

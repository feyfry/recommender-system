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
        // Menjalankan pembersihan cache yang kadaluwarsa secara otomatis
        // saat aplikasi di-bootstrap dalam interval tertentu (tidak setiap request)
        if (! $this->app->runningInConsole() && rand(1, 100) <= 5) { // 5% chance on web requests
            try {
                ApiCache::where('expires_at', '<', now())->limit(100)->delete();
            } catch (\Exception $e) {
                // Jangan biarkan kegagalan pembersihan cache menghentikan aplikasi
                Log::info('Failed to clean expired cache during bootstrap: ' . $e->getMessage());
            }
        }

        // Mengatur default TTL cache menjadi 60 menit untuk semua cache
        // jika tidak ditentukan secara eksplisit
        if (method_exists(Cache::class, 'setDefaultCacheTime')) {
            Cache::setDefaultCacheTime(60);
        }
    }
}

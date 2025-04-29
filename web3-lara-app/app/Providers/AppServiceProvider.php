<?php
namespace App\Providers;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // DIOPTIMALKAN: Konfigurasikan pencatatan aktivitas agar hanya
        // mencatat aktivitas yang benar-benar penting
        if (method_exists(ActivityLog::class, 'shouldLogViewActivities')) {
            ActivityLog::shouldLogViewActivities(false);
        }

        // DIOPTIMALKAN: Log query lambat hanya dalam mode debug
        if (config('app.debug')) {
            DB::listen(function ($query) {
                // Catat query yang lambat (lebih dari 1 detik)
                if ($query->time > 1000) {
                    Log::info(
                        'Slow Query: ' . $query->sql,
                        [
                            'time'     => $query->time,
                            'bindings' => $query->bindings,
                        ]
                    );
                }
            });
        }

        // DIOPTIMALKAN: Siapkan konfigurasi default untuk HTTP client
        if (class_exists('\Illuminate\Support\Facades\Http')) {
            \Illuminate\Support\Facades\Http::macro('apiRequest', function ($method, $url, $options = []) {
                // Default timeout 3 detik untuk mencegah blocking aplikasi terlalu lama
                $timeout = $options['timeout'] ?? 3;

                return \Illuminate\Support\Facades\Http::timeout($timeout)
                    ->withOptions(['verify' => ! app()->isLocal()])
                    ->withHeaders(['Accept' => 'application/json'])
                    ->$method($url, $options['data'] ?? []);
            });
        }
    }
}

<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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
        // DIOPTIMALKAN: Log query lambat hanya dalam mode debug dan dengan threshold lebih tinggi
        if (config('app.debug')) {
            DB::listen(function ($query) {
                // Catat query yang sangat lambat (lebih dari 2 detik)
                // dulu 1 detik (1000ms), sekarang 2 detik (2000ms)
                if ($query->time > 2000) {
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
                // Default timeout 2 detik - lebih agresif (dulu 3 detik)
                $timeout = $options['timeout'] ?? 2;

                return \Illuminate\Support\Facades\Http::timeout($timeout)
                    ->withOptions(['verify' => ! app()->isLocal()])
                    ->withHeaders(['Accept' => 'application/json'])
                    ->$method($url, $options['data'] ?? []);
            });
        }

        // Siapkan paginator
        Paginator::useBootstrap();
    }
}

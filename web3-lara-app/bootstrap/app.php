<?php

use App\Http\Middleware\CacheHeadersMiddleware;
use App\Http\Middleware\CheckRoleMiddleware;
use App\Http\Middleware\HandleCorsMiddleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Psr\SimpleCache\CacheException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Mendaftarkan alias middleware
        $middleware->alias([
            'role'          => CheckRoleMiddleware::class,
            'cache.headers' => CacheHeadersMiddleware::class,
            'cors'          => HandleCorsMiddleware::class,
        ]);

        // Menambahkan middleware untuk semua response
        $middleware->web(append: [
            SetCacheHeaders::class,
        ]);

        // Tambahkan CORS middleware untuk route web3
        $middleware->group('web3', [
            HandleCorsMiddleware::class,
        ]);
    })
    ->withCommands([
        App\Console\Commands\ImportRecommendationData::class,
        App\Console\Commands\SyncRecommendationData::class,
        App\Console\Commands\ClearApiCache::class,
    ])
    ->withSchedule(function (Schedule $schedule) {
        // UPDATED: Production pipeline dengan auto import
        $schedule->exec('cd ' . base_path('../recommendation-engine') . ' && python main.py run --production --evaluate')
            ->cron('0 */12 * * *')
            ->description('Production pipeline lengkap dengan auto import')
            ->after(function () {
                // Auto import setelah production pipeline selesai
                \Illuminate\Support\Facades\Artisan::call('recommend:import --projects --force');
                \Illuminate\Support\Facades\Artisan::call('recommend:import --interactions --force');

                // Clear cache setelah import
                \Illuminate\Support\Facades\Cache::flush();

                \Illuminate\Support\Facades\Log::info('Production pipeline completed with auto import');
            });

        // Export interaksi dari Laravel ke engine setiap 4 jam
        $schedule->command('recommend:sync --interactions')
            ->everyFourHours()
            ->description('Export interaksi pengguna dari Laravel ke engine');

        // Bersihkan cache memory setiap jam
        $schedule->call(function () {
            $knownKeys = [
                'admin_user_stats',
                'admin_project_stats',
                'admin_interaction_stats',
                'admin_transaction_stats',
                'rec_trending_8',
                'rec_popular_8',
                'all_categories',
                'all_chains',
            ];

            foreach ($knownKeys as $key) {
                \Illuminate\Support\Facades\Cache::forget($key);
            }
        })
            ->hourly()
            ->description('Bersihkan cache memory kadaluwarsa');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport([
            CacheException::class,
        ]);
    })
    ->create();

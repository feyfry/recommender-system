<?php

use Psr\SimpleCache\CacheException;
use Illuminate\Foundation\Application;
use Illuminate\Console\Scheduling\Schedule;
use App\Http\Middleware\CheckRoleMiddleware;
use App\Http\Middleware\HandleCorsMiddleware;
use App\Http\Middleware\CacheHeadersMiddleware;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        App\Console\Commands\ClearApiCache::class
    ])
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('recommend:sync --projects')
            ->cron('0 */12 * * *')
            ->description('Sinkronisasi data proyek dari engine rekomendasi');

        $schedule->command('recommend:sync --interactions')
            ->everyFourHours()
            ->description('Sinkronisasi interaksi pengguna dengan engine rekomendasi');

        $schedule->command('recommend:sync --train')
            ->dailyAt('03:00')
            ->description('Melatih model rekomendasi');

        $schedule->command('cache:api-clear --expired')
            ->hourly()
            ->description('Bersihkan cache API yang kadaluwarsa');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport([
            CacheException::class,
        ]);
    })
    ->create();

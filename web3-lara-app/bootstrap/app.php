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
        App\Console\Commands\SyncRecommendationData::class
    ])
    ->withSchedule(function (Schedule $schedule) {
        // ENHANCED: Production pipeline dengan auto import yang lebih robust
        $schedule->exec('cd ' . base_path('../recommendation-engine') . ' && python main.py run --production --evaluate')
            ->cron('0 */12 * * *')
            ->description('Production pipeline lengkap dengan auto import')
            ->after(function () {
                try {
                    // CHECK: Auto import enabled?
                    $autoImportEnabled = env('AUTO_IMPORT_ENABLED', false);

                    if (!$autoImportEnabled) {
                        \Illuminate\Support\Facades\Log::info('Auto import disabled, skipping...');
                        return;
                    }

                    \Illuminate\Support\Facades\Log::info('Starting auto import after pipeline');

                    // Import projects & interactions
                    \Illuminate\Support\Facades\Artisan::call('recommend:import --projects --force');
                    \Illuminate\Support\Facades\Artisan::call('recommend:import --interactions --force');

                    // Clear cache
                    \Illuminate\Support\Facades\Cache::flush();

                    \Illuminate\Support\Facades\Log::info('Auto import completed');
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Auto import failed: ' . $e->getMessage());
                }
            })
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Production pipeline failed to execute');
            });

        // Export interaksi dari Laravel ke engine setiap 4 jam
        $schedule->command('recommend:sync --interactions')
            ->everyFourHours()
            ->description('Export interaksi pengguna dari Laravel ke engine')
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Interaction sync failed');
            });

        // Bersihkan Laravel memory cache setiap jam (lebih agresif)
        $schedule->call(function () {
            try {
                // Clear specific cache keys yang sering digunakan
                $cacheKeys = [
                    'admin_user_stats',
                    'admin_project_stats',
                    'admin_interaction_stats',
                    'admin_transaction_stats',
                    'rec_trending_8',
                    'rec_popular_8',
                    'all_categories',
                    'all_chains',
                    'projects_all_categories',
                    'projects_all_chains',
                    'all_project_categories',
                    'all_project_chains',
                ];

                $clearedCount = 0;
                foreach ($cacheKeys as $key) {
                    if (\Illuminate\Support\Facades\Cache::forget($key)) {
                        $clearedCount++;
                    }
                }

                \Illuminate\Support\Facades\Log::info("Cleared {$clearedCount} cache keys from memory");
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Memory cache cleanup failed: ' . $e->getMessage());
            }
        })
            ->hourly()
            ->description('Bersihkan Laravel memory cache kadaluwarsa');

        // ENHANCED: Backup otomatis data penting setiap hari
        $schedule->call(function () {
            try {
                // Backup hanya interaksi dan portfolio yang penting
                $backupData = [
                    'interactions_count' => \App\Models\Interaction::count(),
                    'portfolios_count'   => \App\Models\Portfolio::count(),
                    'users_count'        => \App\Models\User::count(),
                    'projects_count'     => \App\Models\Project::count(),
                    'backup_date'        => now(),
                ];

                \Illuminate\Support\Facades\Log::info('Daily backup stats: ' . json_encode($backupData));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Daily backup failed: ' . $e->getMessage());
            }
        })
            ->daily()
            ->description('Backup statistik harian')
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Daily backup failed to execute');
            });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport([
            CacheException::class,
        ]);
    })
    ->create();

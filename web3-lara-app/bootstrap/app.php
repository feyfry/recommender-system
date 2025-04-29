<?php

use Psr\SimpleCache\CacheException;
use Illuminate\Foundation\Application;
use App\Http\Middleware\CacheHeadersMiddleware;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CheckRoleMiddleware; // Middleware baru untuk manajemen cache

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
            'cache.headers' => CacheHeadersMiddleware::class, // Daftarkan middleware cache
        ]);

        // Menambahkan middleware cache untuk semua response publik
        // menggunakan sintaks yang benar untuk Laravel 12
        $middleware->web(append: [
            SetCacheHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Konfigurasi exception handling
        $exceptions->dontReport([
            // Tipe exception yang tidak perlu dilaporkan
            CacheException::class,
        ]);
    })
    ->create();

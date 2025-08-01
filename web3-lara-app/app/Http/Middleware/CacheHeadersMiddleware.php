<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, int $seconds = 1800): Response
    {
        $response = $next($request);

        // Jangan tambahkan cache headers untuk halaman admin atau route yang memerlukan autentikasi
        if ($request->is('admin*') || $request->is('panel*') || $request->is('login*')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
            return $response;
        }

        // Tambahkan cache headers untuk halaman publik statis
        // Ditingkatkan dari 600 detik menjadi 1800 detik
        if (! $request->user() && $request->isMethod('GET') && $response->getStatusCode() == 200) {
            $response->setCache([
                'public'   => true,
                'max_age'  => $seconds,
                's_maxage' => $seconds,
            ]);
            $response->headers->set('X-Optimized-By', 'Web3 Recommender');
        }

        return $response;
    }
}

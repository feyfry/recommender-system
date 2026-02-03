<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WelcomeController extends Controller
{
    /**
     * URL API untuk rekomendasi
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * Konstruktor untuk mengatur URL API
     */
    public function __construct()
    {
        $this->apiUrl = env('RECOMMENDATION_API_URL', 'http://localhost:8001');
    }

    /**
     * Menampilkan halaman welcome
     */
    public function index()
    {
        // Ambil cold-start recommendations untuk preview
        $todayRecommendations = $this->getTodayRecommendations();

        return view('welcome', [
            'todayRecommendations' => $todayRecommendations,
        ]);
    }

    /**
     * AJAX endpoint untuk mendapatkan today's recommendations
     */
    public function getTodayRecommendationsAjax()
    {
        $recommendations = $this->getTodayRecommendations();
        return response()->json($recommendations);
    }

    /**
     * Mendapatkan rekomendasi hari ini (cold-start)
     */
    private function getTodayRecommendations()
    {
        return Cache::remember('today_recommendations_4', 60, function () {
            try {
                // Gunakan trending sebagai fallback untuk cold-start
                $response = Http::timeout(3)->get("{$this->apiUrl}/recommend/trending", [
                    'limit' => 4,
                ])->json();

                if (!empty($response) && is_array($response)) {
                    return $this->normalizeRecommendationData($response);
                }

                // Fallback ke popular jika trending gagal
                $response = Http::timeout(3)->get("{$this->apiUrl}/recommend/popular", [
                    'limit' => 4,
                ])->json();

                if (!empty($response) && is_array($response)) {
                    return $this->normalizeRecommendationData($response);
                }

                return [];
            } catch (\Exception $e) {
                Log::error("Error getting today recommendations: " . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Normalisasi data rekomendasi
     */
    private function normalizeRecommendationData($recommendations)
    {
        if (empty($recommendations)) {
            return [];
        }

        $normalized = [];
        foreach ($recommendations as $key => $item) {
            if (is_string($item)) {
                continue;
            }

            $data = is_object($item) ? (array) $item : $item;
            $id = $data['id'] ?? "unknown-{$key}";

            // Extract price change data
            $priceChangePercentage24h = $data['price_change_percentage_24h'] ?? $data['price_change_percentage_24h_in_currency'] ?? 0;

            $normalized[] = [
                'id' => $id,
                'name' => $data['name'] ?? 'Unknown',
                'symbol' => $data['symbol'] ?? 'N/A',
                'image' => $data['image'] ?? null,
                'current_price' => floatval($data['current_price'] ?? 0),
                'price_change_percentage_24h' => floatval($priceChangePercentage24h),
                'market_cap' => floatval($data['market_cap'] ?? 0),
                'total_volume' => floatval($data['total_volume'] ?? 0),
                'primary_category' => $data['primary_category'] ?? $data['category'] ?? 'Uncategorized',
                'chain' => $data['chain'] ?? 'Multiple',
                'popularity_score' => floatval($data['popularity_score'] ?? 0),
                'trend_score' => floatval($data['trend_score'] ?? 0),
            ];
        }

        return $normalized;
    }
}

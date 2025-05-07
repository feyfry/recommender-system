<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TechnicalAnalysisController extends Controller
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
     * Menampilkan halaman analisis teknikal
     */
    public function index(Request $request)
    {
        $projectId = $request->input('project_id');
        $project   = null;

        if ($projectId) {
            $project = Project::find($projectId);
        }

        // Dapatkan presets trading style
        $tradingStyles = [
            'short_term' => [
                'name'        => 'Short-Term Trading',
                'desc'        => 'Periode indikator lebih pendek untuk trading jangka pendek',
                'rsi_period'  => 7,
                'macd_fast'   => 8,
                'macd_slow'   => 17,
                'macd_signal' => 9,
                'bb_period'   => 10,
                'stoch_k'     => 7,
                'stoch_d'     => 3,
                'ma_short'    => 10,
                'ma_medium'   => 30,
                'ma_long'     => 60,
            ],
            'standard'   => [
                'name'        => 'Standard Trading',
                'desc'        => 'Periode standar untuk analisis teknikal',
                'rsi_period'  => 14,
                'macd_fast'   => 12,
                'macd_slow'   => 26,
                'macd_signal' => 9,
                'bb_period'   => 20,
                'stoch_k'     => 14,
                'stoch_d'     => 3,
                'ma_short'    => 20,
                'ma_medium'   => 50,
                'ma_long'     => 200,
            ],
            'long_term'  => [
                'name'        => 'Long-Term Trading',
                'desc'        => 'Periode lebih panjang untuk perspektif jangka panjang',
                'rsi_period'  => 21,
                'macd_fast'   => 19,
                'macd_slow'   => 39,
                'macd_signal' => 9,
                'bb_period'   => 30,
                'stoch_k'     => 21,
                'stoch_d'     => 7,
                'ma_short'    => 50,
                'ma_medium'   => 100,
                'ma_long'     => 200,
            ],
        ];

        // Daftar risiko
        $riskLevels = [
            'low'    => 'Rendah',
            'medium' => 'Sedang',
            'high'   => 'Tinggi',
        ];

        // Dapatkan semua project untuk dropdown (bisa dikelompokkan berdasarkan popularitas)
        $allProjects = Cache::remember('all_projects_for_analysis', 60, function () {
            // Ambil semua project, tetapi pisahkan antara yang populer dan yang lainnya
            $popularProjects = Project::where('popularity_score', '>=', 50)
                ->orderBy('popularity_score', 'desc')
                ->select('id', 'name', 'symbol', 'image', 'popularity_score')
                ->get();

            $otherProjects = Project::where('popularity_score', '<', 50)
                ->orderBy('name', 'asc')
                ->select('id', 'name', 'symbol', 'image', 'popularity_score')
                ->get();

            // Gabungkan dengan opsi grup
            $popularProjects->each(function ($project) {
                $project->group = 'Popular';
            });

            $otherProjects->each(function ($project) {
                $project->group = 'Other';
            });

            return $popularProjects->concat($otherProjects);
        });

        // Pass ke view
        return view('backend.technical_analysis.index', [
            'project'       => $project,
            'tradingStyles' => $tradingStyles,
            'riskLevels'    => $riskLevels,
            'topProjects'   => $allProjects,
        ]);
    }

    /**
     * Mendapatkan sinyal trading dengan periode kustom
     */
    public function getTradingSignals(Request $request)
    {
        $request->validate([
            'project_id' => 'required|string',
            'days' => 'integer|min:7|max:365',
            'risk_tolerance' => 'string|in:low,medium,high',
            'trading_style' => 'string|in:short_term,standard,long_term',
        ]);

        $projectId = $request->input('project_id');
        $days = $request->input('days', 30);
        $riskTolerance = $request->input('risk_tolerance', 'medium');
        $tradingStyle = $request->input('trading_style', 'standard');
        $interval = $request->input('interval', '1d');

        // Buat array untuk periode indikator
        $periods = [];

        // Ambil nilai periode dari request jika ada
        $indicatorKeys = [
            'rsi_period', 'macd_fast', 'macd_slow', 'macd_signal',
            'bb_period', 'stoch_k', 'stoch_d', 'ma_short', 'ma_medium', 'ma_long'
        ];

        foreach ($indicatorKeys as $key) {
            if ($request->has($key)) {
                $periods[$key] = intval($request->input($key));
            }
        }

        // Tentukan cache key yang unik berdasarkan semua parameter
        $cacheKey = "trading_signals_{$projectId}_{$riskTolerance}_{$tradingStyle}_{$days}_{$interval}";

        if (!empty($periods)) {
            $cacheKey .= "_" . md5(json_encode($periods));
        }

        // PERBAIKAN: Returnkan data langsung, jangan gunakan response()->json() dalam cache
        $resultData = Cache::remember($cacheKey, 30, function () use ($projectId, $days, $riskTolerance, $tradingStyle, $interval, $periods) {
            try {
                $requestData = [
                    'project_id' => $projectId,
                    'days' => $days,
                    'interval' => $interval,
                    'risk_tolerance' => $riskTolerance,
                    'trading_style' => $tradingStyle,
                ];

                // Tambahkan periode kustom jika ada
                if (!empty($periods)) {
                    $requestData['periods'] = $periods;
                }

                $response = Http::timeout(5)->post("{$this->apiUrl}/analysis/trading-signals", $requestData);

                if ($response->successful()) {
                    // Return data langsung, bukan response object
                    return $response->json();
                } else {
                    Log::warning("Gagal mendapatkan sinyal trading: " . $response->body());
                    return [
                        'error' => true,
                        'message' => 'Gagal mendapatkan sinyal trading. Coba lagi nanti.'
                    ];
                }
            } catch (\Exception $e) {
                Log::error("Error mendapatkan sinyal trading: " . $e->getMessage());
                return [
                    'error' => true,
                    'message' => 'Terjadi kesalahan: ' . $e->getMessage()
                ];
            }
        });

        // Return data dalam format json
        return response()->json($resultData);
    }

    /**
     * Mendapatkan indikator teknikal dengan periode kustom
     */
    public function getIndicators(Request $request)
    {
        $request->validate([
            'project_id' => 'required|string',
            'days'       => 'integer|min:7|max:365',
            'indicators' => 'array',
        ]);

        $projectId  = $request->input('project_id');
        $days       = $request->input('days', 30);
        $interval   = $request->input('interval', '1d');
        $indicators = $request->input('indicators', ['rsi', 'macd', 'bollinger', 'sma']);

        // Buat array untuk periode indikator
        $periods = [];

        // Ambil nilai periode dari request jika ada
        $indicatorKeys = [
            'rsi_period', 'macd_fast', 'macd_slow', 'macd_signal',
            'bb_period', 'stoch_k', 'stoch_d', 'ma_short', 'ma_medium', 'ma_long',
        ];

        foreach ($indicatorKeys as $key) {
            if ($request->has($key)) {
                $periods[$key] = intval($request->input($key));
            }
        }

        // Tentukan cache key yang unik berdasarkan semua parameter
        $cacheKey = "indicators_{$projectId}_{$days}_{$interval}_" . md5(json_encode($indicators));

        if (! empty($periods)) {
            $cacheKey .= "_" . md5(json_encode($periods));
        }

        return Cache::remember($cacheKey, 30, function () use ($projectId, $days, $interval, $indicators, $periods) {
            try {
                $requestData = [
                    'project_id' => $projectId,
                    'days'       => $days,
                    'interval'   => $interval,
                    'indicators' => $indicators,
                ];

                // Tambahkan periode kustom jika ada
                if (! empty($periods)) {
                    $requestData['periods'] = $periods;
                }

                $response = Http::timeout(5)->post("{$this->apiUrl}/analysis/indicators", $requestData);

                if ($response->successful()) {
                    return response()->json($response->json());
                } else {
                    Log::warning("Gagal mendapatkan indikator teknikal: " . $response->body());
                    return response()->json([
                        'error'   => true,
                        'message' => 'Gagal mendapatkan indikator teknikal. Coba lagi nanti.',
                    ], 500);
                }
            } catch (\Exception $e) {
                Log::error("Error mendapatkan indikator teknikal: " . $e->getMessage());
                return response()->json([
                    'error'   => true,
                    'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                ], 500);
            }
        });
    }

    /**
     * Mendapatkan peristiwa pasar (market events)
     */
    public function getMarketEvents(Request $request, $projectId)
    {
        $days       = $request->input('days', 30);
        $interval   = $request->input('interval', '1d');
        $windowSize = $request->input('window_size', 14);

        // Ambil threshold kustom jika ada
        $thresholds    = [];
        $thresholdKeys = ['pump_threshold', 'dump_threshold', 'volatility_threshold', 'volume_threshold'];

        foreach ($thresholdKeys as $key) {
            if ($request->has($key)) {
                $thresholds[str_replace('_threshold', '', $key)] = floatval($request->input($key));
            }
        }

        // Tentukan cache key yang unik
        $cacheKey = "market_events_{$projectId}_{$days}_{$interval}_{$windowSize}";

        if (! empty($thresholds)) {
            $cacheKey .= "_" . md5(json_encode($thresholds));
        }

        return Cache::remember($cacheKey, 30, function () use ($projectId, $days, $interval, $windowSize, $thresholds) {
            try {
                $params = [
                    'days'        => $days,
                    'interval'    => $interval,
                    'window_size' => $windowSize,
                ];

                if (! empty($thresholds)) {
                    $params['thresholds'] = $thresholds;
                }

                $response = Http::timeout(5)->get("{$this->apiUrl}/analysis/market-events/{$projectId}", $params);

                if ($response->successful()) {
                    return response()->json($response->json());
                } else {
                    Log::warning("Gagal mendapatkan market events: " . $response->body());
                    return response()->json([
                        'error'   => true,
                        'message' => 'Gagal mendapatkan peristiwa pasar. Coba lagi nanti.',
                    ], 500);
                }
            } catch (\Exception $e) {
                Log::error("Error mendapatkan market events: " . $e->getMessage());
                return response()->json([
                    'error'   => true,
                    'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                ], 500);
            }
        });
    }

    /**
     * Mendapatkan alert teknikal
     */
    public function getAlerts(Request $request, $projectId)
    {
        $days     = $request->input('days', 30);
        $interval = $request->input('interval', '1d');
        $lookback = $request->input('lookback', 5);

        // Ambil periode kustom jika ada
        $periods       = [];
        $indicatorKeys = [
            'rsi_period', 'macd_fast', 'macd_slow', 'macd_signal',
            'bb_period', 'stoch_k', 'stoch_d', 'ma_short', 'ma_medium', 'ma_long',
        ];

        foreach ($indicatorKeys as $key) {
            if ($request->has($key)) {
                $periods[$key] = intval($request->input($key));
            }
        }

        // Tentukan cache key yang unik
        $cacheKey = "alerts_{$projectId}_{$days}_{$interval}_{$lookback}";

        if (! empty($periods)) {
            $cacheKey .= "_" . md5(json_encode($periods));
        }

        return Cache::remember($cacheKey, 30, function () use ($projectId, $days, $interval, $lookback, $periods) {
            try {
                $params = [
                    'days'     => $days,
                    'interval' => $interval,
                    'lookback' => $lookback,
                ];

                if (! empty($periods)) {
                    $params['periods'] = $periods;
                }

                $response = Http::timeout(5)->get("{$this->apiUrl}/analysis/alerts/{$projectId}", $params);

                if ($response->successful()) {
                    return response()->json($response->json());
                } else {
                    Log::warning("Gagal mendapatkan alerts: " . $response->body());
                    return response()->json([
                        'error'   => true,
                        'message' => 'Gagal mendapatkan alert teknikal. Coba lagi nanti.',
                    ], 500);
                }
            } catch (\Exception $e) {
                Log::error("Error mendapatkan alerts: " . $e->getMessage());
                return response()->json([
                    'error'   => true,
                    'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                ], 500);
            }
        });
    }

    /**
     * Mendapatkan prediksi harga
     */
    public function getPricePrediction(Request $request, $projectId)
    {
        $days           = $request->input('days', 30);
        $predictionDays = $request->input('prediction_days', 7);
        $interval       = $request->input('interval', '1d');
        $model          = $request->input('model', 'auto');

        // Tentukan cache key yang unik
        $cacheKey = "price_prediction_{$projectId}_{$days}_{$predictionDays}_{$interval}_{$model}";

        return Cache::remember($cacheKey, 30, function () use ($projectId, $days, $predictionDays, $interval, $model) {
            try {
                $params = [
                    'days'            => $days,
                    'prediction_days' => $predictionDays,
                    'interval'        => $interval,
                    'model'           => $model,
                ];

                $response = Http::timeout(8)->get("{$this->apiUrl}/analysis/price-prediction/{$projectId}", $params);

                if ($response->successful()) {
                    return response()->json($response->json());
                } else {
                    Log::warning("Gagal mendapatkan prediksi harga: " . $response->body());
                    return response()->json([
                        'error'   => true,
                        'message' => 'Gagal mendapatkan prediksi harga. Coba lagi nanti.',
                    ], 500);
                }
            } catch (\Exception $e) {
                Log::error("Error mendapatkan prediksi harga: " . $e->getMessage());
                return response()->json([
                    'error'   => true,
                    'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                ], 500);
            }
        });
    }

    /**
     * Mendapatkan data historis untuk chart
     */
    public function getHistoricalData(Request $request)
    {
        $request->validate([
            'project_id' => 'required|string',
            'days' => 'integer|min:7|max:365',
            'interval' => 'string|in:1d,4h,1h,15m',
        ]);

        $projectId = $request->input('project_id');
        $days = $request->input('days', 30);
        $interval = $request->input('interval', '1d');

        // Tentukan cache key
        $cacheKey = "historical_data_{$projectId}_{$days}_{$interval}";

        return Cache::remember($cacheKey, 30, function () use ($projectId, $days, $interval) {
            try {
                $response = Http::timeout(5)->get("{$this->apiUrl}/analysis/historical-data/{$projectId}", [
                    'days' => $days,
                    'interval' => $interval,
                ]);

                if ($response->successful()) {
                    return response()->json($response->json());
                } else {
                    Log::warning("Gagal mendapatkan data historis: " . $response->body());
                    return response()->json([
                        'error' => true,
                        'message' => 'Gagal mendapatkan data historis. Coba lagi nanti.',
                    ], 500);
                }
            } catch (\Exception $e) {
                Log::error("Error mendapatkan data historis: " . $e->getMessage());
                return response()->json([
                    'error' => true,
                    'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                ], 500);
            }
        });
    }
}

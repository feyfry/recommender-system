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

                $response = Http::timeout(10)->post("{$this->apiUrl}/analysis/trading-signals", $requestData);

                if ($response->successful()) {
                    $data = $response->json();

                    // PERBAIKAN: Normalisasi format reversal_signals
                    if (isset($data['reversal_signals']) && is_array($data['reversal_signals'])) {
                        $normalizedSignals = [];
                        foreach ($data['reversal_signals'] as $signal) {
                            if (is_array($signal) && isset($signal['description'])) {
                                // Signal sudah dalam format object yang benar
                                $normalizedSignals[] = $signal;
                            } elseif (is_string($signal)) {
                                // Convert string signal ke object format
                                $normalizedSignals[] = [
                                    'type' => 'general',
                                    'description' => $signal,
                                    'strength' => 0.5
                                ];
                            }
                        }
                        $data['reversal_signals'] = $normalizedSignals;
                    }

                    return $data;
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

                $response = Http::timeout(10)->post("{$this->apiUrl}/analysis/indicators", $requestData);

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

                // PERBAIKAN: Format thresholds sebagai query parameters individual
                if (!empty($thresholds)) {
                    foreach ($thresholds as $key => $value) {
                        $params[$key . '_threshold'] = $value;
                    }
                }

                $response = Http::timeout(10)->get("{$this->apiUrl}/analysis/market-events/{$projectId}", $params);

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
        $tradingStyle = $request->input('trading_style', 'standard');

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
        $cacheKey = "alerts_{$projectId}_{$days}_{$interval}_{$lookback}_{$tradingStyle}";

        if (! empty($periods)) {
            $cacheKey .= "_" . md5(json_encode($periods));
        }

        return Cache::remember($cacheKey, 30, function () use ($projectId, $days, $interval, $lookback, $tradingStyle, $periods) {
            try {
                $params = [
                    'days'     => $days,
                    'interval' => $interval,
                    'lookback' => $lookback,
                    'trading_style' => $tradingStyle,
                ];

                // PERBAIKAN: Handle periods sebagai JSON string di query parameter
                if (! empty($periods)) {
                    foreach ($periods as $key => $value) {
                        $params[$key] = $value;
                    }
                }

                $response = Http::timeout(10)->get("{$this->apiUrl}/analysis/alerts/{$projectId}", $params);

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
     * PERBAIKAN: Enhanced getPricePrediction method dengan timeout dan error handling yang lebih baik
     */
    public function getPricePrediction(Request $request, $projectId)
    {
        $days           = $request->input('days', 30);
        $predictionDays = $request->input('prediction_days', 7);
        $interval       = $request->input('interval', '1d');
        $model          = $request->input('model', 'auto');

        // Tentukan cache key yang unik
        $cacheKey = "price_prediction_{$projectId}_{$days}_{$predictionDays}_{$interval}_{$model}";

        return Cache::remember($cacheKey, 60, function () use ($projectId, $days, $predictionDays, $interval, $model) {
            try {
                $params = [
                    'days'            => $days,
                    'prediction_days' => $predictionDays,
                    'interval'        => $interval,
                    'model'           => $model,
                ];

                // PERBAIKAN: Timeout yang lebih lama untuk ML predictions (terutama LSTM)
                $timeout = $model === 'ml' || $model === 'auto' ? 90 : 30; // 90 detik untuk ML, 30 untuk lainnya

                Log::info("Starting price prediction for {$projectId} with model {$model}, timeout: {$timeout}s");

                $response = Http::timeout($timeout)->get("{$this->apiUrl}/analysis/price-prediction/{$projectId}", $params);

                if ($response->successful()) {
                    $data = $response->json();

                    // PERBAIKAN: Enhanced data validation dan normalisasi
                    if (!isset($data['error'])) {
                        // Validate predictions array
                        if (isset($data['predictions']) && is_array($data['predictions'])) {
                            $validPredictions = [];
                            foreach ($data['predictions'] as $prediction) {
                                // Validate each prediction point
                                if (isset($prediction['date'], $prediction['value'], $prediction['confidence'])) {
                                    // Ensure numeric values are valid
                                    $value = is_numeric($prediction['value']) ? (float)$prediction['value'] : 0.0;
                                    $confidence = is_numeric($prediction['confidence']) ? (float)$prediction['confidence'] : 0.0;

                                    // Only include valid predictions
                                    if ($value > 0 && is_finite($value)) {
                                        $validPredictions[] = [
                                            'date' => $prediction['date'],
                                            'value' => $value,
                                            'confidence' => max(0.0, min(1.0, $confidence)) // Clamp confidence to 0-1
                                        ];
                                    }
                                }
                            }
                            $data['predictions'] = $validPredictions;
                        }

                        // Validate other numeric fields
                        $numericFields = ['current_price', 'predicted_change_percent', 'confidence', 'reversal_probability'];
                        foreach ($numericFields as $field) {
                            if (isset($data[$field]) && !is_numeric($data[$field])) {
                                $data[$field] = 0.0;
                            }
                        }

                        // Ensure required fields exist
                        $data['model_type'] = $data['model_type'] ?? 'Unknown';
                        $data['prediction_direction'] = $data['prediction_direction'] ?? 'unknown';
                        $data['market_regime'] = $data['market_regime'] ?? 'unknown';

                        // Log keberhasilan model untuk debugging
                        Log::info("Price prediction successful for {$projectId}: Model = {$data['model_type']}, Confidence = " . ($data['confidence'] ?? 'N/A'));

                        return $data;
                    } else {
                        Log::warning("API returned error for {$projectId}: " . ($data['message'] ?? 'Unknown error'));
                        return [
                            'error'   => true,
                            'message' => $data['message'] ?? 'Gagal mendapatkan prediksi harga.',
                        ];
                    }
                } else {
                    $statusCode = $response->status();
                    $errorBody = $response->body();

                    Log::warning("HTTP {$statusCode} error for price prediction {$projectId}: {$errorBody}");

                    // PERBAIKAN: Handle specific HTTP errors
                    if ($statusCode === 504 || $statusCode === 408) {
                        return [
                            'error'   => true,
                            'message' => 'Prediksi harga membutuhkan waktu lebih lama dari biasanya. Silakan coba lagi dalam beberapa menit.',
                            'timeout' => true
                        ];
                    } else {
                        return [
                            'error'   => true,
                            'message' => "Gagal mendapatkan prediksi harga (HTTP {$statusCode}). Coba lagi nanti.",
                        ];
                    }
                }
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Log::error("Connection timeout for price prediction {$projectId}: " . $e->getMessage());
                return [
                    'error'   => true,
                    'message' => 'Koneksi timeout. Prediksi harga mungkin membutuhkan waktu lebih lama. Silakan coba lagi.',
                    'timeout' => true
                ];
            } catch (\Exception $e) {
                Log::error("Error mendapatkan prediksi harga {$projectId}: " . $e->getMessage());
                return [
                    'error'   => true,
                    'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                ];
            }
        });
    }
}

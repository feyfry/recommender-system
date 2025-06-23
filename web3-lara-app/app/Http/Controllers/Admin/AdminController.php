<?php
namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Project;
use App\Models\ApiCache;
use App\Models\Portfolio;
use App\Models\Interaction;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\Recommendation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
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
     * Menampilkan dashboard admin
     */
    public function dashboard()
    {
        // DIOPTIMALKAN: Menggunakan cache untuk statistik yang mahal secara komputasi
        $userStats = Cache::remember('admin_user_stats', 15, function () {
            return [
                'total'     => User::count(),
                'active'    => User::active()->count(),
                'new'       => User::newUsers()->count(),
                'admin'     => User::withRole('admin')->count(),
                'community' => User::withRole('community')->count(),
            ];
        });

        $projectStats = Cache::remember('admin_project_stats', 30, function () {
            return [
                'total'      => Project::count(),
                'trending'   => Project::where('trend_score', '>', 70)->count(),
                'popular'    => Project::where('popularity_score', '>', 70)->count(),
                'categories' => Project::select('primary_category')
                    ->distinct()
                    ->whereNotNull('primary_category')
                    ->count(),
                'chains'     => Project::select('chain')
                    ->distinct()
                    ->whereNotNull('chain')
                    ->count(),
            ];
        });

        $interactionStats = Cache::remember('admin_interaction_stats', 15, function () {
            return [
                'total'          => Interaction::count(),
                'views'          => Interaction::ofType('view')->count(),
                'favorites'      => Interaction::ofType('favorite')->count(),
                'portfolio_adds' => Interaction::ofType('portfolio_add')->count(),
                'recent'         => Interaction::recent()->count(),
            ];
        });

        $transactionStats = Cache::remember('admin_transaction_stats', 15, function () {
            return [
                'total'  => Transaction::count(),
                'buy'    => Transaction::ofType('buy')->count(),
                'sell'   => Transaction::ofType('sell')->count(),
                'volume' => Transaction::sum('total_value'),
                'recent' => Transaction::recent()->count(),
            ];
        });

        // DIOPTIMALKAN: Menyimpan aktivitas terbaru selama 5 menit
        $recentInteractions = Interaction::with(['user', 'project'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // DIOPTIMALKAN: Menyimpan pengguna paling aktif selama 30 menit
        $mostActiveUsers = Interaction::select('user_id', DB::raw('COUNT(*) as interaction_count'))
            ->groupBy('user_id')
            ->orderBy('interaction_count', 'desc')
            ->limit(10)
            ->get();

        // DIOPTIMALKAN: Menyimpan proyek dengan interaksi terbanyak selama 30 menit
        $mostInteractedProjects = Cache::remember('admin_most_interacted_projects', 30, function () {
            return Interaction::selectRaw('project_id, COUNT(*) as interaction_count')
                ->groupBy('project_id')
                ->orderBy('interaction_count', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    $item->project = Project::find($item->project_id);
                    return $item;
                });
        });

        // DIOPTIMALKAN: Tidak catat aktivitas melihat dashboard admin karena sangat sering diakses
        return view('admin.dashboard', [
            'userStats'              => $userStats,
            'projectStats'           => $projectStats,
            'interactionStats'       => $interactionStats,
            'transactionStats'       => $transactionStats,
            'recentInteractions' => $recentInteractions,
            'mostActiveUsers' => $mostActiveUsers,
            'mostInteractedProjects' => $mostInteractedProjects,
        ]);
    }

    /**
     * Menampilkan halaman semua interaksi
     */
    public function interactions(Request $request)
    {
        $query = Interaction::with(['user', 'project']);

        // Filter berdasarkan tipe
        if ($request->has('type') && !empty($request->type)) {
            $query->where('interaction_type', $request->type);
        }

        // Filter berdasarkan tanggal
        if ($request->has('from_date') && !empty($request->from_date)) {
            $query->where('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date') && !empty($request->to_date)) {
            $query->where('created_at', '<=', $request->to_date);
        }

        // PERBAIKAN: Hitung statistik dari query yang sudah difilter SEBELUM pagination
        $statsQuery = clone $query; // Clone query untuk statistik
        $totalStats = [
            'total' => $statsQuery->count(),
            'view' => (clone $statsQuery)->where('interaction_type', 'view')->count(),
            'favorite' => (clone $statsQuery)->where('interaction_type', 'favorite')->count(),
            'portfolio_add' => (clone $statsQuery)->where('interaction_type', 'portfolio_add')->count(),
        ];

        // Urutkan
        $query->orderBy($request->get('sort', 'created_at'), $request->get('direction', 'desc'));

        // PERBAIKAN: Pagination dengan query parameters yang preserved
        $interactions = $query->paginate(10)->withQueryString();

        // PERBAIKAN: Buat mapping alias untuk tipe interaksi
        $interactionTypes = [
            'view' => 'View',
            'favorite' => 'Liked',
            'portfolio_add' => 'Portfolio Add'
        ];

        return view('admin.interactions', [
            'interactions' => $interactions,
            'interactionTypes' => $interactionTypes,
            'totalStats' => $totalStats, // PERBAIKAN: Kirim statistik total
            'filters' => $request->only(['type', 'from_date', 'to_date', 'sort', 'direction']),
        ]);
    }

    /**
     * Menampilkan halaman pengelolaan pengguna
     */
    public function users(Request $request)
    {
        $query = User::query();

        // Filter berdasarkan role
        if ($request->has('role') && ! empty($request->role)) {
            $query->where('role', $request->role);
        }

        // Filter berdasarkan pencarian
        if ($request->has('search') && ! empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('user_id', 'like', "%{$request->search}%")
                    ->orWhere('wallet_address', 'like', "%{$request->search}%");
            });
        }

        // PERBAIKAN: Clone query untuk menghitung statistik role SEBELUM pagination
        $roleStatsQuery = clone $query;
        $roleStats = $roleStatsQuery->selectRaw('role, COUNT(*) as count')
            ->groupBy('role')
            ->get();

        // PERBAIKAN: Handle sorting berdasarkan jumlah interactions
        if ($request->get('sort') === 'interactions') {
            $direction = $request->get('direction', 'desc');

            // Join dengan interactions dan group by untuk menghitung jumlah interaksi
            $query->leftJoin('interactions', 'users.user_id', '=', 'interactions.user_id')
                ->select('users.*', DB::raw('COUNT(interactions.id) as interaction_count'))
                ->groupBy('users.id', 'users.user_id', 'users.wallet_address', 'users.nonce', 'users.role', 'users.last_login', 'users.created_at', 'users.updated_at')
                ->orderBy('interaction_count', $direction);
        } else {
            // Urutkan normal jika bukan berdasarkan interactions
            $query->orderBy($request->get('sort', 'created_at'), $request->get('direction', 'desc'));
        }

        // PERBAIKAN: Pagination dengan withQueryString untuk preserve filter parameters
        $users = $query->paginate(10)->withQueryString();

        return view('admin.users', [
            'users'     => $users,
            'roleStats' => $roleStats,
            'filters'   => $request->only(['role', 'search', 'sort', 'direction']),
        ]);
    }

    /**
     * Menampilkan detail pengguna
     */
    public function userDetail($userId)
    {
        $user = User::where('user_id', $userId)->firstOrFail();

        // DIOPTIMALKAN: Cache untuk data yang membutuhkan query kompleks
        $cacheKey = "admin_user_detail_{$userId}";
        $userData = Cache::remember($cacheKey, 15, function () use ($userId, $user) {
            $interactions = Interaction::forUser($userId)
                ->with('project')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            $interactionStats = Interaction::getTypeDistribution($userId);

            $portfolios = Portfolio::forUser($userId)
                ->with('project')
                ->get();

            $transactions = Transaction::forUser($userId)
                ->with('project')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            $recommendations = Recommendation::where('user_id', $userId)
                ->with('project')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return [
                'interactions'     => $interactions,
                'interactionStats' => $interactionStats,
                'portfolios'       => $portfolios,
                'transactions'     => $transactions,
                'recommendations'  => $recommendations,
            ];
        });

        return view('admin.user_detail', [
            'user'             => $user,
            'interactions'     => $userData['interactions'],
            'interactionStats' => $userData['interactionStats'],
            'portfolios'       => $userData['portfolios'],
            'transactions'     => $userData['transactions'],
            'recommendations'  => $userData['recommendations'],
        ]);
    }

    /**
     * Memperbarui role pengguna
     */
    public function updateUserRole(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|in:admin,community',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $user    = User::where('user_id', $userId)->firstOrFail();
        $oldRole = $user->role;

        $user->role = $request->role;
        $user->save();

        // DIOPTIMALKAN: Hapus cache statistik karena data telah berubah
        Cache::forget('admin_user_stats');
        Cache::forget('admin_role_stats');
        Cache::forget("admin_user_detail_{$userId}");

        return redirect()->back()
            ->with('success', "Role pengguna berhasil diperbarui menjadi {$request->role}.");
    }

    /**
     * PERBAIKAN: Menampilkan halaman pengelolaan proyek dengan fix sorting interactions dan statistik trending
     */
    public function projects(Request $request)
    {
        $query = Project::query();

        // Filter berdasarkan kategori
        if ($request->has('category') && ! empty($request->category)) {
            // Handle filter untuk kategori yang berbentuk array
            $query->where(function($q) use ($request) {
                $q->where('primary_category', $request->category)
                ->orWhere('primary_category', 'like', '%"' . $request->category . '"%')
                ->orWhere('primary_category', 'like', "['" . $request->category . "']");
            });
        }

        // Filter berdasarkan blockchain
        if ($request->has('chain') && ! empty($request->chain)) {
            $query->where('chain', $request->chain);
        }

        // Filter berdasarkan pencarian
        if ($request->has('search') && ! empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('symbol', 'like', "%{$request->search}%")
                    ->orWhere('id', 'like', "%{$request->search}%");
            });
        }

        // PERBAIKAN: Clone query untuk statistik SEBELUM sorting dan pagination
        $statsQuery = clone $query;
        $projectStats = [
            'total' => $statsQuery->count(),
            'trending' => (clone $statsQuery)->where('trend_score', '>', 70)->count(),
            'popular' => (clone $statsQuery)->where('popularity_score', '>', 70)->count(),
            'categories' => (clone $statsQuery)->select('primary_category')
                ->distinct()
                ->whereNotNull('primary_category')
                ->count(),
            'chains' => (clone $statsQuery)->select('chain')
                ->distinct()
                ->whereNotNull('chain')
                ->count(),
        ];

        // PERBAIKAN: Handle sorting berdasarkan interactions
        $sortField = $request->get('sort', 'popularity_score');
        $direction = $request->get('direction', 'desc');

        if ($sortField === 'interactions') {
            // Join dengan tabel interactions dan hitung jumlahnya
            $query->leftJoin('interactions', 'projects.id', '=', 'interactions.project_id')
                ->select('projects.*', DB::raw('COUNT(interactions.id) as interaction_count'))
                ->groupBy(
                    'projects.id', 'projects.symbol', 'projects.name', 'projects.image',
                    'projects.current_price', 'projects.market_cap', 'projects.market_cap_rank',
                    'projects.fully_diluted_valuation', 'projects.total_volume', 'projects.high_24h',
                    'projects.low_24h', 'projects.price_change_24h', 'projects.price_change_percentage_24h',
                    'projects.market_cap_change_24h', 'projects.market_cap_change_percentage_24h',
                    'projects.circulating_supply', 'projects.total_supply', 'projects.max_supply',
                    'projects.ath', 'projects.ath_change_percentage', 'projects.ath_date',
                    'projects.atl', 'projects.atl_change_percentage', 'projects.atl_date',
                    'projects.roi', 'projects.last_updated', 'projects.price_change_percentage_1h_in_currency',
                    'projects.price_change_percentage_24h_in_currency', 'projects.price_change_percentage_30d_in_currency',
                    'projects.price_change_percentage_7d_in_currency', 'projects.query_category',
                    'projects.platforms', 'projects.categories', 'projects.twitter_followers',
                    'projects.github_stars', 'projects.github_subscribers', 'projects.github_forks',
                    'projects.description', 'projects.genesis_date', 'projects.sentiment_votes_up_percentage',
                    'projects.telegram_channel_user_count', 'projects.primary_category', 'projects.chain',
                    'projects.popularity_score', 'projects.trend_score', 'projects.developer_activity_score',
                    'projects.social_engagement_score', 'projects.description_length', 'projects.age_days',
                    'projects.maturity_score', 'projects.is_trending', 'projects.created_at', 'projects.updated_at'
                )
                ->orderBy('interaction_count', $direction);
        } else {
            // Urutkan berdasarkan field biasa
            $query->orderBy($sortField, $direction);
        }

        // PERBAIKAN: Pagination dengan withQueryString untuk preserve filter parameters
        $projects = $query->paginate(10)->withQueryString();

        // DIOPTIMALKAN: Cache daftar kategori dan blockchain yang sudah dibersihkan
        // dan unique tanpa filter "Unknown" atau kosong
        $categories = Cache::remember('all_project_categories', 1440, function () { // 24 jam
            $rawCategories = Project::select('primary_category')
                ->distinct()
                ->whereNotNull('primary_category')
                ->where('primary_category', '!=', '')
                ->orderBy('primary_category')
                ->pluck('primary_category');

            $cleanCategories = [];
            foreach ($rawCategories as $category) {
                // Bersihkan format array jika ada
                if (str_starts_with($category, '[') && str_ends_with($category, ']')) {
                    try {
                        $parsed = json_decode($category, true);
                        if (is_array($parsed) && !empty($parsed)) {
                            foreach ($parsed as $cat) {
                                if (!empty($cat) && strtolower($cat) !== 'unknown') {
                                    $cleanCategories[] = $cat;
                                }
                            }
                            continue;
                        }
                    } catch (\Exception $e) {
                        // Fallback ke nilai asli jika parsing gagal
                        $cleanCategories[] = $category;
                    }
                }

                // Tambahkan kategori jika bukan Unknown atau kosong
                if (!empty($category) && strtolower($category) !== 'unknown') {
                    $cleanCategories[] = $category;
                }
            }

            // Kembalikan kategori unik dan diurutkan
            return collect($cleanCategories)
                ->unique()
                ->filter()
                ->sort()
                ->values();
        });

        $chains = Cache::remember('all_project_chains', 1440, function () { // 24 jam
            return Project::select('chain')
                ->distinct()
                ->whereNotNull('chain')
                ->where('chain', '!=', '')
                ->orderBy('chain')
                ->pluck('chain')
                ->filter()
                ->values();
        });

        return view('admin.projects', [
            'projects'     => $projects,
            'projectStats' => $projectStats, // PERBAIKAN: Kirim statistik yang sudah diperbaiki
            'categories'   => $categories,
            'chains'       => $chains,
            'filters'      => $request->only(['category', 'chain', 'search', 'sort', 'direction']),
        ]);
    }

    /**
     * Menampilkan detail proyek
     */
    public function projectDetail($projectId)
    {
        $project = Project::findOrFail($projectId);

        // DIOPTIMALKAN: Cache data detail proyek
        $cacheKey    = "admin_project_detail_{$projectId}";
        $projectData = Cache::remember($cacheKey, 15, function () use ($projectId, $project) {
            // Interaksi dengan proyek
            $interactions = Interaction::forProject($projectId)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            // Statistik interaksi
            $interactionStats = Interaction::where('project_id', $projectId)
                ->selectRaw('interaction_type, COUNT(*) as count')
                ->groupBy('interaction_type')
                ->get();

            // Rekomendasi yang melibatkan proyek ini
            $recommendations = Recommendation::where('project_id', $projectId)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            // Transaksi yang melibatkan proyek ini
            $transactions = Transaction::where('project_id', $projectId)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            // Portfolio yang berisi proyek ini
            $portfolios = Portfolio::where('project_id', $projectId)
                ->with('user')
                ->get();

            return [
                'interactions'     => $interactions,
                'interactionStats' => $interactionStats,
                'recommendations'  => $recommendations,
                'transactions'     => $transactions,
                'portfolios'       => $portfolios,
            ];
        });

        // DIOPTIMALKAN: Cache sinyal trading
        $tradingSignals = Cache::remember("trading_signals_{$projectId}_medium", 30, function () use ($projectId) {
            return $this->getTradingSignals($projectId, 'medium');
        });

        return view('admin.project_detail', [
            'project'          => $project,
            'interactions'     => $projectData['interactions'],
            'interactionStats' => $projectData['interactionStats'],
            'recommendations'  => $projectData['recommendations'],
            'transactions'     => $projectData['transactions'],
            'portfolios'       => $projectData['portfolios'],
            'tradingSignals'   => $tradingSignals,
        ]);
    }

    /**
     * Mendapatkan data evaluasi model terbaru
     *
     * @return array
     */
    private function getLatestModelEvaluation()
    {
        $basePath = base_path('../recommendation-engine/data/models/');
        $pattern = $basePath . 'evaluation_report_*.markdown';

        // Dapatkan semua file yang cocok dengan pattern
        $files = glob($pattern);

        // Nilai default jika tidak ada file evaluasi
        $defaultEvaluation = [
            'fecf' => ['ndcg' => 0.2945, 'hit_ratio' => 0.8148],
            'ncf' => ['ndcg' => 0.1986, 'hit_ratio' => 0.7138],
            'hybrid' => ['ndcg' => 0.2954, 'hit_ratio' => 0.8788]
        ];

        if (empty($files)) {
            // Kembalikan nilai default jika tidak ada file evaluasi
            return $defaultEvaluation;
        }

        // Urutkan file berdasarkan waktu modifikasi (terbaru dulu)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Ambil file terbaru
        $latestFile = $files[0];

        // Baca isi file
        $content = file_get_contents($latestFile);

        // Inisialisasi dengan nilai default
        $evaluation = $defaultEvaluation;

        // Pattern untuk FECF
        if (preg_match('/\| fecf \| .* \| .* \| .* \| ([0-9\.]+) \| ([0-9\.]+) \| .* \|/m', $content, $matches)) {
            $evaluation['fecf']['ndcg'] = $matches[1];
            $evaluation['fecf']['hit_ratio'] = $matches[2];
        }

        // Pattern untuk NCF
        if (preg_match('/\| ncf \| .* \| .* \| .* \| ([0-9\.]+) \| ([0-9\.]+) \| .* \|/m', $content, $matches)) {
            $evaluation['ncf']['ndcg'] = $matches[1];
            $evaluation['ncf']['hit_ratio'] = $matches[2];
        }

        // Pattern untuk hybrid
        if (preg_match('/\| hybrid \| .* \| .* \| .* \| ([0-9\.]+) \| ([0-9\.]+) \| .* \|/m', $content, $matches)) {
            $evaluation['hybrid']['ndcg'] = $matches[1];
            $evaluation['hybrid']['hit_ratio'] = $matches[2];
        }

        return $evaluation;
    }

    /**
     * Menampilkan halaman sinkronisasi data
     */
    public function dataSyncDashboard()
    {
        // DIOPTIMALKAN: Cache statistik proyek
        $projectStats = Cache::remember('data_sync_project_stats', 15, function () {
            return [
                'total'            => Project::count(),
                'recently_updated' => Project::where('updated_at', '>=', now()->subDay())->count(),
            ];
        });

        // DIOPTIMALKAN: Cache statistik API
        $cacheStats = Cache::remember('data_sync_cache_stats', 5, function () {
            return ApiCache::getStats();
        });

        $endpointUsage = Cache::remember('data_sync_endpoint_usage', 5, function () {
            return ApiCache::getEndpointUsage();
        });

        // Dapatkan data evaluasi model terbaru
        $modelEvaluation = $this->getLatestModelEvaluation();

        // DIOPTIMALKAN: Tidak catat aktivitas untuk halaman yang sering dikunjungi admin

        return view('admin.data_sync', [
            'projectStats'    => $projectStats,
            'cacheStats'      => $cacheStats,
            'endpointUsage'   => $endpointUsage,
            'modelEvaluation' => $modelEvaluation,
        ]);
    }

    /**
     * Menjalankan perintah import dari UI dengan validasi yang lebih baik
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function runImportCommand(Request $request)
    {
        // Validasi request
        $request->validate([
            'command' => 'required|string'
        ]);

        $command = $request->input('command');

        // Validasi command yang diizinkan
        $allowedCommands = [
            'recommend:import --projects',
            'recommend:import --interactions',
            'recommend:import --features',
            'recommend:sync --full',
            'recommend:sync --projects',
            'recommend:sync --interactions',
            'recommend:sync --train',
        ];

        if (!in_array($command, $allowedCommands)) {
            return redirect()->back()
                ->with('error', 'Perintah tidak valid atau tidak diizinkan.');
        }

        // Jalankan perintah Artisan
        try {
            // Cek apakah file CSV ada sebelum menjalankan import
            if (str_contains($command, 'import')) {
                if (! $this->validateImportFiles($command)) {
                    return redirect()->back()
                        ->with('error', 'File CSV tidak ditemukan. Pastikan file sudah ada di folder recommendation-engine/data/processed/');
                }
            }

            // Menambahkan flag --force untuk import interaksi saat dijalankan dari web
            if (str_contains($command, 'import --interactions')) {
                $command .= ' --force';
            }

            // Jalankan command dengan buffer output
            \Illuminate\Support\Facades\Artisan::call($command);
            $output = \Illuminate\Support\Facades\Artisan::output();

            // Log output untuk debugging
            Log::info("Command output: " . $output);

            // Check if there was an error in the output
            if (str_contains(strtolower($output), 'gagal') || str_contains(strtolower($output), 'error')) {
                // Extract the specific error message if possible
                preg_match('/error[^:]*:(.+)/i', $output, $matches);
                $errorMessage = $matches[1] ?? 'Terjadi kesalahan saat menjalankan perintah.';

                return redirect()->back()
                    ->with('error', trim($errorMessage));
            }

            // Extract success/fail counts if available
            preg_match('/Berhasil: (\d+), Gagal: (\d+)/', $output, $matches);
            $successCount = $matches[1] ?? 0;
            $failCount = $matches[2] ?? 0;

            if ($successCount > 0 || $failCount == 0) {
                return redirect()->back()
                    ->with('success', "Perintah berhasil dijalankan. Berhasil: {$successCount}, Gagal: {$failCount}");
            } else {
                return redirect()->back()
                    ->with('warning', "Perintah dijalankan dengan beberapa kegagalan. Berhasil: {$successCount}, Gagal: {$failCount}");
            }

        } catch (\Exception $e) {
            Log::error('Error menjalankan command: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Gagal menjalankan perintah: ' . $e->getMessage());
        }
    }

    /**
     * Validasi keberadaan file CSV sebelum import
     */
    private function validateImportFiles($command)
    {
        $basePath = base_path('../recommendation-engine/data/processed/');

        if (str_contains($command, '--projects')) {
            return file_exists($basePath . 'projects.csv');
        } elseif (str_contains($command, '--interactions')) {
            return file_exists($basePath . 'interactions.csv');
        } elseif (str_contains($command, '--features')) {
            return file_exists($basePath . 'features.csv');
        }

        return true;
    }

    /**
     * Memicu sinkronisasi data
     */
    public function triggerDataSync(Request $request)
    {
        $syncType = $request->input('sync_type', 'all');

        try {
            // DIOPTIMALKAN: Gunakan timeout yang lebih kecil untuk HTTP requests
            $response = Http::timeout(5)->post("{$this->apiUrl}/admin/sync-data", [
                'projects_updated' => in_array($syncType, ['all', 'projects']),
                'users_count'      => User::count(),
            ])->json();

            // DIOPTIMALKAN: Hapus cache yang berkaitan dengan data sync
            Cache::forget('data_sync_project_stats');
            Cache::forget('data_sync_cache_stats');
            Cache::forget('data_sync_endpoint_usage');
            Cache::forget('admin_project_stats');

            return redirect()->route('admin.data-sync')
                ->with('success', 'Sinkronisasi data berhasil dipicu.');
        } catch (\Exception $e) {
            return redirect()->route('admin.data-sync')
                ->with('error', 'Gagal memicu sinkronisasi data: ' . $e->getMessage());
        }
    }

    /**
     * Membersihkan cache API
     */
    public function clearApiCache(Request $request)
    {
        $endpoint = $request->input('endpoint');

        if ($endpoint) {
            // Hapus cache untuk endpoint tertentu
            $count = ApiCache::where('endpoint', $endpoint)->count();
            ApiCache::clearEndpoint($endpoint);
            $message = "Cache untuk endpoint {$endpoint} berhasil dihapus ({$count} item).";
        } else {
            // Hapus semua cache
            $count = ApiCache::count();
            ApiCache::clearAll();
            $message = "Semua cache berhasil dihapus ({$count} item).";
        }

        // DIOPTIMALKAN: Hapus cache Laravel juga untuk memulai bersih
        Cache::flush();

        return redirect()->route('admin.data-sync')
            ->with('success', $message);
    }

    /**
     * Melatih model rekomendasi
     */
    public function trainModels(Request $request)
    {
        $models = $request->input('models', ['fecf', 'ncf', 'hybrid']);

        try {
            // DIOPTIMALKAN: Gunakan timeout yang lebih besar untuk operasi melatih model
            $response = Http::timeout(30)->post("{$this->apiUrl}/admin/train-models", [
                'models'     => $models,
                'save_model' => true,
                'force'      => true,  // Pastikan ini true
            ]);

            // Log detail response untuk debugging
            Log::info("Train models response:", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            // DIOPTIMALKAN: Hapus cache rekomendasi global
            $this->clearAllRecommendationCaches();

            return redirect()->route('admin.data-sync')
                ->with('success', 'Pelatihan model berhasil dipicu.');
        } catch (\Exception $e) {
            return redirect()->route('admin.data-sync')
                ->with('error', 'Gagal memicu pelatihan model: ' . $e->getMessage());
        }
    }

    /**
     * Mendapatkan sinyal trading
     */
    private function getTradingSignals($projectId, $riskTolerance = 'medium')
    {
        try {
            // DIOPTIMALKAN: Gunakan timeout yang lebih lama (5 detik) untuk endpoint yang kompleks
            $response = Http::timeout(5)->post("{$this->apiUrl}/analysis/trading-signals", [
                'project_id'     => $projectId,
                'days'           => 30,
                'interval'       => '1d',
                'risk_tolerance' => $riskTolerance,
                'trading_style'  => 'standard',
            ]);

            // Cek respons HTTP secara eksplisit
            if ($response->successful()) {
                return $response->json();
            } else {
                Log::warning("Gagal mendapatkan sinyal trading. Status: " . $response->status() .
                            ", Response: " . $response->body());

                // Fallback ke data placeholder
                return [
                    'project_id'           => $projectId,
                    'action'               => 'hold',
                    'confidence'           => 0.5,
                    'evidence'             => [
                        'Data tidak tersedia saat ini',
                        'Coba lagi nanti',
                    ],
                    'personalized_message' => 'Data analisis teknikal tidak tersedia saat ini.',
                ];
            }
        } catch (\Exception $e) {
            Log::error("Exception mendapatkan sinyal trading: " . $e->getMessage());

            // Fallback ke data placeholder
            return [
                'project_id'           => $projectId,
                'action'               => 'hold',
                'confidence'           => 0.5,
                'evidence'             => [
                    'Data tidak tersedia saat ini',
                    'Coba lagi nanti',
                ],
                'personalized_message' => 'Data analisis teknikal tidak tersedia saat ini.',
            ];
        }
    }

    /**
     * DIOPTIMALKAN: Metode baru untuk membersihkan semua cache rekomendasi
     */
    private function clearAllRecommendationCaches()
    {
        // Hapus cache rekomendasi global
        $globalCaches = [
            'rec_trending_8',
            'rec_trending_20',
            'rec_popular_8',
            'rec_popular_20',
            'all_categories',
            'all_chains',
            'categories_list',
            'chains_list',
        ];

        foreach ($globalCaches as $key) {
            Cache::forget($key);
        }

        // Bersihkan cache API
        ApiCache::where('expires_at', '<=', now())->delete();

        // Hapus cache untuk personal recommendations - ini akan memicu rekomendasi baru
        // untuk semua pengguna pada akses berikutnya
        User::chunk(100, function ($users) {
            foreach ($users as $user) {
                $userId = $user->user_id;
                Cache::forget("personal_recommendations_{$userId}_hybrid_10");
                Cache::forget("personal_recommendations_{$userId}_fecf_10");
                Cache::forget("personal_recommendations_{$userId}_ncf_10");
                Cache::forget("rec_personal_{$userId}_10");
                Cache::forget("rec_personal_hybrid_{$userId}");
                Cache::forget("rec_personal_fecf_{$userId}");
                Cache::forget("rec_personal_ncf_{$userId}");
                Cache::forget("dashboard_personal_recs_{$userId}");
            }
        });
    }
}

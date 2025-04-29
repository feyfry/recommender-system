<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ApiCache;
use App\Models\Interaction;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\Recommendation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        $this->apiUrl = env('RECOMMENDATION_API_URL', 'http://localhost:8000');
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
        $recentActivity = Cache::remember('admin_recent_activity', 5, function () {
            return ActivityLog::with('user')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();
        });

        // DIOPTIMALKAN: Menyimpan pengguna paling aktif selama 30 menit
        $mostActiveUsers = Cache::remember('admin_most_active_users', 30, function () {
            return ActivityLog::getMostActiveUsers(10);
        });

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
            'recentActivity'         => $recentActivity,
            'mostActiveUsers'        => $mostActiveUsers,
            'mostInteractedProjects' => $mostInteractedProjects,
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

        // Urutkan
        $query->orderBy($request->get('sort', 'created_at'), $request->get('direction', 'desc'));

        // Pagination
        $users = $query->paginate(20);

        // DIOPTIMALKAN: Menyimpan statistik role
        $roleStats = Cache::remember('admin_role_stats', 60, function () {
            return User::selectRaw('role, COUNT(*) as count')
                ->groupBy('role')
                ->get();
        });

        // DIOPTIMALKAN: Tidak catat aktivitas melihat halaman users admin

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
                ->limit(50)
                ->get();

            $interactionStats = Interaction::getTypeDistribution($userId);

            $portfolios = Portfolio::forUser($userId)
                ->with('project')
                ->get();

            $transactions = Transaction::forUser($userId)
                ->with('project')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            $activities = ActivityLog::forUser($userId)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            $recommendations = Recommendation::where('user_id', $userId)
                ->with('project')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            return [
                'interactions'     => $interactions,
                'interactionStats' => $interactionStats,
                'portfolios'       => $portfolios,
                'transactions'     => $transactions,
                'activities'       => $activities,
                'recommendations'  => $recommendations,
            ];
        });

        // Catat aktivitas - detail pengguna adalah aktivitas yang ingin dicatat karena penting
        ActivityLog::logAdminAction(Auth::user(), request(), 'view_user_detail', "User: {$userId}");

        return view('admin.user_detail', [
            'user'             => $user,
            'interactions'     => $userData['interactions'],
            'interactionStats' => $userData['interactionStats'],
            'portfolios'       => $userData['portfolios'],
            'transactions'     => $userData['transactions'],
            'activities'       => $userData['activities'],
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

        // Catat aktivitas - perubahan role adalah aktivitas penting untuk dicatat
        ActivityLog::logAdminAction(Auth::user(), request(), 'update_user_role', "User: {$userId}, Role: {$oldRole} -> {$request->role}");

        // DIOPTIMALKAN: Hapus cache statistik karena data telah berubah
        Cache::forget('admin_user_stats');
        Cache::forget('admin_role_stats');
        Cache::forget("admin_user_detail_{$userId}");

        return redirect()->back()
            ->with('success', "Role pengguna berhasil diperbarui menjadi {$request->role}.");
    }

    /**
     * Menampilkan halaman pengelolaan proyek
     */
    public function projects(Request $request)
    {
        $query = Project::query();

        // Filter berdasarkan kategori
        if ($request->has('category') && ! empty($request->category)) {
            $query->where('primary_category', $request->category);
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

        // Urutkan
        $sortField = $request->get('sort', 'popularity_score');
        $direction = $request->get('direction', 'desc');
        $query->orderBy($sortField, $direction);

        // Pagination
        $projects = $query->paginate(20);

                                                                                    // DIOPTIMALKAN: Cache daftar kategori dan blockchain
        $categories = Cache::remember('all_project_categories', 1440, function () { // 24 jam
            return Project::select('primary_category')
                ->distinct()
                ->whereNotNull('primary_category')
                ->orderBy('primary_category')
                ->pluck('primary_category');
        });

        $chains = Cache::remember('all_project_chains', 1440, function () { // 24 jam
            return Project::select('chain')
                ->distinct()
                ->whereNotNull('chain')
                ->orderBy('chain')
                ->pluck('chain');
        });

        // DIOPTIMALKAN: Tidak catat aktivitas untuk page view admin yang sering diakses

        return view('admin.projects', [
            'projects'   => $projects,
            'categories' => $categories,
            'chains'     => $chains,
            'filters'    => $request->only(['category', 'chain', 'search', 'sort', 'direction']),
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
                ->limit(50)
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
                ->limit(20)
                ->get();

            // Transaksi yang melibatkan proyek ini
            $transactions = Transaction::where('project_id', $projectId)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->limit(20)
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

        // Catat aktivitas - detail proyek adalah aktivitas yang ingin dicatat
        ActivityLog::logAdminAction(Auth::user(), request(), 'view_project_detail', "Project: {$projectId}");

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

        // DIOPTIMALKAN: Tidak catat aktivitas untuk halaman yang sering dikunjungi admin

        return view('admin.data_sync', [
            'projectStats'  => $projectStats,
            'cacheStats'    => $cacheStats,
            'endpointUsage' => $endpointUsage,
        ]);
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

            // Log aktivitas - ini adalah aktivitas penting untuk sistem
            ActivityLog::logAdminAction(Auth::user(), request(), 'trigger_data_sync', "Type: {$syncType}");

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

        // Log aktivitas - ini adalah aktivitas penting untuk sistem
        ActivityLog::logAdminAction(Auth::user(), request(), 'clear_api_cache', $endpoint ? "Endpoint: {$endpoint}" : "All cache");

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
            ])->json();

            // Log aktivitas - ini adalah aktivitas penting untuk sistem
            ActivityLog::logAdminAction(Auth::user(), request(), 'train_models', "Models: " . implode(', ', $models));

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
     * Menampilkan log aktivitas
     */
    public function activityLogs(Request $request)
    {
        $query = ActivityLog::query();

        // Filter berdasarkan tipe aktivitas
        if ($request->has('activity_type') && ! empty($request->activity_type)) {
            $query->where('activity_type', $request->activity_type);
        }

        // Filter berdasarkan pengguna
        if ($request->has('user_id') && ! empty($request->user_id)) {
            $query->where('user_id', $request->user_id);
        }

        // Filter berdasarkan tanggal
        if ($request->has('date_from') && ! empty($request->date_from)) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && ! empty($request->date_to)) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        // Urutkan
        $query->orderBy('created_at', 'desc');

        // Pagination
        $logs = $query->with('user')->paginate(50);

                                                                                 // DIOPTIMALKAN: Cache daftar tipe aktivitas
        $activityTypes = Cache::remember('all_activity_types', 60, function () { // 1 jam
            return ActivityLog::select('activity_type')
                ->distinct()
                ->orderBy('activity_type')
                ->pluck('activity_type');
        });

        // DIOPTIMALKAN: Tidak catat melihat log sebagai aktivitas (akan redundan)

        return view('admin.activity_logs', [
            'logs'          => $logs,
            'activityTypes' => $activityTypes,
            'filters'       => $request->only(['activity_type', 'user_id', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Mendapatkan sinyal trading
     */
    private function getTradingSignals($projectId, $riskTolerance = 'medium')
    {
        try {
            // DIOPTIMALKAN: Gunakan timeout
            $response = Http::timeout(3)->post("{$this->apiUrl}/analysis/trading-signals", [
                'project_id'     => $projectId,
                'days'           => 30,
                'interval'       => '1d',
                'risk_tolerance' => $riskTolerance,
                'trading_style'  => 'standard',
            ])->json();

            return $response;
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan sinyal trading: " . $e->getMessage());

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

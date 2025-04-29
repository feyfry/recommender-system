<?php
namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Project;
use App\Models\ApiCache;
use App\Models\Portfolio;
use App\Models\ActivityLog;
use App\Models\Interaction;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\Recommendation;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
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
        // Statistik pengguna
        $userStats = [
            'total'     => User::count(),
            'active'    => User::active()->count(),
            'new'       => User::newUsers()->count(),
            'admin'     => User::withRole('admin')->count(),
            'community' => User::withRole('community')->count(),
        ];

        // Statistik proyek
        $projectStats = [
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

        // Statistik interaksi
        $interactionStats = [
            'total'          => Interaction::count(),
            'views'          => Interaction::ofType('view')->count(),
            'favorites'      => Interaction::ofType('favorite')->count(),
            'portfolio_adds' => Interaction::ofType('portfolio_add')->count(),
            'recent'         => Interaction::recent()->count(),
        ];

        // Statistik transaksi
        $transactionStats = [
            'total'  => Transaction::count(),
            'buy'    => Transaction::ofType('buy')->count(),
            'sell'   => Transaction::ofType('sell')->count(),
            'volume' => Transaction::sum('total_value'),
            'recent' => Transaction::recent()->count(),
        ];

        // Aktivitas terbaru
        $recentActivity = ActivityLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Pengguna paling aktif
        $mostActiveUsers = ActivityLog::getMostActiveUsers(10);

        // Proyek dengan interaksi terbanyak
        $mostInteractedProjects = Interaction::selectRaw('project_id, COUNT(*) as interaction_count')
            ->groupBy('project_id')
            ->orderBy('interaction_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                $item->project = Project::find($item->project_id);
                return $item;
            });

        // Catat aktivitas
        ActivityLog::logAdminAction(Auth::user(), request(), 'view_dashboard');

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

        // Statistik role
        $roleStats = User::selectRaw('role, COUNT(*) as count')
            ->groupBy('role')
            ->get();

        // Catat aktivitas
        ActivityLog::logAdminAction(Auth::user(), request(), 'view_users');

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

        // Interaksi pengguna
        $interactions = Interaction::forUser($userId)
            ->with('project')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // Statistik interaksi
        $interactionStats = Interaction::getTypeDistribution($userId);

        // Portfolio
        $portfolios = Portfolio::forUser($userId)
            ->with('project')
            ->get();

        // Transaksi
        $transactions = Transaction::forUser($userId)
            ->with('project')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Aktivitas
        $activities = ActivityLog::forUser($userId)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // Rekomendasi
        $recommendations = Recommendation::where('user_id', $userId)
            ->with('project')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Catat aktivitas
        ActivityLog::logAdminAction(Auth::user(), request(), 'view_user_detail', "User: {$userId}");

        return view('admin.user_detail', [
            'user'             => $user,
            'interactions'     => $interactions,
            'interactionStats' => $interactionStats,
            'portfolios'       => $portfolios,
            'transactions'     => $transactions,
            'activities'       => $activities,
            'recommendations'  => $recommendations,
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

        // Catat aktivitas
        ActivityLog::logAdminAction(Auth::user(), request(), 'update_user_role', "User: {$userId}, Role: {$oldRole} -> {$request->role}");

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

        // Daftar kategori
        $categories = Project::select('primary_category')
            ->distinct()
            ->whereNotNull('primary_category')
            ->orderBy('primary_category')
            ->pluck('primary_category');

        // Daftar blockchain
        $chains = Project::select('chain')
            ->distinct()
            ->whereNotNull('chain')
            ->orderBy('chain')
            ->pluck('chain');

        // Catat aktivitas
        ActivityLog::logAdminAction(Auth::user(), request(), 'view_projects');

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

        // Sinyal trading
        $tradingSignals = $this->getTradingSignals($projectId, 'medium');

        // Catat aktivitas
        ActivityLog::logAdminAction(Auth::user(), request(), 'view_project_detail', "Project: {$projectId}");

        return view('admin.project_detail', [
            'project'          => $project,
            'interactions'     => $interactions,
            'interactionStats' => $interactionStats,
            'recommendations'  => $recommendations,
            'transactions'     => $transactions,
            'portfolios'       => $portfolios,
            'tradingSignals'   => $tradingSignals,
        ]);
    }

    /**
     * Menampilkan halaman sinkronisasi data
     */
    public function dataSyncDashboard()
    {
        // Statistik proyek
        $projectStats = [
            'total'            => Project::count(),
            'recently_updated' => Project::where('updated_at', '>=', now()->subDay())->count(),
        ];

        // Status cache API
        $cacheStats    = ApiCache::getStats();
        $endpointUsage = ApiCache::getEndpointUsage();

        // Catat aktivitas
        ActivityLog::logAdminAction(Auth::user(), request(), 'view_data_sync');

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
            // Panggil API untuk sinkronisasi data
            $response = Http::post("{$this->apiUrl}/admin/sync-data", [
                'projects_updated' => in_array($syncType, ['all', 'projects']),
                'users_count'      => User::count(),
            ])->json();

            // Log aktivitas
            ActivityLog::logAdminAction(Auth::user(), request(), 'trigger_data_sync', "Type: {$syncType}");

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

        // Log aktivitas
        ActivityLog::logAdminAction(Auth::user(), request(), 'clear_api_cache', $endpoint ? "Endpoint: {$endpoint}" : "All cache");

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
            // Panggil API untuk melatih model
            $response = Http::post("{$this->apiUrl}/admin/train-models", [
                'models'     => $models,
                'save_model' => true,
            ])->json();

            // Log aktivitas
            ActivityLog::logAdminAction(Auth::user(), request(), 'train_models', "Models: " . implode(', ', $models));

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

        // Daftar tipe aktivitas
        $activityTypes = ActivityLog::select('activity_type')
            ->distinct()
            ->orderBy('activity_type')
            ->pluck('activity_type');

        // Catat aktivitas
        ActivityLog::logAdminAction(Auth::user(), request(), 'view_activity_logs');

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
            $response = Http::post("{$this->apiUrl}/analysis/trading-signals", [
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
}

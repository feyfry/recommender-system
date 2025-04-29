<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    /**
     * Nama tabel model.
     *
     * @var string
     */
    protected $table = 'activity_logs';

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'activity_type',
        'description',
        'ip_address',
        'user_agent',
    ];

    /**
     * Mendapatkan relasi ke User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Scope untuk filter log berdasarkan pengguna.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope untuk filter log berdasarkan tipe aktivitas.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('activity_type', $type);
    }

    /**
     * Scope untuk log terbaru.
     */
    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * DIOPTIMALKAN: Flag untuk mengontrol apakah akan mencatat aktivitas view/normal
     * Berguna untuk menghindari pencatatan aktivitas yang terlalu sering
     *
     * @var bool
     */
    protected static $shouldLogViews = false;

    /**
     * DIOPTIMALKAN: Toggle apakah akan mencatat aktivitas view biasa
     *
     * @param bool $shouldLog
     */
    public static function shouldLogViewActivities($shouldLog = true)
    {
        self::$shouldLogViews = $shouldLog;
    }

    /**
     * Mencatat aktivitas login.
     */
    public static function logLogin($user, $request)
    {
        return self::create([
            'user_id'       => $user->user_id,
            'activity_type' => 'login',
            'description'   => 'Login dengan wallet ' . substr($user->wallet_address, 0, 10) . '...',
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);
    }

    /**
     * Mencatat aktivitas logout.
     */
    public static function logLogout($user, $request)
    {
        return self::create([
            'user_id'       => $user->user_id,
            'activity_type' => 'logout',
            'description'   => 'Logout dari sistem',
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);
    }

    /**
     * DIOPTIMALKAN: Mencatat aktivitas melihat rekomendasi hanya jika penting.
     */
    public static function logViewRecommendation($user, $request, $recommendationType)
    {
        // Hanya catat aktivitas view jika flag diaktifkan atau untuk tipe tertentu yang lebih penting
        if (!self::$shouldLogViews && $recommendationType === 'dashboard') {
            return null;
        }

        // Tidak catat aktivitas view pada halaman yang sering dikunjungi
        if (in_array($recommendationType, ['trending', 'popular', 'personal'])) {
            return null;
        }

        return self::create([
            'user_id'       => $user->user_id,
            'activity_type' => 'recommendation_view',
            'description'   => "Melihat rekomendasi tipe: {$recommendationType}",
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);
    }

    /**
     * DIOPTIMALKAN: Mencatat aktivitas interaksi dengan project.
     * Hanya mencatat interaksi yang benar-benar penting untuk sistem rekomendasi.
     */
    public static function logInteraction($user, $request, $projectId, $interactionType)
    {
        // Hanya catat interaksi yang benar-benar penting untuk rekomendasi
        // Ini adalah interaksi yang ingin kita analisis dan gunakan dalam engine rekomendasi
        $relevantInteractions = ['view', 'favorite', 'portfolio_add', 'research', 'click'];

        if (!in_array($interactionType, $relevantInteractions)) {
            return null;
        }

        $project = Project::find($projectId);
        $projectName = $project ? "{$project->name} ({$project->symbol})" : "Project ID: {$projectId}";

        return self::create([
            'user_id'       => $user->user_id,
            'activity_type' => 'project_interaction',
            'description'   => "Interaksi '{$interactionType}' dengan {$projectName}",
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);
    }

    /**
     * Mencatat aktivitas transaksi.
     */
    public static function logTransaction($user, $request, $transaction)
    {
        $projectName   = $transaction->project->name ?? "Unknown";
        $projectSymbol = $transaction->project->symbol ?? "Unknown";
        $type          = ucfirst($transaction->transaction_type);
        $amount        = number_format($transaction->amount, 4);

        return self::create([
            'user_id'       => $user->user_id,
            'activity_type' => 'transaction',
            'description'   => "{$type} {$amount} {$projectSymbol} ({$projectName}) pada harga \${$transaction->price}",
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);
    }

    /**
     * DIOPTIMALKAN: Mencatat aktivitas update profil.
     */
    public static function logProfileUpdate($user, $request)
    {
        return self::create([
            'user_id'       => $user->user_id,
            'activity_type' => 'profile_update',
            'description'   => "Memperbarui informasi profil",
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);
    }

    /**
     * DIOPTIMALKAN: Mencatat aktivitas admin hanya untuk tindakan penting.
     */
    public static function logAdminAction($user, $request, $action, $details = null)
    {
        // Jangan catat aktivitas admin untuk aktivitas view sederhana
        $viewActions = ['view_dashboard', 'view_users', 'view_projects', 'view_data_sync', 'view_activity_logs'];
        if (in_array($action, $viewActions) && empty($details)) {
            return null;
        }

        $description = "Tindakan admin: {$action}";

        if ($details) {
            $description .= " - {$details}";
        }

        return self::create([
            'user_id'       => $user->user_id,
            'activity_type' => 'admin_action',
            'description'   => $description,
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);
    }

    /**
     * Mendapatkan statistik aktivitas berdasarkan tipe.
     */
    public static function getActivityStats($userId = null, $days = 30)
    {
        $query = self::selectRaw('activity_type, COUNT(*) as count, DATE(created_at) as date')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('activity_type', 'date')
            ->orderBy('date');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Mendapatkan pengguna paling aktif.
     */
    public static function getMostActiveUsers($limit = 10, $days = 30)
    {
        return self::join('users', 'activity_logs.user_id', '=', 'users.user_id')
            ->selectRaw('activity_logs.user_id, COUNT(*) as activity_count')
            ->where('activity_logs.created_at', '>=', now()->subDays($days))
            ->groupBy('activity_logs.user_id')
            ->orderBy('activity_count', 'desc')
            ->limit($limit)
            ->get();
    }
}

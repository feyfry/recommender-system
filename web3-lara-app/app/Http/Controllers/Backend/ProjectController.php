<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Interaction;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProjectController extends Controller
{
    /**
     * Menampilkan daftar semua project cryptocurrency
     */
    public function index(Request $request)
    {
        // Parameter filter
        $category      = $request->input('category');
        $chain         = $request->input('chain');
        $search        = $request->input('search');
        $sortField     = $request->input('sort', 'popularity_score');
        $sortDirection = $request->input('direction', 'desc');

        // Ambil data categories dan chains untuk filter
        $categories = Cache::remember('projects_all_categories', 60, function () {
            return Project::select('primary_category')
                ->distinct()
                ->whereNotNull('primary_category')
                ->where('primary_category', '!=', '')
                ->orderBy('primary_category')
                ->get()
                ->pluck('primary_category')
                ->map(function ($category) {
                    // Coba parse jika category dalam format JSON array
                    if (str_starts_with($category, '[') && str_ends_with($category, ']')) {
                        try {
                            // Clean up potential nested quotes
                            $cleaned_value = $category;
                            // Replace double quotes if needed
                            if (str_starts_with($cleaned_value, '"[') && str_ends_with($cleaned_value, ']"')) {
                                $cleaned_value = substr($cleaned_value, 1, -1);
                            }

                            $parsed = json_decode($cleaned_value, true);
                            if (is_array($parsed) && ! empty($parsed)) {
                                return $parsed[0];
                            }
                        } catch (\Exception $e) {
                            // Fallback ke nilai asli jika parsing gagal
                        }
                    }
                    return $category;
                })
                ->filter(function ($category) {
                    return ! empty($category) && strtolower($category) !== 'unknown';
                })
                ->unique()
                ->sort()
                ->values();
        });

        $chains = Cache::remember('projects_all_chains', 60, function () {
            return Project::select('chain')
                ->distinct()
                ->whereNotNull('chain')
                ->where('chain', '!=', '')
                ->where('chain', '!=', 'unknown')
                ->orderBy('chain')
                ->get()
                ->pluck('chain')
                ->filter(function ($chain) {
                    return ! empty($chain) && strtolower($chain) !== 'unknown';
                })
                ->unique()
                ->sort()
                ->values();
        });

        // Query dasar
        $query = Project::query();

        // Terapkan filter kategori
        if ($category) {
            $query->where(function ($q) use ($category) {
                $q->where('primary_category', $category)
                    ->orWhere('primary_category', 'like', '%"' . $category . '"%')
                    ->orWhere('primary_category', 'like', '[' . $category . ']')
                    ->orWhere('primary_category', 'like', "['" . $category . "']");
            });
        }

        // Terapkan filter chain
        if ($chain) {
            $query->where('chain', $chain);
        }

        // Terapkan pencarian
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('symbol', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        // Terapkan pengurutan
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $projects = $query->paginate(20)->withQueryString();

        // Statistik jumlah
        $projectsCount = Cache::remember('projects_count_stats', 30, function () {
            return [
                'total'    => Project::count(),
                'trending' => Project::where('trend_score', '>', 70)->count(),
                'popular'  => Project::where('popularity_score', '>', 70)->count(),
            ];
        });

        return view('backend.projects.index', [
            'projects'      => $projects,
            'categories'    => $categories,
            'chains'        => $chains,
            'filters'       => [
                'category'  => $category,
                'chain'     => $chain,
                'search'    => $search,
                'sort'      => $sortField,
                'direction' => $sortDirection,
            ],
            'projectsCount' => $projectsCount,
        ]);
    }

    /**
     * Menampilkan detail project
     */
    public function show($projectId, Request $request)
    {
        // Redirect ke halaman detail project di RecommendationController
        return redirect()->route('panel.recommendations.project', $projectId);
    }

    /**
     * Like/favorite project
     */
    public function favorite(Request $request)
    {
        $user      = Auth::user();
        $projectId = $request->input('project_id');

        if (! $projectId) {
            return redirect()->back()->with('error', 'ID project diperlukan');
        }

        // Catat interaksi favorit
        $this->recordInteraction($user->user_id, $projectId, 'favorite');

        return redirect()->back()->with('success', 'Project berhasil ditambahkan ke favorit');
    }

    /**
     * FIXED: Tambahkan project ke portfolio - langsung ke route yang benar
     */
    public function addToPortfolio(Request $request)
    {
        $user      = Auth::user();
        $projectId = $request->input('project_id');

        if (! $projectId) {
            return redirect()->back()->with('error', 'ID project diperlukan');
        }

        // Validasi project exists
        $project = Project::find($projectId);
        if (! $project) {
            return redirect()->back()->with('error', 'Project tidak ditemukan');
        }

        // Catat interaksi portfolio_add
        $interaction = $this->recordInteraction($user->user_id, $projectId, 'portfolio_add');

        if ($interaction) {
            // FIXED: Redirect langsung ke route yang benar dengan parameter
            return redirect()->route('panel.portfolio.transaction-management', ['add_project' => $projectId])
                ->with('success', "Project {$project->name} ({$project->symbol}) telah dipilih. Silakan lengkapi detail transaksi.");
        } else {
            return redirect()->back()->with('error', 'Gagal menambahkan project ke portfolio');
        }
    }

    /**
     * Menambahkan interaksi pengguna
     */
    private function recordInteraction($userId, $projectId, $interactionType, $weight = 1)
    {
        try {
            // Validasi proyek ada di database
            $projectExists = Project::where('id', $projectId)->exists();
            if (! $projectExists) {
                Log::warning("Project {$projectId} not found for interaction recording");
                return null;
            }

            // Cek duplikasi untuk mencegah double entry
            $existingInteraction = Interaction::where('user_id', $userId)
                ->where('project_id', $projectId)
                ->where('interaction_type', $interactionType)
                ->where('created_at', '>=', now()->subSeconds(5))
                ->first();

            if ($existingInteraction) {
                Log::info("Duplicate interaction prevented: {$userId}:{$projectId}:{$interactionType}");
                return $existingInteraction;
            }

            // Catat interaksi
            $interaction = Interaction::create([
                'user_id'          => $userId,
                'project_id'       => $projectId,
                'interaction_type' => $interactionType,
                'weight'           => $weight,
                'context'          => [
                    'source'    => 'projects_page',
                    'timestamp' => now()->timestamp,
                ],
            ]);

            // Hapus cache rekomendasi
            $this->clearUserRecommendationCaches($userId);

            Log::info("Interaction recorded successfully: {$userId}:{$projectId}:{$interactionType}");

            return $interaction;
        } catch (\Exception $e) {
            Log::error("Error recording interaction: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Menghapus cache rekomendasi
     */
    private function clearUserRecommendationCaches($userId)
    {
        $cacheKeys = [
            "rec_personal_{$userId}_10",
            "rec_personal_hybrid_{$userId}",
            "rec_personal_fecf_{$userId}",
            "rec_personal_ncf_{$userId}",
            "dashboard_personal_recs_{$userId}",
            "rec_interactions_{$userId}",
            "user_interactions_{$userId}_10",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }
}

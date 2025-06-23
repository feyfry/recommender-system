<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\Web3AuthController;
use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\PortfolioController;
use App\Http\Controllers\Backend\ProfileController;
use App\Http\Controllers\Backend\ProjectController;
use App\Http\Controllers\Backend\RecommendationController;
use App\Http\Controllers\Backend\TechnicalAnalysisController;
use Illuminate\Support\Facades\Route;

// Halaman Utama
Route::get('/', function () {
    return view('welcome');
})->name('welcome');

// Rute autentikasi Web3
Route::get('/login', [Web3AuthController::class, 'showLoginForm'])->name('login');
Route::post('/web3/nonce', [Web3AuthController::class, 'getNonce'])->name('web3.nonce');
// Verifikasi signature
Route::post('/web3/verify', [Web3AuthController::class, 'verifySignature'])->name('web3.verify');
Route::post('/logout', [Web3AuthController::class, 'logout'])->name('logout');

// Rute yang memerlukan autentikasi
Route::prefix('panel')->middleware('auth')->group(function () {
    // Dashboard Pengguna
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('panel.dashboard');
    // Dashboard AJAX endpoints untuk lazy loading
    Route::get('/dashboard/load-portfolio', [DashboardController::class, 'loadPortfolio'])->name('panel.dashboard.load-portfolio');
    Route::get('/dashboard/load-interactions', [DashboardController::class, 'loadInteractions'])->name('panel.dashboard.load-interactions');

    // Projects
    Route::get('/projects', [ProjectController::class, 'index'])->name('panel.projects');
    Route::get('/projects/{id}', [ProjectController::class, 'show'])->name('panel.projects.show');
    Route::post('/projects/favorite', [ProjectController::class, 'favorite'])->name('panel.projects.favorite');
    Route::post('/projects/add-portfolio', [ProjectController::class, 'addToPortfolio'])->name('panel.projects.add-portfolio');

    // Profil Pengguna
    Route::get('/profile', [ProfileController::class, 'edit'])->name('panel.profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('panel.profile.update');

    // Rekomendasi
    Route::prefix('recommendations')->group(function () {
        Route::get('/', [RecommendationController::class, 'index'])->name('panel.recommendations');
        Route::get('/personal', [RecommendationController::class, 'personal'])->name('panel.recommendations.personal');
        Route::get('/trending', [RecommendationController::class, 'trending'])->name('panel.recommendations.trending');
        Route::get('/popular', [RecommendationController::class, 'popular'])->name('panel.recommendations.popular');
        Route::get('/categories', [RecommendationController::class, 'categories'])->name('panel.recommendations.categories');
        Route::get('/chains', [RecommendationController::class, 'chains'])->name('panel.recommendations.chains');
        Route::get('/project/{id}', [RecommendationController::class, 'projectDetail'])->name('panel.recommendations.project');
        Route::post('/favorites/add', [RecommendationController::class, 'addToFavorites'])->name('panel.recommendations.add-favorite');
        Route::post('/portfolio/add', [RecommendationController::class, 'addToPortfolio'])->name('panel.recommendations.add-portfolio');
    });

    // Technical Analysis dengan periode dinamis
    Route::prefix('technical-analysis')->group(function () {
        Route::get('/', [TechnicalAnalysisController::class, 'index'])->name('panel.technical-analysis');
        Route::post('/trading-signals', [TechnicalAnalysisController::class, 'getTradingSignals'])->name('panel.technical-analysis.trading-signals');
        Route::post('/indicators', [TechnicalAnalysisController::class, 'getIndicators'])->name('panel.technical-analysis.indicators');
        Route::get('/market-events/{projectId}', [TechnicalAnalysisController::class, 'getMarketEvents'])->name('panel.technical-analysis.market-events');
        Route::get('/alerts/{projectId}', [TechnicalAnalysisController::class, 'getAlerts'])->name('panel.technical-analysis.alerts');
        Route::get('/price-prediction/{projectId}', [TechnicalAnalysisController::class, 'getPricePrediction'])->name('panel.technical-analysis.price-prediction');
    });

    // Portfolio - UPDATED STRUCTURE dengan multi-chain native token focus
    Route::prefix('portfolio')->group(function () {
        // Main portfolio overview (onchain + manual data)
        Route::get('/', [PortfolioController::class, 'index'])->name('panel.portfolio');

        // ⚡ ENHANCED: Load analytics data untuk portfolio - AJAX endpoint dengan chain selection
        Route::get('/load-analytics', [PortfolioController::class, 'loadAnalyticsData'])->name('panel.portfolio.load-analytics');

        // ⚡ ENHANCED: Onchain Analytics dengan multi-chain native token focus
        Route::get('/onchain-analytics', [PortfolioController::class, 'onchainAnalytics'])->name('panel.portfolio.onchain-analytics');

        // ⚡ NEW: Refresh analytics data dengan chain selection support
        Route::post('/refresh-analytics', [PortfolioController::class, 'refreshAnalyticsData'])->name('panel.portfolio.refresh-analytics');

        // RENAMED: Transaction Management (dulu: transactions)
        Route::get('/transaction-management', [PortfolioController::class, 'transactionManagement'])->name('panel.portfolio.transaction-management');

        // ⚡ ENHANCED: AJAX endpoint untuk refresh onchain data dengan multi-chain native token focus
        Route::post('/refresh-onchain', [PortfolioController::class, 'refreshOnchainData'])->name('panel.portfolio.refresh-onchain');

        // Transaction operations (existing)
        Route::post('/transactions/add', [PortfolioController::class, 'addTransaction'])->name('panel.portfolio.add-transaction');

        // DEPRECATED ROUTES - For backward compatibility (redirects)
        Route::get('/transactions', function () {
            return redirect()->route('panel.portfolio.transaction-management');
        })->name('panel.portfolio.transactions');
    });

    // Admin area (hanya untuk role admin)
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');

        // Pengelolaan Pengguna
        Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
        Route::get('/users/{user_id}', [AdminController::class, 'userDetail'])->name('admin.users.detail');
        Route::put('/users/{user_id}/role', [AdminController::class, 'updateUserRole'])->name('admin.users.update-role');

        // Pengelolaan Proyek
        Route::get('/projects', [AdminController::class, 'projects'])->name('admin.projects');
        Route::get('/projects/{id}', [AdminController::class, 'projectDetail'])->name('admin.projects.detail');

        // Sinkronisasi Data
        Route::get('/data-sync', [AdminController::class, 'dataSyncDashboard'])->name('admin.data-sync');
        Route::post('/data-sync/trigger', [AdminController::class, 'triggerDataSync'])->name('admin.trigger-data-sync');
        Route::post('/data-sync/clear-cache', [AdminController::class, 'clearApiCache'])->name('admin.clear-api-cache');
        Route::post('/data-sync/train-models', [AdminController::class, 'trainModels'])->name('admin.train-models');

        // Import/Export Command
        Route::post('/import-command', [AdminController::class, 'runImportCommand'])->name('admin.import-command');

        Route::get('/interactions', [AdminController::class, 'interactions'])->name('admin.interactions');

        Route::get('/most-interacted-projects', [AdminController::class, 'getMostInteractedProjects'])->name('admin.most-interacted-projects');
    });
});

// Rute fallback
Route::fallback(function () {
    return view('errors.404');
});

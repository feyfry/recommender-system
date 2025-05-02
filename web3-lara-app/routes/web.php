<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\Web3AuthController;
use App\Http\Controllers\Backend\ProfileController;
use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\PortfolioController;
use SebastianBergmann\CodeCoverage\Report\Html\Dashboard;
use App\Http\Controllers\Backend\RecommendationController;

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

    // Profil Pengguna
    Route::get('/profile', [ProfileController::class, 'edit'])->name('panel.profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('panel.profile.update');
    Route::get('/profile/notifications', [ProfileController::class, 'notificationSettings'])->name('panel.profile.notification-settings');
    Route::put('/profile/notifications', [ProfileController::class, 'updateNotificationSettings'])->name('panel.profile.update-notification-settings');

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
    });

    // Portfolio
    Route::prefix('portfolio')->group(function () {
        Route::get('/', [PortfolioController::class, 'index'])->name('panel.portfolio');
        Route::get('/transactions', [PortfolioController::class, 'transactions'])->name('panel.portfolio.transactions');
        Route::get('/price-alerts', [PortfolioController::class, 'priceAlerts'])->name('panel.portfolio.price-alerts');

        Route::post('/transactions/add', [PortfolioController::class, 'addTransaction'])->name('panel.portfolio.add-transaction');
        Route::post('/price-alerts/add', [PortfolioController::class, 'addPriceAlert'])->name('panel.portfolio.add-price-alert');
        Route::delete('/price-alerts/{id}', [PortfolioController::class, 'deletePriceAlert'])->name('panel.portfolio.delete-price-alert');
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

        // Log Aktivitas
        Route::get('/activity-logs', [AdminController::class, 'activityLogs'])->name('admin.activity-logs');
    });
});

// Rute fallback
Route::fallback(function () {
    return view('errors.404');
});

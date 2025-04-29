<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\Web3AuthController;
use App\Http\Controllers\Backend\ProfileController;
use App\Http\Controllers\Backend\DashboardController;
use SebastianBergmann\CodeCoverage\Report\Html\Dashboard;

Route::get('/', function () {
    return view('welcome');
});

// Rute authentikasi Web3
Route::get('/login', [Web3AuthController::class, 'showLoginForm'])->name('login');
Route::post('/web3/nonce', [Web3AuthController::class, 'getNonce'])->name('web3.nonce');
Route::post('/web3/direct-auth', [Web3AuthController::class, 'directAuth'])->name('web3.direct-auth');
Route::post('/web3/verify', [Web3AuthController::class, 'verifySignature'])->name('web3.verify');
Route::post('logout', [Web3AuthController::class, 'logout'])->name('logout');

// Rute yang memerlukan autentikasi
Route::prefix('panel')->middleware('auth')->group(function () {
    Route::get('/dashbboard', [DashboardController::class, 'index'])->name('panel.dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('panel.profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('panel.profile.update');
});

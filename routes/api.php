<?php

use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;

// The dashboard polls these endpoints with the logged-in web session, so the
// web middleware stack (sessions + CSRF cookie) must be applied explicitly.
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('api.dashboard.stats');
    Route::get('/products/low-stock', [DashboardController::class, 'lowStock'])->name('api.products.low-stock');
    Route::get('/transactions/recent', [DashboardController::class, 'recentTransactions'])->name('api.transactions.recent');
});

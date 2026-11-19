<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.store');
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::patch('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    Route::patch('products/{product}/restore', [ProductController::class, 'restore'])->name('products.restore');
    Route::resource('products', ProductController::class);

    Route::get('transactions/adjust', [InventoryTransactionController::class, 'createAdjustment'])
        ->middleware('can:manage-inventory')
        ->name('transactions.adjust');
    Route::post('transactions/adjust', [InventoryTransactionController::class, 'storeAdjustment'])
        ->middleware('can:manage-inventory')
        ->name('transactions.adjust.store');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');

    Route::get('transactions', [InventoryTransactionController::class, 'index'])->name('transactions.index');
    Route::get('transactions/create', [InventoryTransactionController::class, 'create'])
        ->middleware('can:manage-inventory')
        ->name('transactions.create');
    Route::post('transactions', [InventoryTransactionController::class, 'store'])
        ->middleware('can:manage-inventory')
        ->name('transactions.store');
    Route::get('transactions/{transaction}', [InventoryTransactionController::class, 'show'])->name('transactions.show');
    Route::delete('transactions/{transaction}', [InventoryTransactionController::class, 'destroy'])
        ->middleware('can:admin')
        ->name('transactions.destroy');
});

/*
 * Malformed URLs such as /products//////.......////// should not dead-end at a
 * 404. Send them back to the page the user was on instead. External referrers,
 * another malformed URL, and the fallback target itself are rejected to avoid
 * redirect loops.
 */
Route::fallback(function () {
    $safeTarget = route('dashboard');
    $previous = url()->previous();
    $base = rtrim(url('/'), '/');

    if (! is_string($previous) || $previous === '' || $previous === url()->full()) {
        return redirect()->to($safeTarget);
    }

    if ($previous !== $base && ! str_starts_with($previous, $base.'/')) {
        return redirect()->to($safeTarget);
    }

    $path = parse_url($previous, PHP_URL_PATH) ?: '';

    if (preg_match('#/{2,}|\.{2,}#', $path)) {
        return redirect()->to($safeTarget);
    }

    return redirect()->to($previous);
});

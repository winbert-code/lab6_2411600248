<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn (User $user): bool => $user->isAdmin());

        // Admin + manager: catalog, stock movements and order fulfilment.
        Gate::define('manage-inventory', fn (User $user): bool => $user->isInventoryManager());

        // Admin only: user accounts and their roles.
        Gate::define('manage-users', fn (User $user): bool => $user->canManageUsers());

        // Staff may draft orders but cannot receive/complete them.
        Gate::define('create-orders', fn (User $user): bool => $user->canCreateOrders());

        Gate::define('fulfil-orders', fn (User $user): bool => $user->canFulfilOrders());

        Gate::define('export-reports', fn (User $user): bool => $user->isInventoryManager());

        View::composer('layouts.app', function ($view): void {
            $user = auth()->user();

            $view->with([
                'unreadAlerts' => $user?->unreadNotifications->take(5) ?? collect(),
                'unreadAlertsCount' => $user?->unreadNotifications()->count() ?? 0,
            ]);
        });
    }
}

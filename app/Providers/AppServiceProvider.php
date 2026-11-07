<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Gate::before(
            static fn (User $user): ?bool => $user->hasRole('Administrator') ? true : null,
        );

        foreach ([
            'catalog.categories.view',
            'catalog.categories.manage',
            'catalog.products.view',
            'catalog.products.manage',
            'catalog.products.price-history.view',
            'suppliers.view',
            'suppliers.manage',
            'opening-inventory.view',
            'opening-inventory.manage',
            'purchases.view',
            'purchases.manage',
            'purchases.approve',
        ] as $permission) {
            Gate::define(
                $permission,
                static fn (User $user): bool => $user->hasPermissionTo($permission),
            );
        }
    }
}

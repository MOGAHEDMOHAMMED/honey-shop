<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

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
        ] as $permission) {
            Gate::define(
                $permission,
                static fn (User $user): bool => $user->hasPermissionTo($permission),
            );
        }
    }
}

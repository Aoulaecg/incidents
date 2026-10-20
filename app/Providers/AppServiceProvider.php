<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
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
        Gate::define('manage-users', fn (User $user) => $user->isAdministrator());
        Gate::define('declare-incidents', fn (User $user) => $user->active && $user->role === User::ROLE_SUPERVISOR);
        Gate::define('view-all-declarations', fn (User $user) => $user->active && $user->role === User::ROLE_DIRECTOR);
        Paginator::useBootstrapFive();
    }
}

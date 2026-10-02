<?php

namespace App\Providers;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\User;
use App\Policies\DocumentPolicy;
use App\Services\Tenancy\CompanyContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CompanyContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Gate::policy(Invoice::class, DocumentPolicy::class);
        Gate::policy(Bill::class, DocumentPolicy::class);
        Gate::define('permission', fn (User $user, string $permission) => $user->hasCompanyPermission($permission));
    }
}

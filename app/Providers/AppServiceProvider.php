<?php

namespace App\Providers;

use App\Policies\AuditLogPolicy;
use App\Policies\ReportPolicy;
use App\Support\Navigation\RoleNavigation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Compatible with MySQL/MariaDB configurations that limit index key length.
        Schema::defaultStringLength(191);

        Gate::define('viewReports', [ReportPolicy::class, 'viewAny']);
        Gate::define('viewAuditLogs', [AuditLogPolicy::class, 'viewAny']);

        View::composer('layouts.app', function ($view): void {
            $user = Auth::user();

            $view->with('navigation', $user ? RoleNavigation::for($user) : []);
        });
    }
}

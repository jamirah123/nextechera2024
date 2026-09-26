<?php

namespace App\Providers;

use App\Policies\AuditLogPolicy;
use App\Policies\FinancePolicy;
use App\Policies\ReportPolicy;
use App\Services\SystemSettingService;
use App\Support\Access\RolePermissionService;
use App\Support\Navigation\RoleNavigation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->configureRateLimiting();

        Gate::define('viewReports', [ReportPolicy::class, 'viewAny']);
        Gate::define('viewAuditLogs', [AuditLogPolicy::class, 'viewAny']);
        Gate::define('viewFinance', [FinancePolicy::class, 'viewAny']);
        Gate::define('manageFinance', [FinancePolicy::class, 'manage']);
        Gate::define('viewPurchases', [FinancePolicy::class, 'viewPurchases']);
        Gate::define('managePurchases', [FinancePolicy::class, 'managePurchases']);
        Gate::define('approvePayroll', [FinancePolicy::class, 'approvePayroll']);

        try {
            if (Schema::hasTable('system_settings')) {
                app(SystemSettingService::class)->applyRuntimeConfig();
            }

            if (Schema::hasTable('role_permissions') && DB::table('role_permissions')->count() === 0) {
                app(RolePermissionService::class)->seedDefaults();
            }
        } catch (\Throwable) {
            // Ignore during initial install or partial schema.
        }

        View::composer('layouts.app', function ($view): void {
            $user = Auth::user();

            $view->with('navigation', $user ? RoleNavigation::for($user) : []);
            $view->with('navigationGroups', $user ? RoleNavigation::groups($user) : []);
        });

        View::composer(['layouts.app', 'layouts.guest', 'auth.login', 'admin.settings.index'], function ($view): void {
            try {
                if (Schema::hasTable('system_settings')) {
                    $view->with('brand', app(SystemSettingService::class)->branding());
                }
            } catch (\Throwable) {
                // Ignore during initial install or partial schema.
            }
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(45)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('exports', function (Request $request) {
            return Limit::perMinute(12)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('mutations', function (Request $request) {
            return Limit::perMinute(90)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('backups', function (Request $request) {
            return Limit::perMinute(4)->by((string) ($request->user()?->id ?: $request->ip()));
        });
    }
}

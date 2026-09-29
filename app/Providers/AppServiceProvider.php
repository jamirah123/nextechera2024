<?php

namespace App\Providers;

use App\Models\EmailDelivery;
use App\Policies\AuditLogPolicy;
use App\Support\Notifications\EmailFailureMessage;
use App\Support\Notifications\QueuedWorkflowMail;
use App\Policies\FinancePolicy;
use App\Policies\ReportPolicy;
use App\Services\SystemSettingService;
use App\Support\Access\RolePermissionService;
use App\Support\Navigation\RoleNavigation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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

        Event::listen(function (JobProcessing $event): void {
            $mailable = QueuedWorkflowMail::fromJob($event);

            if ($mailable?->deliveryId) {
                EmailDelivery::query()->whereKey($mailable->deliveryId)->update([
                    'status' => 'sending',
                    'attempts' => $event->job->attempts(),
                ]);
            }
        });

        Event::listen(function (MessageSent $event): void {
            $headers = $event->sent->getOriginalMessage()->getHeaders();

            if (! $headers->has('X-PSG-Delivery')) {
                return;
            }

            $id = (int) $headers->get('X-PSG-Delivery')?->getBodyAsString();

            if ($id < 1) {
                return;
            }

            EmailDelivery::query()->whereKey($id)->update([
                'status' => 'sent',
                'sent_at' => now(),
                'failure_reason' => null,
            ]);
        });

        Event::listen(function (JobFailed $event): void {
            Log::error('Queued job failed.', [
                'job' => $event->job->resolveName(),
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'exception' => $event->exception::class,
                'message' => $event->exception->getMessage(),
            ]);

            $mailable = QueuedWorkflowMail::fromJob($event);

            if (! $mailable?->deliveryId) {
                return;
            }

            $maxTries = max(1, (int) ($mailable->tries ?? 1));
            $attempts = $event->job->attempts();

            EmailDelivery::query()->whereKey($mailable->deliveryId)->update([
                'status' => $attempts >= $maxTries ? 'failed' : 'retrying',
                'attempts' => $attempts,
                'failure_reason' => EmailFailureMessage::sanitize($event->exception->getMessage()),
            ]);
        });

        Event::listen(function (ScheduledTaskFailed $event): void {
            Log::critical('Scheduled task failed.', [
                'command' => $event->task->command ?? $event->task->description,
                'exception' => $event->exception::class,
                'message' => $event->exception->getMessage(),
            ]);
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

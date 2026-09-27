<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnforceIdleSession;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\PreventRapidDuplicatePosts;
use App\Support\Errors\UserFacingExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');
        $middleware->prepend(AssignRequestId::class);
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
        ]);
        $middleware->appendToGroup('web', [
            EnsureUserIsActive::class,
            EnforceIdleSession::class,
            PreventRapidDuplicatePosts::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontReportWhen(function (Throwable $exception): bool {
            return UserFacingExceptionRenderer::shouldSilenceReport($exception);
        });

        $exceptions->context(function (Throwable $exception, array $context = []): array {
            return UserFacingExceptionRenderer::logContext();
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            return app(UserFacingExceptionRenderer::class)->render($exception, $request);
        });
    })->create();

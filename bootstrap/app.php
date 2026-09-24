<?php

use App\Http\Middleware\EnforceIdleSession;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
        ]);
        $middleware->appendToGroup('web', [
            EnsureUserIsActive::class,
            EnforceIdleSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (config('app.debug') || $request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            // Framework HTTP / auth / validation flows keep their normal responses.
            if ($e instanceof HttpExceptionInterface
                || $e instanceof \Illuminate\Validation\ValidationException
                || $e instanceof \Illuminate\Auth\AuthenticationException
                || $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
                || $e instanceof \Illuminate\Session\TokenMismatchException
            ) {
                return null;
            }

            // Unexpected failures: friendly page only. Laravel still logs the exception.
            return response()->view('errors.500', [], 500);
        });
    })->create();

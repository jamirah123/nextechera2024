<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PreventRapidDuplicatePosts
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        if ($this->isExcluded($request)) {
            return $next($request);
        }

        $key = 'form-submit:'.self::fingerprint($request);

        try {
            $acquired = Cache::add($key, 1, now()->addSeconds(30));
        } catch (Throwable) {
            return $next($request);
        }

        if (! $acquired) {
            return $this->conflict($request);
        }

        try {
            return $next($request);
        } finally {
            try {
                Cache::forget($key);
            } catch (Throwable) {
                // The lock expires on its own if the cache is unavailable.
            }
        }
    }

    public static function fingerprint(Request $request): string
    {
        $actor = (string) ($request->user()?->id ?: $request->ip());
        $payload = $request->except(['_token', 'password', 'password_confirmation']);

        array_walk_recursive($payload, function (mixed &$value): void {
            if ($value instanceof UploadedFile) {
                $value = $value->getClientOriginalName().':'.$value->getSize();
            }
        });

        return hash('sha256', $actor.'|'.$request->method().'|'.$request->path().'|'.json_encode($payload));
    }

    private function isExcluded(Request $request): bool
    {
        return $request->routeIs(
            'login.store',
            'logout',
            'password.email',
            'password.update',
        );
    }

    private function conflict(Request $request): Response
    {
        $message = 'This action is already being processed. Please wait a moment before trying again.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $message], 409);
        }

        return redirect()->back()
            ->withInput($request->except(['password', 'password_confirmation', '_token']))
            ->with('error', $message);
    }
}

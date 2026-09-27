<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceIdleSession
{
    /**
     * Routes that may run in the background and must not refresh idle activity.
     *
     * @var list<string>
     */
    private array $passiveRoutes = [
        'notifications.index',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        $idleMinutes = max(1, (int) config('psg.session.idle_minutes', 30));
        $lastActivity = $request->session()->get('last_activity_at');

        if (is_numeric($lastActivity) && (now()->getTimestamp() - (int) $lastActivity) > ($idleMinutes * 60)) {
            return $this->expire($request);
        }

        if (! $this->isPassiveRequest($request)) {
            $request->session()->put('last_activity_at', now()->getTimestamp());
        } elseif ($lastActivity === null) {
            $request->session()->put('last_activity_at', now()->getTimestamp());
        }

        return $next($request);
    }

    private function isPassiveRequest(Request $request): bool
    {
        $route = $request->route()?->getName();

        return $route !== null && in_array($route, $this->passiveRoutes, true);
    }

    private function expire(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your session expired after a period of inactivity. Please sign in again.',
                'redirect' => route('login'),
            ], 401);
        }

        return redirect()
            ->route('login')
            ->with('error', 'Your session expired after a period of inactivity. Please sign in again.');
    }
}

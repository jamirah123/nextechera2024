<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private AuditService $audit)
    {
    }

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        $request->session()->put('last_activity_at', now()->getTimestamp());

        $user = $request->user();
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->audit->log(
            action: 'auth.login',
            summary: $user->name.' signed in.',
            category: AuditCategory::Auth,
            severity: AuditSeverity::Info,
            subject: $user,
            actor: $user,
            request: $request,
        );

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $idle = $request->input('reason') === 'idle';

        if ($user) {
            $this->audit->log(
                action: $idle ? 'auth.logout.idle' : 'auth.logout',
                summary: $idle
                    ? $user->name.' signed out after inactivity.'
                    : $user->name.' signed out.',
                category: AuditCategory::Auth,
                severity: AuditSeverity::Info,
                subject: $user,
                actor: $user,
                request: $request,
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $redirect = redirect()->route('login');

        if ($idle) {
            return $redirect->with('status', 'You were signed out because your session was inactive.');
        }

        return $redirect;
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreNewPasswordRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function create(string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => request('email', old('email')),
        ]);
    }

    public function store(StoreNewPasswordRequest $request): RedirectResponse
    {
        $user = User::query()->where('email', $request->input('email'))->first();

        if (! $user?->isActive()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __(Password::INVALID_USER)]);
        }

        $credentials = $request->only('email', 'password', 'password_confirmation', 'token');

        $status = Password::reset(
            $credentials,
            function (User $user, string $password) use ($request): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                $this->audit->log(
                    action: 'auth.password_reset',
                    summary: 'Password reset completed for '.$user->email.'.',
                    category: AuditCategory::Auth,
                    severity: AuditSeverity::Notice,
                    subject: $user,
                    actor: $user,
                    request: $request,
                );
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        return redirect()
            ->route('login')
            ->with('status', __('Your password has been reset. Sign in with your new password.'));
    }
}

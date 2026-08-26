<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StorePasswordResetLinkRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(StorePasswordResetLinkRequest $request): RedirectResponse
    {
        $email = $request->validated('email');

        $user = User::query()->where('email', $email)->first();

        if ($user && $user->isActive()) {
            Password::sendResetLink(['email' => $email]);
        }

        return back()->with('status', __('If an active account matches that email, a reset link has been sent.'));
    }
}

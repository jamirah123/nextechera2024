<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use InvalidArgumentException;

class UserController extends Controller
{
    public function __construct(private UserAccessService $users)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->search($request->string('q')->toString())
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
            ->when($request->filled('status'), function ($q) use ($request): void {
                if ($request->string('status')->toString() === 'active') {
                    $q->where('is_active', true);
                } elseif ($request->string('status')->toString() === 'inactive') {
                    $q->where('is_active', false);
                }
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => UserRole::cases(),
            'filters' => $request->only(['q', 'role', 'status']),
            'stats' => [
                'total' => User::query()->count(),
                'active' => User::query()->where('is_active', true)->count(),
                'inactive' => User::query()->where('is_active', false)->count(),
                'super_admins' => User::query()->where('role', UserRole::SuperAdmin->value)->where('is_active', true)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', [
            'roles' => UserRole::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in(UserRole::values())],
            'password' => ['required', 'confirmed', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $user = $this->users->create($data);

        return redirect()
            ->route('users.show', $user)
            ->with('status', 'User account created.');
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        return view('admin.users.show', [
            'user' => $user,
            'canManage' => request()->user()->can('update', $user),
            'canDelete' => request()->user()->can('delete', $user),
        ]);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => UserRole::cases(),
            'isSelf' => request()->user()->id === $user->id,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in(UserRole::values())],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        if ($request->user()->id === $user->id) {
            $data['is_active'] = true;
        }

        try {
            $this->users->update($user, $data);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['user' => $e->getMessage()]);
        }

        return redirect()
            ->route('users.show', $user)
            ->with('status', 'User account updated.');
    }

    public function updatePassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $this->users->setPassword($user, $data['password']);

        return back()->with('status', 'Password reset successfully.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        try {
            $this->users->setActive($user, ! $user->is_active);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['user' => $e->getMessage()]);
        }

        return back()->with('status', $user->fresh()->is_active ? 'User activated.' : 'User deactivated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        try {
            $this->users->softDelete($user);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['user' => $e->getMessage()]);
        }

        return redirect()
            ->route('users.index')
            ->with('status', 'User account removed.');
    }
}

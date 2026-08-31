<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserAttachmentRules;
use App\Models\Supervisor;
use App\Models\User;
use App\Models\UserAttachment;
use App\Services\UserAccessService;
use App\Services\UserAttachmentService;
use App\Support\Attachments\InlineAttachmentResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function __construct(
        private UserAccessService $users,
        private UserAttachmentService $attachments,
    ) {
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
            ->paginate(table_per_page())
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
            'roles' => $this->users->assignableRolesFor(request()->user()),
            'supervisors' => Supervisor::query()->with('region:id,name')->orderBy('name')->get(['id', 'name', 'supervisor_code', 'region_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $assignableRoles = collect($this->users->assignableRolesFor($request->user()))
            ->map(fn (UserRole $role) => $role->value)
            ->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in($assignableRoles)],
            'supervisor_id' => [
                Rule::requiredIf(fn () => $request->input('role') === UserRole::RegionSupervisor->value),
                'nullable',
                'exists:supervisors,id',
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        try {
            $user = $this->users->create($data);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['role' => $e->getMessage()]);
        }

        return redirect()
            ->route('users.show', $user)
            ->with('status', 'User account created.');
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        return view('admin.users.show', [
            'user' => $user->load(['supervisorProfile.region', 'attachments.uploader']),
            'canManage' => request()->user()->can('update', $user),
            'canDelete' => request()->user()->can('delete', $user),
            'canManageDocuments' => request()->user()->can('manageAttachments', $user),
        ]);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', [
            'user' => $user->load(['supervisorProfile.region', 'attachments.uploader']),
            'roles' => $this->users->assignableRolesFor(request()->user()),
            'supervisors' => Supervisor::query()->with('region:id,name')->orderBy('name')->get(['id', 'name', 'supervisor_code', 'region_id']),
            'isSelf' => request()->user()->id === $user->id,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $assignableRoles = collect($this->users->assignableRolesFor($request->user()))
            ->map(fn (UserRole $role) => $role->value)
            ->all();

        if ($user->isSuperAdmin()) {
            $assignableRoles[] = UserRole::SuperAdmin->value;
            $assignableRoles = array_values(array_unique($assignableRoles));
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in($assignableRoles)],
            'supervisor_id' => [
                Rule::requiredIf(fn () => $request->input('role') === UserRole::RegionSupervisor->value),
                'nullable',
                'exists:supervisors,id',
            ],
            'is_active' => ['sometimes', 'boolean'],
            ...UserAttachmentRules::rules(),
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

        if ($request->hasFile('attachments')) {
            $this->attachments->storeMany(
                $user,
                $request->file('attachments'),
                $request->input('attachment_labels', []),
            );
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

    public function showAttachment(User $user, UserAttachment $attachment): View
    {
        $this->authorize('viewAttachments', $user);
        abort_unless($attachment->user_id === $user->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        $attachment->load('uploader');

        return view('users.attachments.show', [
            'user' => $user,
            'attachment' => $attachment,
            'backRoute' => route('users.show', $user),
            'streamRoute' => route('users.attachments.stream', [$user, $attachment]),
            'downloadRoute' => route('users.attachments.download', [$user, $attachment]),
            'destroyRoute' => route('users.attachments.destroy', [$user, $attachment]),
            'canManage' => request()->user()->can('manageAttachments', $user),
        ]);
    }

    public function streamAttachment(User $user, UserAttachment $attachment): StreamedResponse
    {
        $this->authorize('viewAttachments', $user);
        abort_unless($attachment->user_id === $user->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return (new InlineAttachmentResponse($attachment))->toResponse(request());
    }

    public function downloadAttachment(User $user, UserAttachment $attachment): StreamedResponse
    {
        $this->authorize('viewAttachments', $user);
        abort_unless($attachment->user_id === $user->id, 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function destroyAttachment(User $user, UserAttachment $attachment): RedirectResponse
    {
        $this->authorize('manageAttachments', $user);
        abort_unless($attachment->user_id === $user->id, 404);

        $this->attachments->delete($attachment);

        return redirect()
            ->route('users.show', $user)
            ->with('status', 'Document removed.');
    }
}

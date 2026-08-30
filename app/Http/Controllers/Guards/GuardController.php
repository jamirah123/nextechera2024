<?php

namespace App\Http\Controllers\Guards;

use App\Enums\EmploymentStatus;
use App\Enums\GuardGender;
use App\Enums\OperationalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guards\StoreGuardRequest;
use App\Http\Requests\Guards\UpdateGuardRequest;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\GuardAttachment;
use App\Models\Region;
use App\Services\DeploymentService;
use App\Services\GuardAttachmentService;
use App\Services\GuardService;
use App\Support\Attachments\InlineAttachmentResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuardController extends Controller
{
    public function __construct(
        private GuardService $guards,
        private GuardAttachmentService $attachments,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Guard::class);

        $user = $request->user();
        $regionId = $user->regionId();

        app(DeploymentService::class)->syncDeployedGuardStatuses(
            $user->mustStayInOwnRegion() ? $regionId : null,
        );

        $guards = Guard::query()
            ->with(['region:id,name,code', 'currentSite:id,name,code', 'currentSupervisor:id,name'])
            ->search($request->string('q')->toString())
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
            ->when($request->filled('employment_status'), fn ($q) => $q->where('employment_status', $request->string('employment_status')))
            ->when($request->filled('operational_status'), fn ($q) => $q->where('operational_status', $request->string('operational_status')))
            ->when($request->filled('region_id') && ! $user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $request->integer('region_id')))
            ->latest()
            ->paginate(table_per_page())
            ->withQueryString();

        $statsBase = Guard::query()->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId));

        return view('guards.index', [
            'guards' => $guards,
            'regions' => Region::query()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('id', $regionId))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'employmentStatuses' => EmploymentStatus::cases(),
            'operationalStatuses' => OperationalStatus::cases(),
            'filters' => $request->only(['q', 'employment_status', 'operational_status', 'region_id']),
            'canManage' => $user->can('create', Guard::class),
            'canDelete' => $user->can('deleteAny', Guard::class),
            'stats' => [
                'total' => (clone $statsBase)->count(),
                'active' => (clone $statsBase)->activeEmployment()->count(),
                'on_leave' => (clone $statsBase)->where('operational_status', OperationalStatus::OnLeave)->count(),
                'absent' => (clone $statsBase)->where('operational_status', OperationalStatus::Absent)->count(),
                'deserted' => (clone $statsBase)->where('operational_status', OperationalStatus::Deserted)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Guard::class);

        return view('guards.create', [
            'regions' => Region::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'employmentStatuses' => EmploymentStatus::cases(),
            'operationalStatuses' => OperationalStatus::cases(),
            'genders' => GuardGender::cases(),
            'nextEmploymentId' => $this->guards->nextEmploymentId(),
        ]);
    }

    public function store(StoreGuardRequest $request): RedirectResponse
    {
        $guard = $this->guards->createGuard(
            $request->safe()->except(['reason', 'attachments', 'attachment_labels']),
        );

        if ($request->hasFile('attachments')) {
            $this->attachments->storeMany(
                $guard,
                $request->file('attachments'),
                $request->input('attachment_labels', []),
            );
        }

        return redirect()
            ->route('guards.show', $guard)
            ->with('status', 'Guard registered successfully.');
    }

    public function show(Guard $guard): View
    {
        $this->authorize('view', $guard);

        app(DeploymentService::class)->syncDeployedGuardStatuses(
            request()->user()->mustStayInOwnRegion() ? request()->user()->regionId() : null,
        );

        $guard->refresh();
        $guard->load([
            'region',
            'currentSite.client',
            'currentSupervisor',
            'currentDeployment',
            'statusHistories.changer',
            'creator',
            'updater',
            'attachments.uploader',
        ]);

        return view('guards.show', [
            'guard' => $guard,
            'currentDeployment' => $guard->currentDeployment,
            'canManage' => request()->user()->can('update', $guard),
            'canDelete' => request()->user()->can('delete', $guard),
            'canDeploy' => request()->user()->can('create', Deployment::class),
        ]);
    }

    public function edit(Guard $guard): View
    {
        $this->authorize('update', $guard);

        return view('guards.edit', [
            'guard' => $guard->load('attachments.uploader'),
            'regions' => Region::query()->orderBy('name')->get(['id', 'name', 'code']),
            'employmentStatuses' => EmploymentStatus::cases(),
            'operationalStatuses' => OperationalStatus::cases(),
            'genders' => GuardGender::cases(),
        ]);
    }

    public function update(UpdateGuardRequest $request, Guard $guard): RedirectResponse
    {
        $this->guards->updateGuard(
            $guard,
            $request->safe()->except(['reason', 'attachments', 'attachment_labels']),
            $request->input('reason'),
        );

        if ($request->hasFile('attachments')) {
            $this->attachments->storeMany(
                $guard,
                $request->file('attachments'),
                $request->input('attachment_labels', []),
            );
        }

        return redirect()
            ->route('guards.show', $guard)
            ->with('status', 'Guard profile updated successfully.');
    }

    public function showAttachment(Guard $guard, GuardAttachment $attachment): View
    {
        $this->authorize('view', $guard);
        abort_unless($attachment->guard_id === $guard->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        $attachment->load('uploader');

        return view('guards.attachments.show', [
            'guard' => $guard,
            'attachment' => $attachment,
            'canManage' => request()->user()->can('update', $guard),
        ]);
    }

    public function streamAttachment(Guard $guard, GuardAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $guard);
        abort_unless($attachment->guard_id === $guard->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return (new InlineAttachmentResponse($attachment))->toResponse(request());
    }

    public function downloadAttachment(Guard $guard, GuardAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $guard);
        abort_unless($attachment->guard_id === $guard->id, 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function destroyAttachment(Guard $guard, GuardAttachment $attachment): RedirectResponse
    {
        $this->authorize('update', $guard);
        abort_unless($attachment->guard_id === $guard->id, 404);

        $this->attachments->delete($attachment);

        return redirect()
            ->route('guards.show', $guard)
            ->with('status', 'Attachment removed.');
    }

    public function destroy(Guard $guard): RedirectResponse
    {
        $this->authorize('delete', $guard);

        if ($guard->current_site_id) {
            return back()->withErrors([
                'guard' => 'Cannot archive a guard who is still linked to a current site. Clear the site assignment first.',
            ]);
        }

        $guard->delete();

        return redirect()
            ->route('guards.index')
            ->with('status', 'Guard archived successfully.');
    }
}

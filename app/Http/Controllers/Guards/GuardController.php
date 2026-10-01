<?php

namespace App\Http\Controllers\Guards;

use App\Enums\EmploymentStatus;
use App\Enums\GuardDocumentType;
use App\Enums\GuardGender;
use App\Enums\OperationalStatus;
use App\Http\Controllers\Concerns\ServesPdfDownload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guards\StoreGuardRequest;
use App\Http\Requests\Guards\UpdateGuardRequest;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\GuardAssetIssuance;
use App\Models\GuardAttachment;
use App\Models\Region;
use App\Services\Documents\LetterPdfService;
use App\Services\EntityRelatedRecordsService;
use App\Services\EntityTimelineService;
use App\Services\GuardAttachmentService;
use App\Services\GuardService;
use App\Services\UniformChargeExemptionService;
use App\Support\Attachments\InlineAttachmentResponse;
use App\Support\Finance\PayrollRates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuardController extends Controller
{
    use ServesPdfDownload;

    public function __construct(
        private GuardService $guards,
        private GuardAttachmentService $attachments,
        private EntityTimelineService $timeline,
        private EntityRelatedRecordsService $relatedRecords,
        private LetterPdfService $letters,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Guard::class);

        $user = $request->user();
        $regionId = $user->regionId();

        app(\App\Services\EmployeePromotionService::class)->applyDue($user);

        $guards = Guard::query()
            ->onGuardRoster()
            ->with(['region:id,name,code', 'currentSite:id,name,code', 'currentSupervisor:id,name'])
            ->search($request->string('q')->toString())
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
            ->when($request->filled('employment_status'), fn ($q) => $q->where('employment_status', $request->string('employment_status')))
            ->when($request->filled('operational_status'), fn ($q) => $q->where('operational_status', $request->string('operational_status')))
            ->when($request->filled('region_id') && ! $user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $request->integer('region_id')))
            ->latest()
            ->paginate(table_per_page())
            ->withQueryString();

        $statsBase = Guard::query()->onGuardRoster()->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId));
        $employmentCounts = status_counts((clone $statsBase), 'employment_status');
        $operationalCounts = status_counts((clone $statsBase), 'operational_status');

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
                'total' => array_sum($employmentCounts),
                'active' => (int) ($employmentCounts[EmploymentStatus::Active->value] ?? 0),
                'on_leave' => (int) ($operationalCounts[OperationalStatus::OnLeave->value] ?? 0),
                'absent' => (int) ($operationalCounts[OperationalStatus::Absent->value] ?? 0),
                'deserted' => (int) ($operationalCounts[OperationalStatus::Deserted->value] ?? 0),
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
                $request->input('attachment_document_types', []),
                $request->input('attachment_expires_at', []),
            );
        }

        return redirect()
            ->route('guards.show', $guard)
            ->with('status', 'Guard registered successfully.');
    }

    public function show(Guard $guard): View
    {
        $this->authorize('view', $guard);

        $guard->load([
            'region',
            'currentSite.client',
            'currentSupervisor',
            'currentDeployment',
            'statusHistories.changer',
            'creator',
            'updater',
            'attachments.uploader',
            'salaryAdvances',
            'salaryRevisions.approver',
            'salaryRevisions.creator',
            'uniformChargeRevisions.approver',
            'uniformChargeRevisions.creator',
            'position',
            'linkedStaff',
            'promotions.position',
            'promotions.approver',
            'promotions.creator',
            'promotions.region',
            'supervisorProfile:id,guard_id',
            'assetIssuances.lines',
            'assetRecoveries',
        ]);

        $today = now()->startOfDay();
        $currentRevision = $guard->salaryRevisions->first(
            fn ($revision) => $revision->effective_from->copy()->startOfDay()->lessThanOrEqualTo($today)
                && ($revision->effective_to === null || $revision->effective_to->copy()->startOfDay()->greaterThanOrEqualTo($today))
        );

        return view('guards.show', [
            'guard' => $guard,
            'currentSalary' => PayrollRates::salaryOn($guard, $today),
            'currentRevision' => $currentRevision,
            'currentDeployment' => $guard->currentDeployment,
            'canManage' => request()->user()->can('update', $guard),
            'canViewSalary' => request()->user()->can('viewSalary', $guard),
            'canManageSalary' => request()->user()->can('manageSalary', $guard),
            'canManageUniformCharge' => request()->user()->can('manageUniformCharge', $guard),
            'uniformStatus' => app(UniformChargeExemptionService::class)->statusOn($guard, $today),
            'companyUniformCharge' => (float) config('psg.payroll.uniform_charge', 0),
            'canPromote' => request()->user()->can('promote', $guard),
            'positions' => \App\Models\Position::query()->where('is_active', true)->orderBy('name')->get(),
            'promotionRegions' => Region::query()->orderBy('name')->get(['id', 'name', 'code']),
            'canManageAssets' => request()->user()->can('create', GuardAssetIssuance::class),
            'canManageFinance' => request()->user()->can('manageFinance'),
            'canDelete' => request()->user()->can('delete', $guard),
            'canDeploy' => request()->user()->can('create', Deployment::class),
            'canDownloadTerminationLetter' => in_array($guard->employment_status, [
                EmploymentStatus::Terminated,
                EmploymentStatus::Resigned,
                EmploymentStatus::Retired,
            ], true),
            'timeline' => $this->timeline->for($guard, request()->user()),
            'relatedPanels' => $this->relatedRecords->for($guard),
            'lifecycle' => [
                'steps' => [
                    ['label' => 'Training'],
                    ['label' => 'Available'],
                    ['label' => 'On duty'],
                ],
                'current' => match ($guard->operational_status) {
                    OperationalStatus::Training => 0,
                    OperationalStatus::AwaitingDeployment => 1,
                    OperationalStatus::OnDuty, OperationalStatus::OffDuty => 2,
                    default => 1,
                },
                'terminal' => in_array($guard->employment_status, [
                    EmploymentStatus::Terminated,
                    EmploymentStatus::Resigned,
                    EmploymentStatus::Retired,
                ], true) ? $guard->employment_status->label() : null,
                'terminal_tone' => $guard->employment_status->tone(),
            ],
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
            'canCorrectEmploymentId' => request()->user()->can('correctEmploymentId', $guard),
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
                $request->input('attachment_document_types', []),
                $request->input('attachment_expires_at', []),
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

    public function updateAttachment(Request $request, Guard $guard, GuardAttachment $attachment): RedirectResponse
    {
        $this->authorize('update', $guard);
        abort_unless($attachment->guard_id === $guard->id, 404);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'document_type' => ['nullable', 'string', 'in:'.implode(',', GuardDocumentType::values())],
            'expires_at' => ['nullable', 'date'],
        ]);

        $this->attachments->updateMetadata($attachment, $data);

        return back()->with('status', 'Document details updated.');
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

    public function downloadTerminationLetter(Guard $guard): Response
    {
        $this->authorize('view', $guard);

        try {
            $binary = $this->letters->termination($guard);
        } catch (\InvalidArgumentException $e) {
            abort(403, $e->getMessage());
        }

        return $this->pdfDownload($binary, 'termination-letter-'.$guard->employment_id.'.pdf');
    }
}

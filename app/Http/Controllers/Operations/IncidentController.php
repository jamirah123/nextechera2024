<?php

namespace App\Http\Controllers\Operations;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Http\Controllers\Concerns\ServesPdfDownload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\IncidentAttachmentRules;
use App\Models\Guard;
use App\Models\Incident;
use App\Models\IncidentAttachment;
use App\Models\Site;
use App\Models\User;
use App\Services\Operations\IncidentAttachmentService;
use App\Services\Operations\IncidentReportPdfService;
use App\Services\Operations\IncidentService;
use App\Services\ReportExportService;
use App\Support\Attachments\InlineAttachmentResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncidentController extends Controller
{
    use ServesPdfDownload;

    public function __construct(
        private IncidentService $incidents,
        private IncidentAttachmentService $attachments,
        private IncidentReportPdfService $reports,
        private ReportExportService $exports,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Incident::class);

        $user = $request->user();
        $regionId = $user->regionId();

        $incidents = Incident::query()
            ->with(['site:id,name,code,region_id', 'assignedGuard:id,employment_id,full_name', 'assignee:id,name'])
            ->search($request->string('q')->toString())
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->whereHas('site', fn ($s) => $s->where('region_id', $regionId)))
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->integer('site_id')))
            ->when($request->filled('incident_type'), fn ($q) => $q->where('incident_type', $request->string('incident_type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('occurred_at', $request->string('date')))
            ->latest('occurred_at')
            ->paginate(table_per_page())
            ->withQueryString();

        $statsBase = Incident::query()
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->whereHas('site', fn ($s) => $s->where('region_id', $regionId)));

        return view('operations.incidents.index', [
            'incidents' => $incidents,
            'sites' => Site::query()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'types' => IncidentType::cases(),
            'statuses' => IncidentStatus::cases(),
            'filters' => $request->only(['q', 'site_id', 'incident_type', 'status', 'date']),
            'canManage' => $user->can('create', Incident::class),
            'exportQuery' => array_filter($request->only(['q', 'site_id', 'incident_type', 'status', 'date']), fn ($v) => filled($v)),
            'stats' => [
                'today' => (clone $statsBase)->whereDate('occurred_at', now()->toDateString())->count(),
                'month' => (clone $statsBase)
                    ->whereMonth('occurred_at', now()->month)
                    ->whereYear('occurred_at', now()->year)
                    ->count(),
                'open' => (clone $statsBase)->whereNotIn('status', [IncidentStatus::Resolved->value, IncidentStatus::Closed->value])->count(),
                'critical' => (clone $statsBase)->where('severity', IncidentSeverity::Critical->value)->whereDate('occurred_at', '>=', now()->subDays(7))->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Incident::class);

        $user = request()->user();
        $regionId = $user->regionId();

        return view('operations.incidents.create', [
            'sites' => Site::query()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'guards' => Guard::query()
                ->activeEmployment()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                ->orderBy('full_name')
                ->get(['id', 'employment_id', 'full_name', 'current_site_id']),
            'assignees' => User::query()->active()->orderBy('name')->get(['id', 'name']),
            'types' => IncidentType::cases(),
            'severities' => IncidentSeverity::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Incident::class);

        $data = $request->validate(array_merge([
            'site_id' => ['required', 'exists:sites,id'],
            'guard_id' => ['nullable', 'exists:guards,id'],
            'incident_type' => ['required', Rule::in(IncidentType::values())],
            'severity' => ['required', Rule::in(IncidentSeverity::values())],
            'occurred_at' => ['required', 'date'],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string', 'max:5000'],
            'action_taken' => ['nullable', 'string', 'max:255'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'follow_up_due_at' => ['nullable', 'date', 'after_or_equal:today'],
            'police_reference' => ['nullable', 'string', 'max:120'],
            'client_notified' => ['sometimes', 'boolean'],
        ], IncidentAttachmentRules::upload()));

        $user = $request->user();
        if ($user->mustStayInOwnRegion()) {
            $site = Site::query()->find($data['site_id']);
            if (! $site || ! $user->canAccessRegion($site->region_id)) {
                return back()->withInput()->withErrors(['site_id' => 'You can only log occurrences for sites in your region.']);
            }
        }

        $data['client_notified'] = $request->boolean('client_notified');

        try {
            $incident = $this->incidents->record($data);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['incident' => $e->getMessage()]);
        }

        if ($request->hasFile('attachments')) {
            $this->attachments->storeMany(
                $incident,
                $request->file('attachments'),
                $request->input('attachment_labels', []),
            );
        }

        return redirect()->route('incidents.show', $incident)->with('status', 'Occurrence logged.');
    }

    public function show(Incident $incident): View
    {
        $this->authorize('view', $incident);

        $incident->load([
            'site.client',
            'site.region',
            'assignedGuard',
            'shift',
            'assignee',
            'reporter',
            'attachments.uploader',
        ]);

        return view('operations.incidents.show', [
            'incident' => $incident,
            'canManage' => request()->user()->can('update', $incident),
            'assignees' => User::query()->active()->orderBy('name')->get(['id', 'name']),
            'statuses' => IncidentStatus::cases(),
        ]);
    }

    public function update(Request $request, Incident $incident): RedirectResponse
    {
        $this->authorize('update', $incident);

        $data = $request->validate(array_merge([
            'status' => ['required', Rule::in(IncidentStatus::values())],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'follow_up_due_at' => ['nullable', 'date'],
            'follow_up_notes' => ['nullable', 'string', 'max:5000'],
            'action_taken' => ['nullable', 'string', 'max:255'],
            'police_reference' => ['nullable', 'string', 'max:120'],
            'client_notified' => ['sometimes', 'boolean'],
        ], IncidentAttachmentRules::upload()));

        $data['client_notified'] = $request->boolean('client_notified');

        $this->incidents->updateFollowUp($incident, $data);

        if ($request->hasFile('attachments')) {
            $this->attachments->storeMany(
                $incident,
                $request->file('attachments'),
                $request->input('attachment_labels', []),
            );
        }

        return back()->with('status', 'Occurrence updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Incident::class);
        Gate::authorize('viewReports');

        $user = $request->user();
        $regionId = $user->regionId();

        $rows = Incident::query()
            ->with(['site:id,name,code', 'assignedGuard:id,employment_id,full_name', 'assignee:id,name', 'reporter:id,name'])
            ->search($request->string('q')->toString())
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->whereHas('site', fn ($s) => $s->where('region_id', $regionId)))
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->integer('site_id')))
            ->when($request->filled('incident_type'), fn ($q) => $q->where('incident_type', $request->string('incident_type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('occurred_at', $request->string('date')))
            ->latest('occurred_at')
            ->limit(5000)
            ->get();

        return $this->exports->downloadCsv(
            'psg-occurrence-book.csv',
            $this->reports->exportHeaders(),
            collect($this->reports->exportRows($rows)),
        );
    }

    public function exportDailyPdf(Request $request): Response
    {
        $this->authorize('viewAny', Incident::class);
        Gate::authorize('viewReports');

        $data = $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'date' => ['required', 'date'],
        ]);

        $site = Site::query()->findOrFail($data['site_id']);

        if ($request->user()->mustStayInOwnRegion() && ! $request->user()->canAccessRegion($site->region_id)) {
            abort(403);
        }

        return $this->pdfDownload(
            $this->reports->dailySiteReport($site, $data['date']),
            $this->reports->filename($site, $data['date']),
        );
    }

    public function streamAttachment(Incident $incident, IncidentAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $incident);
        abort_unless($attachment->incident_id === $incident->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return (new InlineAttachmentResponse($attachment))->toResponse(request());
    }

    public function downloadAttachment(Incident $incident, IncidentAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $incident);
        abort_unless($attachment->incident_id === $incident->id, 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function destroyAttachment(Incident $incident, IncidentAttachment $attachment): RedirectResponse
    {
        $this->authorize('update', $incident);
        abort_unless($attachment->incident_id === $incident->id, 404);

        $this->attachments->delete($attachment);

        return back()->with('status', 'Photo removed.');
    }
}

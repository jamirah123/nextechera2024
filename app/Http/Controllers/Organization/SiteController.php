<?php

namespace App\Http\Controllers\Organization;

use App\Enums\SiteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreSiteRequest;
use App\Http\Requests\Organization\UpdateSiteRequest;
use App\Models\Client;
use App\Models\Deployment;
use App\Models\Region;
use App\Models\Site;
use App\Models\Supervisor;
use App\Services\EntityRelatedRecordsService;
use App\Services\EntityTimelineService;
use App\Services\ManpowerService;
use App\Services\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function __construct(
        private OrganizationService $organization,
        private ManpowerService $manpower,
        private EntityTimelineService $timeline,
        private EntityRelatedRecordsService $relatedRecords,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Site::class);

        $user = $request->user();
        $regionId = $user->regionId();

        $sites = Site::query()
            ->with(['client', 'region', 'supervisor'])
            ->search($request->string('q')->toString())
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('region_id') && ! $user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $request->integer('region_id')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->latest()
            ->paginate(table_per_page())
            ->withQueryString();

        $sites->getCollection()->transform(function (Site $site) {
            $site->setAttribute('manpower', $this->manpower->forSite($site));

            return $site;
        });

        return view('organization.sites.index', [
            'sites' => $sites,
            'regions' => Region::query()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('id', $regionId))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => SiteStatus::cases(),
            'filters' => $request->only(['q', 'status', 'region_id', 'client_id']),
            'canManage' => $user->can('create', Site::class),
            'canDelete' => $user->can('deleteAny', Site::class),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Site::class);

        return view('organization.sites.create', [
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'contract_status']),
            'regions' => Region::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'supervisors' => Supervisor::query()->active()->with('region:id,name')->orderBy('name')->get(),
            'statuses' => SiteStatus::cases(),
            'prefill' => [
                'client_id' => $request->integer('client_id') ?: null,
                'region_id' => $request->integer('region_id') ?: null,
                'supervisor_id' => $request->integer('supervisor_id') ?: null,
            ],
        ]);
    }

    public function store(StoreSiteRequest $request): RedirectResponse
    {
        $site = DB::transaction(function () use ($request) {
            $site = Site::query()->create($request->validated());
            $this->organization->syncSiteManpower($site, 'Initial manpower requirement');

            return $site;
        });

        return redirect()
            ->route('sites.show', $site)
            ->with('status', 'Security site created with manpower requirements.');
    }

    public function show(Site $site): View
    {
        $this->authorize('view', $site);

        $site->load([
            'client',
            'region',
            'supervisor',
            'creator',
            'updater',
        ]);

        $manpower = $this->manpower->forSite($site);

        return view('organization.sites.show', [
            'site' => $site,
            'manpower' => $manpower,
            'canManage' => request()->user()->can('update', $site),
            'canDelete' => request()->user()->can('delete', $site),
            'canDeploy' => request()->user()->can('create', Deployment::class),
            'timeline' => $this->timeline->for($site, request()->user()),
            'relatedPanels' => $this->relatedRecords->for($site),
            'lifecycle' => [
                'steps' => [
                    ['label' => 'Pending'],
                    ['label' => 'Active operations'],
                    ['label' => 'Fully staffed'],
                ],
                'current' => match ($site->status) {
                    SiteStatus::Pending => 0,
                    SiteStatus::Active => $manpower['shortage'] > 0 ? 1 : 2,
                    default => 2,
                },
                'terminal' => in_array($site->status, [SiteStatus::Closed, SiteStatus::ContractExpired, SiteStatus::Suspended], true)
                    ? $site->status->label()
                    : null,
                'terminal_tone' => $site->status->tone(),
            ],
        ]);
    }

    public function edit(Site $site): View
    {
        $this->authorize('update', $site);

        return view('organization.sites.edit', [
            'site' => $site,
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'contract_status']),
            'regions' => Region::query()->orderBy('name')->get(['id', 'name', 'code']),
            'supervisors' => Supervisor::query()->with('region:id,name')->orderBy('name')->get(),
            'statuses' => SiteStatus::cases(),
        ]);
    }

    public function update(UpdateSiteRequest $request, Site $site): RedirectResponse
    {
        DB::transaction(function () use ($request, $site): void {
            $before = [
                'required_guards' => (int) $site->required_guards,
                'required_day_guards' => (int) $site->required_day_guards,
                'required_night_guards' => (int) $site->required_night_guards,
                'required_day_armed_guards' => (int) $site->required_day_armed_guards,
                'required_day_unarmed_guards' => (int) $site->required_day_unarmed_guards,
                'required_night_armed_guards' => (int) $site->required_night_armed_guards,
                'required_night_unarmed_guards' => (int) $site->required_night_unarmed_guards,
            ];

            $site->update($request->validated());

            $changed = $before['required_guards'] !== (int) $site->required_guards
                || $before['required_day_guards'] !== (int) $site->required_day_guards
                || $before['required_night_guards'] !== (int) $site->required_night_guards
                || $before['required_day_armed_guards'] !== (int) $site->required_day_armed_guards
                || $before['required_day_unarmed_guards'] !== (int) $site->required_day_unarmed_guards
                || $before['required_night_armed_guards'] !== (int) $site->required_night_armed_guards
                || $before['required_night_unarmed_guards'] !== (int) $site->required_night_unarmed_guards;

            if ($changed) {
                $this->organization->syncSiteManpower($site, 'Manpower requirement updated');
            }
        });

        return redirect()
            ->route('sites.show', $site)
            ->with('status', 'Security site updated successfully.');
    }

    public function destroy(Site $site): RedirectResponse
    {
        $this->authorize('delete', $site);

        $site->delete();

        return redirect()
            ->route('sites.index')
            ->with('status', 'Security site archived successfully.');
    }
}

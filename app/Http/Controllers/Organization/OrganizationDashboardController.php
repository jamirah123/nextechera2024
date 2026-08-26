<?php

namespace App\Http\Controllers\Organization;

use App\Enums\SiteStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Region;
use App\Models\Site;
use App\Models\Supervisor;
use App\Services\ManpowerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationDashboardController extends Controller
{
    public function __invoke(Request $request, ManpowerService $manpower): View
    {
        $this->authorize('viewAny', Region::class);

        $companyManpower = $manpower->forCompany();

        return view('organization.index', [
            'stats' => [
                'regions' => Region::query()->count(),
                'active_regions' => Region::query()->active()->count(),
                'supervisors' => Supervisor::query()->count(),
                'active_supervisors' => Supervisor::query()->active()->count(),
                'clients' => Client::query()->count(),
                'active_clients' => Client::query()->where('contract_status', 'active')->count(),
                'sites' => Site::query()->count(),
                'active_sites' => Site::query()->active()->count(),
            ],
            'manpower' => $companyManpower,
            'recentSites' => Site::query()
                ->with(['client', 'region', 'supervisor'])
                ->latest()
                ->limit(6)
                ->get(),
            'understaffedPreview' => Site::query()
                ->where('status', SiteStatus::Active)
                ->where('required_guards', '>', 0)
                ->orderByDesc('required_guards')
                ->limit(5)
                ->get()
                ->map(fn (Site $site) => [
                    'site' => $site,
                    'manpower' => $manpower->forSite($site),
                ]),
            'canManage' => $request->user()->can('create', Region::class),
        ]);
    }
}

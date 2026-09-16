<?php

namespace App\Http\Controllers\Dashboards;

use App\Http\Controllers\Controller;
use App\Models\Guard;
use App\Models\Region;
use App\Models\Site;
use App\Services\Dashboards\OperationalDashboardService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OperationalDashboardController extends Controller
{
    public function __construct(private OperationalDashboardService $dashboards) {}

    public function company(): View
    {
        $this->authorizeOpsDashboard();

        return view('dashboards.operational.company', $this->dashboards->company());
    }

    public function region(Region $region): View
    {
        $this->authorizeOpsDashboard();
        Gate::authorize('view', $region);

        return view('dashboards.operational.region', $this->dashboards->region($region));
    }

    public function site(Site $site): View
    {
        $this->authorizeOpsDashboard();
        Gate::authorize('view', $site);

        return view('dashboards.operational.site', $this->dashboards->site($site));
    }

    public function guard(Guard $guard): View
    {
        $this->authorizeOpsDashboard();
        Gate::authorize('view', $guard);

        return view('dashboards.operational.guard', $this->dashboards->guard($guard));
    }

    private function authorizeOpsDashboard(): void
    {
        Gate::authorize('viewReports');
    }
}

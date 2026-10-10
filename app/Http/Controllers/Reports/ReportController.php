<?php

namespace App\Http\Controllers\Reports;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\ReportArchive;
use App\Models\Site;
use App\Models\User;
use App\Services\Reports\MonthlyShiftCalculationService;
use App\Services\Reports\OperationalReportService;
use App\Services\Reports\ReportArchiveService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private MonthlyShiftCalculationService $monthlyShifts,
        private OperationalReportService $reports,
        private ReportArchiveService $archives,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeReports();

        return view('reports.index', [
            'cards' => $this->cardsFor($request->user()),
        ]);
    }

    public function history(Request $request): View
    {
        $this->authorizeReports();

        $user = $request->user();
        $keys = $this->visibleArchiveKeys($user);
        $filters = [
            'q' => $request->string('q')->toString(),
            'report_key' => $request->string('report_key')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];

        $archives = ReportArchive::query()
            ->with('author:id,name')
            ->whereIn('report_key', $keys)
            ->when($user->mustStayInOwnRegion(), fn ($query) => $query->where('user_id', $user->id))
            ->when($filters['report_key'] !== '' && in_array($filters['report_key'], $keys, true), fn ($query) => $query->where('report_key', $filters['report_key']))
            ->when($filters['from'] !== '', fn ($query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn ($query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->search($filters['q'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('reports.history', [
            'archives' => $archives,
            'filters' => $filters,
            'reportKeys' => $keys,
        ]);
    }

    public function storeHistory(Request $request): RedirectResponse
    {
        $key = $request->string('report')->toString();
        abort_unless(array_key_exists($key, ReportArchive::LABELS), 404);
        $this->authorizeReportKey($key);

        $bundle = $this->bundle($key, $request);
        $archive = $this->archives->store(
            $request->user(),
            $bundle['key'],
            $bundle['title'],
            $bundle['period'],
            $bundle['filters'],
            $bundle['filename'],
            $bundle['headers'],
            $bundle['rows'],
        );

        return redirect()
            ->route('reports.history', ['q' => $archive->period_label])
            ->with('status', 'Saved '.$archive->title.' for '.$archive->period_label.'.');
    }

    public function downloadHistory(Request $request, ReportArchive $archive): StreamedResponse
    {
        $this->authorizeReports();
        $user = $request->user();
        abort_unless(in_array($archive->report_key, $this->visibleArchiveKeys($user), true), 403);
        abort_if($user->mustStayInOwnRegion() && $archive->user_id !== $user->id, 403);

        return $this->archives->download($archive);
    }

    public function monthlyShifts(Request $request): View
    {
        $this->authorizeReportKey('monthly_shifts');

        $filters = $this->monthFilters($request);
        $rows = $this->monthlyShifts->paginate($filters);
        $totals = $this->monthlyShifts->summaryTotals($filters);

        return view('reports.monthly-shifts', [
            'rows' => $rows,
            'filters' => $filters,
            'regions' => Region::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'code', 'region_id']),
            'totals' => $totals,
            'exportQuery' => $this->cleanQuery($filters),
        ]);
    }

    public function exportMonthlyShifts(Request $request): StreamedResponse
    {
        return $this->streamBundle('monthly_shifts', $request);
    }

    public function dailyShifts(Request $request): View
    {
        $this->authorizeReportKey('daily_shifts');

        $filters = $this->dailyFilters($request);
        $report = $this->reports->dailyShifts($filters);

        return view('reports.daily-shifts', [
            ...$report,
            'filters' => $filters,
            'regions' => Region::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'code']),
            'exportQuery' => $this->cleanQuery($filters),
        ]);
    }

    public function exportDailyShifts(Request $request): StreamedResponse
    {
        return $this->streamBundle('daily_shifts', $request);
    }

    public function weeklyShifts(Request $request): View
    {
        $this->authorizeReportKey('weekly_shifts');

        $filters = $this->weeklyFilters($request);
        $report = $this->reports->weeklyShifts($filters);

        return view('reports.weekly-shifts', [
            ...$report,
            'filters' => $filters,
            'regions' => Region::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'code']),
            'exportQuery' => $this->cleanQuery($filters),
        ]);
    }

    public function exportWeeklyShifts(Request $request): StreamedResponse
    {
        return $this->streamBundle('weekly_shifts', $request);
    }

    public function guards(Request $request): View
    {
        $this->authorizeReportKey('guards');

        $filters = $request->only(['region_id', 'employment_status', 'operational_status']);
        $report = $this->reports->guards($filters);

        return view('reports.guards', [
            ...$report,
            'filters' => $filters,
            'regions' => Region::query()->orderBy('name')->get(['id', 'name']),
            'exportQuery' => $this->cleanQuery($filters),
        ]);
    }

    public function exportGuards(Request $request): StreamedResponse
    {
        return $this->streamBundle('guards', $request);
    }

    public function deployments(Request $request): View
    {
        $this->authorizeReportKey('deployments');

        $filters = $this->deploymentFilters($request);

        return view('reports.deployments', [
            ...$this->reports->deployments($filters),
            'filters' => $filters,
            'regions' => Region::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'code']),
            'exportQuery' => $this->cleanQuery([
                ...$filters,
                'current_only' => $filters['current_only'] ? '1' : '0',
            ]),
        ]);
    }

    public function exportDeployments(Request $request): StreamedResponse
    {
        return $this->streamBundle('deployments', $request);
    }

    public function hr(Request $request): View
    {
        $this->authorizeReportKey('hr');

        $filters = $this->hrFilters($request);

        return view('reports.hr', $this->reports->hr($filters) + [
            'filters' => $filters,
            'exportQuery' => $this->cleanQuery($filters),
        ]);
    }

    public function exportHr(Request $request): StreamedResponse
    {
        return $this->streamBundle('hr', $request);
    }

    /**
     * @return list<array{key: string, title: string, description: string, href: string, tone: string}>
     */
    private function cardsFor(?User $user): array
    {
        $cards = [
            [
                'key' => 'purchases',
                'title' => 'Purchases',
                'description' => 'Supplier bills for uniforms, kit and operational supplies.',
                'href' => route('ledger.purchases.index'),
                'tone' => 'violet',
            ],
            [
                'key' => 'assets',
                'title' => 'Assets & uniforms',
                'description' => 'Kit issues, returns and replacement cost recovery.',
                'href' => route('assets.index'),
                'tone' => 'indigo',
            ],
            [
                'key' => 'guards',
                'title' => 'Guards',
                'description' => 'Employment and operational status across the company.',
                'href' => route('reports.guards'),
                'tone' => 'emerald',
            ],
            [
                'key' => 'monthly_shifts',
                'title' => 'Monthly shift summary',
                'description' => 'Normal, overtime and totals by guard for payroll-ready month-end review.',
                'href' => route('reports.monthly-shifts'),
                'tone' => 'brand',
            ],
            [
                'key' => 'payroll',
                'title' => 'Payroll runs',
                'description' => 'Calculate, approve and pay guards from completed shifts with payslips and bank export.',
                'href' => route('payroll.index'),
                'tone' => 'emerald',
            ],
            [
                'key' => 'daily_shifts',
                'title' => 'Daily shifts',
                'description' => 'Today or any date: scheduled, completed, missed and overtime counts.',
                'href' => route('reports.daily-shifts'),
                'tone' => 'sky',
            ],
            [
                'key' => 'weekly_shifts',
                'title' => 'Weekly shifts',
                'description' => 'Week board with completion and overtime rollups.',
                'href' => route('reports.weekly-shifts'),
                'tone' => 'indigo',
            ],
            [
                'key' => 'deployments',
                'title' => 'Deployments',
                'description' => 'Active deployments and recent transfers.',
                'href' => route('reports.deployments'),
                'tone' => 'amber',
            ],
            [
                'key' => 'manpower',
                'title' => 'Manpower coverage',
                'description' => 'Required, normal, overtime, remaining shortage, and the normal manpower deficit.',
                'href' => route('manpower.coverage'),
                'tone' => 'violet',
            ],
            [
                'key' => 'manpower',
                'title' => 'Manpower deficit',
                'description' => 'Sites and guards where overtime is covering a normal manpower deficit.',
                'href' => route('manpower.deficit-report'),
                'tone' => 'amber',
            ],
            [
                'key' => 'hr',
                'title' => 'HR summary',
                'description' => 'Leave, absences and desertions for a selected period.',
                'href' => route('reports.hr'),
                'tone' => 'rose',
            ],
            [
                'key' => 'uniform_exemptions',
                'title' => 'Uniform Charge Exemptions',
                'description' => 'Guards approved to pay no uniform charge, with effective dates and reasons.',
                'href' => route('reports.uniform-exemptions'),
                'tone' => 'amber',
            ],
        ];

        return array_values(array_filter(
            $cards,
            fn (array $card) => $this->userCanSeeReport($user, $card['key']),
        ));
    }

    private function userCanSeeReport(?User $user, string $key): bool
    {
        if ($user === null) {
            return false;
        }

        if (in_array($user->role, [UserRole::SuperAdmin, UserRole::ManagingDirector], true)) {
            return true;
        }

        if ($user->role === UserRole::ProcurementOfficer) {
            return in_array($key, ['purchases', 'assets', 'guards'], true);
        }

        if ($user->role === UserRole::FinanceManager) {
            return in_array($key, [
                'monthly_shifts',
                'payroll',
                'guards',
                'purchases',
                'assets',
                'deployments',
                'manpower',
                'uniform_exemptions',
            ], true);
        }

        if ($user->role === UserRole::HrManager) {
            return in_array($key, ['guards', 'hr', 'assets', 'deployments', 'manpower', 'uniform_exemptions'], true);
        }

        return in_array($key, [
            'monthly_shifts',
            'daily_shifts',
            'weekly_shifts',
            'guards',
            'deployments',
            'manpower',
            'hr',
        ], true);
    }

    /** @return array{year: int, month: int, region_id: int|null, site_id: int|null} */
    private function monthFilters(Request $request): array
    {
        return [
            'year' => (int) $request->input('year', now()->year),
            'month' => (int) $request->input('month', now()->month),
            'region_id' => $request->integer('region_id') ?: null,
            'site_id' => $request->integer('site_id') ?: null,
        ];
    }

    /** @return array{date: string, region_id: int|null, site_id: int|null} */
    private function dailyFilters(Request $request): array
    {
        return [
            'date' => $request->input('date', now()->toDateString()),
            'region_id' => $request->integer('region_id') ?: null,
            'site_id' => $request->integer('site_id') ?: null,
        ];
    }

    /** @return array{week_start: string, region_id: int|null, site_id: int|null} */
    private function weeklyFilters(Request $request): array
    {
        return [
            'week_start' => $request->input('week_start', now()->startOfWeek()->toDateString()),
            'region_id' => $request->integer('region_id') ?: null,
            'site_id' => $request->integer('site_id') ?: null,
        ];
    }

    /** @return array{current_only: bool, region_id: int|null, site_id: int|null, from: mixed, to: mixed} */
    private function deploymentFilters(Request $request): array
    {
        return [
            'current_only' => $request->boolean('current_only', true),
            'region_id' => $request->integer('region_id') ?: null,
            'site_id' => $request->integer('site_id') ?: null,
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ];
    }

    /** @return array{from: string, to: string} */
    private function hrFilters(Request $request): array
    {
        return [
            'from' => $request->input('from', now()->startOfMonth()->toDateString()),
            'to' => $request->input('to', now()->toDateString()),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function cleanQuery(array $filters): array
    {
        return array_filter($filters, fn ($value) => $value !== null && $value !== '');
    }

    private function streamBundle(string $key, Request $request): StreamedResponse
    {
        $this->authorizeReportKey($key);
        $bundle = $this->bundle($key, $request);
        $archive = $this->archives->store(
            $request->user(),
            $bundle['key'],
            $bundle['title'],
            $bundle['period'],
            $bundle['filters'],
            $bundle['filename'],
            $bundle['headers'],
            $bundle['rows'],
        );

        return $this->archives->download($archive);
    }

    /**
     * @return array{key: string, title: string, period: string, filters: array<string, mixed>, filename: string, headers: list<string>, rows: iterable<int, list<string|int|float|null>>}
     */
    private function bundle(string $key, Request $request): array
    {
        return match ($key) {
            'monthly_shifts' => $this->monthlyBundle($request),
            'daily_shifts' => $this->dailyBundle($request),
            'weekly_shifts' => $this->weeklyBundle($request),
            'guards' => $this->guardsBundle($request),
            'deployments' => $this->deploymentsBundle($request),
            'hr' => $this->hrBundle($request),
            default => abort(404),
        };
    }

    /**
     * @return array{key: string, title: string, period: string, filters: array<string, mixed>, filename: string, headers: list<string>, rows: iterable<int, list<string|int|float|null>>}
     */
    private function monthlyBundle(Request $request): array
    {
        $filters = $this->monthFilters($request);
        $rows = $this->monthlyShifts->calculate($filters);

        return [
            'key' => 'monthly_shifts',
            'title' => ReportArchive::labelFor('monthly_shifts'),
            'period' => $this->withPlace(Carbon::create($filters['year'], $filters['month'], 1)->format('F Y'), $filters),
            'filters' => $filters,
            'filename' => sprintf('psg-monthly-shifts-%04d-%02d', $filters['year'], $filters['month']),
            'headers' => $this->monthlyShifts->exportHeaders(),
            'rows' => $this->monthlyShifts->exportRows($rows),
        ];
    }

    /**
     * @return array{key: string, title: string, period: string, filters: array<string, mixed>, filename: string, headers: list<string>, rows: iterable<int, list<string|int|float|null>>}
     */
    private function dailyBundle(Request $request): array
    {
        $filters = $this->dailyFilters($request);
        $report = $this->reports->dailyShifts($filters);

        return [
            'key' => 'daily_shifts',
            'title' => ReportArchive::labelFor('daily_shifts'),
            'period' => $this->withPlace(Carbon::parse($filters['date'])->format('d M Y'), $filters),
            'filters' => $filters,
            'filename' => 'psg-daily-shifts-'.$filters['date'],
            'headers' => ['#', 'Reference', 'Employment ID', 'Guard', 'Site', 'Period', 'Type', 'Status', 'Start', 'End'],
            'rows' => $report['rows']->values()->map(fn ($shift, int $index) => [
                $index + 1,
                $shift->reference,
                $shift->assignedGuard?->employment_id,
                $shift->assignedGuard?->full_name,
                $shift->site?->name,
                $shift->period->label(),
                $shift->shift_type->label(),
                $shift->status->label(),
                $shift->starts_at->format('H:i'),
                $shift->ends_at->format('H:i'),
            ])->all(),
        ];
    }

    /**
     * @return array{key: string, title: string, period: string, filters: array<string, mixed>, filename: string, headers: list<string>, rows: iterable<int, list<string|int|float|null>>}
     */
    private function weeklyBundle(Request $request): array
    {
        $filters = $this->weeklyFilters($request);
        $report = $this->reports->weeklyShifts($filters);
        $period = Carbon::parse($report['week_start'])->format('d M Y').' – '.Carbon::parse($report['week_end'])->format('d M Y');

        return [
            'key' => 'weekly_shifts',
            'title' => ReportArchive::labelFor('weekly_shifts'),
            'period' => $this->withPlace($period, $filters),
            'filters' => $filters,
            'filename' => 'psg-weekly-shifts-'.$report['week_start'].'-to-'.$report['week_end'],
            'headers' => ['#', 'Date', 'Reference', 'Employment ID', 'Guard', 'Site', 'Period', 'Type', 'Status', 'Start', 'End'],
            'rows' => $report['rows']->values()->map(fn ($shift, int $index) => [
                $index + 1,
                $shift->shift_date->toDateString(),
                $shift->reference,
                $shift->assignedGuard?->employment_id,
                $shift->assignedGuard?->full_name,
                $shift->site?->name,
                $shift->period->label(),
                $shift->shift_type->label(),
                $shift->status->label(),
                $shift->starts_at->format('H:i'),
                $shift->ends_at->format('H:i'),
            ])->all(),
        ];
    }

    /**
     * @return array{key: string, title: string, period: string, filters: array<string, mixed>, filename: string, headers: list<string>, rows: iterable<int, list<string|int|float|null>>}
     */
    private function guardsBundle(Request $request): array
    {
        $filters = $request->only(['region_id', 'employment_status', 'operational_status']);
        $report = $this->reports->guards($filters);
        $extra = array_filter([
            filled($filters['employment_status'] ?? null) ? (string) $filters['employment_status'] : null,
            filled($filters['operational_status'] ?? null) ? (string) $filters['operational_status'] : null,
        ]);

        return [
            'key' => 'guards',
            'title' => ReportArchive::labelFor('guards'),
            'period' => $this->withPlace($extra === [] ? 'All guards' : 'All guards · '.implode(' · ', $extra), $filters),
            'filters' => $filters,
            'filename' => 'psg-guards-report',
            'headers' => ['#', 'Employment ID', 'Name', 'Region', 'Site', 'Employment', 'Operational'],
            'rows' => $report['rows']->values()->map(fn ($guard, int $index) => [
                $index + 1,
                $guard->employment_id,
                $guard->full_name,
                $guard->region?->name,
                $guard->currentSite?->name,
                $guard->employment_status->label(),
                $guard->operational_status->label(),
            ])->all(),
        ];
    }

    /**
     * @return array{key: string, title: string, period: string, filters: array<string, mixed>, filename: string, headers: list<string>, rows: iterable<int, list<string|int|float|null>>}
     */
    private function deploymentsBundle(Request $request): array
    {
        $filters = $this->deploymentFilters($request);
        $report = $this->reports->deployments($filters);
        $headers = ['#', 'Record type', 'Employment ID', 'Guard', 'Site / From', 'To site', 'Status', 'Date'];
        $data = collect();
        $serial = 1;

        foreach ($report['deployments'] as $deployment) {
            $data->push([
                $serial++,
                'Deployment',
                $deployment->assignedGuard?->employment_id,
                $deployment->assignedGuard?->full_name,
                $deployment->site?->name,
                '',
                $deployment->status->label(),
                optional($deployment->start_date)?->toDateString() ?? '',
            ]);
        }

        foreach ($report['transfers'] as $transfer) {
            $data->push([
                $serial++,
                'Transfer',
                '',
                '',
                $transfer->fromSite?->name,
                $transfer->toSite?->name,
                'Transferred',
                optional($transfer->effective_at)?->toDateString() ?? '',
            ]);
        }

        $period = $filters['current_only'] ? 'Current deployments' : 'All deployments';

        if (filled($filters['from']) || filled($filters['to'])) {
            $from = filled($filters['from']) ? Carbon::parse($filters['from'])->format('d M Y') : 'Start';
            $to = filled($filters['to']) ? Carbon::parse($filters['to'])->format('d M Y') : 'Today';
            $period = $from.' – '.$to;
        }

        return [
            'key' => 'deployments',
            'title' => ReportArchive::labelFor('deployments'),
            'period' => $this->withPlace($period, $filters),
            'filters' => $filters,
            'filename' => 'psg-deployments-report',
            'headers' => $headers,
            'rows' => $data->all(),
        ];
    }

    /**
     * @return array{key: string, title: string, period: string, filters: array<string, mixed>, filename: string, headers: list<string>, rows: iterable<int, list<string|int|float|null>>}
     */
    private function hrBundle(Request $request): array
    {
        $filters = $this->hrFilters($request);
        $report = $this->reports->hr($filters);
        $headers = ['#', 'Category', 'Employment ID', 'Guard', 'Detail', 'Status', 'From / Date', 'To', 'Site'];
        $data = collect();
        $serial = 1;

        foreach ($report['leaves'] as $leave) {
            $data->push([
                $serial++,
                'Leave',
                $leave->assignedGuard?->employment_id,
                $leave->assignedGuard?->full_name,
                $leave->typeLabel(),
                $leave->status->label(),
                $leave->start_date->toDateString(),
                $leave->end_date->toDateString(),
                '',
            ]);
        }

        foreach ($report['absences'] as $absence) {
            $data->push([
                $serial++,
                'Absence',
                $absence->assignedGuard?->employment_id,
                $absence->assignedGuard?->full_name,
                $absence->reason?->label() ?? '',
                'Recorded',
                $absence->absence_date->toDateString(),
                '',
                $absence->site?->name,
            ]);
        }

        foreach ($report['desertions'] as $desertion) {
            $data->push([
                $serial++,
                'Desertion',
                $desertion->assignedGuard?->employment_id,
                $desertion->assignedGuard?->full_name,
                '',
                $desertion->hr_status->label(),
                $desertion->date_reported->toDateString(),
                '',
                $desertion->lastKnownSite?->name,
            ]);
        }

        $period = Carbon::parse($filters['from'])->format('d M Y').' – '.Carbon::parse($filters['to'])->format('d M Y');

        return [
            'key' => 'hr',
            'title' => ReportArchive::labelFor('hr'),
            'period' => $period,
            'filters' => $filters,
            'filename' => 'psg-hr-report-'.$filters['from'].'-to-'.$filters['to'],
            'headers' => $headers,
            'rows' => $data->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function withPlace(string $period, array $filters): string
    {
        $place = [];

        if (! empty($filters['site_id'])) {
            $place[] = Site::query()->whereKey($filters['site_id'])->value('name');
        }

        if (! empty($filters['region_id'])) {
            $place[] = Region::query()->whereKey($filters['region_id'])->value('name');
        }

        $place = array_values(array_filter($place));

        return $place === [] ? $period : $period.' · '.implode(' · ', $place);
    }

    /**
     * @return list<string>
     */
    private function visibleArchiveKeys(User $user): array
    {
        return array_values(array_filter(
            array_keys(ReportArchive::LABELS),
            fn (string $key) => $this->userCanSeeReport($user, $key),
        ));
    }

    private function authorizeReports(): void
    {
        Gate::authorize('viewReports');
    }

    private function authorizeReportKey(string $key): void
    {
        $this->authorizeReports();

        abort_unless($this->userCanSeeReport(request()->user(), $key), 403);
    }
}

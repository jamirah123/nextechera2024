<?php

namespace App\Http\Controllers\Hr;

use App\Enums\LeaveStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Concerns\ServesPdfDownload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreLeaveRequest;
use App\Models\Guard;
use App\Models\Leave;
use App\Models\LeaveEntitlement;
use App\Models\LeaveTypeConfig;
use App\Models\Staff;
use App\Services\Documents\LetterPdfService;
use App\Services\LeaveService;
use App\Services\ReportExportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveController extends Controller
{
    use ServesPdfDownload;

    public function __construct(
        private LeaveService $leaves,
        private LetterPdfService $letters,
        private ReportExportService $exports,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Leave::class);
        $this->leaves->syncDue();

        $today = now()->toDateString();
        $weekEnd = now()->addDays(7)->toDateString();

        $leaves = $this->filteredLeaves($request)
            ->with([
                'assignedGuard:id,employment_id,full_name,region_id,current_site_id,position_id',
                'assignedGuard.region:id,name',
                'assignedGuard.position:id,name',
                'assignedGuard.currentSite:id,name',
                'staffMember:id,employment_id,full_name,region_id,job_title,department,position_id',
                'staffMember.region:id,name',
                'staffMember.position:id,name',
                'leaveTypeConfig:id,name,code',
                'approver:id,name',
            ])
            ->latest('start_date')
            ->paginate(table_per_page())
            ->withQueryString();

        $onLeave = Leave::query()
            ->where('status', LeaveStatus::Approved)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN guard_id IS NOT NULL THEN 1 ELSE 0 END) as guards_total, SUM(CASE WHEN staff_id IS NOT NULL AND guard_id IS NULL THEN 1 ELSE 0 END) as staff_total')
            ->first();

        return view('hr.leaves.index', [
            'leaves' => $leaves,
            'statuses' => LeaveStatus::cases(),
            'types' => LeaveTypeConfig::query()->where('is_active', true)->orderBy('name')->get(),
            'regions' => \App\Support\Performance\ReferenceData::regions(),
            'sites' => \App\Support\Performance\ReferenceData::sites(),
            'supervisors' => \App\Support\Performance\ReferenceData::supervisors(),
            'filters' => $request->only(['q', 'status', 'leave_type', 'leave_type_id', 'from', 'to', 'employee_type', 'region_id', 'site_id', 'supervisor_id']),
            'canManage' => $request->user()->can('create', Leave::class),
            'canManageTypes' => $request->user()->can('create', LeaveTypeConfig::class),
            'stats' => [
                'on_leave' => (int) ($onLeave->total ?? 0),
                'guards_on_leave' => (int) ($onLeave->guards_total ?? 0),
                'staff_on_leave' => (int) ($onLeave->staff_total ?? 0),
                'pending' => Leave::query()->where('status', LeaveStatus::Pending)->count(),
                'returning_today' => Leave::query()->where('status', LeaveStatus::Approved)->where('expected_return_date', $today)->count(),
                'returning_week' => Leave::query()->where('status', LeaveStatus::Approved)->whereBetween('expected_return_date', [$today, $weekEnd])->count(),
                'low_balance' => LeaveEntitlement::query()
                    ->where('year', now()->year)
                    ->where('opening_balance', '>', 0)
                    ->whereRaw('(opening_balance + accrued + carried_forward + adjustments - taken - pending - expired) <= 3')
                    ->count(),
                'upcoming' => Leave::query()->where('status', LeaveStatus::Approved)->where('start_date', '>', $today)->where('start_date', '<=', $weekEnd)->count(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Leave::class);

        $rows = $this->filteredLeaves($request)
            ->with([
                'assignedGuard:id,employment_id,full_name,region_id,current_site_id,position_id',
                'assignedGuard.region:id,name',
                'assignedGuard.position:id,name',
                'assignedGuard.currentSite:id,name',
                'staffMember:id,employment_id,full_name,region_id,job_title,department,position_id',
                'staffMember.region:id,name',
                'staffMember.position:id,name',
                'leaveTypeConfig:id,name,code,is_paid,pay_percent',
            ])
            ->latest('start_date')
            ->get()
            ->map(function (Leave $leave): array {
                $employee = $leave->assignedGuard ?? $leave->staffMember;

                return [
                    $leave->employeeCode(),
                    $leave->employeeName(),
                    $leave->guard_id ? 'Guard' : 'Staff',
                    $employee?->position?->name ?? $leave->staffMember?->job_title,
                    $leave->staffMember?->department,
                    $employee?->region?->name,
                    $leave->assignedGuard?->currentSite?->name,
                    $leave->typeLabel(),
                    $leave->leaveTypeConfig?->is_paid ? 'Paid '.$leave->leaveTypeConfig->pay_percent.'%' : 'Unpaid',
                    $leave->start_date->toDateString(),
                    $leave->end_date->toDateString(),
                    optional($leave->expected_return_date)?->toDateString(),
                    $leave->days,
                    $leave->statusLabel(),
                    $leave->conflicting_shifts_count > 0 ? 'Replacement required' : '',
                ];
            });

        return $this->exports->downloadCsv('leave-register.csv', [
            'Employee ID',
            'Employee',
            'Employee type',
            'Position',
            'Department',
            'Region',
            'Site',
            'Leave type',
            'Pay',
            'Start date',
            'End date',
            'Return date',
            'Days',
            'Status',
            'Deployment impact',
        ], $rows);
    }

    public function create(): View
    {
        $this->authorize('create', Leave::class);

        return view('hr.leaves.create', [
            'guards' => Guard::query()->activeEmployment()->orderBy('full_name')->get(['id', 'employment_id', 'full_name']),
            'staff' => Staff::query()->where('employment_status', 'active')->orderBy('full_name')->get(['id', 'employment_id', 'full_name', 'job_title']),
            'types' => LeaveTypeConfig::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreLeaveRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            if ($request->boolean('approve_now') && (
                $request->user()->isSuperAdmin() || $request->user()->hasRole(UserRole::HrManager)
            )) {
                $data['status'] = LeaveStatus::Approved->value;
            }

            $leave = $this->leaves->create($data, $request->file('document'));
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['leave' => $e->getMessage()]);
        }

        return redirect()->route('leaves.show', $leave)->with('status', 'Leave request saved.');
    }

    public function show(Leave $leave): View
    {
        $this->authorize('view', $leave);

        $leave->load(['assignedGuard.region', 'staffMember.region', 'leaveTypeConfig', 'approver', 'requester', 'creator']);

        return view('hr.leaves.show', [
            'leave' => $leave,
            'canApprove' => request()->user()->can('approve', $leave) && $leave->status === LeaveStatus::Pending,
            'canManage' => request()->user()->can('update', $leave),
        ]);
    }

    public function approve(Request $request, Leave $leave): RedirectResponse
    {
        $this->authorize('approve', $leave);

        try {
            $this->leaves->approve($leave, $request->input('notes'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['leave' => $e->getMessage()]);
        }

        return back()->with('status', 'Leave approved. Scheduled shifts stay on the original guard and are flagged for replacement.');
    }

    public function reject(Request $request, Leave $leave): RedirectResponse
    {
        $this->authorize('approve', $leave);

        try {
            $this->leaves->reject($leave, $request->input('notes'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['leave' => $e->getMessage()]);
        }

        return back()->with('status', 'Leave rejected.');
    }

    public function cancel(Request $request, Leave $leave): RedirectResponse
    {
        $this->authorize('update', $leave);

        try {
            $this->leaves->cancel($leave, $request->input('notes'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['leave' => $e->getMessage()]);
        }

        return back()->with('status', 'Leave cancelled.');
    }

    public function complete(Leave $leave): RedirectResponse
    {
        $this->authorize('update', $leave);

        try {
            $this->leaves->complete($leave);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['leave' => $e->getMessage()]);
        }

        return back()->with('status', 'Leave marked completed. Guard status restored.');
    }

    public function downloadLetter(Leave $leave): Response
    {
        $this->authorize('view', $leave);

        try {
            $binary = $this->letters->leaveApproval($leave);
        } catch (InvalidArgumentException $e) {
            abort(403, $e->getMessage());
        }

        $reference = 'LVE-'.str_pad((string) $leave->id, 5, '0', STR_PAD_LEFT);

        return $this->pdfDownload($binary, 'leave-approval-'.$reference.'.pdf');
    }

    /**
     * @return Builder<Leave>
     */
    private function filteredLeaves(Request $request): Builder
    {
        return Leave::query()
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('leave_type'), fn ($q) => $q->where('leave_type', $request->string('leave_type')))
            ->when($request->filled('leave_type_id'), fn ($q) => $q->where('leave_type_id', $request->integer('leave_type_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('end_date', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('start_date', '<=', $request->string('to')))
            ->when($request->string('employee_type')->toString() === 'guard', fn ($q) => $q->whereNotNull('guard_id'))
            ->when($request->string('employee_type')->toString() === 'staff', fn ($q) => $q->whereNotNull('staff_id')->whereNull('guard_id'))
            ->when($request->filled('region_id'), function ($q) use ($request) {
                $regionId = $request->integer('region_id');
                $q->where(function ($inner) use ($regionId): void {
                    $inner->whereHas('assignedGuard', fn ($guard) => $guard->where('region_id', $regionId))
                        ->orWhereHas('staffMember', fn ($staff) => $staff->where('region_id', $regionId));
                });
            })
            ->when($request->filled('site_id'), function ($q) use ($request) {
                $q->whereHas('assignedGuard', fn ($guard) => $guard->where('current_site_id', $request->integer('site_id')));
            })
            ->when($request->filled('supervisor_id'), function ($q) use ($request) {
                $q->whereHas('assignedGuard', fn ($guard) => $guard->where('current_supervisor_id', $request->integer('supervisor_id')));
            });
    }
}

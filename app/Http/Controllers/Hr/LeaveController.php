<?php

namespace App\Http\Controllers\Hr;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\UserRole;
use App\Http\Controllers\Concerns\ServesPdfDownload;
use App\Http\Controllers\Controller;
use App\Models\Guard;
use App\Models\Leave;
use App\Services\Documents\LetterPdfService;
use App\Services\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class LeaveController extends Controller
{
    use ServesPdfDownload;

    public function __construct(
        private LeaveService $leaves,
        private LetterPdfService $letters,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Leave::class);

        $leaves = Leave::query()
            ->with(['assignedGuard:id,employment_id,full_name', 'approver:id,name'])
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('leave_type'), fn ($q) => $q->where('leave_type', $request->string('leave_type')))
            ->latest('start_date')
            ->paginate(table_per_page())
            ->withQueryString();

        return view('hr.leaves.index', [
            'leaves' => $leaves,
            'statuses' => LeaveStatus::cases(),
            'types' => LeaveType::cases(),
            'filters' => $request->only(['q', 'status', 'leave_type']),
            'canManage' => $request->user()->can('create', Leave::class),
            'stats' => [
                'pending' => Leave::query()->where('status', LeaveStatus::Pending)->count(),
                'approved' => Leave::query()->where('status', LeaveStatus::Approved)->count(),
                'completed' => Leave::query()->where('status', LeaveStatus::Completed)->count(),
                'conflicts' => Leave::query()->where('conflicting_shifts_count', '>', 0)->where('status', LeaveStatus::Approved)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Leave::class);

        return view('hr.leaves.create', [
            'guards' => Guard::query()->activeEmployment()->orderBy('full_name')->get(['id', 'employment_id', 'full_name']),
            'types' => LeaveType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Leave::class);

        $data = $request->validate([
            'guard_id' => ['required', 'exists:guards,id'],
            'leave_type' => ['required', Rule::in(LeaveType::values())],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'expected_return_date' => ['nullable', 'date', 'after_or_equal:end_date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'approve_now' => ['sometimes', 'boolean'],
        ]);

        try {
            if ($request->boolean('approve_now') && (
                $request->user()->isSuperAdmin() || $request->user()->hasRole(UserRole::HrManager)
            )) {
                $data['status'] = LeaveStatus::Approved->value;
            }

            $leave = $this->leaves->create($data);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['leave' => $e->getMessage()]);
        }

        return redirect()->route('leaves.show', $leave)->with('status', 'Leave request saved.');
    }

    public function show(Leave $leave): View
    {
        $this->authorize('view', $leave);

        $leave->load(['assignedGuard.region', 'approver', 'requester', 'creator']);

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

        return back()->with('status', 'Leave approved. Conflicting scheduled shifts were cancelled.');
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
}

<?php

namespace App\Http\Controllers\Operations;

use App\Enums\WorkOrderCategory;
use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use App\Support\Access\Access;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class WorkOrderController extends Controller
{
    public function __construct(private WorkOrderService $workOrders)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', WorkOrder::class);

        $user = $request->user();
        $regionId = $user->regionId();
        $canManage = $user->can('create', WorkOrder::class);

        $workOrders = WorkOrder::query()
            ->with(['assignee:id,name', 'region:id,name'])
            ->search($request->string('q')->toString())
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where(function ($inner) use ($regionId, $user): void {
                $inner->where('region_id', $regionId)
                    ->orWhere('assigned_to', $user->id)
                    ->orWhereNull('region_id');
            }))
            ->when(! $canManage, fn ($q) => $q->where('assigned_to', $user->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->integer('assigned_to')))
            ->when($request->boolean('mine'), fn ($q) => $q->where('assigned_to', $user->id))
            ->when($request->boolean('overdue'), fn ($q) => $q->open()->whereNotNull('due_at')->where('due_at', '<', now()))
            ->latest('due_at')
            ->paginate(table_per_page())
            ->withQueryString();

        $statsBase = WorkOrder::query()
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where(function ($inner) use ($regionId, $user): void {
                $inner->where('region_id', $regionId)
                    ->orWhere('assigned_to', $user->id)
                    ->orWhereNull('region_id');
            }))
            ->when(! $canManage, fn ($q) => $q->where('assigned_to', $user->id));

        return view('operations.work-orders.index', [
            'workOrders' => $workOrders,
            'regions' => Region::query()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('id', $regionId))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'categories' => WorkOrderCategory::cases(),
            'statuses' => WorkOrderStatus::cases(),
            'priorities' => WorkOrderPriority::cases(),
            'assignees' => $this->assignableUsers($user),
            'filters' => $request->only(['q', 'status', 'category', 'assigned_to', 'mine', 'overdue']),
            'canManage' => $canManage,
            'stats' => [
                'open' => (clone $statsBase)->open()->count(),
                'mine' => WorkOrder::query()->open()->where('assigned_to', $user->id)->count(),
                'overdue' => (clone $statsBase)->open()->whereNotNull('due_at')->where('due_at', '<', now())->count(),
                'due_week' => (clone $statsBase)->open()->whereBetween('due_at', [now(), now()->addDays(7)])->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', WorkOrder::class);

        $user = $request->user();

        return view('operations.work-orders.create', [
            'categories' => WorkOrderCategory::cases(),
            'priorities' => WorkOrderPriority::cases(),
            'assignees' => $this->assignableUsers($user),
            'regions' => Region::query()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('id', $user->regionId()))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', WorkOrder::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::enum(WorkOrderCategory::class)],
            'priority' => ['required', Rule::enum(WorkOrderPriority::class)],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'region_id' => ['nullable', 'exists:regions,id'],
        ]);

        $workOrder = $this->workOrders->createManual($data, $request->user());

        return redirect()
            ->route('work-orders.show', $workOrder)
            ->with('status', 'Work order created.');
    }

    public function show(WorkOrder $workOrder): View
    {
        $this->authorize('view', $workOrder);

        $workOrder->load(['assignee:id,name', 'region:id,name', 'completer:id,name', 'sourceAuditLog', 'subject']);

        $user = request()->user();

        return view('operations.work-orders.show', [
            'workOrder' => $workOrder,
            'canManage' => $user->can('update', $workOrder),
            'canComplete' => $user->can('complete', $workOrder),
            'canCancel' => $user->can('cancel', $workOrder),
            'assignees' => $this->assignableUsers($user),
            'statuses' => WorkOrderStatus::cases(),
            'priorities' => WorkOrderPriority::cases(),
            'sourceUrl' => $this->sourceEntityUrl($workOrder),
        ]);
    }

    public function update(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('update', $workOrder);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['sometimes', Rule::enum(WorkOrderCategory::class)],
            'priority' => ['sometimes', Rule::enum(WorkOrderPriority::class)],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::enum(WorkOrderStatus::class)],
        ]);

        try {
            $this->workOrders->update($workOrder, $data, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['work_order' => $exception->getMessage()]);
        }

        return redirect()
            ->route('work-orders.show', $workOrder)
            ->with('status', 'Work order updated.');
    }

    public function complete(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('complete', $workOrder);

        $data = $request->validate([
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->workOrders->complete($workOrder, $data['resolution_notes'] ?? null, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['work_order' => $exception->getMessage()]);
        }

        return redirect()
            ->route('work-orders.show', $workOrder)
            ->with('status', 'Work order marked complete.');
    }

    public function cancel(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('cancel', $workOrder);

        $data = $request->validate([
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->workOrders->cancel($workOrder, $data['resolution_notes'] ?? null, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['work_order' => $exception->getMessage()]);
        }

        return redirect()
            ->route('work-orders.index')
            ->with('status', 'Work order cancelled.');
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function assignableUsers(User $user)
    {
        return User::query()
            ->where('is_active', true)
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $user->regionId()))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function sourceEntityUrl(WorkOrder $workOrder): ?string
    {
        $subject = $workOrder->subject;

        if ($subject === null) {
            return null;
        }

        return match ($subject::class) {
            \App\Models\Site::class => route('sites.show', $subject),
            \App\Models\Client::class => route('clients.show', $subject),
            \App\Models\Guard::class => route('guards.show', $subject),
            \App\Models\Leave::class => route('leaves.show', $subject),
            \App\Models\Desertion::class => route('desertions.show', $subject),
            \App\Models\Invoice::class => Access::userCan(request()->user(), 'finance.view')
                ? route('invoices.show', $subject)
                : null,
            default => null,
        };
    }
}

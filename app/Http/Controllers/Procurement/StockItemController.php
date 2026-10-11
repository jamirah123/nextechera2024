<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\AssetAcquisitionSource;
use App\Enums\AssetCategory;
use App\Enums\AssetCondition;
use App\Enums\AssetOperationalStatus;
use App\Enums\AssetTrackingType;
use App\Http\Controllers\Controller;
use App\Models\Guard;
use App\Models\GuardAssetIssuance;
use App\Models\ProcurementStockItem;
use App\Models\Region;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Supplier;
use App\Services\GuardAssetService;
use App\Services\Procurement\CompanyAssetService;
use App\Support\Access\Access;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockItemController extends Controller
{
    public function __construct(
        private CompanyAssetService $assets,
        private GuardAssetService $issues,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GuardAssetIssuance::class);

        $view = $request->string('view')->toString();
        $items = ProcurementStockItem::query()
            ->with(['supplier:id,name,reference', 'region:id,name'])
            ->with(['lines' => fn ($query) => $query->whereNotIn('status', ['returned', 'transferred', 'written_off', 'lost'])->with('issuance.assignedGuard:id,full_name', 'issuance.assignedStaff:id,full_name')])
            ->search($request->string('q')->toString())
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('tracking'), fn ($query) => $query->where('tracking_type', $request->string('tracking')))
            ->when($request->filled('status'), fn ($query) => $query->where('operational_status', $request->string('status')))
            ->when($request->filled('region_id'), fn ($query) => $query->where('region_id', $request->integer('region_id')))
            ->when($view === 'available', fn ($query) => $query->where('operational_status', AssetOperationalStatus::Available)->where('quantity_on_hand', '>', 0))
            ->when($view === 'assigned', fn ($query) => $query->where(fn ($inner) => $inner->where('operational_status', AssetOperationalStatus::Assigned)->orWhere('quantity_issued', '>', 0)))
            ->when($view === 'repair', fn ($query) => $query->whereIn('operational_status', [AssetOperationalStatus::Repair, AssetOperationalStatus::Inspection]))
            ->when($view === 'lost', fn ($query) => $query->where(fn ($inner) => $inner->where('operational_status', AssetOperationalStatus::Lost)->orWhere('quantity_lost', '>', 0)))
            ->when($view === 'retired', fn ($query) => $query->where('operational_status', AssetOperationalStatus::Retired))
            ->when($request->string('availability') === 'low', fn ($query) => $query->whereColumn('quantity_on_hand', '<=', 'reorder_level')->where('is_active', true)->where('tracking_type', AssetTrackingType::Quantity))
            ->orderBy('name')
            ->paginate(table_per_page())
            ->withQueryString();

        $base = ProcurementStockItem::query();

        return view('procurement.stock.index', [
            'items' => $items,
            'categories' => AssetCategory::cases(),
            'statuses' => AssetOperationalStatus::cases(),
            'regions' => Region::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['q', 'category', 'tracking', 'status', 'region_id', 'view', 'availability']),
            'canManage' => $request->user()->can('create', GuardAssetIssuance::class),
            'stats' => [
                'total' => (clone $base)->count(),
                'available' => (clone $base)->where('operational_status', AssetOperationalStatus::Available)->where('quantity_on_hand', '>', 0)->count(),
                'assigned' => (clone $base)->where(fn ($query) => $query->where('operational_status', AssetOperationalStatus::Assigned)->orWhere('quantity_issued', '>', 0))->count(),
                'lost' => (clone $base)->where(fn ($query) => $query->where('operational_status', AssetOperationalStatus::Lost)->orWhere('quantity_lost', '>', 0))->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', GuardAssetIssuance::class);

        return view('procurement.stock.create', $this->formData(request()->user()));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', GuardAssetIssuance::class);

        try {
            $item = $this->assets->register($this->validated($request), $request->user(), $request->file('attachment'));
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['asset_tag' => $exception->getMessage()]);
        }

        return redirect()
            ->route('procurement.stock.show', $item)
            ->with('status', 'Asset registered. It is not assigned to anyone yet.');
    }

    public function show(ProcurementStockItem $item): View
    {
        $this->authorize('viewAny', GuardAssetIssuance::class);

        $item->load([
            'supplier:id,name,reference',
            'region:id,name',
            'site:id,name',
            'creator:id,name',
            'movements.creator:id,name',
            'lines.issuance.assignedGuard:id,full_name,employment_id',
            'lines.issuance.assignedStaff:id,full_name,employment_id,job_title',
        ]);

        $canManage = request()->user()->can('create', GuardAssetIssuance::class);

        return view('procurement.stock.show', [
            'item' => $item,
            'canManage' => $canManage,
            'canSeeCost' => Access::userCan(request()->user(), 'finance.view'),
            'statuses' => AssetOperationalStatus::cases(),
            'guards' => $canManage ? Guard::query()->activeEmployment()->orderBy('full_name')->get(['id', 'employment_id', 'full_name']) : collect(),
            'staffMembers' => $canManage ? Staff::query()->activeEmployment()->orderBy('full_name')->get(['id', 'employment_id', 'full_name', 'job_title']) : collect(),
        ]);
    }

    public function updateStatus(Request $request, ProcurementStockItem $item): RedirectResponse
    {
        $this->authorize('create', GuardAssetIssuance::class);

        $data = $request->validate([
            'operational_status' => ['required', Rule::enum(AssetOperationalStatus::class)],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->assets->changeStatus($item, AssetOperationalStatus::from($data['operational_status']), $data['reason'], $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['operational_status' => $exception->getMessage()]);
        }

        return back()->with('status', 'Asset status updated.');
    }

    public function receive(Request $request, ProcurementStockItem $item): RedirectResponse
    {
        $this->authorize('create', GuardAssetIssuance::class);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->assets->receiveQuantity($item, (int) $data['quantity'], $data['reason'], $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['quantity' => $exception->getMessage()]);
        }

        return back()->with('status', 'Quantity added to stock.');
    }

    public function transfer(Request $request, ProcurementStockItem $item): RedirectResponse
    {
        $this->authorize('create', GuardAssetIssuance::class);

        if (! $request->filled('recipient_type')) {
            $request->merge(['recipient_type' => $request->filled('staff_id') ? 'staff' : 'guard']);
        }

        $data = $request->validate([
            'recipient_type' => ['required', 'in:guard,staff'],
            'guard_id' => ['nullable', 'required_if:recipient_type,guard', 'exists:guards,id'],
            'staff_id' => ['nullable', 'required_if:recipient_type,staff', 'exists:staff,id'],
            'reason' => ['required', 'string', 'max:500'],
            'transferred_on' => ['nullable', 'date'],
        ]);

        if ($data['recipient_type'] === 'staff') {
            $data['guard_id'] = null;
        } else {
            $data['staff_id'] = null;
        }

        try {
            $issuance = $this->issues->transferAssignment($item, $data, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['reason' => $exception->getMessage()]);
        }

        return redirect()
            ->route('assets.show', $issuance)
            ->with('status', 'Asset transferred. The previous assignment is closed.');
    }

    public function template(): StreamedResponse
    {
        $this->authorize('create', GuardAssetIssuance::class);

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['name', 'category', 'tracking_type', 'asset_tag', 'serial_number', 'quantity', 'unit', 'location', 'condition', 'acquisition_source', 'acquisition_date']);
            fputcsv($handle, ['Company laptop', 'laptop', 'individual', 'CSL-IT-0100', 'SN-100', '1', 'pcs', 'Head office', 'good', 'opening_balance', now()->toDateString()]);
            fclose($handle);
        }, 'company-assets-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorize('create', GuardAssetIssuance::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle) ?: [];
        $header = array_map(fn ($column) => strtolower(trim((string) $column)), $header);
        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            if (count(array_filter($line, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $combined = array_combine($header, array_pad($line, count($header), ''));

            if (is_array($combined)) {
                $rows[] = $combined;
            }
        }

        fclose($handle);

        $result = $this->assets->import($rows, $request->user());

        if ($result['errors'] !== []) {
            return back()->withErrors(['file' => implode(' ', $result['errors'])]);
        }

        return redirect()
            ->route('procurement.stock.index')
            ->with('status', $result['created'].' asset(s) registered from the file.');
    }

    public function attachment(ProcurementStockItem $item)
    {
        $this->authorize('viewAny', GuardAssetIssuance::class);
        abort_unless($item->attachment_path && Storage::disk('local')->exists($item->attachment_path), 404);

        return Storage::disk('local')->download($item->attachment_path);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::enum(AssetCategory::class)],
            'tracking_type' => ['required', Rule::enum(AssetTrackingType::class)],
            'asset_tag' => ['nullable', 'string', 'max:64'],
            'serial_number' => ['nullable', 'string', 'max:64'],
            'brand' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:80'],
            'specifications' => ['nullable', 'string', 'max:2000'],
            'unit' => ['nullable', 'string', 'max:24'],
            'quantity_on_hand' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'reorder_level' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'acquisition_date' => ['nullable', 'date'],
            'acquisition_source' => ['required', Rule::enum(AssetAcquisitionSource::class)],
            'purchase_reference' => ['nullable', 'string', 'max:64'],
            'warranty_starts_on' => ['nullable', 'date'],
            'warranty_ends_on' => ['nullable', 'date', 'after_or_equal:warranty_starts_on'],
            'condition' => ['required', Rule::enum(AssetCondition::class)],
            'operational_status' => ['required', Rule::enum(AssetOperationalStatus::class)],
            'location' => ['nullable', 'string', 'max:120'],
            'region_id' => ['nullable', 'exists:regions,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ]);

        if (! Access::userCan($request->user(), 'finance.view')) {
            unset($data['unit_cost']);
        }

        if (($data['tracking_type'] ?? '') === AssetTrackingType::Quantity->value && ! array_key_exists('quantity_on_hand', $data)) {
            $data['quantity_on_hand'] = 0;
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function formData($user): array
    {
        return [
            'categories' => AssetCategory::cases(),
            'trackingTypes' => AssetTrackingType::cases(),
            'conditions' => AssetCondition::cases(),
            'statuses' => array_values(array_filter(
                AssetOperationalStatus::cases(),
                fn (AssetOperationalStatus $status) => $status !== AssetOperationalStatus::Assigned,
            )),
            'sources' => AssetAcquisitionSource::cases(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'reference']),
            'regions' => Region::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name']),
            'canSeeCost' => Access::userCan($user, 'finance.view'),
        ];
    }
}

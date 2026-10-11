<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\SupplierCategory;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewPurchases');

        $suppliers = Supplier::query()
            ->withCount('bills')
            ->search($request->string('q')->toString())
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status') === 'active'))
            ->orderBy(
                $this->sortColumn($request->string('sort')->toString()),
                $request->string('dir')->toString() === 'desc' ? 'desc' : 'asc',
            )
            ->paginate(table_per_page())
            ->withQueryString();

        return view('procurement.suppliers.index', [
            'suppliers' => $suppliers,
            'categories' => SupplierCategory::cases(),
            'filters' => $request->only(['q', 'category', 'status', 'sort', 'dir']),
            'canManage' => $request->user()->can('managePurchases'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('managePurchases');

        return view('procurement.suppliers.create', [
            'categories' => SupplierCategory::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('managePurchases');
        $data = $this->validated($request);

        $supplier = DB::transaction(function () use ($data, $request): Supplier {
            $supplier = Supplier::query()->create([
                ...$data,
                'reference' => $this->nextReference(),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            if ($request->hasFile('document')) {
                $supplier->update([
                    'attachment_path' => $request->file('document')->store('procurement/suppliers', 'local'),
                ]);
            }

            return $supplier;
        });

        return redirect()
            ->route('procurement.suppliers.show', $supplier)
            ->with('status', 'Supplier saved successfully.');
    }

    public function show(Supplier $supplier): View
    {
        Gate::authorize('viewPurchases');

        $supplier->loadCount('bills');
        $bills = $supplier->bills()->latest('bill_date')->latest('id')->limit(20)->get();

        return view('procurement.suppliers.show', [
            'supplier' => $supplier,
            'bills' => $bills,
            'canManage' => request()->user()->can('managePurchases'),
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        Gate::authorize('managePurchases');

        return view('procurement.suppliers.edit', [
            'supplier' => $supplier,
            'categories' => SupplierCategory::cases(),
        ]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        Gate::authorize('managePurchases');
        $data = $this->validated($request, $supplier);

        if ($request->hasFile('document')) {
            if ($supplier->attachment_path) {
                Storage::disk('local')->delete($supplier->attachment_path);
            }
            $data['attachment_path'] = $request->file('document')->store('procurement/suppliers', 'local');
        }

        $supplier->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('procurement.suppliers.show', $supplier)
            ->with('status', 'Supplier saved successfully.');
    }

    public function document(Supplier $supplier): StreamedResponse
    {
        Gate::authorize('viewPurchases');
        abort_unless($supplier->attachment_path && Storage::disk('local')->exists($supplier->attachment_path), 404);

        return Storage::disk('local')->download($supplier->attachment_path);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:40', Rule::unique('suppliers', 'tin')->ignore($supplier?->id)],
            'category' => ['required', Rule::enum(SupplierCategory::class)],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $duplicateName = Supplier::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
            ->when($supplier, fn ($query) => $query->whereKeyNot($supplier->id))
            ->exists();

        if ($duplicateName) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name' => 'A supplier with this name already exists.',
            ]);
        }

        unset($data['document']);
        $data['is_active'] = $request->boolean('is_active');
        $data['tin'] = filled($data['tin'] ?? null) ? $data['tin'] : null;

        return $data;
    }

    private function sortColumn(string $sort): string
    {
        return in_array($sort, ['name', 'reference', 'category', 'tin'], true) ? $sort : 'name';
    }

    private function nextReference(): string
    {
        $latest = Supplier::query()->lockForUpdate()->orderByDesc('id')->value('reference');
        $sequence = 1;

        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return 'SUP-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}

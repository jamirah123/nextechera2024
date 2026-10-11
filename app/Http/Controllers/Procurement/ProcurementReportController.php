<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\AssetLineStatus;
use App\Enums\PurchaseInvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\GuardAssetLine;
use App\Models\ProcurementStockItem;
use App\Models\PurchaseInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProcurementReportController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewPurchases');

        return view('procurement.reports.index', $this->payload($request));
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewPurchases');
        $from = $request->date('from')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $to = $request->date('to')?->toDateString() ?? now()->toDateString();
        $rows = PurchaseInvoice::query()
            ->whereBetween('bill_date', [$from, $to])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('supplier'), fn ($query) => $query->where('supplier_name', 'like', '%'.$request->string('supplier').'%'))
            ->orderBy('reference')
            ->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Reference', 'Supplier', 'Invoice no.', 'Bill date', 'Status', 'Subtotal', 'Input VAT', 'Total']);
            foreach ($rows as $bill) {
                fputcsv($handle, [
                    $bill->reference,
                    $bill->supplier_name,
                    $bill->supplier_invoice_no,
                    optional($bill->bill_date)->toDateString(),
                    $bill->status->value,
                    $bill->subtotal,
                    $bill->tax_amount,
                    $bill->total,
                ]);
            }
            fclose($handle);
        }, 'supplier-bills.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $from = $request->date('from')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $to = $request->date('to')?->toDateString() ?? now()->toDateString();

        $billQuery = PurchaseInvoice::query()
            ->whereBetween('bill_date', [$from, $to])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('supplier'), fn ($query) => $query->where('supplier_name', 'like', '%'.$request->string('supplier').'%'));

        $bills = (clone $billQuery)
            ->with('expenseAccount:id,code,name')
            ->orderByDesc('bill_date')
            ->paginate(table_per_page())
            ->withQueryString();

        $outstanding = GuardAssetLine::query()
            ->with(['issuance.assignedGuard:id,employment_id,full_name', 'stockItem:id,name'])
            ->whereColumn('quantity_returned', '<', 'quantity')
            ->whereNotIn('status', [AssetLineStatus::Returned->value, AssetLineStatus::WrittenOff->value, AssetLineStatus::Lost->value])
            ->latest('id')
            ->limit(25)
            ->get();

        return [
            'bills' => $bills,
            'outstanding' => $outstanding,
            'stock' => ProcurementStockItem::query()->where('is_active', true)->orderBy('name')->limit(25)->get(),
            'statuses' => PurchaseInvoiceStatus::cases(),
            'filters' => ['from' => $from, 'to' => $to, 'status' => $request->string('status')->toString(), 'supplier' => $request->string('supplier')->toString()],
            'totals' => [
                'count' => (clone $billQuery)->count(),
                'subtotal' => (float) (clone $billQuery)->sum('subtotal'),
                'tax' => (float) (clone $billQuery)->sum('tax_amount'),
                'total' => (float) (clone $billQuery)->sum('total'),
            ],
        ];
    }
}

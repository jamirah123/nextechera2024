<?php

namespace App\Services\Procurement;

use App\Enums\AssetLineStatus;
use App\Enums\PurchaseInvoiceStatus;
use App\Models\AuditLog;
use App\Models\GuardAssetLine;
use App\Models\ProcurementStockItem;
use App\Models\PurchaseInvoice;
use Carbon\Carbon;

class ProcurementWorkspaceService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(?string $from = null, ?string $to = null): array
    {
        $start = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
        $end = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $outstanding = GuardAssetLine::query()
            ->whereColumn('quantity_returned', '<', 'quantity')
            ->whereNotIn('status', [AssetLineStatus::Returned->value, AssetLineStatus::WrittenOff->value, AssetLineStatus::Lost->value]);

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'cards' => [
                ['label' => 'On hand', 'value' => (string) ProcurementStockItem::query()->where('is_active', true)->sum('quantity_on_hand'), 'hint' => 'Quantity available to issue', 'tone' => 'emerald', 'href' => route('procurement.stock.index', ['view' => 'available'])],
                ['label' => 'Low stock', 'value' => (string) ProcurementStockItem::query()->where('is_active', true)->whereColumn('quantity_on_hand', '<=', 'reorder_level')->count(), 'hint' => 'At or below reorder level', 'tone' => 'rose', 'href' => route('procurement.stock.index')],
                ['label' => 'Issued now', 'value' => (string) (clone $outstanding)->count(), 'hint' => 'Lines still with employees', 'tone' => 'indigo', 'href' => route('assets.index', ['status' => 'active'])],
                ['label' => 'Returns due', 'value' => (string) (clone $outstanding)->whereNotNull('expected_return_on')->whereDate('expected_return_on', '<', now()->toDateString())->count(), 'hint' => 'Past the expected return date', 'tone' => 'amber', 'href' => route('assets.index', ['status' => 'active'])],
                ['label' => 'Awaiting action', 'value' => (string) PurchaseInvoice::query()->where('status', PurchaseInvoiceStatus::Draft)->count(), 'hint' => 'Draft bills still to post', 'tone' => 'brand', 'href' => route('ledger.purchases.index', ['status' => 'draft'])],
            ],
            'activity' => AuditLog::query()
                ->where(function ($query): void {
                    $query->where('action', 'like', 'ledger.purchase%')
                        ->orWhere('action', 'like', 'asset.%')
                        ->orWhere('action', 'like', 'supplier.%');
                })
                ->whereBetween('created_at', [$start, $end])
                ->latest('id')
                ->limit(8)
                ->get(['id', 'action', 'summary', 'subject_type', 'subject_id', 'created_at']),
        ];
    }
}

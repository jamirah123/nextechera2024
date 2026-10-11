<?php

namespace App\Services\Procurement;

use App\Models\ProcurementStockItem;
use App\Models\ProcurementStockMovement;
use App\Models\User;

class StockLedger
{
    public function record(
        ProcurementStockItem $item,
        string $type,
        int $quantity,
        string $direction,
        ?string $reason = null,
        ?int $lineId = null,
        ?User $actor = null,
    ): ProcurementStockMovement {
        return ProcurementStockMovement::query()->create([
            'stock_item_id' => $item->id,
            'movement_type' => $type,
            'quantity' => max(0, $quantity),
            'direction' => $direction,
            'balance_after' => (int) $item->quantity_on_hand,
            'reason' => $reason,
            'guard_asset_line_id' => $lineId,
            'created_by' => $actor?->id ?? auth()->id(),
        ]);
    }
}

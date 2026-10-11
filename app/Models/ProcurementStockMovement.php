<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementStockMovement extends Model
{
    protected $fillable = [
        'stock_item_id',
        'movement_type',
        'quantity',
        'direction',
        'balance_after',
        'reason',
        'guard_asset_line_id',
        'created_by',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProcurementStockItem::class, 'stock_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function summary(): string
    {
        $who = $this->creator?->name ?? 'The system';
        $quantity = (int) $this->quantity;
        $reason = filled($this->reason) ? trim((string) $this->reason) : null;

        if ($this->movement_type === 'opening') {
            $source = $reason ? preg_replace('/^Registered as\s+/i', '', $reason) : 'an opening balance';
            $action = 'registered this as '.$source;
            $reason = null;
        } else {
            $action = match ($this->movement_type) {
                'issue' => 'issued '.$quantity.' from stock',
                'receipt' => 'added '.$quantity.' to stock',
                'transfer' => 'transferred the current assignment',
                'status' => 'changed the status',
                'returned' => 'returned '.$quantity.' to stock',
                'lost' => 'marked '.$quantity.' lost',
                'damaged', 'written_off' => 'marked '.$quantity.' damaged',
                default => 'recorded '.$this->movement_type.' ('.$quantity.')',
            };
        }

        $sentence = $who.' '.$action;
        if ($reason && strcasecmp($reason, $this->movement_type) !== 0 && strcasecmp($reason, 'Issued') !== 0) {
            $sentence .= '. '.$reason;
        }

        return $sentence.'. On hand afterwards: '.(int) $this->balance_after.'.';
    }
}

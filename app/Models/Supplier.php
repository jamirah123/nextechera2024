<?php

namespace App\Models;

use App\Enums\SupplierCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'reference',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'delivery_address',
        'tin',
        'category',
        'is_active',
        'notes',
        'attachment_path',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => SupplierCategory::class,
            'is_active' => 'boolean',
        ];
    }

    public function bills(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(ProcurementStockItem::class);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($inner) use ($like): void {
            $inner->where('reference', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhere('tin', 'like', $like)
                ->orWhere('contact_person', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like);
        });
    }
}

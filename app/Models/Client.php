<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Models\Concerns\TracksUserChanges;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, SoftDeletes, TracksUserChanges;

    protected $fillable = [
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'contract_start_date',
        'contract_end_date',
        'contract_status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'contract_status' => ContractStatus::class,
            'contract_start_date' => 'date',
            'contract_end_date' => 'date',
        ];
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function isContractActive(): bool
    {
        return $this->contract_status === ContractStatus::Active;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('name', 'like', $like)
                ->orWhere('contact_person', 'like', $like)
                ->orWhere('phone', 'like', $like);
        });
    }
}

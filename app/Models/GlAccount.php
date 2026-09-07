<?php

namespace App\Models;

use App\Enums\GlAccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlAccount extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'system_role',
        'is_postable',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'type' => GlAccountType::class,
            'is_postable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GlJournalLine::class, 'account_id');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class, 'gl_account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePostable($query)
    {
        return $query->where('is_postable', true)->where('is_active', true);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('code', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhere('system_role', 'like', $like);
        });
    }

    public function label(): string
    {
        return $this->code.' · '.$this->name;
    }
}

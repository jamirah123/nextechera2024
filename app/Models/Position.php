<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    protected $fillable = [
        'name',
        'code',
        'is_guard_position',
        'is_staff_position',
        'is_supervisor_position',
        'is_management_position',
        'eligible_for_deployment',
        'eligible_for_shifts',
        'eligible_for_overtime',
        'salary_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_guard_position' => 'boolean',
            'is_staff_position' => 'boolean',
            'is_supervisor_position' => 'boolean',
            'is_management_position' => 'boolean',
            'eligible_for_deployment' => 'boolean',
            'eligible_for_shifts' => 'boolean',
            'eligible_for_overtime' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(EmployeePromotion::class);
    }

    public function leavesGuardRoster(): bool
    {
        return ! $this->is_guard_position;
    }

    public function salaryLabel(): string
    {
        return $this->salary_type === 'variable' ? 'Variable (shift)' : 'Fixed monthly';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    protected $fillable = [
        'company_name',
        'support_email',
        'support_phone',
        'currency',
        'currency_label',
        'currency_decimals',
        'invoice_due_days',
        'default_day_shift_start',
        'default_day_shift_end',
        'default_night_shift_start',
        'default_night_shift_end',
        'backup_keep_days',
        'backup_path',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'currency_decimals' => 'integer',
            'invoice_due_days' => 'integer',
            'backup_keep_days' => 'integer',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

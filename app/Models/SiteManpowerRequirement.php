<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteManpowerRequirement extends Model
{
    protected $fillable = [
        'site_id',
        'required_total',
        'required_day',
        'required_night',
        'effective_from',
        'effective_to',
        'is_current',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_current' => 'boolean',
            'required_total' => 'integer',
            'required_day' => 'integer',
            'required_night' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

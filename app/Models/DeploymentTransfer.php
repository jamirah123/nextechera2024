<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentTransfer extends Model
{
    protected $fillable = [
        'guard_id',
        'from_deployment_id',
        'to_deployment_id',
        'from_site_id',
        'to_site_id',
        'reason',
        'notes',
        'transferred_by',
        'effective_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
        ];
    }

    public function guardRecord(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }

    public function fromDeployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class, 'from_deployment_id');
    }

    public function toDeployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class, 'to_deployment_id');
    }

    public function fromSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'from_site_id');
    }

    public function toSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'to_site_id');
    }

    public function transferrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}

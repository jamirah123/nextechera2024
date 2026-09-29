<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationState extends Model
{
    protected $fillable = [
        'user_id',
        'audit_log_id',
        'priority',
        'delivery_status',
        'delivered_at',
        'read_at',
        'dismissed_at',
        'pinned_unread',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'pinned_unread' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class);
    }
}

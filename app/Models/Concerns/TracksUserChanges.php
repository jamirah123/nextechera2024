<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait TracksUserChanges
{
    public static function bootTracksUserChanges(): void
    {
        static::creating(function ($model): void {
            if (auth()->check()) {
                if ($model->isFillable('created_by') && empty($model->created_by)) {
                    $model->created_by = auth()->id();
                }
                if ($model->isFillable('updated_by')) {
                    $model->updated_by = auth()->id();
                }
            }
        });

        static::updating(function ($model): void {
            if (auth()->check() && $model->isFillable('updated_by')) {
                $model->updated_by = auth()->id();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

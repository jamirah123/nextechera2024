<?php

namespace App\Models\Concerns;

use App\Services\ArchiveService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

trait CapturesDeletionSnapshot
{
    protected static function bootCapturesDeletionSnapshot(): void
    {
        static::deleting(function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                return;
            }

            app(ArchiveService::class)->recordSnapshot($model);
        });

        static::deleted(function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                return;
            }

            app(ArchiveService::class)->logDeletion($model);
        });
    }

    protected function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive(static::class), true);
    }
}

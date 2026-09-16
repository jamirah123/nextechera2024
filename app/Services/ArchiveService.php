<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Models\DeletedRecordSnapshot;
use App\Models\GuardAttachment;
use App\Models\PayrollDeduction;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\User;
use App\Models\UserAttachment;
use App\Support\Archive\DeletedRecordRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ArchiveService
{
    public function __construct(private AuditService $audit) {}

    /**
     * @param  array<string, mixed>  $relations
     */
    public function recordSnapshot(Model $model, ?string $sourceAction = null, array $relations = []): ?DeletedRecordSnapshot
    {
        if (! DeletedRecordRegistry::isTracked($model)) {
            return null;
        }

        $definition = DeletedRecordRegistry::for($model);

        if ($definition === null) {
            return null;
        }

        $attributes = $model->getAttributes();
        $relationPayload = $relations !== [] ? $relations : $this->defaultRelations($model);

        if ($model instanceof GuardAttachment || $model instanceof UserAttachment) {
            $relationPayload['archived_file_path'] = $this->copyAttachmentToArchive($model);
        }

        return DeletedRecordSnapshot::query()->create([
            'record_type' => $definition['type'],
            'model_class' => $model::class,
            'record_id' => (int) $model->getKey(),
            'label' => DeletedRecordRegistry::labelFor($model),
            'attributes' => $attributes,
            'relations' => $relationPayload === [] ? null : $relationPayload,
            'was_soft_deleted' => $this->usesSoftDeletes($model),
            'source_action' => $sourceAction,
            'deleted_by' => auth()->id(),
        ]);
    }

    public function logDeletion(Model $model): void
    {
        $definition = DeletedRecordRegistry::for($model);

        if ($definition === null) {
            return;
        }

        if ($model instanceof User) {
            return;
        }

        $this->audit->log(
            action: $definition['audit_action'],
            summary: DeletedRecordRegistry::labelFor($model).' archived.',
            category: $this->auditCategoryFor($model),
            severity: AuditSeverity::Warning,
            subject: $model,
            context: [
                'record_type' => $definition['type'],
                'record_id' => $model->getKey(),
            ],
        );
    }

    public function restore(DeletedRecordSnapshot $snapshot, ?User $actor = null): Model
    {
        if ($snapshot->isRestored()) {
            throw new InvalidArgumentException('This record has already been restored.');
        }

        if (! $snapshot->isRestorable()) {
            throw new InvalidArgumentException('This record type cannot be restored.');
        }

        return DB::transaction(function () use ($snapshot, $actor) {
            $model = $snapshot->was_soft_deleted
                ? $this->restoreSoftDeleted($snapshot)
                : $this->recreateHardDeleted($snapshot);

            $snapshot->update([
                'restored_at' => now(),
                'restored_by' => $actor?->id ?? auth()->id(),
            ]);

            $this->audit->log(
                action: 'record.restored',
                summary: $snapshot->label.' restored from archive.',
                category: AuditCategory::System,
                severity: AuditSeverity::Notice,
                subject: $model,
                actor: $actor,
                context: [
                    'snapshot_id' => $snapshot->id,
                    'record_type' => $snapshot->record_type,
                ],
            );

            return $model;
        });
    }

    private function restoreSoftDeleted(DeletedRecordSnapshot $snapshot): Model
    {
        /** @var class-string<Model> $class */
        $class = $snapshot->model_class;

        $model = $class::withTrashed()->find($snapshot->record_id);

        if ($model === null) {
            return $this->recreateHardDeleted($snapshot);
        }

        if (method_exists($model, 'trashed') && $model->trashed()) {
            $this->assertCanRestore($snapshot, $model);
            $model->restore();

            return $model->fresh();
        }

        throw new InvalidArgumentException('The archived record still exists and is not deleted.');
    }

    private function recreateHardDeleted(DeletedRecordSnapshot $snapshot): Model
    {
        /** @var class-string<Model> $class */
        $class = $snapshot->model_class;

        return match ($class) {
            GuardAttachment::class => $this->restoreGuardAttachment($snapshot),
            UserAttachment::class => $this->restoreUserAttachment($snapshot),
            PayrollPayslip::class => $this->restorePayrollPayslip($snapshot),
            default => $this->restoreGeneric($snapshot),
        };
    }

    private function restoreGeneric(DeletedRecordSnapshot $snapshot): Model
    {
        /** @var class-string<Model> $class */
        $class = $snapshot->model_class;
        $attributes = $snapshot->attributes;

        if ($class === User::class) {
            $exists = User::withTrashed()->where('email', $attributes['email'] ?? '')->exists();
            if ($exists) {
                throw new InvalidArgumentException('Cannot restore user: another account already uses this email.');
            }
        }

        unset($attributes['deleted_at']);

        /** @var Model $model */
        $model = $class::query()->create($attributes);

        return $model;
    }

    private function restoreGuardAttachment(DeletedRecordSnapshot $snapshot): GuardAttachment
    {
        $attributes = $snapshot->attributes;
        $archivedPath = $snapshot->relations['archived_file_path'] ?? null;

        if (! is_string($archivedPath) || ! Storage::disk('local')->exists($archivedPath)) {
            throw new InvalidArgumentException('The archived attachment file is no longer available.');
        }

        $restoredPath = 'guards/'.$attributes['guard_id'].'/'.basename($archivedPath);
        Storage::disk('local')->makeDirectory('guards/'.$attributes['guard_id']);
        Storage::disk('local')->copy($archivedPath, $restoredPath);

        unset($attributes['id'], $attributes['created_at'], $attributes['updated_at']);
        $attributes['path'] = $restoredPath;

        return GuardAttachment::query()->create($attributes);
    }

    private function restoreUserAttachment(DeletedRecordSnapshot $snapshot): UserAttachment
    {
        $attributes = $snapshot->attributes;
        $archivedPath = $snapshot->relations['archived_file_path'] ?? null;

        if (! is_string($archivedPath) || ! Storage::disk('local')->exists($archivedPath)) {
            throw new InvalidArgumentException('The archived attachment file is no longer available.');
        }

        $restoredPath = 'users/'.$attributes['user_id'].'/'.basename($archivedPath);
        Storage::disk('local')->makeDirectory('users/'.$attributes['user_id']);
        Storage::disk('local')->copy($archivedPath, $restoredPath);

        unset($attributes['id'], $attributes['created_at'], $attributes['updated_at']);
        $attributes['path'] = $restoredPath;

        return UserAttachment::query()->create($attributes);
    }

    private function restorePayrollPayslip(DeletedRecordSnapshot $snapshot): PayrollPayslip
    {
        $attributes = $snapshot->attributes;
        $relations = $snapshot->relations ?? [];

        $run = PayrollRun::query()->find($attributes['payroll_run_id'] ?? null);

        if ($run === null) {
            throw new InvalidArgumentException('Cannot restore payslip: payroll run no longer exists.');
        }

        if ($run->payslips()->where('employment_id', $attributes['employment_id'] ?? '')->exists()) {
            throw new InvalidArgumentException('Cannot restore payslip: a payslip for this employee already exists on the run.');
        }

        unset($attributes['id'], $attributes['created_at'], $attributes['updated_at']);

        $payslip = PayrollPayslip::query()->create($attributes);

        foreach ($relations['deductions'] ?? [] as $deduction) {
            if (! is_array($deduction)) {
                continue;
            }

            unset($deduction['id'], $deduction['created_at'], $deduction['updated_at']);
            $deduction['payroll_payslip_id'] = $payslip->id;
            PayrollDeduction::query()->create($deduction);
        }

        $shiftIds = collect($relations['shift_ids'] ?? [])->filter()->all();
        if ($shiftIds !== []) {
            $payslip->shifts()->sync($shiftIds);
        }

        $run->refresh();
        $this->refreshPayrollRunTotals($run);

        return $payslip->fresh(['deductions']);
    }

    private function refreshPayrollRunTotals(PayrollRun $run): void
    {
        $totals = PayrollPayslip::query()
            ->where('payroll_run_id', $run->id)
            ->selectRaw('COUNT(*) as guard_count, COALESCE(SUM(gross_pay), 0) as gross_total, COALESCE(SUM(total_deductions), 0) as deductions_total, COALESCE(SUM(net_pay), 0) as net_total')
            ->first();

        $run->update([
            'guard_count' => (int) ($totals->guard_count ?? 0),
            'gross_total' => round((float) ($totals->gross_total ?? 0), 2),
            'deductions_total' => round((float) ($totals->deductions_total ?? 0), 2),
            'net_total' => round((float) ($totals->net_total ?? 0), 2),
        ]);
    }

    private function assertCanRestore(DeletedRecordSnapshot $snapshot, Model $model): void
    {
        if ($model instanceof User) {
            $email = $snapshot->attributes['email'] ?? null;
            if ($email && User::query()->where('email', $email)->where('id', '!=', $model->id)->exists()) {
                throw new InvalidArgumentException('Cannot restore user: another active account uses this email.');
            }
        }
    }

    /** @return array<string, mixed> */
    private function defaultRelations(Model $model): array
    {
        if ($model instanceof PayrollPayslip) {
            $model->loadMissing('deductions');

            return [
                'deductions' => $model->deductions->map->getAttributes()->values()->all(),
                'shift_ids' => $model->shifts()->pluck('shifts.id')->all(),
            ];
        }

        return [];
    }

    private function copyAttachmentToArchive(GuardAttachment|UserAttachment $attachment): ?string
    {
        if (! Storage::disk('local')->exists($attachment->path)) {
            return null;
        }

        $archivePath = 'deleted-archive/attachments/'.date('Y/m/d').'/'.$attachment->getKey().'_'.basename($attachment->path);
        Storage::disk('local')->makeDirectory(dirname($archivePath));
        Storage::disk('local')->copy($attachment->path, $archivePath);

        return $archivePath;
    }

    private function usesSoftDeletes(Model $model): bool
    {
        return method_exists($model, 'trashed');
    }

    private function auditCategoryFor(Model $model): AuditCategory
    {
        return match ($model::class) {
            User::class => AuditCategory::Security,
            PayrollPayslip::class, Invoice::class => AuditCategory::Finance,
            Guard::class, Staff::class => AuditCategory::Guard,
            Region::class, Client::class, Site::class, Supervisor::class => AuditCategory::Organization,
            default => AuditCategory::System,
        };
    }
}

<?php

namespace App\Support\Archive;

use App\Models\Client;
use App\Models\Guard;
use App\Models\GuardAttachment;
use App\Models\Invoice;
use App\Models\PayrollPayslip;
use App\Models\Region;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Supervisor;
use App\Models\User;
use App\Models\UserAttachment;
use Illuminate\Database\Eloquent\Model;

class DeletedRecordRegistry
{
    /** @return array<class-string<Model>, array{type: string, label: string, audit_action: string, restorable: bool}> */
    public static function definitions(): array
    {
        return [
            Guard::class => [
                'type' => 'guard',
                'label' => 'Guard',
                'audit_action' => 'guard.archived',
                'restorable' => true,
            ],
            Staff::class => [
                'type' => 'staff',
                'label' => 'Staff member',
                'audit_action' => 'staff.archived',
                'restorable' => true,
            ],
            User::class => [
                'type' => 'user',
                'label' => 'System user',
                'audit_action' => 'user.archived',
                'restorable' => true,
            ],
            Region::class => [
                'type' => 'region',
                'label' => 'Region',
                'audit_action' => 'organization.region_archived',
                'restorable' => true,
            ],
            Client::class => [
                'type' => 'client',
                'label' => 'Client',
                'audit_action' => 'organization.client_archived',
                'restorable' => true,
            ],
            Site::class => [
                'type' => 'site',
                'label' => 'Site',
                'audit_action' => 'organization.site_archived',
                'restorable' => true,
            ],
            Supervisor::class => [
                'type' => 'supervisor',
                'label' => 'Supervisor',
                'audit_action' => 'organization.supervisor_archived',
                'restorable' => true,
            ],
            Invoice::class => [
                'type' => 'invoice',
                'label' => 'Invoice',
                'audit_action' => 'finance.invoice_archived',
                'restorable' => true,
            ],
            GuardAttachment::class => [
                'type' => 'guard_attachment',
                'label' => 'Guard attachment',
                'audit_action' => 'guard.attachment_removed',
                'restorable' => true,
            ],
            UserAttachment::class => [
                'type' => 'user_attachment',
                'label' => 'User attachment',
                'audit_action' => 'user.attachment_removed',
                'restorable' => true,
            ],
            PayrollPayslip::class => [
                'type' => 'payroll_payslip',
                'label' => 'Payroll payslip',
                'audit_action' => 'payroll.payslip_removed',
                'restorable' => true,
            ],
        ];
    }

    public static function isTracked(Model $model): bool
    {
        return array_key_exists($model::class, self::definitions());
    }

    public static function canRestore(string $modelClass): bool
    {
        return (bool) (self::definitions()[$modelClass]['restorable'] ?? false);
    }

    public static function typeLabel(string $type): string
    {
        foreach (self::definitions() as $definition) {
            if ($definition['type'] === $type) {
                return $definition['label'];
            }
        }

        return ucfirst(str_replace('_', ' ', $type));
    }

    /** @return list<string> */
    public static function types(): array
    {
        return collect(self::definitions())
            ->map(fn (array $definition) => $definition['type'])
            ->unique()
            ->values()
            ->all();
    }

    /** @return array{type: string, label: string, audit_action: string, restorable: bool}|null */
    public static function for(Model $model): ?array
    {
        return self::definitions()[$model::class] ?? null;
    }

    public static function labelFor(Model $model): string
    {
        return match ($model::class) {
            Guard::class => trim($model->full_name.' ('.$model->employment_id.')'),
            Staff::class => trim($model->full_name.' ('.$model->employment_id.')'),
            User::class => trim($model->name.' ('.$model->email.')'),
            Region::class, Client::class, Site::class => (string) $model->name,
            Supervisor::class => trim($model->name.' ('.$model->supervisor_code.')'),
            Invoice::class => (string) ($model->reference ?? 'Invoice #'.$model->getKey()),
            GuardAttachment::class, UserAttachment::class => $model->displayName(),
            PayrollPayslip::class => trim($model->full_name.' · '.$model->employment_id),
            default => class_basename($model).' #'.$model->getKey(),
        };
    }
}

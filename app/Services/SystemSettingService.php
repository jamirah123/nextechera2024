<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SystemSettingService
{
    public function __construct(private AuditService $audit)
    {
    }

    public function current(): SystemSetting
    {
        return Cache::rememberForever('system_settings', function (): SystemSetting {
            $settings = SystemSetting::query()->first();

            if ($settings) {
                return $settings;
            }

            return SystemSetting::query()->create($this->defaultAttributes());
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): SystemSetting
    {
        return DB::transaction(function () use ($data) {
            $settings = $this->current();
            $before = $settings->only(array_keys($data));

            $settings->update([
                ...$data,
                'updated_by' => auth()->id(),
            ]);

            Cache::forget('system_settings');
            $fresh = $settings->fresh(['updater']);
            $this->applyRuntimeConfig($fresh);

            $this->audit->log(
                action: 'system.settings_updated',
                summary: 'System settings updated.',
                category: AuditCategory::System,
                severity: AuditSeverity::Notice,
                subject: $fresh,
                context: [
                    'before' => $before,
                    'after' => $fresh->only(array_keys($data)),
                ],
            );

            return $fresh;
        });
    }

    public function applyRuntimeConfig(?SystemSetting $settings = null): void
    {
        $settings ??= $this->current();

        config([
            'app.name' => $settings->company_name,
            'psg.company' => $settings->company_name,
            'psg.currency' => $settings->currency,
            'psg.currency_label' => $settings->currency_label,
            'psg.currency_decimals' => $settings->currency_decimals,
            'psg.invoice_due_days' => $settings->invoice_due_days,
            'psg.support_email' => $settings->support_email,
            'psg.support_phone' => $settings->support_phone,
            'psg.shift_defaults.day.start' => $settings->default_day_shift_start,
            'psg.shift_defaults.day.end' => $settings->default_day_shift_end,
            'psg.shift_defaults.night.start' => $settings->default_night_shift_start,
            'psg.shift_defaults.night.end' => $settings->default_night_shift_end,
            'psg.backup.keep_days' => $settings->backup_keep_days,
            'psg.backup.path' => $settings->backup_path,
        ]);
    }

    /** @return array<string, mixed> */
    private function defaultAttributes(): array
    {
        return [
            'company_name' => config('psg.company', 'Platinum Security Group'),
            'currency' => config('psg.currency', 'UGX'),
            'currency_label' => config('psg.currency_label', 'Ugandan Shillings'),
            'currency_decimals' => config('psg.currency_decimals', 0),
            'invoice_due_days' => config('psg.invoice_due_days', 14),
            'default_day_shift_start' => config('psg.shift_defaults.day.start', '06:00'),
            'default_day_shift_end' => config('psg.shift_defaults.day.end', '18:00'),
            'default_night_shift_start' => config('psg.shift_defaults.night.start', '18:00'),
            'default_night_shift_end' => config('psg.shift_defaults.night.end', '06:00'),
            'backup_keep_days' => config('psg.backup.keep_days', 14),
            'backup_path' => config('psg.backup.path', 'backups'),
        ];
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_and_update_system_settings(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('System settings')
            ->assertSee('Finance defaults');

        $this->actingAs($admin)
            ->put(route('settings.update'), [
                'company_name' => 'Platinum Security Uganda',
                'support_email' => 'finance@platinum.test',
                'support_phone' => '+256700000000',
                'currency' => 'UGX',
                'currency_label' => 'Ugandan Shillings',
                'currency_decimals' => 0,
                'invoice_due_days' => 21,
                'default_day_shift_start' => '07:00',
                'default_day_shift_end' => '19:00',
                'default_night_shift_start' => '19:00',
                'default_night_shift_end' => '07:00',
                'backup_keep_days' => 30,
                'backup_path' => 'backups',
            ])
            ->assertRedirect(route('settings.index'));

        Cache::forget('system_settings');
        app(\App\Services\SystemSettingService::class)->applyRuntimeConfig();

        $this->assertDatabaseHas('system_settings', [
            'company_name' => 'Platinum Security Uganda',
            'invoice_due_days' => 21,
            'default_day_shift_start' => '07:00',
        ]);

        $this->assertSame('Platinum Security Uganda', config('psg.company'));
        $this->assertSame(21, config('psg.invoice_due_days'));
    }

    public function test_non_super_admin_cannot_access_settings(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();

        $this->actingAs($ops)
            ->get(route('settings.index'))
            ->assertForbidden();

        $this->actingAs($ops)
            ->put(route('settings.update'), [
                'company_name' => 'Hacked',
                'currency' => 'UGX',
                'currency_label' => 'UGX',
                'currency_decimals' => 0,
                'invoice_due_days' => 14,
                'default_day_shift_start' => '06:00',
                'default_day_shift_end' => '18:00',
                'default_night_shift_start' => '18:00',
                'default_night_shift_end' => '06:00',
                'backup_keep_days' => 14,
                'backup_path' => 'backups',
            ])
            ->assertForbidden();
    }

    public function test_settings_update_is_audited(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();

        $this->actingAs($admin)
            ->put(route('settings.update'), [
                'company_name' => SystemSetting::query()->value('company_name'),
                'currency' => 'UGX',
                'currency_label' => 'Ugandan Shillings',
                'currency_decimals' => 0,
                'invoice_due_days' => 30,
                'default_day_shift_start' => '06:00',
                'default_day_shift_end' => '18:00',
                'default_night_shift_start' => '18:00',
                'default_night_shift_end' => '06:00',
                'backup_keep_days' => 14,
                'backup_path' => 'backups',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'system.settings_updated',
        ]);
    }
}

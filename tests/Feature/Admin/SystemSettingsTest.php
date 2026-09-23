<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
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
            ->assertSee('Company branding')
            ->assertSee('Identifiers')
            ->assertSee('Theme colors')
            ->assertSee('Email & notifications')
            ->assertSee('Finance defaults')
            ->assertSee('Payroll defaults');

        $this->actingAs($admin)
            ->put(route('settings.update'), $this->baseSettingsPayload([
                'company_name' => 'Platinum Security Uganda',
                'support_email' => 'finance@platinum.test',
                'support_phone' => '+256700000000',
                'invoice_due_days' => 21,
                'default_day_shift_start' => '07:00',
                'default_day_shift_end' => '19:00',
                'default_night_shift_start' => '19:00',
                'default_night_shift_end' => '07:00',
                'backup_keep_days' => 30,
            ]))
            ->assertRedirect(route('settings.index'));

        Cache::forget('system_settings.id');
        app(SystemSettingService::class)->applyRuntimeConfig();

        $this->assertDatabaseHas('system_settings', [
            'company_name' => 'Platinum Security Uganda',
            'invoice_due_days' => 21,
            'default_day_shift_start' => '07:00',
        ]);

        $this->assertSame('Platinum Security Uganda', config('psg.company'));
        $this->assertSame(21, config('psg.invoice_due_days'));
    }

    public function test_super_admin_can_update_branding_and_upload_logo(): void
    {
        Storage::fake('public');

        $admin = User::factory()->role(UserRole::SuperAdmin)->create();
        $logo = UploadedFile::fake()->image('company-logo.png', 120, 120);

        $this->actingAs($admin)
            ->put(route('settings.update'), $this->baseSettingsPayload([
                'company_name' => 'Acme Security Ltd',
                'tagline' => 'Protecting what matters',
                'system_subtitle' => 'Workforce ERP',
                'company_short_name' => 'ACME',
                'theme_primary' => '#0f766e',
                'theme_sidebar' => '#134e4a',
                'email_footer_text' => 'Confidential workforce communication.',
                'support_email' => 'hello@acme.test',
                'support_phone' => '+256700000001',
                'logo' => $logo,
            ]))
            ->assertRedirect(route('settings.index'));

        Cache::forget('system_settings.id');
        app(SystemSettingService::class)->applyRuntimeConfig();

        $settings = SystemSetting::query()->first();

        $this->assertSame('Acme Security Ltd', $settings->company_name);
        $this->assertSame('Protecting what matters', $settings->tagline);
        $this->assertSame('Workforce ERP', $settings->system_subtitle);
        $this->assertNotNull($settings->logo_path);
        Storage::disk('public')->assertExists($settings->logo_path);

        $this->assertSame('Acme Security Ltd', config('psg.company'));
        $this->assertSame('Protecting what matters', config('psg.tagline'));
        $this->assertSame('Workforce ERP', config('psg.system_subtitle'));
        $this->assertSame('#0f766e', config('psg.theme_primary'));
        $this->assertSame('Confidential workforce communication.', config('psg.email_footer'));
        $this->assertStringContainsString('storage/', config('psg.logo_url'));
    }

    public function test_super_admin_can_upload_favicon(): void
    {
        Storage::fake('public');

        $admin = User::factory()->role(UserRole::SuperAdmin)->create();
        $favicon = UploadedFile::fake()->image('favicon.png', 32, 32);

        $this->actingAs($admin)
            ->put(route('settings.update'), $this->baseSettingsPayload([
                'favicon' => $favicon,
            ]))
            ->assertRedirect(route('settings.index'));

        $settings = SystemSetting::query()->first();
        $this->assertNotNull($settings->favicon_path);
        Storage::disk('public')->assertExists($settings->favicon_path);
    }

    public function test_super_admin_can_remove_uploaded_favicon(): void
    {
        Storage::fake('public');

        $admin = User::factory()->role(UserRole::SuperAdmin)->create();
        $path = UploadedFile::fake()->image('favicon.png', 32, 32)->store('branding', 'public');

        SystemSetting::query()->first()?->update(['favicon_path' => $path]);

        $this->actingAs($admin)
            ->delete(route('settings.favicon.remove'))
            ->assertRedirect(route('settings.index'));

        $this->assertNull(SystemSetting::query()->value('favicon_path'));
        Storage::disk('public')->assertMissing($path);
    }

    /** @param  array<string, mixed>  $overrides */
    private function baseSettingsPayload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => SystemSetting::query()->value('company_name'),
            'tagline' => null,
            'system_subtitle' => 'Operations System',
            'login_headline' => 'Guards, shifts, billing and payroll',
            'company_short_name' => null,
            'employment_id_prefix' => 'PSG',
            'invoice_prefix' => 'INV',
            'payroll_run_prefix' => 'PAY',
            'shift_prefix' => 'SHF',
            'timezone' => config('app.timezone', 'Africa/Dar_es_Salaam'),
            'theme_primary' => '#1845de',
            'theme_sidebar' => '#070d18',
            'email_footer_text' => null,
            'notify_workflow_actions_by_email' => '1',
            'notify_proactive_alerts' => '1',
            'support_email' => null,
            'support_phone' => null,
            'currency' => 'UGX',
            'currency_label' => 'Ugandan Shillings',
            'currency_decimals' => 0,
            'vat_rate' => 18,
            'invoice_due_days' => 14,
            'payroll_default_base_shift_rate' => 25000,
            'payroll_standard_shifts_per_month' => 0,
            'payroll_overtime_multiplier' => 1.5,
            'payroll_paye_rate' => 0,
            'payroll_use_progressive_paye' => '1',
            'payroll_nssf_employee_rate' => 5,
            'payroll_uniform_charge' => 0,
            'payroll_bank_export_format' => 'generic',
            'default_day_shift_start' => '06:00',
            'default_day_shift_end' => '18:00',
            'default_night_shift_start' => '18:00',
            'default_night_shift_end' => '06:00',
            'supervisor_normal_start' => '06:00',
            'supervisor_normal_end' => '19:00',
            'backup_keep_days' => 14,
            'backup_keep_daily' => 14,
            'backup_keep_weekly' => 8,
            'backup_keep_monthly' => 12,
            'backup_stale_hours' => 36,
            'backup_path' => 'backups',
            'backup_schedule' => 'daily',
            'backup_notify' => '1',
            'backup_include_files' => '1',
        ], $overrides);
    }

    public function test_super_admin_can_remove_uploaded_logo(): void
    {
        Storage::fake('public');

        $admin = User::factory()->role(UserRole::SuperAdmin)->create();
        $path = UploadedFile::fake()->image('logo.png')->store('branding', 'public');

        SystemSetting::query()->first()?->update(['logo_path' => $path]);

        $this->actingAs($admin)
            ->delete(route('settings.logo.remove'))
            ->assertRedirect(route('settings.index'));

        $this->assertNull(SystemSetting::query()->value('logo_path'));
        Storage::disk('public')->assertMissing($path);
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
                'payroll_default_base_shift_rate' => 25000,
                'payroll_overtime_multiplier' => 1.5,
                'payroll_paye_rate' => 0,
                'payroll_use_progressive_paye' => '1',
                'payroll_nssf_employee_rate' => 5,
                'payroll_uniform_charge' => 0,
                'payroll_bank_export_format' => 'generic',
                'default_day_shift_start' => '06:00',
                'default_day_shift_end' => '18:00',
                'default_night_shift_start' => '18:00',
                'default_night_shift_end' => '06:00',
                'backup_keep_days' => 14,
                'backup_path' => 'backups',
            ])
            ->assertForbidden();
    }

    public function test_super_admin_can_update_payroll_platform_settings(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();

        $this->actingAs($admin)
            ->put(route('settings.update'), $this->baseSettingsPayload([
                'payroll_use_progressive_paye' => '0',
                'payroll_paye_rate' => 15,
                'payroll_bank_export_format' => 'stanbic',
            ]))
            ->assertRedirect(route('settings.index'));

        Cache::forget('system_settings.id');
        app(SystemSettingService::class)->applyRuntimeConfig();

        $this->assertDatabaseHas('system_settings', [
            'payroll_use_progressive_paye' => false,
            'payroll_paye_rate' => 15,
            'payroll_bank_export_format' => 'stanbic',
        ]);

        $this->assertFalse(config('psg.payroll.use_progressive_paye'));
        $this->assertSame(15.0, (float) config('psg.payroll.paye_rate'));
        $this->assertSame('stanbic', config('psg.payroll.bank_export_format'));
    }

    public function test_super_admin_can_update_payroll_defaults(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();

        $this->actingAs($admin)
            ->put(route('settings.update'), $this->baseSettingsPayload([
                'payroll_default_base_shift_rate' => 35000,
                'payroll_overtime_multiplier' => 2,
                'payroll_paye_rate' => 10,
                'payroll_nssf_employee_rate' => 6,
                'payroll_uniform_charge' => 5000,
            ]))
            ->assertRedirect(route('settings.index'));

        Cache::forget('system_settings.id');
        app(SystemSettingService::class)->applyRuntimeConfig();

        $this->assertDatabaseHas('system_settings', [
            'payroll_default_base_shift_rate' => 35000,
            'payroll_paye_rate' => 10,
            'payroll_uniform_charge' => 5000,
        ]);

        $this->assertSame(35000.0, (float) config('psg.payroll.default_monthly_gross'));
        $this->assertSame(round(35000 / now()->daysInMonth, 2), (float) config('psg.payroll.default_base_shift_rate'));
        $this->assertSame(10.0, (float) config('psg.payroll.paye_rate'));
        $this->assertSame(5000.0, (float) config('psg.payroll.uniform_charge'));
    }

    public function test_settings_update_is_audited(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();

        $this->actingAs($admin)
            ->put(route('settings.update'), $this->baseSettingsPayload([
                'invoice_due_days' => 30,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'system.settings_updated',
        ]);
    }

    public function test_employment_prefix_change_affects_next_generated_id(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();

        $this->actingAs($admin)
            ->put(route('settings.update'), $this->baseSettingsPayload([
                'employment_id_prefix' => 'SEC',
            ]))
            ->assertRedirect(route('settings.index'));

        Cache::forget('system_settings.id');
        app(SystemSettingService::class)->flushCache();
        app(SystemSettingService::class)->applyRuntimeConfig();

        $this->assertSame('SEC', config('psg.prefixes.employment'));
        $this->assertSame('SEC001', app(\App\Services\Hr\EmploymentIdService::class)->next());
    }

    public function test_timezone_and_supervisor_hours_apply_at_runtime(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();

        $this->actingAs($admin)
            ->put(route('settings.update'), $this->baseSettingsPayload([
                'timezone' => 'Africa/Kampala',
                'supervisor_normal_start' => '07:00',
                'supervisor_normal_end' => '19:00',
                'default_day_shift_start' => '07:00',
                'default_day_shift_end' => '19:00',
                'vat_rate' => 16,
                'login_headline' => 'Secure operations, configured your way',
            ]))
            ->assertRedirect(route('settings.index'));

        Cache::forget('system_settings.id');
        app(SystemSettingService::class)->flushCache();
        app(SystemSettingService::class)->applyRuntimeConfig();

        $this->assertSame('Africa/Kampala', config('app.timezone'));
        $this->assertSame('07:00', config('psg.supervisor_coverage.normal_start'));
        $this->assertSame('19:00', config('psg.supervisor_coverage.normal_end'));
        $this->assertSame('07:00', config('psg.shift_defaults.day.start'));
        $this->assertSame(16.0, (float) config('psg.vat_rate'));
        $this->assertSame('Secure operations, configured your way', config('psg.login_headline'));
    }

    public function test_settings_audit_includes_before_and_after_values(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();
        SystemSetting::query()->first()?->update(['invoice_due_days' => 14]);

        $this->actingAs($admin)
            ->put(route('settings.update'), $this->baseSettingsPayload([
                'invoice_due_days' => 45,
            ]))
            ->assertRedirect();

        $log = \App\Models\AuditLog::query()
            ->where('action', 'system.settings_updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $context = $log->context;
        $this->assertIsArray($context);
        $this->assertSame(14, (int) data_get($context, 'before.invoice_due_days'));
        $this->assertSame(45, (int) data_get($context, 'after.invoice_due_days'));
    }
}

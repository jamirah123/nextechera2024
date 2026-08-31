<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\SystemSetting;
use App\Models\User;
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
            ->assertSee('Platform settings')
            ->assertSee('Company branding')
            ->assertSee('Theme colors')
            ->assertSee('Email branding')
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
        app(\App\Services\SystemSettingService::class)->applyRuntimeConfig();

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
            ->put(route('settings.update'), [
                'company_name' => 'Acme Security Ltd',
                'tagline' => 'Protecting what matters',
                'system_subtitle' => 'Workforce ERP',
                'company_short_name' => 'ACME',
                'theme_primary' => '#0f766e',
                'theme_sidebar' => '#134e4a',
                'email_footer_text' => 'Confidential workforce communication.',
                'support_email' => 'hello@acme.test',
                'support_phone' => '+256700000001',
                'currency' => 'UGX',
                'currency_label' => 'Ugandan Shillings',
                'currency_decimals' => 0,
                'invoice_due_days' => 14,
                'payroll_default_base_shift_rate' => 25000,
            'payroll_standard_shifts_per_month' => 30,
                'payroll_overtime_multiplier' => 1.5,
                'payroll_paye_rate' => 0,
                'payroll_nssf_employee_rate' => 5,
                'payroll_uniform_charge' => 0,
                'default_day_shift_start' => '06:00',
                'default_day_shift_end' => '18:00',
                'default_night_shift_start' => '18:00',
                'default_night_shift_end' => '06:00',
                'backup_keep_days' => 14,
                'backup_path' => 'backups',
                'logo' => $logo,
            ])
            ->assertRedirect(route('settings.index'));

        Cache::forget('system_settings.id');
        app(\App\Services\SystemSettingService::class)->applyRuntimeConfig();

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
            'company_short_name' => null,
            'theme_primary' => '#1845de',
            'theme_sidebar' => '#070d18',
            'email_footer_text' => null,
            'support_email' => null,
            'support_phone' => null,
            'currency' => 'UGX',
            'currency_label' => 'Ugandan Shillings',
            'currency_decimals' => 0,
            'invoice_due_days' => 14,
            'payroll_default_base_shift_rate' => 25000,
            'payroll_standard_shifts_per_month' => 30,
            'payroll_overtime_multiplier' => 1.5,
            'payroll_paye_rate' => 0,
            'payroll_nssf_employee_rate' => 5,
            'payroll_uniform_charge' => 0,
            'default_day_shift_start' => '06:00',
            'default_day_shift_end' => '18:00',
            'default_night_shift_start' => '18:00',
            'default_night_shift_end' => '06:00',
            'backup_keep_days' => 14,
            'backup_path' => 'backups',
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
            'payroll_standard_shifts_per_month' => 30,
                'payroll_overtime_multiplier' => 1.5,
                'payroll_paye_rate' => 0,
                'payroll_nssf_employee_rate' => 5,
                'payroll_uniform_charge' => 0,
                'default_day_shift_start' => '06:00',
                'default_day_shift_end' => '18:00',
                'default_night_shift_start' => '18:00',
                'default_night_shift_end' => '06:00',
                'backup_keep_days' => 14,
                'backup_path' => 'backups',
            ])
            ->assertForbidden();
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
        app(\App\Services\SystemSettingService::class)->applyRuntimeConfig();

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
}

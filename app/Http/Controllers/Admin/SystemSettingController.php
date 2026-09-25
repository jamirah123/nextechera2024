<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BackupType;
use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\DatabaseBackupService;
use App\Services\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\Console\Output\BufferedOutput;

class SystemSettingController extends Controller
{
    public function __construct(private SystemSettingService $settings) {}

    public function index(): View
    {
        $this->authorize('viewAny', SystemSetting::class);

        $settings = $this->settings->current()->load('updater:id,name');

        return view('admin.settings.index', [
            'settings' => $settings,
            'environment' => [
                'app_env' => config('app.env'),
                'timezone' => config('app.timezone'),
                'database' => config('database.default'),
            ],
            'timezones' => \DateTimeZone::listIdentifiers(\DateTimeZone::ALL),
            'backups' => $this->recentBackups($settings->backup_path),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('update', SystemSetting::class);

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:191'],
            'tagline' => ['nullable', 'string', 'max:191'],
            'system_subtitle' => ['nullable', 'string', 'max:120'],
            'login_headline' => ['nullable', 'string', 'max:191'],
            'company_short_name' => ['nullable', 'string', 'max:12'],
            'employment_id_prefix' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z][A-Za-z0-9]*$/'],
            'invoice_prefix' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z][A-Za-z0-9]*$/'],
            'payroll_run_prefix' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z][A-Za-z0-9]*$/'],
            'shift_prefix' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z][A-Za-z0-9]*$/'],
            'timezone' => ['required', 'timezone:all'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico,svg', 'max:512'],
            'theme_primary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_sidebar' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'email_footer_text' => ['nullable', 'string', 'max:500'],
            'notify_workflow_actions_by_email' => ['nullable', 'boolean'],
            'notify_proactive_alerts' => ['nullable', 'boolean'],
            'support_email' => ['nullable', 'email', 'max:190'],
            'support_phone' => ['nullable', 'string', 'max:40'],
            'currency' => ['required', 'string', 'max:8'],
            'currency_label' => ['required', 'string', 'max:80'],
            'currency_decimals' => ['required', 'integer', 'min:0', 'max:4'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'invoice_due_days' => ['required', 'integer', 'min:1', 'max:120'],
            'company_bank_name' => ['nullable', 'string', 'max:120'],
            'company_bank_account' => ['nullable', 'string', 'max:80'],
            'company_bank_branch' => ['nullable', 'string', 'max:120'],
            'invoice_payment_terms' => ['nullable', 'string', 'max:1000'],
            'payroll_default_base_shift_rate' => ['required', 'numeric', 'min:0'],
            'payroll_standard_shifts_per_month' => ['required', 'integer', 'min:1', 'max:62'],
            'payroll_overtime_multiplier' => ['required', 'numeric', 'min:1', 'max:5'],
            'payroll_paye_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'payroll_use_progressive_paye' => ['nullable', 'boolean'],
            'payroll_paye_brackets' => ['required', 'array'],
            'payroll_paye_brackets.threshold_tax_free' => ['required', 'numeric', 'min:0'],
            'payroll_paye_brackets.band_20_max' => ['required', 'numeric', 'min:0'],
            'payroll_paye_brackets.band_25_max' => ['required', 'numeric', 'min:0'],
            'payroll_paye_brackets.surtax_threshold' => ['required', 'numeric', 'min:0'],
            'payroll_paye_brackets.band_25_base' => ['required', 'numeric', 'min:0'],
            'payroll_paye_brackets.band_30_base' => ['required', 'numeric', 'min:0'],
            'payroll_paye_brackets.rate_20' => ['required', 'numeric', 'min:0', 'max:100'],
            'payroll_paye_brackets.rate_25' => ['required', 'numeric', 'min:0', 'max:100'],
            'payroll_paye_brackets.rate_30' => ['required', 'numeric', 'min:0', 'max:100'],
            'payroll_paye_brackets.rate_surtax' => ['required', 'numeric', 'min:0', 'max:100'],
            'payroll_paye_brackets.label' => ['required', 'string', 'max:120'],
            'payroll_nssf_employee_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'payroll_uniform_charge' => ['required', 'numeric', 'min:0'],
            'payroll_bank_export_format' => ['required', 'string', 'in:generic,centenary,stanbic'],
            'payroll_send_payslip_email_on_approve' => ['nullable', 'boolean'],
            'default_day_shift_start' => ['required', 'date_format:H:i'],
            'default_day_shift_end' => ['required', 'date_format:H:i'],
            'default_night_shift_start' => ['required', 'date_format:H:i'],
            'default_night_shift_end' => ['required', 'date_format:H:i'],
            'supervisor_normal_start' => ['required', 'date_format:H:i'],
            'supervisor_normal_end' => ['required', 'date_format:H:i'],
            'backup_keep_days' => ['required', 'integer', 'min:1', 'max:365'],
            'backup_keep_daily' => ['required', 'integer', 'min:1', 'max:365'],
            'backup_keep_weekly' => ['required', 'integer', 'min:1', 'max:52'],
            'backup_keep_monthly' => ['required', 'integer', 'min:1', 'max:60'],
            'backup_include_files' => ['nullable', 'boolean'],
            'backup_stale_hours' => ['required', 'integer', 'min:6', 'max:168'],
            'backup_path' => ['required', 'string', 'max:120', 'regex:/^[a-zA-Z0-9_\-\/]+$/'],
            'backup_schedule' => ['required', 'string', 'in:daily,weekly,daily_and_weekly'],
            'backup_notify' => ['nullable', 'boolean'],
            'backup_offsite_disk' => ['nullable', 'string', 'max:40', Rule::in(['s3'])],
        ]);

        $logo = $request->file('logo');
        $favicon = $request->file('favicon');
        unset($data['logo'], $data['favicon']);

        $data['employment_id_prefix'] = strtoupper((string) $data['employment_id_prefix']);
        $data['invoice_prefix'] = strtoupper((string) $data['invoice_prefix']);
        $data['payroll_run_prefix'] = strtoupper((string) $data['payroll_run_prefix']);
        $data['shift_prefix'] = strtoupper((string) $data['shift_prefix']);
        $data['payroll_use_progressive_paye'] = $request->boolean('payroll_use_progressive_paye');
        $data['payroll_send_payslip_email_on_approve'] = $request->boolean('payroll_send_payslip_email_on_approve');
        $data['notify_workflow_actions_by_email'] = $request->boolean('notify_workflow_actions_by_email');
        $data['notify_proactive_alerts'] = $request->boolean('notify_proactive_alerts');
        $data['backup_notify'] = $request->boolean('backup_notify');
        $data['backup_include_files'] = $request->boolean('backup_include_files');
        $data['backup_offsite_disk'] = filled($data['backup_offsite_disk'] ?? null) ? $data['backup_offsite_disk'] : null;
        $data['payroll_standard_shifts_per_month'] = max(1, (int) ($data['payroll_standard_shifts_per_month'] ?? 30));
        $data['payroll_paye_brackets'] = \App\Support\Finance\PayrollPayeCalculator::bracketsFrom(
            is_array($data['payroll_paye_brackets'] ?? null) ? $data['payroll_paye_brackets'] : []
        );

        $brackets = $data['payroll_paye_brackets'];
        if (
            $brackets['threshold_tax_free'] >= $brackets['band_20_max']
            || $brackets['band_20_max'] >= $brackets['band_25_max']
            || $brackets['band_25_max'] >= $brackets['surtax_threshold']
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'payroll_paye_brackets.band_20_max' => 'PAYE thresholds must increase: tax-free < 20% band max < 25% band max < surtax threshold.',
                ]);
        }

        // Keep legacy keep_days aligned with the daily retention bucket.
        $data['backup_keep_days'] = (int) $data['backup_keep_daily'];

        $this->settings->update($data, $logo, $favicon);

        return redirect()
            ->route('settings.index')
            ->with('status', 'Platform settings saved.');
    }

    public function removeLogo(): RedirectResponse
    {
        $this->authorize('update', SystemSetting::class);

        $this->settings->removeLogo();

        return redirect()
            ->route('settings.index')
            ->with('status', 'Company logo removed.');
    }

    public function removeFavicon(): RedirectResponse
    {
        $this->authorize('update', SystemSetting::class);

        $this->settings->removeFavicon();

        return redirect()
            ->route('settings.index')
            ->with('status', 'Favicon removed.');
    }

    public function backup(): RedirectResponse
    {
        $this->authorize('runMaintenance', SystemSetting::class);

        try {
            $backup = app(DatabaseBackupService::class)->create(
                BackupType::Manual,
                request()->user(),
                ['notes' => 'Manual backup from Platform Settings.'],
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        return redirect()
            ->route('backups.show', $backup)
            ->with('status', 'Backup '.$backup->reference.' completed successfully.');
    }

    public function productionCheck(): RedirectResponse
    {
        $this->authorize('runMaintenance', SystemSetting::class);

        $buffer = new BufferedOutput;
        $exitCode = Artisan::call('psg:production-check', [], $buffer);
        $output = trim($buffer->fetch());

        if ($exitCode !== 0) {
            return back()->withErrors(['production' => $output ?: 'Production check failed.']);
        }

        return back()->with('status', $output ?: 'Production check passed.');
    }

    /** @return list<array{name: string, size: string, modified: string}> */
    private function recentBackups(string $relativePath): array
    {
        $dir = storage_path('app/'.trim($relativePath, '/\\'));

        if (! is_dir($dir)) {
            return [];
        }

        return collect(File::files($dir))
            ->filter(fn ($file) => str_starts_with($file->getFilename(), 'psg-'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->take(8)
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => $this->formatBytes($file->getSize()),
                'modified' => date('d M Y H:i', $file->getMTime()),
            ])
            ->values()
            ->all();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\Console\Output\BufferedOutput;

class SystemSettingController extends Controller
{
    public function __construct(private SystemSettingService $settings)
    {
    }

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
            'backups' => $this->recentBackups($settings->backup_path),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('update', SystemSetting::class);

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:191'],
            'support_email' => ['nullable', 'email', 'max:190'],
            'support_phone' => ['nullable', 'string', 'max:40'],
            'currency' => ['required', 'string', 'max:8'],
            'currency_label' => ['required', 'string', 'max:80'],
            'currency_decimals' => ['required', 'integer', 'min:0', 'max:4'],
            'invoice_due_days' => ['required', 'integer', 'min:1', 'max:120'],
            'default_day_shift_start' => ['required', 'date_format:H:i'],
            'default_day_shift_end' => ['required', 'date_format:H:i'],
            'default_night_shift_start' => ['required', 'date_format:H:i'],
            'default_night_shift_end' => ['required', 'date_format:H:i'],
            'backup_keep_days' => ['required', 'integer', 'min:1', 'max:365'],
            'backup_path' => ['required', 'string', 'max:120', 'regex:/^[a-zA-Z0-9_\-\/]+$/'],
        ]);

        $this->settings->update($data);

        return redirect()
            ->route('settings.index')
            ->with('status', 'System settings saved.');
    }

    public function backup(): RedirectResponse
    {
        $this->authorize('runMaintenance', SystemSetting::class);

        $settings = $this->settings->current();

        $exitCode = Artisan::call('psg:backup-database', [
            '--keep' => $settings->backup_keep_days,
            '--path' => $settings->backup_path,
        ]);

        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            return back()->withErrors(['backup' => $output ?: 'Database backup failed.']);
        }

        return back()->with('status', $output ?: 'Database backup completed.');
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

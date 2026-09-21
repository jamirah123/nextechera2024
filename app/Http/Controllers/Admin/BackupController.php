<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Http\Controllers\Controller;
use App\Models\DatabaseBackup;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(private DatabaseBackupService $backups) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', DatabaseBackup::class);

        $filters = $request->only(['q', 'status', 'type']);
        $latest = $this->backups->latestSuccessful();
        $hoursSince = $this->backups->hoursSinceLastSuccessfulBackup();
        $staleHours = (int) config('psg.backup.stale_hours', 36);

        return view('admin.backups.index', [
            'backups' => $this->backups->paginate($filters),
            'filters' => $filters,
            'statuses' => BackupStatus::cases(),
            'types' => BackupType::cases(),
            'latest' => $latest,
            'hours_since' => $hoursSince,
            'is_stale' => $hoursSince === null || $hoursSince >= $staleHours,
            'settings' => [
                'keep_daily' => (int) config('psg.backup.keep_daily', config('psg.backup.keep_days', 14)),
                'keep_weekly' => (int) config('psg.backup.keep_weekly', 8),
                'keep_monthly' => (int) config('psg.backup.keep_monthly', 12),
                'include_files' => (bool) config('psg.backup.include_files', true),
                'stale_hours' => $staleHours,
                'path' => (string) config('psg.backup.path', 'backups'),
                'schedule' => (string) config('psg.backup.schedule', 'daily'),
                'offsite' => (string) (config('psg.backup.offsite_disk') ?: 'local only'),
                'notify' => (bool) config('psg.backup.notify', true),
            ],
        ]);
    }

    public function show(DatabaseBackup $backup): View
    {
        $this->authorize('view', $backup);

        $backup->load(['creator:id,name', 'verifier:id,name', 'restorer:id,name']);

        return view('admin.backups.show', [
            'backup' => $backup,
            'fileExists' => $backup->fileExists(),
            'filesArchiveExists' => $backup->filesArchiveExists(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DatabaseBackup::class);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $backup = $this->backups->create(BackupType::Manual, $request->user(), [
                'notes' => $data['notes'] ?? 'Manual backup initiated from admin console.',
            ]);
        } catch (Throwable $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        return redirect()
            ->route('backups.show', $backup)
            ->with('status', 'Backup '.$backup->reference.' completed successfully.');
    }

    public function download(DatabaseBackup $backup): StreamedResponse|RedirectResponse
    {
        $this->authorize('download', $backup);

        try {
            return $this->backups->download($backup);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }
    }

    public function downloadFiles(DatabaseBackup $backup): StreamedResponse|RedirectResponse
    {
        $this->authorize('downloadFiles', $backup);

        try {
            return $this->backups->downloadFiles($backup);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }
    }

    public function verify(DatabaseBackup $backup): RedirectResponse
    {
        $this->authorize('verify', $backup);

        try {
            $this->backups->verify($backup, request()->user());
        } catch (Throwable $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        return back()->with('status', 'Backup '.$backup->reference.' passed integrity verification.');
    }

    public function testRestore(DatabaseBackup $backup): RedirectResponse
    {
        $this->authorize('testRestore', $backup);

        try {
            $result = $this->backups->testRestore($backup, request()->user());
        } catch (Throwable $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        return back()->with('status', 'Restore drill passed for '.$backup->reference.'. '.$result['notes']);
    }

    public function restore(Request $request, DatabaseBackup $backup): RedirectResponse
    {
        $this->authorize('restore', $backup);

        $data = $request->validate([
            'confirmation' => ['required', 'in:RESTORE'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'confirmation.in' => 'Type RESTORE in capitals to confirm this destructive operation.',
        ]);

        try {
            $result = $this->backups->restore($backup, $request->user(), $data['notes'] ?? null);
        } catch (Throwable $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        return redirect()
            ->route('backups.show', $result['restored'])
            ->with(
                'status',
                'Database restored from '.$result['restored']->reference
                .'. Safety backup saved as '.$result['safety']->reference.'.'
            );
    }

    public function restoreFiles(Request $request, DatabaseBackup $backup): RedirectResponse
    {
        $this->authorize('restoreFiles', $backup);

        $data = $request->validate([
            'confirmation' => ['required', 'in:RESTORE FILES'],
            'overwrite' => ['nullable', 'boolean'],
        ], [
            'confirmation.in' => 'Type RESTORE FILES in capitals to confirm.',
        ]);

        try {
            $count = $this->backups->restoreFiles(
                $backup,
                $request->user(),
                $request->boolean('overwrite'),
            );
        } catch (Throwable $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        return back()->with('status', 'Restored '.$count.' file(s) from '.$backup->reference.'.');
    }

    public function destroy(DatabaseBackup $backup): RedirectResponse
    {
        $this->authorize('delete', $backup);

        try {
            $this->backups->dismissFailed($backup, request()->user());
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        return redirect()
            ->route('backups.index')
            ->with('status', 'Failed backup record dismissed.');
    }
}

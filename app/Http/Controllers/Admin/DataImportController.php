<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCsvImportJob;
use App\Jobs\RunAccountingExportJob;
use App\Services\Exports\AccountingExportService;
use App\Services\Imports\GuardImportService;
use App\Services\Imports\OpeningBalanceImportService;
use App\Services\Imports\SiteImportService;
use App\Services\ReportExportService;
use App\Services\SystemSettingService;
use App\Support\Access\Access;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataImportController extends Controller
{
    public function __construct(
        private ReportExportService $exports,
        private GuardImportService $guardImport,
        private SiteImportService $siteImport,
        private OpeningBalanceImportService $openingBalances,
        private AccountingExportService $accountingExport,
        private SystemSettingService $settings,
    ) {}

    public function index(): View
    {
        $this->authorizeAccess();

        $settings = $this->settings->current();
        $exportPath = trim((string) ($settings->accounting_export_path ?: 'exports/accounting'), '/');
        $recentExports = collect(Storage::disk('local')->files($exportPath))
            ->filter(fn (string $path) => str_ends_with(strtolower($path), '.csv'))
            ->sortDesc()
            ->take(8)
            ->values();

        return view('admin.data-import.index', [
            'canImportGuards' => Access::userCan(auth()->user(), 'guards.manage'),
            'canImportSites' => Access::userCan(auth()->user(), 'organization.manage'),
            'canImportFinance' => Access::userCan(auth()->user(), 'finance.manage'),
            'settings' => $settings,
            'recentExports' => $recentExports,
        ]);
    }

    public function template(string $type): StreamedResponse
    {
        $this->authorizeAccess();

        [$headers, $rows, $filename] = match ($type) {
            'guards' => $this->guardTemplate(),
            'sites' => $this->siteTemplate(),
            'opening-balances' => $this->openingBalanceTemplate(),
            default => abort(404),
        };

        return $this->exports->downloadCsv($filename, $headers, $rows);
    }

    public function importGuards(Request $request): RedirectResponse
    {
        $this->authorizeAccess();
        abort_unless(Access::userCan($request->user(), 'guards.manage'), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $path = $request->file('file')->store('imports/guards');
        ProcessCsvImportJob::dispatch('guards', $path);

        return back()->with('status', 'Guard import queued. Refresh this page shortly to see results.');
    }

    public function importSites(Request $request): RedirectResponse
    {
        $this->authorizeAccess();
        abort_unless(Access::userCan($request->user(), 'organization.manage'), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $path = $request->file('file')->store('imports/sites');
        ProcessCsvImportJob::dispatch('sites', $path);

        return back()->with('status', 'Site import queued. Refresh this page shortly to see results.');
    }

    public function importOpeningBalances(Request $request): RedirectResponse
    {
        $this->authorizeAccess();
        abort_unless(Access::userCan($request->user(), 'finance.manage'), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $path = $request->file('file')->store('imports/opening-balances');
        ProcessCsvImportJob::dispatch('opening-balances', $path);

        return back()->with('status', 'Opening balance import queued. Refresh this page shortly to see results.');
    }

    public function updateAccountingExport(Request $request): RedirectResponse
    {
        $this->authorizeAccess();
        abort_unless(Access::userCan($request->user(), 'finance.manage'), 403);

        $data = $request->validate([
            'accounting_export_enabled' => ['nullable', 'boolean'],
            'accounting_export_path' => ['required', 'string', 'max:120', 'regex:/^[a-zA-Z0-9_\-\/]+$/'],
        ]);

        $data['accounting_export_enabled'] = $request->boolean('accounting_export_enabled');

        $this->settings->update($data);

        return back()->with('status', 'Accounting export settings saved.');
    }

    public function runAccountingExport(Request $request): RedirectResponse
    {
        $this->authorizeAccess();
        abort_unless(Access::userCan($request->user(), 'finance.manage'), 403);

        RunAccountingExportJob::dispatch();

        return back()->with('status', 'Accounting export queued. Check recent exports in a moment.');
    }

    public function downloadExport(string $file): StreamedResponse
    {
        $this->authorizeAccess();
        abort_unless(Access::userCan(auth()->user(), 'finance.manage'), 403);

        $settings = $this->settings->current();
        $base = trim((string) ($settings->accounting_export_path ?: 'exports/accounting'), '/');
        $path = $base.'/'.basename($file);

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }

    /** @return array{0: list<string>, 1: list<list<string>>, 2: string} */
    private function guardTemplate(): array
    {
        abort_unless(Access::userCan(auth()->user(), 'guards.manage'), 403);

        return [
            $this->guardImport->templateHeaders(),
            $this->guardImport->templateSampleRows(),
            'psg-guards-import-template.csv',
        ];
    }

    /** @return array{0: list<string>, 1: list<list<string>>, 2: string} */
    private function siteTemplate(): array
    {
        abort_unless(Access::userCan(auth()->user(), 'organization.manage'), 403);

        return [
            $this->siteImport->templateHeaders(),
            $this->siteImport->templateSampleRows(),
            'psg-sites-import-template.csv',
        ];
    }

    /** @return array{0: list<string>, 1: list<list<string>>, 2: string} */
    private function openingBalanceTemplate(): array
    {
        abort_unless(Access::userCan(auth()->user(), 'finance.manage'), 403);

        return [
            $this->openingBalances->templateHeaders(),
            $this->openingBalances->templateSampleRows(),
            'psg-opening-balances-import-template.csv',
        ];
    }

    private function authorizeAccess(): void
    {
        abort_unless(
            Access::userCan(auth()->user(), 'admin.data_import'),
            403,
        );
    }
}

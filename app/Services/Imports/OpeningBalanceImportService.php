<?php

namespace App\Services\Imports;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Guard;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Site;
use App\Services\AuditService;
use App\Services\Finance\GuardAdvanceService;
use App\Support\Imports\ImportResult;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OpeningBalanceImportService
{
    public function __construct(
        private CsvImportService $csv,
        private GuardAdvanceService $advances,
        private AuditService $audit,
    ) {}

    /** @return list<string> */
    public function templateHeaders(): array
    {
        return [
            'record_type',
            'client_name',
            'site_code',
            'employment_id',
            'description',
            'total_amount',
            'amount_paid',
            'balance_remaining',
            'monthly_installment',
            'due_date',
            'period_start',
            'period_end',
            'notes',
        ];
    }

    /** @return list<list<string>> */
    public function templateSampleRows(): array
    {
        return [
            [
                'client_invoice',
                'Acme Industries Ltd',
                'ACME-HQ',
                '',
                'Opening balance — security services Jan',
                '2500000',
                '1000000',
                '',
                '',
                now()->addDays(14)->toDateString(),
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
                'Migrated invoice balance',
            ],
            [
                'guard_advance',
                '',
                '',
                'PSG0001',
                'Salary advance opening balance',
                '500000',
                '',
                '350000',
                '50000',
                '',
                '',
                '',
                'Outstanding advance at go-live',
            ],
        ];
    }

    public function import(UploadedFile $file): ImportResult
    {
        $rows = $this->csv->readRows($file);
        $result = new ImportResult;

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $type = strtolower(trim((string) ($row['record_type'] ?? '')));

            try {
                match ($type) {
                    'client_invoice', 'invoice' => $this->importClientInvoice($row, $line, $result),
                    'guard_advance', 'advance' => $this->importGuardAdvance($row, $line, $result),
                    default => $result->addError($line, 'Unknown record_type. Use client_invoice or guard_advance.'),
                };
            } catch (\Throwable $e) {
                $result->addError($line, $e->getMessage());
            }
        }

        return $result;
    }

    /** @param  array<string, string|null>  $row */
    private function importClientInvoice(array $row, int $line, ImportResult $result): void
    {
        $payload = [
            'client_name' => $row['client_name'] ?? null,
            'site_code' => $row['site_code'] ?? null,
            'description' => $row['description'] ?? 'Opening balance',
            'total_amount' => $this->csv->parseNumber($row['total_amount'] ?? null),
            'amount_paid' => $this->csv->parseNumber($row['amount_paid'] ?? null) ?? 0,
            'due_date' => $row['due_date'] ?? null,
            'period_start' => $row['period_start'] ?? null,
            'period_end' => $row['period_end'] ?? null,
            'notes' => $row['notes'] ?? null,
        ];

        $validator = Validator::make($payload, [
            'client_name' => ['required', 'string', 'max:191'],
            'site_code' => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:191'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'amount_paid' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            $result->addError($line, $validator->errors()->first());

            return;
        }

        $data = $validator->validated();

        $clientId = Client::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($data['client_name'])])
            ->value('id');

        if (! $clientId) {
            $result->addError($line, 'Client not found: '.$data['client_name']);

            return;
        }

        $siteId = null;
        if (filled($data['site_code'])) {
            $siteId = Site::query()
                ->whereRaw('LOWER(code) = ?', [strtolower($data['site_code'])])
                ->value('id');

            if (! $siteId) {
                $result->addError($line, 'Site not found: '.$data['site_code']);

                return;
            }
        }

        $total = round((float) $data['total_amount'], 2);
        $paid = round(min((float) $data['amount_paid'], $total), 2);
        $balance = round(max(0, $total - $paid), 2);
        $dueDate = $data['due_date'] ?? Carbon::parse($data['period_end'])->addDays((int) config('psg.invoice_due_days', 14))->toDateString();

        $status = match (true) {
            $balance <= 0 => InvoiceStatus::Paid,
            $paid > 0 => InvoiceStatus::PartiallyPaid,
            Carbon::parse($dueDate)->lt(now()->startOfDay()) => InvoiceStatus::Overdue,
            default => InvoiceStatus::Issued,
        };

        DB::transaction(function () use ($data, $clientId, $siteId, $total, $paid, $balance, $dueDate, $status, &$result): void {
            $invoice = Invoice::query()->create([
                'reference' => $this->nextInvoiceReference($data['period_start']),
                'client_id' => $clientId,
                'site_id' => $siteId,
                'status' => $status,
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'issue_date' => now()->toDateString(),
                'due_date' => $dueDate,
                'currency' => Money::currency(),
                'subtotal' => $total,
                'tax_amount' => 0,
                'total' => $total,
                'amount_paid' => $paid,
                'balance' => $balance,
                'notes' => trim(($data['notes'] ?? '').' [Opening balance import]'),
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'description' => $data['description'],
                'quantity' => 1,
                'unit_price' => $total,
                'line_total' => $total,
                'sort_order' => 0,
            ]);

            $this->audit->log(
                action: 'finance.opening_balance_imported',
                summary: 'Opening invoice balance imported for '.$data['client_name'].' ('.$invoice->reference.').',
                category: AuditCategory::Finance,
                severity: AuditSeverity::Notice,
                subject: $invoice,
            );

            $result->created++;
        });
    }

    /** @param  array<string, string|null>  $row */
    private function importGuardAdvance(array $row, int $line, ImportResult $result): void
    {
        $original = $this->csv->parseNumber($row['total_amount'] ?? null);
        $remaining = $this->csv->parseNumber($row['balance_remaining'] ?? null);

        $payload = [
            'employment_id' => $row['employment_id'] ?? null,
            'description' => $row['description'] ?? 'Opening balance advance',
            'original_amount' => $original,
            'balance_remaining' => $remaining ?? $original,
            'monthly_installment' => $this->csv->parseNumber($row['monthly_installment'] ?? null),
            'notes' => $row['notes'] ?? null,
        ];

        $validator = Validator::make($payload, [
            'employment_id' => ['required', 'string', 'max:20'],
            'description' => ['required', 'string', 'max:191'],
            'original_amount' => ['required', 'numeric', 'min:0.01'],
            'balance_remaining' => ['required', 'numeric', 'min:0'],
            'monthly_installment' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            $result->addError($line, $validator->errors()->first());

            return;
        }

        $data = $validator->validated();

        if ((float) $data['balance_remaining'] > (float) $data['original_amount']) {
            $result->addError($line, 'Balance remaining cannot exceed original amount.');

            return;
        }

        $guard = Guard::query()
            ->whereRaw('UPPER(employment_id) = ?', [strtoupper($data['employment_id'])])
            ->first();

        if (! $guard) {
            $result->addError($line, 'Guard not found: '.$data['employment_id']);

            return;
        }

        $advance = $this->advances->create($guard, [
            'label' => $data['description'],
            'original_amount' => $data['original_amount'],
            'monthly_installment' => $data['monthly_installment'],
            'notes' => trim(($data['notes'] ?? '').' [Opening balance import]'),
        ], auth()->user());

        $advance->update([
            'balance_remaining' => round((float) $data['balance_remaining'], 2),
            'is_active' => (float) $data['balance_remaining'] > 0,
        ]);

        $this->audit->log(
            action: 'finance.opening_balance_imported',
            summary: 'Opening salary advance imported for '.$guard->employment_id.'.',
            category: AuditCategory::Finance,
            severity: AuditSeverity::Notice,
            subject: $advance,
        );

        $result->created++;
    }

    private function nextInvoiceReference(string $periodStart): string
    {
        $stamp = Carbon::parse($periodStart)->format('Ym');
        $count = Invoice::withTrashed()->where('reference', 'like', "INV-{$stamp}-%")->count() + 1;

        return 'INV-'.$stamp.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}

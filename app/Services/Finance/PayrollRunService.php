<?php

namespace App\Services\Finance;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\GlJournalSource;
use App\Enums\PayrollRunStatus;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Finance\Ledger\LedgerPostingService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PayrollRunService
{
    public function __construct(
        private PayrollCalculationService $calculator,
        private PaymentService $payments,
        private AuditService $audit,
        private LedgerPostingService $ledger,
    ) {
    }

    /**
     * @param  array{
     *     period_year: int,
     *     period_month: int,
     *     region_id?: int|null,
     *     site_id?: int|null,
     *     notes?: string|null
     * }  $data
     */
    public function createDraft(array $data, ?User $actor = null): PayrollRun
    {
        $year = (int) $data['period_year'];
        $month = (int) $data['period_month'];
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        if (! self::isPeriodClosed($year, $month)) {
            throw new InvalidArgumentException(
                'Payroll for '.$start->format('F Y').' cannot be opened until the month has ended. Choose a completed period.'
            );
        }

        $existing = PayrollRun::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->where('region_id', $data['region_id'] ?? null)
            ->where('site_id', $data['site_id'] ?? null)
            ->whereNot('status', PayrollRunStatus::Cancelled->value)
            ->first();

        if ($existing) {
            $message = $existing->status === PayrollRunStatus::Paid
                ? 'Payroll for '.$start->format('F Y').' has already been paid for this scope. Choose a different period.'
                : 'A payroll run already exists for this period and scope.';

            throw new InvalidArgumentException($message);
        }

        return DB::transaction(function () use ($data, $start, $end, $year, $month, $actor) {
            $run = PayrollRun::query()->create([
                'reference' => $this->nextReference($year, $month),
                'period_year' => $year,
                'period_month' => $month,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'region_id' => $data['region_id'] ?? null,
                'site_id' => $data['site_id'] ?? null,
                'status' => PayrollRunStatus::Draft,
                'currency' => Money::currency(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor?->id,
            ]);

            $this->audit->log(
                action: 'payroll.run_created',
                summary: 'Draft payroll run '.$run->reference.' opened.',
                category: AuditCategory::Finance,
                severity: AuditSeverity::Notice,
                subject: $run,
            );

            return $run;
        });
    }

    public function calculate(PayrollRun $run): PayrollRun
    {
        return $this->calculator->calculate($run);
    }

    public function approve(PayrollRun $run, ?User $actor = null): PayrollRun
    {
        if ($run->status !== PayrollRunStatus::Submitted) {
            throw new InvalidArgumentException('Only submitted payroll runs can be approved.');
        }

        if ($run->payslips()->count() === 0) {
            throw new InvalidArgumentException('Cannot approve a payroll run with no payslips.');
        }

        $run->update([
            'status' => PayrollRunStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $actor?->id,
        ]);

        $this->audit->log(
            action: 'payroll.approved',
            summary: 'Payroll run '.$run->reference.' approved.',
            category: AuditCategory::Finance,
            severity: AuditSeverity::Notice,
            subject: $run,
        );

        $run = $run->fresh(['payslips', 'approver']);
        $this->ledger->postPayrollAccrual($run, $actor);

        return $run;
    }

    public function submit(PayrollRun $run, ?User $actor = null): PayrollRun
    {
        if ($run->status !== PayrollRunStatus::Calculated) {
            throw new InvalidArgumentException('Only calculated payroll runs can be submitted for approval.');
        }

        if ($run->payslips()->count() === 0) {
            throw new InvalidArgumentException('Calculate payroll before submitting for approval.');
        }

        $run->update([
            'status' => PayrollRunStatus::Submitted,
            'submitted_at' => now(),
            'submitted_by' => $actor?->id,
        ]);

        $this->audit->log(
            action: 'payroll.submitted',
            summary: 'Payroll '.$run->reference.' ('.$run->periodLabel().') submitted for Managing Director approval.',
            category: AuditCategory::Finance,
            severity: AuditSeverity::Warning,
            subject: $run,
            actor: $actor,
        );

        return $run->fresh(['payslips', 'submitter']);
    }

    public function reject(PayrollRun $run, ?User $actor = null, ?string $reason = null): PayrollRun
    {
        if ($run->status !== PayrollRunStatus::Submitted) {
            throw new InvalidArgumentException('Only submitted payroll runs can be returned to finance.');
        }

        $notes = $run->notes;

        if (filled($reason)) {
            $notes = trim(($notes ? $notes."\n\n" : '').'Returned to finance: '.$reason);
        }

        $run->update([
            'status' => PayrollRunStatus::Calculated,
            'submitted_at' => null,
            'submitted_by' => null,
            'notes' => $notes,
        ]);

        $this->audit->log(
            action: 'payroll.rejected',
            summary: 'Payroll run '.$run->reference.' returned to finance for revision.',
            category: AuditCategory::Finance,
            severity: AuditSeverity::Warning,
            subject: $run,
            actor: $actor,
            context: [
                'reason' => $reason,
            ],
        );

        return $run->fresh(['payslips', 'submitter']);
    }

    public function markPaid(PayrollRun $run, ?User $actor = null): PayrollRun
    {
        if ($run->status !== PayrollRunStatus::Approved) {
            throw new InvalidArgumentException('Only approved payroll runs can be marked as paid.');
        }

        return DB::transaction(function () use ($run, $actor) {
            $run->update([
                'status' => PayrollRunStatus::Paid,
                'paid_at' => now(),
                'paid_by' => $actor?->id,
            ]);

            $this->payments->recordPayrollDisbursement($run->fresh(), $actor);
            $this->ledger->postPayrollPayment($run->fresh(), $actor);

            $this->audit->log(
                action: 'payroll.paid',
                summary: 'Payroll run '.$run->reference.' marked as paid.',
                category: AuditCategory::Finance,
                severity: AuditSeverity::Notice,
                subject: $run,
            );

            return $run->fresh(['payslips', 'payer', 'payment']);
        });
    }

    public function cancel(PayrollRun $run): PayrollRun
    {
        if ($run->status === PayrollRunStatus::Cancelled) {
            throw new InvalidArgumentException('This payroll run is already cancelled.');
        }

        return DB::transaction(function () use ($run) {
            $previousStatus = $run->status;

            if ($previousStatus === PayrollRunStatus::Paid) {
                $this->payments->removePayrollDisbursement($run);
                $this->ledger->reverseForDocument(GlJournalSource::PayrollPayment, $run, auth()->user(), 'Payroll cancelled');
            }

            if (in_array($previousStatus, [PayrollRunStatus::Approved, PayrollRunStatus::Paid], true)) {
                $this->ledger->reverseForDocument(GlJournalSource::PayrollAccrual, $run, auth()->user(), 'Payroll cancelled');
            }

            if ($run->payslips()->exists()) {
                $this->calculator->clearPayslips($run);
            }

            $run->update([
                'status' => PayrollRunStatus::Cancelled,
                'submitted_at' => null,
                'submitted_by' => null,
                'approved_at' => null,
                'approved_by' => null,
                'paid_at' => null,
                'paid_by' => null,
            ]);

            $this->audit->log(
                action: 'payroll.cancelled',
                summary: 'Payroll run '.$run->reference.' cancelled.',
                category: AuditCategory::Finance,
                severity: AuditSeverity::Warning,
                subject: $run,
                context: [
                    'previous_status' => $previousStatus->value,
                ],
            );

            return $run->fresh();
        });
    }

    private function nextReference(int $year, int $month): string
    {
        $prefix = sprintf('PAY-%d-%02d-', $year, $month);
        $latest = PayrollRun::query()
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('reference');

        $sequence = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    public static function isPeriodClosed(int $year, int $month): bool
    {
        return Carbon::create($year, $month, 1)->endOfMonth()->toDateString() < now()->toDateString();
    }

    public static function lastClosedPeriod(): Carbon
    {
        return now()->startOfMonth()->subDay();
    }
}

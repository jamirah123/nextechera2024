<?php

namespace App\Services\Finance\Ledger;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\BankStatementLineStatus;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuditService;
use InvalidArgumentException;

class BankReconciliationService
{
    public function __construct(private AuditService $audit)
    {
    }

    /**
     * @param  array{
     *     transaction_date: string,
     *     description: string,
     *     amount: float|int|string,
     *     external_reference?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function addStatementLine(BankAccount $account, array $data): BankStatementLine
    {
        $amount = round((float) $data['amount'], 2);
        if ($amount == 0.0) {
            throw new InvalidArgumentException('Statement line amount cannot be zero.');
        }

        return BankStatementLine::query()->create([
            'bank_account_id' => $account->id,
            'transaction_date' => $data['transaction_date'],
            'description' => $data['description'],
            'external_reference' => $data['external_reference'] ?? null,
            'amount' => $amount,
            'status' => BankStatementLineStatus::Unmatched,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function matchToPayment(BankStatementLine $line, Payment $payment, ?User $actor = null): BankStatementLine
    {
        if ($line->status !== BankStatementLineStatus::Unmatched) {
            throw new InvalidArgumentException('Only unmatched statement lines can be matched.');
        }

        if (abs(abs((float) $line->amount) - abs((float) $payment->amount)) > 0.009) {
            throw new InvalidArgumentException('Payment amount does not match the statement line.');
        }

        if (BankStatementLine::query()
            ->where('matched_payment_id', $payment->id)
            ->where('status', BankStatementLineStatus::Matched->value)
            ->exists()) {
            throw new InvalidArgumentException('This payment is already matched to another statement line.');
        }

        $line->update([
            'status' => BankStatementLineStatus::Matched,
            'matched_payment_id' => $payment->id,
            'matched_journal_id' => null,
            'matched_at' => now(),
            'matched_by' => $actor?->id,
        ]);

        $this->audit->log(
            action: 'ledger.bank_line_matched',
            summary: 'Bank line matched to payment '.$payment->reference.'.',
            category: AuditCategory::Finance,
            severity: AuditSeverity::Notice,
            subject: $line,
            actor: $actor,
        );

        return $line->fresh(['matchedPayment', 'bankAccount']);
    }

    public function unmatch(BankStatementLine $line, ?User $actor = null): BankStatementLine
    {
        if ($line->status === BankStatementLineStatus::Unmatched) {
            return $line;
        }

        $line->update([
            'status' => BankStatementLineStatus::Unmatched,
            'matched_payment_id' => null,
            'matched_journal_id' => null,
            'matched_at' => null,
            'matched_by' => null,
        ]);

        $this->audit->log(
            action: 'ledger.bank_line_unmatched',
            summary: 'Bank statement line unmarked.',
            category: AuditCategory::Finance,
            severity: AuditSeverity::Notice,
            subject: $line,
            actor: $actor,
        );

        return $line->fresh();
    }

    public function exclude(BankStatementLine $line, ?User $actor = null): BankStatementLine
    {
        $line->update([
            'status' => BankStatementLineStatus::Excluded,
            'matched_payment_id' => null,
            'matched_journal_id' => null,
            'matched_at' => now(),
            'matched_by' => $actor?->id,
        ]);

        return $line->fresh();
    }

    public function autoMatch(BankAccount $account, ?User $actor = null): int
    {
        $matched = 0;

        $lines = $account->statementLines()->unmatched()->get();

        foreach ($lines as $line) {
            $paymentQuery = Payment::query()
                ->whereRaw('ABS(amount - ABS(?)) < 0.01', [(float) $line->amount])
                ->whereDate('payment_date', $line->transaction_date)
                ->whereDoesntHave('matchedStatementLines');

            if (filled($line->external_reference)) {
                $ref = $line->external_reference;
                $paymentQuery->where(function ($q) use ($ref): void {
                    $q->where('reference', $ref)
                        ->orWhere('external_reference', $ref);
                });
            }

            $payment = $paymentQuery->first();
            if (! $payment) {
                continue;
            }

            try {
                $this->matchToPayment($line, $payment, $actor);
                $matched++;
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        return $matched;
    }

    /**
     * @return array{book_balance: float, statement_balance: float, unmatched_count: int, difference: float}
     */
    public function summary(BankAccount $account): array
    {
        $statementBalance = (float) $account->opening_balance
            + (float) $account->statementLines()->sum('amount');

        $matchedIn = (float) $account->statementLines()
            ->where('status', BankStatementLineStatus::Matched->value)
            ->sum('amount');

        $unmatched = $account->statementLines()->unmatched()->count();

        return [
            'book_balance' => round((float) $account->opening_balance + $matchedIn, 2),
            'statement_balance' => round($statementBalance, 2),
            'unmatched_count' => $unmatched,
            'difference' => round($statementBalance - ((float) $account->opening_balance + $matchedIn), 2),
        ];
    }
}

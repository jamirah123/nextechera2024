<?php

namespace App\Services\Finance\Ledger;

use App\Enums\GlAccountType;
use App\Enums\GlJournalStatus;
use App\Models\GlAccount;
use App\Models\GlJournalLine;
use App\Models\GlPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LedgerReportService
{
    /**
     * @return array{
     *     period: GlPeriod,
     *     rows: Collection<int, array{account: GlAccount, debit: float, credit: float, balance: float}>,
     *     debit_total: float,
     *     credit_total: float
     * }
     */
    public function trialBalance(GlPeriod $period): array
    {
        $accounts = GlAccount::query()->active()->orderBy('code')->get();
        $totals = $this->postedTotalsUpTo($period);

        $rows = $accounts->map(function (GlAccount $account) use ($totals) {
            $debit = round((float) ($totals[$account->id]['debit'] ?? 0), 2);
            $credit = round((float) ($totals[$account->id]['credit'] ?? 0), 2);
            $net = round($debit - $credit, 2);

            return [
                'account' => $account,
                'debit' => $net > 0 ? $net : 0.0,
                'credit' => $net < 0 ? abs($net) : 0.0,
                'balance' => $net,
                'movement_debit' => $debit,
                'movement_credit' => $credit,
            ];
        })->filter(fn (array $row) => abs($row['balance']) > 0.009 || $row['movement_debit'] > 0 || $row['movement_credit'] > 0)
            ->values();

        return [
            'period' => $period,
            'rows' => $rows,
            'debit_total' => round((float) $rows->sum('debit'), 2),
            'credit_total' => round((float) $rows->sum('credit'), 2),
        ];
    }

    /**
     * @return array{
     *     period: GlPeriod,
     *     revenue: Collection<int, array{account: GlAccount, amount: float}>,
     *     expenses: Collection<int, array{account: GlAccount, amount: float}>,
     *     revenue_total: float,
     *     expense_total: float,
     *     net: float
     * }
     */
    public function profitAndLoss(GlPeriod $period): array
    {
        $movements = $this->postedTotalsInPeriod($period);

        $mapType = function (GlAccountType $type, bool $creditNature) use ($movements): Collection {
            return GlAccount::query()
                ->active()
                ->where('type', $type->value)
                ->orderBy('code')
                ->get()
                ->map(function (GlAccount $account) use ($movements, $creditNature) {
                    $debit = (float) ($movements[$account->id]['debit'] ?? 0);
                    $credit = (float) ($movements[$account->id]['credit'] ?? 0);
                    $amount = $creditNature ? round($credit - $debit, 2) : round($debit - $credit, 2);

                    return [
                        'account' => $account,
                        'amount' => $amount,
                    ];
                })
                ->filter(fn (array $row) => abs($row['amount']) > 0.009)
                ->values();
        };

        $revenue = $mapType(GlAccountType::Revenue, true);
        $expenses = $mapType(GlAccountType::Expense, false);
        $revenueTotal = round((float) $revenue->sum('amount'), 2);
        $expenseTotal = round((float) $expenses->sum('amount'), 2);

        return [
            'period' => $period,
            'revenue' => $revenue,
            'expenses' => $expenses,
            'revenue_total' => $revenueTotal,
            'expense_total' => $expenseTotal,
            'net' => round($revenueTotal - $expenseTotal, 2),
        ];
    }

    /** @return array<int, array{debit: float, credit: float}> */
    private function postedTotalsUpTo(GlPeriod $period): array
    {
        return $this->aggregate(function ($q) use ($period): void {
            $q->whereDate('journal_date', '<=', $period->ends_on);
        });
    }

    /** @return array<int, array{debit: float, credit: float}> */
    private function postedTotalsInPeriod(GlPeriod $period): array
    {
        return $this->aggregate(function ($q) use ($period): void {
            $q->whereDate('journal_date', '>=', $period->starts_on)
                ->whereDate('journal_date', '<=', $period->ends_on);
        });
    }

    /**
     * @param  callable(Builder): void  $constrainJournal
     * @return array<int, array{debit: float, credit: float}>
     */
    private function aggregate(callable $constrainJournal): array
    {
        $rows = GlJournalLine::query()
            ->selectRaw('account_id, SUM(debit) as debit_total, SUM(credit) as credit_total')
            ->whereHas('journal', function ($q) use ($constrainJournal): void {
                $q->where('status', GlJournalStatus::Posted->value);
                $constrainJournal($q);
            })
            ->groupBy('account_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->account_id] = [
                'debit' => (float) $row->debit_total,
                'credit' => (float) $row->credit_total,
            ];
        }

        return $out;
    }
}

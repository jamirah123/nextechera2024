<?php

namespace App\Services\Finance\Ledger;

use App\Models\GlAccount;
use InvalidArgumentException;

class ChartOfAccountsService
{
    public function accountByRole(string $role): GlAccount
    {
        $account = GlAccount::query()
            ->postable()
            ->where('system_role', $role)
            ->first();

        if (! $account) {
            throw new InvalidArgumentException('System GL account "'.$role.'" is not configured.');
        }

        return $account;
    }

    public function cashBank(): GlAccount
    {
        return $this->accountByRole('cash_bank');
    }

    public function accountsReceivable(): GlAccount
    {
        return $this->accountByRole('accounts_receivable');
    }

    public function vatOutput(): GlAccount
    {
        return $this->accountByRole('vat_output');
    }

    public function vatInput(): GlAccount
    {
        return $this->accountByRole('vat_input');
    }

    public function accountsPayable(): GlAccount
    {
        return $this->accountByRole('accounts_payable');
    }

    public function operatingExpense(): GlAccount
    {
        return $this->accountByRole('operating_expense');
    }

    public function payrollPayable(): GlAccount
    {
        return $this->accountByRole('payroll_payable');
    }

    public function payrollDeductions(): GlAccount
    {
        return $this->accountByRole('payroll_deductions');
    }

    public function revenueServices(): GlAccount
    {
        return $this->accountByRole('revenue_services');
    }

    public function payrollExpense(): GlAccount
    {
        return $this->accountByRole('payroll_expense');
    }
}

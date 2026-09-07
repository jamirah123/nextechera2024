<?php

namespace App\Services\Finance\Ledger;

use App\Enums\GlAccountType;
use App\Models\BankAccount;
use App\Models\GlAccount;
use App\Support\Money;
use InvalidArgumentException;

class BankAccountService
{
    public function create(array $data): BankAccount
    {
        $glAccountId = filled($data['gl_account_id'] ?? null)
            ? (int) $data['gl_account_id']
            : $this->createCashAccount($data['name'])->id;

        $this->assertCashAccount($glAccountId);

        return BankAccount::query()->create([
            'gl_account_id' => $glAccountId,
            'name' => $data['name'],
            'bank_name' => $data['bank_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'currency' => $data['currency'] ?? Money::currency(),
            'is_active' => true,
            'opening_balance' => round((float) ($data['opening_balance'] ?? 0), 2),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function update(BankAccount $account, array $data): BankAccount
    {
        if (filled($data['gl_account_id'] ?? null)) {
            $this->assertCashAccount((int) $data['gl_account_id']);
        }

        $account->update([
            'gl_account_id' => $data['gl_account_id'] ?? $account->gl_account_id,
            'name' => $data['name'] ?? $account->name,
            'bank_name' => $data['bank_name'] ?? $account->bank_name,
            'account_number' => $data['account_number'] ?? $account->account_number,
            'opening_balance' => array_key_exists('opening_balance', $data)
                ? round((float) $data['opening_balance'], 2)
                : $account->opening_balance,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $account->is_active,
            'notes' => $data['notes'] ?? $account->notes,
        ]);

        return $account->fresh('glAccount');
    }

    private function createCashAccount(string $bankName): GlAccount
    {
        $base = 1100;
        $used = GlAccount::query()
            ->where('code', 'like', '11%')
            ->pluck('code')
            ->map(fn ($code) => (int) $code)
            ->all();

        $code = $base;
        while (in_array($code, $used, true)) {
            $code++;
        }

        return GlAccount::query()->create([
            'code' => (string) $code,
            'name' => 'Bank · '.$bankName,
            'type' => GlAccountType::Asset,
            'system_role' => null,
            'is_postable' => true,
            'is_active' => true,
            'description' => 'Cash at bank for '.$bankName,
        ]);
    }

    private function assertCashAccount(int $accountId): void
    {
        $account = GlAccount::query()->postable()->find($accountId);
        if (! $account || $account->type !== GlAccountType::Asset) {
            throw new InvalidArgumentException('Bank accounts must post to an active asset GL account.');
        }
    }
}

<?php

namespace App\Services\Finance;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Models\BillingProfile;
use App\Services\AuditService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function __construct(private AuditService $audit)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): BillingProfile
    {
        return DB::transaction(function () use ($data) {
            $profile = BillingProfile::query()->create($this->attributesFrom($data));

            $this->audit->log(
                action: 'finance.billing_profile_created',
                summary: 'Billing profile created for client #'.$profile->client_id.'.',
                category: AuditCategory::Finance,
                severity: AuditSeverity::Notice,
                subject: $profile,
            );

            return $profile->fresh(['client', 'site']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BillingProfile $profile, array $data): BillingProfile
    {
        return DB::transaction(function () use ($profile, $data) {
            $profile->update($this->attributesFrom($data, $profile));

            $this->audit->log(
                action: 'finance.billing_profile_updated',
                summary: 'Billing profile #'.$profile->id.' updated.',
                category: AuditCategory::Finance,
                severity: AuditSeverity::Notice,
                subject: $profile,
            );

            return $profile->fresh(['client', 'site']);
        });
    }

    /** @return array<string, mixed> */
    private function attributesFrom(array $data, ?BillingProfile $profile = null): array
    {
        $armedShift = (float) ($data['rate_per_armed_shift'] ?? $profile?->rate_per_armed_shift ?? 0);
        $unarmedShift = (float) ($data['rate_per_unarmed_shift'] ?? $profile?->rate_per_unarmed_shift ?? 0);
        $armedCostShift = (float) ($data['cost_per_armed_shift'] ?? $profile?->cost_per_armed_shift ?? 0);
        $unarmedCostShift = (float) ($data['cost_per_unarmed_shift'] ?? $profile?->cost_per_unarmed_shift ?? 0);

        return [
            'client_id' => $data['client_id'] ?? $profile?->client_id,
            'site_id' => array_key_exists('site_id', $data) ? $data['site_id'] : $profile?->site_id,
            'currency' => $data['currency'] ?? $profile?->currency ?? Money::currency(),
            'contracted_armed_guards' => (int) ($data['contracted_armed_guards'] ?? $profile?->contracted_armed_guards ?? 0),
            'contracted_unarmed_guards' => (int) ($data['contracted_unarmed_guards'] ?? $profile?->contracted_unarmed_guards ?? 0),
            'monthly_rate_per_armed_guard' => (float) ($data['monthly_rate_per_armed_guard'] ?? $profile?->monthly_rate_per_armed_guard ?? 0),
            'monthly_rate_per_unarmed_guard' => (float) ($data['monthly_rate_per_unarmed_guard'] ?? $profile?->monthly_rate_per_unarmed_guard ?? 0),
            'monthly_cost_per_armed_guard' => (float) ($data['monthly_cost_per_armed_guard'] ?? $profile?->monthly_cost_per_armed_guard ?? 0),
            'monthly_cost_per_unarmed_guard' => (float) ($data['monthly_cost_per_unarmed_guard'] ?? $profile?->monthly_cost_per_unarmed_guard ?? 0),
            'monthly_site_fee' => $data['monthly_site_fee'] ?? $profile?->monthly_site_fee ?? 0,
            'rate_per_armed_shift' => $armedShift,
            'rate_per_unarmed_shift' => $unarmedShift,
            'rate_per_guard_shift' => max($armedShift, $unarmedShift),
            'cost_per_armed_shift' => $armedCostShift,
            'cost_per_unarmed_shift' => $unarmedCostShift,
            'cost_per_guard_shift' => max($armedCostShift, $unarmedCostShift),
            'effective_from' => $data['effective_from'] ?? $profile?->effective_from,
            'effective_to' => array_key_exists('effective_to', $data) ? $data['effective_to'] : $profile?->effective_to,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : ($profile?->is_active ?? true),
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $profile?->notes,
        ];
    }
}

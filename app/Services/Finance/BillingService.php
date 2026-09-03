<?php

namespace App\Services\Finance;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\BillingMode;
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
        $armedDay = (float) ($data['rate_per_armed_day_shift'] ?? $data['rate_per_armed_shift'] ?? $profile?->rate_per_armed_day_shift ?? 0);
        $unarmedDay = (float) ($data['rate_per_unarmed_day_shift'] ?? $data['rate_per_unarmed_shift'] ?? $profile?->rate_per_unarmed_day_shift ?? 0);
        $armedNight = (float) ($data['rate_per_armed_night_shift'] ?? $data['rate_per_armed_shift'] ?? $profile?->rate_per_armed_night_shift ?? $armedDay);
        $unarmedNight = (float) ($data['rate_per_unarmed_night_shift'] ?? $data['rate_per_unarmed_shift'] ?? $profile?->rate_per_unarmed_night_shift ?? $unarmedDay);

        // Keep legacy flat shift columns in sync (used by profitability estimates).
        $armedShift = max($armedDay, $armedNight, (float) ($data['rate_per_armed_shift'] ?? $profile?->rate_per_armed_shift ?? 0));
        $unarmedShift = max($unarmedDay, $unarmedNight, (float) ($data['rate_per_unarmed_shift'] ?? $profile?->rate_per_unarmed_shift ?? 0));
        $armedCostShift = (float) ($data['cost_per_armed_shift'] ?? $profile?->cost_per_armed_shift ?? 0);
        $unarmedCostShift = (float) ($data['cost_per_unarmed_shift'] ?? $profile?->cost_per_unarmed_shift ?? 0);

        $dayArmedCount = (int) ($data['contracted_day_armed_guards'] ?? $profile?->contracted_day_armed_guards ?? 0);
        $dayUnarmedCount = (int) ($data['contracted_day_unarmed_guards'] ?? $profile?->contracted_day_unarmed_guards ?? 0);
        $nightArmedCount = (int) ($data['contracted_night_armed_guards'] ?? $profile?->contracted_night_armed_guards ?? 0);
        $nightUnarmedCount = (int) ($data['contracted_night_unarmed_guards'] ?? $profile?->contracted_night_unarmed_guards ?? 0);

        $rateArmed = (float) ($data['monthly_rate_per_armed_guard']
            ?? $profile?->monthly_rate_per_armed_guard
            ?? max(
                (float) ($profile?->monthly_rate_per_armed_day_guard ?? 0),
                (float) ($profile?->monthly_rate_per_armed_night_guard ?? 0),
            ));
        $rateUnarmed = (float) ($data['monthly_rate_per_unarmed_guard']
            ?? $profile?->monthly_rate_per_unarmed_guard
            ?? max(
                (float) ($profile?->monthly_rate_per_unarmed_day_guard ?? 0),
                (float) ($profile?->monthly_rate_per_unarmed_night_guard ?? 0),
            ));

        return [
            'client_id' => $data['client_id'] ?? $profile?->client_id,
            'site_id' => array_key_exists('site_id', $data) ? $data['site_id'] : $profile?->site_id,
            'currency' => $data['currency'] ?? $profile?->currency ?? Money::currency(),
            'billing_mode' => $data['billing_mode'] ?? $profile?->billing_mode?->value ?? BillingMode::Monthly->value,
            'cash_no_tax' => array_key_exists('cash_no_tax', $data)
                ? (bool) $data['cash_no_tax']
                : (bool) ($profile?->cash_no_tax ?? false),
            'contracted_day_armed_guards' => $dayArmedCount,
            'contracted_day_unarmed_guards' => $dayUnarmedCount,
            'contracted_night_armed_guards' => $nightArmedCount,
            'contracted_night_unarmed_guards' => $nightUnarmedCount,
            'contracted_armed_guards' => $dayArmedCount + $nightArmedCount,
            'contracted_unarmed_guards' => $dayUnarmedCount + $nightUnarmedCount,
            'monthly_rate_per_armed_guard' => $rateArmed,
            'monthly_rate_per_unarmed_guard' => $rateUnarmed,
            // Keep day/night columns mirrored for older invoice/report paths.
            'monthly_rate_per_armed_day_guard' => $rateArmed,
            'monthly_rate_per_unarmed_day_guard' => $rateUnarmed,
            'monthly_rate_per_armed_night_guard' => $rateArmed,
            'monthly_rate_per_unarmed_night_guard' => $rateUnarmed,
            'monthly_cost_per_armed_guard' => (float) ($data['monthly_cost_per_armed_guard'] ?? $profile?->monthly_cost_per_armed_guard ?? 0),
            'monthly_cost_per_unarmed_guard' => (float) ($data['monthly_cost_per_unarmed_guard'] ?? $profile?->monthly_cost_per_unarmed_guard ?? 0),
            'monthly_site_fee' => 0.0,
            'rate_per_armed_day_shift' => $armedDay,
            'rate_per_unarmed_day_shift' => $unarmedDay,
            'rate_per_armed_night_shift' => $armedNight,
            'rate_per_unarmed_night_shift' => $unarmedNight,
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

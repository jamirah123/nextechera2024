<?php

namespace App\Services;

use App\Enums\AssetCategory;
use App\Enums\AssetIssuanceStatus;
use App\Enums\AssetLineStatus;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Models\Guard;
use App\Models\GuardAssetIssuance;
use App\Models\GuardAssetLine;
use App\Models\GuardAssetRecovery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GuardAssetService
{
    public function __construct(private AuditService $audit) {}

    /**
     * @param  array{
     *     guard_id: int,
     *     issuance_type: string,
     *     issued_at: string,
     *     notes?: string|null,
     *     lines: list<array{
     *         asset_category: string,
     *         description?: string|null,
     *         size?: string|null,
     *         serial_number?: string|null,
     *         quantity?: int,
     *         unit_value?: float|int|string|null,
     *         recover_cost?: bool,
     *         recovery_amount?: float|int|string|null,
     *         monthly_recovery?: float|int|string|null,
     *         notes?: string|null
     *     }>
     * }  $data
     */
    public function issue(array $data, ?User $actor = null): GuardAssetIssuance
    {
        $actor ??= auth()->user();
        $lines = $data['lines'] ?? [];

        if ($lines === []) {
            throw new InvalidArgumentException('Add at least one asset line to issue.');
        }

        $guard = Guard::query()->findOrFail($data['guard_id']);

        return DB::transaction(function () use ($data, $lines, $guard, $actor): GuardAssetIssuance {
            $issuance = GuardAssetIssuance::query()->create([
                'reference' => $this->nextReference(),
                'guard_id' => $guard->id,
                'region_id' => $guard->region_id,
                'issuance_type' => $data['issuance_type'],
                'status' => AssetIssuanceStatus::Active,
                'issued_at' => $data['issued_at'],
                'notes' => $data['notes'] ?? null,
                'issued_by' => $actor?->id,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            foreach ($lines as $lineData) {
                $this->createLine($issuance, $guard, $lineData, $actor);
            }

            $issuance->load(['assignedGuard:id,employment_id,full_name', 'lines', 'issuer:id,name']);

            $this->audit->log(
                action: 'asset.issued',
                summary: 'Assets issued to '.$guard->full_name.' ('.$issuance->reference.')',
                category: AuditCategory::Hr,
                severity: AuditSeverity::Info,
                subject: $issuance,
                context: [
                    'guard_id' => $guard->id,
                    'line_count' => $issuance->lines->count(),
                ],
            );

            return $issuance;
        });
    }

    /**
     * @return list<string>
     */
    public function modificationBlockers(GuardAssetIssuance $issuance): array
    {
        $issuance->loadMissing(['lines.recovery.payrollDeductions']);

        $blockers = [];

        foreach ($issuance->lines as $line) {
            if ((int) $line->quantity_returned > 0) {
                $blockers[] = 'Returns have already been recorded on this issuance.';

                break;
            }

            if ((float) $line->recovered_amount > 0) {
                $blockers[] = 'Payroll recovery has already been applied to one or more items.';

                break;
            }

            $recovery = $line->recovery;

            if ($recovery !== null && $recovery->payrollDeductions->isNotEmpty()) {
                $blockers[] = 'Payroll deductions exist for this issuance and must be reversed before editing or deleting.';

                break;
            }
        }

        return array_values(array_unique($blockers));
    }

    public function canModify(GuardAssetIssuance $issuance): bool
    {
        return $this->modificationBlockers($issuance) === [];
    }

    /**
     * @param  array{
     *     guard_id: int,
     *     issuance_type: string,
     *     issued_at: string,
     *     notes?: string|null,
     *     lines: list<array{
     *         id?: int|null,
     *         asset_category: string,
     *         description?: string|null,
     *         size?: string|null,
     *         serial_number?: string|null,
     *         quantity?: int,
     *         unit_value?: float|int|string|null,
     *         recover_cost?: bool,
     *         recovery_amount?: float|int|string|null,
     *         monthly_recovery?: float|int|string|null,
     *         notes?: string|null
     *     }>
     * }  $data
     */
    public function updateIssuance(GuardAssetIssuance $issuance, array $data, ?User $actor = null): GuardAssetIssuance
    {
        $blockers = $this->modificationBlockers($issuance);

        if ($blockers !== []) {
            throw new InvalidArgumentException($blockers[0]);
        }

        $actor ??= auth()->user();
        $lines = $data['lines'] ?? [];

        if ($lines === []) {
            throw new InvalidArgumentException('Add at least one asset line.');
        }

        $guard = Guard::query()->findOrFail($data['guard_id']);

        return DB::transaction(function () use ($issuance, $data, $lines, $guard, $actor): GuardAssetIssuance {
            $issuance->update([
                'guard_id' => $guard->id,
                'region_id' => $guard->region_id,
                'issuance_type' => $data['issuance_type'],
                'issued_at' => $data['issued_at'],
                'notes' => $data['notes'] ?? null,
                'updated_by' => $actor?->id,
            ]);

            $submittedLineIds = collect($lines)
                ->pluck('id')
                ->filter(fn ($id) => filled($id))
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $issuance->load('lines.recovery');

            foreach ($issuance->lines as $existingLine) {
                if (in_array($existingLine->id, $submittedLineIds, true)) {
                    continue;
                }

                $existingLine->recovery?->delete();
                $existingLine->delete();
            }

            foreach ($lines as $lineData) {
                if (filled($lineData['id'] ?? null)) {
                    $this->updateLine($issuance, $guard, $lineData, $actor);
                } else {
                    $this->createLine($issuance, $guard, $lineData, $actor);
                }
            }

            $issuance->refreshStatus();
            $issuance->load(['assignedGuard:id,employment_id,full_name', 'lines.recovery', 'issuer:id,name']);

            $this->audit->log(
                action: 'asset.updated',
                summary: 'Asset issuance updated for '.$guard->full_name.' ('.$issuance->reference.')',
                category: AuditCategory::Hr,
                severity: AuditSeverity::Info,
                subject: $issuance,
                context: [
                    'guard_id' => $guard->id,
                    'line_count' => $issuance->lines->count(),
                ],
            );

            return $issuance;
        });
    }

    public function deleteIssuance(GuardAssetIssuance $issuance, ?User $actor = null): void
    {
        $blockers = $this->modificationBlockers($issuance);

        if ($blockers !== []) {
            throw new InvalidArgumentException($blockers[0]);
        }

        $actor ??= auth()->user();

        DB::transaction(function () use ($issuance, $actor): void {
            $issuance->load(['assignedGuard:id,full_name,employment_id', 'lines.recovery']);

            $reference = $issuance->reference;
            $guardName = $issuance->assignedGuard?->full_name ?? 'guard';

            foreach ($issuance->lines as $line) {
                $line->recovery?->delete();
                $line->delete();
            }

            $issuance->delete();

            $this->audit->log(
                action: 'asset.deleted',
                summary: 'Asset issuance deleted for '.$guardName.' ('.$reference.')',
                category: AuditCategory::Hr,
                severity: AuditSeverity::Warning,
                subject: null,
                context: [
                    'reference' => $reference,
                    'guard_name' => $guardName,
                    'deleted_by' => $actor?->id,
                ],
            );
        });
    }

    /**
     * @param  array<string, mixed>  $lineData
     */
    private function updateLine(GuardAssetIssuance $issuance, Guard $guard, array $lineData, ?User $actor): GuardAssetLine
    {
        $line = $issuance->lines()->with('recovery')->findOrFail((int) $lineData['id']);

        $category = AssetCategory::from($lineData['asset_category']);
        $quantity = max(1, (int) ($lineData['quantity'] ?? 1));
        $unitValue = round(max(0, (float) ($lineData['unit_value'] ?? 0)), 2);
        $recoverCost = (bool) ($lineData['recover_cost'] ?? false);
        $recoveryAmount = $recoverCost
            ? round(max(0, (float) ($lineData['recovery_amount'] ?? ($unitValue * $quantity))), 2)
            : 0.0;

        if ($category->requiresSerial() && blank($lineData['serial_number'] ?? null)) {
            throw new InvalidArgumentException($category->label().' items require a serial number.');
        }

        $line->update([
            'asset_category' => $category->value,
            'description' => filled($lineData['description'] ?? null) ? trim((string) $lineData['description']) : null,
            'size' => filled($lineData['size'] ?? null) ? trim((string) $lineData['size']) : null,
            'serial_number' => filled($lineData['serial_number'] ?? null) ? trim((string) $lineData['serial_number']) : null,
            'quantity' => $quantity,
            'unit_value' => $unitValue,
            'recovery_amount' => $recoveryAmount,
            'monthly_recovery' => filled($lineData['monthly_recovery'] ?? null)
                ? round(max(0, (float) $lineData['monthly_recovery']), 2)
                : null,
            'notes' => filled($lineData['notes'] ?? null) ? trim((string) $lineData['notes']) : null,
        ]);

        $this->syncLineRecovery($line, $guard, $recoveryAmount, $actor);

        return $line->fresh(['recovery']);
    }

    private function syncLineRecovery(GuardAssetLine $line, Guard $guard, float $recoveryAmount, ?User $actor): void
    {
        $line->loadMissing('recovery');
        $recovery = $line->recovery;

        if ($recoveryAmount <= 0) {
            $recovery?->delete();

            return;
        }

        $payload = [
            'guard_id' => $guard->id,
            'label' => $line->displayLabel(),
            'original_amount' => $recoveryAmount,
            'balance_remaining' => $recoveryAmount,
            'monthly_installment' => $line->monthly_recovery,
            'is_active' => true,
        ];

        if ($recovery !== null) {
            $recovery->update($payload);

            return;
        }

        GuardAssetRecovery::query()->create([
            ...$payload,
            'guard_asset_line_id' => $line->id,
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $lineData
     */
    private function createLine(GuardAssetIssuance $issuance, Guard $guard, array $lineData, ?User $actor): GuardAssetLine
    {
        $category = AssetCategory::from($lineData['asset_category']);
        $quantity = max(1, (int) ($lineData['quantity'] ?? 1));
        $unitValue = round(max(0, (float) ($lineData['unit_value'] ?? 0)), 2);
        $recoverCost = (bool) ($lineData['recover_cost'] ?? false);
        $recoveryAmount = $recoverCost
            ? round(max(0, (float) ($lineData['recovery_amount'] ?? ($unitValue * $quantity))), 2)
            : 0.0;

        if ($category->requiresSerial() && blank($lineData['serial_number'] ?? null)) {
            throw new InvalidArgumentException($category->label().' items require a serial number.');
        }

        $line = GuardAssetLine::query()->create([
            'guard_asset_issuance_id' => $issuance->id,
            'asset_category' => $category->value,
            'description' => filled($lineData['description'] ?? null) ? trim((string) $lineData['description']) : null,
            'size' => filled($lineData['size'] ?? null) ? trim((string) $lineData['size']) : null,
            'serial_number' => filled($lineData['serial_number'] ?? null) ? trim((string) $lineData['serial_number']) : null,
            'quantity' => $quantity,
            'unit_value' => $unitValue,
            'recovery_amount' => $recoveryAmount,
            'monthly_recovery' => filled($lineData['monthly_recovery'] ?? null)
                ? round(max(0, (float) $lineData['monthly_recovery']), 2)
                : null,
            'status' => AssetLineStatus::Issued,
            'notes' => filled($lineData['notes'] ?? null) ? trim((string) $lineData['notes']) : null,
        ]);

        $this->syncLineRecovery($line, $guard, $recoveryAmount, $actor);

        return $line;
    }

    /**
     * @param  array<int, array{return_qty?: int, disposition?: string, notes?: string|null}>  $lineReturns
     */
    public function recordReturns(GuardAssetIssuance $issuance, array $lineReturns, ?User $actor = null, bool $terminationReturn = false): GuardAssetIssuance
    {
        $actor ??= auth()->user();

        return DB::transaction(function () use ($issuance, $lineReturns, $terminationReturn): GuardAssetIssuance {
            $issuance->load('lines', 'assignedGuard:id,full_name,employment_id');

            foreach ($lineReturns as $lineId => $payload) {
                $line = $issuance->lines->firstWhere('id', (int) $lineId);

                if ($line === null) {
                    continue;
                }

                $returnQty = max(0, (int) ($payload['return_qty'] ?? 0));
                $disposition = $payload['disposition'] ?? 'returned';

                if ($returnQty <= 0 && $disposition === 'returned') {
                    continue;
                }

                if ($terminationReturn && $returnQty === 0 && $line->quantityOutstanding() > 0) {
                    $returnQty = $line->quantityOutstanding();
                }

                $newReturned = min((int) $line->quantity, (int) $line->quantity_returned + $returnQty);

                $status = match ($disposition) {
                    'lost' => AssetLineStatus::Lost,
                    'written_off' => AssetLineStatus::WrittenOff,
                    default => $newReturned >= (int) $line->quantity
                        ? AssetLineStatus::Returned
                        : ($newReturned > 0 ? AssetLineStatus::PartiallyReturned : $line->status),
                };

                $line->update([
                    'quantity_returned' => $newReturned,
                    'status' => $status,
                    'returned_at' => in_array($status, [AssetLineStatus::Returned, AssetLineStatus::PartiallyReturned, AssetLineStatus::WrittenOff, AssetLineStatus::Lost], true)
                        ? now()
                        : $line->returned_at,
                    'notes' => filled($payload['notes'] ?? null)
                        ? trim((string) $payload['notes'])
                        : $line->notes,
                ]);
            }

            $issuance->refreshStatus();

            $this->audit->log(
                action: $terminationReturn ? 'asset.termination_return' : 'asset.returned',
                summary: ($terminationReturn ? 'Termination return for ' : 'Asset return for ')
                    .$issuance->assignedGuard?->full_name.' ('.$issuance->reference.')',
                category: AuditCategory::Hr,
                severity: AuditSeverity::Info,
                subject: $issuance->fresh(['lines']),
            );

            return $issuance->fresh(['lines', 'assignedGuard', 'issuer', 'region']);
        });
    }

    public function outstandingCountForGuard(Guard $guard): int
    {
        return GuardAssetLine::query()
            ->whereHas('issuance', fn ($q) => $q->where('guard_id', $guard->id))
            ->whereNotIn('status', [AssetLineStatus::Returned, AssetLineStatus::WrittenOff, AssetLineStatus::Lost])
            ->whereColumn('quantity_returned', '<', 'quantity')
            ->count();
    }

    public function outstandingRecoveryBalance(Guard $guard): float
    {
        return (float) GuardAssetRecovery::query()
            ->where('guard_id', $guard->id)
            ->where('is_active', true)
            ->where('balance_remaining', '>', 0)
            ->sum('balance_remaining');
    }

    private function nextReference(): string
    {
        $prefix = 'AST-'.now()->format('Ymd');
        $latest = GuardAssetIssuance::query()
            ->where('reference', 'like', $prefix.'-%')
            ->orderByDesc('reference')
            ->value('reference');

        $sequence = 1;

        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%04d', $prefix, $sequence);
    }
}

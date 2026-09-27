<?php

namespace App\Services;

use App\Models\Site;
use App\Models\SiteManpowerRequirement;
use App\Models\Supervisor;
use App\Models\SupervisorAssignmentHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    /**
     * Seeded sites often only set required_day_guards / required_night_guards.
     * Billing and the client list read the armed/unarmed day/night columns.
     */
    public function ensureArmedUnarmedBreakdown(Site $site): Site
    {
        $day = (int) $site->required_day_guards;
        $night = (int) $site->required_night_guards;
        $breakdown = (int) $site->required_day_armed_guards
            + (int) $site->required_day_unarmed_guards
            + (int) $site->required_night_armed_guards
            + (int) $site->required_night_unarmed_guards;

        if ($breakdown > 0 || ($day + $night) <= 0) {
            return $site;
        }

        $dayArmed = (int) floor($day * 0.3);
        $nightArmed = (int) floor($night * 0.3);

        $site->update([
            'required_day_armed_guards' => $dayArmed,
            'required_day_unarmed_guards' => $day - $dayArmed,
            'required_night_armed_guards' => $nightArmed,
            'required_night_unarmed_guards' => $night - $nightArmed,
            'required_guards' => $day + $night,
        ]);

        $this->syncSiteManpower($site->fresh(), 'Armed/unarmed split from day/night totals');

        return $site->fresh();
    }

    public function syncSiteManpower(Site $site, ?string $notes = null): SiteManpowerRequirement
    {
        return DB::transaction(function () use ($site, $notes) {
            SiteManpowerRequirement::query()
                ->where('site_id', $site->id)
                ->where('is_current', true)
                ->update([
                    'is_current' => false,
                    'effective_to' => now()->toDateString(),
                ]);

            return SiteManpowerRequirement::query()->create([
                'site_id' => $site->id,
                'required_total' => (int) $site->required_guards,
                'required_day' => (int) $site->required_day_guards,
                'required_day_armed' => (int) $site->required_day_armed_guards,
                'required_day_unarmed' => (int) $site->required_day_unarmed_guards,
                'required_night' => (int) $site->required_night_guards,
                'required_night_armed' => (int) $site->required_night_armed_guards,
                'required_night_unarmed' => (int) $site->required_night_unarmed_guards,
                'effective_from' => now()->toDateString(),
                'is_current' => true,
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);
        });
    }

    public function recordSupervisorAssignment(
        Supervisor $supervisor,
        ?int $previousRegionId,
        int $newRegionId,
        string $changeType,
        ?string $reason = null,
        ?string $notes = null,
        ?array $meta = null,
        ?\Carbon\CarbonInterface $startsOn = null,
    ): SupervisorAssignmentHistory {
        $startsOn = ($startsOn ?? now())->copy()->startOfDay();

        $open = SupervisorAssignmentHistory::query()
            ->where('supervisor_id', $supervisor->id)
            ->where(function ($query): void {
                $query->whereNull('ends_on')->orWhereNull('status');
            })
            ->where(function ($query): void {
                $query->whereNull('status')->orWhere('status', 'current');
            })
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();

        if ($open !== null && (int) $open->new_region_id !== $newRegionId) {
            $end = $startsOn->copy()->subDay();
            if ($open->starts_on === null || $end->greaterThanOrEqualTo($open->starts_on->copy()->startOfDay())) {
                $open->update([
                    'ends_on' => $end->toDateString(),
                    'status' => 'ended',
                ]);
            }
        }

        return SupervisorAssignmentHistory::query()->create([
            'supervisor_id' => $supervisor->id,
            'previous_region_id' => $previousRegionId,
            'new_region_id' => $newRegionId,
            'change_type' => $changeType,
            'reason' => $reason,
            'notes' => $notes,
            'remarks' => $notes,
            'meta' => $meta,
            'changed_by' => auth()->id(),
            'effective_at' => $startsOn,
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => null,
            'status' => 'current',
        ]);
    }

    public function transferSupervisor(
        Supervisor $supervisor,
        int $regionId,
        \Carbon\CarbonInterface $startsOn,
        ?string $remarks = null,
        ?User $actor = null,
    ): Supervisor {
        if ((int) $supervisor->region_id === $regionId) {
            throw new \InvalidArgumentException('Choose a different region.');
        }

        return DB::transaction(function () use ($supervisor, $regionId, $startsOn, $remarks) {
            $previous = (int) $supervisor->region_id;
            $supervisor->update([
                'region_id' => $regionId,
                'assignment_date' => $startsOn->toDateString(),
            ]);

            $this->recordSupervisorAssignment(
                $supervisor,
                $previous,
                $regionId,
                'region_transfer',
                $remarks,
                $remarks,
                null,
                $startsOn,
            );

            $supervisor->load('guardProfile');
            if ($supervisor->guardProfile) {
                app(GuardService::class)->updateGuard($supervisor->guardProfile, [
                    'region_id' => $regionId,
                ], 'supervisor_region_transfer');
            }

            return $supervisor->fresh(['region', 'assignmentHistories']);
        });
    }

    public function nextSupervisorCode(): string
    {
        $latest = Supervisor::withTrashed()
            ->where('supervisor_code', 'like', 'SUP%')
            ->orderByDesc('id')
            ->value('supervisor_code');

        $number = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches)) {
            $number = ((int) $matches[1]) + 1;
        }

        return 'SUP'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}

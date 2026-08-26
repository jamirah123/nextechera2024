<?php

namespace App\Services;

use App\Models\Site;
use App\Models\SiteManpowerRequirement;
use App\Models\Supervisor;
use App\Models\SupervisorAssignmentHistory;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
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
                'required_night' => (int) $site->required_night_guards,
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
    ): SupervisorAssignmentHistory {
        return SupervisorAssignmentHistory::query()->create([
            'supervisor_id' => $supervisor->id,
            'previous_region_id' => $previousRegionId,
            'new_region_id' => $newRegionId,
            'change_type' => $changeType,
            'reason' => $reason,
            'notes' => $notes,
            'meta' => $meta,
            'changed_by' => auth()->id(),
            'effective_at' => now(),
        ]);
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

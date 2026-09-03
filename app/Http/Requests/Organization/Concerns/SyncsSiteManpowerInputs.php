<?php

namespace App\Http\Requests\Organization\Concerns;

trait SyncsSiteManpowerInputs
{
    protected function syncSiteManpowerInputs(): void
    {
        $hasBreakdown = $this->has('required_day_armed_guards')
            || $this->has('required_day_unarmed_guards')
            || $this->has('required_night_armed_guards')
            || $this->has('required_night_unarmed_guards');

        $hasLegacy = $this->has('required_day_guards') || $this->has('required_night_guards');

        if (! $hasBreakdown && ! $hasLegacy) {
            return;
        }

        if ($hasBreakdown) {
            $dayArmed = max(0, (int) $this->input('required_day_armed_guards', 0));
            $dayUnarmed = max(0, (int) $this->input('required_day_unarmed_guards', 0));
            $nightArmed = max(0, (int) $this->input('required_night_armed_guards', 0));
            $nightUnarmed = max(0, (int) $this->input('required_night_unarmed_guards', 0));
        } else {
            // Legacy day/night totals → treat as unarmed until reclassified.
            $dayArmed = 0;
            $dayUnarmed = max(0, (int) $this->input('required_day_guards', 0));
            $nightArmed = 0;
            $nightUnarmed = max(0, (int) $this->input('required_night_guards', 0));
        }

        $day = $dayArmed + $dayUnarmed;
        $night = $nightArmed + $nightUnarmed;

        $this->merge([
            'required_day_armed_guards' => $dayArmed,
            'required_day_unarmed_guards' => $dayUnarmed,
            'required_night_armed_guards' => $nightArmed,
            'required_night_unarmed_guards' => $nightUnarmed,
            'required_day_guards' => $day,
            'required_night_guards' => $night,
            'required_guards' => $day + $night,
            'number_of_posts' => max($day, $night),
        ]);
    }
}

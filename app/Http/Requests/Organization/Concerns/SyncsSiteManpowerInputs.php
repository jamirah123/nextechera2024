<?php

namespace App\Http\Requests\Organization\Concerns;

trait SyncsSiteManpowerInputs
{
    protected function syncSiteManpowerInputs(): void
    {
        if (! $this->has('required_day_guards') && ! $this->has('required_night_guards')) {
            return;
        }

        $day = max(0, (int) $this->input('required_day_guards', 0));
        $night = max(0, (int) $this->input('required_night_guards', 0));

        $this->merge([
            'required_day_guards' => $day,
            'required_night_guards' => $night,
            'required_guards' => $day + $night,
            'number_of_posts' => max($day, $night),
        ]);
    }
}

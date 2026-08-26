<?php

namespace Tests\Feature\Shifts;

use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\ReplacementReason;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Shift;
use App\Models\ShiftReplacement;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReplacementManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_manager_can_record_replacement(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        [$originalGuard, $site] = $this->deployedGuardAndSite();
        $replacementGuard = $this->deployedGuardOnSite($site);

        $shift = Shift::factory()->create([
            'guard_id' => $originalGuard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_date' => now()->toDateString(),
            'starts_at' => now()->setTime(6, 0),
            'ends_at' => now()->setTime(18, 0),
            'period' => ShiftPeriod::Day,
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Scheduled,
        ]);

        $this->actingAs($manager)
            ->post(route('replacements.store'), [
                'original_shift_id' => $shift->id,
                'replacement_guard_id' => $replacementGuard->id,
                'reason' => ReplacementReason::Sick->value,
                'notes' => 'Called in sick',
                'acknowledge_warnings' => true,
            ])
            ->assertRedirect();

        $this->assertSame(ShiftStatus::Replaced, $shift->fresh()->status);

        $record = ShiftReplacement::query()->where('original_shift_id', $shift->id)->first();
        $this->assertNotNull($record);
        $this->assertSame($replacementGuard->id, $record->replacement_guard_id);

        $replacementShift = Shift::query()->find($record->replacement_shift_id);
        $this->assertNotNull($replacementShift);
        $this->assertSame(ShiftType::Replacement, $replacementShift->shift_type);
        $this->assertSame($shift->id, $replacementShift->replaced_shift_id);
        $this->assertSame($replacementGuard->id, $replacementShift->guard_id);
    }

    public function test_cannot_replace_with_same_guard(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        [$guard, $site] = $this->deployedGuardAndSite();

        $shift = Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_date' => now()->toDateString(),
            'starts_at' => now()->setTime(6, 0),
            'ends_at' => now()->setTime(18, 0),
            'status' => ShiftStatus::Scheduled,
        ]);

        $this->actingAs($manager)
            ->post(route('replacements.store'), [
                'original_shift_id' => $shift->id,
                'replacement_guard_id' => $guard->id,
                'reason' => ReplacementReason::Unavailable->value,
                'acknowledge_warnings' => true,
            ])
            ->assertSessionHasErrors('replacement');
    }

    public function test_finance_cannot_create_replacements(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();

        $this->actingAs($finance)
            ->get(route('replacements.create'))
            ->assertForbidden();
    }

    /** @return array{0: Guard, 1: Site} */
    private function deployedGuardAndSite(): array
    {
        $site = Site::factory()->create();

        return [$this->deployedGuardOnSite($site), $site];
    }

    private function deployedGuardOnSite(Site $site): Guard
    {
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'current_supervisor_id' => $site->supervisor_id,
        ]);

        Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
        ]);

        return $guard;
    }
}

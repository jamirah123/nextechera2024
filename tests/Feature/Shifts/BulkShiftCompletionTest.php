<?php

namespace Tests\Feature\Shifts;

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkShiftCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_manager_can_bulk_complete_scheduled_shifts(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $shiftA = Shift::factory()->create(['status' => ShiftStatus::Scheduled]);
        $shiftB = Shift::factory()->create(['status' => ShiftStatus::InProgress]);
        $shiftC = Shift::factory()->create(['status' => ShiftStatus::Cancelled]);

        $this->actingAs($manager)
            ->from(route('shifts.index'))
            ->post(route('shifts.bulk-complete'), [
                'date' => now()->toDateString(),
                'selected' => [$shiftA->id, $shiftB->id, $shiftC->id],
            ])
            ->assertRedirect(route('shifts.index'))
            ->assertSessionHas('status');

        $this->assertSame(ShiftStatus::Completed, $shiftA->fresh()->status);
        $this->assertSame(ShiftStatus::Completed, $shiftB->fresh()->status);
        $this->assertSame(ShiftStatus::Cancelled, $shiftC->fresh()->status);
    }

    public function test_region_supervisor_cannot_bulk_complete_shifts(): void
    {
        $supervisor = User::factory()->role(UserRole::RegionSupervisor)->create();
        $shift = Shift::factory()->create(['status' => ShiftStatus::Scheduled]);

        $this->actingAs($supervisor)
            ->post(route('shifts.bulk-complete'), [
                'selected' => [$shift->id],
            ])
            ->assertForbidden();

        $this->assertSame(ShiftStatus::Scheduled, $shift->fresh()->status);
    }
}

<?php

namespace Tests\Feature\Shifts;

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftService;
use App\Services\Shifts\ShiftLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class ShiftAutoCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_in_progress_shift_is_completed_automatically_when_window_ends(): void
    {
        Carbon::setTestNow('2026-08-28 18:00:00');

        $shift = Shift::factory()->create([
            'status' => ShiftStatus::InProgress,
            'starts_at' => now()->setTime(6, 0),
            'ends_at' => now()->setTime(18, 0),
        ]);

        app(ShiftLifecycleService::class)->sync();

        $this->assertSame(ShiftStatus::Completed, $shift->fresh()->status);
    }

    public function test_scheduled_shift_past_end_is_marked_missed_not_completed(): void
    {
        Carbon::setTestNow('2026-08-28 18:00:00');

        $shift = Shift::factory()->create([
            'status' => ShiftStatus::Scheduled,
            'starts_at' => now()->setTime(6, 0),
            'ends_at' => now()->setTime(18, 0),
        ]);

        app(ShiftLifecycleService::class)->sync();

        $this->assertSame(ShiftStatus::Missed, $shift->fresh()->status);
    }

    public function test_shift_manager_cannot_manually_mark_shift_completed(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $shift = Shift::factory()->create(['status' => ShiftStatus::InProgress]);

        $this->actingAs($manager)
            ->post(route('shifts.status', $shift), [
                'status' => ShiftStatus::Completed->value,
            ])
            ->assertSessionHasErrors();

        $this->assertSame(ShiftStatus::InProgress, $shift->fresh()->status);
    }

    public function test_shift_service_rejects_manual_completion(): void
    {
        $shift = Shift::factory()->create(['status' => ShiftStatus::InProgress]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('completed automatically');

        app(ShiftService::class)->updateStatus($shift, ShiftStatus::Completed);
    }

    public function test_shift_manager_can_mark_shift_cancelled(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $shift = Shift::factory()->create(['status' => ShiftStatus::Scheduled]);

        $this->actingAs($manager)
            ->post(route('shifts.status', $shift), [
                'status' => ShiftStatus::Cancelled->value,
                'notes' => 'Guard reassigned',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(ShiftStatus::Cancelled, $shift->fresh()->status);
    }
}

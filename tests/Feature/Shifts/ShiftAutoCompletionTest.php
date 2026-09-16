<?php

namespace Tests\Feature\Shifts;

use App\Enums\AttendanceEventType;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User;
use App\Services\Shifts\ShiftLifecycleService;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShiftAutoCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_in_progress_shift_is_completed_when_window_ends(): void
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

    public function test_scheduled_shift_past_end_is_completed_unless_cancelled(): void
    {
        Carbon::setTestNow('2026-08-28 18:00:00');

        $shift = Shift::factory()->create([
            'status' => ShiftStatus::Scheduled,
            'starts_at' => now()->setTime(6, 0),
            'ends_at' => now()->setTime(18, 0),
        ]);

        app(ShiftLifecycleService::class)->sync();

        $this->assertSame(ShiftStatus::Completed, $shift->fresh()->status);
    }

    public function test_attendance_gate_marks_in_progress_missed_when_enabled(): void
    {
        config(['psg.shifts.require_attendance_to_complete' => true]);
        Carbon::setTestNow('2026-08-28 18:00:00');

        $shift = Shift::factory()->create([
            'status' => ShiftStatus::InProgress,
            'starts_at' => now()->setTime(6, 0),
            'ends_at' => now()->setTime(18, 0),
        ]);

        app(ShiftLifecycleService::class)->sync();

        $this->assertSame(ShiftStatus::Missed, $shift->fresh()->status);
    }

    public function test_attendance_gate_completes_when_evidence_exists(): void
    {
        config(['psg.shifts.require_attendance_to_complete' => true]);
        Carbon::setTestNow('2026-08-28 18:00:00');

        $shift = Shift::factory()->create([
            'status' => ShiftStatus::InProgress,
            'starts_at' => now()->setTime(6, 0),
            'ends_at' => now()->setTime(18, 0),
        ]);

        Attendance::query()->create([
            'guard_id' => $shift->guard_id,
            'site_id' => $shift->site_id,
            'shift_id' => $shift->id,
            'event_type' => AttendanceEventType::CheckIn,
            'source' => 'test',
            'occurred_at' => now()->setTime(6, 5),
        ]);

        app(ShiftLifecycleService::class)->sync();

        $this->assertSame(ShiftStatus::Completed, $shift->fresh()->status);
    }

    public function test_shift_manager_can_manually_mark_shift_completed_for_correction(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $shift = Shift::factory()->create(['status' => ShiftStatus::Missed]);

        $this->actingAs($manager)
            ->post(route('shifts.status', $shift), [
                'status' => ShiftStatus::Completed->value,
                'notes' => 'Corrected — duty was worked',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(ShiftStatus::Completed, $shift->fresh()->status);
    }

    public function test_shift_service_allows_manual_completion_for_corrections(): void
    {
        $shift = Shift::factory()->create(['status' => ShiftStatus::Cancelled]);

        $updated = app(ShiftService::class)->updateStatus($shift, ShiftStatus::Completed, 'Reopened as completed');

        $this->assertSame(ShiftStatus::Completed, $updated->status);
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

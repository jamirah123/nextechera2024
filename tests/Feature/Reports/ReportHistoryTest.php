<?php

namespace Tests\Feature\Reports;

use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\Guard;
use App\Models\ReportArchive;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_exporting_a_report_keeps_a_searchable_copy(): void
    {
        $user = User::factory()->role(UserRole::FinanceManager)->create(['name' => 'Amina Finance']);
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'current_supervisor_id' => $site->supervisor_id,
        ]);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'region_id' => $site->region_id,
            'site_id' => $site->id,
            'supervisor_id' => $site->supervisor_id,
            'shift_date' => now()->toDateString(),
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Completed,
        ]);

        $month = now()->format('F Y');

        $download = $this->actingAs($user)
            ->get(route('reports.monthly-shifts.export', [
                'year' => now()->year,
                'month' => now()->month,
            ]));

        $download->assertOk();
        $this->assertStringContainsString($guard->full_name, $download->streamedContent());

        $this->assertDatabaseHas('report_archives', [
            'user_id' => $user->id,
            'report_key' => 'monthly_shifts',
            'title' => 'Monthly shift summary',
        ]);

        $this->actingAs($user)
            ->get(route('reports.history', ['q' => $month]))
            ->assertOk()
            ->assertSee('Monthly shift summary')
            ->assertSee($month)
            ->assertSee('Amina Finance');

        $archive = ReportArchive::query()->firstOrFail();
        Storage::disk('local')->assertExists($archive->storage_path);

        $saved = $this->actingAs($user)->get(route('reports.history.download', $archive));
        $saved->assertOk();
        $this->assertStringContainsString($guard->full_name, $saved->streamedContent());

        $procurement = User::factory()->role(UserRole::ProcurementOfficer)->create();

        $this->actingAs($procurement)
            ->get(route('reports.history', ['q' => 'monthly']))
            ->assertOk()
            ->assertDontSee('Monthly shift summary');

        $this->actingAs($procurement)
            ->get(route('reports.history.download', $archive))
            ->assertForbidden();
    }

    public function test_a_user_can_save_a_report_and_only_open_reports_they_may_see(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();

        $this->actingAs($hr)
            ->get(route('reports.hr'))
            ->assertOk()
            ->assertSee('Save report');

        $this->actingAs($hr)
            ->post(route('reports.history.store'), [
                'report' => 'hr',
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ])
            ->assertRedirect(route('reports.history', ['q' => now()->startOfMonth()->format('d M Y').' – '.now()->format('d M Y')]))
            ->assertSessionHas('status');

        $this->actingAs($hr)
            ->get(route('reports.history', ['q' => 'HR summary', 'report_key' => 'hr']))
            ->assertOk()
            ->assertSee('HR summary');

        $this->actingAs($hr)
            ->from(route('reports.hr'))
            ->post(route('reports.history.store'), [
                'report' => 'monthly_shifts',
                'year' => now()->year,
                'month' => now()->month,
            ])
            ->assertRedirect(route('reports.hr'))
            ->assertSessionHas('error', 'You do not have permission to perform this action.');

        $this->assertSame(1, ReportArchive::query()->count());
    }
}

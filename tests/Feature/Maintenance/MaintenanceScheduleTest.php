<?php

namespace Tests\Feature\Maintenance;

use App\Enums\InvoiceStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MaintenanceScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_overdue_invoices_command_updates_past_due_balances(): void
    {
        $client = Client::factory()->create();

        $overdue = Invoice::query()->create([
            'reference' => 'INV-OVERDUE-001',
            'client_id' => $client->id,
            'status' => InvoiceStatus::Issued,
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
            'issue_date' => now()->subMonth(),
            'due_date' => now()->subDays(3),
            'currency' => 'UGX',
            'subtotal' => 1000000,
            'tax_amount' => 0,
            'total' => 1000000,
            'amount_paid' => 0,
            'balance' => 1000000,
        ]);

        $current = Invoice::query()->create([
            'reference' => 'INV-CURRENT-001',
            'client_id' => $client->id,
            'status' => InvoiceStatus::Issued,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'issue_date' => now(),
            'due_date' => now()->addDays(7),
            'currency' => 'UGX',
            'subtotal' => 500000,
            'tax_amount' => 0,
            'total' => 500000,
            'amount_paid' => 0,
            'balance' => 500000,
        ]);

        $this->artisan('psg:mark-overdue-invoices')
            ->expectsOutputToContain('Marked 1 invoice(s) as overdue.')
            ->assertSuccessful();

        $this->assertSame(InvoiceStatus::Overdue, $overdue->fresh()->status);
        $this->assertSame(InvoiceStatus::Issued, $current->fresh()->status);
    }

    public function test_sync_shift_statuses_command_starts_and_marks_missed_shifts(): void
    {
        Carbon::setTestNow('2026-08-27 12:00:00');

        $active = Shift::factory()->create([
            'status' => ShiftStatus::Scheduled,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(5),
        ]);

        $elapsed = Shift::factory()->create([
            'status' => ShiftStatus::Confirmed,
            'starts_at' => now()->subHours(10),
            'ends_at' => now()->subHour(),
        ]);

        $finished = Shift::factory()->create([
            'status' => ShiftStatus::InProgress,
            'starts_at' => now()->subHours(10),
            'ends_at' => now()->subHour(),
        ]);

        $future = Shift::factory()->create([
            'status' => ShiftStatus::Scheduled,
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(10),
        ]);

        $this->artisan('psg:sync-shift-statuses')
            ->expectsOutputToContain('1 started, 2 completed, 0 marked missed.')
            ->assertSuccessful();

        $this->assertSame(ShiftStatus::InProgress, $active->fresh()->status);
        $this->assertSame(ShiftStatus::Completed, $elapsed->fresh()->status);
        $this->assertSame(ShiftStatus::Completed, $finished->fresh()->status);
        $this->assertSame(ShiftStatus::Scheduled, $future->fresh()->status);

        Carbon::setTestNow();
    }

    public function test_release_shift_window_guards_command_runs_successfully(): void
    {
        $this->artisan('psg:release-shift-window-guards')
            ->expectsOutputToContain('Shift window release complete')
            ->assertSuccessful();
    }

    public function test_super_admin_navigation_includes_finance_section(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Client Billing', false)
            ->assertSee(route('billing.index'), false)
            ->assertSee(route('profitability.index'), false);
    }
}

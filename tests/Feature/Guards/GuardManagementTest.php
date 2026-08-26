<?php

namespace Tests\Feature\Guards;

use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Models\Guard;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_manager_can_register_guard_with_employment_id(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $region = Region::factory()->create();

        $this->actingAs($hr)
            ->post(route('guards.store'), [
                'first_name' => 'John',
                'middle_name' => 'Kamau',
                'last_name' => 'Mwangi',
                'region_id' => $region->id,
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::AwaitingDeployment->value,
                'phone' => '+254700000001',
            ])
            ->assertRedirect();

        $guard = Guard::query()->first();

        $this->assertNotNull($guard);
        $this->assertSame('PSG0001', $guard->employment_id);
        $this->assertSame('John Kamau Mwangi', $guard->full_name);
        $this->assertDatabaseCount('guard_status_histories', 2);
    }

    public function test_operations_manager_cannot_create_guards(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();

        $this->actingAs($ops)
            ->get(route('guards.create'))
            ->assertForbidden();
    }

    public function test_finance_manager_can_view_but_not_edit_guards(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $guard = Guard::factory()->create([
            'employment_id' => 'PSG0100',
            'full_name' => 'View Only Guard',
        ]);

        $this->actingAs($finance)
            ->get(route('guards.index'))
            ->assertOk()
            ->assertSee('PSG0100')
            ->assertDontSee('Register guard', false);

        $this->actingAs($finance)
            ->get(route('guards.edit', $guard))
            ->assertForbidden();
    }

    public function test_status_change_creates_history(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $guard = Guard::factory()->create([
            'employment_id' => 'PSG0200',
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
        ]);

        $this->actingAs($admin)
            ->put(route('guards.update', $guard), [
                'first_name' => $guard->first_name,
                'middle_name' => $guard->middle_name,
                'last_name' => $guard->last_name,
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::OnLeave->value,
                'region_id' => $guard->region_id,
                'reason' => 'Annual leave approved',
            ])
            ->assertRedirect(route('guards.show', $guard));

        $this->assertDatabaseHas('guard_status_histories', [
            'guard_id' => $guard->id,
            'status_type' => 'operational',
            'previous_status' => OperationalStatus::OffDuty->value,
            'new_status' => OperationalStatus::OnLeave->value,
            'reason' => 'Annual leave approved',
        ]);
    }

    public function test_live_search_filters_guards_by_employment_id(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        Guard::factory()->create(['employment_id' => 'PSG0555', 'full_name' => 'Alpha Guard', 'first_name' => 'Alpha', 'last_name' => 'Guard']);
        Guard::factory()->create(['employment_id' => 'PSG0666', 'full_name' => 'Beta Guard', 'first_name' => 'Beta', 'last_name' => 'Guard']);

        $this->actingAs($hr)
            ->get(route('guards.index', ['q' => 'PSG0555']))
            ->assertOk()
            ->assertSee('Alpha Guard')
            ->assertDontSee('Beta Guard');
    }

    public function test_global_search_includes_guards(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $guard = Guard::factory()->create([
            'employment_id' => 'PSG0777',
            'full_name' => 'Searchable Guard',
            'first_name' => 'Searchable',
            'last_name' => 'Guard',
        ]);

        $this->actingAs($admin)
            ->getJson(route('search', ['q' => 'PSG0777']))
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'guard',
                'title' => 'Searchable Guard',
                'url' => route('guards.show', $guard),
            ]);
    }
}

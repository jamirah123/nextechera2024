<?php

namespace Tests\Feature\Organization;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Region;
use App\Models\Site;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_manager_can_create_full_organization_chain(): void
    {
        $user = User::factory()->role(UserRole::OperationsManager)->create();

        $this->actingAs($user)
            ->post(route('regions.store'), [
                'name' => 'Central',
                'code' => 'CEN',
                'description' => 'Central region',
                'manager_name' => 'Amina Juma',
                'manager_phone' => '+255700000010',
                'status' => 'active',
            ])
            ->assertRedirect();

        $region = Region::query()->where('code', 'CEN')->firstOrFail();

        $hr = User::factory()->role(UserRole::HrManager)->create();

        $this->actingAs($hr)
            ->post(route('staff.store'), [
                'employee_type' => 'supervisor',
                'employment_id' => 'PSG050',
                'first_name' => 'John',
                'last_name' => 'Supervisor',
                'phone' => '+255700000011',
                'email' => 'john.sup@example.com',
                'region_id' => $region->id,
                'employment_status' => 'active',
                'supervisor_status' => 'active',
                'assignment_date' => now()->toDateString(),
                'assignment_reason' => 'Initial posting',
                'notes' => 'Primary',
                'monthly_salary' => 0,
                'address' => 'Kampala',
                'bank_name' => 'Centenary',
                'bank_account' => '1234567890',
            ])
            ->assertRedirect();

        $supervisor = Supervisor::query()->where('email', 'john.sup@example.com')->firstOrFail();
        $this->assertNotEmpty($supervisor->supervisor_code);
        $this->assertSame('PSG050', $supervisor->fresh()->guardProfile?->employment_id);
        $this->assertSame('PSG050', $supervisor->fresh()->staffProfile?->employment_id);
        $this->assertSame('Kampala', $supervisor->staffProfile?->address);
        $this->assertSame('Centenary', $supervisor->staffProfile?->bank_name);
        $this->assertDatabaseHas('supervisor_assignment_histories', [
            'supervisor_id' => $supervisor->id,
            'new_region_id' => $region->id,
            'change_type' => 'initial_assignment',
        ]);

        $this->actingAs($user)
            ->post(route('clients.store'), [
                'name' => 'ABC Logistics',
                'contact_person' => 'Jane Client',
                'phone' => '+255700000012',
                'email' => 'jane@abc.local',
                'address' => 'Dar es Salaam',
                'contract_start_date' => now()->toDateString(),
                'contract_end_date' => now()->addYear()->toDateString(),
                'contract_status' => 'active',
                'notes' => null,
            ])
            ->assertRedirect();

        $client = Client::query()->where('name', 'ABC Logistics')->firstOrFail();

        $this->actingAs($user)
            ->post(route('sites.store'), [
                'name' => 'ABC Warehouse',
                'code' => 'ABC-WH',
                'client_id' => $client->id,
                'region_id' => $region->id,
                'supervisor_id' => $supervisor->id,
                'physical_location' => 'Industrial Area',
                'latitude' => null,
                'longitude' => null,
                'site_contact_person' => 'Site Contact',
                'site_contact_phone' => '+255700000013',
                'contract_start_date' => now()->toDateString(),
                'contract_end_date' => now()->addYear()->toDateString(),
                'required_guards' => 12,
                'required_day_guards' => 6,
                'required_night_guards' => 6,
                'number_of_posts' => 4,
                'status' => 'active',
                'notes' => null,
            ])
            ->assertRedirect();

        $site = Site::query()->where('code', 'ABC-WH')->firstOrFail();
        $this->assertDatabaseHas('site_manpower_requirements', [
            'site_id' => $site->id,
            'required_total' => 12,
            'required_day' => 6,
            'required_night' => 6,
            'is_current' => true,
        ]);
    }

    public function test_finance_manager_can_view_but_cannot_create_regions(): void
    {
        $user = User::factory()->role(UserRole::FinanceManager)->create();

        $this->actingAs($user)
            ->get(route('organization.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('regions.create'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('regions.store'), [
                'name' => 'Central',
                'code' => 'CEN',
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    public function test_site_rejects_supervisor_from_another_region(): void
    {
        $user = User::factory()->superAdmin()->create();
        $regionA = Region::factory()->create(['code' => 'REGA']);
        $regionB = Region::factory()->create(['code' => 'REGB']);
        $supervisor = Supervisor::factory()->create(['region_id' => $regionB->id]);
        $client = Client::factory()->create();

        $this->actingAs($user)
            ->from(route('sites.create'))
            ->post(route('sites.store'), [
                'name' => 'Mismatch Site',
                'code' => 'MIS-01',
                'client_id' => $client->id,
                'region_id' => $regionA->id,
                'supervisor_id' => $supervisor->id,
                'required_guards' => 4,
                'required_day_guards' => 2,
                'required_night_guards' => 2,
                'number_of_posts' => 1,
                'status' => 'active',
            ])
            ->assertRedirect(route('sites.create'))
            ->assertSessionHasErrors('supervisor_id');
    }

    public function test_supervisor_region_transfer_creates_history(): void
    {
        $user = User::factory()->superAdmin()->create();
        $regionA = Region::factory()->create(['code' => 'AAA']);
        $regionB = Region::factory()->create(['code' => 'BBB']);
        $supervisor = Supervisor::factory()->create(['region_id' => $regionA->id]);

        $this->actingAs($user)
            ->put(route('supervisors.update', $supervisor), [
                'name' => $supervisor->name,
                'phone' => $supervisor->phone,
                'email' => $supervisor->email,
                'region_id' => $regionB->id,
                'status' => 'active',
                'assignment_date' => now()->toDateString(),
                'notes' => null,
                'reason' => 'Operational realignment',
            ])
            ->assertRedirect(route('supervisors.show', $supervisor));

        $this->assertDatabaseHas('supervisor_assignment_histories', [
            'supervisor_id' => $supervisor->id,
            'previous_region_id' => $regionA->id,
            'new_region_id' => $regionB->id,
            'change_type' => 'region_transfer',
        ]);
    }

    public function test_site_show_page_lists_armed_and_unarmed_manpower(): void
    {
        $user = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create([
            'required_day_guards' => 9,
            'required_day_armed_guards' => 3,
            'required_day_unarmed_guards' => 6,
            'required_night_guards' => 10,
            'required_night_armed_guards' => 4,
            'required_night_unarmed_guards' => 6,
            'required_guards' => 19,
        ]);

        $this->actingAs($user)
            ->get(route('sites.show', $site))
            ->assertOk()
            ->assertSee('Day manpower')
            ->assertSee('Night manpower')
            ->assertSee('3 armed · 6 unarmed')
            ->assertSee('4 armed · 6 unarmed');
    }

    public function test_manpower_coverage_page_loads(): void
    {
        $user = User::factory()->role(UserRole::ShiftManager)->create();
        Site::factory()->create(['required_guards' => 10, 'required_day_guards' => 5, 'required_night_guards' => 5]);

        $this->actingAs($user)
            ->get(route('manpower.coverage'))
            ->assertOk()
            ->assertSee('Manpower');
    }
}

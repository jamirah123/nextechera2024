<?php

namespace Tests\Feature\Staff;

use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Models\Region;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_manager_can_register_staff_member(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $region = Region::factory()->create();

        $this->actingAs($hr)
            ->post(route('staff.store'), [
                'employee_type' => 'staff',
                'employment_id' => 'PSG001',
                'first_name' => 'Jane',
                'last_name' => 'Nabwire',
                'job_title' => 'Finance Officer',
                'department' => 'Finance',
                'region_id' => $region->id,
                'employment_status' => EmploymentStatus::Active->value,
                'monthly_salary' => 1500000,
                'bank_name' => 'Stanbic',
                'bank_account' => '1234567890',
            ])
            ->assertRedirect();

        $staff = Staff::query()->first();

        $this->assertNotNull($staff);
        $this->assertSame('PSG001', $staff->employment_id);
        $this->assertSame('Jane Nabwire', $staff->full_name);
        $this->assertSame(1500000.0, (float) $staff->monthly_salary);
    }

    public function test_finance_manager_can_view_but_not_register_staff(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $staff = Staff::factory()->create(['employment_id' => 'PSG0100', 'full_name' => 'Office Clerk']);

        $this->actingAs($finance)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertSee('Office Clerk')
            ->assertSee('Inactive', false)
            ->assertSee('Left', false)
            ->assertDontSee('Register employee', false);

        $this->actingAs($finance)
            ->get(route('staff.create'))
            ->assertForbidden();
    }

    public function test_staff_index_shows_employment_summary_cards(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();

        Staff::factory()->create(['employment_status' => EmploymentStatus::Active]);
        Staff::factory()->create(['employment_status' => EmploymentStatus::Suspended]);
        Staff::factory()->create(['employment_status' => EmploymentStatus::Resigned]);

        $this->actingAs($hr)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertSee('Total', false)
            ->assertSee('Active', false)
            ->assertSee('Inactive', false)
            ->assertSee('Left', false);
    }
}

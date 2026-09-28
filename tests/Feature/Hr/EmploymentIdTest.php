<?php

namespace Tests\Feature\Hr;

use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Guard;
use App\Models\Region;
use App\Models\Staff;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\Hr\EmploymentIdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmploymentIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_id_starts_at_psg001_and_grows_past_999(): void
    {
        $ids = app(EmploymentIdService::class);

        $this->assertSame('PSG001', $ids->next());

        Guard::factory()->create(['employment_id' => 'PSG999']);

        $this->assertSame('PSG1000', $ids->next());
    }

    public function test_sequence_is_shared_across_guards_and_staff(): void
    {
        Guard::factory()->create(['employment_id' => 'PSG025']);
        Staff::factory()->create(['employment_id' => 'PSG030']);

        $this->assertSame('PSG031', app(EmploymentIdService::class)->next());
    }

    public function test_create_form_prefills_next_employment_id(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        Guard::factory()->create(['employment_id' => 'PSG004']);

        $this->actingAs($hr)
            ->get(route('guards.create'))
            ->assertOk()
            ->assertSee('name="employment_id"', false)
            ->assertSee('value="PSG005"', false)
            ->assertSee('already had an existing company ID', false);

        $this->actingAs($hr)
            ->get(route('staff.create'))
            ->assertOk()
            ->assertSee('value="PSG005"', false);
    }

    public function test_manual_legacy_id_is_accepted_when_unique(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $region = Region::factory()->create();

        $this->actingAs($hr)
            ->post(route('guards.store'), [
                'employment_id' => 'psg457',
                'first_name' => 'Legacy',
                'last_name' => 'Guard',
                'region_id' => $region->id,
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::Training->value,
            ])
            ->assertRedirect();

        $this->assertSame('PSG457', Guard::query()->value('employment_id'));
    }

    public function test_duplicate_id_across_staff_and_guard_is_rejected(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $region = Region::factory()->create();
        Staff::factory()->create(['employment_id' => 'PSG100']);

        $this->actingAs($hr)
            ->from(route('guards.create'))
            ->post(route('guards.store'), [
                'employment_id' => 'PSG100',
                'first_name' => 'Clash',
                'last_name' => 'Guard',
                'region_id' => $region->id,
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::Training->value,
            ])
            ->assertRedirect(route('guards.create'))
            ->assertSessionHasErrors('employment_id');
    }

    public function test_invalid_format_is_rejected(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $region = Region::factory()->create();

        $this->actingAs($hr)
            ->from(route('guards.create'))
            ->post(route('guards.store'), [
                'employment_id' => 'STF001',
                'first_name' => 'Bad',
                'last_name' => 'Format',
                'region_id' => $region->id,
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::Training->value,
            ])
            ->assertSessionHasErrors('employment_id');
    }

    public function test_hr_manager_cannot_change_employment_id_after_create(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = Guard::factory()->create(['employment_id' => 'PSG200']);

        $this->actingAs($hr)
            ->put(route('guards.update', $guard), [
                'employment_id' => 'PSG201',
                'first_name' => $guard->first_name,
                'last_name' => $guard->last_name,
                'employment_status' => $guard->employment_status->value,
                'operational_status' => $guard->operational_status->value,
            ])
            ->assertSessionHasErrors('employment_id');

        $this->assertSame('PSG200', $guard->fresh()->employment_id);
    }

    public function test_super_admin_can_correct_employment_id_with_audit_log(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();
        $guard = Guard::factory()->create(['employment_id' => 'PSG300']);

        $this->actingAs($admin)
            ->put(route('guards.update', $guard), [
                'employment_id' => 'PSG457',
                'first_name' => $guard->first_name,
                'last_name' => $guard->last_name,
                'employment_status' => $guard->employment_status->value,
                'operational_status' => $guard->operational_status->value,
                'reason' => 'Restored legacy company ID from paper file.',
            ])
            ->assertRedirect(route('guards.show', $guard));

        $this->assertSame('PSG457', $guard->fresh()->employment_id);

        $log = AuditLog::query()->where('action', 'employment_id.corrected')->first();
        $this->assertNotNull($log);
        $this->assertTrue($log->is_override);
        $this->assertSame('PSG300', $log->context['from'] ?? null);
        $this->assertSame('PSG457', $log->context['to'] ?? null);
    }

    public function test_supervisor_registration_uses_shared_employment_id(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $region = Region::factory()->create();
        Guard::factory()->create(['employment_id' => 'PSG010']);

        $this->actingAs($hr)
            ->get(route('staff.create', ['employee_type' => 'supervisor']))
            ->assertOk()
            ->assertSee('value="PSG011"', false)
            ->assertSee('Employee type', false)
            ->assertSee('Payroll &amp; banking', false);

        $this->actingAs($hr)
            ->post(route('staff.store'), [
                'employee_type' => 'supervisor',
                'employment_id' => 'PSG457',
                'first_name' => 'James',
                'last_name' => 'Otieno',
                'region_id' => $region->id,
                'employment_status' => EmploymentStatus::Active->value,
                'supervisor_status' => 'active',
                'monthly_salary' => 0,
            ])
            ->assertRedirect();

        $supervisor = Supervisor::query()->where('name', 'James Otieno')->firstOrFail();
        $this->assertSame('PSG457', $supervisor->fresh()->guardProfile?->employment_id);
        $this->assertSame('PSG457', $supervisor->fresh()->staffProfile?->employment_id);
        $this->assertSame('Supervisor', $supervisor->staffProfile?->job_title);
        $this->assertSame('PSG458', app(EmploymentIdService::class)->next());
    }

    public function test_operations_manager_cannot_register_supervisors(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $region = Region::factory()->create();

        $this->actingAs($ops)
            ->get(route('staff.create', ['employee_type' => 'supervisor']))
            ->assertForbidden();

        $this->actingAs($ops)
            ->post(route('staff.store'), [
                'employee_type' => 'supervisor',
                'employment_id' => 'PSG500',
                'first_name' => 'Ops',
                'last_name' => 'Created',
                'region_id' => $region->id,
                'employment_status' => EmploymentStatus::Active->value,
                'supervisor_status' => 'active',
                'monthly_salary' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'You do not have permission to perform this action.');

        $this->actingAs($ops)
            ->get(route('supervisors.index'))
            ->assertOk()
            ->assertDontSee('New supervisor', false);
    }

    public function test_supervisor_cannot_reuse_guard_employment_id(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $region = Region::factory()->create();
        Guard::factory()->create(['employment_id' => 'PSG100']);

        $this->actingAs($hr)
            ->from(route('staff.create', ['employee_type' => 'supervisor']))
            ->post(route('staff.store'), [
                'employee_type' => 'supervisor',
                'employment_id' => 'PSG100',
                'first_name' => 'Clash',
                'last_name' => 'Supervisor',
                'region_id' => $region->id,
                'employment_status' => EmploymentStatus::Active->value,
                'supervisor_status' => 'active',
                'monthly_salary' => 0,
            ])
            ->assertRedirect(route('staff.create', ['employee_type' => 'supervisor']))
            ->assertSessionHasErrors('employment_id');
    }

    public function test_super_admin_can_correct_supervisor_employment_id(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();
        $region = Region::factory()->create();
        $hr = User::factory()->role(UserRole::HrManager)->create();

        $this->actingAs($hr)
            ->post(route('staff.store'), [
                'employee_type' => 'supervisor',
                'employment_id' => 'PSG220',
                'first_name' => 'Mary',
                'last_name' => 'Wambui',
                'region_id' => $region->id,
                'employment_status' => EmploymentStatus::Active->value,
                'supervisor_status' => 'active',
                'monthly_salary' => 0,
            ])
            ->assertRedirect();

        $supervisor = Supervisor::query()->where('name', 'Mary Wambui')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('supervisors.update', $supervisor), [
                'employment_id' => 'PSG777',
                'name' => $supervisor->name,
                'region_id' => $supervisor->region_id,
                'status' => $supervisor->status->value,
                'reason' => 'Corrected legacy supervisor ID.',
            ])
            ->assertRedirect(route('supervisors.show', $supervisor));

        $this->assertSame('PSG777', $supervisor->fresh()->guardProfile?->employment_id);
        $this->assertSame('PSG777', $supervisor->fresh()->staffProfile?->employment_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'employment_id.corrected',
        ]);
    }

    public function test_registered_supervisor_appears_on_staff_index(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $region = Region::factory()->create();

        $this->actingAs($hr)
            ->post(route('staff.store'), [
                'employee_type' => 'supervisor',
                'employment_id' => 'PSG330',
                'first_name' => 'Peter',
                'last_name' => 'Okello',
                'region_id' => $region->id,
                'employment_status' => EmploymentStatus::Active->value,
                'supervisor_status' => 'active',
                'monthly_salary' => 0,
            ])
            ->assertRedirect();

        $this->actingAs($hr)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertSee('Peter Okello')
            ->assertSee('PSG330')
            ->assertSee('Field supervisor', false);
    }
}

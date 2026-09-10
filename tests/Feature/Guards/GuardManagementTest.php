<?php

namespace Tests\Feature\Guards;

use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Models\Guard;
use App\Models\GuardAttachment;
use App\Models\Region;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
                'employment_id' => 'PSG001',
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
        $this->assertSame('PSG001', $guard->employment_id);
        $this->assertSame('John Kamau Mwangi', $guard->full_name);
        $this->assertDatabaseCount('guard_status_histories', 2);
    }

    public function test_new_guard_defaults_to_training_operational_status(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $region = Region::factory()->create();

        $this->actingAs($hr)
            ->post(route('guards.store'), [
                'employment_id' => 'PSG002',
                'first_name' => 'Trainee',
                'last_name' => 'Guard',
                'region_id' => $region->id,
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::Training->value,
            ])
            ->assertRedirect();

        $this->assertSame(OperationalStatus::Training, Guard::query()->first()->operational_status);
    }

    public function test_training_guard_is_hidden_from_deployment_board(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $training = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::Training,
            'region_id' => $site->region_id,
            'full_name' => 'Training Wing Guard',
        ]);
        $ready = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
            'full_name' => 'Ready Guard',
        ]);

        $this->actingAs($ops)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertSee('Ready Guard', false)
            ->assertDontSee('Training Wing Guard', false);
    }

    public function test_training_guard_cannot_be_deployed_until_hr_clears_them(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::Training,
            'region_id' => $site->region_id,
        ]);

        $this->actingAs($ops)
            ->post(route('deployments.store'), [
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'shift_type' => 'day',
                'start_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('deployment');

        $this->assertDatabaseCount('deployments', 0);
    }

    public function test_hr_manager_can_upload_attachments_when_registering_guard(): void
    {
        Storage::fake('local');

        $hr = User::factory()->role(UserRole::HrManager)->create();
        $region = Region::factory()->create();
        $idCopy = UploadedFile::fake()->create('national-id.pdf', 120, 'application/pdf');
        $contract = UploadedFile::fake()->create('contract.pdf', 80, 'application/pdf');

        $response = $this->actingAs($hr)
            ->post(route('guards.store'), [
                'employment_id' => 'PSG010',
                'first_name' => 'Jane',
                'last_name' => 'Wanjiku',
                'region_id' => $region->id,
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::AwaitingDeployment->value,
                'attachments' => [$idCopy, $contract],
            ]);

        $guard = Guard::query()->first();
        $response->assertRedirect(route('guards.show', $guard));

        $this->assertDatabaseCount('guard_attachments', 2);
        $this->assertSame(2, $guard->attachments()->count());

        foreach ($guard->attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment->path);
        }

        $this->actingAs($hr)
            ->get(route('guards.show', $guard))
            ->assertOk()
            ->assertSee('national-id.pdf')
            ->assertSee('contract.pdf')
            ->assertSee('View', false);

        $attachment = $guard->attachments()->where('original_name', 'national-id.pdf')->firstOrFail();

        $this->actingAs($hr)
            ->get(route('guards.attachments.show', [$guard, $attachment]))
            ->assertOk()
            ->assertSee('Document viewer', false)
            ->assertSee('national-id.pdf', false);

        $this->actingAs($hr)
            ->get(route('guards.attachments.stream', [$guard, $attachment]))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->actingAs($hr)
            ->get(route('guards.attachments.download', [$guard, $attachment]))
            ->assertOk();

        $this->actingAs($hr)
            ->from(route('guards.attachments.show', [$guard, $attachment]))
            ->delete(route('guards.attachments.destroy', [$guard, $attachment]))
            ->assertRedirect(route('guards.show', $guard));

        $this->assertDatabaseMissing('guard_attachments', ['id' => $attachment->id]);
    }

    public function test_finance_manager_can_download_but_not_delete_guard_attachments(): void
    {
        Storage::fake('local');

        $hr = User::factory()->role(UserRole::HrManager)->create();
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $region = Region::factory()->create();

        $this->actingAs($hr)
            ->post(route('guards.store'), [
                'employment_id' => 'PSG011',
                'first_name' => 'Peter',
                'last_name' => 'Ochieng',
                'region_id' => $region->id,
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::AwaitingDeployment->value,
                'attachments' => [UploadedFile::fake()->create('certificate.pdf', 50, 'application/pdf')],
            ]);

        $guard = Guard::query()->first();
        $attachment = GuardAttachment::query()->first();

        $this->actingAs($finance)
            ->get(route('guards.attachments.show', [$guard, $attachment]))
            ->assertOk();

        $this->actingAs($finance)
            ->get(route('guards.attachments.stream', [$guard, $attachment]))
            ->assertOk();

        $this->actingAs($finance)
            ->get(route('guards.attachments.download', [$guard, $attachment]))
            ->assertOk();

        $this->actingAs($finance)
            ->delete(route('guards.attachments.destroy', [$guard, $attachment]))
            ->assertForbidden();

        $this->assertDatabaseCount('guard_attachments', 1);
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

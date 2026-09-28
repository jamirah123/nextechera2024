<?php

namespace Tests\Feature\Organization;

use App\Enums\UserRole;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationDeleteAndSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_delete_controls_on_region_index_and_show(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $region = Region::factory()->create(['name' => 'Central Zone', 'code' => 'CZX']);

        $this->actingAs($admin)
            ->get(route('regions.index'))
            ->assertOk()
            ->assertSee('Delete', false)
            ->assertSee(route('regions.destroy', $region), false);

        $this->actingAs($admin)
            ->get(route('regions.show', $region))
            ->assertOk()
            ->assertSee('Delete', false);
    }

    public function test_finance_manager_does_not_see_delete_controls(): void
    {
        $user = User::factory()->role(UserRole::FinanceManager)->create();
        Region::factory()->create(['name' => 'Eastern Zone', 'code' => 'EZX']);

        $this->actingAs($user)
            ->get(route('regions.index'))
            ->assertOk()
            ->assertDontSee('>Delete<', false);
    }

    public function test_region_live_search_filters_results(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Region::factory()->create(['name' => 'Central', 'code' => 'CEN']);
        Region::factory()->create(['name' => 'Western', 'code' => 'WES']);

        $this->actingAs($admin)
            ->get(route('regions.index', ['q' => 'West']))
            ->assertOk()
            ->assertSee('Western')
            ->assertDontSee('Central');
    }

    public function test_super_admin_can_delete_empty_region(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $region = Region::factory()->create(['code' => 'DEL1']);

        $this->actingAs($admin)
            ->delete(route('regions.destroy', $region))
            ->assertRedirect(route('regions.index'));

        $this->assertSoftDeleted($region);
    }

    public function test_operations_manager_cannot_delete_region(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $region = Region::factory()->create(['code' => 'OPS1', 'name' => 'Ops Locked']);

        $this->assertFalse($ops->can('delete', $region));
        $this->assertFalse($ops->can('deleteAny', Region::class));
        $this->assertTrue($ops->can('update', $region));

        $this->actingAs($ops)
            ->get(route('regions.index'))
            ->assertOk()
            ->assertDontSee('>Delete<', false);

        $this->actingAs($ops)
            ->delete(route('regions.destroy', $region))
            ->assertRedirect()
            ->assertSessionHas('error', 'You do not have permission to perform this action.');

        $this->assertNotSoftDeleted($region);
    }
}

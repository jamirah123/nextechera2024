<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\DeletedRecordSnapshot;
use App\Models\Guard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchivedRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_and_restore_archived_guard(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();
        $guard = Guard::factory()->create([
            'current_site_id' => null,
            'full_name' => 'Archive Test Guard',
            'employment_id' => 'G-ARCH-1',
        ]);

        $this->actingAs($admin)->delete(route('guards.destroy', $guard))->assertRedirect();

        $snapshot = DeletedRecordSnapshot::query()->where('record_type', 'guard')->first();
        $this->assertNotNull($snapshot);
        $this->assertSame('Archive Test Guard (G-ARCH-1)', $snapshot->label);
        $this->assertNull($snapshot->restored_at);

        $this->actingAs($admin)
            ->get(route('archived.index'))
            ->assertOk()
            ->assertSee('Archive Test Guard');

        $this->actingAs($admin)
            ->post(route('archived.restore', $snapshot))
            ->assertRedirect(route('archived.index', ['status' => 'restored']));

        $this->assertNotNull($snapshot->fresh()->restored_at);
        $this->assertNotNull(Guard::query()->find($guard->id));
    }

    public function test_finance_manager_cannot_access_archived_records(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();

        $this->actingAs($finance)
            ->get(route('archived.index'))
            ->assertForbidden();
    }
}

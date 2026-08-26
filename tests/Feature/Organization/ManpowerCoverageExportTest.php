<?php

namespace Tests\Feature\Organization;

use App\Enums\UserRole;
use App\Models\Region;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManpowerCoverageExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_coverage_page_shows_export_actions(): void
    {
        $user = User::factory()->role(UserRole::OperationsManager)->create();
        Site::factory()->create([
            'required_guards' => 10,
            'required_day_guards' => 5,
            'required_night_guards' => 5,
        ]);

        $this->actingAs($user)
            ->get(route('manpower.coverage'))
            ->assertOk()
            ->assertSee('Export CSV')
            ->assertSee('Print')
            ->assertDontSee('Export Excel')
            ->assertSee(route('manpower.coverage.export', ['format' => 'csv']), false);
    }

    public function test_user_can_export_coverage_csv(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = Site::factory()->create([
            'name' => 'ABC Warehouse',
            'code' => 'ABC-WH',
            'required_guards' => 12,
            'required_day_guards' => 6,
            'required_night_guards' => 6,
        ]);

        $response = $this->actingAs($user)
            ->get(route('manpower.coverage.export', ['format' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('#', $content);
        $this->assertStringContainsString('Site Code', $content);
        $this->assertStringContainsString('ABC-WH', $content);
        $this->assertStringContainsString('ABC Warehouse', $content);
        $this->assertStringContainsString('Coverage %', $content);
    }

    public function test_delete_confirmation_uses_in_app_modal_markup(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $region = Region::factory()->create(['code' => 'MOD1']);

        $this->actingAs($admin)
            ->get(route('regions.show', $region))
            ->assertOk()
            ->assertSee('Confirm delete', false)
            ->assertSee('Yes, delete', false)
            ->assertDontSee('onsubmit="return confirm', false);
    }
}

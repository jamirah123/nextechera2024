<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Region;
use App\Models\Site;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_includes_live_search_for_all_roles(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->role($role)->create();

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('id="global-search"', false)
                ->assertSee('aria-label="Search"', false)
                ->assertDontSee('>Search system<', false)
                ->assertSee('globalSearch', false)
                ->assertSee('/search', false);
        }
    }

    public function test_search_returns_matching_organization_records(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $region = Region::factory()->create(['name' => 'Nairobi Metro', 'code' => 'NBO']);
        $supervisor = Supervisor::factory()->create([
            'name' => 'Jane Supervisor',
            'supervisor_code' => 'SUP0099',
            'region_id' => $region->id,
        ]);
        $client = Client::factory()->create(['name' => 'Acme Holdings']);
        $site = Site::factory()->create([
            'name' => 'Westgate Post',
            'code' => 'WG01',
            'client_id' => $client->id,
            'region_id' => $region->id,
            'supervisor_id' => $supervisor->id,
        ]);

        $this->actingAs($admin)
            ->getJson(route('search', ['q' => 'Westgate']))
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'site',
                'title' => 'Westgate Post',
                'url' => route('sites.show', $site),
            ]);

        $this->actingAs($admin)
            ->getJson(route('search', ['q' => 'Acme']))
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'client',
                'title' => 'Acme Holdings',
            ]);

        $this->actingAs($admin)
            ->getJson(route('search', ['q' => 'Nairobi']))
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'region',
                'title' => 'Nairobi Metro',
            ]);

        $this->actingAs($admin)
            ->getJson(route('search', ['q' => 'SUP0099']))
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'supervisor',
                'title' => 'Jane Supervisor',
            ]);
    }

    public function test_search_requires_authentication(): void
    {
        $this->getJson(route('search', ['q' => 'test']))
            ->assertUnauthorized();
    }

    public function test_short_query_returns_empty_results(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->getJson(route('search', ['q' => 'a']))
            ->assertOk()
            ->assertJson(['results' => []]);
    }
}

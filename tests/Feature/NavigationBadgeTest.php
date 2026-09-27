<?php

namespace Tests\Feature;

use App\Enums\WorkOrderCategory;
use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_badges_follow_open_work_orders(): void
    {
        $user = User::factory()->superAdmin()->create();

        WorkOrder::query()->create([
            'reference' => 'WO-LIVE-1',
            'title' => 'Open cover',
            'category' => WorkOrderCategory::Staffing,
            'status' => WorkOrderStatus::Open,
        ]);
        WorkOrder::query()->create([
            'reference' => 'WO-LIVE-2',
            'title' => 'Assigned cover',
            'category' => WorkOrderCategory::Staffing,
            'status' => WorkOrderStatus::Assigned,
        ]);
        WorkOrder::query()->create([
            'reference' => 'WO-LIVE-3',
            'title' => 'Finished cover',
            'category' => WorkOrderCategory::Staffing,
            'status' => WorkOrderStatus::Completed,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('badgeCount(', false);

        $this->actingAs($user)
            ->getJson(route('navigation.badges'))
            ->assertOk()
            ->assertJsonPath('badges.'.route('work-orders.index'), 2);

        WorkOrder::query()->where('reference', 'WO-LIVE-1')->update([
            'status' => WorkOrderStatus::Completed,
        ]);

        $this->actingAs($user)
            ->getJson(route('navigation.badges'))
            ->assertOk()
            ->assertJsonPath('badges.'.route('work-orders.index'), 1);
    }
}

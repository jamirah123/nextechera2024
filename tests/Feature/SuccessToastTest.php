<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuccessToastTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_success_flash_renders_once_as_a_toast_and_not_on_refresh(): void
    {
        $user = User::factory()->role(UserRole::ShiftManager)->create();

        $this->actingAs($user)
            ->from(route('notifications.index'))
            ->post(route('notifications.preferences'), ['in_app' => '1'])
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('status', 'Notification preferences saved.');

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Notification preferences saved.')
            ->assertSee('\u0022tone\u0022:\u0022success\u0022')
            ->assertSee('Dismiss notification')
            ->assertSee('prefers-reduced-motion')
            ->assertSee('psg-toasts');

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('Notification preferences saved.');
    }

    public function test_validation_and_authorization_failures_do_not_flash_success(): void
    {
        $shifts = User::factory()->role(UserRole::ShiftManager)->create();

        $this->actingAs($shifts)
            ->post(route('guards.store'), [])
            ->assertSessionMissing('status');

        $this->actingAs($shifts)
            ->from(route('dashboard'))
            ->post(route('invoices.store'), [])
            ->assertSessionMissing('status');
    }

    public function test_partial_and_queued_results_do_not_use_the_success_tone(): void
    {
        $user = User::factory()->role(UserRole::ShiftManager)->create();

        $this->actingAs($user)
            ->withSession([
                'status' => 'Posted 2 guard(s). Skipped 1.',
                'deployment_errors' => ['One guard could not be posted.'],
            ])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('\u0022tone\u0022:\u0022warning\u0022')
            ->assertDontSee('\u0022tone\u0022:\u0022success\u0022');

        $this->flushSession();

        $this->actingAs($user)
            ->withSession([
                'status' => 'Payroll calculation queued. Refresh this page in a moment.',
            ])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('\u0022tone\u0022:\u0022info\u0022')
            ->assertDontSee('\u0022tone\u0022:\u0022success\u0022');
    }

    public function test_an_error_flash_uses_the_error_tone(): void
    {
        $user = User::factory()->role(UserRole::ShiftManager)->create();

        $this->actingAs($user)
            ->withSession([
                'error' => 'You do not have permission to perform this action.',
            ])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('You do not have permission to perform this action.')
            ->assertSee('\u0022tone\u0022:\u0022error\u0022')
            ->assertDontSee('\u0022tone\u0022:\u0022success\u0022');
    }
}

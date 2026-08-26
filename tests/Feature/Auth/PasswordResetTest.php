<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Forgot password')
            ->assertSee('Send reset link');
    }

    public function test_login_page_links_to_forgot_password(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'), false);
    }

    public function test_active_user_can_request_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset-me@example.com',
        ]);

        $this->post(route('password.email'), [
            'email' => $user->email,
        ])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_inactive_user_does_not_receive_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->inactive()->create([
            'email' => 'inactive-reset@example.com',
        ]);

        $this->post(route('password.email'), [
            'email' => $user->email,
        ])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-complete@example.com',
        ]);

        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword@456',
            'password_confirmation' => 'NewPassword@456',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('NewPassword@456', $user->fresh()->password));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.password_reset',
            'actor_id' => $user->id,
        ]);
    }
}

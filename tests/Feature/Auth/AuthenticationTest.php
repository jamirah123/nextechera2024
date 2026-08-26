<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Welcome back');
        $response->assertSee('Platinum Security Group');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->role(UserRole::ShiftManager)->create([
            'email' => 'shifts@example.com',
            'password' => 'Password@123',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'Password@123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertNotNull($user->last_login_ip);
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'ops@example.com',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_inactive_users_cannot_authenticate(): void
    {
        $user = User::factory()->inactive()->create([
            'email' => 'inactive@example.com',
            'password' => 'Password@123',
        ]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'Password@123',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
    }

    public function test_authenticated_users_can_access_dashboard(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee($user->name);
        $response->assertSee('Super Admin');
    }

    public function test_guests_are_redirected_from_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}

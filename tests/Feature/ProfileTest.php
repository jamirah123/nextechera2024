<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Shift Manager',
            'phone' => '+255700000004',
        ]);

        $response = $this->actingAs($user)->get(route('profile.show'));

        $response->assertOk();
        $response->assertSee('Shift Manager');
        $response->assertSee('+255700000004');
        $response->assertSee('Edit profile');
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Updated Manager',
            'email' => 'updated@platinumsecurity.local',
            'phone' => '+255711111111',
        ]);

        $response->assertRedirect(route('profile.show'));

        $user->refresh();
        $this->assertSame('Updated Manager', $user->name);
        $this->assertSame('updated@platinumsecurity.local', $user->email);
        $this->assertSame('+255711111111', $user->phone);
    }

    public function test_user_can_update_password(): void
    {
        $user = User::factory()->create([
            'password' => 'Password@123',
        ]);

        $response = $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'Password@123',
            'password' => 'NewPassword@123',
            'password_confirmation' => 'NewPassword@123',
        ]);

        $response->assertRedirect(route('profile.edit'));
        $this->assertTrue(Hash::check('NewPassword@123', $user->fresh()->password));
    }

    public function test_password_update_requires_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'Password@123',
        ]);

        $response = $this->actingAs($user)->from(route('profile.edit'))->put(route('profile.password'), [
            'current_password' => 'wrong-password',
            'password' => 'NewPassword@123',
            'password_confirmation' => 'NewPassword@123',
        ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasErrors('current_password');
    }
}

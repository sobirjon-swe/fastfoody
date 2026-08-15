<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Aziz Karimov',
            'email' => 'aziz@example.com',
            'phone' => '+998901112233',
            'password' => 'parol-12345',
            'password_confirmation' => 'parol-12345',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'aziz@example.com')
            ->assertJsonPath('user.role', UserRole::Customer->value)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'phone', 'role']]);

        $user = User::firstWhere('email', 'aziz@example.com');

        $this->assertNotNull($user);
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertNull($user->restaurant_id);
        $this->assertCount(1, $user->tokens);
    }

    public function test_registration_cannot_grant_a_privileged_role(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->postJson('/api/auth/register', [
            'name' => 'Soxta Admin',
            'email' => 'fake@example.com',
            'password' => 'parol-12345',
            'password_confirmation' => 'parol-12345',
            'role' => UserRole::SuperAdmin->value,
            'restaurant_id' => $restaurant->id,
        ])->assertCreated();

        $user = User::firstWhere('email', 'fake@example.com');

        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertNull($user->restaurant_id);
    }

    public function test_registration_requires_a_unique_email_and_confirmed_password(): void
    {
        User::factory()->create(['email' => 'band@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Aziz',
            'email' => 'band@example.com',
            'password' => 'parol-12345',
            'password_confirmation' => 'boshqa-parol',
        ])->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
    }
}

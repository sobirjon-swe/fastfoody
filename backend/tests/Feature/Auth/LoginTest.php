<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_log_in_with_valid_credentials(): void
    {
        User::factory()->create(['email' => 'mijoz@example.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'mijoz@example.com',
            'password' => 'password',
            'device_name' => 'web',
        ])->assertOk()
            ->assertJsonPath('user.email', 'mijoz@example.com')
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_login_fails_with_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'mijoz@example.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'mijoz@example.com',
            'password' => 'notiogri-parol',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertSame(0, User::firstWhere('email', 'mijoz@example.com')->tokens()->count());
    }

    public function test_staff_login_returns_their_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create(['name' => 'Chorsu Fastfood']);
        $staff = User::factory()->staff($restaurant)->create(['email' => 'xodim@example.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'xodim@example.com',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('user.role', UserRole::RestaurantStaff->value)
            ->assertJsonPath('user.restaurant_id', $restaurant->id)
            ->assertJsonPath('user.restaurant.name', 'Chorsu Fastfood');

        $this->assertTrue($staff->fresh()->isRestaurantStaff());
    }

    public function test_the_current_user_can_be_fetched_and_the_token_revoked(): void
    {
        $user = User::factory()->superAdmin()->create();
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', UserRole::SuperAdmin->value)
            ->assertJsonMissingPath('user.password');

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->assertSame(0, $user->tokens()->count());

        // Drop the guard cached by the previous request so the revoked token is
        // resolved from the database again.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_guests_cannot_reach_protected_routes(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    }
}

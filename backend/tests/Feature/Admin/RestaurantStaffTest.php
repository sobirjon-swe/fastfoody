<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantStaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_a_staff_account_bound_to_the_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create();

        $response = $this->actingAs(User::factory()->superAdmin()->create(), 'sanctum')
            ->postJson("/api/admin/restaurants/{$restaurant->id}/staff", [
                'name' => 'Yangi Xodim',
                'email' => 'xodim@fastfoody.uz',
                'phone' => '+998901112233',
                'password' => 'parol-12345',
                'password_confirmation' => 'parol-12345',
            ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', UserRole::RestaurantStaff->value)
            ->assertJsonPath('user.restaurant_id', $restaurant->id);

        $staff = User::firstWhere('email', 'xodim@fastfoody.uz');

        $this->assertTrue($staff->isRestaurantStaff());
        $this->assertSame($restaurant->id, $staff->restaurant_id);
        $this->assertTrue(password_verify('parol-12345', $staff->password));
    }

    public function test_the_new_staff_member_can_sign_in(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create(), 'sanctum')
            ->postJson("/api/admin/restaurants/{$restaurant->id}/staff", [
                'name' => 'Yangi Xodim',
                'email' => 'xodim@fastfoody.uz',
                'password' => 'parol-12345',
                'password_confirmation' => 'parol-12345',
            ])->assertCreated();

        $this->postJson('/api/auth/login', [
            'email' => 'xodim@fastfoody.uz',
            'password' => 'parol-12345',
        ])->assertOk()->assertJsonPath('user.restaurant.id', $restaurant->id);
    }

    public function test_staff_creation_validates_a_unique_email(): void
    {
        $restaurant = Restaurant::factory()->create();
        User::factory()->create(['email' => 'band@fastfoody.uz']);

        $this->actingAs(User::factory()->superAdmin()->create(), 'sanctum')
            ->postJson("/api/admin/restaurants/{$restaurant->id}/staff", [
                'name' => 'Yangi Xodim',
                'email' => 'band@fastfoody.uz',
                'password' => 'parol-12345',
                'password_confirmation' => 'boshqa-parol',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_only_the_restaurants_own_staff_are_listed(): void
    {
        $restaurant = Restaurant::factory()->create();
        $other = Restaurant::factory()->create();

        User::factory()->staff($restaurant)->create(['name' => 'Bizniki']);
        User::factory()->staff($other)->create(['name' => 'Begona']);
        User::factory()->customer()->create(['name' => 'Mijoz']);

        $this->actingAs(User::factory()->superAdmin()->create(), 'sanctum')
            ->getJson("/api/admin/restaurants/{$restaurant->id}/staff")
            ->assertOk()
            ->assertJsonCount(1, 'staff')
            ->assertJsonPath('staff.0.name', 'Bizniki');
    }

    public function test_staff_cannot_create_other_staff_accounts(): void
    {
        $restaurant = Restaurant::factory()->create();
        $staff = User::factory()->staff($restaurant)->create();

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/admin/restaurants/{$restaurant->id}/staff", [
                'name' => 'Sherik',
                'email' => 'sherik@fastfoody.uz',
                'password' => 'parol-12345',
                'password_confirmation' => 'parol-12345',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'sherik@fastfoody.uz']);
    }
}

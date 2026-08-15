<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileAndStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_change_their_own_details(): void
    {
        $user = User::factory()->customer()->create(['name' => 'Eski Ism']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile', [
                'name' => 'Yangi Ism',
                'phone' => '+998901112233',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Yangi Ism')
            ->assertJsonPath('user.phone', '+998901112233');

        $this->assertSame('Yangi Ism', $user->fresh()->name);
    }

    public function test_the_profile_cannot_be_used_to_change_role_or_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->customer()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile', [
                'name' => 'Ayyor Mijoz',
                'role' => 'super_admin',
                'restaurant_id' => $restaurant->id,
            ])
            ->assertOk();

        $user->refresh();

        $this->assertTrue($user->isCustomer());
        $this->assertNull($user->restaurant_id);
    }

    public function test_an_email_that_belongs_to_somebody_else_is_refused(): void
    {
        User::factory()->create(['email' => 'band@fastfoody.uz']);
        $user = User::factory()->customer()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile', ['email' => 'band@fastfoody.uz'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_changing_the_password_requires_the_current_one(): void
    {
        $user = User::factory()->customer()->create(['password' => Hash::make('eski-parol')]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/auth/password', [
                'current_password' => 'notoʻgʻri',
                'password' => 'yangi-parol-123',
                'password_confirmation' => 'yangi-parol-123',
            ])
            ->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('eski-parol', $user->fresh()->password));
    }

    public function test_a_new_password_closes_other_sessions_but_keeps_this_one(): void
    {
        $user = User::factory()->customer()->create(['password' => Hash::make('eski-parol')]);
        $other = $user->createToken('telefon')->plainTextToken;
        $current = $user->createToken('web')->plainTextToken;

        $this->withToken($current)->putJson('/api/auth/password', [
            'current_password' => 'eski-parol',
            'password' => 'yangi-parol-123',
            'password_confirmation' => 'yangi-parol-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('yangi-parol-123', $user->fresh()->password));
        $this->assertSame(1, $user->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson('/api/auth/me')->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withToken($current)->getJson('/api/auth/me')->assertOk();
    }

    public function test_staff_statistics_count_only_their_own_restaurant(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');

        $restaurant = Restaurant::factory()->create();
        $staff = User::factory()->staff($restaurant)->create();

        Order::factory()->status(OrderStatus::PickedUp)->create([
            'restaurant_id' => $restaurant->id, 'total_price' => 50000, 'prep_minutes' => 10,
        ]);
        Order::factory()->status(OrderStatus::Paid)->create([
            'restaurant_id' => $restaurant->id, 'total_price' => 30000, 'prep_minutes' => 20,
        ]);
        // Bekor qilingan buyurtma tushumga kirmaydi.
        Order::factory()->status(OrderStatus::CancelledOutOfStock)->create([
            'restaurant_id' => $restaurant->id, 'total_price' => 99000, 'prep_minutes' => 5,
        ]);
        // Boshqa oshxonaning buyurtmasi ham kirmaydi.
        Order::factory()->status(OrderStatus::PickedUp)->create(['total_price' => 77000]);

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/staff/statistics')
            ->assertOk()
            ->assertJsonPath('statistics.today.orders', 3)
            ->assertJsonPath('statistics.today.revenue', '80000.00')
            ->assertJsonPath('statistics.today.cancelled', 1)
            ->assertJsonPath('statistics.today.average_prep_minutes', 15);

        Carbon::setTestNow();
    }

    public function test_the_super_admin_sees_every_restaurant(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');

        $alfa = Restaurant::factory()->create(['name' => 'Alfa']);
        $beta = Restaurant::factory()->create(['name' => 'Beta']);

        Order::factory()->status(OrderStatus::PickedUp)->create([
            'restaurant_id' => $alfa->id, 'total_price' => 40000,
        ]);
        Order::factory()->status(OrderStatus::PickedUp)->create([
            'restaurant_id' => $beta->id, 'total_price' => 60000,
        ]);

        $this->actingAs(User::factory()->superAdmin()->create(), 'sanctum')
            ->getJson('/api/admin/statistics')
            ->assertOk()
            ->assertJsonPath('statistics.today.orders', 2)
            ->assertJsonPath('statistics.today.revenue', '100000.00')
            ->assertJsonCount(2, 'statistics.restaurants')
            ->assertJsonPath('statistics.restaurants.0.name', 'Alfa')
            ->assertJsonPath('statistics.restaurants.0.today.revenue', '40000.00')
            ->assertJsonPath('statistics.restaurants.1.today.revenue', '60000.00');

        Carbon::setTestNow();
    }

    public function test_statistics_are_role_restricted(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'sanctum')->getJson('/api/admin/statistics')->assertForbidden();
        $this->actingAs($customer, 'sanctum')->getJson('/api/staff/statistics')->assertForbidden();

        $staff = User::factory()->staff()->create();
        $this->actingAs($staff, 'sanctum')->getJson('/api/admin/statistics')->assertForbidden();
    }
}

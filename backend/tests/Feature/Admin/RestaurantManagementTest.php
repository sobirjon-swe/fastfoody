<?php

namespace Tests\Feature\Admin;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    public function test_super_admin_can_list_restaurants_with_counts(): void
    {
        $restaurant = Restaurant::factory()->create(['name' => 'Alfa Fastfood']);
        MenuItem::factory()->count(3)->for($restaurant)->create();
        User::factory()->staff($restaurant)->create();
        Restaurant::factory()->inactive()->create(['name' => 'Beta Fastfood']);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/restaurants');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.name', 'Alfa Fastfood')
            ->assertJsonPath('data.0.menu_items_count', 3)
            ->assertJsonPath('data.0.staff_count', 1)
            ->assertJsonPath('data.1.name', 'Beta Fastfood')
            ->assertJsonPath('data.1.is_active', false);
    }

    public function test_the_list_can_be_filtered_by_search_term_and_status(): void
    {
        Restaurant::factory()->create(['name' => 'Chorsu Fastfood', 'address' => 'Toshkent']);
        Restaurant::factory()->create(['name' => 'Yunusobod Burger', 'address' => 'Toshkent']);
        Restaurant::factory()->inactive()->create(['name' => 'Yopilgan Joy', 'address' => 'Samarqand']);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/restaurants?q=Chorsu')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Chorsu Fastfood');

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/restaurants?status=inactive')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Yopilgan Joy');

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/restaurants?status=active')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_an_unknown_status_value_does_not_filter_anything(): void
    {
        Restaurant::factory()->create();
        Restaurant::factory()->inactive()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/restaurants?status=hammasi')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_search_treats_like_wildcards_as_plain_characters(): void
    {
        Restaurant::factory()->create(['name' => '100% Halol', 'address' => 'Toshkent']);
        Restaurant::factory()->create(['name' => 'Boshqa Joy', 'address' => 'Toshkent 100']);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/restaurants?q='.urlencode('100%'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', '100% Halol');

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/restaurants?q='.urlencode('%'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/restaurants?q='.urlencode('Boshqa_Joy'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_super_admin_can_create_a_restaurant(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/restaurants', [
                'name' => 'Yangi Fastfood',
                'address' => 'Toshkent, Yunusobod 4',
                'phone' => '+998901234567',
                'opens_at' => '10:00',
                'closes_at' => '23:30',
            ]);

        $response->assertCreated()
            ->assertJsonPath('restaurant.name', 'Yangi Fastfood')
            ->assertJsonPath('restaurant.opens_at', '10:00')
            ->assertJsonPath('restaurant.closes_at', '23:30')
            ->assertJsonPath('restaurant.is_active', true)
            ->assertJsonPath('restaurant.menu_items_count', 0);

        $this->assertDatabaseHas('restaurants', [
            'name' => 'Yangi Fastfood',
            'opens_at' => '10:00:00',
        ]);
    }

    public function test_creating_a_restaurant_validates_its_input(): void
    {
        Restaurant::factory()->create(['name' => 'Band Nom']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/restaurants', [
                'name' => 'Band Nom',
                'address' => '',
                'opens_at' => '25:00',
                'closes_at' => '10:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'address', 'opens_at']);
    }

    public function test_a_restaurant_can_be_updated_and_deactivated(): void
    {
        $restaurant = Restaurant::factory()->create(['name' => 'Eski Nom']);

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/admin/restaurants/{$restaurant->id}", [
                'name' => 'Yangi Nom',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('restaurant.name', 'Yangi Nom')
            ->assertJsonPath('restaurant.is_active', false);

        $restaurant->refresh();

        $this->assertSame('Yangi Nom', $restaurant->name);
        $this->assertFalse($restaurant->is_active);
    }

    public function test_a_partial_update_keeps_the_other_working_hour(): void
    {
        $restaurant = Restaurant::factory()->create(['opens_at' => '09:00:00', 'closes_at' => '22:00:00']);

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/admin/restaurants/{$restaurant->id}", ['closes_at' => '23:00'])
            ->assertOk()
            ->assertJsonPath('restaurant.opens_at', '09:00')
            ->assertJsonPath('restaurant.closes_at', '23:00');

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/admin/restaurants/{$restaurant->id}", ['closes_at' => '09:00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('closes_at');
    }

    public function test_seconds_cannot_be_used_to_slip_past_the_working_hour_comparison(): void
    {
        $restaurant = Restaurant::factory()->create(['opens_at' => '09:00:00', 'closes_at' => '22:00:00']);

        // "09:00:00" and "09:00" are the same instant, so this must be rejected
        // exactly like the plain "09:00" form.
        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/admin/restaurants/{$restaurant->id}", ['closes_at' => '09:00:00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('closes_at');

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/restaurants', [
                'name' => 'Nol Uzunlikdagi Ish Vaqti',
                'address' => 'Toshkent',
                'opens_at' => '09:00',
                'closes_at' => '09:00:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('closes_at');

        $this->assertSame('22:00', $restaurant->fresh()->closes_at);
        $this->assertDatabaseMissing('restaurants', ['name' => 'Nol Uzunlikdagi Ish Vaqti']);
    }

    public function test_a_partial_update_does_not_rewrite_the_untouched_working_hour(): void
    {
        $restaurant = Restaurant::factory()->create(['opens_at' => '09:30:45', 'closes_at' => '22:00:00']);

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/admin/restaurants/{$restaurant->id}", ['closes_at' => '23:15'])
            ->assertOk();

        $this->assertDatabaseHas('restaurants', [
            'id' => $restaurant->id,
            'opens_at' => '09:30:45',
            'closes_at' => '23:15:00',
        ]);
    }

    public function test_updating_can_keep_the_restaurants_own_name(): void
    {
        $restaurant = Restaurant::factory()->create(['name' => 'Oʻz Nomi']);

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/admin/restaurants/{$restaurant->id}", [
                'name' => 'Oʻz Nomi',
                'address' => 'Yangi manzil',
            ])
            ->assertOk()
            ->assertJsonPath('restaurant.address', 'Yangi manzil');
    }

    public function test_staff_and_customers_cannot_reach_restaurant_management(): void
    {
        $restaurant = Restaurant::factory()->create();

        foreach ([User::factory()->staff($restaurant)->create(), User::factory()->customer()->create()] as $user) {
            $this->actingAs($user, 'sanctum')->getJson('/api/admin/restaurants')->assertForbidden();
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/admin/restaurants', ['name' => 'X'])
                ->assertForbidden();
            $this->actingAs($user, 'sanctum')
                ->patchJson("/api/admin/restaurants/{$restaurant->id}", ['name' => 'X'])
                ->assertForbidden();
        }
    }

    public function test_guests_cannot_reach_restaurant_management(): void
    {
        $this->getJson('/api/admin/restaurants')->assertUnauthorized();
        $this->postJson('/api/admin/restaurants', ['name' => 'X'])->assertUnauthorized();
    }
}

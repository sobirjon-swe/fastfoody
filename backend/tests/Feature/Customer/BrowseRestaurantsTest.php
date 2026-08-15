<?php

namespace Tests\Feature\Customer;

use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrowseRestaurantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_restaurants_are_listed(): void
    {
        $open = Restaurant::factory()->create(['name' => 'Ochiq Fastfood']);
        MenuItem::factory()->count(2)->for($open)->create();
        MenuItem::factory()->for($open)->unavailable()->create();
        Restaurant::factory()->inactive()->create(['name' => 'Yopiq Fastfood']);

        $this->getJson('/api/restaurants')
            ->assertOk()
            ->assertJsonCount(1, 'restaurants')
            ->assertJsonPath('restaurants.0.name', 'Ochiq Fastfood')
            // The count advertises what can actually be ordered.
            ->assertJsonPath('restaurants.0.menu_items_count', 2);
    }

    public function test_the_list_can_be_searched(): void
    {
        Restaurant::factory()->create(['name' => 'Chorsu Fastfood', 'address' => 'Toshkent']);
        Restaurant::factory()->create(['name' => 'Samarqand Burger', 'address' => 'Samarqand']);

        $this->getJson('/api/restaurants?q=Samarqand')
            ->assertOk()
            ->assertJsonCount(1, 'restaurants')
            ->assertJsonPath('restaurants.0.name', 'Samarqand Burger');
    }

    public function test_a_menu_shows_only_available_items(): void
    {
        $restaurant = Restaurant::factory()->create();
        MenuItem::factory()->for($restaurant)->create(['name' => 'Lavash']);
        MenuItem::factory()->for($restaurant)->unavailable()->create(['name' => 'Tugagan Taom']);
        MenuItem::factory()->create(['name' => 'Begona Taom']);

        $this->getJson("/api/restaurants/{$restaurant->id}")
            ->assertOk()
            ->assertJsonPath('restaurant.id', $restaurant->id)
            ->assertJsonCount(1, 'menu_items')
            ->assertJsonPath('menu_items.0.name', 'Lavash');
    }

    public function test_an_inactive_restaurant_is_not_found(): void
    {
        $restaurant = Restaurant::factory()->inactive()->create();

        $this->getJson("/api/restaurants/{$restaurant->id}")->assertNotFound();
    }

    public function test_browsing_does_not_require_signing_in(): void
    {
        Restaurant::factory()->create();

        $this->getJson('/api/restaurants')->assertOk();
    }
}

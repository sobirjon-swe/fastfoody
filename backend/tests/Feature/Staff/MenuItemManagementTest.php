<?php

namespace Tests\Feature\Staff;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuItemManagementTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->staff = User::factory()->staff($this->restaurant)->create();
    }

    public function test_staff_only_see_the_menu_of_their_own_restaurant(): void
    {
        MenuItem::factory()->for($this->restaurant)->create(['name' => 'Bizning Lavash']);
        MenuItem::factory()->create(['name' => 'Begona Lavash']);

        $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/staff/menu-items')
            ->assertOk()
            ->assertJsonCount(1, 'menu_items')
            ->assertJsonPath('menu_items.0.name', 'Bizning Lavash');
    }

    public function test_staff_can_create_a_menu_item_for_their_restaurant(): void
    {
        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/staff/menu-items', [
                'name' => 'Lavash',
                'description' => 'Tovuqli',
                'price' => 32000,
                'base_prep_minutes' => 4,
                'extra_prep_minutes' => 2,
            ]);

        $response->assertCreated()
            ->assertJsonPath('menu_item.name', 'Lavash')
            ->assertJsonPath('menu_item.restaurant_id', $this->restaurant->id)
            ->assertJsonPath('menu_item.is_available', true);

        $this->assertDatabaseHas('menu_items', [
            'name' => 'Lavash',
            'restaurant_id' => $this->restaurant->id,
            'base_prep_minutes' => 4,
        ]);
    }

    public function test_a_menu_item_cannot_be_created_for_another_restaurant(): void
    {
        $other = Restaurant::factory()->create();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/staff/menu-items', [
                'name' => 'Lavash',
                'price' => 32000,
                'base_prep_minutes' => 4,
                'extra_prep_minutes' => 2,
                'restaurant_id' => $other->id,
            ])
            ->assertCreated()
            ->assertJsonPath('menu_item.restaurant_id', $this->restaurant->id);

        $this->assertSame(0, $other->menuItems()->count());
    }

    public function test_creating_a_menu_item_validates_its_input(): void
    {
        MenuItem::factory()->for($this->restaurant)->create(['name' => 'Band Taom']);

        $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/staff/menu-items', [
                'name' => 'Band Taom',
                'price' => -5,
                'base_prep_minutes' => 0,
                'extra_prep_minutes' => -1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'price', 'base_prep_minutes', 'extra_prep_minutes']);
    }

    public function test_the_same_name_is_allowed_in_a_different_restaurant(): void
    {
        MenuItem::factory()->create(['name' => 'Lavash']);

        $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/staff/menu-items', [
                'name' => 'Lavash',
                'price' => 30000,
                'base_prep_minutes' => 3,
                'extra_prep_minutes' => 1,
            ])
            ->assertCreated();
    }

    public function test_staff_can_update_and_toggle_availability(): void
    {
        $item = MenuItem::factory()->for($this->restaurant)->create(['price' => 30000]);

        $this->actingAs($this->staff, 'sanctum')
            ->patchJson("/api/staff/menu-items/{$item->id}", [
                'price' => 35000,
                'is_available' => false,
            ])
            ->assertOk()
            ->assertJsonPath('menu_item.price', '35000.00')
            ->assertJsonPath('menu_item.is_available', false);

        $item->refresh();

        $this->assertSame('35000.00', $item->price);
        $this->assertFalse($item->is_available);
    }

    public function test_staff_can_delete_their_own_menu_item(): void
    {
        $item = MenuItem::factory()->for($this->restaurant)->create();

        $this->actingAs($this->staff, 'sanctum')
            ->deleteJson("/api/staff/menu-items/{$item->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
    }

    public function test_another_restaurants_item_is_invisible_rather_than_forbidden(): void
    {
        $foreign = MenuItem::factory()->create(['name' => 'Begona Taom']);

        $this->actingAs($this->staff, 'sanctum')
            ->getJson("/api/staff/menu-items/{$foreign->id}")
            ->assertNotFound();

        $this->actingAs($this->staff, 'sanctum')
            ->patchJson("/api/staff/menu-items/{$foreign->id}", ['price' => 1000])
            ->assertNotFound();

        $this->actingAs($this->staff, 'sanctum')
            ->deleteJson("/api/staff/menu-items/{$foreign->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('menu_items', ['id' => $foreign->id, 'name' => 'Begona Taom']);
    }

    public function test_staff_without_a_restaurant_cannot_touch_any_menu(): void
    {
        $orphan = User::factory()->staff()->create();
        $orphan->restaurant_id = null;
        $orphan->save();

        $this->actingAs($orphan, 'sanctum')
            ->getJson('/api/staff/menu-items')
            ->assertForbidden();

        $this->actingAs($orphan, 'sanctum')
            ->postJson('/api/staff/menu-items', [
                'name' => 'Lavash',
                'price' => 30000,
                'base_prep_minutes' => 3,
                'extra_prep_minutes' => 1,
            ])
            ->assertForbidden();
    }

    public function test_customers_and_super_admins_do_not_use_the_staff_menu_routes(): void
    {
        $this->actingAs(User::factory()->customer()->create(), 'sanctum')
            ->getJson('/api/staff/menu-items')
            ->assertForbidden();

        $this->actingAs(User::factory()->superAdmin()->create(), 'sanctum')
            ->getJson('/api/staff/menu-items')
            ->assertForbidden();
    }

    public function test_guests_cannot_reach_the_staff_menu_routes(): void
    {
        $this->getJson('/api/staff/menu-items')->assertUnauthorized();
        $this->postJson('/api/staff/menu-items', ['name' => 'X'])->assertUnauthorized();
    }
}

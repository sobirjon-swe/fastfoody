<?php

namespace Tests\Feature\Customer;

use App\Enums\OrderStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceOrderTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $customer;

    private MenuItem $lavash;

    private MenuItem $cola;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->customer = User::factory()->customer()->create();

        $this->lavash = MenuItem::factory()->for($this->restaurant)->create([
            'name' => 'Lavash',
            'price' => 32000,
            'base_prep_minutes' => 4,
            'extra_prep_minutes' => 2,
        ]);

        $this->cola = MenuItem::factory()->for($this->restaurant)->create([
            'name' => 'Cola',
            'price' => 9000,
            'base_prep_minutes' => 1,
            'extra_prep_minutes' => 0,
        ]);
    }

    public function test_a_customer_can_place_an_order(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [
                ['menu_item_id' => $this->lavash->id, 'quantity' => 2],
                ['menu_item_id' => $this->cola->id, 'quantity' => 3],
            ],
        ]);

        // 2 lavash = 64 000, 3 cola = 27 000
        // tayyorlash: (4 + 2) + (1 + 0 + 0) = 7 daqiqa
        $response->assertCreated()
            ->assertJsonPath('order.status', OrderStatus::Pending->value)
            ->assertJsonPath('order.total_price', '91000.00')
            ->assertJsonPath('order.prep_minutes', 7)
            ->assertJsonPath('order.ready_at', null)
            ->assertJsonCount(2, 'order.items')
            ->assertJsonPath('order.items.0.name', 'Lavash')
            ->assertJsonPath('order.items.0.line_total', '64000.00');

        $order = Order::firstOrFail();

        $this->assertSame($this->customer->id, $order->customer_id);
        $this->assertSame($this->restaurant->id, $order->restaurant_id);
        $this->assertSame('91000.00', $order->total_price);
        $this->assertSame(7, $order->prep_minutes);
    }

    public function test_prices_come_from_the_menu_not_from_the_cart(): void
    {
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [[
                'menu_item_id' => $this->lavash->id,
                'quantity' => 1,
                'unit_price' => 1,
                'line_total' => 1,
                'name' => 'Tekin lavash',
            ]],
        ])->assertCreated()
            ->assertJsonPath('order.total_price', '32000.00')
            ->assertJsonPath('order.items.0.name', 'Lavash')
            ->assertJsonPath('order.items.0.unit_price', '32000.00');
    }

    public function test_the_order_keeps_its_own_copy_of_the_item(): void
    {
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [['menu_item_id' => $this->lavash->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->lavash->update(['name' => 'Yangi nom', 'price' => 99000]);
        $this->lavash->delete();

        $item = Order::firstOrFail()->items()->firstOrFail();

        $this->assertSame('Lavash', $item->name);
        $this->assertSame('32000.00', $item->unit_price);
        $this->assertNull($item->menu_item_id);
    }

    public function test_an_item_of_another_restaurant_cannot_be_ordered(): void
    {
        $foreign = MenuItem::factory()->create(['name' => 'Begona Taom']);

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [['menu_item_id' => $foreign->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.menu_item_id');

        $this->assertSame(0, Order::count());
    }

    public function test_an_unavailable_item_cannot_be_ordered(): void
    {
        $this->cola->update(['is_available' => false]);

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [
                ['menu_item_id' => $this->lavash->id, 'quantity' => 1],
                ['menu_item_id' => $this->cola->id, 'quantity' => 1],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('items.1.menu_item_id');

        // The whole cart is refused, nothing is half-ordered.
        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());
    }

    public function test_an_inactive_restaurant_does_not_take_orders(): void
    {
        $this->restaurant->update(['is_active' => false]);

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [['menu_item_id' => $this->lavash->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('restaurant_id');
    }

    public function test_the_cart_is_validated(): void
    {
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('items');

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [['menu_item_id' => $this->lavash->id, 'quantity' => 0]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.quantity');

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [
                ['menu_item_id' => $this->lavash->id, 'quantity' => 1],
                ['menu_item_id' => $this->lavash->id, 'quantity' => 2],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.menu_item_id');
    }

    public function test_staff_and_super_admins_do_not_place_orders(): void
    {
        foreach ([User::factory()->staff()->create(), User::factory()->superAdmin()->create()] as $user) {
            $this->actingAs($user, 'sanctum')->postJson('/api/orders', [
                'restaurant_id' => $this->restaurant->id,
                'items' => [['menu_item_id' => $this->lavash->id, 'quantity' => 1]],
            ])->assertForbidden();
        }
    }

    public function test_guests_cannot_place_orders(): void
    {
        $this->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [['menu_item_id' => $this->lavash->id, 'quantity' => 1]],
        ])->assertUnauthorized();
    }
}

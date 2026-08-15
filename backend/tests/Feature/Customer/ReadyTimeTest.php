<?php

namespace Tests\Feature\Customer;

use App\Enums\OrderStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\KitchenQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * TZ 5-bandi: yakuniy tayyor boʻlish vaqti = navbatdagi oxirgi buyurtma tugash
 * vaqti + yangi buyurtmaning oʻz tayyorlanish vaqti.
 */
class ReadyTimeTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $customer;

    private MenuItem $lavash;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-15 12:00:00');

        $this->restaurant = Restaurant::factory()->create();
        $this->customer = User::factory()->customer()->create();
        $this->lavash = MenuItem::factory()->for($this->restaurant)->create([
            'name' => 'Lavash',
            'price' => 32000,
            'base_prep_minutes' => 4,
            'extra_prep_minutes' => 2,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_an_empty_kitchen_promises_only_the_orders_own_time(): void
    {
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders/estimate', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [['menu_item_id' => $this->lavash->id, 'quantity' => 3]],
        ])->assertOk()
            // 4 + 2 + 2 = 8 daqiqa, navbat boʻsh
            ->assertJsonPath('estimate.prep_minutes', 8)
            ->assertJsonPath('estimate.queue_minutes', 0)
            ->assertJsonPath('estimate.ready_at', '2026-08-15T12:08:00.000000Z')
            ->assertJsonPath('estimate.total_price', '96000.00');
    }

    public function test_the_queue_is_added_on_top_of_the_orders_own_time(): void
    {
        // Oshxonada 12:10 da tugaydigan buyurtma turibdi.
        Order::factory()->status(OrderStatus::Paid)->create([
            'restaurant_id' => $this->restaurant->id,
            'prep_minutes' => 10,
            'ready_at' => Carbon::parse('2026-08-15 12:10:00'),
        ]);

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders/estimate', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [['menu_item_id' => $this->lavash->id, 'quantity' => 1]],
        ])->assertOk()
            ->assertJsonPath('estimate.queue_minutes', 10)
            ->assertJsonPath('estimate.prep_minutes', 4)
            // 12:10 (navbat) + 4 daqiqa
            ->assertJsonPath('estimate.ready_at', '2026-08-15T12:14:00.000000Z');
    }

    public function test_only_the_last_order_in_the_queue_counts(): void
    {
        foreach ([5, 20, 12] as $minutes) {
            Order::factory()->status(OrderStatus::Paid)->create([
                'restaurant_id' => $this->restaurant->id,
                'prep_minutes' => $minutes,
                'ready_at' => Carbon::parse('2026-08-15 12:00:00')->addMinutes($minutes),
            ]);
        }

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders/estimate', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [['menu_item_id' => $this->lavash->id, 'quantity' => 1]],
        ])->assertOk()
            ->assertJsonPath('estimate.queue_minutes', 20)
            ->assertJsonPath('estimate.ready_at', '2026-08-15T12:24:00.000000Z');
    }

    public function test_a_queue_that_is_running_late_does_not_promise_the_past(): void
    {
        Order::factory()->status(OrderStatus::Preparing)->create([
            'restaurant_id' => $this->restaurant->id,
            'prep_minutes' => 10,
            'ready_at' => Carbon::parse('2026-08-15 11:30:00'),
        ]);

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders/estimate', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [['menu_item_id' => $this->lavash->id, 'quantity' => 1]],
        ])->assertOk()
            ->assertJsonPath('estimate.queue_minutes', 0)
            ->assertJsonPath('estimate.ready_at', '2026-08-15T12:04:00.000000Z');
    }

    public function test_finished_and_unpaid_orders_do_not_occupy_the_kitchen(): void
    {
        foreach ([OrderStatus::Pending, OrderStatus::Ready, OrderStatus::PickedUp, OrderStatus::Expired] as $status) {
            Order::factory()->status($status)->create([
                'restaurant_id' => $this->restaurant->id,
                'prep_minutes' => 30,
                'ready_at' => Carbon::parse('2026-08-15 13:00:00'),
            ]);
        }

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders/estimate', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [['menu_item_id' => $this->lavash->id, 'quantity' => 1]],
        ])->assertOk()->assertJsonPath('estimate.queue_minutes', 0);
    }

    public function test_another_restaurants_queue_is_irrelevant(): void
    {
        $other = Restaurant::factory()->create();
        Order::factory()->status(OrderStatus::Paid)->create([
            'restaurant_id' => $other->id,
            'prep_minutes' => 45,
            'ready_at' => Carbon::parse('2026-08-15 12:45:00'),
        ]);

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders/estimate', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [['menu_item_id' => $this->lavash->id, 'quantity' => 1]],
        ])->assertOk()->assertJsonPath('estimate.queue_minutes', 0);
    }

    public function test_paying_books_the_order_into_the_queue(): void
    {
        Order::factory()->status(OrderStatus::Paid)->create([
            'restaurant_id' => $this->restaurant->id,
            'prep_minutes' => 10,
            'ready_at' => Carbon::parse('2026-08-15 12:10:00'),
        ]);

        $order = $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [['menu_item_id' => $this->lavash->id, 'quantity' => 2]],
        ])->assertCreated()
            // Toʻlanmagan buyurtma navbatda joy egallamaydi.
            ->assertJsonPath('order.ready_at', null)
            ->assertJsonPath('order.estimated_ready_at', '2026-08-15T12:16:00.000000Z')
            ->json('order');

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/orders/{$order['id']}/pay")
            ->assertOk()
            ->assertJsonPath('order.ready_at', '2026-08-15T12:16:00.000000Z')
            ->assertJsonPath('order.estimated_ready_at', null);

        $this->assertSame(
            '2026-08-15 12:16:00',
            Order::find($order['id'])->ready_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_a_second_customer_is_queued_behind_the_first(): void
    {
        $first = $this->placeAndPay($this->customer, quantity: 1);

        $second = $this->placeAndPay(User::factory()->customer()->create(), quantity: 1);

        $this->assertSame('2026-08-15 12:04:00', $first->ready_at->format('Y-m-d H:i:s'));
        // Ikkinchi mijoz birinchisining orqasida: 12:04 + 4 daqiqa
        $this->assertSame('2026-08-15 12:08:00', $second->ready_at->format('Y-m-d H:i:s'));
    }

    public function test_the_estimate_shrinks_again_once_the_kitchen_frees_up(): void
    {
        $paid = $this->placeAndPay($this->customer, quantity: 5);

        // 4 + 2*4 = 12 daqiqa
        $this->assertSame('2026-08-15 12:12:00', $paid->ready_at->format('Y-m-d H:i:s'));

        $queue = app(KitchenQueue::class);

        $this->assertSame(12, $queue->estimate($this->restaurant, 0)['queue_minutes']);

        // Oshxona buyurtmani tayyor deb belgiladi — navbat boʻshadi.
        $paid->status = OrderStatus::Ready;
        $paid->save();

        $this->assertSame(0, $queue->estimate($this->restaurant, 0)['queue_minutes']);
    }

    public function test_an_estimate_is_refused_for_a_cart_that_could_not_be_ordered(): void
    {
        $this->lavash->update(['is_available' => false]);

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders/estimate', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [['menu_item_id' => $this->lavash->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.menu_item_id');

        $this->assertSame(0, Order::count());
    }

    public function test_only_customers_may_ask_for_an_estimate(): void
    {
        $this->actingAs(User::factory()->staff()->create(), 'sanctum')
            ->postJson('/api/orders/estimate', [
                'restaurant_id' => $this->restaurant->id,
                'items' => [['menu_item_id' => $this->lavash->id, 'quantity' => 1]],
            ])->assertForbidden();
    }

    private function placeAndPay(User $customer, int $quantity): Order
    {
        $order = $this->actingAs($customer, 'sanctum')->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [['menu_item_id' => $this->lavash->id, 'quantity' => $quantity]],
        ])->assertCreated()->json('order');

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/orders/{$order['id']}/pay")
            ->assertOk();

        return Order::findOrFail($order['id']);
    }
}

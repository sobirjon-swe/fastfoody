<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\KitchenQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 5-bosqich: oshxona taom tugaganini bildiradi, mijoz esa almashtirish yoki pul
 * qaytarish orasidan tanlaydi.
 */
class OutOfStockFlowTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $staff;

    private User $customer;

    private MenuItem $lavash;

    private MenuItem $burger;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-15 12:00:00');

        $this->restaurant = Restaurant::factory()->create();
        $this->staff = User::factory()->staff($this->restaurant)->create();
        $this->customer = User::factory()->customer()->create();

        $this->lavash = MenuItem::factory()->for($this->restaurant)->create([
            'name' => 'Lavash', 'price' => 32000, 'base_prep_minutes' => 4, 'extra_prep_minutes' => 2,
        ]);
        $this->burger = MenuItem::factory()->for($this->restaurant)->create([
            'name' => 'Gamburger', 'price' => 28000, 'base_prep_minutes' => 3, 'extra_prep_minutes' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<int, array{0: MenuItem, 1: int}>  $lines
     */
    private function paidOrder(array $lines): Order
    {
        $order = $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id,
            'items' => array_map(fn ($line) => [
                'menu_item_id' => $line[0]->id,
                'quantity' => $line[1],
            ], $lines),
        ])->assertCreated()->json('order');

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/orders/{$order['id']}/pay")
            ->assertOk();

        return Order::with('items')->findOrFail($order['id']);
    }

    public function test_staff_report_puts_the_order_in_front_of_the_customer(): void
    {
        $order = $this->paidOrder([[$this->lavash, 2], [$this->burger, 1]]);
        $lavashLine = $order->items->firstWhere('name', 'Lavash');

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/orders/{$order->id}/out-of-stock", [
                'order_item_id' => $lavashLine->id,
            ])
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::AwaitingCustomerDecision->value)
            ->assertJsonPath('order.ready_at', null);

        $order->refresh();

        $this->assertSame(OrderStatus::AwaitingCustomerDecision, $order->status);
        $this->assertNotNull($order->items()->find($lavashLine->id)->out_of_stock_at);

        // Taxtadan yoʻqolib qolmaydi: oshxona uning kutib turganini koʻradi,
        // lekin uni oldinga sura olmaydi.
        $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/staff/orders')
            ->assertOk()
            ->assertJsonPath('orders.0.id', $order->id)
            ->assertJsonPath('orders.0.next_statuses', [])
            ->assertJsonPath('orders.0.can_report_out_of_stock', false);
        // Navbatdan chiqdi: mijoz javob bermaguncha oshxona uni pishirmaydi.
        $this->assertSame(0, app(KitchenQueue::class)->estimate($this->restaurant, 0)['queue_minutes']);
    }

    public function test_the_customer_sees_which_item_ran_out(): void
    {
        $order = $this->paidOrder([[$this->lavash, 1]]);
        $line = $order->items->first();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/orders/{$order->id}/out-of-stock", ['order_item_id' => $line->id])
            ->assertOk();

        $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::AwaitingCustomerDecision->value)
            ->assertJsonPath('order.items.0.is_out_of_stock', true);
    }

    public function test_the_item_can_also_be_taken_off_the_menu(): void
    {
        $order = $this->paidOrder([[$this->lavash, 1]]);

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/orders/{$order->id}/out-of-stock", [
                'order_item_id' => $order->items->first()->id,
                'mark_menu_item_unavailable' => true,
            ])
            ->assertOk();

        $this->assertFalse($this->lavash->fresh()->is_available);

        // Yangi mijoz endi uni buyurtma qila olmaydi.
        $this->actingAs(User::factory()->customer()->create(), 'sanctum')
            ->postJson('/api/orders', [
                'restaurant_id' => $this->restaurant->id,
                'items' => [['menu_item_id' => $this->lavash->id, 'quantity' => 1]],
            ])->assertStatus(422);
    }

    public function test_the_customer_can_replace_the_missing_item(): void
    {
        $order = $this->paidOrder([[$this->lavash, 2]]);
        $line = $order->items->first();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/orders/{$order->id}/out-of-stock", ['order_item_id' => $line->id])
            ->assertOk();

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/orders/{$order->id}/replace-item", [
                'order_item_id' => $line->id,
                'menu_item_id' => $this->burger->id,
            ])
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::Paid->value)
            // 2 gamburger = 56 000, tayyorlash 3 + 1 = 4 daqiqa
            ->assertJsonPath('order.total_price', '56000.00')
            ->assertJsonPath('order.prep_minutes', 4)
            ->assertJsonPath('order.items.0.name', 'Gamburger')
            ->assertJsonPath('order.items.0.quantity', 2)
            ->assertJsonPath('order.items.0.is_out_of_stock', false)
            // Navbatga qaytdi va yangi tarkib boʻyicha vaqt oldi.
            ->assertJsonPath('order.ready_at', '2026-08-15T12:04:00.000000Z');
    }

    public function test_a_replacement_must_be_available_and_from_the_same_restaurant(): void
    {
        $order = $this->paidOrder([[$this->lavash, 1]]);
        $line = $order->items->first();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/orders/{$order->id}/out-of-stock", ['order_item_id' => $line->id])
            ->assertOk();

        $foreign = MenuItem::factory()->create();

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/orders/{$order->id}/replace-item", [
                'order_item_id' => $line->id,
                'menu_item_id' => $foreign->id,
            ])->assertStatus(422)->assertJsonValidationErrors('menu_item_id');

        $this->burger->update(['is_available' => false]);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/orders/{$order->id}/replace-item", [
                'order_item_id' => $line->id,
                'menu_item_id' => $this->burger->id,
            ])->assertStatus(422)->assertJsonValidationErrors('menu_item_id');

        $this->assertSame(OrderStatus::AwaitingCustomerDecision, $order->fresh()->status);
    }

    public function test_the_customer_can_cancel_and_get_the_money_back(): void
    {
        $order = $this->paidOrder([[$this->lavash, 1]]);
        $line = $order->items->first();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/orders/{$order->id}/out-of-stock", ['order_item_id' => $line->id])
            ->assertOk();

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::CancelledOutOfStock->value)
            ->assertJsonPath('order.ready_at', null);

        $order->refresh();

        $this->assertNotNull($order->refunded_at);
        $this->assertSame(0, app(KitchenQueue::class)->estimate($this->restaurant, 0)['queue_minutes']);
    }

    public function test_a_customer_cannot_cancel_an_order_that_is_simply_cooking(): void
    {
        $order = $this->paidOrder([[$this->lavash, 1]]);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/orders/{$order->id}/cancel")
            ->assertStatus(422);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertNull($order->fresh()->refunded_at);
    }

    public function test_a_blocked_order_is_not_moved_along_by_the_kitchen(): void
    {
        $order = $this->paidOrder([[$this->lavash, 1]]);

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/orders/{$order->id}/out-of-stock", [
                'order_item_id' => $order->items->first()->id,
            ])->assertOk();

        $this->actingAs($this->staff, 'sanctum')
            ->patchJson("/api/staff/orders/{$order->id}", ['status' => OrderStatus::Preparing->value])
            ->assertStatus(422);

        $this->assertSame(OrderStatus::AwaitingCustomerDecision, $order->fresh()->status);
    }

    public function test_out_of_stock_cannot_be_reported_twice_or_after_pickup(): void
    {
        $order = $this->paidOrder([[$this->lavash, 1]]);
        $line = $order->items->first();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/orders/{$order->id}/out-of-stock", ['order_item_id' => $line->id])
            ->assertOk();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/orders/{$order->id}/out-of-stock", ['order_item_id' => $line->id])
            ->assertStatus(422);

        $picked = Order::factory()->status(OrderStatus::PickedUp)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        $pickedLine = OrderItem::factory()->for($picked)->create();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/orders/{$picked->id}/out-of-stock", ['order_item_id' => $pickedLine->id])
            ->assertStatus(422);
    }

    public function test_another_restaurants_staff_cannot_report_anything(): void
    {
        $order = $this->paidOrder([[$this->lavash, 1]]);
        $otherStaff = User::factory()->staff()->create();

        $this->actingAs($otherStaff, 'sanctum')
            ->postJson("/api/staff/orders/{$order->id}/out-of-stock", [
                'order_item_id' => $order->items->first()->id,
            ])->assertNotFound();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_another_customer_cannot_resolve_someone_elses_order(): void
    {
        $order = $this->paidOrder([[$this->lavash, 1]]);
        $line = $order->items->first();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/orders/{$order->id}/out-of-stock", ['order_item_id' => $line->id])
            ->assertOk();

        $stranger = User::factory()->customer()->create();

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/orders/{$order->id}/cancel")
            ->assertNotFound();

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/orders/{$order->id}/replace-item", [
                'order_item_id' => $line->id,
                'menu_item_id' => $this->burger->id,
            ])->assertNotFound();

        $this->assertSame(OrderStatus::AwaitingCustomerDecision, $order->fresh()->status);
    }

    public function test_the_next_customer_is_not_delayed_by_a_blocked_order(): void
    {
        $blocked = $this->paidOrder([[$this->lavash, 5]]);

        // 4 + 2*4 = 12 daqiqa navbat
        $this->assertSame(12, app(KitchenQueue::class)->estimate($this->restaurant, 0)['queue_minutes']);

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/staff/orders/{$blocked->id}/out-of-stock", [
                'order_item_id' => $blocked->items->first()->id,
            ])->assertOk();

        $this->actingAs(User::factory()->customer()->create(), 'sanctum')
            ->postJson('/api/orders/estimate', [
                'restaurant_id' => $this->restaurant->id,
                'items' => [['menu_item_id' => $this->burger->id, 'quantity' => 1]],
            ])->assertOk()->assertJsonPath('estimate.queue_minutes', 0);
    }
}

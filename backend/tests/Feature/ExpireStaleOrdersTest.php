<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\KitchenQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExpireStaleOrdersTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-15 12:00:00');
        $this->restaurant = Restaurant::factory()->create();

        config([
            'fastfoody.expiry.unpaid_after_minutes' => 15,
            'fastfoody.expiry.uncollected_after_minutes' => 30,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function order(OrderStatus $status, array $attributes = []): Order
    {
        return Order::factory()->status($status)->create([
            'restaurant_id' => $this->restaurant->id,
            ...$attributes,
        ]);
    }

    public function test_an_abandoned_unpaid_order_expires(): void
    {
        $old = $this->order(OrderStatus::Pending, ['created_at' => Carbon::parse('2026-08-15 11:40:00')]);
        $fresh = $this->order(OrderStatus::Pending, ['created_at' => Carbon::parse('2026-08-15 11:50:00')]);

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(OrderStatus::Expired, $old->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $fresh->fresh()->status);
    }

    public function test_food_that_was_never_collected_expires(): void
    {
        $cold = $this->order(OrderStatus::Ready, ['ready_at' => Carbon::parse('2026-08-15 11:20:00')]);
        $recent = $this->order(OrderStatus::Ready, ['ready_at' => Carbon::parse('2026-08-15 11:45:00')]);

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(OrderStatus::Expired, $cold->fresh()->status);
        $this->assertSame(OrderStatus::Ready, $recent->fresh()->status);
    }

    public function test_orders_that_are_being_worked_on_are_never_touched(): void
    {
        $untouched = [
            $this->order(OrderStatus::Paid, ['ready_at' => Carbon::parse('2026-08-15 10:00:00')]),
            $this->order(OrderStatus::Preparing, ['ready_at' => Carbon::parse('2026-08-15 10:00:00')]),
            $this->order(OrderStatus::AwaitingCustomerDecision),
            $this->order(OrderStatus::PickedUp, ['ready_at' => Carbon::parse('2026-08-15 09:00:00')]),
            $this->order(OrderStatus::CancelledOutOfStock),
        ];

        $this->artisan('orders:expire')->assertSuccessful();

        foreach ($untouched as $order) {
            $status = $order->status;

            $this->assertSame($status, $order->fresh()->status);
        }
    }

    public function test_the_windows_come_from_configuration(): void
    {
        config(['fastfoody.expiry.unpaid_after_minutes' => 120]);

        $order = $this->order(OrderStatus::Pending, ['created_at' => Carbon::parse('2026-08-15 11:40:00')]);

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_staff_can_close_an_order_the_customer_never_came_for(): void
    {
        $staff = User::factory()->staff($this->restaurant)->create();
        $ready = $this->order(OrderStatus::Ready, ['ready_at' => Carbon::parse('2026-08-15 11:55:00')]);

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/staff/orders')
            ->assertOk()
            ->assertJsonPath('orders.0.next_statuses', [
                OrderStatus::PickedUp->value,
                OrderStatus::Expired->value,
            ]);

        $this->actingAs($staff, 'sanctum')
            ->patchJson("/api/staff/orders/{$ready->id}", ['status' => OrderStatus::Expired->value])
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::Expired->value);

        $this->assertSame(OrderStatus::Expired, $ready->fresh()->status);
    }

    public function test_a_cooking_order_cannot_be_expired_by_hand(): void
    {
        $staff = User::factory()->staff($this->restaurant)->create();
        $preparing = $this->order(OrderStatus::Preparing);

        $this->actingAs($staff, 'sanctum')
            ->patchJson("/api/staff/orders/{$preparing->id}", ['status' => OrderStatus::Expired->value])
            ->assertStatus(422);

        $this->assertSame(OrderStatus::Preparing, $preparing->fresh()->status);
    }

    public function test_an_expired_order_holds_no_place_in_the_queue(): void
    {
        $this->order(OrderStatus::Pending, [
            'created_at' => Carbon::parse('2026-08-15 11:40:00'),
            'prep_minutes' => 30,
        ]);

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(0, app(KitchenQueue::class)->estimate($this->restaurant, 0)['queue_minutes']);
    }
}

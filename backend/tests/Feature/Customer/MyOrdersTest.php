<?php

namespace Tests\Feature\Customer;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_sees_only_their_own_orders(): void
    {
        $customer = User::factory()->customer()->create();
        Order::factory()->count(2)->create(['customer_id' => $customer->id]);
        Order::factory()->create();

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(2, 'orders');
    }

    public function test_another_customers_order_is_not_found(): void
    {
        $customer = User::factory()->customer()->create();
        $foreign = Order::factory()->create();

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/orders/{$foreign->id}")
            ->assertNotFound();

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/orders/{$foreign->id}/pay")
            ->assertNotFound();

        $this->assertSame(OrderStatus::Pending, $foreign->fresh()->status);
    }

    public function test_an_order_can_be_opened_with_its_items(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        OrderItem::factory()->for($order)->create(['name' => 'Lavash', 'quantity' => 2]);

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('order.id', $order->id)
            ->assertJsonCount(1, 'order.items')
            ->assertJsonPath('order.items.0.name', 'Lavash')
            ->assertJsonPath('order.items.0.quantity', 2)
            ->assertJsonPath('order.restaurant.id', $order->restaurant_id);
    }

    public function test_paying_marks_the_order_as_paid(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/orders/{$order->id}/pay")
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::Paid->value);

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
    }

    public function test_an_order_cannot_be_paid_twice(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->status(OrderStatus::Paid)->create(['customer_id' => $customer->id]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/orders/{$order->id}/pay")
            ->assertStatus(409);
    }

    public function test_a_not_found_response_does_not_leak_internals(): void
    {
        $customer = User::factory()->customer()->create();

        $response = $this->actingAs($customer, 'sanctum')->getJson('/api/orders/999999');

        $response->assertNotFound()
            ->assertJsonPath('message', 'Soʻralgan maʼlumot topilmadi.');

        $this->assertStringNotContainsString('App\\Models', $response->getContent());
    }

    public function test_guests_cannot_list_orders(): void
    {
        $this->getJson('/api/orders')->assertUnauthorized();
    }
}

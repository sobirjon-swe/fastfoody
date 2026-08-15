<?php

namespace Tests\Feature\Staff;

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

class OrderBoardTest extends TestCase
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

    private function order(OrderStatus $status, array $attributes = []): Order
    {
        return Order::factory()->status($status)->create([
            'restaurant_id' => $this->restaurant->id,
            ...$attributes,
        ]);
    }

    public function test_the_board_shows_the_orders_that_need_attention(): void
    {
        $paid = $this->order(OrderStatus::Paid, ['paid_at' => Carbon::parse('2026-08-15 12:00:00')]);
        $preparing = $this->order(OrderStatus::Preparing, ['paid_at' => Carbon::parse('2026-08-15 11:00:00')]);
        $ready = $this->order(OrderStatus::Ready, ['paid_at' => Carbon::parse('2026-08-15 11:30:00')]);
        $this->order(OrderStatus::Pending);
        $this->order(OrderStatus::PickedUp);

        $response = $this->actingAs($this->staff, 'sanctum')->getJson('/api/staff/orders');

        $response->assertOk()->assertJsonCount(3, 'orders');

        // Eng eski toʻlov birinchi: oshxona navbat tartibida ishlaydi.
        $this->assertSame(
            [$preparing->id, $ready->id, $paid->id],
            array_column($response->json('orders'), 'id'),
        );
    }

    public function test_the_board_can_be_filtered_by_status(): void
    {
        $this->order(OrderStatus::Paid);
        $pickedUp = $this->order(OrderStatus::PickedUp);

        $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/staff/orders?status='.OrderStatus::PickedUp->value)
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.id', $pickedUp->id);
    }

    public function test_an_order_carries_its_items_customer_and_next_steps(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Aziz', 'phone' => '+998901112233']);
        $order = $this->order(OrderStatus::Paid, ['customer_id' => $customer->id]);
        OrderItem::factory()->for($order)->create(['name' => 'Lavash', 'quantity' => 2]);

        $this->actingAs($this->staff, 'sanctum')
            ->getJson("/api/staff/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('order.customer.name', 'Aziz')
            ->assertJsonPath('order.customer.phone', '+998901112233')
            ->assertJsonPath('order.items.0.name', 'Lavash')
            ->assertJsonPath('order.next_statuses', [OrderStatus::Preparing->value]);
    }

    public function test_staff_walk_an_order_through_the_whole_chain(): void
    {
        $order = $this->order(OrderStatus::Paid);

        foreach ([OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::PickedUp] as $next) {
            $this->actingAs($this->staff, 'sanctum')
                ->patchJson("/api/staff/orders/{$order->id}", ['status' => $next->value])
                ->assertOk()
                ->assertJsonPath('order.status', $next->value);

            $this->assertSame($next, $order->fresh()->status);
        }

        $this->assertSame([], $order->fresh()->status->nextForStaff());
    }

    public function test_a_step_that_skips_or_goes_backwards_is_refused(): void
    {
        $paid = $this->order(OrderStatus::Paid);

        // «tayyor» ga sakrash mumkin emas — avval tayyorlanishi kerak.
        $this->actingAs($this->staff, 'sanctum')
            ->patchJson("/api/staff/orders/{$paid->id}", ['status' => OrderStatus::Ready->value])
            ->assertStatus(422);

        $ready = $this->order(OrderStatus::Ready);

        $this->actingAs($this->staff, 'sanctum')
            ->patchJson("/api/staff/orders/{$ready->id}", ['status' => OrderStatus::Preparing->value])
            ->assertStatus(422);

        $this->assertSame(OrderStatus::Paid, $paid->fresh()->status);
        $this->assertSame(OrderStatus::Ready, $ready->fresh()->status);
    }

    public function test_an_unpaid_order_cannot_be_started_by_the_kitchen(): void
    {
        $pending = $this->order(OrderStatus::Pending);

        $this->actingAs($this->staff, 'sanctum')
            ->patchJson("/api/staff/orders/{$pending->id}", ['status' => OrderStatus::Preparing->value])
            ->assertStatus(422);

        $this->assertSame(OrderStatus::Pending, $pending->fresh()->status);
    }

    public function test_the_kitchen_cannot_set_a_status_that_is_not_its_own(): void
    {
        $order = $this->order(OrderStatus::Paid);

        // Toʻlov mijozning ishi, bekor qilish esa «mahsulot yoʻq» oqimining
        // bir qismi — ikkalasi ham oshxonaning tugmasi emas.
        foreach ([OrderStatus::Pending, OrderStatus::CancelledOutOfStock] as $status) {
            $this->actingAs($this->staff, 'sanctum')
                ->patchJson("/api/staff/orders/{$order->id}", ['status' => $status->value])
                ->assertStatus(422)
                ->assertJsonValidationErrors('status');
        }

        // «muddati_otdi» oshxonaning holati, lekin faqat tayyor buyurtma uchun.
        $this->actingAs($this->staff, 'sanctum')
            ->patchJson("/api/staff/orders/{$order->id}", ['status' => OrderStatus::Expired->value])
            ->assertStatus(422);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_another_restaurants_order_is_invisible(): void
    {
        $foreign = Order::factory()->status(OrderStatus::Paid)->create();

        $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/staff/orders')
            ->assertOk()
            ->assertJsonCount(0, 'orders');

        $this->actingAs($this->staff, 'sanctum')
            ->getJson("/api/staff/orders/{$foreign->id}")
            ->assertNotFound();

        $this->actingAs($this->staff, 'sanctum')
            ->patchJson("/api/staff/orders/{$foreign->id}", ['status' => OrderStatus::Preparing->value])
            ->assertNotFound();

        $this->assertSame(OrderStatus::Paid, $foreign->fresh()->status);
    }

    public function test_marking_an_order_ready_frees_the_queue_for_the_next_customer(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');

        $menuItem = MenuItem::factory()->for($this->restaurant)->create([
            'base_prep_minutes' => 10,
            'extra_prep_minutes' => 0,
        ]);

        $customer = User::factory()->customer()->create();
        $placed = $this->actingAs($customer, 'sanctum')->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [['menu_item_id' => $menuItem->id, 'quantity' => 1]],
        ])->json('order');

        $this->actingAs($customer, 'sanctum')->postJson("/api/orders/{$placed['id']}/pay")->assertOk();

        $queue = app(KitchenQueue::class);
        $this->assertSame(10, $queue->estimate($this->restaurant, 0)['queue_minutes']);

        $this->actingAs($this->staff, 'sanctum')
            ->patchJson("/api/staff/orders/{$placed['id']}", ['status' => OrderStatus::Preparing->value])
            ->assertOk();

        // Tayyorlanayotgan buyurtma hali navbatda.
        $this->assertSame(10, $queue->estimate($this->restaurant, 0)['queue_minutes']);

        $this->actingAs($this->staff, 'sanctum')
            ->patchJson("/api/staff/orders/{$placed['id']}", ['status' => OrderStatus::Ready->value])
            ->assertOk();

        // Tayyor boʻlgani navbatdan chiqadi — keyingi mijoz kutmaydi.
        $this->assertSame(0, $queue->estimate($this->restaurant, 0)['queue_minutes']);

        Carbon::setTestNow();
    }

    public function test_customers_and_super_admins_cannot_use_the_board(): void
    {
        $order = $this->order(OrderStatus::Paid);

        foreach ([User::factory()->customer()->create(), User::factory()->superAdmin()->create()] as $user) {
            $this->actingAs($user, 'sanctum')->getJson('/api/staff/orders')->assertForbidden();
            $this->actingAs($user, 'sanctum')
                ->patchJson("/api/staff/orders/{$order->id}", ['status' => OrderStatus::Preparing->value])
                ->assertForbidden();
        }
    }

    public function test_guests_cannot_use_the_board(): void
    {
        $this->getJson('/api/staff/orders')->assertUnauthorized();
    }
}

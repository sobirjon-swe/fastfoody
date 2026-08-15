<?php

namespace Tests\Feature\Customer;

use App\Enums\OrderStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\PickupCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PickupCodeTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $customer;

    private MenuItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-15 12:00:00');

        $this->restaurant = Restaurant::factory()->create();
        $this->customer = User::factory()->customer()->create();
        $this->item = MenuItem::factory()->for($this->restaurant)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function placeOrder(): array
    {
        return $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ])->assertCreated()->json('order');
    }

    public function test_a_code_is_issued_when_the_order_is_paid(): void
    {
        $order = $this->placeOrder();

        // Toʻlanmagan buyurtmada kod yoʻq.
        $this->assertNull($order['pickup_code']);

        $paid = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/orders/{$order['id']}/pay")
            ->assertOk()
            ->json('order');

        $this->assertMatchesRegularExpression('/^\d{4}$/', $paid['pickup_code']);
    }

    public function test_two_open_orders_of_one_restaurant_never_share_a_code(): void
    {
        $codes = [];

        for ($i = 0; $i < 5; $i++) {
            $order = $this->placeOrder();
            $codes[] = $this->actingAs($this->customer, 'sanctum')
                ->postJson("/api/orders/{$order['id']}/pay")
                ->assertOk()
                ->json('order.pickup_code');
        }

        $this->assertSame($codes, array_unique($codes));
    }

    public function test_an_open_orders_code_is_never_handed_out_again(): void
    {
        Order::factory()->status(OrderStatus::Paid)->create([
            'restaurant_id' => $this->restaurant->id,
            'pickup_code' => '1234',
        ]);

        $generator = app(PickupCode::class);
        $seen = [];

        for ($i = 0; $i < 100; $i++) {
            $seen[] = $generator->generateFor($this->restaurant);
        }

        $this->assertNotContains('1234', $seen);
    }

    public function test_a_finished_order_does_not_block_the_pool(): void
    {
        Order::factory()->status(OrderStatus::PickedUp)->create([
            'restaurant_id' => $this->restaurant->id,
            'pickup_code' => '1234',
        ]);

        // Tugagan buyurtma kodni band qilmaydi, shuning uchun oddiy 4 raqamli
        // kod beriladi (zaxira 6 belgili variantga oʻtilmaydi).
        $this->assertMatchesRegularExpression(
            '/^\d{4}$/',
            app(PickupCode::class)->generateFor($this->restaurant),
        );
    }

    public function test_the_kitchen_can_find_an_order_by_its_code(): void
    {
        $staff = User::factory()->staff($this->restaurant)->create();
        $order = $this->placeOrder();
        $code = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/orders/{$order['id']}/pay")
            ->json('order.pickup_code');

        $this->actingAs($staff, 'sanctum')
            ->getJson("/api/staff/orders?code={$code}")
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.id', $order['id'])
            ->assertJsonPath('orders.0.pickup_code', $code);
    }

    public function test_a_code_search_also_finds_orders_that_are_already_finished(): void
    {
        $staff = User::factory()->staff($this->restaurant)->create();
        Order::factory()->status(OrderStatus::PickedUp)->create([
            'restaurant_id' => $this->restaurant->id,
            'pickup_code' => '4321',
        ]);

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/staff/orders?code=4321')
            ->assertOk()
            ->assertJsonCount(1, 'orders');
    }

    public function test_a_code_of_another_restaurant_is_not_found(): void
    {
        $staff = User::factory()->staff($this->restaurant)->create();
        Order::factory()->status(OrderStatus::Paid)->create(['pickup_code' => '5555']);

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/staff/orders?code=5555')
            ->assertOk()
            ->assertJsonCount(0, 'orders');
    }
}

<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * 7-bosqich (2-qism): buyurtma holati oʻzgarganda mijozga Telegram orqali
 * xabar boradi. Ilova yopiq boʻlsa ham «buyurtmangiz tayyor» xabari keladi.
 */
class TelegramNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $staff;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-15 12:00:00');
        config()->set('services.telegram.bot_token', '123456:TEST-BOT-TOKEN');
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->restaurant = Restaurant::factory()->create();
        $this->staff = User::factory()->staff($this->restaurant)->create();

        $this->customer = User::factory()->customer()->create();
        $this->customer->telegram_id = 777123;
        $this->customer->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function paidOrder(): Order
    {
        $order = Order::factory()
            ->for($this->customer, 'customer')
            ->for($this->restaurant)
            ->status(OrderStatus::Paid)
            ->create(['pickup_code' => '4821', 'ready_at' => now()->addMinutes(14)]);

        $item = MenuItem::factory()->for($this->restaurant)->create(['name' => 'Lavash']);
        OrderItem::factory()->for($order)->for($item, 'menuItem')->create(['name' => 'Lavash']);

        return $order;
    }

    /** @return array<int, string> */
    private function sentMessages(): array
    {
        return Http::recorded()
            ->map(fn (array $pair) => $pair[0])
            ->filter(fn (Request $request) => str_contains($request->url(), 'sendMessage'))
            ->map(fn (Request $request) => (string) $request->data()['text'])
            ->values()
            ->all();
    }

    public function test_customer_is_notified_with_the_pickup_code_when_the_order_is_ready(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->staff)
            ->patchJson("/api/staff/orders/{$order->id}", ['status' => OrderStatus::Preparing->value])
            ->assertOk();

        $this->actingAs($this->staff)
            ->patchJson("/api/staff/orders/{$order->id}", ['status' => OrderStatus::Ready->value])
            ->assertOk();

        $messages = $this->sentMessages();

        // «Tayyorlanmoqda» oraliq holati uchun xabar yuborilmaydi.
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('tayyor', $messages[0]);
        $this->assertStringContainsString('4821', $messages[0]);

        Http::assertSent(fn (Request $request) => $request->data()['chat_id'] === 777123);
    }

    public function test_payment_notification_carries_the_code_and_the_ready_time(): void
    {
        $menuItem = MenuItem::factory()->for($this->restaurant)->create([
            'base_prep_minutes' => 5, 'extra_prep_minutes' => 0,
        ]);

        $order = $this->actingAs($this->customer)
            ->postJson('/api/orders', [
                'restaurant_id' => $this->restaurant->id,
                'items' => [['menu_item_id' => $menuItem->id, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('order.id');

        $this->actingAs($this->customer)->postJson("/api/orders/{$order}/pay")->assertOk();

        $messages = $this->sentMessages();

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('qabul qilindi', $messages[0]);
        // 12:00 UTC + 5 daqiqa = Toshkent vaqti bilan 17:05.
        $this->assertStringContainsString('17:05', $messages[0]);
    }

    public function test_out_of_stock_report_notifies_the_customer(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->staff)
            ->postJson("/api/staff/orders/{$order->id}/out-of-stock", [
                'order_item_id' => $order->items()->value('id'),
            ])
            ->assertOk();

        $messages = $this->sentMessages();

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('tugab qoldi', $messages[0]);
    }

    public function test_expired_order_notifies_the_customer(): void
    {
        $order = $this->paidOrder();
        $order->status = OrderStatus::Ready;
        $order->save();

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        Carbon::setTestNow(now()->addHours(2));
        $this->artisan('orders:expire')->assertSuccessful();

        $messages = $this->sentMessages();

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('muddati', $messages[0]);
    }

    public function test_customers_without_telegram_are_not_notified(): void
    {
        $this->customer->telegram_id = null;
        $this->customer->save();

        $order = $this->paidOrder();

        $this->actingAs($this->staff)
            ->patchJson("/api/staff/orders/{$order->id}", ['status' => OrderStatus::Preparing->value])
            ->assertOk();

        Http::assertNothingSent();
    }

    public function test_nothing_is_sent_when_the_bot_token_is_missing(): void
    {
        config()->set('services.telegram.bot_token', null);

        $order = $this->paidOrder();

        $this->actingAs($this->staff)
            ->patchJson("/api/staff/orders/{$order->id}", ['status' => OrderStatus::Preparing->value])
            ->assertOk();

        Http::assertNothingSent();
    }

    public function test_a_failing_telegram_api_does_not_break_the_request(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 500)]);

        $order = $this->paidOrder();

        $this->actingAs($this->staff)
            ->patchJson("/api/staff/orders/{$order->id}", ['status' => OrderStatus::Preparing->value])
            ->assertOk();

        $this->actingAs($this->staff)
            ->patchJson("/api/staff/orders/{$order->id}", ['status' => OrderStatus::Ready->value])
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::Ready->value);
    }
}

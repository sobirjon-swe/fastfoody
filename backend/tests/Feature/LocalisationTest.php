<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * 8-bosqich: interfeys toʻrt tilda — oʻzbekcha lotin, oʻzbekcha kirill,
 * ruscha va inglizcha.
 */
class LocalisationTest extends TestCase
{
    use RefreshDatabase;

    private const CLOSED_ORDER_PATH = '/api/orders';

    private Restaurant $restaurant;

    private User $customer;

    private MenuItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        // Oshxona yopiq boʻlgan payt: xato xabari har bir tilda tekshiriladi.
        Carbon::setTestNow('2026-08-15 00:00:00');

        $this->restaurant = Restaurant::factory()->create([
            'opens_at' => '09:00:00',
            'closes_at' => '22:00:00',
        ]);
        $this->customer = User::factory()->customer()->create();
        $this->item = MenuItem::factory()->for($this->restaurant)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function cart(): array
    {
        return [
            'restaurant_id' => $this->restaurant->id,
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ];
    }

    public function test_the_browser_language_decides_the_response_language(): void
    {
        $this->actingAs($this->customer)
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->postJson(self::CLOSED_ORDER_PATH, $this->cart())
            ->assertStatus(422)
            ->assertJsonPath('errors.restaurant_id.0', 'The restaurant is closed right now. Opening hours: 09:00–22:00.');

        $this->app['auth']->forgetGuards();

        $this->actingAs($this->customer)
            ->withHeader('Accept-Language', 'uz-Cyrl')
            ->postJson(self::CLOSED_ORDER_PATH, $this->cart())
            ->assertStatus(422)
            ->assertJsonPath('errors.restaurant_id.0', 'Ошхона ҳозир ёпиқ. Иш вақти: 09:00–22:00.');
    }

    public function test_russian_is_supported_too(): void
    {
        $this->actingAs($this->customer)
            ->withHeader('Accept-Language', 'ru-RU,ru;q=0.9')
            ->postJson(self::CLOSED_ORDER_PATH, $this->cart())
            ->assertStatus(422)
            ->assertJsonPath('errors.restaurant_id.0', 'Заведение сейчас закрыто. Часы работы: 09:00–22:00.');
    }

    public function test_an_unsupported_browser_language_falls_back_to_uzbek(): void
    {
        $this->actingAs($this->customer)
            ->withHeader('Accept-Language', 'de-DE,fr;q=0.7')
            ->postJson(self::CLOSED_ORDER_PATH, $this->cart())
            ->assertStatus(422)
            ->assertJsonPath('errors.restaurant_id.0', 'Oshxona hozir yopiq. Ish vaqti: 09:00–22:00.');
    }

    public function test_the_saved_preference_beats_the_browser_language(): void
    {
        $this->customer->locale = 'uz_Cyrl';
        $this->customer->save();

        // Brauzer inglizcha, lekin foydalanuvchi kirillni tanlagan.
        $this->actingAs($this->customer)
            ->withHeader('Accept-Language', 'en-US')
            ->postJson(self::CLOSED_ORDER_PATH, $this->cart())
            ->assertStatus(422)
            ->assertJsonPath('errors.restaurant_id.0', 'Ошхона ҳозир ёпиқ. Иш вақти: 09:00–22:00.');
    }

    public function test_validation_messages_are_translated_too(): void
    {
        $this->actingAs($this->customer)
            ->withHeader('Accept-Language', 'en')
            ->postJson(self::CLOSED_ORDER_PATH, ['restaurant_id' => $this->restaurant->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    }

    public function test_the_language_can_be_changed_from_the_profile(): void
    {
        $this->actingAs($this->customer)
            ->patchJson('/api/auth/profile', ['locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('user.locale', 'en');

        $this->assertSame('en', $this->customer->fresh()->locale);
    }

    public function test_an_unknown_language_is_refused(): void
    {
        $this->actingAs($this->customer)
            ->patchJson('/api/auth/profile', ['locale' => 'fr'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('locale');
    }

    public function test_telegram_sign_in_picks_up_the_telegram_language(): void
    {
        config()->set('services.telegram.bot_token', '123456:TEST-BOT-TOKEN');

        $pairs = [
            'auth_date' => (string) now()->timestamp,
            'user' => json_encode(['id' => 991122, 'first_name' => 'Alyona', 'language_code' => 'ru']),
        ];
        ksort($pairs);
        $check = implode("\n", array_map(fn ($k, $v) => "{$k}={$v}", array_keys($pairs), $pairs));
        $secret = hash_hmac('sha256', '123456:TEST-BOT-TOKEN', 'WebAppData', true);
        $pairs['hash'] = hash_hmac('sha256', $check, $secret);

        $this->postJson('/api/auth/telegram', ['init_data' => http_build_query($pairs)])
            ->assertOk()
            ->assertJsonPath('user.locale', 'ru');
    }

    public function test_the_bot_writes_in_the_customers_language_not_the_staffs(): void
    {
        config()->set('services.telegram.bot_token', '123456:TEST-BOT-TOKEN');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->customer->telegram_id = 991133;
        $this->customer->locale = 'en';
        $this->customer->save();

        $staff = User::factory()->staff($this->restaurant)->create(['locale' => 'uz']);

        $order = Order::factory()
            ->for($this->customer, 'customer')
            ->for($this->restaurant)
            ->status(OrderStatus::Preparing)
            ->create(['pickup_code' => '4821']);

        // Xodim oʻzbek tilida ishlaydi, mijoz esa inglizcha xabar olishi kerak.
        $this->actingAs($staff)
            ->patchJson("/api/staff/orders/{$order->id}", ['status' => OrderStatus::Ready->value])
            ->assertOk();

        Http::assertSent(fn (Request $request) => str_contains(
            (string) $request->data()['text'],
            'Your order is ready! Pickup code: 4821.',
        ));
    }
}

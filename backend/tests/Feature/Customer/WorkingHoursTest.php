<?php

namespace Tests\Feature\Customer;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Ish vaqti endi bezak emas: yopiq oshxona buyurtma ham, tayyor boʻlish vaqti
 * bahosini ham bermaydi.
 *
 * Vaqtlar UTC'da saqlanadi, ish vaqti esa mahalliy soatda yoziladi
 * (config/fastfoody.php dagi `timezone`, sukut boʻyicha Asia/Tashkent = UTC+5).
 */
class WorkingHoursTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->customer()->create();
        config(['fastfoody.timezone' => 'Asia/Tashkent']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function restaurantOpen(string $opens, string $closes): Restaurant
    {
        return Restaurant::factory()->create(['opens_at' => $opens, 'closes_at' => $closes]);
    }

    private function order(Restaurant $restaurant): TestResponse
    {
        $item = MenuItem::factory()->for($restaurant)->create();

        return $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', [
            'restaurant_id' => $restaurant->id,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);
    }

    public function test_an_order_is_accepted_during_working_hours(): void
    {
        // 12:00 UTC = 17:00 Toshkent
        Carbon::setTestNow('2026-08-15 12:00:00');

        $this->order($this->restaurantOpen('09:00', '22:00'))->assertCreated();
    }

    public function test_an_order_is_refused_when_the_restaurant_is_closed(): void
    {
        // 02:00 UTC = 07:00 Toshkent — hali ochilmagan
        Carbon::setTestNow('2026-08-15 02:00:00');

        $restaurant = $this->restaurantOpen('09:00', '22:00');

        $this->order($restaurant)
            ->assertStatus(422)
            ->assertJsonValidationErrors('restaurant_id')
            ->assertJsonFragment(['Oshxona hozir yopiq. Ish vaqti: 09:00–22:00.']);

        $this->assertSame(0, Order::count());
    }

    public function test_a_closed_restaurant_does_not_quote_a_ready_time_either(): void
    {
        Carbon::setTestNow('2026-08-15 02:00:00');

        $restaurant = $this->restaurantOpen('09:00', '22:00');
        $item = MenuItem::factory()->for($restaurant)->create();

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders/estimate', [
            'restaurant_id' => $restaurant->id,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('restaurant_id');
    }

    public function test_hours_that_pass_midnight_are_understood(): void
    {
        $restaurant = $this->restaurantOpen('22:00', '03:00');

        // 20:00 UTC = 01:00 Toshkent — yarim tundan keyin, hali ochiq
        Carbon::setTestNow('2026-08-15 20:00:00');
        $this->assertTrue($restaurant->isOpenAt());

        // 12:00 UTC = 17:00 Toshkent — ish vaqtidan oldin
        Carbon::setTestNow('2026-08-15 12:00:00');
        $this->assertFalse($restaurant->isOpenAt());
    }

    public function test_the_edges_of_the_working_day_are_exact(): void
    {
        $restaurant = $this->restaurantOpen('09:00', '22:00');

        Carbon::setTestNow('2026-08-15 04:00:00'); // 09:00 Toshkent
        $this->assertTrue($restaurant->isOpenAt(), 'ochilish daqiqasida ochiq');

        Carbon::setTestNow('2026-08-15 03:59:00'); // 08:59 Toshkent
        $this->assertFalse($restaurant->isOpenAt(), 'ochilishdan bir daqiqa oldin yopiq');

        Carbon::setTestNow('2026-08-15 16:59:00'); // 21:59 Toshkent
        $this->assertTrue($restaurant->isOpenAt(), 'yopilishdan bir daqiqa oldin ochiq');

        Carbon::setTestNow('2026-08-15 17:00:00'); // 22:00 Toshkent
        $this->assertFalse($restaurant->isOpenAt(), 'yopilish daqiqasida yopiq');
    }

    public function test_an_inactive_restaurant_is_closed_whatever_the_clock_says(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');

        $restaurant = Restaurant::factory()->inactive()->create([
            'opens_at' => '09:00',
            'closes_at' => '22:00',
        ]);

        $this->assertFalse($restaurant->isOpenAt());
    }

    public function test_the_customer_facing_list_says_whether_a_restaurant_is_open(): void
    {
        Carbon::setTestNow('2026-08-15 02:00:00'); // 07:00 Toshkent

        // Roʻyxat nom boʻyicha tartiblanadi, shuning uchun nomlar aniq.
        Restaurant::factory()->create(['name' => 'Ertalabki', 'opens_at' => '06:00', 'closes_at' => '23:00']);
        Restaurant::factory()->create(['name' => 'Kunduzgi', 'opens_at' => '09:00', 'closes_at' => '22:00']);

        $response = $this->getJson('/api/restaurants')->assertOk();

        $this->assertSame(
            ['Ertalabki' => true, 'Kunduzgi' => false],
            array_column($response->json('restaurants'), 'is_open_now', 'name'),
        );
    }
}

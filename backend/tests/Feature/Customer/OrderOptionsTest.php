<?php

namespace Tests\Feature\Customer;

use App\Models\MenuItem;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * 9-bosqich: modifikatorlar — oʻlcham, sous, qoʻshimchalar.
 *
 * Ali hot-dog buyurtma qilganda mayonez oʻrniga smetana tanlay olishi kerak;
 * narx ham, tayyorlash vaqti ham shunga qarab oʻzgaradi.
 */
class OrderOptionsTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $customer;

    private MenuItem $hotDog;

    private OptionGroup $sauce;

    private Option $mayo;

    private Option $sourCream;

    private OptionGroup $extras;

    private Option $cheese;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->customer = User::factory()->customer()->create();

        $this->hotDog = MenuItem::factory()->for($this->restaurant)->create([
            'name' => 'Xot-dog', 'price' => 18000, 'base_prep_minutes' => 2, 'extra_prep_minutes' => 1,
        ]);

        // Majburiy, bittasi: sous tanlanmasa buyurtma qabul qilinmaydi.
        $this->sauce = $this->group($this->hotDog, 'Sous', min: 1, max: 1);
        $this->mayo = $this->option($this->sauce, 'Mayonez', price: 0, prep: 0);
        $this->sourCream = $this->option($this->sauce, 'Smetana', price: 2000, prep: 1);

        // Ixtiyoriy, xohlagancha.
        $this->extras = $this->group($this->hotDog, 'Qoʻshimchalar', min: 0, max: null);
        $this->cheese = $this->option($this->extras, 'Pishloq', price: 3000, prep: 1);
    }

    private function group(MenuItem $item, string $name, int $min, ?int $max): OptionGroup
    {
        $group = new OptionGroup(['name' => $name, 'min_select' => $min, 'max_select' => $max]);
        $group->menu_item_id = $item->id;
        $group->save();

        return $group;
    }

    private function option(OptionGroup $group, string $name, float $price, int $prep): Option
    {
        $option = new Option([
            'name' => $name, 'price_delta' => $price, 'prep_delta_minutes' => $prep, 'is_available' => true,
        ]);
        $option->option_group_id = $group->id;
        $option->save();

        return $option->refresh();
    }

    /** @param array<int, int> $optionIds */
    private function order(array $optionIds, int $quantity = 1): TestResponse
    {
        return $this->actingAs($this->customer)->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [[
                'menu_item_id' => $this->hotDog->id,
                'quantity' => $quantity,
                'option_ids' => $optionIds,
            ]],
        ]);
    }

    public function test_options_change_the_price_and_the_preparation_time(): void
    {
        // 18 000 + 2 000 (smetana) + 3 000 (pishloq) = 23 000
        $response = $this->order([$this->sourCream->id, $this->cheese->id])->assertCreated();

        $response->assertJsonPath('order.total_price', '23000.00')
            // 2 daq taom + 1 daq smetana + 1 daq pishloq
            ->assertJsonPath('order.prep_minutes', 4);
    }

    public function test_option_price_applies_per_unit_but_prep_time_only_once(): void
    {
        // 3 dona: (18 000 + 3 000) × 3 = 63 000
        $response = $this->order([$this->mayo->id, $this->cheese->id], quantity: 3)->assertCreated();

        $response->assertJsonPath('order.total_price', '63000.00')
            // 2 + 1 + 1 (dona uchun) = 4, ustiga pishloq bir marta = 5
            ->assertJsonPath('order.prep_minutes', 5);
    }

    public function test_a_required_group_must_be_answered(): void
    {
        $this->order([$this->cheese->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors("items.0.option_ids.{$this->sauce->id}");

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_single_choice_group_refuses_two_answers(): void
    {
        $this->order([$this->mayo->id, $this->sourCream->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors("items.0.option_ids.{$this->sauce->id}");
    }

    public function test_an_option_from_another_item_is_refused(): void
    {
        $other = MenuItem::factory()->for($this->restaurant)->create(['name' => 'Lavash']);
        $otherGroup = $this->group($other, 'Oʻlcham', min: 0, max: 1);
        $otherOption = $this->option($otherGroup, 'Katta', price: 5000, prep: 1);

        $this->order([$this->mayo->id, $otherOption->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.option_ids');
    }

    public function test_an_unavailable_option_is_refused(): void
    {
        $this->sourCream->update(['is_available' => false]);

        $this->order([$this->sourCream->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.option_ids');
    }

    public function test_the_estimate_uses_the_same_rules_as_the_order(): void
    {
        $this->actingAs($this->customer)->postJson('/api/orders/estimate', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [['menu_item_id' => $this->hotDog->id, 'quantity' => 1, 'option_ids' => []]],
        ])->assertStatus(422);

        $this->app['auth']->forgetGuards();

        $this->actingAs($this->customer)->postJson('/api/orders/estimate', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [[
                'menu_item_id' => $this->hotDog->id,
                'quantity' => 1,
                'option_ids' => [$this->sourCream->id],
            ]],
        ])->assertOk()->assertJsonPath('estimate.total_price', '20000.00');
    }

    public function test_the_order_keeps_its_own_copy_of_the_chosen_options(): void
    {
        $order = $this->order([$this->sourCream->id])->assertCreated()->json('order.id');

        // Menyu keyin oʻzgardi: narx oshdi, variant nomi almashdi.
        $this->sourCream->update(['name' => 'Smetana (yangi)', 'price_delta' => 9000]);

        $this->app['auth']->forgetGuards();

        $this->actingAs($this->customer)
            ->getJson("/api/orders/{$order}")
            ->assertOk()
            ->assertJsonPath('order.items.0.options.0.name', 'Smetana')
            ->assertJsonPath('order.items.0.options.0.group_name', 'Sous')
            ->assertJsonPath('order.items.0.options.0.price_delta', '2000.00')
            ->assertJsonPath('order.total_price', '20000.00');
    }

    public function test_the_customer_menu_lists_the_groups(): void
    {
        $this->getJson("/api/restaurants/{$this->restaurant->id}")
            ->assertOk()
            ->assertJsonPath('menu_items.0.option_groups.0.name', 'Sous')
            ->assertJsonPath('menu_items.0.option_groups.0.is_required', true)
            ->assertJsonPath('menu_items.0.option_groups.0.options.1.name', 'Smetana');
    }

    public function test_unavailable_options_are_hidden_from_the_customer(): void
    {
        $this->sourCream->update(['is_available' => false]);

        $names = $this->getJson("/api/restaurants/{$this->restaurant->id}")
            ->assertOk()
            ->json('menu_items.0.option_groups.0.options.*.name');

        $this->assertSame(['Mayonez'], $names);
    }
}

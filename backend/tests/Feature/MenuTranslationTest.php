<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 8-bosqich (3-qism): taom nomi va tavsifi mijozning tilida koʻrinadi.
 *
 * Tarjima majburiy emas — xodim bitta tilda ishlayversa ham boʻladi, lekin
 * toʻldirgan tili mijozga yetib boradi.
 */
class MenuTranslationTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $staff;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->staff = User::factory()->staff($this->restaurant)->create();
        $this->customer = User::factory()->customer()->create();
    }

    /** @param array<string, mixed> $translations */
    private function lavash(array $translations = []): MenuItem
    {
        $item = MenuItem::factory()->for($this->restaurant)->create([
            'name' => 'Lavash',
            'description' => 'Tovuq goʻshtli klassik lavash',
            'category' => 'Lavashlar',
        ]);

        if ($translations !== []) {
            $item->translations = $translations;
            $item->save();
        }

        return $item;
    }

    public function test_staff_can_save_translations_and_they_come_back(): void
    {
        $response = $this->actingAs($this->staff)->postJson('/api/staff/menu-items', [
            'name' => 'Lavash',
            'price' => 32000,
            'base_prep_minutes' => 4,
            'extra_prep_minutes' => 2,
            'translations' => [
                'ru' => ['name' => 'Лаваш', 'description' => 'Классический с курицей'],
                'en' => ['name' => 'Lavash wrap'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('menu_item.translations.ru.name', 'Лаваш')
            ->assertJsonPath('menu_item.translations.en.name', 'Lavash wrap');
    }

    public function test_empty_translations_are_not_stored(): void
    {
        $this->actingAs($this->staff)->postJson('/api/staff/menu-items', [
            'name' => 'Xot-dog',
            'price' => 18000,
            'base_prep_minutes' => 2,
            'extra_prep_minutes' => 1,
            // Xodim oynani ochib, hech narsa yozmasdan saqlagan holat.
            'translations' => ['ru' => ['name' => '', 'description' => '  '], 'en' => []],
        ])->assertCreated()->assertJsonPath('menu_item.translations', null);
    }

    public function test_customer_sees_the_menu_in_their_own_language(): void
    {
        $this->lavash([
            'ru' => ['name' => 'Лаваш', 'description' => 'Классический с курицей', 'category' => 'Лаваши'],
        ]);

        $this->withHeader('Accept-Language', 'ru')
            ->getJson("/api/restaurants/{$this->restaurant->id}")
            ->assertOk()
            ->assertJsonPath('menu_items.0.name', 'Лаваш')
            ->assertJsonPath('menu_items.0.description', 'Классический с курицей')
            ->assertJsonPath('menu_items.0.category', 'Лаваши');
    }

    public function test_a_missing_translation_falls_back_to_the_original_name(): void
    {
        // Faqat nomi tarjima qilingan; tavsif tarjimasiz qolgan.
        $this->lavash(['en' => ['name' => 'Lavash wrap']]);

        $this->withHeader('Accept-Language', 'en')
            ->getJson("/api/restaurants/{$this->restaurant->id}")
            ->assertOk()
            ->assertJsonPath('menu_items.0.name', 'Lavash wrap')
            // Boʻsh joy emas, asl matn koʻrsatiladi.
            ->assertJsonPath('menu_items.0.description', 'Tovuq goʻshtli klassik lavash');
    }

    public function test_an_untranslated_language_shows_the_original(): void
    {
        $this->lavash(['ru' => ['name' => 'Лаваш']]);

        $this->withHeader('Accept-Language', 'uz-Cyrl')
            ->getJson("/api/restaurants/{$this->restaurant->id}")
            ->assertOk()
            ->assertJsonPath('menu_items.0.name', 'Lavash');
    }

    public function test_the_order_keeps_its_own_copy_of_the_translation(): void
    {
        $item = $this->lavash(['ru' => ['name' => 'Лаваш']]);

        $order = $this->actingAs($this->customer)
            ->postJson('/api/orders', [
                'restaurant_id' => $this->restaurant->id,
                'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('order.id');

        // Menyu keyin oʻzgardi — buyurtma eski nusxasini saqlab qolishi kerak.
        $item->name = 'Lavash XXL';
        $item->translations = ['ru' => ['name' => 'Лаваш XXL']];
        $item->save();

        $this->app['auth']->forgetGuards();

        $this->actingAs($this->customer)
            ->withHeader('Accept-Language', 'ru')
            ->getJson("/api/orders/{$order}")
            ->assertOk()
            ->assertJsonPath('order.items.0.name', 'Лаваш');
    }

    public function test_staff_always_edit_the_original_text_not_a_translation(): void
    {
        $item = $this->lavash(['ru' => ['name' => 'Лаваш', 'description' => 'Классический']]);

        // Xodim ruscha ishlayapti, lekin tahrirlash oynasi asl matnni
        // koʻrsatishi kerak: aks holda saqlaganda tarjima asl nom oʻrniga
        // yozilib ketardi.
        $this->actingAs($this->staff)
            ->withHeader('Accept-Language', 'ru')
            ->getJson("/api/staff/menu-items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('menu_item.name', 'Lavash')
            ->assertJsonPath('menu_item.description', 'Tovuq goʻshtli klassik lavash')
            ->assertJsonPath('menu_item.translations.ru.name', 'Лаваш');
    }

    public function test_the_base_language_cannot_be_overridden_through_translations(): void
    {
        // `uz` asosiy til: u `name` maydonida turadi, tarjimalar orasida emas.
        $this->actingAs($this->staff)->postJson('/api/staff/menu-items', [
            'name' => 'Burger',
            'price' => 28000,
            'base_prep_minutes' => 3,
            'extra_prep_minutes' => 1,
            'translations' => ['uz' => ['name' => 'Boshqa nom']],
        ])->assertStatus(422)->assertJsonValidationErrors('translations.uz');
    }
}

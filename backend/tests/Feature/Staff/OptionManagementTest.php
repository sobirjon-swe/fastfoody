<?php

namespace Tests\Feature\Staff;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * 9-bosqich: xodim modifikator guruhlarini bitta soʻrovda saqlaydi.
 */
class OptionManagementTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $staff;

    private MenuItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->staff = User::factory()->staff($this->restaurant)->create();
        $this->item = MenuItem::factory()->for($this->restaurant)->create(['name' => 'Xot-dog']);
    }

    /** @param array<int, mixed> $groups */
    private function sync(array $groups): TestResponse
    {
        return $this->actingAs($this->staff)
            ->putJson("/api/staff/menu-items/{$this->item->id}/options", ['groups' => $groups]);
    }

    /** @return array<string, mixed> */
    private function sauceGroup(): array
    {
        return [
            'name' => 'Sous',
            'min_select' => 1,
            'max_select' => 1,
            'translations' => ['ru' => ['name' => 'Соус']],
            'options' => [
                ['name' => 'Mayonez', 'price_delta' => 0, 'prep_delta_minutes' => 0],
                [
                    'name' => 'Smetana',
                    'price_delta' => 2000,
                    'prep_delta_minutes' => 1,
                    'translations' => ['ru' => ['name' => 'Сметана']],
                ],
            ],
        ];
    }

    public function test_staff_can_create_groups_with_translations(): void
    {
        $this->sync([$this->sauceGroup()])
            ->assertOk()
            ->assertJsonPath('menu_item.option_groups.0.name', 'Sous')
            ->assertJsonPath('menu_item.option_groups.0.translations.ru.name', 'Соус')
            ->assertJsonPath('menu_item.option_groups.0.options.1.name', 'Smetana')
            ->assertJsonPath('menu_item.option_groups.0.options.1.price_delta', '2000.00');

        $this->assertDatabaseCount('option_groups', 1);
        $this->assertDatabaseCount('options', 2);
    }

    public function test_a_second_save_updates_instead_of_duplicating(): void
    {
        $created = $this->sync([$this->sauceGroup()])->json('menu_item.option_groups.0');

        $this->app['auth']->forgetGuards();

        $group = $this->sauceGroup();
        $group['id'] = $created['id'];
        $group['name'] = 'Sous tanlovi';
        $group['options'][0]['id'] = $created['options'][0]['id'];
        // Ikkinchi variant roʻyxatdan chiqarildi — u oʻchirilishi kerak.
        unset($group['options'][1]);
        $group['options'] = array_values($group['options']);

        $this->sync([$group])
            ->assertOk()
            ->assertJsonPath('menu_item.option_groups.0.name', 'Sous tanlovi')
            ->assertJsonCount(1, 'menu_item.option_groups.0.options');

        $this->assertDatabaseCount('option_groups', 1);
        $this->assertDatabaseCount('options', 1);
    }

    public function test_an_empty_list_removes_every_group(): void
    {
        $this->sync([$this->sauceGroup()])->assertOk();
        $this->app['auth']->forgetGuards();

        $this->sync([])->assertOk()->assertJsonCount(0, 'menu_item.option_groups');

        $this->assertDatabaseCount('option_groups', 0);
        $this->assertDatabaseCount('options', 0);
    }

    public function test_a_group_needs_at_least_one_option(): void
    {
        $this->sync([['name' => 'Sous', 'min_select' => 0, 'max_select' => 1, 'options' => []]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('groups.0.options');
    }

    public function test_a_required_group_cannot_ask_for_more_than_it_offers(): void
    {
        $this->sync([[
            'name' => 'Sous',
            'min_select' => 3,
            'max_select' => null,
            'options' => [['name' => 'Mayonez', 'price_delta' => 0, 'prep_delta_minutes' => 0]],
        ]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('groups.0.min_select');
    }

    public function test_a_negative_price_is_refused(): void
    {
        $group = $this->sauceGroup();
        $group['options'][0]['price_delta'] = -500;

        $this->sync([$group])
            ->assertStatus(422)
            ->assertJsonValidationErrors('groups.0.options.0.price_delta');
    }

    public function test_another_restaurants_item_stays_out_of_reach(): void
    {
        $other = MenuItem::factory()->for(Restaurant::factory())->create();

        $this->actingAs($this->staff)
            ->putJson("/api/staff/menu-items/{$other->id}/options", ['groups' => [$this->sauceGroup()]])
            ->assertNotFound();

        $this->assertDatabaseCount('option_groups', 0);
    }

    public function test_an_id_from_another_item_does_not_hijack_it(): void
    {
        $mine = $this->sync([$this->sauceGroup()])->json('menu_item.option_groups.0');

        $second = MenuItem::factory()->for($this->restaurant)->create(['name' => 'Lavash']);

        $this->app['auth']->forgetGuards();

        // Boshqa taomning guruh id'si bilan saqlashga urinish: u guruh
        // oʻzgarmasligi, yangi guruh esa oʻz taomiga yaratilishi kerak.
        $group = $this->sauceGroup();
        $group['id'] = $mine['id'];
        $group['name'] = 'Oʻgʻirlangan';

        $this->actingAs($this->staff)
            ->putJson("/api/staff/menu-items/{$second->id}/options", ['groups' => [$group]])
            ->assertOk();

        $this->assertDatabaseHas('option_groups', ['id' => $mine['id'], 'name' => 'Sous']);
        $this->assertDatabaseCount('option_groups', 2);
    }

    public function test_staff_see_the_original_names_not_translations(): void
    {
        $this->sync([$this->sauceGroup()])->assertOk();
        $this->app['auth']->forgetGuards();

        $this->actingAs($this->staff)
            ->withHeader('Accept-Language', 'ru')
            ->getJson("/api/staff/menu-items/{$this->item->id}")
            ->assertOk()
            ->assertJsonPath('menu_item.option_groups.0.name', 'Sous')
            ->assertJsonPath('menu_item.option_groups.0.translations.ru.name', 'Соус');
    }
}

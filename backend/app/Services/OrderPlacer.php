<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\MenuItem;
use App\Models\Option;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a cart into an order.
 *
 * Everything that costs money or time is read from the menu inside one
 * transaction: the client only says which item and how many.
 */
class OrderPlacer
{
    public function __construct(private readonly CartOptions $options) {}

    /**
     * Prices and preparation time of a cart, without writing anything. The cart
     * goes through exactly the same checks as a real order, so an estimate can
     * never be shown for something that could not be ordered.
     *
     * @param  array<int, array{menu_item_id: int, quantity: int}>  $cart
     * @return array{total_price: string, prep_minutes: int}
     *
     * @throws ValidationException
     */
    public function preview(Restaurant $restaurant, array $cart): array
    {
        $this->assertOpen($restaurant);

        $totalTiyin = 0;
        $prepMinutes = 0;

        foreach ($this->lines($restaurant, $cart) as $line) {
            $totalTiyin += $line['line_tiyin'];
            $prepMinutes += $line['prep_minutes'];
        }

        return [
            'total_price' => Money::toDecimal($totalTiyin),
            'prep_minutes' => $prepMinutes,
        ];
    }

    /**
     * @param  array<int, array{menu_item_id: int, quantity: int}>  $cart
     *
     * @throws ValidationException when the restaurant or one of the items
     *                             cannot be ordered from right now
     */
    public function place(User $customer, Restaurant $restaurant, array $cart): Order
    {
        $this->assertOpen($restaurant);

        return DB::transaction(function () use ($customer, $restaurant, $cart) {
            $lines = $this->lines($restaurant, $cart, lock: true);

            $order = new Order(['total_price' => 0, 'prep_minutes' => 0]);
            $order->customer_id = $customer->id;
            $order->restaurant_id = $restaurant->id;
            // Set explicitly rather than leaning on the column default, so the
            // model that is returned already carries its state.
            $order->status = OrderStatus::Pending;
            $order->save();

            $totalTiyin = 0;
            $prepMinutes = 0;

            foreach ($lines as $line) {
                $menuItem = $line['menu_item'];

                $orderItem = $order->items()->make([
                    'menu_item_id' => $menuItem->id,
                    'name' => $menuItem->name,
                    'unit_price' => $menuItem->price,
                    'quantity' => $line['quantity'],
                    'line_total' => Money::toDecimal($line['line_tiyin']),
                    'prep_minutes' => $line['prep_minutes'],
                ]);
                // Nom bilan birga tarjimasi ham nusxa koʻchiriladi: keyin
                // menyu oʻzgarsa ham mijoz buyurtmasini oʻz tilida koʻradi.
                $orderItem->translations = $menuItem->translations;
                $orderItem->save();

                foreach ($line['options'] as $option) {
                    $chosen = $orderItem->options()->make([
                        'option_id' => $option->id,
                        'group_name' => $option->group->name,
                        'name' => $option->name,
                        'price_delta' => $option->price_delta,
                        'prep_delta_minutes' => $option->prep_delta_minutes,
                    ]);
                    // Guruh nomi ham, variant nomi ham nusxa koʻchiriladi:
                    // chek keyin ham oʻsha koʻrinishda qoladi.
                    $chosen->translations = $this->optionTranslations($option);
                    $chosen->save();
                }

                $totalTiyin += $line['line_tiyin'];
                $prepMinutes += $line['prep_minutes'];
            }

            $order->fill([
                'total_price' => Money::toDecimal($totalTiyin),
                'prep_minutes' => $prepMinutes,
            ])->save();

            return $order->load('items.options', 'restaurant');
        });
    }

    /**
     * Variant va u tegishli guruh nomlarini bitta tarjima toʻplamiga
     * yigʻadi, shunda chek qatorini bir joydan chizish mumkin.
     *
     * @return array<string, array<string, string>>|null
     */
    private function optionTranslations(Option $option): ?array
    {
        $merged = [];

        foreach ((array) $option->translations as $locale => $fields) {
            $merged[$locale] = array_filter([
                'name' => $fields['name'] ?? null,
                'group_name' => $option->group->translations[$locale]['name'] ?? null,
            ], fn ($value) => is_string($value) && $value !== '');
        }

        foreach ((array) $option->group->translations as $locale => $fields) {
            if (isset($fields['name']) && ! isset($merged[$locale]['group_name'])) {
                $merged[$locale]['group_name'] = $fields['name'];
            }
        }

        return array_filter($merged) ?: null;
    }

    /**
     * Oshxona faol boʻlishi ham, ayni damda ish vaqtida boʻlishi ham shart:
     * yopiq oshxonaga berilgan buyurtma uchun tayyor boʻlish vaqti maʼnosiz.
     */
    private function assertOpen(Restaurant $restaurant): void
    {
        if (! $restaurant->is_active) {
            throw ValidationException::withMessages([
                'restaurant_id' => __('Bu oshxona hozir buyurtma qabul qilmaydi.'),
            ]);
        }

        if (! $restaurant->isOpenAt()) {
            throw ValidationException::withMessages([
                'restaurant_id' => __('Oshxona hozir yopiq. Ish vaqti: :opens–:closes.', [
                    'opens' => $restaurant->opens_at,
                    'closes' => $restaurant->closes_at,
                ]),
            ]);
        }
    }

    /**
     * Modifikator narxi **har bir dona uchun** qoʻshiladi (3 ta gamburgerga
     * pishloq — uch marta pul), tayyorlash vaqti esa **qatorga bir marta**:
     * sousni butun partiyaga birdan qoʻshiladi, shuning uchun uni har donaga
     * koʻpaytirish navbatni asossiz choʻzardi.
     *
     * @param  array<int, array{menu_item_id: int, quantity: int, option_ids?: array<int, int>}>  $cart
     * @return array<int, array{menu_item: MenuItem, quantity: int, line_tiyin: int, prep_minutes: int, options: Collection<int, Option>}>
     */
    private function lines(Restaurant $restaurant, array $cart, bool $lock = false): array
    {
        $menuItems = $this->menuItemsFor($restaurant, $cart, $lock);
        $selection = $this->options->resolve($menuItems, $cart);

        return array_map(function (array $line, int $index) use ($menuItems, $selection) {
            $menuItem = $menuItems[(int) $line['menu_item_id']];
            $quantity = (int) $line['quantity'];
            $options = $selection[$index] ?? collect();

            $optionTiyin = $options->sum(fn (Option $option) => Money::toTiyin($option->price_delta));
            $optionMinutes = (int) $options->sum(fn (Option $option) => $option->prep_delta_minutes);

            return [
                'menu_item' => $menuItem,
                'quantity' => $quantity,
                'line_tiyin' => (Money::toTiyin($menuItem->price) + $optionTiyin) * $quantity,
                'prep_minutes' => $menuItem->prepMinutesFor($quantity) + $optionMinutes,
                'options' => $options,
            ];
        }, $cart, array_keys($cart));
    }

    /**
     * @param  array<int, array{menu_item_id: int, quantity: int}>  $cart
     * @return array<int, MenuItem>
     */
    private function menuItemsFor(Restaurant $restaurant, array $cart, bool $lock): array
    {
        $ids = array_map(static fn (array $line) => (int) $line['menu_item_id'], $cart);

        // Scoped to this restaurant, so an id copied from another restaurant's
        // menu simply does not exist here.
        $query = MenuItem::where('restaurant_id', $restaurant->id)->whereIn('id', $ids);

        if ($lock) {
            $query->lockForUpdate();
        }

        $menuItems = $query->with('optionGroups')->get()->keyBy('id');

        $errors = [];

        foreach ($cart as $index => $line) {
            $menuItem = $menuItems->get((int) $line['menu_item_id']);

            if (! $menuItem) {
                $errors["items.{$index}.menu_item_id"] = __('Bu taom ushbu oshxona menyusida yoʻq.');

                continue;
            }

            if (! $menuItem->is_available) {
                $errors["items.{$index}.menu_item_id"] = __(':name hozir mavjud emas.', ['name' => $menuItem->name]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $menuItems->all();
    }
}

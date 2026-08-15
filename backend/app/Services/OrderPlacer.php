<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\Money;
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

                $order->items()->create([
                    'menu_item_id' => $menuItem->id,
                    'name' => $menuItem->name,
                    'unit_price' => $menuItem->price,
                    'quantity' => $line['quantity'],
                    'line_total' => Money::toDecimal($line['line_tiyin']),
                    'prep_minutes' => $line['prep_minutes'],
                ]);

                $totalTiyin += $line['line_tiyin'];
                $prepMinutes += $line['prep_minutes'];
            }

            $order->fill([
                'total_price' => Money::toDecimal($totalTiyin),
                'prep_minutes' => $prepMinutes,
            ])->save();

            return $order->load('items', 'restaurant');
        });
    }

    private function assertOpen(Restaurant $restaurant): void
    {
        if (! $restaurant->is_active) {
            throw ValidationException::withMessages([
                'restaurant_id' => __('Bu oshxona hozir buyurtma qabul qilmaydi.'),
            ]);
        }
    }

    /**
     * @param  array<int, array{menu_item_id: int, quantity: int}>  $cart
     * @return array<int, array{menu_item: MenuItem, quantity: int, line_tiyin: int, prep_minutes: int}>
     */
    private function lines(Restaurant $restaurant, array $cart, bool $lock = false): array
    {
        $menuItems = $this->menuItemsFor($restaurant, $cart, $lock);

        return array_map(function (array $line) use ($menuItems) {
            $menuItem = $menuItems[(int) $line['menu_item_id']];
            $quantity = (int) $line['quantity'];

            return [
                'menu_item' => $menuItem,
                'quantity' => $quantity,
                'line_tiyin' => Money::toTiyin($menuItem->price) * $quantity,
                'prep_minutes' => $menuItem->prepMinutesFor($quantity),
            ];
        }, $cart);
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

        $menuItems = $query->get()->keyBy('id');

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

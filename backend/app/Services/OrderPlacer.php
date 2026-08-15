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
     * @param  array<int, array{menu_item_id: int, quantity: int}>  $cart
     *
     * @throws ValidationException when the restaurant or one of the items
     *                             cannot be ordered from right now
     */
    public function place(User $customer, Restaurant $restaurant, array $cart): Order
    {
        if (! $restaurant->is_active) {
            throw ValidationException::withMessages([
                'restaurant_id' => __('Bu oshxona hozir buyurtma qabul qilmaydi.'),
            ]);
        }

        return DB::transaction(function () use ($customer, $restaurant, $cart) {
            $menuItems = $this->menuItemsFor($restaurant, $cart);

            $order = new Order(['total_price' => 0, 'prep_minutes' => 0]);
            $order->customer_id = $customer->id;
            $order->restaurant_id = $restaurant->id;
            // Set explicitly rather than leaning on the column default, so the
            // model that is returned already carries its state.
            $order->status = OrderStatus::Pending;
            $order->save();

            $totalTiyin = 0;
            $prepMinutes = 0;

            foreach ($cart as $line) {
                $menuItem = $menuItems[$line['menu_item_id']];
                $quantity = (int) $line['quantity'];
                $lineTiyin = Money::toTiyin($menuItem->price) * $quantity;
                $linePrep = $menuItem->prepMinutesFor($quantity);

                $order->items()->create([
                    'menu_item_id' => $menuItem->id,
                    'name' => $menuItem->name,
                    'unit_price' => $menuItem->price,
                    'quantity' => $quantity,
                    'line_total' => Money::toDecimal($lineTiyin),
                    'prep_minutes' => $linePrep,
                ]);

                $totalTiyin += $lineTiyin;
                $prepMinutes += $linePrep;
            }

            $order->fill([
                'total_price' => Money::toDecimal($totalTiyin),
                'prep_minutes' => $prepMinutes,
            ])->save();

            return $order->load('items', 'restaurant');
        });
    }

    /**
     * @param  array<int, array{menu_item_id: int, quantity: int}>  $cart
     * @return array<int, MenuItem>
     */
    private function menuItemsFor(Restaurant $restaurant, array $cart): array
    {
        $ids = array_map(static fn (array $line) => (int) $line['menu_item_id'], $cart);

        // Scoped to this restaurant, so an id copied from another restaurant's
        // menu simply does not exist here.
        $menuItems = MenuItem::where('restaurant_id', $restaurant->id)
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

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

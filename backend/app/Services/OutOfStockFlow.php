<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «Mahsulot tugadi» oqimi (5-bosqich).
 *
 * Oshxona toʻlangan buyurtmadagi biror taom tugaganini bildiradi; buyurtma
 * navbatdan chiqib mijozning qarorini kutadi. Mijoz ikkitadan birini tanlaydi:
 * tugagan taomni boshqasiga almashtirish yoki buyurtmani bekor qilib pulni
 * qaytarib olish (MVP'da toʻlov ham, qaytarish ham simulyatsiya).
 */
class OutOfStockFlow
{
    public function __construct(private readonly KitchenQueue $queue) {}

    /**
     * Oshxona: shu qatordagi taom tugadi.
     *
     * @throws ValidationException
     */
    public function report(Order $order, OrderItem $item, bool $markMenuItemUnavailable): Order
    {
        if (! $order->status->canReportOutOfStock()) {
            throw ValidationException::withMessages([
                'order_item_id' => __('Bu buyurtma uchun mahsulot tugaganini belgilab boʻlmaydi.'),
            ]);
        }

        return DB::transaction(function () use ($order, $item, $markMenuItemUnavailable) {
            $item->out_of_stock_at = now();
            $item->save();

            $order->status = OrderStatus::AwaitingCustomerDecision;
            // Navbatdan chiqadi: mijoz javob bermaguncha oshxona uni pishirmaydi.
            $order->ready_at = null;
            $order->save();

            if ($markMenuItemUnavailable && $item->menuItem) {
                $item->menuItem->update(['is_available' => false]);
            }

            return $order->load('items.options', 'customer', 'restaurant');
        });
    }

    /**
     * Mijoz: tugagan taomni menyudagi boshqasiga almashtiradi. Miqdor oʻzgarmaydi,
     * narx va tayyorlash vaqti yangi taomdan olinadi va buyurtma navbatga
     * qaytadi.
     *
     * @throws ValidationException
     */
    public function replace(Order $order, OrderItem $item, MenuItem $replacement): Order
    {
        $this->assertAwaitingDecision($order);

        if ($replacement->restaurant_id !== $order->restaurant_id) {
            throw ValidationException::withMessages([
                'menu_item_id' => __('Bu taom ushbu oshxona menyusida yoʻq.'),
            ]);
        }

        if (! $replacement->is_available) {
            throw ValidationException::withMessages([
                'menu_item_id' => __(':name hozir mavjud emas.', ['name' => $replacement->name]),
            ]);
        }

        return DB::transaction(function () use ($order, $item, $replacement) {
            $quantity = $item->quantity;

            $item->fill([
                'menu_item_id' => $replacement->id,
                'name' => $replacement->name,
                'unit_price' => $replacement->price,
                'line_total' => Money::toDecimal(Money::toTiyin($replacement->price) * $quantity),
                'prep_minutes' => $replacement->prepMinutesFor($quantity),
            ]);
            $item->translations = $replacement->translations;
            $item->out_of_stock_at = null;
            $item->save();

            $this->recalculate($order);

            $order->status = OrderStatus::Paid;
            // Yangi tarkib boʻyicha navbatga qaytadi.
            $this->queue->schedule($order);
            $order->save();

            return $order->load('items.options', 'customer', 'restaurant');
        });
    }

    /**
     * Mijoz: buyurtmani bekor qiladi, pul qaytariladi (simulyatsiya).
     *
     * @throws ValidationException
     */
    public function cancel(Order $order): Order
    {
        $this->assertAwaitingDecision($order);

        $order->status = OrderStatus::CancelledOutOfStock;
        $order->refunded_at = now();
        $order->ready_at = null;
        $order->save();

        return $order->load('items.options', 'customer', 'restaurant');
    }

    private function assertAwaitingDecision(Order $order): void
    {
        if ($order->status !== OrderStatus::AwaitingCustomerDecision) {
            throw ValidationException::withMessages([
                'status' => __('Bu buyurtma sizning qaroringizni kutmayapti.'),
            ]);
        }
    }

    /**
     * Buyurtma jami summasi va tayyorlash vaqti har doim qatorlardan yigʻiladi,
     * shuning uchun almashtirishdan keyin ular oʻz-oʻzidan toʻgʻri qoladi.
     */
    private function recalculate(Order $order): void
    {
        $items = $order->items()->get();

        $order->total_price = Money::toDecimal(
            $items->sum(fn (OrderItem $line) => Money::toTiyin($line->line_total)),
        );
        $order->prep_minutes = (int) $items->sum('prep_minutes');
    }
}

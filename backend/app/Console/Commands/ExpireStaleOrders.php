<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Osilib qolgan buyurtmalarni yopadi:
 *
 *   - toʻlov qilinmagan savatcha (mijoz fikridan qaytgan);
 *   - tayyor boʻlib, lekin olib ketilmagan taom.
 *
 * Ikkalasi ham `muddati_otdi` holatiga oʻtadi. Pul qaytarilmaydi: TZ 5-bandiga
 * koʻra mijozning kelmasligi uning oʻz masʼuliyati.
 */
class ExpireStaleOrders extends Command
{
    protected $signature = 'orders:expire';

    protected $description = 'Toʻlanmagan va olib ketilmagan buyurtmalarni muddati oʻtgan deb belgilaydi';

    public function handle(): int
    {
        $now = Carbon::now();

        $unpaid = $this->expire(
            Order::where('status', OrderStatus::Pending)
                ->where('created_at', '<=', $now->copy()->subMinutes($this->minutes('unpaid_after_minutes')))
        );

        $uncollected = $this->expire(
            Order::where('status', OrderStatus::Ready)
                ->whereNotNull('ready_at')
                ->where('ready_at', '<=', $now->copy()->subMinutes($this->minutes('uncollected_after_minutes')))
        );

        $this->info("Toʻlanmagan: {$unpaid}, olib ketilmagan: {$uncollected}.");

        return self::SUCCESS;
    }

    /**
     * Buyurtmalar bittalab saqlanadi, ommaviy `update()` bilan emas: aks holda
     * model hodisalari ishlamaydi va mijoz «buyurtmangiz yopildi» xabarini
     * olmay qoladi. Bir seansda yopiladigan buyurtmalar soni kam, shuning uchun
     * bu narx sezilmaydi.
     *
     * @param  Builder<Order>  $query
     */
    private function expire(Builder $query): int
    {
        $count = 0;

        $query->chunkById(100, function ($orders) use (&$count) {
            foreach ($orders as $order) {
                $order->status = OrderStatus::Expired;
                $order->save();
                $count++;
            }
        });

        return $count;
    }

    private function minutes(string $key): int
    {
        return (int) config("fastfoody.expiry.{$key}");
    }
}

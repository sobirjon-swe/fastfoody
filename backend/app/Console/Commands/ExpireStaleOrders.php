<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;
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

        $unpaid = Order::where('status', OrderStatus::Pending)
            ->where('created_at', '<=', $now->copy()->subMinutes($this->minutes('unpaid_after_minutes')))
            ->update(['status' => OrderStatus::Expired, 'updated_at' => $now]);

        $uncollected = Order::where('status', OrderStatus::Ready)
            ->whereNotNull('ready_at')
            ->where('ready_at', '<=', $now->copy()->subMinutes($this->minutes('uncollected_after_minutes')))
            ->update(['status' => OrderStatus::Expired, 'updated_at' => $now]);

        $this->info("Toʻlanmagan: {$unpaid}, olib ketilmagan: {$uncollected}.");

        return self::SUCCESS;
    }

    private function minutes(string $key): int
    {
        return (int) config("fastfoody.expiry.{$key}");
    }
}

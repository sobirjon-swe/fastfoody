<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\TelegramNotifier;

/**
 * Buyurtma holati qayerda oʻzgarishidan qatʼi nazar (mijoz toʻlovi, oshxona
 * paneli, `orders:expire` buyrugʻi) mijozga xabar bitta joydan yuboriladi —
 * shu sababli yangi oqim qoʻshilganda xabarnomani qoʻshishni unutish mumkin
 * emas.
 */
class OrderObserver
{
    public function __construct(private readonly TelegramNotifier $notifier) {}

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $this->notifier->orderStatusChanged($order);
    }
}

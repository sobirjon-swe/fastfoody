<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Restaurant;
use Carbon\CarbonImmutable;

/**
 * Oshxona navbati.
 *
 * Tayyor boʻlish vaqti ikki omilga tayanadi (TZ 5-bandi):
 *   1) buyurtmaning oʻz tayyorlanish vaqti — miqdorga bogʻliq, Order::prep_minutes;
 *   2) oshxonaning joriy navbati — hozir tayyorlanayotgan buyurtmalar qachon tugashi.
 *
 * Yakuniy vaqt = (navbatdagi oxirgi buyurtma tugash vaqti) + (yangi buyurtmaning
 * oʻz tayyorlanish vaqti). Oshxona bitta oqim sifatida qaraladi: buyurtmalar
 * ketma-ket tayyorlanadi.
 */
class KitchenQueue
{
    /**
     * When the kitchen becomes free, i.e. when the last order already in the
     * queue is due. Never in the past: a queue that is behind schedule still
     * starts the next order now, not retroactively.
     */
    public function freeAt(Restaurant $restaurant): CarbonImmutable
    {
        $now = CarbonImmutable::now();

        $active = Order::where('restaurant_id', $restaurant->id)
            ->inKitchen()
            ->get(['ready_at', 'prep_minutes']);

        $lastReadyAt = $active
            ->filter(fn (Order $order) => $order->ready_at !== null)
            ->max('ready_at');

        $freeAt = $lastReadyAt
            ? CarbonImmutable::parse($lastReadyAt)->max($now)
            : $now;

        // Defensive: an order that is in the kitchen without a scheduled time
        // still takes the cooks' time, so its own duration is appended.
        $unscheduledMinutes = $active
            ->filter(fn (Order $order) => $order->ready_at === null)
            ->sum('prep_minutes');

        return $freeAt->addMinutes((int) $unscheduledMinutes);
    }

    /**
     * What a cart of this size would be promised if it were paid for now.
     *
     * @return array{queue_minutes: int, prep_minutes: int, ready_at: CarbonImmutable}
     */
    public function estimate(Restaurant $restaurant, int $prepMinutes): array
    {
        $now = CarbonImmutable::now();
        $freeAt = $this->freeAt($restaurant);

        return [
            'queue_minutes' => (int) round($now->diffInMinutes($freeAt)),
            'prep_minutes' => $prepMinutes,
            'ready_at' => $freeAt->addMinutes($prepMinutes),
        ];
    }

    /**
     * Books the order into the queue. Called when the payment lands, because
     * only a paid order occupies the kitchen — an abandoned cart must not push
     * everyone else's food back.
     */
    public function schedule(Order $order): CarbonImmutable
    {
        $readyAt = $this->freeAt($order->restaurant)->addMinutes($order->prep_minutes);

        $order->ready_at = $readyAt;

        return $readyAt;
    }
}

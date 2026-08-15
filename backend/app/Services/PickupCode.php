<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Support\Str;

/**
 * Olib ketish kodi — mijoz oshxonada aytadigan qisqa raqam.
 *
 * Kod butun tizim boʻylab emas, bitta oshxonaning ayni damdagi buyurtmalari
 * orasida takrorlanmasa yetarli: xodim uni faqat ochiq buyurtmalar ichidan
 * qidiradi. Shu sababli kod qisqa (4 raqam) va oʻqishga oson.
 */
class PickupCode
{
    private const LENGTH = 4;

    private const MAX_ATTEMPTS = 20;

    public function generateFor(Restaurant $restaurant): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $code = str_pad((string) random_int(0, 9999), self::LENGTH, '0', STR_PAD_LEFT);

            if (! $this->isTaken($restaurant, $code)) {
                return $code;
            }
        }

        // Deyarli erishib boʻlmas holat: ochiq buyurtmalar juda koʻp. Uzunroq
        // kod bilan davom etamiz, buyurtma esa kodsiz qolmaydi.
        return strtoupper(Str::random(6));
    }

    private function isTaken(Restaurant $restaurant, string $code): bool
    {
        return Order::where('restaurant_id', $restaurant->id)
            ->where('pickup_code', $code)
            ->whereNotIn('status', [
                OrderStatus::PickedUp,
                OrderStatus::CancelledOutOfStock,
                OrderStatus::Expired,
            ])
            ->exists();
    }
}

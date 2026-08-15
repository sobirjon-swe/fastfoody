<?php

namespace App\Enums;

/**
 * Buyurtma holatlari:
 *   kutilmoqda → tolov_qilindi → tayyorlanmoqda → tayyor → olib_ketildi
 *
 * Qoʻshimcha holatlar: bekor_qilindi_mahsulot_yoq, muddati_otdi.
 * Column values stay ASCII so they are safe to index and to compare in SQL.
 */
enum OrderStatus: string
{
    case Pending = 'kutilmoqda';
    case Paid = 'tolov_qilindi';
    case Preparing = 'tayyorlanmoqda';
    case Ready = 'tayyor';
    case PickedUp = 'olib_ketildi';
    case CancelledOutOfStock = 'bekor_qilindi_mahsulot_yoq';
    case Expired = 'muddati_otdi';

    /**
     * Orders that still occupy the kitchen queue. The ready-time calculation of
     * 3-bosqich sums the work that is in these states.
     */
    public function isActiveInKitchen(): bool
    {
        return in_array($this, [self::Paid, self::Preparing], strict: true);
    }

    public function isFinished(): bool
    {
        return in_array(
            $this,
            [self::PickedUp, self::CancelledOutOfStock, self::Expired],
            strict: true,
        );
    }
}

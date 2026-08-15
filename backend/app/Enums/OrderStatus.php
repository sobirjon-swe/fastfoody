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
    /**
     * Oshxona taom tugaganini bildirdi va mijozning qarorini kutmoqda:
     * almashtirish yoki bekor qilib pulni qaytarish.
     */
    case AwaitingCustomerDecision = 'mijoz_qarori_kutilmoqda';
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

    /**
     * Mijozning javobi kutilayotgan buyurtma oshxonada pishmaydi, shuning uchun
     * navbatda ham joy egallamaydi.
     */
    public function isBlocked(): bool
    {
        return $this === self::AwaitingCustomerDecision;
    }

    /**
     * Oshxona shu holatdagi buyurtma uchun «mahsulot tugadi» deb ayta oladi:
     * pul olingan, lekin taom hali berilmagan.
     */
    public function canReportOutOfStock(): bool
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

    /**
     * Oshxona xodimi shu holatdan qaysi holatlarga oʻtkaza oladi.
     *
     * Zanjir faqat oldinga yuradi. `kutilmoqda` — mijozning ishi (toʻlov), shu
     * sababli oshxona uni qoʻzgʻata olmaydi; tugagan holatlar ham yopiq.
     * Bekor qilish (`bekor_qilindi_mahsulot_yoq`) 5-bosqichda qoʻshiladi.
     *
     * @return array<int, self>
     */
    public function nextForStaff(): array
    {
        return match ($this) {
            self::Paid => [self::Preparing],
            self::Preparing => [self::Ready],
            self::Ready => [self::PickedUp],
            default => [],
        };
    }

    public function canBeMovedByStaffTo(self $next): bool
    {
        return in_array($next, $this->nextForStaff(), strict: true);
    }

    /**
     * Oshxona panelining ish taxtasi: hozir eʼtibor talab qiladigan holatlar.
     *
     * Mijozning javobi kutilayotgan buyurtma ham shu yerda qoladi — u
     * pishirilmaydi, lekin oshxona uning kutib turganini koʻrishi kerak.
     *
     * @return array<int, self>
     */
    public static function board(): array
    {
        return [self::Paid, self::Preparing, self::AwaitingCustomerDecision, self::Ready];
    }
}

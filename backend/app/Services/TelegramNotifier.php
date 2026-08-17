<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Jobs\SendTelegramMessage;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Buyurtma holati oʻzgarganda mijozga Telegram orqali xabar beradi.
 *
 * Mijoz uchun bu pollingdan yaxshiroq: ilova yopiq boʻlsa ham «buyurtmangiz
 * tayyor» xabari telefoniga keladi. Faqat Telegram orqali kirgan hisoblarga
 * yuboriladi — boshqalarda chat identifikatori yoʻq.
 */
class TelegramNotifier
{
    public function orderStatusChanged(Order $order): void
    {
        // Munosabat orqali emas, toʻgʻridan-toʻgʻri soʻrov bilan: buyurtma
        // qayerdan kelishiga qarab `customer` yuklangan boʻlmasligi mumkin,
        // lokal muhitda esa kechikkan yuklash taqiqlangan.
        $chatId = User::whereKey($order->customer_id)->value('telegram_id');

        if ($chatId === null) {
            return;
        }

        $text = $this->message($order);

        if ($text === null) {
            return;
        }

        SendTelegramMessage::dispatch((int) $chatId, $text);
    }

    /**
     * Har bir holat uchun xabar. Mijozga taʼsir qilmaydigan oraliq holatlar
     * (masalan «tayyorlanmoqda») uchun xabar yuborilmaydi — telefon behuda
     * chirillamasligi kerak.
     */
    private function message(Order $order): ?string
    {
        $code = $order->pickup_code;

        return match ($order->status) {
            OrderStatus::Paid => __('Buyurtmangiz qabul qilindi. Olib ketish kodi: :code. :ready', [
                'code' => $code,
                'ready' => $this->readyLine($order->ready_at),
            ]),
            OrderStatus::Ready => __('Buyurtmangiz tayyor! Olib ketish kodi: :code.', ['code' => $code]),
            OrderStatus::AwaitingCustomerDecision => __(
                'Kechirasiz, buyurtmangizdagi mahsulot tugab qoldi. Ilovada uni almashtiring yoki buyurtmani bekor qiling.',
            ),
            OrderStatus::CancelledOutOfStock => __('Buyurtmangiz bekor qilindi, toʻlov qaytariladi.'),
            OrderStatus::Expired => __('Buyurtmangiz muddati oʻtgani uchun yopildi.'),
            default => null,
        };
    }

    private function readyLine(?CarbonInterface $readyAt): string
    {
        if ($readyAt === null) {
            return '';
        }

        return __('Taxminan :time ga tayyor boʻladi.', [
            'time' => $readyAt->setTimezone(config('fastfoody.timezone'))->format('H:i'),
        ]);
    }
}

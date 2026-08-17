<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Telegram'ga bitta xabar yuboradi.
 *
 * Xabar yuborilmasligi biznes jarayonini toʻxtatmaydi: mijoz baribir ilovada
 * holatni koʻradi. Shuning uchun har qanday nosozlik logga yoziladi va
 * yutiladi — buyurtma holatini oʻzgartirgan soʻrov muvaffaqiyatli tugaydi.
 */
class SendTelegramMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $chatId,
        private readonly string $text,
    ) {}

    public function handle(): void
    {
        $token = (string) config('services.telegram.bot_token');

        if ($token === '') {
            return;
        }

        try {
            $response = Http::timeout(5)
                ->retry(2, 200, throw: false)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $this->chatId,
                    'text' => $this->text,
                ]);

            if ($response->failed()) {
                Log::warning('Telegram xabari yuborilmadi.', [
                    'chat_id' => $this->chatId,
                    'status' => $response->status(),
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Telegram xabarini yuborishda xatolik.', [
                'chat_id' => $this->chatId,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}

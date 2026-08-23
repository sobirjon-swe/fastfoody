<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendTelegramMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Telegram botiga kelgan xabarlarni qabul qiladi.
 *
 * Bot ataylab kichik: buyurtma berish, menyu va toʻlov — hammasi Mini App
 * ichida. Bu yerdagi yagona vazifa — odam botni birinchi marta ochganda unga
 * ilovaga kiradigan tugmani berish, chunki `/start` ga javob boʻlmasa bot
 * buzuq koʻrinadi.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if (! $this->isFromTelegram($request)) {
            // Telegram javob matnini oʻqimaydi, shuning uchun sababni
            // oshkor qilmaymiz — shunchaki qabul qilmaymiz.
            return response()->noContent(Response::HTTP_FORBIDDEN);
        }

        $message = $request->input('message');

        if (is_array($message)) {
            $this->handleMessage($message);
        }

        /*
         * Telegram 200 dan boshqa javobni xato deb biladi va xabarni qayta
         * yuboraveradi. Biz qoʻllab-quvvatlamaydigan yangilanish (tahrirlangan
         * xabar, kanal posti va h.k.) ham muvaffaqiyatli hisoblanadi.
         */
        return response()->noContent();
    }

    private function isFromTelegram(Request $request): bool
    {
        $secret = (string) config('services.telegram.webhook_secret');

        if ($secret === '') {
            return false;
        }

        return hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'));
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleMessage(array $message): void
    {
        $chatId = data_get($message, 'chat.id');
        $text = trim((string) data_get($message, 'text', ''));

        if (! is_int($chatId) && ! is_string($chatId)) {
            return;
        }

        $locale = $this->localeFor($message);

        // `/start` guruhga qoʻshilganda `/start@fastfoodybot` koʻrinishida keladi.
        $command = strtolower(strtok($text, ' ') ?: '');
        $command = explode('@', $command)[0];

        $reply = match ($command) {
            '/start' => __(
                'Assalomu alaykum! FastFoody bilan taomni oldindan buyurtma qiling — yetib '
                ."borganingizda tayyor boʻladi.\n\nBoshlash uchun quyidagi tugmani bosing.",
                [],
                $locale,
            ),
            default => __(
                'Buyurtma berish uchun quyidagi tugmani bosing — ilova shu yerda, Telegram ichida ochiladi.',
                [],
                $locale,
            ),
        };

        SendTelegramMessage::dispatch((int) $chatId, $reply, $this->openAppButton($locale));
    }

    /**
     * Ilovani Telegram ichida ochadigan tugma.
     *
     * @return array<string, mixed>
     */
    private function openAppButton(?string $locale): array
    {
        return [
            'inline_keyboard' => [[[
                'text' => __('FastFoody’ni ochish', [], $locale),
                'web_app' => ['url' => config('fastfoody.frontend_url')],
            ]]],
        ];
    }

    /**
     * Til: avval hisobdagi tanlov (odam ilovada tanlagan boʻlsa), keyin
     * Telegram interfeysi tili. Ikkalasi ham boʻlmasa — sukut boʻyicha til.
     *
     * @param  array<string, mixed>  $message
     */
    private function localeFor(array $message): ?string
    {
        $telegramId = data_get($message, 'from.id');
        $supported = (array) config('fastfoody.locales');

        if (is_int($telegramId)) {
            $saved = User::where('telegram_id', $telegramId)->value('locale');

            if (is_string($saved) && in_array($saved, $supported, true)) {
                return $saved;
            }
        }

        // Telegram "uz", "ru", "en-US" kabi kodlar beradi.
        $code = str_replace('-', '_', (string) data_get($message, 'from.language_code', ''));

        foreach ($supported as $locale) {
            if (strcasecmp($code, $locale) === 0 || strcasecmp(strtok($code, '_') ?: '', $locale) === 0) {
                return $locale;
            }
        }

        return null;
    }
}

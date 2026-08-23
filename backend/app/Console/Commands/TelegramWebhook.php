<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Telegram webhook'ini oʻrnatadi, holatini koʻrsatadi yoki oʻchiradi.
 *
 *   php artisan telegram:webhook          — oʻrnatadi
 *   php artisan telegram:webhook --info   — joriy holatni koʻrsatadi
 *   php artisan telegram:webhook --delete — oʻchiradi
 */
class TelegramWebhook extends Command
{
    protected $signature = 'telegram:webhook {--info : Joriy holatni koʻrsatish} {--delete : Webhook’ni oʻchirish}';

    protected $description = 'Telegram bot webhook’ini boshqaradi';

    public function handle(): int
    {
        $token = (string) config('services.telegram.bot_token');

        if ($token === '') {
            $this->error('TELEGRAM_BOT_TOKEN .env faylida koʻrsatilmagan.');

            return self::FAILURE;
        }

        $base = "https://api.telegram.org/bot{$token}";

        if ($this->option('info')) {
            return $this->show($base);
        }

        if ($this->option('delete')) {
            return $this->send($base.'/deleteWebhook', ['drop_pending_updates' => true], 'Webhook oʻchirildi.');
        }

        $secret = (string) config('services.telegram.webhook_secret');

        if ($secret === '') {
            $this->error('TELEGRAM_WEBHOOK_SECRET .env faylida koʻrsatilmagan — usiz webhook qabul qilinmaydi.');

            return self::FAILURE;
        }

        $url = rtrim((string) config('app.url'), '/').'/api/telegram/webhook';

        return $this->send($base.'/setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            // Faqat kerakli turdagi yangilanishlar: qolgani behuda trafik.
            'allowed_updates' => json_encode(['message']),
            'drop_pending_updates' => true,
        ], "Webhook oʻrnatildi: {$url}");
    }

    private function show(string $base): int
    {
        $response = Http::timeout(10)->get($base.'/getWebhookInfo');
        $result = (array) $response->json('result', []);

        foreach (['url', 'pending_update_count', 'last_error_message', 'last_error_date'] as $key) {
            $this->line(str_pad($key, 22).': '.var_export($result[$key] ?? null, true));
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $url, array $payload, string $success): int
    {
        $response = Http::timeout(10)->post($url, $payload);

        if (! $response->successful() || $response->json('ok') !== true) {
            $this->error('Telegram rad etdi: '.$response->body());

            return self::FAILURE;
        }

        $this->info($success);

        return self::SUCCESS;
    }
}

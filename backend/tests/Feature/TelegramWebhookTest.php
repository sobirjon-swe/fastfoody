<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bot `/start` ga javob berishi kerak: usiz odam botni ochib hech narsa
 * koʻrmaydi va uni buzuq deb oʻylaydi. Javobda Mini App'ni ochadigan tugma
 * boʻlishi shart — buyurtma berish faqat ilova ichida.
 */
class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.telegram.bot_token', '123456:TEST-BOT-TOKEN');
        config()->set('services.telegram.webhook_secret', self::SECRET);
        config()->set('fastfoody.frontend_url', 'https://app.example.test');

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function update(array $overrides = []): array
    {
        return [
            'update_id' => 1,
            'message' => array_replace_recursive([
                'message_id' => 10,
                'chat' => ['id' => 777123],
                'from' => ['id' => 777123, 'language_code' => 'uz'],
                'text' => '/start',
            ], $overrides),
        ];
    }

    private function sendUpdate(array $payload, ?string $secret = self::SECRET)
    {
        return $this->withHeaders(
            $secret === null ? [] : ['X-Telegram-Bot-Api-Secret-Token' => $secret],
        )->postJson('/api/telegram/webhook', $payload);
    }

    /** Maxfiy sarlavhasiz kelgan soʻrov bot nomidan javob yozdira olmaydi. */
    public function test_a_request_without_the_secret_header_is_refused(): void
    {
        $this->sendUpdate($this->update(), secret: null)->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_a_request_with_a_wrong_secret_is_refused(): void
    {
        $this->sendUpdate($this->update(), secret: 'boshqa-secret')->assertForbidden();

        Http::assertNothingSent();
    }

    /**
     * Maxfiy soʻz sozlanmagan boʻlsa webhook umuman ochilmaydi — aks holda
     * uni bilgan har kim bot nomidan xabar yozdira olardi.
     */
    public function test_the_webhook_is_closed_when_no_secret_is_configured(): void
    {
        config()->set('services.telegram.webhook_secret', '');

        $this->sendUpdate($this->update(), secret: '')->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_start_answers_with_a_button_that_opens_the_mini_app(): void
    {
        $this->sendUpdate($this->update())->assertNoContent();

        Http::assertSent(function (Request $request) {
            $markup = json_decode($request['reply_markup'], true);

            return str_contains($request->url(), '/sendMessage')
                && $request['chat_id'] === 777123
                && str_contains($request['text'], 'FastFoody')
                && $markup['inline_keyboard'][0][0]['web_app']['url'] === 'https://app.example.test';
        });
    }

    /** Guruhda buyruq `/start@botnomi` koʻrinishida keladi. */
    public function test_start_addressed_to_the_bot_is_recognised(): void
    {
        $this->sendUpdate($this->update(['text' => '/start@fastfoodybot']))->assertNoContent();

        Http::assertSent(fn (Request $request) => str_contains($request['text'], 'Boshlash uchun'));
    }

    /** Boshqa matnga ham javob boʻladi, lekin boshqacha. */
    public function test_any_other_text_gets_the_short_hint(): void
    {
        $this->sendUpdate($this->update(['text' => 'salom']))->assertNoContent();

        Http::assertSent(fn (Request $request) => str_contains($request['text'], 'Buyurtma berish uchun'));
    }

    public function test_the_reply_uses_the_language_from_telegram(): void
    {
        $this->sendUpdate($this->update(['from' => ['language_code' => 'ru']]))->assertNoContent();

        Http::assertSent(fn (Request $request) => str_contains($request['text'], 'Здравствуйте'));
    }

    /**
     * Ilovada til tanlagan odam Telegram interfeysi boshqa tilda boʻlsa ham
     * oʻzi tanlagan tilda xabar oladi.
     */
    public function test_a_saved_profile_language_wins_over_the_telegram_one(): void
    {
        $user = User::factory()->customer()->create();
        $user->forceFill(['telegram_id' => 777123, 'locale' => 'ru'])->save();

        $this->sendUpdate($this->update(['from' => ['language_code' => 'en']]))->assertNoContent();

        Http::assertSent(fn (Request $request) => str_contains($request['text'], 'Здравствуйте'));
    }

    /**
     * Qoʻllab-quvvatlanmaydigan yangilanish ham 200 qaytarishi kerak, aks
     * holda Telegram uni qayta-qayta yuboraveradi.
     */
    public function test_an_unsupported_update_is_accepted_without_a_reply(): void
    {
        $this->sendUpdate(['update_id' => 2, 'edited_message' => ['text' => 'x']])->assertNoContent();

        Http::assertNothingSent();
    }
}

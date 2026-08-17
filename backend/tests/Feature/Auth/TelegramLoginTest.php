<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramLoginTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = '123456:TEST-BOT-TOKEN';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.telegram.bot_token', self::TOKEN);
    }

    /**
     * Telegram initData'ni oʻsha bot tokeni bilan imzolab beradi — Mini App
     * bizga aynan shunday satr yuboradi.
     *
     * `$extra` orqali Telegram qoʻshadigan boshqa maydonlarni ham qoʻshish
     * mumkin; ular imzo hisobiga kiradi, chunki Telegram hash'ni oʻzi
     * yuborayotgan hamma maydon ustidan hisoblaydi.
     *
     * @param  array<string, mixed>  $user
     * @param  array<string, string>  $extra
     */
    private function initData(
        array $user = [],
        ?int $authDate = null,
        ?string $token = null,
        array $extra = [],
    ): string {
        $pairs = $extra + [
            'auth_date' => (string) ($authDate ?? now()->timestamp),
            'query_id' => 'AAF-test',
            'user' => json_encode($user + ['id' => 5551234, 'first_name' => 'Aziz'], JSON_UNESCAPED_UNICODE),
        ];

        ksort($pairs);

        $checkString = implode("\n", array_map(
            fn (string $key, string $value) => $key.'='.$value,
            array_keys($pairs),
            $pairs,
        ));

        $secret = hash_hmac('sha256', $token ?? self::TOKEN, 'WebAppData', true);
        $pairs['hash'] = hash_hmac('sha256', $checkString, $secret);

        return http_build_query($pairs);
    }

    public function test_valid_init_data_creates_a_customer_and_returns_a_token(): void
    {
        $response = $this->postJson('/api/auth/telegram', [
            'init_data' => $this->initData(['last_name' => 'Karimov', 'username' => 'aziz']),
        ]);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Aziz Karimov')
            ->assertJsonPath('user.role', UserRole::Customer->value)
            ->assertJsonPath('user.email', null);

        $this->assertNotEmpty($response->json('token'));

        $this->assertDatabaseHas('users', [
            'telegram_id' => 5551234,
            'telegram_username' => 'aziz',
            'role' => UserRole::Customer->value,
        ]);
    }

    public function test_second_login_reuses_the_same_account(): void
    {
        $this->postJson('/api/auth/telegram', ['init_data' => $this->initData()])->assertOk();

        $this->app['auth']->forgetGuards();

        $this->postJson('/api/auth/telegram', [
            'init_data' => $this->initData(['first_name' => 'Aziz', 'last_name' => 'Yangi']),
        ])->assertOk()->assertJsonPath('user.name', 'Aziz Yangi');

        $this->assertSame(1, User::where('telegram_id', 5551234)->count());
    }

    /**
     * Bot API 8.0 dan boshlab Telegram initData'ga `signature` maydonini ham
     * qoʻshadi (uchinchi tomon Ed25519 tekshiruvi uchun). U hash hisobiga
     * KIRADI: faqat `hash` chiqarib tashlanadi. Zamonaviy Telegram ilovalari
     * shu maydonni yuboradi, shuning uchun bu test aslida asosiy holat.
     */
    public function test_init_data_with_a_signature_field_is_accepted(): void
    {
        $this->postJson('/api/auth/telegram', [
            'init_data' => $this->initData(extra: ['signature' => 'ZmFrZS1lZDI1NTE5LXNpZ25hdHVyZQ']),
        ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Aziz');

        $this->assertDatabaseHas('users', ['telegram_id' => 5551234]);
    }

    public function test_tampered_signature_field_is_rejected(): void
    {
        $signed = $this->initData(extra: ['signature' => 'ZmFrZS1lZDI1NTE5LXNpZ25hdHVyZQ']);
        $forged = str_replace('ZmFrZS1lZDI1NTE5LXNpZ25hdHVyZQ', 'YnVzaGdhLXNpZ25hdHVyZS12YWx1ZQ', $signed);

        $this->postJson('/api/auth/telegram', ['init_data' => $forged])
            ->assertStatus(422)
            ->assertJsonValidationErrors('init_data');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_tampered_init_data_is_rejected(): void
    {
        $signed = $this->initData();
        // Imzo oʻsha-oʻsha, lekin foydalanuvchi boshqasiga almashtirildi.
        $forged = str_replace('Aziz', 'Boshqa', $signed);

        $this->postJson('/api/auth/telegram', ['init_data' => $forged])
            ->assertStatus(422)
            ->assertJsonValidationErrors('init_data');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_init_data_signed_with_another_token_is_rejected(): void
    {
        $this->postJson('/api/auth/telegram', [
            'init_data' => $this->initData(token: '999:OTHER-BOT'),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('init_data');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_stale_init_data_is_rejected(): void
    {
        config()->set('fastfoody.telegram.max_auth_age_minutes', 60);

        $this->postJson('/api/auth/telegram', [
            'init_data' => $this->initData(authDate: now()->subHours(3)->timestamp),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('init_data');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_staff_account_keeps_its_role_and_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create();

        $staff = User::factory()->create(['name' => 'Eski ism']);
        $staff->role = UserRole::RestaurantStaff;
        $staff->restaurant_id = $restaurant->id;
        $staff->telegram_id = 5551234;
        $staff->save();

        $this->postJson('/api/auth/telegram', ['init_data' => $this->initData()])
            ->assertOk()
            ->assertJsonPath('user.role', UserRole::RestaurantStaff->value)
            ->assertJsonPath('user.restaurant_id', $restaurant->id);
    }

    public function test_deactivated_account_cannot_sign_in(): void
    {
        $user = User::factory()->create();
        $user->telegram_id = 5551234;
        $user->deactivated_at = now();
        $user->save();

        $this->postJson('/api/auth/telegram', ['init_data' => $this->initData()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('init_data');
    }

    public function test_telegram_account_cannot_be_signed_in_with_an_empty_password(): void
    {
        $this->postJson('/api/auth/telegram', ['init_data' => $this->initData()])->assertOk();

        $this->app['auth']->forgetGuards();

        $user = User::firstWhere('telegram_id', 5551234);
        $user->email = 'aziz@example.com';
        $user->save();

        $this->postJson('/api/auth/login', ['email' => 'aziz@example.com', 'password' => ''])
            ->assertStatus(422);
    }

    public function test_login_is_unavailable_when_the_bot_token_is_missing(): void
    {
        config()->set('services.telegram.bot_token', null);

        $this->postJson('/api/auth/telegram', ['init_data' => $this->initData()])
            ->assertStatus(503);
    }
}

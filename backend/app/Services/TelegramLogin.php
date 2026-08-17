<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Telegram Mini App ichida ochilgan sahifa bizga `initData` satrini beradi —
 * unda foydalanuvchi maʼlumotlari va bot tokeni bilan qoʻyilgan imzo bor.
 * Imzo faqat bot egasida bor token bilan tekshiriladi, shuning uchun mijoz
 * yuborgan `user` maydoniga ishonish mumkin (imzo toʻgʻri chiqsa).
 *
 * Algoritm Telegram hujjatidagidek: hash'dan boshqa hamma juftlik alifbo
 * boʻyicha tartiblanadi, "\n" bilan ulanadi va HMAC-SHA256 hisoblanadi.
 */
class TelegramLogin
{
    /**
     * initData'ni tekshirib, foydalanuvchi hisobini qaytaradi. Hisob boʻlmasa
     * yangi mijoz hisobi ochiladi.
     */
    public function authenticate(string $initData): User
    {
        $profile = $this->verify($initData);

        $user = User::firstWhere('telegram_id', $profile['id']);

        if ($user === null) {
            $user = new User;
            $user->telegram_id = $profile['id'];
            // Rol hech qachon Telegram'dan olinmaydi: yangi hisob doim mijoz.
            $user->role = UserRole::Customer;
            // Telegram tilini boshlangʻich til sifatida olamiz; keyin
            // foydalanuvchi profilidan oʻzgartira oladi.
            $user->locale = $this->locale($profile['language_code']);
        }

        $user->name = $profile['name'];
        $user->telegram_username = $profile['username'];
        $user->save();

        if ($user->isDeactivated()) {
            throw ValidationException::withMessages([
                'init_data' => [__('Bu hisob faolsizlantirilgan. Tizim egasiga murojaat qiling.')],
            ]);
        }

        return $user;
    }

    /**
     * Imzoni tekshiradi va Telegram bergan profilni qaytaradi.
     *
     * @return array{id: int, name: string, username: ?string, language_code: ?string}
     */
    public function verify(string $initData): array
    {
        $token = (string) config('services.telegram.bot_token');

        if ($token === '') {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN sozlanmagan.');
        }

        $pairs = $this->parse($initData);
        $hash = $pairs['hash'] ?? null;

        // Faqat `hash` chiqariladi. Bot API 8.0 dan beri Telegram initData'ga
        // `signature` maydonini ham qoʻshadi — u uchinchi tomon Ed25519
        // tekshiruvi uchun, lekin hash oʻsha maydon bilan birga hisoblanadi.
        // Uni bu yerda chiqarib tashlash data-check-string'ni oʻzgartiradi va
        // imzo zamonaviy Telegram ilovalarining hech birida mos kelmaydi.
        unset($pairs['hash']);

        if (! is_string($hash) || $pairs === []) {
            $this->reject();
        }

        ksort($pairs);

        $checkString = implode("\n", array_map(
            fn (string $key, string $value) => $key.'='.$value,
            array_keys($pairs),
            $pairs,
        ));

        $secret = hash_hmac('sha256', $token, 'WebAppData', true);
        $expected = hash_hmac('sha256', $checkString, $secret);

        if (! hash_equals($expected, $hash)) {
            $this->reject();
        }

        $this->assertFresh($pairs['auth_date'] ?? null);

        return $this->profile($pairs['user'] ?? null);
    }

    /**
     * initData — oddiy query satri. `parse_str` kalitlardagi nuqta va probelni
     * oʻzgartirib yuborgani uchun qoʻlda ajratamiz.
     *
     * @return array<string, string>
     */
    private function parse(string $initData): array
    {
        $pairs = [];

        foreach (explode('&', $initData) as $chunk) {
            if ($chunk === '') {
                continue;
            }

            $parts = explode('=', $chunk, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $pairs[urldecode($parts[0])] = urldecode($parts[1]);
        }

        return $pairs;
    }

    /**
     * Eski initData qayta ishlatilmasin: Telegram uni yopilgan oynadan keyin
     * ham qaytarib berishi mumkin.
     */
    private function assertFresh(?string $authDate): void
    {
        if ($authDate === null || ! ctype_digit($authDate)) {
            $this->reject();
        }

        $signedAt = CarbonImmutable::createFromTimestampUTC((int) $authDate);
        $maxAge = (int) config('fastfoody.telegram.max_auth_age_minutes');

        if ($signedAt->addMinutes($maxAge)->isPast()) {
            throw ValidationException::withMessages([
                'init_data' => [__('Telegram seansi eskirgan. Ilovani qayta oching.')],
            ]);
        }
    }

    /**
     * @return array{id: int, name: string, username: ?string, language_code: ?string}
     */
    private function profile(?string $userJson): array
    {
        $user = json_decode((string) $userJson, true);

        if (! is_array($user) || ! isset($user['id']) || ! is_int($user['id'])) {
            $this->reject();
        }

        $name = trim(implode(' ', array_filter([
            $user['first_name'] ?? null,
            $user['last_name'] ?? null,
        ], fn ($part) => is_string($part) && $part !== '')));

        if ($name === '') {
            $name = is_string($user['username'] ?? null) && $user['username'] !== ''
                ? $user['username']
                : __('Telegram foydalanuvchisi');
        }

        return [
            'id' => $user['id'],
            'name' => mb_substr($name, 0, 255),
            'username' => is_string($user['username'] ?? null) ? $user['username'] : null,
            'language_code' => is_string($user['language_code'] ?? null) ? $user['language_code'] : null,
        ];
    }

    /**
     * Telegram tili ilova tillariga solishtiriladi ("en-GB" ham "en" boʻladi).
     * Mos til topilmasa NULL qaytadi va til `Accept-Language` yoki sukut
     * boʻyicha aniqlanadi.
     */
    private function locale(?string $languageCode): ?string
    {
        if ($languageCode === null) {
            return null;
        }

        $code = strtolower(str_replace('-', '_', $languageCode));

        foreach ((array) config('fastfoody.locales') as $locale) {
            if ($code === strtolower($locale) || str_starts_with($code, strtolower($locale).'_')) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * Imzo notoʻgʻri boʻlsa sababi aytilmaydi — buzgʻunchiga qaysi qadamda
     * xato qilgani haqida maʼlumot bermaymiz.
     */
    private function reject(): never
    {
        throw ValidationException::withMessages([
            'init_data' => [__('Telegram maʼlumotlari tasdiqlanmadi.')],
        ]);
    }
}

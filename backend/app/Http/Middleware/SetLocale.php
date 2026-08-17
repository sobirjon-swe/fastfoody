<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Javob tilini tanlaydi.
 *
 * Tartib: foydalanuvchi profilida saqlangan til → soʻrovdagi `Accept-Language`
 * → sukut boʻyicha til. Shu sababli hech narsa tanlamagan mijoz ham brauzeri
 * yoki Telegram'i qaysi tilda boʻlsa, shu tilda javob oladi.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = (array) config('fastfoody.locales');

        $locale = $this->fromUser($request, $supported)
            ?? $this->fromHeader($request, $supported);

        if ($locale !== null) {
            app()->setLocale($locale);
        }

        return $next($request);
    }

    /**
     * @param  array<int, string>  $supported
     */
    private function fromUser(Request $request, array $supported): ?string
    {
        // Sanctum tokeni bu middleware'dan keyin ham hal qilinishi mumkin,
        // shuning uchun foydalanuvchi boʻlmasa jimgina keyingi manbaga oʻtamiz.
        $locale = $request->user()?->locale;

        return is_string($locale) && in_array($locale, $supported, true) ? $locale : null;
    }

    /**
     * `Accept-Language: uz-Cyrl,en;q=0.8` kabi sarlavhadan mos tilni topadi.
     *
     * @param  array<int, string>  $supported
     */
    private function fromHeader(Request $request, array $supported): ?string
    {
        foreach ($request->getLanguages() as $language) {
            foreach ($supported as $locale) {
                if (strcasecmp(str_replace('-', '_', $language), $locale) === 0) {
                    return $locale;
                }
            }
        }

        return null;
    }
}

<?php

namespace App\Http\Requests\Staff\Concerns;

/**
 * Taom tarjimalari uchun umumiy qoidalar.
 *
 * Tarjima **majburiy emas**: xodim bitta tilda ishlayversa ham boʻladi, lekin
 * toʻldirsa mijoz oʻz tilida koʻradi. Asosiy til (`APP_LOCALE`) bu yerda
 * qabul qilinmaydi — u `name`/`description` maydonlarida turadi.
 */
trait TranslationRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function translationRules(): array
    {
        $rules = ['translations' => ['nullable', 'array']];

        foreach ((array) config('fastfoody.locales') as $locale) {
            if ($locale === config('app.locale')) {
                // Asosiy til `name`/`description` maydonlarida turadi; uni
                // tarjimalar orasiga yozish jimgina yoʻqolib ketmasin.
                $rules["translations.{$locale}"] = ['prohibited'];

                continue;
            }

            $rules["translations.{$locale}"] = ['nullable', 'array'];
            $rules["translations.{$locale}.name"] = ['nullable', 'string', 'max:255'];
            $rules["translations.{$locale}.description"] = ['nullable', 'string', 'max:500'];
            $rules["translations.{$locale}.category"] = ['nullable', 'string', 'max:60'];
        }

        return $rules;
    }
}

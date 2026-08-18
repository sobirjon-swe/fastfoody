<?php

namespace App\Models\Concerns;

/**
 * Nomi va tavsifi bir nechta tilda saqlanadigan modellar uchun.
 *
 * Asosiy til — oshxona kiritgan matnning oʻzi (`name`, `description`, ...).
 * Qolgan tillar `translations` JSON ustunida; tarjima yoʻq yoki boʻsh boʻlsa
 * asl matn qaytadi, shuning uchun mijoz hech qachon boʻsh joy koʻrmaydi.
 */
trait HasTranslations
{
    public function translated(string $field, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        $translations = $this->translations ?? [];
        $value = $translations[$locale][$field] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : $this->{$field};
    }

    /**
     * Faqat toʻldirilgan tarjimalarni saqlaydi: boʻsh maydonlar bazaga
     * yozilmaydi, aks holda «tarjima bor, lekin boʻsh» holati paydo boʻlardi.
     *
     * @param  array<string, array<string, string|null>>  $input
     * @return array<string, array<string, string>>|null
     */
    public static function cleanTranslations(array $input): ?array
    {
        $clean = [];

        foreach ($input as $locale => $fields) {
            if (! is_array($fields)) {
                continue;
            }

            $values = array_filter(
                array_map(fn ($value) => is_string($value) ? trim($value) : null, $fields),
                fn ($value) => $value !== null && $value !== '',
            );

            if ($values !== []) {
                $clean[$locale] = $values;
            }
        }

        return $clean === [] ? null : $clean;
    }
}

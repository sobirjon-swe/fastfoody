<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Modifikator guruhlarining yakuniy holati.
 *
 * `min_select`/`max_select` guruh turini belgilaydi: majburiy yoki ixtiyoriy,
 * bitta yoki bir nechta tanlovli.
 */
class SyncOptionGroupsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'groups' => ['present', 'array', 'max:10'],
            'groups.*.id' => ['nullable', 'integer'],
            'groups.*.name' => ['required', 'string', 'max:120'],
            'groups.*.min_select' => ['required', 'integer', 'min:0', 'max:20'],
            'groups.*.max_select' => ['nullable', 'integer', 'min:1', 'max:20', 'gte:groups.*.min_select'],
            'groups.*.options' => ['required', 'array', 'min:1', 'max:20'],
            'groups.*.options.*.id' => ['nullable', 'integer'],
            'groups.*.options.*.name' => ['required', 'string', 'max:120'],
            'groups.*.options.*.price_delta' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'groups.*.options.*.prep_delta_minutes' => ['required', 'integer', 'min:0', 'max:600'],
            'groups.*.options.*.is_available' => ['boolean'],
        ];

        foreach ((array) config('fastfoody.locales') as $locale) {
            if ($locale === config('app.locale')) {
                $rules["groups.*.translations.{$locale}"] = ['prohibited'];
                $rules["groups.*.options.*.translations.{$locale}"] = ['prohibited'];

                continue;
            }

            $rules["groups.*.translations.{$locale}.name"] = ['nullable', 'string', 'max:120'];
            $rules["groups.*.options.*.translations.{$locale}.name"] = ['nullable', 'string', 'max:120'];
        }

        return $rules;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function after(): array
    {
        return [
            function ($validator) {
                foreach ((array) $this->input('groups', []) as $index => $group) {
                    $min = (int) ($group['min_select'] ?? 0);
                    $count = is_array($group['options'] ?? null) ? count($group['options']) : 0;

                    // Majburiy guruhda variant soni talabdan kam boʻlsa,
                    // mijoz buyurtmani hech qachon yakunlay olmaydi.
                    if ($min > $count) {
                        $validator->errors()->add(
                            "groups.{$index}.min_select",
                            __('Majburiy tanlov variantlar sonidan koʻp boʻlmasligi kerak.'),
                        );
                    }
                }
            },
        ];
    }
}

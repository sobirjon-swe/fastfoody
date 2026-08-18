<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\Staff\Concerns\TranslationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuItemRequest extends FormRequest
{
    use TranslationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * The name is unique per restaurant only — two different restaurants may
     * both sell a "Lavash".
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('menu_items', 'name')
                    ->where('restaurant_id', $this->user()->restaurant_id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:60'],
            // Rasm alohida soʻrov bilan yuklanadi (multipart), shu sababli bu
            // yerda faqat uni oʻchirish tanlovi bor.
            'remove_image' => ['boolean'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'base_prep_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'extra_prep_minutes' => ['required', 'integer', 'min:0', 'max:600'],
            'is_available' => ['boolean'],
        ] + $this->translationRules();
    }
}

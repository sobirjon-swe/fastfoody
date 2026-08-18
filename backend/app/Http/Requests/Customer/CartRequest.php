<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared shape of a cart: the same rules guard the estimate and the real order,
 * so a customer can never be quoted a time for a cart that would be refused.
 */
abstract class CartRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Only ids and quantities are accepted; prices and preparation times are
     * read from the menu on the server, never from the cart.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.menu_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            // Modifikatorlar: faqat identifikatorlar keladi, narx va vaqt
            // menyudan oʻqiladi (9-bosqich).
            'items.*.option_ids' => ['nullable', 'array', 'max:20'],
            'items.*.option_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items' => __('Savatcha'),
            'items.*.menu_item_id' => __('Taom'),
            'items.*.quantity' => __('Miqdor'),
            'items.*.option_ids' => __('Modifikatorlar'),
        ];
    }
}

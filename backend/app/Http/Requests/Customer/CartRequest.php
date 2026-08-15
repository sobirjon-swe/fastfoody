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
        ];
    }
}

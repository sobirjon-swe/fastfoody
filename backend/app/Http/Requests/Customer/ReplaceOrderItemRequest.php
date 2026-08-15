<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceOrderItemRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'order_item_id' => ['required', 'integer'],
            'menu_item_id' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'order_item_id' => __('Tugagan taom'),
            'menu_item_id' => __('Yangi taom'),
        ];
    }
}

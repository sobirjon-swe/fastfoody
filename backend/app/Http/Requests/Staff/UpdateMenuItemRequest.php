<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuItemRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('menu_items', 'name')
                    ->where('restaurant_id', $this->user()->restaurant_id)
                    ->ignore($this->route('menuItem')),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99'],
            'base_prep_minutes' => ['sometimes', 'required', 'integer', 'min:1', 'max:600'],
            'extra_prep_minutes' => ['sometimes', 'required', 'integer', 'min:0', 'max:600'],
            'is_available' => ['sometimes', 'boolean'],
        ];
    }
}

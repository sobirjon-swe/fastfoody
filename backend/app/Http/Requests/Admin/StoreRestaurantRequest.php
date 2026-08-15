<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRestaurantRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('restaurants', 'name')],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'opens_at' => ['required', 'date_format:H:i,H:i:s'],
            'closes_at' => ['required', 'date_format:H:i,H:i:s', 'different:opens_at'],
            'is_active' => ['boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRestaurantRequest extends FormRequest
{
    /**
     * A partial update may send only one side of the working hours; the stored
     * value fills in the other one so "different" still compares real times.
     */
    protected function prepareForValidation(): void
    {
        $restaurant = $this->route('restaurant');

        if (! $restaurant) {
            return;
        }

        if ($this->has('closes_at') && ! $this->has('opens_at')) {
            $this->merge(['opens_at' => $restaurant->opens_at]);
        }

        if ($this->has('opens_at') && ! $this->has('closes_at')) {
            $this->merge(['closes_at' => $restaurant->closes_at]);
        }
    }

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
                Rule::unique('restaurants', 'name')->ignore($this->route('restaurant')),
            ],
            'address' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'opens_at' => ['sometimes', 'required', 'date_format:H:i,H:i:s'],
            'closes_at' => ['sometimes', 'required', 'date_format:H:i,H:i:s', 'different:opens_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

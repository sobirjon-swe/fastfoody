<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalisesWorkingHours;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRestaurantRequest extends FormRequest
{
    use NormalisesWorkingHours;

    protected function prepareForValidation(): void
    {
        $this->normaliseWorkingHours();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Only the fields the client actually sent are validated and written, so a
     * partial update never rewrites the other working hour.
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
            'opens_at' => [
                'sometimes', 'required', 'date_format:H:i',
                $this->differentFromWorkingHour('closes_at'),
            ],
            'closes_at' => [
                'sometimes', 'required', 'date_format:H:i',
                $this->differentFromWorkingHour('opens_at'),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

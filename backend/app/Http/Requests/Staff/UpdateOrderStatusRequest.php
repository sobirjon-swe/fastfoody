<?php

namespace App\Http\Requests\Staff;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Only statuses the kitchen owns are accepted here; whether the step is
     * legal from the order's current status is decided by the controller.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    OrderStatus::Preparing->value,
                    OrderStatus::Ready->value,
                    OrderStatus::PickedUp->value,
                    OrderStatus::Expired->value,
                ]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['status' => __('Holat')];
    }
}

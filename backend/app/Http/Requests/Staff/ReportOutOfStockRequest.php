<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class ReportOutOfStockRequest extends FormRequest
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
            // Taom umuman tugagan boʻlsa, uni menyudan ham «mavjud emas»
            // qilib qoʻyish mumkin, shunda yangi mijozlar buyurtma bera olmaydi.
            'mark_menu_item_unavailable' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['order_item_id' => __('Taom')];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\OptionGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Xodim paneli uchun: matnlar tarjima qilinmaydi (asl matn tahrirlanadi) va
 * mavjud boʻlmagan variantlar ham koʻrinadi.
 *
 * @mixin OptionGroup
 */
class StaffOptionGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'menu_item_id' => $this->menu_item_id,
            'name' => $this->name,
            'translations' => $this->translations,
            'min_select' => $this->min_select,
            'max_select' => $this->max_select,
            'position' => $this->position,
            'options' => $this->whenLoaded('options', fn () => $this->options->map(fn ($option) => [
                'id' => $option->id,
                'name' => $option->name,
                'translations' => $option->translations,
                'price_delta' => $option->price_delta,
                'prep_delta_minutes' => $option->prep_delta_minutes,
                'is_available' => $option->is_available,
                'position' => $option->position,
            ])),
        ];
    }
}

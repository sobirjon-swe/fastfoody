<?php

namespace App\Http\Resources;

use App\Models\OptionGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mijoz koʻradigan modifikator guruhi: nomlar tarjima qilingan, faqat
 * mavjud variantlar koʻrsatiladi.
 *
 * @mixin OptionGroup
 */
class OptionGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->translated('name'),
            'min_select' => $this->min_select,
            'max_select' => $this->max_select,
            'is_required' => $this->isRequired(),
            'options' => OptionResource::collection(
                $this->whenLoaded('options', fn () => $this->options->where('is_available', true)),
            ),
        ];
    }
}

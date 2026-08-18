<?php

namespace App\Http\Resources;

use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MenuItem
 */
class MenuItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'restaurant_id' => $this->restaurant_id,
            // Mijoz oʻz tilida koʻradi; tarjima boʻlmasa asl nom qaytadi.
            'name' => $this->translated('name'),
            'description' => $this->translated('description'),
            'category' => $this->translated('category'),
            'image_url' => $this->imageUrl(),
            'price' => $this->price,
            'base_prep_minutes' => $this->base_prep_minutes,
            'extra_prep_minutes' => $this->extra_prep_minutes,
            'is_available' => $this->is_available,
            'option_groups' => OptionGroupResource::collection($this->whenLoaded('optionGroups')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Restaurant
 */
class RestaurantResource extends JsonResource
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
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'opens_at' => $this->opens_at,
            'closes_at' => $this->closes_at,
            'is_active' => $this->is_active,
            'is_open_now' => $this->isOpenAt(),
            'menu_items_count' => $this->whenCounted('menuItems'),
            'staff_count' => $this->whenCounted('staff'),
        ];
    }
}

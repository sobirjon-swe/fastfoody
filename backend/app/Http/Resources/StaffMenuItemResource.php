<?php

namespace App\Http\Resources;

use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Xodim panelidagi taom.
 *
 * Mijoz koʻradigan `MenuItemResource` dan farqi: bu yerda matnlar **tarjima
 * qilinmaydi**. Xodim asl matnni tahrirlaydi, shuning uchun ruscha ishlayotgan
 * xodimning tahrirlash oynasi asl oʻzbekcha nomni koʻrsatishi shart — aks
 * holda saqlaganda tarjima asl nom oʻrniga yozilib ketardi.
 *
 * @mixin MenuItem
 */
class StaffMenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'restaurant_id' => $this->restaurant_id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'translations' => $this->translations,
            'image_url' => $this->imageUrl(),
            'price' => $this->price,
            'base_prep_minutes' => $this->base_prep_minutes,
            'extra_prep_minutes' => $this->extra_prep_minutes,
            'is_available' => $this->is_available,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

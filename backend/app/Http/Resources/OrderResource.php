<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
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
            'restaurant' => RestaurantResource::make($this->whenLoaded('restaurant')),
            'status' => $this->status->value,
            'pickup_code' => $this->pickup_code,
            'total_price' => $this->total_price,
            'prep_minutes' => $this->prep_minutes,
            'ready_at' => $this->ready_at,
            // Toʻlanmagan buyurtma uchun jonli baho, toʻlangani uchun null.
            'estimated_ready_at' => $this->resource->estimatedReadyAt,
            'paid_at' => $this->paid_at,
            'refunded_at' => $this->refunded_at,
            'created_at' => $this->created_at,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}

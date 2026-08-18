<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
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
            'menu_item_id' => $this->menu_item_id,
            'name' => $this->translated('name'),
            'options' => $this->whenLoaded('options', fn () => $this->options->map(fn ($option) => [
                'group_name' => $option->translated('group_name'),
                'name' => $option->translated('name'),
                'price_delta' => $option->price_delta,
            ])),
            'unit_price' => $this->unit_price,
            'quantity' => $this->quantity,
            'line_total' => $this->line_total,
            'prep_minutes' => $this->prep_minutes,
            'is_out_of_stock' => $this->out_of_stock_at !== null,
        ];
    }
}

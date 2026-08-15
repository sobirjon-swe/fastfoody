<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the kitchen sees. Unlike the customer's view it carries the buyer's name
 * and phone — the staff has to hand the food to somebody — and the transitions
 * this order may still make.
 *
 * @mixin Order
 */
class StaffOrderResource extends JsonResource
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
            'status' => $this->status->value,
            'pickup_code' => $this->pickup_code,
            'next_statuses' => array_map(
                fn ($status) => $status->value,
                $this->status->nextForStaff(),
            ),
            'total_price' => $this->total_price,
            'prep_minutes' => $this->prep_minutes,
            'ready_at' => $this->ready_at,
            'paid_at' => $this->paid_at,
            'can_report_out_of_stock' => $this->status->canReportOutOfStock(),
            'created_at' => $this->created_at,
            'customer' => [
                'name' => $this->whenLoaded('customer', fn () => $this->customer->name),
                'phone' => $this->whenLoaded('customer', fn () => $this->customer->phone),
            ],
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}

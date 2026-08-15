<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => User::factory()->customer(),
            'restaurant_id' => Restaurant::factory(),
            'status' => OrderStatus::Pending,
            'total_price' => fake()->numberBetween(20, 200) * 1000,
            'prep_minutes' => fake()->numberBetween(3, 30),
            'ready_at' => null,
            'paid_at' => null,
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'paid_at' => $status === OrderStatus::Pending ? null : now(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Oddiy hisobot: bugungi va shu haftadagi buyurtmalar.
 *
 * Tushum faqat pul haqiqatan olingan buyurtmalardan hisoblanadi — bekor
 * qilingani (puli qaytarilgan) va toʻlanmay muddati oʻtgani kirmaydi.
 */
class StatisticsController extends Controller
{
    /** Tushumga kiradigan holatlar. */
    private const EARNING = [
        OrderStatus::Paid,
        OrderStatus::Preparing,
        OrderStatus::AwaitingCustomerDecision,
        OrderStatus::Ready,
        OrderStatus::PickedUp,
    ];

    /** Oshxona xodimi — faqat oʻz oshxonasi. */
    public function staff(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;

        abort_if($restaurantId === null, Response::HTTP_FORBIDDEN, __('Sizga oshxona biriktirilmagan.'));

        return response()->json([
            'statistics' => $this->forRestaurant(Restaurant::findOrFail($restaurantId)),
        ]);
    }

    /** Tizim egasi — umumiy va har bir oshxona kesimida. */
    public function admin(): JsonResponse
    {
        $restaurants = Restaurant::orderBy('name')->get();

        return response()->json([
            'statistics' => [
                'today' => $this->window(Order::query(), Carbon::today()),
                'week' => $this->window(Order::query(), Carbon::today()->subDays(6)),
                'restaurants' => $restaurants->map(fn (Restaurant $restaurant) => [
                    'id' => $restaurant->id,
                    'name' => $restaurant->name,
                    'is_active' => $restaurant->is_active,
                    ...$this->forRestaurant($restaurant),
                ]),
            ],
        ]);
    }

    /**
     * @return array{today: array<string, mixed>, week: array<string, mixed>}
     */
    private function forRestaurant(Restaurant $restaurant): array
    {
        return [
            'today' => $this->window($restaurant->orders(), Carbon::today()),
            'week' => $this->window($restaurant->orders(), Carbon::today()->subDays(6)),
        ];
    }

    /**
     * @param  Builder<Order>|HasMany<Order, Restaurant>  $query
     * @return array<string, mixed>
     */
    private function window($query, Carbon $since): array
    {
        $orders = (clone $query)->where('created_at', '>=', $since)->get([
            'status', 'total_price', 'prep_minutes',
        ]);

        $earning = $orders->filter(fn (Order $order) => in_array($order->status, self::EARNING, strict: true));

        return [
            'orders' => $orders->count(),
            'revenue' => Money::toDecimal(
                $earning->sum(fn (Order $order) => Money::toTiyin($order->total_price)),
            ),
            'cancelled' => $orders->where('status', OrderStatus::CancelledOutOfStock)->count(),
            'expired' => $orders->where('status', OrderStatus::Expired)->count(),
            'average_prep_minutes' => $earning->isEmpty()
                ? 0
                : (int) round($earning->avg('prep_minutes')),
        ];
    }
}

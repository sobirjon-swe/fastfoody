<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Restaurant;
use App\Services\OrderPlacer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A customer's own orders. Every lookup is scoped to the signed in customer,
 * so somebody else's order id resolves to 404.
 */
class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'orders' => OrderResource::collection(
                $this->query($request)->with('restaurant')->latest()->get(),
            ),
        ]);
    }

    public function store(StoreOrderRequest $request, OrderPlacer $placer): JsonResponse
    {
        $restaurant = Restaurant::findOrFail($request->integer('restaurant_id'));

        $order = $placer->place($request->user(), $restaurant, $request->validated('items'));

        return response()->json([
            'order' => OrderResource::make($order),
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, int $order): JsonResponse
    {
        return response()->json([
            'order' => OrderResource::make($this->find($request, $order)),
        ]);
    }

    /**
     * Simulated payment for the MVP: no provider is called, the order is simply
     * marked as paid and from that moment it counts towards the kitchen queue.
     */
    public function pay(Request $request, int $order): JsonResponse
    {
        $paid = $this->find($request, $order);

        if ($paid->status !== OrderStatus::Pending) {
            return response()->json([
                'message' => __('Bu buyurtma uchun toʻlov allaqachon amalga oshirilgan yoki u bekor qilingan.'),
            ], Response::HTTP_CONFLICT);
        }

        $paid->status = OrderStatus::Paid;
        $paid->paid_at = now();
        $paid->save();

        return response()->json([
            'order' => OrderResource::make($paid),
        ]);
    }

    private function find(Request $request, int $order): Order
    {
        return $this->query($request)->with('items', 'restaurant')->findOrFail($order);
    }

    /**
     * @return Builder<Order>
     */
    private function query(Request $request): Builder
    {
        return Order::where('customer_id', $request->user()->id);
    }
}

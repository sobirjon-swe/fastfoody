<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\EstimateOrderRequest;
use App\Http\Requests\Customer\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Restaurant;
use App\Services\KitchenQueue;
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
    public function __construct(private readonly KitchenQueue $queue) {}

    public function index(Request $request): JsonResponse
    {
        $orders = $this->query($request)->with('restaurant')->latest()->get();

        $orders->each(fn (Order $order) => $this->attachEstimate($order));

        return response()->json([
            'orders' => OrderResource::collection($orders),
        ]);
    }

    /**
     * Tayyor boʻlish vaqti toʻlovdan oldin koʻrsatiladi: mijoz «bu menga mos
     * keladimi» deb oʻzi qaror qiladi. Hech narsa saqlanmaydi.
     */
    public function estimate(EstimateOrderRequest $request, OrderPlacer $placer): JsonResponse
    {
        $restaurant = Restaurant::findOrFail($request->integer('restaurant_id'));
        $cart = $placer->preview($restaurant, $request->validated('items'));
        $estimate = $this->queue->estimate($restaurant, $cart['prep_minutes']);

        return response()->json([
            'estimate' => [
                'total_price' => $cart['total_price'],
                'prep_minutes' => $estimate['prep_minutes'],
                'queue_minutes' => $estimate['queue_minutes'],
                'ready_at' => $estimate['ready_at'],
            ],
        ]);
    }

    public function store(StoreOrderRequest $request, OrderPlacer $placer): JsonResponse
    {
        $restaurant = Restaurant::findOrFail($request->integer('restaurant_id'));

        $order = $placer->place($request->user(), $restaurant, $request->validated('items'));

        return response()->json([
            'order' => OrderResource::make($this->attachEstimate($order)),
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, int $order): JsonResponse
    {
        return response()->json([
            'order' => OrderResource::make($this->attachEstimate($this->find($request, $order))),
        ]);
    }

    /**
     * Simulated payment for the MVP: no provider is called. Paying is also the
     * moment the order enters the kitchen queue and its ready time is fixed —
     * an unpaid cart must not hold up anybody else's food.
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
        $this->queue->schedule($paid);
        $paid->save();

        return response()->json([
            'order' => OrderResource::make($paid),
        ]);
    }

    /**
     * A pending order holds no place in the queue, so its ready time is a live
     * estimate that is recomputed on every read.
     */
    private function attachEstimate(Order $order): Order
    {
        if ($order->status === OrderStatus::Pending) {
            $order->estimatedReadyAt = $this->queue
                ->estimate($order->restaurant, $order->prep_minutes)['ready_at'];
        }

        return $order;
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

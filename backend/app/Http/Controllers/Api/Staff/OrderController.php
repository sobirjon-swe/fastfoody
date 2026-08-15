<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ReportOutOfStockRequest;
use App\Http\Requests\Staff\UpdateOrderStatusRequest;
use App\Http\Resources\StaffOrderResource;
use App\Models\Order;
use App\Services\OutOfStockFlow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Oshxona paneli: kelgan buyurtmalar va ularning holati.
 *
 * Har bir soʻrov xodimning oʻz oshxonasi bilan chegaralangan, shu sababli
 * boshqa oshxonaning buyurtmasi 404 boʻlib qaytadi.
 */
class OrderController extends Controller
{
    /**
     * Ish taxtasi: sukut boʻyicha faqat eʼtibor talab qiladigan buyurtmalar
     * (toʻlangan, tayyorlanayotgan, tayyor), eng eskisi birinchi — oshxona
     * navbat tartibida ishlaydi.
     */
    public function index(Request $request): JsonResponse
    {
        $statuses = $request->filled('status')
            ? [OrderStatus::tryFrom($request->string('status')->toString())]
            : OrderStatus::board();

        $orders = $this->query($request)
            ->whereIn('status', array_filter($statuses))
            ->with('items', 'customer')
            ->orderBy('paid_at')
            ->orderBy('id')
            ->paginate(perPage: 30)
            ->withQueryString();

        return response()->json([
            'orders' => StaffOrderResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, int $order): JsonResponse
    {
        return response()->json([
            'order' => StaffOrderResource::make($this->find($request, $order)),
        ]);
    }

    /**
     * Holatni bir qadam oldinga suradi. Ruxsat etilmagan sakrash (masalan
     * «tayyor» dan «toʻlangan» ga qaytish) 422 bilan rad etiladi.
     */
    public function update(UpdateOrderStatusRequest $request, int $order): JsonResponse
    {
        $found = $this->find($request, $order);
        $next = OrderStatus::from($request->validated('status'));

        if (! $found->status->canBeMovedByStaffTo($next)) {
            return response()->json([
                'message' => __('«:from» holatidan «:to» holatiga oʻtib boʻlmaydi.', [
                    'from' => $found->status->value,
                    'to' => $next->value,
                ]),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $found->status = $next;
        $found->save();

        return response()->json([
            'order' => StaffOrderResource::make($found),
        ]);
    }

    /**
     * «Mahsulot tugadi»: buyurtma navbatdan chiqadi va mijozning qarorini
     * kutadi (almashtirish yoki pul qaytarish).
     */
    public function reportOutOfStock(
        ReportOutOfStockRequest $request,
        int $order,
        OutOfStockFlow $flow,
    ): JsonResponse {
        $found = $this->find($request, $order);

        $item = $found->items->firstWhere('id', $request->integer('order_item_id'));

        abort_if($item === null, Response::HTTP_NOT_FOUND);

        $updated = $flow->report($found, $item, $request->boolean('mark_menu_item_unavailable'));

        return response()->json(['order' => StaffOrderResource::make($updated)]);
    }

    private function find(Request $request, int $order): Order
    {
        return $this->query($request)->with('items', 'customer')->findOrFail($order);
    }

    /**
     * @return Builder<Order>
     */
    private function query(Request $request): Builder
    {
        $restaurantId = $request->user()->restaurant_id;

        abort_if($restaurantId === null, Response::HTTP_FORBIDDEN, __('Sizga oshxona biriktirilmagan.'));

        return Order::where('restaurant_id', $restaurantId);
    }
}

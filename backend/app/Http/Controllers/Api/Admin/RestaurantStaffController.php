<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRestaurantStaffRequest;
use App\Http\Resources\UserResource;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff accounts cannot be self-registered, so the super admin creates them
 * here and they are bound to this restaurant from the start.
 */
class RestaurantStaffController extends Controller
{
    public function index(Restaurant $restaurant): JsonResponse
    {
        return response()->json([
            'staff' => UserResource::collection($restaurant->staff()->orderBy('name')->get()),
        ]);
    }

    /**
     * Xodim hisobi oʻchirilmaydi — faolsizlantiriladi va barcha tokenlari bekor
     * qilinadi, shunda u darhol tizimdan chiqib qoladi, tarix esa saqlanadi.
     */
    public function destroy(Restaurant $restaurant, User $staff): JsonResponse
    {
        $this->assertBelongsTo($restaurant, $staff);

        $staff->deactivated_at = now();
        $staff->save();
        $staff->tokens()->delete();

        return response()->json(['user' => UserResource::make($staff)]);
    }

    public function restore(Restaurant $restaurant, User $staff): JsonResponse
    {
        $this->assertBelongsTo($restaurant, $staff);

        $staff->deactivated_at = null;
        $staff->save();

        return response()->json(['user' => UserResource::make($staff)]);
    }

    private function assertBelongsTo(Restaurant $restaurant, User $staff): void
    {
        abort_unless(
            $staff->isRestaurantStaff() && $staff->restaurant_id === $restaurant->id,
            Response::HTTP_NOT_FOUND,
        );
    }

    public function store(StoreRestaurantStaffRequest $request, Restaurant $restaurant): JsonResponse
    {
        $staff = new User($request->safe()->only('name', 'email', 'phone', 'password'));
        $staff->role = UserRole::RestaurantStaff;
        $staff->restaurant_id = $restaurant->id;
        $staff->save();

        return response()->json([
            'user' => UserResource::make($staff),
        ], Response::HTTP_CREATED);
    }
}

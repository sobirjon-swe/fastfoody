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

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRestaurantRequest;
use App\Http\Requests\Admin\UpdateRestaurantRequest;
use App\Http\Resources\RestaurantResource;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restaurant management for the super admin. Restaurants are never deleted —
 * they are deactivated, so their history stays intact.
 */
class RestaurantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $restaurants = Restaurant::query()
            ->withCount(['menuItems', 'staff'])
            ->when($request->filled('q'), function ($query) use ($request) {
                // "%" and "_" typed by the admin are literal characters, not
                // wildcards, so they are escaped before building the pattern.
                $term = addcslashes($request->string('q')->trim()->toString(), '%_\\');
                // Both sides are lowered so the search stays case-insensitive on
                // PostgreSQL, where LIKE — unlike MySQL's default collation — is
                // case-sensitive. Backslash is the default LIKE escape on both.
                $pattern = mb_strtolower("%{$term}%");

                $query->where(fn ($q) => $q
                    ->whereRaw('LOWER(name) LIKE ? ESCAPE ?', [$pattern, '\\'])
                    ->orWhereRaw('LOWER(address) LIKE ? ESCAPE ?', [$pattern, '\\']));
            })
            // Anything other than the two known values means "no filter", so a
            // mistyped parameter cannot quietly answer with the inactive list.
            ->when(
                in_array($request->string('status')->toString(), ['active', 'inactive'], true),
                fn ($query) => $query->where('is_active', $request->string('status')->toString() === 'active'),
            )
            ->orderBy('name')
            ->paginate(perPage: 15)
            ->withQueryString();

        return response()->json([
            'data' => RestaurantResource::collection($restaurants->items()),
            'meta' => [
                'current_page' => $restaurants->currentPage(),
                'last_page' => $restaurants->lastPage(),
                'per_page' => $restaurants->perPage(),
                'total' => $restaurants->total(),
            ],
        ]);
    }

    public function store(StoreRestaurantRequest $request): JsonResponse
    {
        $restaurant = Restaurant::create($request->validated());

        // Pick up column defaults (is_active) that the request did not set.
        $restaurant->refresh();

        return response()->json([
            'restaurant' => RestaurantResource::make($restaurant->loadCount(['menuItems', 'staff'])),
        ], Response::HTTP_CREATED);
    }

    public function show(Restaurant $restaurant): JsonResponse
    {
        return response()->json([
            'restaurant' => RestaurantResource::make($restaurant->loadCount(['menuItems', 'staff'])),
        ]);
    }

    public function update(UpdateRestaurantRequest $request, Restaurant $restaurant): JsonResponse
    {
        $restaurant->update($request->validated());

        return response()->json([
            'restaurant' => RestaurantResource::make($restaurant->loadCount(['menuItems', 'staff'])),
        ]);
    }
}

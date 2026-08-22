<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuItemResource;
use App\Http\Resources\RestaurantResource;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a customer may browse: active restaurants and the items they are
 * actually selling right now. An inactive restaurant is answered with 404 — to
 * a customer it simply does not exist.
 */
class RestaurantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $restaurants = Restaurant::query()
            ->active()
            ->withCount(['menuItems' => fn ($query) => $query->available()])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = addcslashes($request->string('q')->trim()->toString(), '%_\\');
                // Lowered on both sides so the search behaves the same on
                // PostgreSQL, where LIKE is case-sensitive.
                $pattern = mb_strtolower("%{$term}%");

                $query->where(fn ($q) => $q
                    ->whereRaw('LOWER(name) LIKE ? ESCAPE ?', [$pattern, '\\'])
                    ->orWhereRaw('LOWER(address) LIKE ? ESCAPE ?', [$pattern, '\\']));
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'restaurants' => RestaurantResource::collection($restaurants),
        ]);
    }

    public function show(Restaurant $restaurant): JsonResponse
    {
        abort_unless($restaurant->is_active, Response::HTTP_NOT_FOUND);

        return response()->json([
            'restaurant' => RestaurantResource::make($restaurant),
            'menu_items' => MenuItemResource::collection(
                $restaurant->menuItems()->available()->with('optionGroups.options')->orderBy('name')->get(),
            ),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreMenuItemRequest;
use App\Http\Requests\Staff\UpdateMenuItemRequest;
use App\Http\Requests\Staff\UploadMenuItemImageRequest;
use App\Http\Resources\MenuItemResource;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menu management for restaurant staff.
 *
 * Every lookup goes through query(), which is bound to the signed in staff
 * member's own restaurant. Another restaurant's item is therefore answered with
 * 404 instead of 403 — the API never confirms that it exists.
 */
class MenuItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'menu_items' => MenuItemResource::collection(
                $this->query($request)->orderBy('name')->get(),
            ),
        ]);
    }

    public function store(StoreMenuItemRequest $request): JsonResponse
    {
        $menuItem = new MenuItem($request->validated());
        $menuItem->restaurant_id = $this->restaurantId($request);
        $menuItem->save();

        // Pick up column defaults (is_available) that the request did not set.
        $menuItem->refresh();

        return response()->json([
            'menu_item' => MenuItemResource::make($menuItem),
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, int $menuItem): JsonResponse
    {
        return response()->json([
            'menu_item' => MenuItemResource::make($this->find($request, $menuItem)),
        ]);
    }

    public function update(UpdateMenuItemRequest $request, int $menuItem): JsonResponse
    {
        $item = $this->find($request, $menuItem);
        $item->update($request->validated());

        return response()->json([
            'menu_item' => MenuItemResource::make($item),
        ]);
    }

    public function destroy(Request $request, int $menuItem): Response
    {
        $item = $this->find($request, $menuItem);

        $this->deleteImage($item);
        $item->delete();

        return response()->noContent();
    }

    /**
     * Rasm alohida (multipart) soʻrov bilan yuklanadi. Eski rasm oʻrniga
     * yangisi kelsa, eskisi diskdan oʻchiriladi — ular yigʻilib qolmasin.
     */
    public function uploadImage(
        UploadMenuItemImageRequest $request,
        int $menuItem,
    ): JsonResponse {
        $item = $this->find($request, $menuItem);

        $this->deleteImage($item);

        $item->image_path = $request->file('image')->store("menu-items/{$item->restaurant_id}", 'public');
        $item->save();

        return response()->json(['menu_item' => MenuItemResource::make($item)]);
    }

    public function destroyImage(Request $request, int $menuItem): JsonResponse
    {
        $item = $this->find($request, $menuItem);

        $this->deleteImage($item);
        $item->image_path = null;
        $item->save();

        return response()->json(['menu_item' => MenuItemResource::make($item)]);
    }

    private function deleteImage(MenuItem $item): void
    {
        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }
    }

    private function find(Request $request, int $menuItem): MenuItem
    {
        return $this->query($request)->findOrFail($menuItem);
    }

    /**
     * @return Builder<MenuItem>
     */
    private function query(Request $request): Builder
    {
        return MenuItem::where('restaurant_id', $this->restaurantId($request));
    }

    /**
     * A staff account with no restaurant (its restaurant was removed) must not
     * fall back to "all restaurants", so the request is refused outright.
     */
    private function restaurantId(Request $request): int
    {
        $restaurantId = $request->user()->restaurant_id;

        abort_if($restaurantId === null, Response::HTTP_FORBIDDEN, 'Sizga oshxona biriktirilmagan.');

        return $restaurantId;
    }
}

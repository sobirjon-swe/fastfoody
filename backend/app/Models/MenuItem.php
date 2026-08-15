<?php

namespace App\Models;

use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * restaurant_id is not fillable: an item always belongs to the restaurant of
 * the staff member creating it, never to a restaurant named in the request.
 */
#[Fillable(['name', 'description', 'category', 'price', 'base_prep_minutes', 'extra_prep_minutes', 'is_available'])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'base_prep_minutes' => 'integer',
            'extra_prep_minutes' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    /**
     * Rasmning toʻliq manzili; rasm yuklanmagan boʻlsa null.
     */
    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Minutes this item needs on its own: the base time for the first portion
     * plus the extra time for every additional portion.
     */
    public function prepMinutesFor(int $quantity): int
    {
        if ($quantity < 1) {
            return 0;
        }

        return $this->base_prep_minutes + $this->extra_prep_minutes * ($quantity - 1);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_available', true);
    }
}

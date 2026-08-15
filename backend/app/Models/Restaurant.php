<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\RestaurantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'address', 'phone', 'opens_at', 'closes_at', 'is_active'])]
class Restaurant extends Model
{
    /** @use HasFactory<RestaurantFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Working hours are stored as SQL time values but exchanged with the API as
     * "HH:MM", so both sides always see the same shape.
     *
     * @return Attribute<string, string>
     */
    protected function opensAt(): Attribute
    {
        return self::timeAttribute();
    }

    /**
     * @return Attribute<string, string>
     */
    protected function closesAt(): Attribute
    {
        return self::timeAttribute();
    }

    /**
     * @return Attribute<string, string>
     */
    private static function timeAttribute(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => substr($value, 0, 5),
            set: fn (string $value) => strlen($value) === 5 ? $value.':00' : $value,
        );
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function staff(): HasMany
    {
        return $this->users()->where('role', UserRole::RestaurantStaff);
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

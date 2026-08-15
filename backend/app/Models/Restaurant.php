<?php

namespace App\Models;

use App\Enums\UserRole;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
     * Oshxona shu daqiqada buyurtma qabul qiladimi.
     *
     * Ish vaqti mahalliy soatda yoziladi, vaqtlar esa UTC'da saqlanadi, shuning
     * uchun solishtirishdan oldin mahalliy mintaqaga oʻtkaziladi. Yarim tundan
     * oshadigan ish vaqti (masalan 10:00–02:00) ham toʻgʻri ishlaydi.
     */
    public function isOpenAt(?CarbonInterface $moment = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = ($moment ?? CarbonImmutable::now())
            ->setTimezone(config('fastfoody.timezone'))
            ->format('H:i');

        return $this->opens_at <= $this->closes_at
            ? $now >= $this->opens_at && $now < $this->closes_at
            : $now >= $this->opens_at || $now < $this->closes_at;
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Taomga beriladigan bitta savol: «Oʻlcham», «Sous», «Qoʻshimchalar».
 *
 * menu_item_id fillable emas — guruh doim tahrirlanayotgan taomga tegishli.
 */
#[Fillable(['name', 'min_select', 'max_select', 'position'])]
class OptionGroup extends Model
{
    use HasTranslations;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'min_select' => 'integer',
            'max_select' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    /**
     * @return HasMany<Option, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(Option::class)->orderBy('position')->orderBy('id');
    }

    /** Kamida bitta variant tanlanishi shartmi. */
    public function isRequired(): bool
    {
        return $this->min_select > 0;
    }
}

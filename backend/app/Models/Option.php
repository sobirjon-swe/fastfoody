<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Guruhdagi bitta javob: «Katta», «Smetana», «Mayonezsiz».
 *
 * Narx va vaqt qoʻshimchasi manfiy boʻlmaydi: biror ingredientni olib tashlash
 * narxni kamaytirmaydi — bu oshxona uchun ham, hisob-kitob uchun ham soddaroq.
 */
#[Fillable(['name', 'price_delta', 'prep_delta_minutes', 'is_available', 'position'])]
class Option extends Model
{
    use HasTranslations;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'price_delta' => 'decimal:2',
            'prep_delta_minutes' => 'integer',
            'is_available' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<OptionGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class, 'option_group_id');
    }
}

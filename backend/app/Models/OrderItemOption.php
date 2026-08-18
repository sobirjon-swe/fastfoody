<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Buyurtma qatoridagi tanlangan variant nusxasi. Nomi, narxi va vaqti
 * buyurtma paytidagi holicha yoziladi — menyu keyin oʻzgarsa ham chek
 * oʻzgarmaydi.
 */
#[Fillable(['option_id', 'group_name', 'name', 'price_delta', 'prep_delta_minutes'])]
class OrderItemOption extends Model
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
        ];
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}

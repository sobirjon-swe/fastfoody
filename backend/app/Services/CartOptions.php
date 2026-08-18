<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Option;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Savatchadagi modifikator tanlovlarini tekshiradi va narx/vaqt qoʻshimchasini
 * hisoblaydi (9-bosqich).
 *
 * Tanlov ham xuddi narx kabi **serverda tekshiriladi**: mijoz yuborgan
 * `option_ids` faqat identifikator, qolgani menyudan oʻqiladi.
 */
class CartOptions
{
    /**
     * Har bir savatcha qatori uchun tanlangan variantlarni qaytaradi.
     *
     * @param  array<int, MenuItem>  $menuItems  menu_item_id => MenuItem
     * @param  array<int, array{menu_item_id: int, option_ids?: array<int, int>}>  $cart
     * @return array<int, Collection<int, Option>> savatcha indeksi => variantlar
     *
     * @throws ValidationException
     */
    public function resolve(array $menuItems, array $cart): array
    {
        $options = $this->optionsFor($menuItems);
        $errors = [];
        $selection = [];

        foreach ($cart as $index => $line) {
            $menuItem = $menuItems[(int) $line['menu_item_id']] ?? null;

            if ($menuItem === null) {
                continue;
            }

            $chosenIds = array_values(array_unique(array_map('intval', $line['option_ids'] ?? [])));
            $chosen = $options->only($chosenIds);

            $selection[$index] = $this->check($menuItem, $chosen, $chosenIds, $index, $errors);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $selection;
    }

    /**
     * Bitta qator tanlovini tekshiradi.
     *
     * @param  Collection<int, Option>  $chosen
     * @param  array<int, int>  $chosenIds
     * @param  array<string, string>  $errors
     * @return Collection<int, Option>
     */
    private function check(
        MenuItem $menuItem,
        Collection $chosen,
        array $chosenIds,
        int $index,
        array &$errors,
    ): Collection {
        $groups = $menuItem->optionGroups;
        $groupIds = $groups->pluck('id')->all();
        $field = "items.{$index}.option_ids";

        // Begona variant: boshqa taomning yoki umuman yoʻq variantning id'si.
        if (count($chosenIds) !== $chosen->count()
            || $chosen->contains(fn (Option $option) => ! in_array($option->option_group_id, $groupIds, true))) {
            $errors[$field] = __('Tanlangan variant bu taomga tegishli emas.');

            return collect();
        }

        foreach ($chosen as $option) {
            if (! $option->is_available) {
                $errors[$field] = __(':name hozir mavjud emas.', ['name' => $option->translated('name')]);

                return collect();
            }
        }

        foreach ($groups as $group) {
            $count = $chosen->where('option_group_id', $group->id)->count();

            if ($count < $group->min_select) {
                $errors["{$field}.{$group->id}"] = __('«:group» uchun kamida :count ta variant tanlang.', [
                    'group' => $group->translated('name'),
                    'count' => $group->min_select,
                ]);
            }

            if ($group->max_select !== null && $count > $group->max_select) {
                $errors["{$field}.{$group->id}"] = __('«:group» uchun koʻpi bilan :count ta variant tanlash mumkin.', [
                    'group' => $group->translated('name'),
                    'count' => $group->max_select,
                ]);
            }
        }

        return $chosen;
    }

    /**
     * Savatchadagi taomlarning barcha variantlari, id boʻyicha.
     *
     * @param  array<int, MenuItem>  $menuItems
     * @return Collection<int, Option>
     */
    private function optionsFor(array $menuItems): Collection
    {
        $groupIds = collect($menuItems)
            ->flatMap(fn (MenuItem $item) => $item->optionGroups->pluck('id'))
            ->all();

        return Option::whereIn('option_group_id', $groupIds)->get()->keyBy('id');
    }
}

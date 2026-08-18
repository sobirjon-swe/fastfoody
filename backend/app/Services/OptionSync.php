<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Option;
use App\Models\OptionGroup;
use Illuminate\Support\Facades\DB;

/**
 * Taomning modifikator guruhlarini bitta soʻrovda yangilaydi.
 *
 * Xodim panelida guruhlar va variantlar bitta oynada tahrirlanadi, shuning
 * uchun API ham bitta amal beradi: kelgan roʻyxat — yakuniy holat. Roʻyxatda
 * yoʻq guruh yoki variant oʻchiriladi.
 *
 * Oʻchirilgan variant eski buyurtmalarni buzmaydi: `order_item_options` oʻz
 * nusxasini saqlaydi, `option_id` esa NULL boʻlib qoladi.
 */
class OptionSync
{
    /**
     * @param  array<int, array<string, mixed>>  $groups
     */
    public function sync(MenuItem $menuItem, array $groups): MenuItem
    {
        DB::transaction(function () use ($menuItem, $groups) {
            $keptGroups = [];

            foreach ($groups as $position => $input) {
                $group = $this->group($menuItem, $input);
                $group->fill([
                    'name' => $input['name'],
                    'min_select' => (int) ($input['min_select'] ?? 0),
                    'max_select' => $input['max_select'] ?? null,
                    'position' => $position,
                ]);
                $group->translations = OptionGroup::cleanTranslations((array) ($input['translations'] ?? []));
                $group->save();

                $this->syncOptions($group, (array) ($input['options'] ?? []));
                $keptGroups[] = $group->id;
            }

            $menuItem->optionGroups()->whereKeyNot($keptGroups)->delete();
        });

        return $menuItem->load('optionGroups.options');
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     */
    private function syncOptions(OptionGroup $group, array $options): void
    {
        $kept = [];

        foreach ($options as $position => $input) {
            $option = $this->option($group, $input);
            $option->fill([
                'name' => $input['name'],
                'price_delta' => $input['price_delta'] ?? 0,
                'prep_delta_minutes' => (int) ($input['prep_delta_minutes'] ?? 0),
                'is_available' => (bool) ($input['is_available'] ?? true),
                'position' => $position,
            ]);
            $option->translations = Option::cleanTranslations((array) ($input['translations'] ?? []));
            $option->save();

            $kept[] = $option->id;
        }

        $group->options()->whereKeyNot($kept)->delete();
    }

    /**
     * Mavjud guruhni faqat shu taom ichidan qidiradi: begona id yuborilsa
     * yangi guruh yaratiladi, boshqa taomniki oʻzgarmaydi.
     *
     * @param  array<string, mixed>  $input
     */
    private function group(MenuItem $menuItem, array $input): OptionGroup
    {
        $group = isset($input['id'])
            ? $menuItem->optionGroups()->find($input['id'])
            : null;

        if ($group === null) {
            $group = new OptionGroup;
            $group->menu_item_id = $menuItem->id;
        }

        return $group;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function option(OptionGroup $group, array $input): Option
    {
        $option = isset($input['id'])
            ? $group->options()->find($input['id'])
            : null;

        if ($option === null) {
            $option = new Option;
            $option->option_group_id = $group->id;
        }

        return $option;
    }
}

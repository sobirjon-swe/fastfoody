<?php

namespace Tests\Unit;

use App\Models\MenuItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins the meaning of the two prep-time columns that 3-bosqich will build the
 * ready-time calculation on: the first portion costs the base time, every
 * further portion costs the extra time.
 */
class MenuItemPrepTimeTest extends TestCase
{
    #[DataProvider('quantities')]
    public function test_it_adds_the_extra_minutes_per_additional_portion(int $quantity, int $expected): void
    {
        $item = new MenuItem(['base_prep_minutes' => 3, 'extra_prep_minutes' => 1]);

        $this->assertSame($expected, $item->prepMinutesFor($quantity));
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function quantities(): array
    {
        return [
            'zero is free' => [0, 0],
            'one portion is the base time' => [1, 3],
            'two portions add one minute' => [2, 4],
            'five portions add four minutes' => [5, 7],
        ];
    }

    public function test_an_item_without_extra_time_stays_flat(): void
    {
        $drink = new MenuItem(['base_prep_minutes' => 1, 'extra_prep_minutes' => 0]);

        $this->assertSame(1, $drink->prepMinutesFor(1));
        $this->assertSame(1, $drink->prepMinutesFor(10));
    }
}

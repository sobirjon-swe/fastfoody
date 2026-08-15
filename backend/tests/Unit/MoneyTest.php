<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[DataProvider('amounts')]
    public function test_it_converts_between_decimal_strings_and_tiyin(string $decimal, int $tiyin): void
    {
        $this->assertSame($tiyin, Money::toTiyin($decimal));
        $this->assertSame($decimal, Money::toDecimal($tiyin));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function amounts(): array
    {
        return [
            'zero' => ['0.00', 0],
            'whole so\'m' => ['32000.00', 3200000],
            'with tiyin' => ['12500.75', 1250075],
            'single tiyin' => ['0.01', 1],
            'large' => ['9999999999.99', 999999999999],
        ];
    }

    public function test_sums_stay_exact_where_floats_would_drift(): void
    {
        $total = Money::toTiyin('32000.00') * 2 + Money::toTiyin('9000.00') * 3;

        $this->assertSame('91000.00', Money::toDecimal($total));

        $drifting = 0;

        for ($i = 0; $i < 10; $i++) {
            $drifting += Money::toTiyin('0.10');
        }

        $this->assertSame('1.00', Money::toDecimal($drifting));
    }

    public function test_it_rejects_a_value_that_is_not_a_number(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::toTiyin('32 000');
    }
}

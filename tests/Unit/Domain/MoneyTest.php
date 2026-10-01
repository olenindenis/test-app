<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\EarningLine\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[Test]
    #[DataProvider('validAmounts')]
    public function it_parses_decimal_strings_into_cents(string $input, int $expectedCents): void
    {
        $this->assertSame($expectedCents, Money::fromString($input)->cents);
    }

    public static function validAmounts(): iterable
    {
        yield 'integer' => ['1000', 100000];
        yield 'two decimals' => ['1050.00', 105000];
        yield 'negative' => ['-45.55', -4555];
        yield 'explicit plus' => ['+100.10', 10010];
        yield 'one decimal' => ['0.2', 20];
        yield 'small negative' => ['-0.10', -10];
        yield 'surrounding spaces' => [' 12.34 ', 1234];
    }

    #[Test]
    #[DataProvider('invalidAmounts')]
    public function it_rejects_invalid_amounts(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromString($input);
    }

    public static function invalidAmounts(): iterable
    {
        yield 'empty' => [''];
        yield 'three decimals' => ['1.234'];
        yield 'letters' => ['abc'];
        yield 'thousands separator' => ['1,000.00'];
        yield 'currency symbol' => ['$10'];
    }

    #[Test]
    public function it_adds_amounts_without_floating_point_errors(): void
    {
        $total = Money::fromString('0.10')->add(Money::fromString('0.20'));

        $this->assertTrue($total->equals(Money::fromString('0.30')));
    }

    #[Test]
    public function it_formats_amounts_for_humans_and_machines(): void
    {
        $this->assertSame('$1,104.45', Money::fromCents(110445)->format());
        $this->assertSame("\u{2212}$45.55", Money::fromCents(-4555)->format());
        $this->assertSame('+$100.10', Money::fromCents(10010)->formatSigned());
        $this->assertSame("\u{2212}$0.20", Money::fromCents(-20)->formatSigned());
        $this->assertSame('-45.55', Money::fromCents(-4555)->toDecimalString());
        $this->assertSame('1234567.05', Money::fromCents(123456705)->toDecimalString());
        $this->assertSame('$0.00', Money::zero()->format());
    }
}

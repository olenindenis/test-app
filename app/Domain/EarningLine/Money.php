<?php

declare(strict_types=1);

namespace App\Domain\EarningLine;

use InvalidArgumentException;

final readonly class Money
{
    public const string PATTERN = '/^([+-])?(\d{1,12})(?:\.(\d{1,2}))?$/';

    private const string MINUS_SIGN = "\u{2212}";

    private function __construct(public int $cents) {}

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function fromString(string $amount): self
    {
        $amount = trim($amount);

        if (preg_match(self::PATTERN, $amount, $m) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid monetary amount.', $amount));
        }

        $cents = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return new self(($m[1] ?? '') === '-' ? -$cents : $cents);
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    public function toDecimalString(): string
    {
        return ($this->isNegative() ? '-' : '').$this->absoluteDecimal(thousands: '');
    }

    public function format(): string
    {
        return ($this->isNegative() ? self::MINUS_SIGN : '').'$'.$this->absoluteDecimal(thousands: ',');
    }

    public function formatSigned(): string
    {
        return $this->isNegative() ? $this->format() : '+'.$this->format();
    }

    private function absoluteDecimal(string $thousands): string
    {
        $abs = abs($this->cents);

        return number_format(intdiv($abs, 100), 0, '', $thousands).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }
}

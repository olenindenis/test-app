<?php

declare(strict_types=1);

namespace App\Domain\EarningLine;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

final readonly class EarningLineId
{
    private function __construct(public string $value) {}

    public static function generate(): self
    {
        return new self(Uuid::uuid7()->toString());
    }

    public static function fromString(string $value): self
    {
        if (! Uuid::isValid($value)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid earning line id.', $value));
        }

        return new self(strtolower($value));
    }
}

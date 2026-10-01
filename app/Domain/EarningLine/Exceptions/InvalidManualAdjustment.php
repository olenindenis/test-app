<?php

declare(strict_types=1);

namespace App\Domain\EarningLine\Exceptions;

use DomainException;

final class InvalidManualAdjustment extends DomainException
{
    public static function missingComment(): self
    {
        return new self('A manual adjustment requires a comment explaining why it was made.');
    }

    public static function commentTooLong(int $max): self
    {
        return new self(sprintf('The adjustment comment may not be longer than %d characters.', $max));
    }

    public static function zeroAmount(): self
    {
        return new self('A manual adjustment amount cannot be zero.');
    }

    public static function missingAuthor(): self
    {
        return new self('A manual adjustment must record who made it.');
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\EarningLine\Exceptions;

use App\Domain\EarningLine\EarningLineId;
use DomainException;

final class EarningLineNotFound extends DomainException
{
    public static function withId(EarningLineId $id): self
    {
        return new self(sprintf('Earning line "%s" does not exist.', $id->value));
    }
}

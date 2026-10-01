<?php

declare(strict_types=1);

namespace App\Infrastructure\EventStore;

use RuntimeException;
use Throwable;

final class ConcurrencyException extends RuntimeException
{
    public static function forStream(string $aggregateId, int $expectedVersion, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Earning line "%s" was modified concurrently (expected version %d). Reload and try again.', $aggregateId, $expectedVersion),
            previous: $previous,
        );
    }
}

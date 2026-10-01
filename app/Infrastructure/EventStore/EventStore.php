<?php

declare(strict_types=1);

namespace App\Infrastructure\EventStore;

interface EventStore
{
    public function append(string $aggregateId, int $expectedVersion, array $events): void;

    public function load(string $aggregateId): array;

    public function all(): iterable;
}

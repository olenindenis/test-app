<?php

declare(strict_types=1);

namespace App\Infrastructure\EventStore;

use App\Domain\Shared\DomainEvent;

interface EventStore
{
    /**
     * Appends events to an aggregate's stream.
     *
     * @param  list<DomainEvent>  $events
     *
     * @throws ConcurrencyException when the stream is no longer at $expectedVersion
     */
    public function append(string $aggregateId, int $expectedVersion, array $events): void;

    /** @return list<DomainEvent> events of one aggregate, oldest first */
    public function load(string $aggregateId): array;

    /** @return iterable<DomainEvent> every stored event in the order it was recorded */
    public function all(): iterable;
}

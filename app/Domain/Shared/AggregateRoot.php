<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Minimal event-sourced aggregate root.
 *
 * State changes happen only by recording events; the same apply() logic is
 * used both when a new event is recorded and when the aggregate is rebuilt
 * from its stored history.
 */
abstract class AggregateRoot
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private int $version = 0;

    final protected function __construct() {}

    /** @param iterable<DomainEvent> $history */
    public static function reconstitute(iterable $history): static
    {
        $aggregate = new static;

        foreach ($history as $event) {
            $aggregate->applyEvent($event);
        }

        return $aggregate;
    }

    /** Number of events applied to this aggregate (stored + not yet released). */
    public function version(): int
    {
        return $this->version;
    }

    /** Version the aggregate had when it was loaded, i.e. before the pending events. */
    public function persistedVersion(): int
    {
        return $this->version - count($this->recordedEvents);
    }

    /** @return list<DomainEvent> */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    /** @return list<DomainEvent> */
    public function pendingEvents(): array
    {
        return $this->recordedEvents;
    }

    protected function recordThat(DomainEvent $event): void
    {
        $this->applyEvent($event);
        $this->recordedEvents[] = $event;
    }

    abstract protected function apply(DomainEvent $event): void;

    private function applyEvent(DomainEvent $event): void
    {
        $this->apply($event);
        $this->version++;
    }
}

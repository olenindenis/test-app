<?php

declare(strict_types=1);

namespace App\Domain\Shared;

abstract class AggregateRoot
{
    private array $recordedEvents = [];

    private int $version = 0;

    final protected function __construct() {}

    public static function reconstitute(iterable $history): static
    {
        $aggregate = new static;

        foreach ($history as $event) {
            $aggregate->applyEvent($event);
        }

        return $aggregate;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function persistedVersion(): int
    {
        return $this->version - count($this->recordedEvents);
    }

    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

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

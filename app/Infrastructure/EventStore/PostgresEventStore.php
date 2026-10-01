<?php

declare(strict_types=1);

namespace App\Infrastructure\EventStore;

use App\Domain\Shared\DomainEvent;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class PostgresEventStore implements EventStore
{
    private const string TABLE = 'earning_line_events';

    public function __construct(
        private ConnectionInterface $db,
        private EventSerializer $serializer,
    ) {}

    public function append(string $aggregateId, int $expectedVersion, array $events): void
    {
        if ($events === []) {
            return;
        }

        $version = $expectedVersion;
        $rows = array_map(fn(DomainEvent $event): array => [
            'aggregate_id' => $aggregateId,
            'version' => ++$version,
            'event_type' => $event::eventType(),
            'payload' => $this->serializer->serialize($event),
            'occurred_at' => $event->occurredAt()->format(DomainEvent::DATE_FORMAT),
        ], $events);

        try {
            $this->db->table(self::TABLE)->insert($rows);
        } catch (UniqueConstraintViolationException $e) {
            throw ConcurrencyException::forStream($aggregateId, $expectedVersion, $e);
        }
    }

    public function load(string $aggregateId): array
    {
        return $this->db->table(self::TABLE)
            ->where('aggregate_id', $aggregateId)
            ->orderBy('version')
            ->get(['event_type', 'payload'])
            ->map(fn(object $row): DomainEvent => $this->serializer->deserialize($row->event_type, $row->payload))
            ->all();
    }

    public function all(): iterable
    {
        foreach ($this->db->table(self::TABLE)->select(['id', 'event_type', 'payload'])->lazyById(500) as $row) {
            yield $this->serializer->deserialize($row->event_type, $row->payload);
        }
    }
}

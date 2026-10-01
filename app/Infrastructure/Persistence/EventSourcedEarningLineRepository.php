<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\EarningLine\EarningLine;
use App\Domain\EarningLine\EarningLineId;
use App\Domain\EarningLine\EarningLineRepository;
use App\Domain\EarningLine\Exceptions\EarningLineNotFound;
use App\Infrastructure\EventStore\EventStore;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

/**
 * Loads an earning line by replaying its events and saves it by appending the
 * newly recorded ones. Appending and publishing happen in one transaction, so
 * the synchronous projections are always consistent with the event store.
 */
final readonly class EventSourcedEarningLineRepository implements EarningLineRepository
{
    public function __construct(
        private EventStore $eventStore,
        private Dispatcher $events,
        private ConnectionInterface $db,
    ) {}

    public function get(EarningLineId $id): EarningLine
    {
        $history = $this->eventStore->load($id->value);

        if ($history === []) {
            throw EarningLineNotFound::withId($id);
        }

        return EarningLine::reconstitute($history);
    }

    public function save(EarningLine $line): void
    {
        if ($line->pendingEvents() === []) {
            return;
        }

        $this->db->transaction(function () use ($line): void {
            $expectedVersion = $line->persistedVersion();
            $newEvents = $line->releaseEvents();

            $this->eventStore->append($line->id()->value, $expectedVersion, $newEvents);

            foreach ($newEvents as $event) {
                $this->events->dispatch($event);
            }
        });
    }
}

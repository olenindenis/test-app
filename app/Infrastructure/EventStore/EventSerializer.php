<?php

declare(strict_types=1);

namespace App\Infrastructure\EventStore;

use App\Domain\EarningLine\Events\EarningLineCalculated;
use App\Domain\EarningLine\Events\EarningLineRecalculated;
use App\Domain\EarningLine\Events\ManualAdjustmentAdded;
use App\Domain\EarningLine\Events\SystemRecalculationIgnored;
use App\Domain\Shared\DomainEvent;
use UnexpectedValueException;

/** Maps stored event types back to their PHP classes. */
final class EventSerializer
{
    /** @var array<string, class-string<DomainEvent>> */
    private array $map = [];

    /** @param list<class-string<DomainEvent>> $eventClasses */
    public function __construct(array $eventClasses = [
        EarningLineCalculated::class,
        EarningLineRecalculated::class,
        ManualAdjustmentAdded::class,
        SystemRecalculationIgnored::class,
    ])
    {
        foreach ($eventClasses as $class) {
            $this->map[$class::eventType()] = $class;
        }
    }

    public function serialize(DomainEvent $event): string
    {
        return json_encode($event->toPayload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    public function deserialize(string $eventType, string $payload): DomainEvent
    {
        $class = $this->map[$eventType] ?? throw new UnexpectedValueException("Unknown event type \"{$eventType}\".");

        return $class::fromPayload(json_decode($payload, true, flags: JSON_THROW_ON_ERROR));
    }
}

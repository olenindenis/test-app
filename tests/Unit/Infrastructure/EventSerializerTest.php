<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Domain\EarningLine\AdjustmentComment;
use App\Domain\EarningLine\EarningLine;
use App\Domain\EarningLine\EarningLineId;
use App\Domain\EarningLine\Money;
use App\Infrastructure\EventStore\EventSerializer;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class EventSerializerTest extends TestCase
{
    #[Test]
    public function every_event_survives_a_serialization_round_trip(): void
    {
        $at = new DateTimeImmutable('2026-10-01T12:34:56.789+00:00');
        $line = EarningLine::calculate(EarningLineId::generate(), 'employee-1', Money::fromString('1000'), $at);
        $line->recalculate(Money::fromString('1050'), $at);
        $line->addManualAdjustment(Money::fromString('-45.55'), AdjustmentComment::fromString('Declined benefit — ünïcødé'), 'specialist-1', $at);
        $line->recalculate(Money::fromString('1200'), $at);

        $serializer = new EventSerializer;

        foreach ($line->releaseEvents() as $event) {
            $restored = $serializer->deserialize($event::eventType(), $serializer->serialize($event));

            $this->assertEquals($event, $restored);
        }
    }

    #[Test]
    public function unknown_event_types_are_rejected(): void
    {
        $this->expectException(UnexpectedValueException::class);

        (new EventSerializer)->deserialize('something.else', '{}');
    }
}

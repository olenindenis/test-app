<?php

declare(strict_types=1);

namespace App\Domain\EarningLine\Events;

use App\Domain\EarningLine\EarningLineId;
use App\Domain\EarningLine\Money;
use App\Domain\Shared\DomainEvent;
use DateTimeImmutable;

/** The system recalculated a line that had no manual adjustments yet. */
final readonly class EarningLineRecalculated implements DomainEvent
{
    public function __construct(
        public EarningLineId $lineId,
        public Money $previousAmount,
        public Money $newAmount,
        public DateTimeImmutable $recalculatedAt,
    ) {}

    public static function eventType(): string
    {
        return 'earning_line.recalculated';
    }

    public function aggregateId(): string
    {
        return $this->lineId->value;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->recalculatedAt;
    }

    public function toPayload(): array
    {
        return [
            'line_id' => $this->lineId->value,
            'previous_amount_cents' => $this->previousAmount->cents,
            'new_amount_cents' => $this->newAmount->cents,
            'recalculated_at' => $this->recalculatedAt->format(self::DATE_FORMAT),
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            EarningLineId::fromString($payload['line_id']),
            Money::fromCents($payload['previous_amount_cents']),
            Money::fromCents($payload['new_amount_cents']),
            new DateTimeImmutable($payload['recalculated_at']),
        );
    }
}

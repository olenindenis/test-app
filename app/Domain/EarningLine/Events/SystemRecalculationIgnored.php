<?php

declare(strict_types=1);

namespace App\Domain\EarningLine\Events;

use App\Domain\EarningLine\EarningLineId;
use App\Domain\EarningLine\Money;
use App\Domain\Shared\DomainEvent;
use DateTimeImmutable;

/**
 * The system tried to recalculate a line that already has manual adjustments.
 * The attempt does not change the line's value; it is recorded for auditing only.
 */
final readonly class SystemRecalculationIgnored implements DomainEvent
{
    public function __construct(
        public EarningLineId $lineId,
        public Money $attemptedAmount,
        public DateTimeImmutable $attemptedAt,
    ) {}

    public static function eventType(): string
    {
        return 'earning_line.recalculation_ignored';
    }

    public function aggregateId(): string
    {
        return $this->lineId->value;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->attemptedAt;
    }

    public function toPayload(): array
    {
        return [
            'line_id' => $this->lineId->value,
            'attempted_amount_cents' => $this->attemptedAmount->cents,
            'attempted_at' => $this->attemptedAt->format(DATE_RFC3339_EXTENDED),
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            EarningLineId::fromString($payload['line_id']),
            Money::fromCents($payload['attempted_amount_cents']),
            new DateTimeImmutable($payload['attempted_at']),
        );
    }
}
